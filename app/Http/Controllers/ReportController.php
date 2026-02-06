<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Menu;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
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
            ->with(['items.menu', 'payments'])
            ->get();

        $totalOrders = $orders->count();
        $totalRevenue = $orders->sum('total');
        $totalDiscount = $orders->sum('discount_amount');
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
                'total_discount' => $totalDiscount,
                'total_tax' => $totalTax,
                'gross_sales' => $totalRevenue + $totalDiscount,
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

        $bestSellers = OrderItem::whereHas('order', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'closed');
        })
            ->select('menu_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_revenue'))
            ->groupBy('menu_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->with('menu')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'best_sellers' => $bestSellers,
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
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%U',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $revenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'closed')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '$dateFormat') as period"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(subtotal) as total_subtotal'),
                DB::raw('SUM(discount_amount) as total_discount'),
                DB::raw('SUM(tax) as total_tax'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $summary = [
            'total_orders' => $revenue->sum('total_orders'),
            'total_revenue' => $revenue->sum('total_revenue'),
            'total_discount' => $revenue->sum('total_discount'),
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

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'waiters' => $waiterPerformance,
                'cashiers' => $cashierPerformance,
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
        $activeDiscount = DB::table('discounts')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->count();

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
                'open_orders' => $openOrders,
                'active_discounts' => $activeDiscount,
            ],
        ]);
    }
}
