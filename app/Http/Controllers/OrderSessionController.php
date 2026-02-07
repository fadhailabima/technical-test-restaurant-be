<?php

namespace App\Http\Controllers;

use App\Models\OrderSession;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenApi\Attributes as OA;

class OrderSessionController extends Controller
{
    #[OA\Get(
        path: "/api/order-sessions",
        tags: ["Order Sessions"],
        summary: "Get all order sessions",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["active", "completed", "cancelled"])),
            new OA\Parameter(name: "table_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Session list retrieved successfully")
        ]
    )]
    public function index(Request $request)
    {
        $query = OrderSession::with(['table', 'orders']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        $sessions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    #[OA\Get(
        path: "/api/order-sessions/{session}",
        tags: ["Order Sessions"],
        summary: "Get session details",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Session details retrieved successfully")
        ]
    )]
    public function show(OrderSession $session)
    {
        $session->load(['table', 'orders.items.menu', 'orders.payments']);

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    #[OA\Post(
        path: "/api/order-sessions/{session}/complete",
        tags: ["Order Sessions"],
        summary: "Complete a session (close all orders and mark table available)",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Session completed successfully")
        ]
    )]
    public function complete(Request $request, OrderSession $session)
    {
        // Check if all orders are paid
        if ($session->hasUnpaidOrders()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete session. All orders must be paid first.',
            ], 400);
        }

        DB::beginTransaction();
        try {
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

            // Mark table as available if no other active sessions
            $session->table->markAsAvailable();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session completed successfully',
                'data' => $session->fresh(['orders']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete session: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/order-sessions/{session}/receipt",
        tags: ["Order Sessions"],
        summary: "Generate session receipt PDF",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "session", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Receipt PDF generated successfully")
        ]
    )]
    public function generateReceipt(OrderSession $session)
    {
        if ($session->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only generate receipt for completed sessions',
            ], 422);
        }

        $session->load(['table', 'orders.items.menu', 'orders.payments', 'orders.waiter', 'orders.cashier']);

        // Calculate change if cash payment
        $totalPaid = $session->orders->flatMap->payments->sum('amount');
        $totalAmount = $session->orders->sum('total');
        $change = max(0, $totalPaid - $totalAmount);

        $pdf = Pdf::loadView('receipts.session', [
            'session' => $session,
            'change' => $change
        ]);

        // Generate improved filename: Receipt_YYYYMMDD_Table-XX_CustomerName.pdf
        $date = $session->started_at->format('Ymd');
        $tableNumber = str_pad($session->table->table_number, 2, '0', STR_PAD_LEFT);
        $customerName = str_replace(' ', '-', ucwords(strtolower($session->customer_name)));
        
        $filename = sprintf(
            'Receipt_%s_Table-%s_%s.pdf',
            $date,
            $tableNumber,
            $customerName
        );

        return $pdf->download($filename);
    }
}
