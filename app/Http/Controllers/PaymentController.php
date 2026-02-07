<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderSession;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\BulkPaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Post(
        path: "/api/payments/bulk",
        tags: ["Payments"],
        summary: "Pay all orders in a session (customer) at once",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["session_id", "payment_method", "amount"],
                properties: [
                    new OA\Property(property: "session_id", type: "integer", description: "Order session ID"),
                    new OA\Property(property: "payment_method", type: "string", enum: ["cash", "card", "qris", "gopay", "ovo", "dana"]),
                    new OA\Property(property: "amount", type: "number", example: 300000),
                    new OA\Property(property: "reference_number", type: "string"),
                    new OA\Property(property: "notes", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Bulk payment processed successfully")
        ]
    )]
    public function bulkPayment(BulkPaymentRequest $request)
    {
        $validated = $request->validated();

        $session = OrderSession::with('orders')->findOrFail($validated['session_id']);

        // Get all unpaid orders in this session
        $orders = $session->orders()
            ->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled'])
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No unpaid orders found in this session',
            ], 404);
        }

        // Calculate total from all orders
        $totalAmount = $orders->sum('total');

        if ($validated['amount'] < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount must be at least ' . $totalAmount . ' (total from ' . $orders->count() . ' orders)',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $payments = [];
            
            foreach ($orders as $order) {
                $payment = $order->payments()->create([
                    'payment_method' => $validated['payment_method'],
                    'amount' => $order->total,
                    'status' => 'completed',
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'paid_at' => now(),
                ]);
                
                $order->update(['payment_status' => 'paid']);
                $payments[] = $payment;
            }

            // Auto-complete session since all orders are now paid
            // Close all orders that are not already closed
            foreach ($session->orders as $order) {
                if (!in_array($order->status, ['closed', 'cancelled'])) {
                    $order->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                        'cashier_id' => $request->user()->id,
                    ]);
                }
            }
            
            // Mark session as completed
            $session->update([
                'status' => 'completed',
                'ended_at' => now(),
            ]);
            
            // Mark table as available
            $session->table->markAsAvailable();

            DB::commit();

            // Generate receipt URL
            $receiptUrl = route('api.order-sessions.receipt', $session->id);

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran ' . $orders->count() . ' order berhasil',
                'data' => [
                    'session' => $session->fresh(),
                    'orders_paid' => $orders->count(),
                    'total_amount' => $totalAmount,
                    'payments' => $payments,
                    'change' => $validated['payment_method'] === 'cash'
                        ? $validated['amount'] - $totalAmount
                        : 0,
                    'session_completed' => true,
                    'receipt_url' => $receiptUrl,
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
    public function store(StorePaymentRequest $request, Order $order)
    {
        $validated = $request->validated();

        // Override order_id with route parameter
        $validated['order_id'] = $order->id;

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order sudah dibayar',
            ], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add payment to cancelled order',
            ], 400);
        }

        if ($order->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add payment to order without items',
            ], 400);
        }

        // Payment must be at least the order total
        if ($validated['amount'] < $order->total) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount must be at least ' . $order->total,
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

            // Mark order as paid
            $order->update(['payment_status' => 'paid']);

            // Check if all orders in session are paid, then auto-complete session
            $session = $order->orderSession;
            if ($session && !$session->hasUnpaidOrders()) {
                // Close all orders that are not already closed
                foreach ($session->orders as $sessionOrder) {
                    if (!in_array($sessionOrder->status, ['closed', 'cancelled'])) {
                        $sessionOrder->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                            'cashier_id' => $request->user()->id,
                        ]);
                    }
                }
                
                // Mark session as completed
                $session->update([
                    'status' => 'completed',
                    'ended_at' => now(),
                ]);
                
                // Mark table as available
                $session->table->markAsAvailable();
            }

            DB::commit();

            // Generate receipt PDF if session completed
            $receiptUrl = null;
            if ($session && $session->fresh()->status === 'completed') {
                $receiptUrl = route('api.order-sessions.receipt', $session->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil',
                'data' => [
                    'payment' => $payment,
                    'change' => $validated['payment_method'] === 'cash'
                        ? $validated['amount'] - $order->total
                        : 0,
                    'session_completed' => $session && $session->fresh()->status === 'completed',
                    'receipt_url' => $receiptUrl,
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

    #[OA\Get(
        path: "/api/payments",
        tags: ["Payments"],
        summary: "Get all payments grouped by order session",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "payment_method", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["completed", "refunded"]))
        ],
        responses: [
            new OA\Response(response: 200, description: "Payments retrieved successfully")
        ]
    )]
    public function all(Request $request)
    {
        $query = Payment::with(['order.orderSession.table', 'order.waiter']);

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->get();

        // Group payments by order session
        $groupedBySession = $payments->groupBy(function($payment) {
            return $payment->order->order_session_id;
        })->map(function($sessionPayments) {
            $firstPayment = $sessionPayments->first();
            $session = $firstPayment->order->orderSession;
            
            return [
                'session_id' => $session->id,
                'customer_name' => $session->customer_name,
                'table_number' => $session->table->table_number,
                'session_started_at' => $session->started_at,
                'session_status' => $session->status,
                'orders_count' => $session->orders->count(),
                'total_amount' => $sessionPayments->sum('amount'),
                'payment_method' => $sessionPayments->first()->payment_method,
                'paid_at' => $sessionPayments->first()->paid_at,
                'payments' => $sessionPayments->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'order_number' => $payment->order->order_number,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'status' => $payment->status,
                        'reference_number' => $payment->reference_number,
                        'paid_at' => $payment->paid_at,
                    ];
                }),
                'waiter_name' => $firstPayment->order->waiter->name ?? null,
            ];
        })->values();

        $summary = [
            'total_amount' => $payments->sum('amount'),
            'total_sessions' => $groupedBySession->count(),
            'total_payments' => $payments->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $groupedBySession,
            'summary' => $summary,
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
