<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\ReturPenjualan;
use App\Traits\QueueExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MonthlySalesSummaryExport;
use App\Exports\SalesByDayOfWeekExport;
use App\Exports\SalesByDateNumberExport;
use App\Exports\SalesDetailReportExport;
use App\Exports\SalesByPlatformExport;
use App\Exports\SalesByStatusDayExport;
use App\Exports\InternalProductSalesExport;
use App\Exports\SalesExportMappedExport;
use App\Queries\Analytics\Sales\SalesDetailQuery;

class SalesAnalyticsController extends Controller
{
    use QueueExport;
    public function salesByPlatformReport(Request $request)
    {
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->get();
        
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : Carbon::today()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : Carbon::today()->format('Y-m-d');
        
        // Parse dates
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
} catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
        }
        
        // Build base query with subqueries for total_value and total_volume (calculated in DB)
        $baseQuery = Order::withoutGlobalScope('mainCategory')
            ->whereNotNull('platform_id')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon])
            ->selectRaw('orders.*')
            ->selectRaw('(
                SELECT COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0)
                FROM order_items
                WHERE order_items.order_id = orders.id
                  AND order_items.quantity > 0
            ) as total_value')
            ->selectRaw('(
                SELECT COALESCE(SUM(order_items.quantity), 0)
                FROM order_items
                WHERE order_items.order_id = orders.id
                  AND order_items.quantity > 0
            ) as total_volume');
        
        // Apply platform filter if set
        if ($request->filled('platform_id')) {
            $baseQuery->where('platform_id', $request->platform_id);
        }
        
        $baseQuery->whereExists(function($q) {
            $q->select(DB::raw(1))
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->where('order_items.quantity', '>', 0);
        });
        
        // Determine sort order
        $sortBy = $request->input('sort', 'date_newest');
        
        switch ($sortBy) {
            case 'value_highest':
                $baseQuery->orderByRaw('total_value DESC');
                break;
            case 'value_lowest':
                $baseQuery->orderByRaw('total_value ASC');
                break;
            case 'volume_highest':
                $baseQuery->orderByRaw('total_volume DESC');
                break;
            case 'volume_lowest':
                $baseQuery->orderByRaw('total_volume ASC');
                break;
            case 'date_oldest':
                $baseQuery->orderBy('tanggal', 'asc');
                break;
            case 'date_newest':
            default:
                $baseQuery->orderBy('tanggal', 'desc');
                break;
        }
        
        // Use pagination instead of get() - much faster for large datasets
        $orders = $baseQuery->with(['platform'])->paginate(50);
        
        // Cast total_value and total_volume as float for proper display
        $orders->getCollection()->transform(function($order) {
            $order->total_value = (float) $order->total_value;
            $order->total_volume = (float) $order->total_volume;
            return $order;
        });
        
        // Calculate summary using aggregate queries (very lightweight, no data loading)
        $summaryQuery = Order::withoutGlobalScope('mainCategory')
            ->whereNotNull('platform_id')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
            
        if ($request->filled('platform_id')) {
            $summaryQuery->where('platform_id', $request->platform_id);
        }
        
        $summaryQuery->whereExists(function($q) {
            $q->select(DB::raw(1))
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->where('order_items.quantity', '>', 0);
        });
        
        // Get valid order IDs for summary calculation
        $validOrderIds = $summaryQuery->pluck('id')->toArray();
        
        // Calculate summary using single aggregate query (all in DB)
        if (!empty($validOrderIds)) {
            $summaryData = DB::table('order_items')
                ->selectRaw('COUNT(DISTINCT order_id) as total_orders')
                ->selectRaw('COALESCE(SUM(price_after_discount * quantity), 0) as total_value')
                ->selectRaw('COALESCE(SUM(quantity), 0) as total_volume')
                ->whereIn('order_id', $validOrderIds)
                ->where('quantity', '>', 0)
                ->first();
        } else {
            $summaryData = (object)[
                'total_orders' => 0,
                'total_value' => 0,
                'total_volume' => 0
            ];
        }
        
        // Get total returns count
        $totalReturns = ReturPenjualan::whereIn('order_id', function($q) use ($startDateCarbon, $endDateCarbon, $request) {
            $q->select('id')
              ->from('orders')
              ->whereNotNull('platform_id')
              ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
              
            if ($request->filled('platform_id')) {
                $q->where('platform_id', $request->platform_id);
            }
        })->count();
        
        // Build summary array
        $summary = [
            'total_orders' => (int) $summaryData->total_orders,
            'total_value' => (float) $summaryData->total_value,
            'total_volume' => (float) $summaryData->total_volume,
            'avg_order_value' => $summaryData->total_orders > 0 
                ? (float) $summaryData->total_value / (int) $summaryData->total_orders 
                : 0,
            'avg_order_volume' => $summaryData->total_orders > 0 
                ? (float) $summaryData->total_volume / (int) $summaryData->total_orders 
                : 0,
            'total_returns' => $totalReturns,
        ];
        
        // Get platform summary using aggregate query (lightweight)
        $platformSummaryQuery = Order::withoutGlobalScope('mainCategory')
            ->whereNotNull('platform_id')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
            
        if ($request->filled('platform_id')) {
            $platformSummaryQuery->where('platform_id', $request->platform_id);
        }
        
        $platformSummaryQuery->whereExists(function($q) {
            $q->select(DB::raw(1))
                ->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->where('order_items.quantity', '>', 0);
        });
        
        $platformOrderIds = $platformSummaryQuery->pluck('id')->toArray();
        
        if (!empty($platformOrderIds)) {
            $platformSummary = DB::table('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->join('platforms', 'orders.platform_id', '=', 'platforms.id')
                ->select('platforms.id as platform_id', 'platforms.name as platform')
                ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
                ->selectRaw('COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0) as total_value')
                ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_volume')
                ->whereIn('orders.id', $platformOrderIds)
                ->where('order_items.quantity', '>', 0)
                ->groupBy('platforms.id', 'platforms.name')
                ->get()
                ->map(function($item) {
                    return [
                        'platform' => $item->platform,
                        'order_count' => (int) $item->order_count,
                        'total_value' => (float) $item->total_value,
                        'total_volume' => (float) $item->total_volume,
                        'avg_order_value' => $item->order_count > 0 
                            ? (float) $item->total_value / (int) $item->order_count 
                            : 0,
                        'avg_order_volume' => $item->order_count > 0 
                            ? (float) $item->total_volume / (int) $item->order_count 
                            : 0,
                    ];
                });
        } else {
            $platformSummary = collect();
        }
        
        return view('analytics.sales_by_platform', [
            'orders' => $orders, // Paginated collection
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $request->platform_id,
            'sortBy' => $sortBy,
            'summary' => $summary,
            'platformSummary' => $platformSummary,
        ]);
    }

    /**
     * Display a detailed sales report with various filters
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function internalProductSalesReport(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->get();
        
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
        }
        
        // Get order IDs in date range with platform filter
        $orderIdsQuery = Order::withoutGlobalScope('mainCategory')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('platform_id')
            ->whereHas('orderItems.platformProduct.mappingBarang', function($query) {
                $query->where('is_active', true);
            });
            
        if ($request->has('platform_id') && !empty($request->platform_id)) {
            $orderIdsQuery->where('platform_id', $request->platform_id);
        }
        
        $orderIds = $orderIdsQuery->pluck('id')->toArray();
        
        // Get all retur details for these orders
        $allReturDetails = [];
        if (!empty($orderIds)) {
            $allReturDetails = DB::table('retur_penjualan_details')
                ->join('retur_penjualans', 'retur_penjualan_details.retur_penjualan_id', '=', 'retur_penjualans.id')
                ->whereIn('retur_penjualans.order_id', $orderIds)
                ->whereIn('retur_penjualans.status', ['draft', 'selesai'])
                ->select('retur_penjualan_details.order_item_id', DB::raw('SUM(retur_penjualan_details.qty) as total_qty'))
                ->groupBy('retur_penjualan_details.order_item_id')
                ->pluck('total_qty', 'order_item_id')
                ->toArray();
        }
        
        // Group by internal product
        $productData = [];
        
        if (!empty($orderIds)) {
            // Process orders in chunks
            foreach (array_chunk($orderIds, 100) as $chunk) {
                $chunkOrders = Order::withoutGlobalScope('mainCategory')
                    ->whereIn('id', $chunk)
                    ->with(['orderItems' => function($query) {
                        $query->with(['platformProduct.mappingBarang' => function($q) {
                            $q->where('is_active', true)->with('product');
                        }]);
                    }])
                    ->get();
                
                foreach ($chunkOrders as $order) {
                    foreach ($order->orderItems as $orderItem) {
                        if (!$orderItem->platformProduct || !$orderItem->platformProduct->mappingBarang || $orderItem->platformProduct->mappingBarang->isEmpty()) {
                            continue;
                        }
                        
                        // Get retur quantity
                        $itemQtyReturIndividual = isset($allReturDetails[$orderItem->id]) 
                            ? (float) $allReturDetails[$orderItem->id] 
                            : 0.0;
                        
                        // Process each internal product in the mapping
                        foreach ($orderItem->platformProduct->mappingBarang as $mapping) {
                            if (!$mapping->product) continue;
                            
                            $productId = $mapping->product->id;
                            $productName = $mapping->product->name;
                            $productSku = $mapping->product->sku ?? '-';
                            $mappingQty = (float) $mapping->quantity;
                            
                            // Get total package quantity for this platform product
                            $totalPackageQty = $orderItem->platformProduct->mappingBarang->sum('quantity');
                            
                            // Calculate retur for this specific internal product
                            $itemQtyRetur = $totalPackageQty > 0 ? ($itemQtyReturIndividual * $mappingQty) / $totalPackageQty : 0;
                            
                            // Calculate quantities
                            $currentItemQty = (float) ($orderItem->quantity ?? 0);
                            $originalQty = $currentItemQty + ($totalPackageQty > 0 ? $itemQtyReturIndividual / $totalPackageQty : 0);
                            $remainingQty = max(0.0, $originalQty - ($totalPackageQty > 0 ? $itemQtyReturIndividual / $totalPackageQty : 0));
                            
                            // Calculate internal product quantity
                            $internalQty = $remainingQty * $mappingQty;
                            
                            // Calculate value (proportional to this internal product)
                            $itemPrice = (float) ($orderItem->price_after_discount ?? 0);
                            // Value should be proportional: internal_qty * (price_per_platform_product / total_package_qty)
                            $itemValue = $internalQty * ($itemPrice / max($totalPackageQty, 1));
                            
                            if ($internalQty > 0) {
                                if (!isset($productData[$productId])) {
                                    $productData[$productId] = [
                                        'product_name' => $productName,
                                        'product_sku' => $productSku,
                                        'total_qty' => 0,
                                        'total_value' => 0,
                                        'order_count' => 0,
                                        'order_ids' => []
                                    ];
                                }
                                
                                $productData[$productId]['total_qty'] += $internalQty;
                                $productData[$productId]['total_value'] += $itemValue;
                                
                                // Count unique orders
                                if (!in_array($order->id, $productData[$productId]['order_ids'])) {
                                    $productData[$productId]['order_ids'][] = $order->id;
                                    $productData[$productId]['order_count']++;
                                }
                            }
                        }
                    }
                }
                
                unset($chunkOrders);
            }
        }
        
        // Convert to collection for sorting and pagination
        $productsCollection = collect($productData)->map(function($data, $productId) {
            return (object)[
                'product_id' => $productId,
                'product_name' => $data['product_name'],
                'product_sku' => $data['product_sku'],
                'total_qty' => round($data['total_qty'], 0),
                'total_value' => round($data['total_value'], 2),
                'order_count' => $data['order_count']
            ];
        });
        
        // Apply sorting
        $sortBy = $request->input('sort', 'qty_highest');
        
        switch ($sortBy) {
            case 'qty_lowest':
                $productsCollection = $productsCollection->sortBy('total_qty');
                break;
            case 'value_highest':
                $productsCollection = $productsCollection->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $productsCollection = $productsCollection->sortBy('total_value');
                break;
            case 'name_asc':
                $productsCollection = $productsCollection->sortBy('product_name');
                break;
            case 'name_desc':
                $productsCollection = $productsCollection->sortByDesc('product_name');
                break;
            case 'qty_highest':
            default:
                $productsCollection = $productsCollection->sortByDesc('total_qty');
                break;
        }
        
        // Manual pagination
        $perPage = 25;
        $currentPage = $request->input('page', 1);
        $total = $productsCollection->count();
        $products = $productsCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $products,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Calculate summary
        $summary = [
            'total_products' => $productsCollection->count(),
            'total_orders' => $productsCollection->sum('order_count'),
            'total_value' => $productsCollection->sum('total_value'),
            'total_qty' => $productsCollection->sum('total_qty'),
        ];
        
        return view('analytics.internal_product_sales', [
            'products' => $paginatedProducts,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $request->platform_id,
            'sortBy' => $sortBy,
            'summary' => $summary
        ]);
    }

    public function exportInternalProductSales(Request $request)
    {
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
        }
        
        // Get order IDs in date range with platform filter
        $orderIdsQuery = Order::withoutGlobalScope('mainCategory')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('platform_id')
            ->whereHas('orderItems.platformProduct.mappingBarang', function($query) {
                $query->where('is_active', true);
            });
            
        if ($request->has('platform_id') && !empty($request->platform_id)) {
            $orderIdsQuery->where('platform_id', $request->platform_id);
        }
        
        $orderIds = $orderIdsQuery->pluck('id')->toArray();
        
        // Get all retur details for these orders
        $allReturDetails = [];
        if (!empty($orderIds)) {
            $allReturDetails = DB::table('retur_penjualan_details')
                ->join('retur_penjualans', 'retur_penjualan_details.retur_penjualan_id', '=', 'retur_penjualans.id')
                ->whereIn('retur_penjualans.order_id', $orderIds)
                ->whereIn('retur_penjualans.status', ['draft', 'selesai'])
                ->select('retur_penjualan_details.order_item_id', DB::raw('SUM(retur_penjualan_details.qty) as total_qty'))
                ->groupBy('retur_penjualan_details.order_item_id')
                ->pluck('total_qty', 'retur_penjualan_details.order_item_id')
                ->toArray();
        }
        
        // Group by internal product
        $productData = [];
        
        if (!empty($orderIds)) {
            // Process orders in chunks
            foreach (array_chunk($orderIds, 100) as $chunk) {
                $chunkOrders = Order::withoutGlobalScope('mainCategory')
                    ->whereIn('id', $chunk)
                    ->with(['orderItems' => function($query) {
                        $query->with(['platformProduct.mappingBarang' => function($q) {
                            $q->where('is_active', true)->with('product');
                        }]);
                    }])
                    ->get();
                
                foreach ($chunkOrders as $order) {
                    foreach ($order->orderItems as $orderItem) {
                        if (!$orderItem->platformProduct || !$orderItem->platformProduct->mappingBarang || $orderItem->platformProduct->mappingBarang->isEmpty()) {
                            continue;
                        }
                        
                        // Get retur quantity
                        $itemQtyReturIndividual = isset($allReturDetails[$orderItem->id]) 
                            ? (float) $allReturDetails[$orderItem->id] 
                            : 0.0;
                        
                        // Process each internal product in the mapping
                        foreach ($orderItem->platformProduct->mappingBarang as $mapping) {
                            if (!$mapping->product) continue;
                            
                            $productId = $mapping->product->id;
                            $productName = $mapping->product->name;
                            $productSku = $mapping->product->sku ?? '-';
                            $mappingQty = (float) $mapping->quantity;
                            
                            // Get total package quantity for this platform product
                            $totalPackageQty = $orderItem->platformProduct->mappingBarang->sum('quantity');
                            
                            // Calculate retur for this specific internal product
                            $itemQtyRetur = $totalPackageQty > 0 ? ($itemQtyReturIndividual * $mappingQty) / $totalPackageQty : 0;
                            
                            // Calculate quantities
                            $currentItemQty = (float) ($orderItem->quantity ?? 0);
                            $originalQty = $currentItemQty + ($totalPackageQty > 0 ? $itemQtyReturIndividual / $totalPackageQty : 0);
                            $remainingQty = max(0.0, $originalQty - ($totalPackageQty > 0 ? $itemQtyReturIndividual / $totalPackageQty : 0));
                            
                            // Calculate internal product quantity
                            $internalQty = $remainingQty * $mappingQty;
                            
                            // Calculate value (proportional to this internal product)
                            $itemPrice = (float) ($orderItem->price_after_discount ?? 0);
                            // Value should be proportional: internal_qty * (price_per_platform_product / total_package_qty)
                            $itemValue = $internalQty * ($itemPrice / max($totalPackageQty, 1));
                            
                            if ($internalQty > 0) {
                                if (!isset($productData[$productId])) {
                                    $productData[$productId] = [
                                        'product_name' => $productName,
                                        'product_sku' => $productSku,
                                        'total_qty' => 0,
                                        'total_value' => 0,
                                        'order_count' => 0,
                                        'order_ids' => []
                                    ];
                                }
                                
                                $productData[$productId]['total_qty'] += $internalQty;
                                $productData[$productId]['total_value'] += $itemValue;
                                
                                // Count unique orders
                                if (!in_array($order->id, $productData[$productId]['order_ids'])) {
                                    $productData[$productId]['order_ids'][] = $order->id;
                                    $productData[$productId]['order_count']++;
                                }
                            }
                        }
                    }
                }
                
                unset($chunkOrders);
            }
        }
        
        // Convert to collection for sorting
        $productsCollection = collect($productData)->map(function($data, $productId) {
            return (object)[
                'product_id' => $productId,
                'product_name' => $data['product_name'],
                'product_sku' => $data['product_sku'],
                'total_qty' => round($data['total_qty'], 0),
                'total_value' => round($data['total_value'], 2),
                'order_count' => $data['order_count']
            ];
        });
        
        // Apply sorting
        $sortBy = $request->input('sort', 'qty_highest');
        
        switch ($sortBy) {
            case 'qty_lowest':
                $productsCollection = $productsCollection->sortBy('total_qty');
                break;
            case 'value_highest':
                $productsCollection = $productsCollection->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $productsCollection = $productsCollection->sortBy('total_value');
                break;
            case 'name_asc':
                $productsCollection = $productsCollection->sortBy('product_name');
                break;
            case 'name_desc':
                $productsCollection = $productsCollection->sortByDesc('product_name');
                break;
            case 'qty_highest':
            default:
                $productsCollection = $productsCollection->sortByDesc('total_qty');
                break;
        }
        
        // Get all products (no pagination for export)
        $products = $productsCollection->values();
        
        // Calculate summary
        $summary = [
            'total_products' => $productsCollection->count(),
            'total_orders' => $productsCollection->sum('order_count'),
            'total_value' => $productsCollection->sum('total_value'),
            'total_qty' => $productsCollection->sum('total_qty'),
        ];
        
        $filename = 'analytics-penjualan-master-internal-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new InternalProductSalesExport($products, $summary, $startDate, $endDate),
            $filename,
            'Export Penjualan Master Internal'
        );
    }

    public function salesExportMapped(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->get();
        
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        $sortBy = $request->input('sort', 'date_newest');
        $search = $request->input('search');
        
        // Build filters array for SQL query
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'platform_id' => $request->input('platform_id'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'min_qty' => $request->input('min_qty'),
            'max_qty' => $request->input('max_qty'),
            'search' => $search,
            'sort' => $sortBy,
        ];
        
        // Get pagination parameters
        $perPage = 25;
        $page = $request->input('page', 1);
        
        // Execute optimized SQL query untuk mendapatkan order IDs yang sudah difilter
        // excludeZeroQty = false: Mapped report menampilkan ALL order termasuk full retur
        $sqlQuery = SalesDetailQuery::build($filters, $perPage, $page, false);
        $orderResults = DB::select($sqlQuery);
        
        // Get order IDs dari hasil query
        $orderIds = collect($orderResults)->pluck('id')->toArray();
        
        // Get count untuk pagination
        $countQuery = SalesDetailQuery::buildCount($filters, false);
        $countResult = DB::selectOne($countQuery);
        $total = (int)($countResult->total ?? 0);
        
        // Eager load orders dengan relationships yang diperlukan untuk view (including nested product for internal mapping)
        $orders = collect();
        if (!empty($orderIds)) {
            $orders = Order::withoutGlobalScope('mainCategory')
                ->whereIn('id', $orderIds)
                ->with([
                    'orderItems.platformProduct.mappingBarang' => function($query) {
                        $query->where('is_active', true)->with('product');
                    },
                    'orderItems.returPenjualanDetails',
                    'platform'
                ])
                ->get()
                ->sortBy(function($order) use ($orderIds) {
                    return array_search($order->id, $orderIds);
                })
                ->values();
            
            // Calculate hari (day of week) for each order
            $orders->transform(function($order) {
                if ($order->tanggal) {
                    $order->hari = Carbon::parse($order->tanggal)->locale('id')->isoFormat('dddd');
                }
                return $order;
            });
        }
        
        // Create paginator manually
        $paginatedOrders = new \Illuminate\Pagination\LengthAwarePaginator(
            $orders,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Get summary menggunakan SQL query (optimized)
        // excludeZeroQty = false: Mapped report menampilkan ALL order
        $summaryQuery = SalesDetailQuery::buildSummary($filters, false);
        $summaryResult = DB::selectOne($summaryQuery);
        
        // Calculate percentage shown (hanya affected oleh price/qty filters)
        $hasPriceOrQtyFilter = ($request->has('min_price') && !empty($request->min_price)) || 
                                ($request->has('max_price') && !empty($request->max_price)) || 
                                ($request->has('min_qty') && !empty($request->min_qty)) || 
                                ($request->has('max_qty') && !empty($request->max_qty));
        
        // Untuk percentage, kita perlu menghitung total orders yang match date/platform filters (tanpa price/qty)
        $summaryWithoutPriceQtyFilters = $filters;
        $summaryWithoutPriceQtyFilters['min_price'] = null;
        $summaryWithoutPriceQtyFilters['max_price'] = null;
        $summaryWithoutPriceQtyFilters['min_qty'] = null;
        $summaryWithoutPriceQtyFilters['max_qty'] = null;
        
        $summaryQueryWithoutPriceQty = SalesDetailQuery::buildSummary($summaryWithoutPriceQtyFilters, false);
        $summaryResultWithoutPriceQty = DB::selectOne($summaryQueryWithoutPriceQty);
        $totalOrdersByDatePlatform = (int)($summaryResultWithoutPriceQty->total_orders ?? 0);
        
        $percentageShown = $hasPriceOrQtyFilter && $totalOrdersByDatePlatform > 0
            ? round((($summaryResult->total_orders ?? 0) / $totalOrdersByDatePlatform) * 100, 1)
            : 100;
        
        // Build summary array
        $summary = [
            'total_orders' => (int)($summaryResult->total_orders ?? 0),
            'total_orders_after_returns' => (int)($summaryResult->total_orders ?? 0),
            'total_value' => (float)($summaryResultWithoutPriceQty->total_value ?? 0),
            'total_volume' => (float)($summaryResultWithoutPriceQty->total_volume ?? 0),
            'avg_order_value' => (float)($summaryResultWithoutPriceQty->avg_order_value ?? 0),
            'avg_order_volume' => (float)($summaryResultWithoutPriceQty->avg_order_volume ?? 0),
            'percentage_shown' => $percentageShown,
            'total_orders_all' => (int)($summaryResult->total_orders_all ?? 0),
            'total_orders_before_returns' => $totalOrdersByDatePlatform,
            'total_returns' => (int)($summaryResult->total_returns ?? 0),
            'orders_with_returns' => (int)($summaryResult->orders_with_returns ?? 0),
        ];
        
        return view('analytics.sales_export_mapped', [
            'orders' => $paginatedOrders,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $request->platform_id,
            'sortBy' => $sortBy,
            'search' => $search,
            'summary' => $summary
        ]);
    }

    public function exportSalesMapped(Request $request)
    {
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        $sortBy = $request->input('sort', 'date_newest');
        $search = $request->input('search');
        
        // Build filters array for SQL query
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'platform_id' => $request->input('platform_id'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'min_qty' => $request->input('min_qty'),
            'max_qty' => $request->input('max_qty'),
            'search' => $search,
            'sort' => $sortBy,
        ];
        
        $filename = 'sales-detail-mapped-' . date('Y-m-d') . '.xlsx';
        return $this->queueExcelExport(
            new SalesExportMappedExport($filters),
            $filename,
            'Export Sales Detail Mapped'
        );
    }

    public function salesDetailReport(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->get();
        
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        $sortBy = $request->input('sort', 'date_newest');
        
        // Build filters array for SQL query
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'platform_id' => $request->input('platform_id'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'min_qty' => $request->input('min_qty'),
            'max_qty' => $request->input('max_qty'),
            'sort' => $sortBy,
        ];
        
        // Get pagination parameters
        $perPage = 25;
        $page = $request->input('page', 1);
        
        // Execute optimized SQL query untuk mendapatkan order IDs yang sudah difilter
        $sqlQuery = SalesDetailQuery::build($filters, $perPage, $page);
        $orderResults = DB::select($sqlQuery);
        
        // Get order IDs dari hasil query
        $orderIds = collect($orderResults)->pluck('id')->toArray();
        
        // Get count untuk pagination
        $countQuery = SalesDetailQuery::buildCount($filters);
        $countResult = DB::selectOne($countQuery);
        $total = (int)($countResult->total ?? 0);
        
        // Eager load orders dengan relationships yang diperlukan untuk view
        $orders = collect();
        if (!empty($orderIds)) {
            $orders = Order::withoutGlobalScope('mainCategory')
                ->whereIn('id', $orderIds)
                ->with([
                    'orderItems.platformProduct.mappingBarang' => function($query) {
                        $query->where('is_active', true);
                    },
                    'platform'
                ])
                ->get()
                ->sortBy(function($order) use ($orderIds) {
                    return array_search($order->id, $orderIds);
                })
                ->values();
            
            // Calculate hari (day of week) for each order
            $orders->transform(function($order) {
                if ($order->tanggal) {
                    $order->hari = Carbon::parse($order->tanggal)->locale('id')->isoFormat('dddd');
                }
                return $order;
            });
        }
        
        // Create paginator manually
        $paginatedOrders = new \Illuminate\Pagination\LengthAwarePaginator(
            $orders,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Get summary menggunakan SQL query (optimized)
        $summaryQuery = SalesDetailQuery::buildSummary($filters);
        $summaryResult = DB::selectOne($summaryQuery);
        
        // Calculate percentage shown (hanya affected oleh price/qty filters)
        $hasPriceOrQtyFilter = ($request->has('min_price') && !empty($request->min_price)) || 
                                ($request->has('max_price') && !empty($request->max_price)) || 
                                ($request->has('min_qty') && !empty($request->min_qty)) || 
                                ($request->has('max_qty') && !empty($request->max_qty));
        
        // Untuk percentage, kita perlu menghitung total orders yang match date/platform filters (tanpa price/qty)
        $summaryWithoutPriceQtyFilters = $filters;
        $summaryWithoutPriceQtyFilters['min_price'] = null;
        $summaryWithoutPriceQtyFilters['max_price'] = null;
        $summaryWithoutPriceQtyFilters['min_qty'] = null;
        $summaryWithoutPriceQtyFilters['max_qty'] = null;
        
        $summaryQueryWithoutPriceQty = SalesDetailQuery::buildSummary($summaryWithoutPriceQtyFilters);
        $summaryResultWithoutPriceQty = DB::selectOne($summaryQueryWithoutPriceQty);
        $totalOrdersByDatePlatform = (int)($summaryResultWithoutPriceQty->total_orders ?? 0);
        
        $percentageShown = $hasPriceOrQtyFilter && $totalOrdersByDatePlatform > 0
            ? round((($summaryResult->total_orders ?? 0) / $totalOrdersByDatePlatform) * 100, 1)
            : 100;
        
        // Hitung order yang FULL retur (qty total = 0, sudah di-exclude oleh HAVING)
        $totalOrdersAll = (int)($summaryResult->total_orders_all ?? 0);
        $totalOrdersFiltered = (int)($summaryResult->total_orders ?? 0);
        $fullyReturnedOrders = max(0, $totalOrdersAll - $totalOrdersFiltered);
        
        // Build summary array
        // Use summary without price/qty filters for total_value and total_volume to match export calculation
        $summary = [
            'total_orders' => $totalOrdersFiltered,
            'total_orders_after_returns' => $totalOrdersFiltered,
            'total_value' => (float)($summaryResultWithoutPriceQty->total_value ?? 0),
            'total_volume' => (float)($summaryResultWithoutPriceQty->total_volume ?? 0),
            'avg_order_value' => (float)($summaryResultWithoutPriceQty->avg_order_value ?? 0),
            'avg_order_volume' => (float)($summaryResultWithoutPriceQty->avg_order_volume ?? 0),
            'percentage_shown' => $percentageShown,
            'total_orders_all' => $totalOrdersAll,
            'total_orders_before_returns' => $totalOrdersByDatePlatform,
            'total_returns' => (int)($summaryResult->total_returns ?? 0),
            'orders_with_returns' => (int)($summaryResult->orders_with_returns ?? 0),
            'fully_returned_orders' => $fullyReturnedOrders,
        ];
        
        return view('analytics.sales_detail_report', [
            'orders' => $paginatedOrders,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $request->platform_id,
            'sortBy' => $sortBy,
            'summary' => $summary
        ]);
    }
    
    // Additional methods for other analytics reports will go here
    // ... (other methods)
    public function salesByDayOfWeekReport(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::all();
        
        // Set default date range to TODAY only for faster loading
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '7days':
                    $startDate = now()->subDays(7)->format('Y-m-d');
                    break;
                case '2weeks':
                    $startDate = now()->subWeeks(2)->format('Y-m-d');
                    break;
                case '1month':
                    $startDate = now()->subMonth()->format('Y-m-d');
                    break;
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
            }
        }
        
        // Get order IDs that have returns (to exclude from calculation)
        $orderIdsWithReturn = ReturPenjualan::whereIn('status', ['draft', 'selesai'])
            ->pluck('order_id')
            ->unique()
            ->toArray();
        
        // Build UNION ALL query for all financial transactions
        // This aggregates all financial transactions from all platforms in one query
        // Note: shopee2, tiktok2, and lazada tables don't have qty column, so we use 0
        $financialTransactionsQuery = "
            SELECT 
                ft.order_id,
                ft.saldo_masuk,
                COALESCE(oi.total_nominal, 0) as total_nominal,
                COALESCE(hpp.total_hpp, 0) as total_hpp,
                ft.qty,
                o.tanggal,
                o.platform_id
            FROM (
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM shopee_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM shopee2_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM tiktok_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM tiktok2_financial_transactions WHERE saldo_masuk > 0
            ) as ft
            INNER JOIN orders o ON ft.order_id = o.id
            LEFT JOIN (
                SELECT 
                    order_id,
                    SUM(price_after_discount * quantity) as total_nominal
                FROM order_items
                GROUP BY order_id
            ) as oi ON ft.order_id = oi.order_id
            LEFT JOIN (
                SELECT 
                    oi.order_id,
                    SUM(
                        CASE 
                            WHEN oi.warehouse_stock_id IS NOT NULL 
                                AND ws.id IS NOT NULL 
                                AND pd.id IS NOT NULL 
                                AND pd.qty > 0 
                            THEN (pd.subtotal / pd.qty) * oi.quantity
                            ELSE 0
                        END
                    ) as total_hpp
                FROM order_items oi
                LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                GROUP BY oi.order_id
            ) as hpp ON ft.order_id = hpp.order_id
            WHERE o.tanggal BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        // Apply platform filter if set
        if ($request->filled('platform_id')) {
            $platformId = $request->input('platform_id');
            $financialTransactionsQuery .= " AND o.platform_id = ?";
            $params[] = $platformId;
        }
        
        // Exclude orders with returns
        if (!empty($orderIdsWithReturn)) {
            $placeholders = implode(',', array_fill(0, count($orderIdsWithReturn), '?'));
            $financialTransactionsQuery .= " AND ft.order_id NOT IN ($placeholders)";
            $params = array_merge($params, $orderIdsWithReturn);
        }
        
        // Main aggregate query: Group by day of week
        $aggregateQuery = "
            SELECT 
                DAYOFWEEK(tanggal) as day_number,
                SUM(saldo_masuk) as total_value,
                SUM(total_nominal) as total_nominal,
                SUM(total_hpp) as total_hpp,
                SUM(qty) as total_volume,
                COUNT(DISTINCT order_id) as order_count
            FROM ($financialTransactionsQuery) as transactions
            GROUP BY DAYOFWEEK(tanggal)
        ";
        
        // Execute the aggregate query
        $dayOfWeekResults = DB::select($aggregateQuery, $params);
        
        // Initialize day of week data structure
        // MySQL DAYOFWEEK: 1=Sunday, 2=Monday, 3=Tuesday, 4=Wednesday, 5=Thursday, 6=Friday, 7=Saturday
        $dayOfWeekData = [];
        $dayNames = [
            1 => 'Minggu',
            2 => 'Senin', 
            3 => 'Selasa',
            4 => 'Rabu',
            5 => 'Kamis',
            6 => 'Jumat',
            7 => 'Sabtu'
        ];
        
        // Initialize all days with zero values
        for ($i = 1; $i <= 7; $i++) {
            $dayOfWeekData[$i] = [
                'day_number' => $i,
                'day_name' => $dayNames[$i],
                'total_value' => 0,
                'total_nominal' => 0,
                'total_hpp' => 0,
                'total_volume' => 0,
                'order_count' => 0
            ];
        }
        
        // Populate from query results
        foreach ($dayOfWeekResults as $result) {
            $dayNum = (int)$result->day_number;
            if (isset($dayOfWeekData[$dayNum])) {
                $dayOfWeekData[$dayNum]['total_value'] = (float)$result->total_value;
                $dayOfWeekData[$dayNum]['total_nominal'] = (float)($result->total_nominal ?? 0);
                $dayOfWeekData[$dayNum]['total_hpp'] = (float)($result->total_hpp ?? 0);
                $dayOfWeekData[$dayNum]['total_volume'] = (float)$result->total_volume;
                $dayOfWeekData[$dayNum]['order_count'] = (int)$result->order_count;
            }
        }
        
        // Apply business logic: Saturday gets 1/6 of Monday's orders
        $mondayOrderCount = $dayOfWeekData[2]['order_count'];
        $ordersToMove = (int)($mondayOrderCount / 6); // 1/6 of Monday orders
        
        if ($ordersToMove > 0 && $mondayOrderCount > 0) {
            // Calculate 1/6 of Monday's total value, nominal, HPP, and volume proportionally
            $mondayValueRatio = $mondayOrderCount > 0 ? $dayOfWeekData[2]['total_value'] / $mondayOrderCount : 0;
            $mondayNominalRatio = $mondayOrderCount > 0 ? $dayOfWeekData[2]['total_nominal'] / $mondayOrderCount : 0;
            $mondayHppRatio = $mondayOrderCount > 0 ? $dayOfWeekData[2]['total_hpp'] / $mondayOrderCount : 0;
            $mondayVolumeRatio = $mondayOrderCount > 0 ? $dayOfWeekData[2]['total_volume'] / $mondayOrderCount : 0;
            
            $mondayValueToMove = $mondayValueRatio * $ordersToMove;
            $mondayNominalToMove = $mondayNominalRatio * $ordersToMove;
            $mondayHppToMove = $mondayHppRatio * $ordersToMove;
            $mondayVolumeToMove = $mondayVolumeRatio * $ordersToMove;
            
            // Update Monday data (subtract 1/6)
            $dayOfWeekData[2]['total_value'] -= $mondayValueToMove;
            $dayOfWeekData[2]['total_nominal'] -= $mondayNominalToMove;
            $dayOfWeekData[2]['total_hpp'] -= $mondayHppToMove;
            $dayOfWeekData[2]['total_volume'] -= $mondayVolumeToMove;
            $dayOfWeekData[2]['order_count'] -= $ordersToMove;
            
            // Update Saturday data (add 1/6 from Monday)
            $dayOfWeekData[7]['total_value'] += $mondayValueToMove;
            $dayOfWeekData[7]['total_nominal'] += $mondayNominalToMove;
            $dayOfWeekData[7]['total_hpp'] += $mondayHppToMove;
            $dayOfWeekData[7]['total_volume'] += $mondayVolumeToMove;
            $dayOfWeekData[7]['order_count'] += $ordersToMove;
        }
        
        // Convert to collection format for view
        $completeDayOfWeekData = collect();
        for ($i = 1; $i <= 7; $i++) {
            $completeDayOfWeekData->push([
                'day_number' => $i,
                'day_name' => $dayNames[$i],
                'total_value' => $dayOfWeekData[$i]['total_value'],
                'total_nominal' => $dayOfWeekData[$i]['total_nominal'],
                'total_hpp' => $dayOfWeekData[$i]['total_hpp'],
                'total_volume' => $dayOfWeekData[$i]['total_volume'],
                'order_count' => $dayOfWeekData[$i]['order_count']
            ]);
        }
        
        // Calculate total summary using aggregate query
        $totalSummaryQuery = "
            SELECT 
                SUM(saldo_masuk) as total_value,
                SUM(total_nominal) as total_nominal,
                SUM(total_hpp) as total_hpp,
                SUM(qty) as total_volume,
                COUNT(DISTINCT order_id) as total_orders
            FROM ($financialTransactionsQuery) as transactions
        ";
        
        $totalSummary = DB::selectOne($totalSummaryQuery, $params);
        $totalValue = (float)($totalSummary->total_value ?? 0);
        $totalNominal = (float)($totalSummary->total_nominal ?? 0);
        $totalHpp = (float)($totalSummary->total_hpp ?? 0);
        $totalVolume = (float)($totalSummary->total_volume ?? 0);
        $totalOrders = (int)($totalSummary->total_orders ?? 0);
        
        // Count total orders (before filtering) for comparison
        $totalAllOrdersQuery = Order::whereBetween('tanggal', [$startDate, $endDate]);
        if ($request->filled('platform_id')) {
            $totalAllOrdersQuery->where('platform_id', $request->input('platform_id'));
        }
        $totalAllOrders = $totalAllOrdersQuery->count();
        
        // Count orders with financial transactions (filtered)
        $totalFilteredOrdersQuery = "
            SELECT COUNT(DISTINCT order_id) as total
            FROM ($financialTransactionsQuery) as transactions
        ";
        $totalFilteredResult = DB::selectOne($totalFilteredOrdersQuery, $params);
        $totalFilteredOrders = (int)($totalFilteredResult->total ?? 0);
        
        $totalReturns = count($orderIdsWithReturn);
        
        $summary = [
            'total_orders' => $totalOrders,
            'total_value' => $totalValue,
            'total_nominal' => $totalNominal,
            'total_hpp' => $totalHpp,
            'total_gross_profit' => $totalValue - $totalHpp,
            'total_volume' => $totalVolume,
            'total_returns' => $totalReturns,
            'total_orders_with_returns' => $totalOrders + $totalReturns,
        ];
        
        $summary['avg_order_value'] = $totalOrders > 0 ? $totalValue / $totalOrders : 0;
        $summary['avg_order_volume'] = $totalOrders > 0 ? $totalVolume / $totalOrders : 0;
        
        // Find best performing days
        $bestDaySales = $completeDayOfWeekData->max('total_value');
        $bestDayVolume = $completeDayOfWeekData->max('total_volume');
        $bestDayOrders = $completeDayOfWeekData->max('order_count');
        
        $summary['best_day_sales'] = $bestDaySales;
        $summary['best_day_volume'] = $bestDayVolume;
        $summary['best_day_orders'] = $bestDayOrders;
        
        // Create day of week summary indexed by day number (0 for Sunday, 1-6 for Monday-Saturday)
        $dayOfWeekSummary = [];
        foreach ($completeDayOfWeekData as $day) {
            // Convert from MySQL day number (1-7) to JavaScript day number (0-6)
            // MySQL: 1=Sunday, 2=Monday, ..., 7=Saturday
            // JS: 0=Sunday, 1=Monday, ..., 6=Saturday
            $jsDay = ($day['day_number'] - 1) % 7; // Convert 1 (Sunday in MySQL) to 0 (Sunday in JS)
            $dayOfWeekSummary[$jsDay] = [
                'day_name' => $day['day_name'],
                'total_value' => $day['total_value'],
                'total_nominal' => $day['total_nominal'],
                'total_hpp' => $day['total_hpp'],
                'total_gross_profit' => $day['total_value'] - $day['total_hpp'],
                'total_volume' => $day['total_volume'],
                'order_count' => $day['order_count']
            ];
        }
        
        // Define day names for JavaScript
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu'
        ];
        
        // Prepare platform summary data using aggregate query
        $platformSummary = [];
        if ($request->filled('platform_id')) {
            $selectedPlatform = $platforms->where('id', $request->platform_id)->first();
            if ($selectedPlatform) {
                $platformSummaryQuery = "
                    SELECT 
                        COUNT(DISTINCT order_id) as order_count,
                        SUM(saldo_masuk) as total_value,
                        SUM(total_nominal) as total_nominal,
                        SUM(total_hpp) as total_hpp,
                        SUM(qty) as total_volume
                    FROM ($financialTransactionsQuery) as transactions
                    WHERE platform_id = ?
                ";
                $platformParams = $params;
                $platformParams[] = $selectedPlatform->id;
                $platformResult = DB::selectOne($platformSummaryQuery, $platformParams);
                
                $platformSummary = collect([[
                    'platform' => $selectedPlatform->name,
                    'order_count' => (int)($platformResult->order_count ?? 0),
                    'total_value' => (float)($platformResult->total_value ?? 0),
                    'total_nominal' => (float)($platformResult->total_nominal ?? 0),
                    'total_hpp' => (float)($platformResult->total_hpp ?? 0),
                    'total_gross_profit' => (float)($platformResult->total_value ?? 0) - (float)($platformResult->total_hpp ?? 0),
                    'total_volume' => (float)($platformResult->total_volume ?? 0)
                ]]);
            }
        } else {
            // Group by platform for all platforms using aggregate query
            // The financialTransactionsQuery already includes platform_id in SELECT
            $platformSummaryQuery = "
                SELECT 
                    platform_id,
                    COUNT(DISTINCT order_id) as order_count,
                    SUM(saldo_masuk) as total_value,
                    SUM(total_nominal) as total_nominal,
                    SUM(total_hpp) as total_hpp,
                    SUM(qty) as total_volume
                FROM ($financialTransactionsQuery) as transactions
                GROUP BY platform_id
            ";
            $platformResults = DB::select($platformSummaryQuery, $params);
            
            $platformSummary = collect($platformResults)->map(function($result) use ($platforms) {
                $platform = $platforms->where('id', $result->platform_id)->first();
                $totalValue = (float)$result->total_value;
                $totalHpp = (float)($result->total_hpp ?? 0);
                return [
                    'platform' => $platform ? $platform->name : 'Unknown',
                    'order_count' => (int)$result->order_count,
                    'total_value' => $totalValue,
                    'total_nominal' => (float)($result->total_nominal ?? 0),
                    'total_hpp' => $totalHpp,
                    'total_gross_profit' => $totalValue - $totalHpp,
                    'total_volume' => (float)$result->total_volume
                ];
            })->values();
        }
        
        // Add information about filtered vs total orders
        $summary['total_all_orders'] = $totalAllOrders;
        $summary['total_filtered_orders'] = $totalFilteredOrders;
        $summary['percent_filtered'] = $totalAllOrders > 0 
            ? round(($totalFilteredOrders / $totalAllOrders) * 100, 1) 
            : 0;
        
        return view('analytics.sales_by_day_of_week', [
            'dayOfWeekData' => $completeDayOfWeekData,
            'dayOfWeekSummary' => $dayOfWeekSummary,
            'dayNames' => $dayNames,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $request->input('platform_id'),
            'platformSummary' => $platformSummary,
            'summary' => $summary
        ]);
    }

    public function salesByDateNumberReport(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::whereIn('name', ['Shopee Lamourad', 'Shopee Liefmarket', 'Tiktok Lamourad', 'Tiktok Liefmarket', 'Offline'])->get();
        
        // Set default date range to TODAY only for faster loading
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '7days':
                    $startDate = now()->subDays(7)->format('Y-m-d');
                    break;
                case '2weeks':
                    $startDate = now()->subWeeks(2)->format('Y-m-d');
                    break;
                case '1month':
                    $startDate = now()->subMonth()->format('Y-m-d');
                    break;
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
            }
        }
        
        // View mode (volume or value)
        $viewMode = $request->input('view_mode', 'volume');
        
        // Build UNION ALL query for all financial transactions
        // Note: shopee2, tiktok2, and lazada tables don't have qty column, so we use 0
        $financialTransactionsQuery = "
            SELECT 
                ft.order_id,
                ft.saldo_masuk,
                COALESCE(oi.total_nominal, 0) as total_nominal,
                COALESCE(hpp.total_hpp, 0) as total_hpp,
                ft.qty,
                o.tanggal,
                DAY(o.tanggal) as date_number,
                o.platform_id
            FROM (
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM shopee_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM shopee2_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM tiktok_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM tiktok2_financial_transactions WHERE saldo_masuk > 0
            ) as ft
            INNER JOIN orders o ON ft.order_id = o.id
            LEFT JOIN (
                SELECT 
                    order_id,
                    SUM(price_after_discount * quantity) as total_nominal
                FROM order_items
                GROUP BY order_id
            ) as oi ON ft.order_id = oi.order_id
            LEFT JOIN (
                SELECT 
                    oi.order_id,
                    SUM(
                        CASE 
                            WHEN oi.warehouse_stock_id IS NOT NULL 
                                AND ws.id IS NOT NULL 
                                AND pd.id IS NOT NULL 
                                AND pd.qty > 0 
                            THEN (pd.subtotal / pd.qty) * oi.quantity
                            ELSE 0
                        END
                    ) as total_hpp
                FROM order_items oi
                LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                GROUP BY oi.order_id
            ) as hpp ON ft.order_id = hpp.order_id
            WHERE o.tanggal BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        // Apply platform filter if set
        $selectedPlatform = null;
        if ($request->filled('platform_id')) {
            $selectedPlatform = $request->input('platform_id');
            $financialTransactionsQuery .= " AND o.platform_id = ?";
            $params[] = $selectedPlatform;
        }
        
        // Query 1: Group by date number (1-31) - Aggregated by DAY(tanggal)
        $dateNumberQuery = "
            SELECT 
                date_number,
                SUM(saldo_masuk) as total_value,
                SUM(total_nominal) as total_nominal,
                SUM(total_hpp) as total_hpp,
                SUM(qty) as total_volume,
                COUNT(DISTINCT order_id) as order_count
            FROM ($financialTransactionsQuery) as transactions
            GROUP BY date_number
            ORDER BY date_number
        ";
        
        $dateNumberResults = DB::select($dateNumberQuery, $params);
        
        // Create complete date number data (1-31, including dates with zero sales)
        $completeDateNumberSummary = [];
        for ($i = 1; $i <= 31; $i++) {
            $dateKey = sprintf('%02d', $i);
            $completeDateNumberSummary[$dateKey] = [
                'date_number' => $dateKey,
                'order_count' => 0,
                'total_value' => 0,
                'total_nominal' => 0,
                'total_hpp' => 0,
                'total_volume' => 0,
            ];
        }
        
        // Populate with actual data from query results (format date_number ke '01'-'31')
        foreach ($dateNumberResults as $result) {
            $dateKey = sprintf('%02d', (int)$result->date_number);
            $completeDateNumberSummary[$dateKey] = [
                'date_number' => $dateKey,
                'order_count' => (int)$result->order_count,
                'total_value' => (float)$result->total_value,
                'total_nominal' => (float)($result->total_nominal ?? 0),
                'total_hpp' => (float)($result->total_hpp ?? 0),
                'total_volume' => (float)$result->total_volume,
            ];
        }
        
        // Query 2: Total summary (all aggregated data)
        $totalSummaryQuery = "
            SELECT 
                SUM(saldo_masuk) as total_value,
                SUM(total_nominal) as total_nominal,
                SUM(total_hpp) as total_hpp,
                SUM(qty) as total_volume,
                COUNT(DISTINCT order_id) as total_orders
            FROM ($financialTransactionsQuery) as transactions
        ";
        
        $totalSummary = DB::selectOne($totalSummaryQuery, $params);
        $totalValue = (float)($totalSummary->total_value ?? 0);
        $totalNominal = (float)($totalSummary->total_nominal ?? 0);
        $totalHpp = (float)($totalSummary->total_hpp ?? 0);
        $totalVolume = (float)($totalSummary->total_volume ?? 0);
        $totalOrders = (int)($totalSummary->total_orders ?? 0);
        
        // Query 3: Count total all orders per date number (before filtering by financial transactions)
        // This will show all orders including those without financial transactions
        $allOrdersByDateQuery = "
            SELECT 
                DAY(tanggal) as date_number,
                COUNT(*) as total_all_orders_count
            FROM orders
            WHERE tanggal BETWEEN ? AND ?
        ";
        
        $allOrdersParams = [$startDate, $endDate];
        if ($selectedPlatform) {
            $allOrdersByDateQuery .= " AND platform_id = ?";
            $allOrdersParams[] = $selectedPlatform;
        }
        $allOrdersByDateQuery .= " GROUP BY DAY(tanggal)";
        
        $allOrdersByDateResults = DB::select($allOrdersByDateQuery, $allOrdersParams);
        
        // Create all orders count by date for comparison
        $allOrdersByDate = [];
        foreach ($allOrdersByDateResults as $result) {
            $dateKey = sprintf('%02d', (int)$result->date_number);
            $allOrdersByDate[$dateKey] = (int)$result->total_all_orders_count;
        }
        
        // Query 4: Count total all orders (before filtering by financial transactions)
        $totalAllOrdersQuery = Order::whereBetween('tanggal', [$startDate, $endDate]);
        if ($selectedPlatform) {
            $totalAllOrdersQuery->where('platform_id', $selectedPlatform);
        }
        $totalAllOrders = $totalAllOrdersQuery->count();
        
        // Debug: Check if there are orders on dates 5 and 31 without financial transactions
        // This will help identify the issue
        $debugQuery = "
            SELECT 
                DAY(o.tanggal) as date_number,
                COUNT(DISTINCT o.id) as order_count,
                GROUP_CONCAT(DISTINCT o.id ORDER BY o.id LIMIT 10) as sample_order_ids,
                GROUP_CONCAT(DISTINCT DATE(o.tanggal) ORDER BY o.tanggal LIMIT 5) as sample_dates
            FROM orders o
            LEFT JOIN (
                SELECT DISTINCT order_id 
                FROM (
                    SELECT order_id FROM shopee_financial_transactions WHERE saldo_masuk > 0
                    UNION
                    SELECT order_id FROM shopee2_financial_transactions WHERE saldo_masuk > 0
                    UNION
                    SELECT order_id FROM tiktok_financial_transactions WHERE saldo_masuk > 0
                    UNION
                    SELECT order_id FROM tiktok2_financial_transactions WHERE saldo_masuk > 0
                ) as all_ft
            ) as ft ON o.id = ft.order_id
            WHERE o.tanggal BETWEEN ? AND ?
            AND ft.order_id IS NULL
        ";
        
        $debugParams = [$startDate, $endDate];
        if ($selectedPlatform) {
            $debugQuery .= " AND o.platform_id = ?";
            $debugParams[] = $selectedPlatform;
        }
        $debugQuery .= " AND DAY(o.tanggal) IN (5, 31)";
        $debugQuery .= " GROUP BY DAY(o.tanggal)";
        
        $debugResults = DB::select($debugQuery, $debugParams);
        
        // Also check: Are there any orders on date 31 at all in the period?
        $checkDate31Query = "
            SELECT 
                DATE(tanggal) as full_date,
                COUNT(*) as order_count,
                GROUP_CONCAT(id ORDER BY id LIMIT 10) as sample_order_ids
            FROM orders
            WHERE tanggal BETWEEN ? AND ?
            AND DAY(tanggal) = 31
        ";
        
        $checkDate31Params = [$startDate, $endDate];
        if ($selectedPlatform) {
            $checkDate31Query .= " AND platform_id = ?";
            $checkDate31Params[] = $selectedPlatform;
        }
        $checkDate31Query .= " GROUP BY DATE(tanggal) ORDER BY DATE(tanggal)";
        
        $date31Results = DB::select($checkDate31Query, $checkDate31Params);
        
        // Add debug info to summary for troubleshooting
        $debugInfo = [];
        foreach ($debugResults as $debug) {
            $dateKey = sprintf('%02d', (int)$debug->date_number);
            $debugInfo[$dateKey] = [
                'order_count' => (int)$debug->order_count,
                'sample_order_ids' => $debug->sample_order_ids ?? '',
                'sample_dates' => $debug->sample_dates ?? '',
            ];
        }
        
        // Special handling for date 31: Check if date 31 exists in the date range
        $date31Info = [];
        foreach ($date31Results as $result) {
            $date31Info[] = [
                'full_date' => $result->full_date,
                'order_count' => (int)$result->order_count,
                'sample_order_ids' => $result->sample_order_ids ?? '',
            ];
        }
        
        // Add date 31 specific info to debugInfo
        if (!empty($date31Info)) {
            $debugInfo['31_details'] = $date31Info;
        }
        
        // Query 5: Platform summary
        $platformSummaryQuery = "
            SELECT 
                o.platform_id,
                SUM(ft.saldo_masuk) as total_value,
                SUM(COALESCE(oi.total_nominal, 0)) as total_nominal,
                SUM(COALESCE(hpp.total_hpp, 0)) as total_hpp,
                SUM(ft.qty) as total_volume,
                COUNT(DISTINCT ft.order_id) as order_count
            FROM (
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM shopee_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM shopee2_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, COALESCE(qty, 0) as qty FROM tiktok_financial_transactions WHERE saldo_masuk > 0
                UNION ALL
                SELECT order_id, saldo_masuk, 0 as qty FROM tiktok2_financial_transactions WHERE saldo_masuk > 0
            ) as ft
            INNER JOIN orders o ON ft.order_id = o.id
            LEFT JOIN (
                SELECT 
                    order_id,
                    SUM(price_after_discount * quantity) as total_nominal
                FROM order_items
                GROUP BY order_id
            ) as oi ON ft.order_id = oi.order_id
            LEFT JOIN (
                SELECT 
                    oi.order_id,
                    SUM(
                        CASE 
                            WHEN oi.warehouse_stock_id IS NOT NULL 
                                AND ws.id IS NOT NULL 
                                AND pd.id IS NOT NULL 
                                AND pd.qty > 0 
                            THEN (pd.subtotal / pd.qty) * oi.quantity
                            ELSE 0
                        END
                    ) as total_hpp
                FROM order_items oi
                LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                GROUP BY oi.order_id
            ) as hpp ON ft.order_id = hpp.order_id
            WHERE o.tanggal BETWEEN ? AND ?
        ";
        
        $platformParams = [$startDate, $endDate];
        if ($selectedPlatform) {
            $platformSummaryQuery .= " AND o.platform_id = ?";
            $platformParams[] = $selectedPlatform;
        }
        
        $platformSummaryQuery .= " GROUP BY o.platform_id";
        
        $platformResults = DB::select($platformSummaryQuery, $platformParams);
        
        // Map platform results with platform names
        $platformSummary = collect($platformResults)->map(function($result) use ($platforms) {
            $platform = $platforms->where('id', $result->platform_id)->first();
            $totalValue = (float)$result->total_value;
            $totalHpp = (float)($result->total_hpp ?? 0);
            return [
                'platform' => $platform ? $platform->name : 'Unknown',
                'order_count' => (int)$result->order_count,
                'total_value' => $totalValue,
                'total_nominal' => (float)($result->total_nominal ?? 0),
                'total_hpp' => $totalHpp,
                'total_gross_profit' => $totalValue - $totalHpp,
                'total_volume' => (float)$result->total_volume,
            ];
        });
        
        // Calculate summary metrics
        $summary = [
            'total_orders' => $totalOrders,
            'total_value' => $totalValue,
            'total_nominal' => $totalNominal,
            'total_hpp' => $totalHpp,
            'total_gross_profit' => $totalValue - $totalHpp,
            'total_volume' => $totalVolume,
            'avg_order_value' => $totalOrders > 0 ? $totalValue / $totalOrders : 0,
            'avg_order_volume' => $totalOrders > 0 ? $totalVolume / $totalOrders : 0,
            'total_all_orders' => $totalAllOrders,
            'total_filtered_orders' => $totalOrders,
            'percent_filtered' => $totalAllOrders > 0 
                ? round(($totalOrders / $totalAllOrders) * 100, 1) 
                : 0,
        ];
        
        // Query 6: Group by month and date number for monthly breakdown
        // Use the same financialTransactionsQuery structure for consistency
        $monthlyDateNumberQuery = "
            SELECT 
                DATE_FORMAT(tanggal, '%Y-%m') as month_key,
                YEAR(tanggal) as year,
                MONTH(tanggal) as month,
                DAY(tanggal) as date_number,
                SUM(saldo_masuk) as total_value,
                SUM(total_nominal) as total_nominal,
                SUM(total_hpp) as total_hpp,
                SUM(qty) as total_volume,
                COUNT(DISTINCT order_id) as order_count
            FROM ($financialTransactionsQuery) as transactions
            GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), YEAR(tanggal), MONTH(tanggal), DAY(tanggal)
            ORDER BY year, month, date_number
        ";
        
        $monthlyParams = $params;
        $monthlyDateNumberResults = DB::select($monthlyDateNumberQuery, $monthlyParams);
        
        // Group data by month
        $monthlyData = [];
        $startCarbon = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);
        
        // Initialize monthly data structure for all months in range
        $currentMonth = $startCarbon->copy()->startOfMonth();
        while ($currentMonth->lte($endCarbon)) {
            $monthKey = $currentMonth->format('Y-m');
            $monthName = $currentMonth->locale('id')->translatedFormat('F Y');
            
            // Determine date range for this month
            $monthStartDate = $currentMonth->copy()->startOfMonth();
            $monthEndDate = $currentMonth->copy()->endOfMonth();
            
            // Adjust start date if it's the first month in range
            if ($currentMonth->format('Y-m') == $startCarbon->format('Y-m')) {
                $monthStartDate = $startCarbon->copy();
            }
            
            // Adjust end date if it's the last month in range
            if ($currentMonth->format('Y-m') == $endCarbon->format('Y-m')) {
                $monthEndDate = $endCarbon->copy();
            }
            
            // Get the first and last day of the month in the range
            $firstDayInRange = $monthStartDate->day;
            $lastDayInRange = $monthEndDate->day;
            
            // Initialize date data for this month
            $monthDateData = [];
            for ($day = $firstDayInRange; $day <= $lastDayInRange; $day++) {
                $dateKey = sprintf('%02d', $day);
                $monthDateData[$dateKey] = [
                    'date_number' => $dateKey,
                    'order_count' => 0,
                    'total_value' => 0,
                    'total_nominal' => 0,
                    'total_hpp' => 0,
                    'total_volume' => 0,
                ];
            }
            
            $monthlyData[$monthKey] = [
                'month_key' => $monthKey,
                'month_name' => $monthName,
                'year' => $currentMonth->year,
                'month' => $currentMonth->month,
                'first_day' => $firstDayInRange,
                'last_day' => $lastDayInRange,
                'date_data' => $monthDateData,
                'summary' => [
                    'total_orders' => 0,
                    'total_value' => 0,
                    'total_nominal' => 0,
                    'total_hpp' => 0,
                    'total_gross_profit' => 0,
                    'total_volume' => 0,
                ],
            ];
            
            $currentMonth->addMonth();
        }
        
        // Populate monthly data from query results
        foreach ($monthlyDateNumberResults as $result) {
            $monthKey = $result->month_key;
            $dateKey = sprintf('%02d', (int)$result->date_number);
            
            if (isset($monthlyData[$monthKey]['date_data'][$dateKey])) {
                $monthlyData[$monthKey]['date_data'][$dateKey] = [
                    'date_number' => $dateKey,
                    'order_count' => (int)$result->order_count,
                    'total_value' => (float)$result->total_value,
                    'total_nominal' => (float)($result->total_nominal ?? 0),
                    'total_hpp' => (float)($result->total_hpp ?? 0),
                    'total_volume' => (float)$result->total_volume,
                ];
                
                // Update month summary
                $monthlyData[$monthKey]['summary']['total_orders'] += (int)$result->order_count;
                $monthlyData[$monthKey]['summary']['total_value'] += (float)$result->total_value;
                $monthlyData[$monthKey]['summary']['total_nominal'] += (float)($result->total_nominal ?? 0);
                $monthlyData[$monthKey]['summary']['total_hpp'] += (float)($result->total_hpp ?? 0);
                $monthlyData[$monthKey]['summary']['total_volume'] += (float)$result->total_volume;
            }
        }
        
        // Calculate gross profit for each month
        foreach ($monthlyData as $monthKey => &$monthInfo) {
            $monthInfo['summary']['total_gross_profit'] = $monthInfo['summary']['total_value'] - $monthInfo['summary']['total_hpp'];
        }
        
        return view('analytics.sales_by_date_number', [
            'dateNumberSummary' => $completeDateNumberSummary,
            'monthlyData' => $monthlyData,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $selectedPlatform,
            'viewMode' => $viewMode,
            'summary' => $summary,
            'platformSummary' => $platformSummary,
            'allOrdersByDate' => $allOrdersByDate, // For comparison: all orders vs filtered
            'debugInfo' => $debugInfo, // Orders without financial transactions on dates 5 and 31
        ]);
    }
    public function salesByStatusAndDayReport(Request $request)
    {
        // Get platforms for filter
        $platforms = Platform::all();
        
        // Set default date range to TODAY to reduce initial load
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '7days':
                    $startDate = now()->subDays(7)->format('Y-m-d');
                    break;
                case '2weeks':
                    $startDate = now()->subWeeks(2)->format('Y-m-d');
                    break;
                case '1month':
                    $startDate = now()->subMonth()->format('Y-m-d');
                    break;
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
            }
        }
        
        // View mode (volume or value)
        $viewMode = $request->input('view_mode', 'volume');
        
        // Apply platform filter if set
        $selectedPlatform = null;
        $platformFilter = '';
        if ($request->filled('platform_id')) {
            $selectedPlatform = $request->input('platform_id');
            $platformFilter = " AND o.platform_id = " . intval($selectedPlatform);
        }
        
        // Apply status_hari filter if set
        $selectedStatus = null;
        $statusFilter = '';
        if ($request->filled('status')) {
            $selectedStatus = $request->input('status');
            $statusFilter = " AND (
                o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
            )";
        }
        
        // Build base query: UNION ALL semua financial transactions
        // Note: Shopee2 and TikTok2 don't have qty column, so we use 0
        $allTransactionsSQL = "
            SELECT 
                o.id as order_id,
                o.tanggal,
                o.status_hari,
                o.platform_id,
                COALESCE(ft.saldo_masuk, 0) as saldo_masuk,
                COALESCE(ft.qty, 0) as qty
            FROM orders o
            INNER JOIN shopee_financial_transactions ft ON ft.order_id = o.id
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                AND ft.saldo_masuk > 0
                " . $platformFilter . $statusFilter . "
            
            UNION ALL
            
            SELECT 
                o.id as order_id,
                o.tanggal,
                o.status_hari,
                o.platform_id,
                COALESCE(ft.saldo_masuk, 0) as saldo_masuk,
                COALESCE(ft.qty, 0) as qty
            FROM orders o
            INNER JOIN tiktok_financial_transactions ft ON ft.order_id = o.id
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                AND ft.saldo_masuk > 0
                " . $platformFilter . $statusFilter . "
        ";
        
        // Query untuk mendapatkan semua status unik (expand comma-separated)
        $allStatusesQuery = "
            SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(o.status_hari, ',', numbers.n), ',', -1)) as status
            FROM (
                SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 
                UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
            ) numbers
            INNER JOIN orders o 
                ON CHAR_LENGTH(o.status_hari) - CHAR_LENGTH(REPLACE(o.status_hari, ',', '')) >= numbers.n - 1
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                AND o.status_hari IS NOT NULL
                AND o.status_hari != ''
            UNION
            SELECT DISTINCT o.status_hari as status
            FROM orders o
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                AND o.status_hari IS NOT NULL
                AND o.status_hari != ''
                AND o.status_hari NOT LIKE '%,%'
            ORDER BY status";
        
        $allStatuses = collect(DB::select($allStatusesQuery))->pluck('status')->filter()->unique()->sort()->values()->toArray();
        
        // Day of week names
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        
        // Query untuk statusDayMatrix: GROUP BY status dan day of week
        // Menggunakan CTE untuk expand comma-separated status dan aggregate transactions per order
        $statusDayMatrixQuery = "
            WITH all_transactions AS (
                " . $allTransactionsSQL . "
            ),
            order_totals AS (
                SELECT 
                    at.order_id,
                    SUM(at.saldo_masuk) as order_total_value,
                    SUM(at.qty) as order_total_volume,
                    COALESCE(oi.total_nominal, 0) as order_total_nominal,
                    COALESCE(hpp.total_hpp, 0) as order_total_hpp
                FROM all_transactions at
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(price_after_discount * quantity) as total_nominal
                    FROM order_items
                    GROUP BY order_id
                ) as oi ON at.order_id = oi.order_id
                LEFT JOIN (
                    SELECT 
                        oi.order_id,
                        SUM(
                            CASE 
                                WHEN oi.warehouse_stock_id IS NOT NULL 
                                    AND ws.id IS NOT NULL 
                                    AND pd.id IS NOT NULL 
                                    AND pd.qty > 0 
                                THEN (pd.subtotal / pd.qty) * oi.quantity
                                ELSE 0
                            END
                        ) as total_hpp
                    FROM order_items oi
                    LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                    LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                    GROUP BY oi.order_id
                ) as hpp ON at.order_id = hpp.order_id
                GROUP BY at.order_id, oi.total_nominal, hpp.total_hpp
            ),
            expanded_orders AS (
                SELECT 
                    o.id as order_id,
                    o.tanggal,
                    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(o.status_hari, ',', numbers.n), ',', -1)) as status,
                    DAYOFWEEK(o.tanggal) - 1 as day_of_week,
                    o.platform_id
                FROM (
                    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 
                    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                ) numbers
                INNER JOIN orders o 
                    ON CHAR_LENGTH(o.status_hari) - CHAR_LENGTH(REPLACE(o.status_hari, ',', '')) >= numbers.n - 1
                INNER JOIN order_totals ot ON ot.order_id = o.id
                WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                    AND o.status_hari IS NOT NULL
                    AND o.status_hari != ''
                    " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                    " . ($selectedStatus ? "AND (
                        o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                    )" : "") . "
                UNION
                SELECT 
                    o.id as order_id,
                    o.tanggal,
                    o.status_hari as status,
                    DAYOFWEEK(o.tanggal) - 1 as day_of_week,
                    o.platform_id
                FROM orders o
                INNER JOIN order_totals ot ON ot.order_id = o.id
                WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                    AND o.status_hari IS NOT NULL
                    AND o.status_hari != ''
                    AND o.status_hari NOT LIKE '%,%'
                    " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                    " . ($selectedStatus ? "AND (
                        o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                    )" : "") . "
            )
            SELECT 
                eo.status,
                eo.day_of_week,
                COUNT(DISTINCT eo.order_id) as order_count,
                COALESCE(SUM(ot.order_total_value), 0) as total_value,
                COALESCE(SUM(ot.order_total_nominal), 0) as total_nominal,
                COALESCE(SUM(ot.order_total_hpp), 0) as total_hpp,
                COALESCE(SUM(ot.order_total_volume), 0) as total_volume
            FROM expanded_orders eo
            INNER JOIN order_totals ot ON ot.order_id = eo.order_id
            GROUP BY eo.status, eo.day_of_week";
        
        $statusDayMatrixResults = collect(DB::select($statusDayMatrixQuery));
        
        // Initialize matrix with zeros
        $statusDayMatrix = [];
        foreach ($allStatuses as $status) {
            $statusDayMatrix[$status] = [];
            foreach (range(0, 6) as $dayNum) {
                $statusDayMatrix[$status][$dayNum] = [
                    'order_count' => 0,
                    'total_value' => 0,
                    'total_nominal' => 0,
                    'total_hpp' => 0,
                    'total_volume' => 0,
                ];
            }
        }
        
        // Fill matrix with query results
        foreach ($statusDayMatrixResults as $row) {
            $status = trim($row->status ?? '');
            $dayOfWeek = (int)($row->day_of_week ?? 0);
            if (!empty($status) && isset($statusDayMatrix[$status][$dayOfWeek])) {
                $statusDayMatrix[$status][$dayOfWeek] = [
                    'order_count' => (int)($row->order_count ?? 0),
                    'total_value' => (float)($row->total_value ?? 0),
                    'total_nominal' => (float)($row->total_nominal ?? 0),
                    'total_hpp' => (float)($row->total_hpp ?? 0),
                    'total_volume' => (float)($row->total_volume ?? 0),
                ];
            }
        }
        
        // Query untuk statusSummary: GROUP BY status saja
        $statusSummaryQuery = "
            WITH all_transactions AS (
                " . $allTransactionsSQL . "
            ),
            order_totals AS (
                SELECT 
                    at.order_id,
                    SUM(at.saldo_masuk) as order_total_value,
                    SUM(at.qty) as order_total_volume,
                    COALESCE(oi.total_nominal, 0) as order_total_nominal,
                    COALESCE(hpp.total_hpp, 0) as order_total_hpp
                FROM all_transactions at
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(price_after_discount * quantity) as total_nominal
                    FROM order_items
                    GROUP BY order_id
                ) as oi ON at.order_id = oi.order_id
                LEFT JOIN (
                    SELECT 
                        oi.order_id,
                        SUM(
                            CASE 
                                WHEN oi.warehouse_stock_id IS NOT NULL 
                                    AND ws.id IS NOT NULL 
                                    AND pd.id IS NOT NULL 
                                    AND pd.qty > 0 
                                THEN (pd.subtotal / pd.qty) * oi.quantity
                                ELSE 0
                            END
                        ) as total_hpp
                    FROM order_items oi
                    LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                    LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                    GROUP BY oi.order_id
                ) as hpp ON at.order_id = hpp.order_id
                GROUP BY at.order_id, oi.total_nominal, hpp.total_hpp
            ),
            expanded_orders AS (
                SELECT 
                    o.id as order_id,
                    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(o.status_hari, ',', numbers.n), ',', -1)) as status
                FROM (
                    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 
                    UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                ) numbers
                INNER JOIN orders o 
                    ON CHAR_LENGTH(o.status_hari) - CHAR_LENGTH(REPLACE(o.status_hari, ',', '')) >= numbers.n - 1
                INNER JOIN order_totals ot ON ot.order_id = o.id
                WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                    AND o.status_hari IS NOT NULL
                    AND o.status_hari != ''
                    " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                    " . ($selectedStatus ? "AND (
                        o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                    )" : "") . "
                UNION
                SELECT 
                    o.id as order_id,
                    o.status_hari as status
                FROM orders o
                INNER JOIN order_totals ot ON ot.order_id = o.id
                WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                    AND o.status_hari IS NOT NULL
                    AND o.status_hari != ''
                    AND o.status_hari NOT LIKE '%,%'
                    " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                    " . ($selectedStatus ? "AND (
                        o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                        OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                    )" : "") . "
            )
            SELECT 
                eo.status,
                COUNT(DISTINCT eo.order_id) as total_orders,
                COALESCE(SUM(ot.order_total_value), 0) as total_value,
                COALESCE(SUM(ot.order_total_nominal), 0) as total_nominal,
                COALESCE(SUM(ot.order_total_hpp), 0) as total_hpp,
                COALESCE(SUM(ot.order_total_volume), 0) as total_volume
            FROM expanded_orders eo
            INNER JOIN order_totals ot ON ot.order_id = eo.order_id
            GROUP BY eo.status";
        
        $statusSummaryResults = collect(DB::select($statusSummaryQuery));
        $statusSummary = [];
        foreach ($statusSummaryResults as $row) {
            $status = trim($row->status ?? '');
            if (!empty($status)) {
                $statusSummary[$status] = [
                    'total_orders' => (int)($row->total_orders ?? 0),
                    'total_value' => (float)($row->total_value ?? 0),
                    'total_nominal' => (float)($row->total_nominal ?? 0),
                    'total_hpp' => (float)($row->total_hpp ?? 0),
                    'total_volume' => (float)($row->total_volume ?? 0),
                ];
            }
        }
        
        // Fill missing statuses with zeros
        foreach ($allStatuses as $status) {
            if (!isset($statusSummary[$status])) {
                $statusSummary[$status] = [
                    'total_orders' => 0,
                    'total_value' => 0,
                    'total_nominal' => 0,
                    'total_hpp' => 0,
                    'total_volume' => 0,
                ];
            }
        }
        
        // Query untuk dayOfWeekSummary: GROUP BY day of week saja
        $dayOfWeekSummaryQuery = "
            WITH all_transactions AS (
                " . $allTransactionsSQL . "
            ),
            order_totals AS (
                SELECT 
                    at.order_id,
                    SUM(at.saldo_masuk) as order_total_value,
                    SUM(at.qty) as order_total_volume,
                    COALESCE(oi.total_nominal, 0) as order_total_nominal,
                    COALESCE(hpp.total_hpp, 0) as order_total_hpp
                FROM all_transactions at
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(price_after_discount * quantity) as total_nominal
                    FROM order_items
                    GROUP BY order_id
                ) as oi ON at.order_id = oi.order_id
                LEFT JOIN (
                    SELECT 
                        oi.order_id,
                        SUM(
                            CASE 
                                WHEN oi.warehouse_stock_id IS NOT NULL 
                                    AND ws.id IS NOT NULL 
                                    AND pd.id IS NOT NULL 
                                    AND pd.qty > 0 
                                THEN (pd.subtotal / pd.qty) * oi.quantity
                                ELSE 0
                            END
                        ) as total_hpp
                    FROM order_items oi
                    LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                    LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                    GROUP BY oi.order_id
                ) as hpp ON at.order_id = hpp.order_id
                GROUP BY at.order_id, oi.total_nominal, hpp.total_hpp
            )
            SELECT 
                DAYOFWEEK(o.tanggal) - 1 as day_of_week,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(ot.order_total_value), 0) as total_value,
                COALESCE(SUM(ot.order_total_nominal), 0) as total_nominal,
                COALESCE(SUM(ot.order_total_hpp), 0) as total_hpp,
                COALESCE(SUM(ot.order_total_volume), 0) as total_volume
            FROM orders o
            INNER JOIN order_totals ot ON ot.order_id = o.id
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                " . ($selectedStatus ? "AND (
                    o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                )" : "") . "
            GROUP BY DAYOFWEEK(o.tanggal) - 1";
        
        $dayOfWeekSummaryResults = collect(DB::select($dayOfWeekSummaryQuery));
        $dayOfWeekSummary = [];
        foreach (range(0, 6) as $dayNum) {
            $dayOfWeekSummary[$dayNum] = [
                'day_name' => $dayNames[$dayNum],
                'order_count' => 0,
                'total_value' => 0,
                'total_nominal' => 0,
                'total_hpp' => 0,
                'total_volume' => 0,
            ];
        }
        foreach ($dayOfWeekSummaryResults as $row) {
            $dayOfWeek = (int)($row->day_of_week ?? 0);
            if (isset($dayOfWeekSummary[$dayOfWeek])) {
                $dayOfWeekSummary[$dayOfWeek] = [
                    'day_name' => $dayNames[$dayOfWeek] ?? 'Unknown',
                    'order_count' => (int)($row->order_count ?? 0),
                    'total_value' => (float)($row->total_value ?? 0),
                    'total_nominal' => (float)($row->total_nominal ?? 0),
                    'total_hpp' => (float)($row->total_hpp ?? 0),
                    'total_volume' => (float)($row->total_volume ?? 0),
                ];
            }
        }
        
        // Query untuk overall summary
        $overallSummaryQuery = "
            WITH all_transactions AS (
                " . $allTransactionsSQL . "
            ),
            order_totals AS (
                SELECT 
                    at.order_id,
                    SUM(at.saldo_masuk) as order_total_value,
                    SUM(at.qty) as order_total_volume,
                    COALESCE(oi.total_nominal, 0) as order_total_nominal,
                    COALESCE(hpp.total_hpp, 0) as order_total_hpp
                FROM all_transactions at
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(price_after_discount * quantity) as total_nominal
                    FROM order_items
                    GROUP BY order_id
                ) as oi ON at.order_id = oi.order_id
                LEFT JOIN (
                    SELECT 
                        oi.order_id,
                        SUM(
                            CASE 
                                WHEN oi.warehouse_stock_id IS NOT NULL 
                                    AND ws.id IS NOT NULL 
                                    AND pd.id IS NOT NULL 
                                    AND pd.qty > 0 
                                THEN (pd.subtotal / pd.qty) * oi.quantity
                                ELSE 0
                            END
                        ) as total_hpp
                    FROM order_items oi
                    LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                    LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                    GROUP BY oi.order_id
                ) as hpp ON at.order_id = hpp.order_id
                GROUP BY at.order_id, oi.total_nominal, hpp.total_hpp
            )
            SELECT 
                COUNT(DISTINCT o.id) as total_all_orders,
                COUNT(DISTINCT ot.order_id) as total_filtered_orders,
                COALESCE(SUM(ot.order_total_value), 0) as total_value,
                COALESCE(SUM(ot.order_total_nominal), 0) as total_nominal,
                COALESCE(SUM(ot.order_total_hpp), 0) as total_hpp,
                COALESCE(SUM(ot.order_total_volume), 0) as total_volume
            FROM orders o
            LEFT JOIN order_totals ot ON ot.order_id = o.id
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                " . ($selectedStatus ? "AND (
                    o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                )" : "") . "";
        
        $overallSummaryResult = DB::selectOne($overallSummaryQuery);
        $totalAllOrders = (int)($overallSummaryResult->total_all_orders ?? 0);
        $totalFilteredOrders = (int)($overallSummaryResult->total_filtered_orders ?? 0);
        $totalValue = (float)($overallSummaryResult->total_value ?? 0);
        $totalNominal = (float)($overallSummaryResult->total_nominal ?? 0);
        $totalHpp = (float)($overallSummaryResult->total_hpp ?? 0);
        $totalVolume = (float)($overallSummaryResult->total_volume ?? 0);
        
        $summary = [
            'total_orders' => $totalFilteredOrders, // Only count orders with financial transactions
            'total_value' => $totalValue,
            'total_nominal' => $totalNominal,
            'total_hpp' => $totalHpp,
            'total_gross_profit' => $totalValue - $totalHpp,
            'total_volume' => $totalVolume,
            'avg_order_value' => $totalFilteredOrders > 0 ? $totalValue / $totalFilteredOrders : 0,
            'avg_order_volume' => $totalFilteredOrders > 0 ? $totalVolume / $totalFilteredOrders : 0,
            'total_all_orders' => $totalAllOrders,
            'total_filtered_orders' => $totalFilteredOrders,
            'percent_filtered' => $totalAllOrders > 0 ? round(($totalFilteredOrders / $totalAllOrders) * 100, 1) : 0,
        ];
        
        // Query untuk platformSummary: GROUP BY platform_id
        $platformSummaryQuery = "
            WITH all_transactions AS (
                " . $allTransactionsSQL . "
            ),
            order_totals AS (
                SELECT 
                    at.order_id,
                    SUM(at.saldo_masuk) as order_total_value,
                    SUM(at.qty) as order_total_volume,
                    COALESCE(oi.total_nominal, 0) as order_total_nominal,
                    COALESCE(hpp.total_hpp, 0) as order_total_hpp
                FROM all_transactions at
                LEFT JOIN (
                    SELECT 
                        order_id,
                        SUM(price_after_discount * quantity) as total_nominal
                    FROM order_items
                    GROUP BY order_id
                ) as oi ON at.order_id = oi.order_id
                LEFT JOIN (
                    SELECT 
                        oi.order_id,
                        SUM(
                            CASE 
                                WHEN oi.warehouse_stock_id IS NOT NULL 
                                    AND ws.id IS NOT NULL 
                                    AND pd.id IS NOT NULL 
                                    AND pd.qty > 0 
                                THEN (pd.subtotal / pd.qty) * oi.quantity
                                ELSE 0
                            END
                        ) as total_hpp
                    FROM order_items oi
                    LEFT JOIN warehouse_stock ws ON oi.warehouse_stock_id = ws.id
                    LEFT JOIN penerimaan_detail pd ON ws.penerimaan_detail_id = pd.id
                    GROUP BY oi.order_id
                ) as hpp ON at.order_id = hpp.order_id
                GROUP BY at.order_id, oi.total_nominal, hpp.total_hpp
            )
            SELECT 
                o.platform_id,
                p.name as platform,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(ot.order_total_value), 0) as total_value,
                COALESCE(SUM(ot.order_total_nominal), 0) as total_nominal,
                COALESCE(SUM(ot.order_total_hpp), 0) as total_hpp,
                COALESCE(SUM(ot.order_total_volume), 0) as total_volume
            FROM orders o
            INNER JOIN order_totals ot ON ot.order_id = o.id
            LEFT JOIN platforms p ON p.id = o.platform_id
            WHERE o.tanggal BETWEEN " . DB::getPdo()->quote($startDate) . " AND " . DB::getPdo()->quote($endDate) . "
                " . ($selectedPlatform ? "AND o.platform_id = " . intval($selectedPlatform) : "") . "
                " . ($selectedStatus ? "AND (
                    o.status_hari = " . DB::getPdo()->quote($selectedStatus) . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote($selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus . ',%') . "
                    OR o.status_hari LIKE " . DB::getPdo()->quote('%,' . $selectedStatus) . "
                )" : "") . "
            GROUP BY o.platform_id, p.name";
        
        $platformSummaryResults = collect(DB::select($platformSummaryQuery));
        $platformSummary = $platformSummaryResults->map(function($row) {
            $totalValue = (float)($row->total_value ?? 0);
            $totalHpp = (float)($row->total_hpp ?? 0);
            return [
                'platform' => trim($row->platform ?? 'Unknown'),
                'order_count' => (int)($row->order_count ?? 0),
                'total_value' => $totalValue,
                'total_nominal' => (float)($row->total_nominal ?? 0),
                'total_hpp' => $totalHpp,
                'total_gross_profit' => $totalValue - $totalHpp,
                'total_volume' => (float)($row->total_volume ?? 0),
            ];
        })->values()->toArray();
        
        return view('analytics.sales_by_status_day', [
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $selectedPlatform,
            'selectedStatus' => $selectedStatus,
            'viewMode' => $viewMode,
            'summary' => $summary,
            'platformSummary' => $platformSummary,
            'allStatuses' => $allStatuses,
            'dayNames' => $dayNames,
            'statusDayMatrix' => $statusDayMatrix,
            'statusSummary' => $statusSummary,
            'dayOfWeekSummary' => $dayOfWeekSummary,
        ]);
    }
    public function monthlySalesSummaryReport(Request $request)
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '512M');
        
        // Get platforms for filter
        $platforms = Platform::all();
        
        // Set default date range to TODAY only for faster loading
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
                case '6months':
                    $startDate = now()->subMonths(6)->format('Y-m-d');
                    break;
                case '1year':
                    $startDate = now()->subYear()->format('Y-m-d');
                    break;
            }
        }
        
        // View mode (volume or value)
        $viewMode = $request->input('view_mode', 'value');
        
        // Build the query for orders with optimized loading
        $query = Order::with([
            'platform', 
            'orderItems.warehouseStock.penerimaanDetail',
            'shopeeFinancialTransactions' => function($q) {
                $q->where('saldo_masuk', '>', 0);
            },
            'shopee2FinancialTransactions' => function($q) {
                $q->where('saldo_masuk', '>', 0);
            },
            'tiktokFinancialTransactions' => function($q) {
                $q->where('saldo_masuk', '>', 0);
            },
            'tiktok2FinancialTransactions' => function($q) {
                $q->where('saldo_masuk', '>', 0);
            },
        ]);
        
        // Apply date filter
        $query->whereBetween('tanggal', [$startDate, $endDate]);
        
        // Apply platform filter if set
        $selectedPlatform = null;
        if ($request->filled('platform_id')) {
            $selectedPlatform = $request->input('platform_id');
            $query->where('platform_id', $selectedPlatform);
        }
        
        // Use chunking for large datasets to prevent memory issues
        $allOrders = collect();
        $query->chunk(1000, function($orders) use (&$allOrders) {
            $allOrders = $allOrders->merge($orders);
        });
        
        // Filter orders to only include those with valid financial transactions (having saldo_masuk)
        $orders = $allOrders->filter(function($order) {
            $hasValidTransaction = false;
            
            // Check if order has any financial transactions with saldo_masuk
            if ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0)->count() > 0) {
                $hasValidTransaction = true;
            } elseif ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0)->count() > 0) {
                $hasValidTransaction = true;
            }
            
            return $hasValidTransaction;
        });
        
        // Helper function to calculate HPP from penerimaan detail
        $calculateHpp = function($penerimaanDetail) {
            if (!$penerimaanDetail) {
                return 0;
            }
            
            $hppAsli = $penerimaanDetail->harga_hpp ?? 0;
            $hppSetelahDiskon = $hppAsli;
            
            // Apply percentage discounts in sequence
            for ($i = 1; $i <= 5; $i++) {
                $diskonPersen = $penerimaanDetail->{"diskon_persen_{$i}"} ?? 0;
                if ($diskonPersen > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $diskonPersen / 100);
                }
            }
            
            // Apply nominal discounts
            for ($i = 1; $i <= 5; $i++) {
                $diskonNominal = $penerimaanDetail->{"diskon_nominal_{$i}"} ?? 0;
                $hppSetelahDiskon -= $diskonNominal;
            }
            
            // Ensure price doesn't go negative
            return max(0, $hppSetelahDiskon);
        };
        
        // Group orders by year-month and calculate monthly totals
        $monthlySummary = $orders->groupBy(function($order) {
            return Carbon::parse($order->tanggal)->format('Y-m');
        })->map(function($monthOrders, $yearMonth) use ($calculateHpp) {
            // Calculate total value from financial transactions
            $totalValue = 0;
            $totalNominal = 0;
            $totalVolume = 0;
            $totalHpp = 0;
            
            foreach ($monthOrders as $order) {
                // Calculate nominal and HPP from order_items
                foreach ($order->orderItems as $item) {
                    $totalNominal += $item->price_after_discount * $item->quantity;
                    
                    // Calculate HPP for this item
                    if ($item->warehouseStock && $item->warehouseStock->penerimaanDetail) {
                        $hppPerUnit = $calculateHpp($item->warehouseStock->penerimaanDetail);
                        $totalHpp += $hppPerUnit * $item->quantity;
                    }
                }
                
                // Process Shopee transactions
                foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                    $totalValue += $transaction->saldo_masuk;
                    $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
                }
                
                // Process TikTok transactions
                foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                    $totalValue += $transaction->saldo_masuk;
                    $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
                }
            }
            
            $totalGrossProfit = $totalValue - $totalHpp;
            
            $monthYear = Carbon::createFromFormat('Y-m', $yearMonth);
            
            return [
                'year_month' => $yearMonth,
                'month_name' => $monthYear->format('M Y'),
                'order_count' => $monthOrders->count(),
                'total_value' => $totalValue,
                'total_nominal' => $totalNominal,
                'total_hpp' => $totalHpp,
                'total_gross_profit' => $totalGrossProfit,
                'total_volume' => $totalVolume,
                'avg_value' => $monthOrders->count() > 0 ? $totalValue / $monthOrders->count() : 0,
                'avg_volume' => $monthOrders->count() > 0 ? $totalVolume / $monthOrders->count() : 0,
                'value_volume_ratio' => $totalVolume > 0 ? $totalValue / $totalVolume : 0,
            ];
        })->sortBy('year_month');
        
        // Calculate total saldo masuk, nominal, HPP, and volume from financial transactions
        $totalValue = 0;
        $totalNominal = 0;
        $totalVolume = 0;
        $totalHpp = 0;
        
        foreach ($orders as $order) {
            // Calculate nominal and HPP from order_items
            foreach ($order->orderItems as $item) {
                $totalNominal += $item->price_after_discount * $item->quantity;
                
                // Calculate HPP for this item
                if ($item->warehouseStock && $item->warehouseStock->penerimaanDetail) {
                    $hppPerUnit = $calculateHpp($item->warehouseStock->penerimaanDetail);
                    $totalHpp += $hppPerUnit * $item->quantity;
                }
            }
            
            // Process Shopee transactions
            foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                $totalValue += $transaction->saldo_masuk;
                $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
            }
            
            // Process TikTok transactions
            foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                $totalValue += $transaction->saldo_masuk;
                $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
            }
        }
        
        $totalGrossProfit = $totalValue - $totalHpp;
        
        // Calculate overall summary
        $summary = [
            'total_orders' => $orders->count(),
            'total_value' => $totalValue,
            'total_nominal' => $totalNominal,
            'total_hpp' => $totalHpp,
            'total_gross_profit' => $totalGrossProfit,
            'total_volume' => $totalVolume,
            'avg_order_value' => $orders->count() > 0 ? 
                $totalValue / $orders->count() : 0,
            'avg_order_volume' => $orders->count() > 0 ?
                $totalVolume / $orders->count() : 0,
            'months_count' => $monthlySummary->count(),
            'avg_value_volume_ratio' => $totalVolume > 0 ? 
                $totalValue / $totalVolume : 0,
            'total_all_orders' => $allOrders->count(),
            'total_filtered_orders' => $orders->count(),
            'percent_filtered' => $allOrders->count() > 0 
                ? round(($orders->count() / $allOrders->count()) * 100, 1) 
                : 0,
        ];
        
        // Get summary by platform
        $platformSummary = [];
        if ($orders->count() > 0) {
            $platformSummary = $orders->groupBy('platform_id')->map(function($platformOrders, $platformId) use ($calculateHpp) {
                $platform = Platform::find($platformId);
                
                // Calculate total saldo masuk, nominal, HPP, and volume for this platform
                $totalValue = 0;
                $totalNominal = 0;
                $totalVolume = 0;
                $totalHpp = 0;
                
                foreach ($platformOrders as $order) {
                    // Calculate nominal and HPP from order_items
                    foreach ($order->orderItems as $item) {
                        $totalNominal += $item->price_after_discount * $item->quantity;
                        
                        // Calculate HPP for this item
                        if ($item->warehouseStock && $item->warehouseStock->penerimaanDetail) {
                            $hppPerUnit = $calculateHpp($item->warehouseStock->penerimaanDetail);
                            $totalHpp += $hppPerUnit * $item->quantity;
                        }
                    }
                    
                    // Add financial transactions - check all available transaction types
                    // Shopee transactions
                    foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                        $totalValue += $transaction->saldo_masuk;
                        $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
                    }
                    
                    // Shopee2 transactions
                    foreach ($order->shopee2FinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                        $totalValue += $transaction->saldo_masuk;
                        $totalVolume += 0; // Shopee2 doesn't have qty
                    }
                    
                    // TikTok transactions
                    foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                        $totalValue += $transaction->saldo_masuk;
                        $totalVolume += $transaction->qty > 0 ? $transaction->qty : 0;
                    }
                    
                    // TikTok2 transactions
                    foreach ($order->tiktok2FinancialTransactions->where('saldo_masuk', '>', 0) as $transaction) {
                        $totalValue += $transaction->saldo_masuk;
                        $totalVolume += 0; // TikTok2 doesn't have qty
                    }
                }
                
                $totalGrossProfit = $totalValue - $totalHpp;
                
                return [
                    'platform' => $platform ? $platform->name : 'Unknown',
                    'order_count' => $platformOrders->count(),
                    'total_value' => $totalValue,
                    'total_nominal' => $totalNominal,
                    'total_hpp' => $totalHpp,
                    'total_gross_profit' => $totalGrossProfit,
                    'total_volume' => $totalVolume,
                ];
            });
        }
        
        return view('analytics.monthly_sales_summary', [
            'monthlySummary' => $monthlySummary,
            'platforms' => $platforms,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedPlatform' => $selectedPlatform,
            'viewMode' => $viewMode,
            'summary' => $summary,
            'platformSummary' => $platformSummary,
        ]);
    }
    public function exportMonthlySalesSummary(Request $request)
    {
        // Get the same data as the view
        $platforms = Platform::all();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->subMonths(6)->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
                case '6months':
                    $startDate = now()->subMonths(6)->format('Y-m-d');
                    break;
                case '1year':
                    $startDate = now()->subYear()->format('Y-m-d');
                    break;
            }
        }

        // Build the query for orders (same logic as in the view method)
        $query = Order::with(['platform', 'items', 'financialTransactions']);
        
        if ($startDate && $endDate) {
            $query->whereBetween('order_date', [$startDate, $endDate]);
        }
        
        if ($selectedPlatform) {
            $query->where('platform_id', $selectedPlatform);
        }
        
        $orders = $query->get();
        
        // Filter orders that have financial transactions
        $validOrders = $orders->filter(function ($order) {
            return $order->financialTransactions->isNotEmpty();
        });

        // Group by month and calculate summary
        $monthlySummary = $validOrders->groupBy(function ($order) {
            return $order->order_date->format('Y-m');
        })->map(function ($monthOrders, $yearMonth) {
            $totalValue = $monthOrders->sum(function ($order) {
                return $order->financialTransactions->sum('nominal_fix');
            });
            
            $totalVolume = $monthOrders->sum(function ($order) {
                return $order->items->sum('quantity');
            });
            
            return [
                'year_month' => $yearMonth,
                'month_name' => Carbon::createFromFormat('Y-m', $yearMonth)->format('M Y'),
                'order_count' => $monthOrders->count(),
                'total_value' => $totalValue,
                'total_volume' => $totalVolume,
            ];
        })->sortBy('year_month')->values();

        // Calculate summary
        $summary = [
            'total_orders' => $validOrders->count(),
            'total_value' => $validOrders->sum(function ($order) {
                return $order->financialTransactions->sum('nominal_fix');
            }),
            'total_volume' => $validOrders->sum(function ($order) {
                return $order->items->sum('quantity');
            }),
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 ? $summary['total_value'] / $summary['total_orders'] : 0;
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 ? $summary['total_volume'] / $summary['total_orders'] : 0;

        $platformName = $selectedPlatform ? $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown' : null;
        
        $filename = 'analisis-saldo-masuk-bulanan-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new MonthlySalesSummaryExport($monthlySummary, $summary, $startDate, $endDate, $platformName),
            $filename,
            'Export Analisis Saldo Masuk Bulanan'
        );
    }

    /**
     * Export sales by day of week to Excel
     */
    public function exportSalesByDayOfWeek(Request $request)
    {
        // Get the same data as the view
        $platforms = Platform::all();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '7days':
                    $startDate = now()->subDays(7)->format('Y-m-d');
                    break;
                case '2weeks':
                    $startDate = now()->subWeeks(2)->format('Y-m-d');
                    break;
                case '1month':
                    $startDate = now()->subMonth()->format('Y-m-d');
                    break;
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
            }
        }

        // Build the query for orders
        $query = Order::with(['platform', 'items', 'financialTransactions']);
        
        if ($startDate && $endDate) {
            $query->whereBetween('order_date', [$startDate, $endDate]);
        }
        
        if ($selectedPlatform) {
            $query->where('platform_id', $selectedPlatform);
        }
        
        $orders = $query->get();
        
        // Filter orders that have financial transactions
        $validOrders = $orders->filter(function ($order) {
            return $order->financialTransactions->isNotEmpty();
        });

        // Initialize day of week summary
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin', 
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu'
        ];
        
        $dayOfWeekSummary = [];
        foreach ($dayNames as $dayNum => $dayName) {
            $dayOfWeekSummary[$dayNum] = [
                'day_name' => $dayName,
                'order_count' => 0,
                'total_value' => 0,
                'total_volume' => 0,
            ];
        }

        // Group by day of week
        foreach ($validOrders as $order) {
            $dayOfWeek = $order->order_date->dayOfWeek;
            
            $dayOfWeekSummary[$dayOfWeek]['order_count']++;
            $dayOfWeekSummary[$dayOfWeek]['total_value'] += $order->financialTransactions->sum('nominal_fix');
            $dayOfWeekSummary[$dayOfWeek]['total_volume'] += $order->items->sum('quantity');
        }

        // Calculate summary
        $summary = [
            'total_orders' => $validOrders->count(),
            'total_value' => $validOrders->sum(function ($order) {
                return $order->financialTransactions->sum('nominal_fix');
            }),
            'total_volume' => $validOrders->sum(function ($order) {
                return $order->items->sum('quantity');
            }),
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 ? $summary['total_value'] / $summary['total_orders'] : 0;
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 ? $summary['total_volume'] / $summary['total_orders'] : 0;

        $platformName = $selectedPlatform ? $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown' : null;
        
        $filename = 'analisis-saldo-masuk-per-hari-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new SalesByDayOfWeekExport($dayOfWeekSummary, $summary, $startDate, $endDate, $platformName),
            $filename,
            'Export Analisis Saldo Masuk Per Hari'
        );
    }

    /**
     * Export sales by date number to Excel
     */
    public function exportSalesByDateNumber(Request $request)
    {
        // Get the same data as the view
        $platforms = Platform::all();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        
        // Apply quick date range if set
        if ($request->filled('quick_range')) {
            $range = $request->input('quick_range');
            $endDate = now()->format('Y-m-d');
            
            switch ($range) {
                case '7days':
                    $startDate = now()->subDays(7)->format('Y-m-d');
                    break;
                case '2weeks':
                    $startDate = now()->subWeeks(2)->format('Y-m-d');
                    break;
                case '1month':
                    $startDate = now()->subMonth()->format('Y-m-d');
                    break;
                case '3months':
                    $startDate = now()->subMonths(3)->format('Y-m-d');
                    break;
            }
        }

        // Build the query for orders (mirror view logic)
        $query = Order::with([
            'platform',
            'orderItems',
            'shopeeFinancialTransactions',
            'tiktokFinancialTransactions',
        ]);

        // Date filter uses existing 'tanggal' column
        $query->whereBetween('tanggal', [$startDate, $endDate]);

        if ($selectedPlatform) {
            $query->where('platform_id', $selectedPlatform);
        }

        $allOrders = $query->get();

        // Keep only orders that have any financial transaction with saldo_masuk > 0
        $orders = $allOrders->filter(function($order) {
            return (
                $order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0)->count() > 0 ||
                $order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0)->count() > 0
            );
        });

        // Group orders by date number (1-31) and compute totals from transactions
        $grouped = $orders->groupBy(function($order) {
            return \Carbon\Carbon::parse($order->tanggal)->format('d');
        })->map(function($dateOrders, $dateNumber) {
            $totalValue = 0;
            $totalVolume = 0;
            foreach ($dateOrders as $order) {
                foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $t) {
                    $totalValue += $t->saldo_masuk;
                    $totalVolume += $t->qty > 0 ? $t->qty : 0;
                }
                foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $t) {
                    $totalValue += $t->saldo_masuk;
                    $totalVolume += $t->qty > 0 ? $t->qty : 0;
                }
            }
            return [
                'date_number' => $dateNumber,
                'order_count' => $dateOrders->count(),
                'total_value' => $totalValue,
                'total_volume' => $totalVolume,
            ];
        });

        // Create complete 01-31 array to keep rows consistent
        $dateNumberSummary = [];
        for ($i = 1; $i <= 31; $i++) {
            $key = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            if (isset($grouped[$key])) {
                $dateNumberSummary[$i] = $grouped[$key];
            } else {
                $dateNumberSummary[$i] = [
                    'date_number' => $key,
                    'order_count' => 0,
                    'total_value' => 0,
                    'total_volume' => 0,
                ];
            }
        }

        // Calculate overall summary identical to view
        $totalValue = 0;
        $totalVolume = 0;
        foreach ($orders as $order) {
            foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $t) {
                $totalValue += $t->saldo_masuk;
                $totalVolume += $t->qty > 0 ? $t->qty : 0;
            }
            foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $t) {
                $totalValue += $t->saldo_masuk;
                $totalVolume += $t->qty > 0 ? $t->qty : 0;
            }
        }

        $summary = [
            'total_orders' => $orders->count(),
            'total_value' => $totalValue,
            'total_volume' => $totalVolume,
        ];
         
        $summary['avg_order_value'] = $summary['total_orders'] > 0 ? $summary['total_value'] / $summary['total_orders'] : 0;
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 ? $summary['total_volume'] / $summary['total_orders'] : 0;
 
        $platformName = $selectedPlatform ? $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown' : null;
         
        $filename = 'analisis-saldo-masuk-per-tanggal-' . date('Y-m-d') . '.xlsx';
         
        return $this->queueExcelExport(
            new SalesByDateNumberExport($dateNumberSummary, $summary, $startDate, $endDate, $platformName),
            $filename,
            'Export Analisis Saldo Masuk Per Tanggal'
        );
    }

    /**
     * Export sales detail report to Excel
     * PERBAIKAN: Export ALL data (termasuk yang fully returned) dan pastikan qty retur terisi dengan benar
     */
    public function exportSalesDetailReport(Request $request)
    {
        // Increase memory limit and execution time for large exports
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        
        // Parse dates
        $startDate = $request->input('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->input('end_date') ?? now()->format('Y-m-d');
        $sortBy = $request->input('sort', 'date_newest');
        
        // Build filters array for SalesDetailQuery (excludeZeroQty = true by default)
        // Ini KUNCI: detail report HANYA menampilkan order dengan total qty > 0
        // (exclude order yang full retur), berbeda dengan sales-export-mapped
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'platform_id' => $request->input('platform_id'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'min_qty' => $request->input('min_qty'),
            'max_qty' => $request->input('max_qty'),
            'sort' => $sortBy,
        ];
        
        // Gunakan SalesDetailQuery untuk mendapatkan order IDs yang sudah difilter
        // excludeZeroQty = true (default): HANYA order dengan remaining_qty > 0
        $orderIds = collect(DB::select(SalesDetailQuery::build($filters, 100000, 1)))->pluck('id')->toArray();
        
        // Build the query for order items with eager loading to avoid N+1
        // HANYA order yang lolos filter (qty total > 0 setelah retur)
        $query = \App\Models\OrderItem::with([
            'order.platform',
            'order.orderItems.platformProduct', // Preload all order items for totals calculation
            'platformProduct.mappingBarang'
        ])->whereHas('order', function($q) use ($orderIds) {
            $q->withoutGlobalScope('mainCategory')
              ->whereIn('id', $orderIds);
        });
        
        // Apply sorting based on order
        // Use subquery to avoid DISTINCT + ORDER BY conflict
        switch ($sortBy) {
            case 'date_oldest':
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                      ->orderBy('orders.tanggal', 'asc')
                      ->orderBy('orders.id', 'asc')
                      ->orderBy('order_items.id', 'asc')
                      ->select('order_items.*');
                break;
            case 'value_highest':
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                      ->orderBy('orders.total', 'desc')
                      ->orderBy('orders.id', 'asc')
                      ->orderBy('order_items.id', 'asc')
                      ->select('order_items.*');
                break;
            case 'value_lowest':
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                      ->orderBy('orders.total', 'asc')
                      ->orderBy('orders.id', 'asc')
                      ->orderBy('order_items.id', 'asc')
                      ->select('order_items.*');
                break;
            case 'date_newest':
            default:
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                      ->orderBy('orders.tanggal', 'desc')
                      ->orderBy('orders.id', 'desc')
                      ->orderBy('order_items.id', 'desc')
                      ->select('order_items.*');
                break;
        }
        
        // Calculate summary menggunakan SalesDetailQuery (konsisten dengan view)
        // excludeZeroQty = true: HANYA order dengan remaining_qty > 0
        $summaryResult = DB::selectOne(SalesDetailQuery::buildSummary($filters));
        
        $summary = [
            'total_orders' => (int)($summaryResult->total_orders ?? 0),
            'total_value' => (float)($summaryResult->total_value ?? 0),
            'total_volume' => (float)($summaryResult->total_volume ?? 0),
            'avg_order_value' => (float)($summaryResult->avg_order_value ?? 0),
            'avg_order_volume' => (float)($summaryResult->avg_order_volume ?? 0),
        ];
        
        $filename = 'laporan-detail-penjualan-' . date('Y-m-d') . '.xlsx';
        
        // Pass query instead of collection to use chunking
        return $this->queueExcelExport(
            new SalesDetailReportExport($query, $summary, $startDate, $endDate, $request->platform_id),
            $filename,
            'Export Laporan Detail Penjualan',
            true
        );
    }

    /**
     * Export sales by platform to Excel
     */
    public function exportSalesByPlatform(Request $request)
    {
        // Use the same logic as the view method to ensure consistency
        $platforms = Platform::all();
        
        // Set default date range - Default ke hari ini untuk mencegah memory exhaustion
        $startDate = $request->filled('start_date') ? $request->input('start_date') : Carbon::today()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : Carbon::today()->format('Y-m-d');
        
        // Build the query for orders - ensure we're only getting online orders (with platform_id)
        $query = Order::withoutGlobalScope('mainCategory')->with([
            'platform',
            'orderItems',
            'orderItems.platformProduct.mappingBarang' => function($query) {
                $query->where('is_active', true);
            },
            'orderItems.platformProduct.mappingBarang.product',
        ])->whereNotNull('platform_id') // Ensure only online orders
          ->whereHas('orderItems', function($q) {
              $q->where('quantity', '>', 0);
          });
        
        // Apply date filter (selalu ada karena ada default)
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
            $query->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
        } catch (\Exception $e) {
            // Jika format tanggal invalid, gunakan hari ini sebagai fallback
            $query->whereDate('tanggal', Carbon::today());
        }
        
        // Apply platform filter if set
        if ($request->filled('platform_id')) {
            $query->where('platform_id', $request->platform_id);
        }
        
        // Determine sort order
        $sortBy = $request->input('sort', 'date_newest');
        
        // Get the orders without sorting first
        $orders = $query->get();
        
        // Sort orders based on user selection
        switch ($sortBy) {
            case 'value_highest':
                $orders = $orders->sortByDesc(function($order) {
                    return $order->orderItems->where('quantity', '>', 0)->sum(function($item) {
                        return $item->price_after_discount * $item->quantity;
                    });
                });
                break;
            case 'value_lowest':
                $orders = $orders->sortBy(function($order) {
                    return $order->orderItems->where('quantity', '>', 0)->sum(function($item) {
                        return $item->price_after_discount * $item->quantity;
                    });
                });
                break;
            case 'volume_highest':
                $orders = $orders->sortByDesc(function($order) {
                    return $order->orderItems->where('quantity', '>', 0)->sum('quantity');
                });
                break;
            case 'volume_lowest':
                $orders = $orders->sortBy(function($order) {
                    return $order->orderItems->where('quantity', '>', 0)->sum('quantity');
                });
                break;
            case 'date_newest':
                $orders = $orders->sortByDesc('tanggal');
                break;
            case 'date_oldest':
                $orders = $orders->sortBy('tanggal');
                break;
            default:
                $orders = $orders->sortByDesc('tanggal');
                break;
        }
        
        // Calculate total value and volume for each order
        $orders = $orders->map(function($order) {
            $order->total_value = $order->orderItems->where('quantity', '>', 0)->sum(function($item) {
                return $item->price_after_discount * $item->quantity;
            });
            $order->total_volume = $order->orderItems->where('quantity', '>', 0)->sum('quantity');
            return $order;
        });

        $orderIds = $orders->pluck('id')->toArray();
        $totalReturns = empty($orderIds)
            ? 0
            : ReturPenjualan::whereIn('order_id', $orderIds)->count();

        $validOrders = $orders;

        $summary = [
            'total_orders' => $validOrders->count(),
            'total_value' => $validOrders->sum('total_value'),
            'total_volume' => $validOrders->sum('total_volume'),
            'avg_order_value' => $validOrders->count() > 0 ? 
                $validOrders->sum('total_value') / $validOrders->count() : 0,
            'avg_order_volume' => $validOrders->count() > 0 ? 
                $validOrders->sum('total_volume') / $validOrders->count() : 0,
            'total_returns' => $totalReturns,
        ];
        
        $filename = 'daftar-pesanan-platform-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new SalesByPlatformExport($validOrders->values(), $summary, $startDate, $endDate, $request->platform_id),
            $filename,
            'Export Daftar Pesanan Platform'
        );
    }

    /**
     * Export sales by status and day to Excel
     */
    public function exportSalesByStatusDay(Request $request)
    {
        // Mirror logic from salesByStatusAndDayReport so Excel matches the page
        $platforms = Platform::all();
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        $selectedStatus = $request->input('status');

        $query = Order::with([
            'platform',
            'orderItems',
            'shopeeFinancialTransactions',
            'tiktokFinancialTransactions',
        ]);

        $query->whereBetween('tanggal', [$startDate, $endDate]);
        if ($selectedPlatform) {
            $query->where('platform_id', $selectedPlatform);
        }
        if ($selectedStatus) {
            $query->where(function($q) use ($selectedStatus) {
                $q->where('status_hari', $selectedStatus)
                  ->orWhere('status_hari', 'LIKE', $selectedStatus . ',%')
                  ->orWhere('status_hari', 'LIKE', '%,' . $selectedStatus . ',%')
                  ->orWhere('status_hari', 'LIKE', '%,' . $selectedStatus);
            });
        }

        $orders = $query->get();

        // Build list of statuses
        $rawStatuses = Order::distinct()->pluck('status_hari')->filter()->values()->toArray();
        $allStatuses = [];
        foreach ($rawStatuses as $status) {
            if (strpos($status, ',') !== false) {
                foreach (array_map('trim', explode(',', $status)) as $s) {
                    if (!empty($s) && !in_array($s, $allStatuses)) $allStatuses[] = $s;
                }
            } else {
                if (!in_array($status, $allStatuses)) $allStatuses[] = $status;
            }
        }
        sort($allStatuses);

        // Init matrix
        $statusDayMatrix = [];
        foreach ($allStatuses as $status) {
            $statusDayMatrix[$status] = [];
            foreach (range(0, 6) as $dayNum) {
                $statusDayMatrix[$status][$dayNum] = [
                    'order_count' => 0,
                    'total_value' => 0,
                    'total_volume' => 0,
                ];
            }
        }

        // Fill matrix based on financial transactions (saldo_masuk, qty)
        foreach ($orders as $order) {
            $dayOfWeek = \Carbon\Carbon::parse($order->tanggal)->dayOfWeek;
            $totalValue = 0; $totalVolume = 0;
            foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $t) { $totalValue += $t->saldo_masuk; $totalVolume += max(0, $t->qty); }
            foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $t) { $totalValue += $t->saldo_masuk; $totalVolume += max(0, $t->qty); }

            if (!empty($order->status_hari)) {
                if (strpos($order->status_hari, ',') !== false) {
                    foreach (array_map('trim', explode(',', $order->status_hari)) as $s) {
                        if (!empty($s) && isset($statusDayMatrix[$s][$dayOfWeek])) {
                            $statusDayMatrix[$s][$dayOfWeek]['order_count']++;
                            $statusDayMatrix[$s][$dayOfWeek]['total_value'] += $totalValue;
                            $statusDayMatrix[$s][$dayOfWeek]['total_volume'] += $totalVolume;
                        }
                    }
                } else {
                    $s = $order->status_hari;
                    if (isset($statusDayMatrix[$s][$dayOfWeek])) {
                        $statusDayMatrix[$s][$dayOfWeek]['order_count']++;
                        $statusDayMatrix[$s][$dayOfWeek]['total_value'] += $totalValue;
                        $statusDayMatrix[$s][$dayOfWeek]['total_volume'] += $totalVolume;
                    }
                }
            }
        }

        // Build summary identical to view
        $totalValue = 0; $totalVolume = 0;
        foreach ($orders as $order) {
            foreach ($order->shopeeFinancialTransactions->where('saldo_masuk', '>', 0) as $t) { $totalValue += $t->saldo_masuk; $totalVolume += max(0, $t->qty); }
            foreach ($order->tiktokFinancialTransactions->where('saldo_masuk', '>', 0) as $t) { $totalValue += $t->saldo_masuk; $totalVolume += max(0, $t->qty); }
        }
        $summary = [
            'total_orders' => $orders->count(),
            'total_value' => $totalValue,
            'total_volume' => $totalVolume,
        ];

        // Aggregate by status only (sum across all days) for exporter
        $rows = [];
        foreach ($statusDayMatrix as $status => $byDay) {
            $orderCount = 0; $totalVal = 0; $totalVol = 0;
            foreach ($byDay as $data) {
                $orderCount += $data['order_count'] ?? 0;
                $totalVal += $data['total_value'] ?? 0;
                $totalVol += $data['total_volume'] ?? 0;
            }
            $rows[] = [
                'status' => $status,
                'order_count' => $orderCount,
                'total_value' => $totalVal,
                'total_volume' => $totalVol,
                'avg_order_value' => $orderCount > 0 ? $totalVal / $orderCount : 0,
                'avg_order_volume' => $orderCount > 0 ? $totalVol / $orderCount : 0,
            ];
        }

        $platformName = $selectedPlatform ? ($platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown') : null;
        $filename = 'laporan-penjualan-status-hari-' . date('Y-m-d') . '.xlsx';
        return $this->queueExcelExport(
            new SalesByStatusDayExport($rows, $summary, $startDate, $endDate, $platformName, $selectedStatus, $request),
            $filename,
            'Export Laporan Penjualan Status Hari'
        );
    }

}
