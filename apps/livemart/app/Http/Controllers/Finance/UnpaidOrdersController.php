<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Traits\QueueExport;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UnpaidOrdersExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Shared\Helpers\PathHelper;
use Shared\Helpers\SecurePathHelper;

class UnpaidOrdersController extends Controller
{
    use QueueExport;

    /**
     * Display unpaid orders from all platforms
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get all platforms (already cleaned in DB to only include 4 main platforms)
        $platforms = Platform::all();
        
        // Simpan semua filter ke variabel
        $filters = [
            'platform' => $request->input('platform'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'order_number' => $request->input('order_number'),
            'customer_name' => $request->input('customer_name'),
            'min_value' => $request->input('min_value'),
            'max_value' => $request->input('max_value'),
            'min_age' => $request->input('min_age'),
            'max_age' => $request->input('max_age'),
            'sort_by' => $request->input('sort_by', 'tanggal'),
            'sort_order' => $request->input('sort_order', 'desc'),
        ];
        
        // Build base query with SQL calculations
        // Use withoutGlobalScope for both to ensure all unpaid orders are shown
        // regardless of main category filter, since unpaid orders should show all orders
        $buildQuery = function() use ($filters) {
            $query = Order::withoutGlobalScope('mainCategory');
            
            // Base filters for unpaid orders
            $query->whereDoesntHave('shopeeFinancialTransactions')
                ->whereDoesntHave('shopee2FinancialTransactions')
                ->whereDoesntHave('tiktokFinancialTransactions')
                ->whereDoesntHave('tiktok2FinancialTransactions');
            
            // Platform filter
        if ($filters['platform']) {
            $query->whereHas('platform', function($q) use ($filters) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($filters['platform'])]);
            });
        }
            
            // Date filters
            if ($filters['from_date']) {
                $query->whereDate('tanggal', '>=', $filters['from_date']);
            }
            if ($filters['to_date']) {
                $query->whereDate('tanggal', '<=', $filters['to_date']);
            }
            
            // Order number filter
            if ($filters['order_number']) {
                $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
            }
            
            // Age filters
            if ($filters['min_age']) {
                $query->where('tanggal', '<=', now()->subDays($filters['min_age']));
            }
            if ($filters['max_age']) {
                $query->where('tanggal', '>=', now()->subDays($filters['max_age']));
            }
            
            // Add SQL calculations for order items
            $query->select([
                'orders.id',
                'orders.platform_id',
                'orders.order_number',
                'orders.tanggal',
                'orders.status',
                'orders.created_at',
                'orders.updated_at',
                DB::raw('COUNT(DISTINCT order_items.id) as total_items'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0) as total_value'),
                DB::raw('COALESCE(DATEDIFF(NOW(), orders.tanggal), 0) as days_since_order'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as order_total_quantity'),
                DB::raw('COALESCE((
                    SELECT SUM(rpd.qty)
                    FROM retur_penjualan_details rpd
                    INNER JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                    INNER JOIN order_items oi ON rpd.order_item_id = oi.id
                    WHERE oi.order_id = orders.id
                    AND rp.status IN ("draft", "selesai")
                ), 0) as returned_quantity'),
                DB::raw('CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM retur_penjualans rp2 
                        WHERE rp2.order_id = orders.id 
                        AND rp2.status = "selesai"
                    ) AND (
                        SELECT COALESCE(SUM(rpd.qty), 0)
                        FROM retur_penjualan_details rpd
                        INNER JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                        INNER JOIN order_items oi ON rpd.order_item_id = oi.id
                        WHERE oi.order_id = orders.id
                        AND rp.status = "selesai"
                    ) < (
                        SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = orders.id
                    ) THEN 1 
                    ELSE 0 
                END as is_return_unpaid'),
                DB::raw('CASE 
                    WHEN EXISTS (
                        SELECT 1 
                        FROM retur_penjualans rp3 
                        WHERE rp3.order_id = orders.id 
                        AND rp3.status = "selesai"
                    ) AND (
                        SELECT COALESCE(SUM(rpd.qty), 0)
                        FROM retur_penjualan_details rpd
                        INNER JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                        INNER JOIN order_items oi ON rpd.order_item_id = oi.id
                        WHERE oi.order_id = orders.id
                        AND rp.status = "selesai"
                    ) < (
                        SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = orders.id
                    ) THEN "RETUR SEBAGIAN" 
                    ELSE NULL 
                END as unpaid_reason')
            ])
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->groupBy('orders.id', 'orders.platform_id', 'orders.order_number', 'orders.tanggal', 'orders.status', 'orders.created_at', 'orders.updated_at');
            
            // Value filters using having clause
            if ($filters['min_value']) {
                $query->havingRaw('total_value >= ?', [$filters['min_value']]);
            }
            if ($filters['max_value']) {
                $query->havingRaw('total_value <= ?', [$filters['max_value']]);
            }
            
            // Include orders that:
            // 1. Are not fully returned (returned_quantity < order_total_quantity OR returned_quantity = 0)
            // 2. OR have retur penjualan dengan status 'selesai' TAPI hanya retur sebagian (bukan retur full)
            // Retur full TIDAK akan muncul di unpaid orders
            $query->havingRaw('(returned_quantity < order_total_quantity OR returned_quantity = 0)');
            
            return $query;
        };
        
        // Build query
        $query = $buildQuery();
        
        // Get total count separately (without loading all data)
        $totalCount = $query ? $query->count() : 0;
        
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $sortBy = $filters['sort_by'] === 'tanggal' ? 'tanggal' : 'order_number';
        $sortOrder = $filters['sort_order'] === 'asc' ? 'asc' : 'desc';
        
        if ($totalCount == 0) {
            // No results
            $unpaidOrders = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            // Get results with sorting and pagination
            $results = $query->orderBy($sortBy, $sortOrder)
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();
            
            $unpaidOrders = new \Illuminate\Pagination\LengthAwarePaginator(
                $results,
                $totalCount,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        
        // Calculate summary for cards
        $summary = $this->calculateSummaryFromCollection($unpaidOrders->getCollection());
        
        return view('financial.unpaid-orders.index', compact('unpaidOrders', 'platforms', 'filters', 'summary'));
    }

    /**
     * Export unpaid orders to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel(Request $request)
    {
        // Use secure dynamic filename generation
        $filename = SecurePathHelper::getSafeFilename('unpaid_orders_' . date('Y-m-d_H-i-s') . '.xlsx');
        
        // Ensure secure temp directory exists
        SecurePathHelper::getSecureTempPath();
        
        // Clean up old temp files securely
        SecurePathHelper::cleanupSecureTempFiles();
        
        return $this->queueExcelExport(new UnpaidOrdersExport($request->all()), $filename, 'Export Unpaid Orders');
    }

    /**
     * Export unpaid orders to PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(Request $request)
    {
        // Build base query
        $platform = $request->input('platform');
        
        $query = Order::withoutGlobalScope('mainCategory')
            ->with(['orderItems.warehouseStock.tax', 'platform', 'mainCategory'])
            ->whereDoesntHave('shopeeFinancialTransactions')
            ->whereDoesntHave('tiktokFinancialTransactions');
            
        if ($platform) {
            $query->whereHas('platform', function($q) use ($platform) {
                $q->whereRaw('LOWER(name) = ?', [strtolower($platform)]);
            });
        }

        // Apply filters to query
        $applyFilters = function($query) use ($request) {
            if (!$query) return $query;
            
            if ($request->filled('from_date')) {
                $query->whereDate('tanggal', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('tanggal', '<=', $request->to_date);
            }
            if ($request->filled('order_number')) {
                $query->where('order_number', 'like', '%' . $request->order_number . '%');
            }
            if ($request->filled('min_value')) {
                $query->whereHas('orderItems', function($q) use ($request) {
                    $q->selectRaw('order_id, SUM(price_after_discount * quantity) as total_value')
                      ->groupBy('order_id')
                      ->having('total_value', '>=', $request->min_value);
                });
            }
            if ($request->filled('max_value')) {
                $query->whereHas('orderItems', function($q) use ($request) {
                    $q->selectRaw('order_id, SUM(price_after_discount * quantity) as total_value')
                      ->groupBy('order_id')
                      ->having('total_value', '<=', $request->max_value);
                });
            }
            if ($request->filled('min_age')) {
                $query->where('tanggal', '<=', now()->subDays($request->min_age));
            }
            if ($request->filled('max_age')) {
                $query->where('tanggal', '>=', now()->subDays($request->max_age));
            }
            
            return $query;
        };

        // Apply filters
        $query = $applyFilters($query);

        // Get results
        $allOrders = $query->get();
        
        // Filter orders: hanya tampilkan retur sebagian, retur full tidak akan muncul
        $allOrders = $allOrders->filter(function($order) {
            // Check if order has retur penjualan with status 'selesai'
            $hasReturSelesai = $order->returPenjualan()
                ->where('status', 'selesai')
                ->exists();
            
            // If it has retur selesai, check if it's partial return (not full return)
            if ($hasReturSelesai) {
                $hasFinancialTransaction = $order->shopeeFinancialTransactions()->exists() ||
                    $order->tiktokFinancialTransactions()->exists();
                
                // Only include if no financial transaction AND it's partial return (not full)
                if (!$hasFinancialTransaction) {
                    // Check if it's partial return (not fully returned)
                    return !$order->isFullyReturned();
                }
                
                return false;
            }
            
            // Otherwise, exclude fully returned orders
            return !$order->isFullyReturned();
        });

        // Sort collection
        $sortBy = $request->get('sort_by', 'tanggal') === 'tanggal' ? 'tanggal' : 'order_number';
        $sortOrder = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        
        $allOrders = $allOrders->sortBy([
            [$sortBy, $sortOrder]
        ]);

        $summary = $this->calculateSummaryFromCollection($allOrders);

        // Use dynamic filename generation
        $filename = SecurePathHelper::getSafeFilename('unpaid_orders_' . date('Y-m-d_H-i-s') . '.pdf');
        
        // Ensure secure temp directory exists
        SecurePathHelper::getSecureTempPath();
        
        // Clean up old temp files securely
        SecurePathHelper::cleanupSecureTempFiles();
        
        $pdf = Pdf::loadView('exports.financial.unpaid-orders', compact('allOrders', 'summary'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download($filename);
    }

    /**
     * Calculate summary from collection
     */
    private function calculateSummaryFromCollection($orders)
    {
        $totalOrders = $orders->count();
        $totalValue = 0;
        $platformBreakdown = [];
        $ageBreakdown = [
            '0-7_days' => 0,
            '8-14_days' => 0,
            '15-21_days' => 0,
            '22-30_days' => 0,
            '30+_days' => 0
        ];
        
        foreach ($orders as $order) {
            // Use pre-calculated values from SQL if available, otherwise calculate
            $orderValue = isset($order->total_value) ? (float)$order->total_value : 0;
            if ($orderValue == 0 && $order->relationLoaded('orderItems')) {
                foreach ($order->orderItems as $item) {
                    $orderValue += $item->price_after_discount * $item->quantity;
                }
            }
            
            $totalValue += $orderValue;
            $platformName = $order->platform->name ?? 'Unknown';
            if (!isset($platformBreakdown[$platformName])) {
                $platformBreakdown[$platformName] = [
                    'count' => 0,
                    'value' => 0
                ];
            }
            $platformBreakdown[$platformName]['count']++;
            $platformBreakdown[$platformName]['value'] += $orderValue;
            
            // Use pre-calculated days_since_order if available
            $daysSinceOrder = isset($order->days_since_order) ? (int)$order->days_since_order : 0;
            if ($daysSinceOrder == 0 && $order->tanggal) {
                $daysSinceOrder = $order->tanggal->diffInDays(now());
            }
            
            if ($daysSinceOrder <= 7) {
                $ageBreakdown['0-7_days']++;
            } elseif ($daysSinceOrder <= 14) {
                $ageBreakdown['8-14_days']++;
            } elseif ($daysSinceOrder <= 21) {
                $ageBreakdown['15-21_days']++;
            } elseif ($daysSinceOrder <= 30) {
                $ageBreakdown['22-30_days']++;
            } else {
                $ageBreakdown['30+_days']++;
            }
        }
        return [
            'total_orders' => $totalOrders, 
            'total_value' => $totalValue,
            'platform_breakdown' => $platformBreakdown,
            'age_breakdown' => $ageBreakdown
        ];
    }



} 