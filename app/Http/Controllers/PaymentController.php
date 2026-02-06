<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Post(
        path: "/api/orders/{order}/payments",
        tags: ["Payments"],
        summary: "Add payment to order",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["payment_method", "amount"],
                properties: [
                    new OA\Property(property: "payment_method", type: "string", enum: ["cash", "card", "qris", "gopay", "ovo", "dana"]),
                    new OA\Property(property: "amount", type: "number", example: 150000),
                    new OA\Property(property: "reference_number", type: "string"),
                    new OA\Property(property: "notes", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Payment processed successfully")
        ]
    )]
    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,qris,gopay,ovo,dana',
            'amount' => 'required|numeric|min:0',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order sudah dibayar',
            ], 400);
        }

        if ($order->status !== 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Order harus ditutup dulu sebelum pembayaran',
            ], 400);
        }

        $totalPaid = $order->payments()->where('status', 'completed')->sum('amount');
        $remaining = $order->total - $totalPaid;

        if ($validated['amount'] > $remaining) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah pembayaran melebihi sisa tagihan',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $payment = $order->payments()->create([
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $totalPaid += $payment->amount;

            if ($totalPaid >= $order->total) {
                $order->update(['payment_status' => 'paid']);

                if ($order->table) {
                    $order->table->markAsAvailable();
                }
            } else {
                $order->update(['payment_status' => 'partial']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil',
                'data' => [
                    'payment' => $payment,
                    'remaining' => max(0, $order->total - $totalPaid),
                    'change' => $validated['payment_method'] === 'cash'
                        ? max(0, $validated['amount'] - $remaining)
                        : 0,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/orders/{order}/payments",
        tags: ["Payments"],
        summary: "Get payment history for order",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Payment history retrieved successfully")
        ]
    )]
    public function index(Order $order)
    {
        $payments = $order->payments()->latest()->get();
        $totalPaid = $payments->where('status', 'completed')->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total_paid' => $totalPaid,
                'remaining' => max(0, $order->total - $totalPaid),
                'order_total' => $order->total,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/orders/{order}/payments/{payment}/refund",
        tags: ["Payments"],
        summary: "Refund payment",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "payment", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reason"],
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Customer request")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Payment refunded successfully")
        ]
    )]
    public function refund(Request $request, Order $order, Payment $payment)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        if ($payment->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment tidak ditemukan',
            ], 404);
        }

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembayaran completed yang bisa di-refund',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $payment->update([
                'status' => 'refunded',
                'notes' => ($payment->notes ? $payment->notes . ' | ' : '') . 'Refund: ' . $validated['reason'],
            ]);

            $totalPaid = $order->payments()->where('status', 'completed')->sum('amount');

            if ($totalPaid == 0) {
                $order->update(['payment_status' => 'unpaid']);
            } elseif ($totalPaid < $order->total) {
                $order->update(['payment_status' => 'partial']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Refund berhasil',
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Refund gagal: ' . $e->getMessage(),
            ], 500);
        }
    }
}
