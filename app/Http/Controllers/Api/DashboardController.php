<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $totalSales = Order::query()->where('payment_status', 'paid')->sum('total');
        $totalOrders = Order::count();
        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $totalProducts = Product::count();
        $totalCustomers = User::role('customer')->count();

        $recentOrders = Order::query()
            ->with('items')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}
