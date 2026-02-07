<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Menu;
use App\Models\OrderItem;
use App\Models\OrderSession;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Get(
        path: "/api/reports/dashboard",
        tags: ["Reports"],
        summary: "Get dashboard overview with real-time data",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Dashboard data retrieved successfully")
        ]
    )]
    public function dashboard(Request $request)
    {
        $today = now()->toDateString();
        
        // Today's statistics
        $todayOrders = Order::whereDate('created_at', $today)
            ->where('status', 'closed')
            ->selectRaw('COUNT(*) as count, SUM(total) as revenue')
            ->first();
        
        // Active sessions with orders
        $activeSessions = OrderSession::where('status', 'active')
            ->with(['table', 'orders' => function($query) {
                $query->with('items.menu')->latest();
            }])
            ->get()
            ->map(function($session) {
                return [
                    'session_id' => $session->id,
                    'customer_name' => $session->customer_name,
                    'table_number' => $session->table->table_number,
                    'started_at' => $session->started_at,
                    'orders_count' => $session->orders->count(),
                    'total_amount' => $session->orders->sum('total'),
                    'paid_amount' => $session->orders->where('payment_status', 'paid')->sum('total'),
                    'latest_order_status' => $session->orders->first()?->status,
                    'orders' => $session->orders,
                ];
            });
        
        // Current orders status breakdown
        $orderStatusBreakdown = Order::whereHas('orderSession', function($query) {
                $query->where('status', 'active');
            })
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        
        // Tables status
        $tablesStatus = DB::table('tables')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        
        // Recent completed orders (last 10)
        $recentOrders = Order::where('status', 'closed')
            ->with(['orderSession.table', 'items.menu', 'payments'])
            ->latest('closed_at')
            ->limit(10)
            ->get()
            ->map(function($order) {
                return [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'table_number' => $order->table->table_number,
                    'total' => $order->total,
                    'closed_at' => $order->closed_at,
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'orders_count' => $todayOrders->count ?? 0,
                    'revenue' => $todayOrders->revenue ?? 0,
                ],
                'active_sessions' => $activeSessions,
                'orders_status' => $orderStatusBreakdown,
                'tables_status' => $tablesStatus,
                'recent_orders' => $recentOrders,
                'summary' => [
                    'active_customers' => $activeSessions->count(),
                    'pending_orders' => $orderStatusBreakdown->get('open', 0) + $orderStatusBreakdown->get('preparing', 0),
                    'occupied_tables' => $tablesStatus->get('occupied', 0),
                ],
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/staff-dashboard",
        tags: ["Reports"],
        summary: "Get dashboard for staff (pelayan/kasir)",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Staff dashboard data retrieved successfully")
        ]
    )]
    public function staffDashboard(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        // Active sessions - semua yang sedang berjalan
        $activeSessions = OrderSession::where('status', 'active')
            ->count();

        // Total orders hari ini
        $totalOrders = Order::whereDate('created_at', $today)
            ->count();

        // Meja tersedia
        $availableTables = DB::table('tables')
            ->where('status', 'available')
            ->count();

        // Meja terisi (occupied)
        $occupiedTables = DB::table('tables')
            ->where('status', 'occupied')
            ->count();

        // Untuk pelayan - orders yang mereka tangani hari ini
        if ($user->role === 'pelayan') {
            $myOrders = Order::whereDate('created_at', $today)
                ->where('waiter_id', $user->id)
                ->count();

            $myActiveSessions = OrderSession::where('status', 'active')
                ->whereHas('orders', function($query) use ($user) {
                    $query->where('waiter_id', $user->id);
                })
                ->with(['table', 'orders' => function($query) use ($user) {
                    $query->where('waiter_id', $user->id)
                        ->with('items.menu')
                        ->latest();
                }])
                ->get()
                ->map(function($session) {
                    return [
                        'session_id' => $session->id,
                        'customer_name' => $session->customer_name,
                        'table_number' => $session->table->table_number,
                        'orders_count' => $session->orders->count(),
                        'latest_order_status' => $session->orders->first()?->status,
                        'total_amount' => $session->orders->sum('total'),
                    ];
                });

            // Pending orders (perlu diproses)
            $pendingOrders = Order::where('waiter_id', $user->id)
                ->whereIn('status', ['open', 'preparing'])
                ->with(['orderSession.table', 'items.menu'])
                ->get()
                ->map(function($order) {
                    return [
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'table_number' => $order->table->table_number,
                        'status' => $order->status,
                        'items_count' => $order->items->count(),
                        'created_at' => $order->opened_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'active_sessions' => $activeSessions,
                    'total_orders' => $totalOrders,
                    'available_tables' => $availableTables,
                    'occupied_tables' => $occupiedTables,
                    'my_stats' => [
                        'my_orders_today' => $myOrders,
                        'my_active_sessions' => $myActiveSessions,
                        'pending_orders' => $pendingOrders,
                    ],
                ],
            ]);
        }

        // Untuk kasir - fokus ke payment
        if ($user->role === 'kasir') {
            $todayPayments = Payment::whereDate('created_at', $today)
                ->where('cashier_id', $user->id)
                ->selectRaw('COUNT(*) as count, SUM(amount) as total')
                ->first();

            $unpaidOrders = Order::where('payment_status', 'unpaid')
                ->where('status', 'closed')
                ->with(['orderSession.table'])
                ->get()
                ->map(function($order) {
                    return [
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'table_number' => $order->table->table_number,
                        'total' => $order->total,
                        'closed_at' => $order->closed_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'active_sessions' => $activeSessions,
                    'total_orders' => $totalOrders,
                    'available_tables' => $availableTables,
                    'occupied_tables' => $occupiedTables,
                    'cashier_stats' => [
                        'payments_today' => $todayPayments->count ?? 0,
                        'revenue_today' => $todayPayments->total ?? 0,
                        'unpaid_orders' => $unpaidOrders,
                    ],
                ],
            ]);
        }

        // Default response untuk role lain
        return response()->json([
            'success' => true,
            'data' => [
                'active_sessions' => $activeSessions,
                'total_orders' => $totalOrders,
                'available_tables' => $availableTables,
                'occupied_tables' => $occupiedTables,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/daily-sales",
        tags: ["Reports"],
        summary: "Get daily sales report",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date", example: "2026-02-06"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Daily sales report retrieved successfully")
        ]
    )]
    public function dailySales(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $orders = Order::whereDate('created_at', $date)
            ->where('status', 'closed')
            ->with(['orderSession.table', 'items.menu', 'payments'])
            ->get();

        $totalOrders = $orders->count();
        $totalRevenue = $orders->sum('total');
        $totalTax = $orders->sum('tax');

        $paymentMethods = Payment::whereHas('order', function ($query) use ($date) {
            $query->whereDate('created_at', $date);
        })
            ->where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'total_tax' => $totalTax,
                'gross_sales' => $totalRevenue,
                'payment_methods' => $paymentMethods,
                'orders' => $orders,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/best-sellers",
        tags: ["Reports"],
        summary: "Get best selling items",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", required: false, schema: new OA\Schema(type: "integer", example: 10))
        ],
        responses: [
            new OA\Response(response: 200, description: "Best sellers retrieved successfully")
        ]
    )]
    public function bestSellers(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $limit = $request->input('limit', 10);

        // Get best sellers from completed & paid orders only
        $bestSellers = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'closed')
                ->where('payment_status', 'paid'); // Only count paid orders
        })
            ->select('menu_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('menu_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->with(['menu' => function ($query) {
                $query->select('id', 'name', 'category', 'price');
            }])
            ->get()
            ->map(function ($item) {
                return [
                    'menu_id' => $item->menu_id,
                    'menu_name' => $item->menu->name,
                    'category' => $item->menu->category,
                    'price' => $item->menu->price,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_revenue' => (float) $item->total_revenue,
                    'average_price' => $item->total_quantity > 0 
                        ? round($item->total_revenue / $item->total_quantity, 2)
                        : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'best_sellers' => $bestSellers,
                'total_items' => $bestSellers->count(),
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/revenue",
        tags: ["Reports"],
        summary: "Get revenue report",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "group_by", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["hour", "day", "week", "month"]))
        ],
        responses: [
            new OA\Response(response: 200, description: "Revenue report retrieved successfully")
        ]
    )]
    public function revenue(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $groupBy = $request->input('group_by', 'day');

        $dateFormat = match ($groupBy) {
            'hour' => 'YYYY-MM-DD HH24:00:00',
            'day' => 'YYYY-MM-DD',
            'week' => 'IYYY-IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        $revenue = Order::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'closed')
            ->select(
                DB::raw("TO_CHAR(created_at, '$dateFormat') as period"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(subtotal) as total_subtotal'),
                DB::raw('SUM(tax) as total_tax'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $summary = [
            'total_orders' => $revenue->sum('total_orders'),
            'total_revenue' => $revenue->sum('total_revenue'),
            'average_order_value' => $revenue->sum('total_orders') > 0
                ? $revenue->sum('total_revenue') / $revenue->sum('total_orders')
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'group_by' => $groupBy,
                ],
                'summary' => $summary,
                'revenue_data' => $revenue,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/staff-performance",
        tags: ["Reports"],
        summary: "Get staff performance report",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Staff performance retrieved successfully")
        ]
    )]
    public function staffPerformance(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $waiterPerformance = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'closed')
            ->select(
                'waiter_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total) as total_sales')
            )
            ->groupBy('waiter_id')
            ->with('waiter:id,name,email')
            ->get();

        $cashierPerformance = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'closed')
            ->whereNotNull('cashier_id')
            ->select(
                'cashier_id',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(total) as total_processed')
            )
            ->groupBy('cashier_id')
            ->with('cashier:id,name,email')
            ->get();

        // Add customer insights from sessions
        $sessionStats = DB::table('order_sessions')
            ->join('orders', 'order_sessions.id', '=', 'orders.order_session_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', 'closed')
            ->select(
                'order_sessions.customer_name',
                DB::raw('COUNT(DISTINCT order_sessions.id) as total_sessions'),
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('SUM(orders.total) as total_spent')
            )
            ->groupBy('order_sessions.customer_name')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'waiters' => $waiterPerformance,
                'cashiers' => $cashierPerformance,
                'top_customers' => $sessionStats,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/category-analysis",
        tags: ["Reports"],
        summary: "Get category analysis report",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Category analysis retrieved successfully")
        ]
    )]
    public function categoryAnalysis(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $categoryStats = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'closed');
        })
            ->join('menus', 'order_items.menu_id', '=', 'menus.id')
            ->select(
                'menus.category',
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.quantity) as items_sold'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('menus.category')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'categories' => $categoryStats,
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/reports/summary",
        tags: ["Reports"],
        summary: "Get summary report (today & this month)",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Summary report retrieved successfully")
        ]
    )]
    public function summary(Request $request)
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        $todayStats = Order::whereDate('created_at', $today)
            ->where('status', 'closed')
            ->selectRaw('COUNT(*) as orders, SUM(total) as revenue')
            ->first();

        $monthStats = Order::whereBetween('created_at', [$thisMonth, now()])
            ->where('status', 'closed')
            ->selectRaw('COUNT(*) as orders, SUM(total) as revenue')
            ->first();

        $openOrders = Order::where('status', 'open')->count();
        
        // Active sessions (customers currently dining)
        $activeSessions = DB::table('order_sessions')
            ->where('status', 'active')
            ->count();
        
        // Tables currently occupied
        $occupiedTables = DB::table('tables')
            ->where('status', 'occupied')
            ->count();
        
        // Pending orders (open or preparing)
        $pendingOrders = Order::whereIn('status', ['open', 'preparing'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'orders' => $todayStats->orders ?? 0,
                    'revenue' => $todayStats->revenue ?? 0,
                ],
                'this_month' => [
                    'orders' => $monthStats->orders ?? 0,
                    'revenue' => $monthStats->revenue ?? 0,
                ],
                'current' => [
                    'open_orders' => $openOrders,
                    'pending_orders' => $pendingOrders,
                    'active_sessions' => $activeSessions,
                    'occupied_tables' => $occupiedTables,
                ],
            ],
        ]);
    }
}
