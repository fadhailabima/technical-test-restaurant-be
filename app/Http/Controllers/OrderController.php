<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Table;
use App\Models\Menu;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    #[OA\Get(
        path: "/api/orders",
        tags: ["Orders"],
        summary: "Get all orders",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "payment_status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "from_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "to_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Order list retrieved successfully")
        ]
    )]
    public function index(Request $request)
    {
        $query = Order::with(['orderSession.table', 'waiter', 'cashier', 'items.menu']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('opened_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('opened_at', '<=', $request->to_date);
        }

        $orders = $query->orderBy('opened_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    #[OA\Post(
        path: "/api/orders/open",
        tags: ["Orders"],
        summary: "Open new order",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["table_id"],
                properties: [
                    new OA\Property(property: "table_id", type: "integer", example: 1),
                    new OA\Property(property: "customer_name", type: "string", example: "Samuel"),
                    new OA\Property(
                        property: "items",
                        type: "array",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "menu_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 2),
                                new OA\Property(property: "notes", type: "string", example: "pedas")
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Order opened successfully")
        ]
    )]
    public function openOrder(StoreOrderRequest $request)
    {
        $validated = $request->validated();

        $table = Table::findOrFail($validated['table_id']);

        DB::beginTransaction();
        try {
            // Find or create order session for this customer at this table
            $session = OrderSession::firstOrCreate(
                [
                    'table_id' => $table->id,
                    'customer_name' => $validated['customer_name'],
                    'status' => 'active',
                ],
                [
                    'started_at' => now(),
                ]
            );

            // Check if the latest order in this session is at least ready
            $latestOrder = $session->orders()->orderBy('created_at', 'desc')->first();
            
            if ($latestOrder && in_array($latestOrder->status, ['open', 'preparing'])) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Cannot create new order. Latest order ('. $latestOrder->order_number .') is still in '. $latestOrder->status .' status. Please wait until it is at least ready.'
                ], 422);
            }

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'order_session_id' => $session->id,
                'waiter_id' => $request->user()->id,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            // Add items if provided
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $menu = Menu::findOrFail($item['menu_id']);
                    $subtotal = $menu->price * $item['quantity'];

                    $order->items()->create([
                        'menu_id' => $menu->id,
                        'quantity' => $item['quantity'],
                        'price' => $menu->price,
                        'subtotal' => $subtotal,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                // Recalculate order totals
                $order->calculateTotals();
            }

            $table->markAsOccupied();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order opened successfully',
                'data' => $order->load(['orderSession.table', 'waiter', 'items.menu']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to open order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/orders/{order}",
        tags: ["Orders"],
        summary: "Get order detail",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Order detail retrieved successfully")
        ]
    )]
    public function show(Order $order)
    {
        $order->load(['orderSession.table', 'waiter', 'cashier', 'payments', 'items.menu']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    #[OA\Post(
        path: "/api/orders/{order}/items",
        tags: ["Orders"],
        summary: "Add item to order",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["menu_id", "quantity"],
                properties: [
                    new OA\Property(property: "menu_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 2),
                    new OA\Property(property: "notes", type: "string", example: "Tidak pedas")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Item added successfully")
        ]
    )]
    public function addItem(Request $request, Order $order)
    {
        if (!in_array($order->status, ['open', 'preparing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add items to this order',
            ], 422);
        }

        $validated = $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $menu = Menu::findOrFail($validated['menu_id']);

        if (!$menu->is_available) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item is not available',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Check if item already exists in order
            $existingItem = OrderItem::where('order_id', $order->id)
                ->where('menu_id', $menu->id)
                ->first();

            if ($existingItem) {
                // Update quantity
                $existingItem->quantity += $validated['quantity'];
                $existingItem->calculateSubtotal();
                $item = $existingItem;
            } else {
                // Create new order item
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menu->id,
                    'quantity' => $validated['quantity'],
                    'price' => $menu->price,
                    'subtotal' => $menu->price * $validated['quantity'],
                    'notes' => $validated['notes'] ?? null,
                ]);
            }

            $order->calculateTotals();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item added to order successfully',
                'data' => $item->load('menu'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: "/api/orders/{order}/items/{orderItem}",
        tags: ["Orders"],
        summary: "Remove item from order",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "orderItem", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Item removed successfully")
        ]
    )]
    public function removeItem(Order $order, OrderItem $orderItem)
    {
        if (!in_array($order->status, ['open', 'preparing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot remove items from this order',
            ], 422);
        }

        if ($orderItem->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item does not belong to this order',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $orderItem->delete();
            $order->calculateTotals();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from order successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: "/api/orders/{order}/close",
        tags: ["Orders"],
        summary: "Close order",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Order closed successfully")
        ]
    )]
    public function closeOrder(Request $request, Order $order)
    {
        if ($order->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Order is already closed',
            ], 422);
        }

        if ($order->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot close order without items',
            ], 422);
        }

        // Check if order is fully paid
        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot close order. Payment must be completed first',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order->update([
                'status' => 'closed',
                'cashier_id' => $request->user()->id,
                'closed_at' => now(),
            ]);

            // Check if session can be completed (all orders closed)
            $session = $order->orderSession;
            if ($session && $session->allOrdersClosed()) {
                $session->update([
                    'status' => 'completed',
                    'ended_at' => now(),
                ]);
                
                // Mark table as available
                $session->table->markAsAvailable();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order closed successfully',
                'data' => $order->load(['orderSession.table', 'waiter', 'cashier', 'items.menu']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to close order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: "/api/orders/{order}/receipt",
        tags: ["Orders"],
        summary: "Generate receipt PDF",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Receipt PDF generated successfully")
        ]
    )]
    public function generateReceipt(Order $order)
    {
        if ($order->status !== 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Can only generate receipt for closed orders',
            ], 422);
        }

        $order->load(['orderSession.table', 'waiter', 'cashier', 'payments', 'items.menu']);

        $pdf = Pdf::loadView('receipts.order', ['order' => $order]);

        return $pdf->download('receipt-' . $order->order_number . '.pdf');
    }

    #[OA\Patch(
        path: "/api/orders/{order}/status",
        tags: ["Orders"],
        summary: "Update order status",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "order", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["open", "preparing", "ready", "served", "closed", "cancelled"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Order status updated successfully")
        ]
    )]
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,preparing,ready,served,closed,cancelled',
        ]);

        if ($order->status === 'closed' && $validated['status'] !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update status of closed order',
            ], 400);
        }

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh(['orderSession.table', 'waiter', 'cashier', 'items.menu']),
        ]);
    }
}
