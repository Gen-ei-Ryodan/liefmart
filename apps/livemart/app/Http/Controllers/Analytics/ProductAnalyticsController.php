<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Platform;
use App\Models\PlatformProduct;
use App\Models\Product;
use App\Traits\QueueExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesByMasterProductExport;
use App\Exports\SalesByPlatformProductExport;
use App\Exports\ProdukInternalTerlarisExport;
use App\Exports\ProdukPlatformTerlarisExport;
use App\Models\SubBrand;
use App\Models\ProductCategory;
use App\Models\ProductType;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ReturPenjualan;
use App\Models\OrderItem;

class ProductAnalyticsController extends Controller
{
    use QueueExport;
    private function calculateAverageCostForProduct($productId)
    {
        // Weighted average cost from penerimaan_detail records
        $details = \App\Models\PenerimaanDetail::where('product_id', $productId)
            ->whereHas('penerimaan') // ensure linked good receipt exists
            ->get(['qty', 'harga_hpp']);

        if ($details->isEmpty()) {
            return 0; // No purchase data available
        }

        $totalCost = 0;
        $totalQuantity = 0;

        foreach ($details as $detail) {
            $qty = (float) $detail->qty;
            $hpp = (float) $detail->harga_hpp;
            $totalCost += $hpp * $qty;
            $totalQuantity += $qty;
        }

        return $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;
    }


    /**
     * Return SubBrands filtered by selected Brand IDs (JSON)
     * Enhanced with AJAX search support and limit
     */
    public function getSubBrands(Request $request)
    {
        $brandIds = $request->input('brand_ids', $request->input('brand_ids', []));
        $search = $request->input('search', '');
        
        if (!is_array($brandIds)) {
            $brandIds = array_filter(explode(',', (string) $brandIds));
        }

        // Convert brand IDs to integers
        $brandIds = array_map('intval', array_filter($brandIds));

        if (empty($brandIds)) {
            return response()->json([]);
        }

        $query = \App\Models\SubBrand::query();
        $query->whereIn('brand_id', $brandIds);
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Limit to 50 results for performance
        $subBrands = $query->orderBy('name')->limit(50)->get(['id', 'name', 'brand_id']);
        
        // Ensure we return proper JSON array, filter out any null/empty values
        $result = $subBrands->filter(function($item) {
            return $item->id && $item->name;
        })->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'brand_id' => $item->brand_id
            ];
        })->values();
        
        return response()->json($result);
    }

    /**
     * Return Product Types filtered by selected Category IDs (JSON)
     * Enhanced with AJAX search support and limit
     */
    public function getProductTypes(Request $request)
    {
        $categoryIds = $request->input('category_ids', []);
        $search = $request->input('search', '');
        
        if (!is_array($categoryIds)) {
            $categoryIds = array_filter(explode(',', (string) $categoryIds));
        }
        if (empty($categoryIds)) {
            return response()->json([]);
        }
        
        $query = \App\Models\ProductType::whereIn('product_category_id', $categoryIds);
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        // Limit to 50 results for performance
        $types = $query->orderBy('name')->limit(50)->get(['id', 'name', 'product_category_id']);
        return response()->json($types);
    }

    /**
     * Return Product Sizes filtered by selected Type IDs (JSON)
     * Enhanced with AJAX search support and limit
     */
    public function getProductSizes(Request $request)
    {
        $typeIds = $request->input('type_ids', []);
        $search = $request->input('search', '');
        
        if (!is_array($typeIds)) {
            $typeIds = array_filter(explode(',', (string) $typeIds));
        }
        if (empty($typeIds)) {
            return response()->json([]);
        }
        
        $query = \App\Models\ProductSize::whereIn('product_type_id', $typeIds);
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        // Limit to 50 results for performance
        $sizes = $query->orderBy('name')->limit(50)->get(['id', 'name', 'product_type_id']);
        return response()->json($sizes);
    }

    /**
     * Return Product Variants filtered by selected Size IDs (JSON)
     * Enhanced with AJAX search support and limit
     */
    public function getProductVariants(Request $request)
    {
        $sizeIds = $request->input('size_ids', []);
        $search = $request->input('search', '');
        
        if (!is_array($sizeIds)) {
            $sizeIds = array_filter(explode(',', (string) $sizeIds));
        }
        if (empty($sizeIds)) {
            return response()->json([]);
        }
        
        $query = \App\Models\ProductVariant::whereIn('product_size_id', $sizeIds);
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        // Limit to 50 results for performance
        $variants = $query->orderBy('name')->limit(50)->get(['id', 'name', 'product_size_id']);
        return response()->json($variants);
    }

    /**
     * Return Product Categories filtered by selected SubBrand IDs (JSON)
     * Enhanced with AJAX search support and limit
     */
    public function getProductCategories(Request $request)
    {
        $subBrandIds = $request->input('sub_brand_ids', []);
        $search = $request->input('search', '');
        
        if (!is_array($subBrandIds)) {
            $subBrandIds = array_filter(explode(',', (string) $subBrandIds));
        }
        if (empty($subBrandIds)) {
            return response()->json([]);
        }

        // Find distinct category IDs used by products under the selected sub-brands
        $categoryIds = \App\Models\Product::whereIn('sub_brand_id', $subBrandIds)
            ->whereNotNull('product_category_id')
            ->distinct()
            ->pluck('product_category_id')
            ->toArray();

        if (empty($categoryIds)) {
            return response()->json([]);
        }

        $query = \App\Models\ProductCategory::whereIn('id', $categoryIds);
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Limit to 50 results for performance
        $categories = $query->orderBy('name')->limit(50)->get(['id','name']);

        return response()->json($categories);
    }



    /**
     * Export sales by platform to Excel
     */
    public function produkPlatformTerlaris(Request $request)
    {
        $platforms = Platform::all();
        
        // Set default date range (today if not provided)
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'quantity_highest');
        $limit = $request->input('limit', 100);
        
        // Parse dates
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
            $startDate = $startDateCarbon->format('Y-m-d');
            $endDate = $endDateCarbon->format('Y-m-d');
        }
        
        // Build main query with returns calculated in database (subquery)
        // FIXED: Exclude orders that have returns from order_count
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
            ->join('platforms', 'platform_products.platform_id', '=', 'platforms.id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            // Exclude orders that have returns
            ->whereNotExists(function($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('retur_penjualans')
                    ->whereColumn('retur_penjualans.order_id', 'orders.id')
                    ->whereIn('retur_penjualans.status', ['draft', 'selesai']);
            })
            ->select(
                'platform_products.id as platform_product_id',
                'platform_products.platform_product_name',
                'platform_products.variant',
                'platforms.id as platform_id',
                'platforms.name as platform_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.price_after_discount * order_items.quantity) as total_value'),
                // Subquery untuk hitung retur (individual units) - from ALL orders in period
                DB::raw('(
                    SELECT COALESCE(SUM(rpd.qty), 0)
                    FROM retur_penjualan_details rpd
                    JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                    JOIN order_items oi2 ON rpd.order_item_id = oi2.id
                    JOIN orders o2 ON oi2.order_id = o2.id
                    WHERE oi2.platform_product_id = platform_products.id
                      AND rp.status IN ("draft", "selesai")
                      AND o2.tanggal BETWEEN "' . $startDateCarbon->format('Y-m-d H:i:s') . '" 
                      AND "' . $endDateCarbon->format('Y-m-d H:i:s') . '"
                ) as qty_retur_individual'),
                // Subquery untuk package quantity (untuk konversi)
                DB::raw('(
                    SELECT COALESCE(SUM(mb.quantity), 1)
                    FROM mapping_barangs mb
                    WHERE mb.platform_product_id = platform_products.id
                      AND mb.is_active = 1
                ) as package_quantity')
            )
            ->groupBy('platform_products.id', 'platform_products.platform_product_name', 'platform_products.variant', 'platforms.id', 'platforms.name');
        
        // Apply platform filter
        if ($selectedPlatform) {
            $query->where('platforms.id', $selectedPlatform);
        }
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('platform_products.platform_product_name', 'like', "%$search%")
                  ->orWhere('platform_products.variant', 'like', "%$search%");
            });
        }
        
        // Apply sorting at database level
        switch ($sortBy) {
            case 'quantity_lowest':
                $query->orderByRaw('(SUM(order_items.quantity) - (qty_retur_individual / package_quantity)) ASC');
                break;
            case 'value_highest':
                $query->orderByRaw('SUM(order_items.price_after_discount * order_items.quantity) DESC');
                break;
            case 'value_lowest':
                $query->orderByRaw('SUM(order_items.price_after_discount * order_items.quantity) ASC');
                break;
            case 'order_count_highest':
                $query->orderByRaw('COUNT(DISTINCT order_items.order_id) DESC');
                break;
            case 'order_count_lowest':
                $query->orderByRaw('COUNT(DISTINCT order_items.order_id) ASC');
                break;
            case 'quantity_highest':
            default:
                $query->orderByRaw('(SUM(order_items.quantity) - (qty_retur_individual / package_quantity)) DESC');
                break;
        }
        
        // Paginate at database level (no get() + manual pagination)
        $currentPage = $request->input('page', 1);
        $perPage = $limit;
        
        // Get total count for pagination
        $totalCount = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->count();
        
        // Apply pagination
        $products = $query->skip(($currentPage - 1) * $perPage)
            ->take($perPage)
            ->get();
        
        // Transform results (calculate net quantity in PHP - unavoidable for division)
        // PERBAIKAN: order_items.quantity sudah dikurangi retur, jadi:
        // - total_quantity (dari order_items) = NET (setelah retur)
        // - TERJUAL (original) = total_quantity + qty_retur
        $items = $products->map(function($product) {
            $qtyReturPackage = $product->package_quantity > 0 
                ? $product->qty_retur_individual / $product->package_quantity 
                : $product->qty_retur_individual;
            
            // total_quantity dari order_items sudah adalah NET (setelah retur)
            $netQuantity = (float) $product->total_quantity;
            
            // TERJUAL (original sebelum retur) = NET + RETUR
            $totalQuantityOriginal = $netQuantity + (float) $qtyReturPackage;
            
            return [
                'platform_product_id' => $product->platform_product_id,
                'platform_product_name' => $product->platform_product_name,
                'variant' => $product->variant ?? '-',
                'platform_id' => $product->platform_id,
                'platform_name' => $product->platform_name,
                'total_quantity' => (float) $totalQuantityOriginal, // TERJUAL (sebelum retur)
                'qty_retur' => (float) $qtyReturPackage,
                'net_quantity' => (float) $netQuantity, // NET (setelah retur)
                'order_count' => (int) $product->order_count,
                'total_value' => (float) $product->total_value,
            ];
        });
        
        // Create paginator
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $totalCount,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Calculate total orders correctly (unique orders, exclude returns) - same logic as salesByPlatformReport
        $baseOrderQuery = Order::withoutGlobalScope('mainCategory')
            ->whereNotNull('platform_id')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
        
        if ($selectedPlatform) {
            $baseOrderQuery->where('platform_id', $selectedPlatform);
        }
        
        // Apply search filter to orders (if search is used, filter orders that have matching products)
        if ($search) {
            $baseOrderQuery->whereHas('orderItems.platformProduct', function($q) use ($search) {
                $q->where('platform_product_name', 'like', "%$search%")
                  ->orWhere('variant', 'like', "%$search%");
            });
        }
        
        // Get all order IDs matching filters
        $allOrderIds = $baseOrderQuery->pluck('id')->toArray();
        
        // Get order IDs that have returns (exclude them)
        $orderIdsWithReturn = [];
        if (!empty($allOrderIds)) {
            $orderIdsWithReturn = ReturPenjualan::whereIn('order_id', $allOrderIds)
                ->pluck('order_id')
                ->unique()
                ->toArray();
        }
        
        // Calculate unique valid orders (excluding returns)
        $totalOrders = count($allOrderIds) - count($orderIdsWithReturn);
        
        // Get valid order IDs (excluding returns)
        $validOrderIds = array_diff($allOrderIds, $orderIdsWithReturn);
        
        // Get total returns count
        $totalReturnsCount = !empty($allOrderIds) 
            ? ReturPenjualan::whereIn('order_id', $allOrderIds)->count() 
            : 0;
        
        // Calculate total_value and total_volume from valid orders only (same logic as salesByPlatformReport)
        // FIXED: Count distinct platform_products.id correctly
        $summaryData = null;
        if (!empty($validOrderIds)) {
            // Get total products (distinct count)
            $totalProductsCount = DB::table('order_items')
                ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
                ->whereIn('order_items.order_id', $validOrderIds)
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('platform_products.platform_id', $selectedPlatform);
                })
                ->when($search, function($q) use ($search) {
                    $q->where(function($query) use ($search) {
                        $query->where('platform_products.platform_product_name', 'like', "%$search%")
                              ->orWhere('platform_products.variant', 'like', "%$search%");
                    });
                })
                ->distinct()
                ->count('platform_products.id');
            
            // Get total quantity and value
            $summaryAggregates = DB::table('order_items')
                ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
                ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_quantity_sold')
                ->selectRaw('COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0) as total_value')
                ->whereIn('order_items.order_id', $validOrderIds)
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('platform_products.platform_id', $selectedPlatform);
                })
                ->when($search, function($q) use ($search) {
                    $q->where(function($query) use ($search) {
                        $query->where('platform_products.platform_product_name', 'like', "%$search%")
                              ->orWhere('platform_products.variant', 'like', "%$search%");
                    });
                })
                ->first();
            
            $summaryData = (object)[
                'total_products' => $totalProductsCount,
                'total_quantity_sold' => $summaryAggregates->total_quantity_sold,
                'total_value' => $summaryAggregates->total_value
            ];
        } else {
            $summaryData = (object)[
                'total_products' => 0,
                'total_quantity_sold' => 0,
                'total_value' => 0
            ];
        }
        
        // Calculate total returns using database query (no collection looping)
        $totalReturnsData = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                $q->where('platform_products.platform_id', $selectedPlatform);
            })
            ->when($search, function($q) use ($search) {
                $q->where(function($query) use ($search) {
                    $query->where('platform_products.platform_product_name', 'like', "%$search%")
                          ->orWhere('platform_products.variant', 'like', "%$search%");
                });
            })
            ->selectRaw('
                SUM(
                    (
                        SELECT COALESCE(SUM(rpd.qty), 0)
                        FROM retur_penjualan_details rpd
                        JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                        WHERE rpd.order_item_id = order_items.id
                          AND rp.status IN ("draft", "selesai")
                    ) / (
                        SELECT COALESCE(SUM(mb.quantity), 1)
                        FROM mapping_barangs mb
                        WHERE mb.platform_product_id = order_items.platform_product_id
                          AND mb.is_active = 1
                    )
                ) as total_returns
            ')
            ->first();
        
        $totalReturnsQty = $totalReturnsData ? (float) $totalReturnsData->total_returns : 0;
        
        // Calculate returns from valid orders only (for net quantity calculation)
        $returnsFromValidOrders = 0;
        if (!empty($validOrderIds)) {
            $returnsValidData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
                ->whereIn('order_items.order_id', $validOrderIds)
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('platform_products.platform_id', $selectedPlatform);
                })
                ->when($search, function($q) use ($search) {
                    $q->where(function($query) use ($search) {
                        $query->where('platform_products.platform_product_name', 'like', "%$search%")
                              ->orWhere('platform_products.variant', 'like', "%$search%");
                    });
                })
                ->selectRaw('
                    SUM(
                        (
                            SELECT COALESCE(SUM(rpd.qty), 0)
                            FROM retur_penjualan_details rpd
                            JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                            WHERE rpd.order_item_id = order_items.id
                              AND rp.status IN ("draft", "selesai")
                        ) / (
                            SELECT COALESCE(SUM(mb.quantity), 1)
                            FROM mapping_barangs mb
                            WHERE mb.platform_product_id = order_items.platform_product_id
                              AND mb.is_active = 1
                        )
                    ) as returns_from_valid
                ')
                ->first();
            
            $returnsFromValidOrders = $returnsValidData ? (float) $returnsValidData->returns_from_valid : 0;
        }
        
        // Calculate net quantity (total sold - returns) from valid orders
        $totalNetQuantity = (float) $summaryData->total_quantity_sold - (float) $returnsFromValidOrders;
        
        // Calculate summary - using data from valid orders only (same logic as salesByPlatformReport)
        $summary = [
            'total_products' => (int) $summaryData->total_products, // Unique platform products from valid orders
            'total_quantity' => max(0, $totalNetQuantity), // Net quantity (sold - returns) from valid orders
            'total_quantity_with_returns' => (float) $summaryData->total_quantity_sold, // Total quantity sold from valid orders (before retur)
            'total_returns' => (float) $totalReturnsQty, // Total returned quantity (in package units) from valid platform products
            'total_orders' => $totalOrders, // Unique orders excluding returns (same as salesByPlatformReport)
            'total_value' => (float) $summaryData->total_value, // Total value from valid orders only (same as salesByPlatformReport)
            'total_returns_count' => $totalReturnsCount, // Total count of returns
        ];
        
        return view('analytics.produk_platform_terlaris', compact(
            'platforms',
            'startDate',
            'endDate',
            'selectedPlatform',
            'search',
            'sortBy',
            'limit',
            'paginator',
            'summary'
        ));
    }

    /**
     * Produk Internal Terlaris - Menampilkan produk internal dengan jumlah terjual terbanyak
     * OPTIMIZED: Menggunakan database query untuk data utama, minimal eager loading
     */
    public function produkInternalTerlaris(Request $request)
    {
        $platforms = Platform::all();
        
        // Set default date range (today if not provided)
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'quantity_highest');
        $limit = $request->input('limit', 100);
        
        // Parse dates
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
            $startDate = $startDateCarbon->format('Y-m-d');
            $endDate = $endDateCarbon->format('Y-m-d');
        }
        
        // Main query - calculate real quantity of internal products sold
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
            ->join('products', 'mapping_barangs.product_id', '=', 'products.id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            ->where('mapping_barangs.is_active', 1)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                // FIXED: Calculate real quantity sold (order_items.quantity × mapping_barangs.quantity)
                // This shows the actual number of internal products sold
                DB::raw('SUM(order_items.quantity * mapping_barangs.quantity) as total_quantity'),
                // FIXED: Count distinct orders properly (not order_items)
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                // FIXED: GROUP_CONCAT with ORDER BY and SEPARATOR to prevent truncation
                DB::raw('GROUP_CONCAT(DISTINCT orders.platform_id ORDER BY orders.platform_id SEPARATOR ",") as platform_ids')
            )
            ->groupBy('products.id', 'products.name', 'products.sku');
        
        // Apply platform filter
        if ($selectedPlatform) {
            $query->where('orders.platform_id', $selectedPlatform);
        }
        
        // FIXED: Exclude orders that have returns (consistent with other reports)
        $query->whereNotExists(function($subquery) {
            $subquery->select(DB::raw(1))
                ->from('retur_penjualans')
                ->whereColumn('retur_penjualans.order_id', 'orders.id')
                ->whereIn('retur_penjualans.status', ['draft', 'selesai']);
        });
        
        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('products.name', 'like', "%$search%")
                  ->orWhere('products.sku', 'like', "%$search%");
            });
        }
        
        // Apply sorting at database level
        switch ($sortBy) {
            case 'quantity_lowest':
                $query->orderByRaw('total_quantity ASC');
                break;
            case 'order_count_highest':
                $query->orderByRaw('order_count DESC');
                break;
            case 'order_count_lowest':
                $query->orderByRaw('order_count ASC');
                break;
            case 'quantity_highest':
            default:
                $query->orderByRaw('total_quantity DESC');
                break;
        }
        
        // FIXED: Use Laravel paginate() instead of manual skip/take
        // Clone query for count to avoid binding issues
        $perPage = $limit;
        $paginator = $query->paginate($perPage, ['*'], 'page', $request->input('page', 1))
            ->appends($request->query());
        
        // Get platform names for display (batch query)
        $platformIds = [];
        foreach ($paginator->items() as $product) {
            if ($product->platform_ids) {
                $platformIds = array_merge($platformIds, explode(',', $product->platform_ids));
            }
        }
        $platformIds = array_unique($platformIds);
        $platformNames = Platform::whereIn('id', $platformIds)->pluck('name', 'id');
        
        // Calculate returns using database query (batch for all products in current page)
        $productIds = collect($paginator->items())->pluck('product_id')->toArray();
        
        // Get returns - calculate real return quantity
        $returnsData = [];
        if (!empty($productIds)) {
            $returnsData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
                ->join('retur_penjualan_details', 'order_items.id', '=', 'retur_penjualan_details.order_item_id')
                ->join('retur_penjualans', 'retur_penjualan_details.retur_penjualan_id', '=', 'retur_penjualans.id')
                ->whereIn('mapping_barangs.product_id', $productIds)
                ->where('mapping_barangs.is_active', 1)
                ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
                ->whereIn('retur_penjualans.status', ['draft', 'selesai'])
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('orders.platform_id', $selectedPlatform);
                })
                ->select(
                    'mapping_barangs.product_id',
                    // FIXED: retur_penjualan_details.qty is already in individual product units, no need to multiply
                    DB::raw('SUM(retur_penjualan_details.qty) as qty_retur')
                )
                ->groupBy('mapping_barangs.product_id')
                ->get()
                ->keyBy('product_id');
        }
        
        // Transform paginator items
        $transformedItems = collect($paginator->items())->map(function($product) use ($platformNames, $returnsData) {
            $platformIdArray = $product->platform_ids ? explode(',', $product->platform_ids) : [];
            $platforms = [];
            foreach ($platformIdArray as $pid) {
                if (isset($platformNames[$pid])) {
                    $platforms[] = $platformNames[$pid];
                }
            }
            
            $qtyRetur = isset($returnsData[$product->product_id]) 
                ? (float) $returnsData[$product->product_id]->qty_retur 
                : 0;
            
            $netQuantity = max(0, $product->total_quantity - $qtyRetur);
            
            return [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'product_sku' => $product->product_sku ?? '-',
                'total_quantity' => (float) $product->total_quantity,
                'qty_retur' => $qtyRetur,
                'net_quantity' => $netQuantity,
                'order_count' => (int) $product->order_count,
                'platforms' => implode(', ', array_unique($platforms)),
            ];
        });
        
        // Replace paginator items with transformed data
        $paginator->setCollection($transformedItems);
        
        // Calculate total orders correctly (unique orders, exclude returns) - same logic as other reports
        $baseOrderQuery = Order::withoutGlobalScope('mainCategory')
            ->whereNotNull('platform_id')
            ->whereBetween('tanggal', [$startDateCarbon, $endDateCarbon]);
        
        if ($selectedPlatform) {
            $baseOrderQuery->where('platform_id', $selectedPlatform);
        }
        
        // Get all order IDs matching filters
        $allOrderIds = $baseOrderQuery->pluck('id')->toArray();
        
        // Get order IDs that have returns (exclude them)
        $orderIdsWithReturn = [];
        if (!empty($allOrderIds)) {
            $orderIdsWithReturn = ReturPenjualan::whereIn('order_id', $allOrderIds)
                ->pluck('order_id')
                ->unique()
                ->toArray();
        }
        
        // Calculate unique valid orders (excluding returns)
        $totalOrders = count($allOrderIds) - count($orderIdsWithReturn);
        
        // Get valid order IDs (excluding returns)
        $validOrderIds = array_diff($allOrderIds, $orderIdsWithReturn);
        
        // Get total returns count
        $totalReturnsCount = !empty($allOrderIds) 
            ? ReturPenjualan::whereIn('order_id', $allOrderIds)->count() 
            : 0;
        
        // Calculate total_products and total_quantity from valid orders only
        // For internal products, we need to count unique internal products from valid orders
        $validInternalProductIds = [];
        $totalQuantitySold = 0;
        
        if (!empty($validOrderIds)) {
            // Get order items from valid orders
            $validOrderItems = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereIn('order_items.order_id', $validOrderIds)
                ->whereNotNull('orders.platform_id')
                ->with(['platformProduct.mappingBarang' => function($q) {
                    $q->where('is_active', true);
                }])
                ->get();
            
            // Filter by platform if selected
            if ($selectedPlatform) {
                $validOrderItems = $validOrderItems->filter(function($item) use ($selectedPlatform) {
                    return $item->order && $item->order->platform_id == $selectedPlatform;
                });
            }
            
            // Calculate quantities and collect product IDs
            $productQtys = [];
            foreach ($validOrderItems as $item) {
                if (!$item->platformProduct) continue;
                
                $platformProduct = $item->platformProduct;
                $mappings = $platformProduct->mappingBarang->where('is_active', true);
                
                if ($mappings->isEmpty()) continue;
                
                foreach ($mappings as $mapping) {
                    if (!$mapping->product) continue;
                    
                    $productId = $mapping->product->id;
                    $mappingQuantity = $mapping->quantity;
                    
                    // FIXED: Calculate real quantity (order_items.quantity × mapping.quantity)
                    $internalProductQty = $item->quantity * $mappingQuantity;
                    
                    if (!isset($productQtys[$productId])) {
                        $productQtys[$productId] = 0;
                    }
                    $productQtys[$productId] += $internalProductQty;
                    $validInternalProductIds[] = $productId;
                }
            }
            
            $validInternalProductIds = array_unique($validInternalProductIds);
            $totalQuantitySold = array_sum($productQtys);
        }
        
        // Calculate total returns using database query (from all orders in period)
        $totalReturnsData = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            ->where('mapping_barangs.is_active', 1)
            ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                $q->where('orders.platform_id', $selectedPlatform);
            })
            ->when($search, function($q) use ($search) {
                $q->whereExists(function($query) use ($search) {
                    $query->select(DB::raw(1))
                        ->from('products')
                        ->whereColumn('products.id', 'mapping_barangs.product_id')
                        ->where(function($q2) use ($search) {
                            $q2->where('products.name', 'like', "%$search%")
                               ->orWhere('products.sku', 'like', "%$search%");
                        });
                });
            })
            ->selectRaw('
                SUM(
                    (
                        SELECT COALESCE(SUM(rpd.qty), 0)
                        FROM retur_penjualan_details rpd
                        JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                        WHERE rpd.order_item_id = order_items.id
                          AND rp.status IN ("draft", "selesai")
                    )
                ) as total_returns
            ')
            ->first();
        
        $totalReturnsQty = $totalReturnsData ? (float) $totalReturnsData->total_returns : 0;
        
        // Calculate returns from valid orders only (for net quantity calculation)
        $returnsFromValidOrders = 0;
        if (!empty($validOrderIds)) {
            $returnsValidData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
                ->whereIn('order_items.order_id', $validOrderIds)
                ->where('mapping_barangs.is_active', 1)
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('orders.platform_id', $selectedPlatform);
                })
                ->when($search, function($q) use ($search) {
                    $q->whereExists(function($query) use ($search) {
                        $query->select(DB::raw(1))
                            ->from('products')
                            ->whereColumn('products.id', 'mapping_barangs.product_id')
                            ->where(function($q2) use ($search) {
                                $q2->where('products.name', 'like', "%$search%")
                                   ->orWhere('products.sku', 'like', "%$search%");
                            });
                    });
                })
                ->selectRaw('
                    SUM(
                        (
                            SELECT COALESCE(SUM(rpd.qty), 0)
                            FROM retur_penjualan_details rpd
                            JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                            WHERE rpd.order_item_id = order_items.id
                              AND rp.status IN ("draft", "selesai")
                        )
                    ) as returns_from_valid
                ')
                ->first();
            
            $returnsFromValidOrders = $returnsValidData ? (float) $returnsValidData->returns_from_valid : 0;
        }
        
        // Calculate net quantity (total sold - returns) from valid orders
        $totalNetQuantity = (float) $totalQuantitySold - (float) $returnsFromValidOrders;
        
        // Calculate summary - using data from valid orders only (same logic as other reports)
        $summary = [
            'total_products' => count($validInternalProductIds), // Unique internal products from valid orders
            'total_quantity' => max(0, $totalNetQuantity), // Net quantity (sold - returns) from valid orders
            'total_quantity_with_returns' => (float) $totalQuantitySold, // Total quantity sold from valid orders (before retur)
            'total_returns' => (float) $totalReturnsQty, // Total returned quantity from all orders in period
            'total_orders' => $totalOrders, // Unique orders excluding returns (same as other reports)
            'total_returns_count' => $totalReturnsCount, // Total count of returns
        ];
        
        return view('analytics.produk_internal_terlaris', compact(
            'platforms',
            'startDate',
            'endDate',
            'selectedPlatform',
            'search',
            'sortBy',
            'limit',
            'paginator',
            'summary'
        ));
    }

    /**
     * Export Produk Internal Terlaris to Excel
     * Exports all data matching filters (no pagination)
     */
    public function exportProdukInternalTerlaris(Request $request)
    {
        // Use same logic as produkInternalTerlaris but without pagination
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'quantity_highest');
        
        // Parse dates
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
            $startDate = $startDateCarbon->format('Y-m-d');
            $endDate = $endDateCarbon->format('Y-m-d');
        }
        
        // Main query - same as produkInternalTerlaris but get ALL data
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
            ->join('products', 'mapping_barangs.product_id', '=', 'products.id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            ->where('mapping_barangs.is_active', 1)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                DB::raw('SUM(order_items.quantity * mapping_barangs.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                DB::raw('GROUP_CONCAT(DISTINCT orders.platform_id ORDER BY orders.platform_id SEPARATOR ",") as platform_ids')
            )
            ->groupBy('products.id', 'products.name', 'products.sku');
        
        // Apply filters
        if ($selectedPlatform) {
            $query->where('orders.platform_id', $selectedPlatform);
        }
        
        $query->whereNotExists(function($subquery) {
            $subquery->select(DB::raw(1))
                ->from('retur_penjualans')
                ->whereColumn('retur_penjualans.order_id', 'orders.id')
                ->whereIn('retur_penjualans.status', ['draft', 'selesai']);
        });
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('products.name', 'like', "%$search%")
                  ->orWhere('products.sku', 'like', "%$search%");
            });
        }
        
        // Apply sorting
        switch ($sortBy) {
            case 'quantity_lowest':
                $query->orderByRaw('total_quantity ASC');
                break;
            case 'order_count_highest':
                $query->orderByRaw('order_count DESC');
                break;
            case 'order_count_lowest':
                $query->orderByRaw('order_count ASC');
                break;
            case 'quantity_highest':
            default:
                $query->orderByRaw('total_quantity DESC');
                break;
        }
        
        // Get ALL products (no pagination)
        $products = $query->get();
        
        // Get platform names
        $platformIds = [];
        foreach ($products as $product) {
            if ($product->platform_ids) {
                $platformIds = array_merge($platformIds, explode(',', $product->platform_ids));
            }
        }
        $platformIds = array_unique($platformIds);
        $platformNames = Platform::whereIn('id', $platformIds)->pluck('name', 'id');
        
        // Get returns for all products
        $productIds = $products->pluck('product_id')->toArray();
        $returnsData = [];
        if (!empty($productIds)) {
            $returnsData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('mapping_barangs', 'order_items.platform_product_id', '=', 'mapping_barangs.platform_product_id')
                ->join('retur_penjualan_details', 'order_items.id', '=', 'retur_penjualan_details.order_item_id')
                ->join('retur_penjualans', 'retur_penjualan_details.retur_penjualan_id', '=', 'retur_penjualans.id')
                ->whereIn('mapping_barangs.product_id', $productIds)
                ->where('mapping_barangs.is_active', 1)
                ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
                ->whereIn('retur_penjualans.status', ['draft', 'selesai'])
                ->when($selectedPlatform, function($q) use ($selectedPlatform) {
                    $q->where('orders.platform_id', $selectedPlatform);
                })
                ->select(
                    'mapping_barangs.product_id',
                    // FIXED: retur_penjualan_details.qty is already in individual product units
                    DB::raw('SUM(retur_penjualan_details.qty) as qty_retur')
                )
                ->groupBy('mapping_barangs.product_id')
                ->get()
                ->keyBy('product_id');
        }
        
        // Transform products
        $transformedProducts = $products->map(function($product) use ($platformNames, $returnsData) {
            $platformIdArray = $product->platform_ids ? explode(',', $product->platform_ids) : [];
            $platforms = [];
            foreach ($platformIdArray as $pid) {
                if (isset($platformNames[$pid])) {
                    $platforms[] = $platformNames[$pid];
                }
            }
            
            $qtyRetur = isset($returnsData[$product->product_id]) 
                ? (float) $returnsData[$product->product_id]->qty_retur 
                : 0;
            
            $netQuantity = max(0, $product->total_quantity - $qtyRetur);
            
            return [
                'product_id' => $product->product_id,
                'product_name' => $product->product_name,
                'product_sku' => $product->product_sku ?? '-',
                'total_quantity' => (float) $product->total_quantity,
                'qty_retur' => $qtyRetur,
                'net_quantity' => $netQuantity,
                'order_count' => (int) $product->order_count,
                'platforms' => implode(', ', array_unique($platforms)),
            ];
        });
        
        // Calculate summary
        $summary = [
            'total_products' => $transformedProducts->count(),
            'total_quantity' => $transformedProducts->sum('net_quantity'),
            'total_returns' => $transformedProducts->sum('qty_retur'),
            'total_orders' => $transformedProducts->sum('order_count'),
        ];
        
        $filename = 'produk-internal-terlaris-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new ProdukInternalTerlarisExport($transformedProducts, $summary, $startDate, $endDate),
            $filename,
            'Export Produk Internal Terlaris'
        );
    }

    /**
     * Export Produk Platform Terlaris to Excel
     * Exports all data matching filters (no pagination)
     */
    public function exportProdukPlatformTerlaris(Request $request)
    {
        // Use same logic as produkPlatformTerlaris but without pagination
        $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
        $selectedPlatform = $request->input('platform_id');
        $search = $request->input('search');
        $sortBy = $request->input('sort', 'quantity_highest');
        
        // Parse dates
        try {
            $startDateCarbon = Carbon::parse($startDate)->startOfDay();
            $endDateCarbon = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            $startDateCarbon = Carbon::today()->startOfDay();
            $endDateCarbon = Carbon::today()->endOfDay();
            $startDate = $startDateCarbon->format('Y-m-d');
            $endDate = $endDateCarbon->format('Y-m-d');
        }
        
        // Build main query - same as produkPlatformTerlaris but get ALL data
        // FIXED: Exclude orders that have returns from order_count
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('platform_products', 'order_items.platform_product_id', '=', 'platform_products.id')
            ->join('platforms', 'platform_products.platform_id', '=', 'platforms.id')
            ->whereBetween('orders.tanggal', [$startDateCarbon, $endDateCarbon])
            ->whereNotNull('orders.platform_id')
            // Exclude orders that have returns
            ->whereNotExists(function($subquery) {
                $subquery->select(DB::raw(1))
                    ->from('retur_penjualans')
                    ->whereColumn('retur_penjualans.order_id', 'orders.id')
                    ->whereIn('retur_penjualans.status', ['draft', 'selesai']);
            })
            ->select(
                'platform_products.id as platform_product_id',
                'platform_products.platform_product_name',
                'platform_products.variant',
                'platforms.id as platform_id',
                'platforms.name as platform_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('SUM(order_items.price_after_discount * order_items.quantity) as total_value'),
                DB::raw('(
                    SELECT COALESCE(SUM(rpd.qty), 0)
                    FROM retur_penjualan_details rpd
                    JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                    JOIN order_items oi2 ON rpd.order_item_id = oi2.id
                    JOIN orders o2 ON oi2.order_id = o2.id
                    WHERE oi2.platform_product_id = platform_products.id
                      AND rp.status IN ("draft", "selesai")
                      AND o2.tanggal BETWEEN "' . $startDateCarbon->format('Y-m-d H:i:s') . '" 
                      AND "' . $endDateCarbon->format('Y-m-d H:i:s') . '"
                ) as qty_retur_individual'),
                DB::raw('(
                    SELECT COALESCE(SUM(mb.quantity), 1)
                    FROM mapping_barangs mb
                    WHERE mb.platform_product_id = platform_products.id
                      AND mb.is_active = 1
                ) as package_quantity')
            )
            ->groupBy('platform_products.id', 'platform_products.platform_product_name', 'platform_products.variant', 'platforms.id', 'platforms.name');
        
        // Apply filters
        if ($selectedPlatform) {
            $query->where('platforms.id', $selectedPlatform);
        }
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('platform_products.platform_product_name', 'like', "%$search%")
                  ->orWhere('platform_products.variant', 'like', "%$search%");
            });
        }
        
        // Apply sorting
        switch ($sortBy) {
            case 'quantity_lowest':
                $query->orderByRaw('(SUM(order_items.quantity) - (qty_retur_individual / package_quantity)) ASC');
                break;
            case 'value_highest':
                $query->orderByRaw('SUM(order_items.price_after_discount * order_items.quantity) DESC');
                break;
            case 'value_lowest':
                $query->orderByRaw('SUM(order_items.price_after_discount * order_items.quantity) ASC');
                break;
            case 'order_count_highest':
                $query->orderByRaw('COUNT(DISTINCT order_items.order_id) DESC');
                break;
            case 'order_count_lowest':
                $query->orderByRaw('COUNT(DISTINCT order_items.order_id) ASC');
                break;
            case 'quantity_highest':
            default:
                $query->orderByRaw('(SUM(order_items.quantity) - (qty_retur_individual / package_quantity)) DESC');
                break;
        }
        
        // Get ALL products (no pagination)
        $products = $query->get();
        
        // Transform results - same logic as view method
        // Retur dihitung dari subquery yang ada di query utama (sama seperti view)
        $transformedProducts = $products->map(function($product) {
            $qtyReturPackage = $product->package_quantity > 0 
                ? $product->qty_retur_individual / $product->package_quantity 
                : $product->qty_retur_individual;
            
            $netQuantity = max(0, $product->total_quantity - $qtyReturPackage);
            
            return [
                'platform_product_id' => $product->platform_product_id,
                'platform_product_name' => $product->platform_product_name,
                'variant' => $product->variant ?? '-',
                'platform_id' => $product->platform_id,
                'platform_name' => $product->platform_name,
                'total_quantity' => (float) $product->total_quantity,
                'qty_retur' => (float) $qtyReturPackage,
                'net_quantity' => (float) $netQuantity,
                'order_count' => (int) $product->order_count,
                'total_value' => (float) $product->total_value,
            ];
        });
        
        // Calculate summary
        $summary = [
            'total_products' => $transformedProducts->count(),
            'total_quantity' => $transformedProducts->sum('net_quantity'),
            'total_returns' => $transformedProducts->sum('qty_retur'),
            'total_orders' => $transformedProducts->sum('order_count'),
            'total_value' => $transformedProducts->sum('total_value'),
        ];
        
        $filename = 'produk-platform-terlaris-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new ProdukPlatformTerlarisExport($transformedProducts, $summary, $startDate, $endDate),
            $filename,
            'Export Produk Platform Terlaris'
        );
    }

    /**
     * Sales by Master Product Report
     */
    public function salesByMasterProductReport(Request $request)
    {
        try {
            // Increase execution time and memory limit for large datasets
            set_time_limit(120);
            ini_set('memory_limit', '1024M');
            
            // Validate date range
            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            
            if ($startDate > $endDate) {
                return redirect()->back()->with('error', 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
            }
            
            $startDateObj = \Carbon\Carbon::parse($startDate);
            $endDateObj = \Carbon\Carbon::parse($endDate);
            if ($startDateObj->diffInDays($endDateObj) > 90) {
                return redirect()->back()->with('error', 'Rentang tanggal tidak boleh lebih dari 90 hari untuk performa yang optimal.');
            }
            
            // Use Query class - all calculations done in SQL
            $query = new \App\Queries\SalesByMasterProductQuery($request);
            
            // Performance testing: Enable query logging
            if (config('app.debug') && $request->has('debug_query')) {
                \DB::flushQueryLog();
                \DB::enableQueryLog();
            }
            
            // Use pagination with limit 20 for better performance (max 20 for 22-column table)
            $perPage = min((int) $request->input('per_page', 20), 20); // Max 20 per page for performance
            
            $startTime = microtime(true);
            $productRows = $query->paginate($perPage);
            $paginationTime = (microtime(true) - $startTime) * 1000; // milliseconds
            
            // Get summary using database aggregates (never loads all rows)
            $startTime = microtime(true);
            $summary = $query->getSummary();
            $summaryTime = (microtime(true) - $startTime) * 1000; // milliseconds
            
            // Log performance metrics if debug mode
            if (config('app.debug') && $request->has('debug_query')) {
                $queryLog = \DB::getQueryLog();
                \Log::info('Sales by Master Product Performance', [
                    'pagination_time_ms' => $paginationTime,
                    'summary_time_ms' => $summaryTime,
                    'total_time_ms' => $paginationTime + $summaryTime,
                    'total_queries' => count($queryLog),
                    'queries' => array_map(function($log) {
                        return [
                            'time' => $log['time'],
                            'query' => substr($log['query'], 0, 500),
                        ];
                    }, $queryLog),
                ]);
                
                // Return debug info if requested
                if ($request->has('debug_query') && $request->input('debug_query') === 'json') {
                    return response()->json([
                        'pagination_time_ms' => $paginationTime,
                        'summary_time_ms' => $summaryTime,
                        'total_time_ms' => $paginationTime + $summaryTime,
                        'queries' => $queryLog,
                    ]);
                }
            }
            
            // Get filter data for view
            $platforms = Platform::all();
            $brands = \App\Models\Brand::orderBy('name')->get();
            
            // Get selected values from request
            $selectedBrands = (array) $request->input('brands', []);
            $selectedSubBrands = (array) $request->input('sub_brands', []);
            $selectedProductCategories = (array) $request->input('product_categories', []);
            $selectedProductTypes = (array) $request->input('product_types', []);
            $selectedProductSizes = (array) $request->input('product_sizes', []);
            $selectedProductVariants = (array) $request->input('product_variants', []);
            
            // Cascade: filter sub brands by selected brands
            $subBrands = collect();
            if (!empty($selectedBrands)) {
                $subBrands = \App\Models\SubBrand::whereIn('brand_id', $selectedBrands)->orderBy('name')->get();
            } elseif (!empty($selectedSubBrands)) {
                // If sub brands are selected but no brands, get sub brands anyway
                $subBrands = \App\Models\SubBrand::whereIn('id', $selectedSubBrands)->orderBy('name')->get();
            }
            
            // Cascade: filter product categories by selected sub brands
            $productCategories = collect();
            if (!empty($selectedSubBrands)) {
                // Find distinct category IDs used by products under the selected sub-brands
                $categoryIds = \App\Models\Product::whereIn('sub_brand_id', $selectedSubBrands)
                    ->whereNotNull('product_category_id')
                    ->distinct()
                    ->pluck('product_category_id')
                    ->toArray();
                if (!empty($categoryIds)) {
                    $productCategories = \App\Models\ProductCategory::whereIn('id', $categoryIds)->orderBy('name')->get();
                }
            } elseif (!empty($selectedProductCategories)) {
                // If categories are selected but no sub brands, get categories anyway
                $productCategories = \App\Models\ProductCategory::whereIn('id', $selectedProductCategories)->orderBy('name')->get();
            }
            
            // Cascade: filter product types by selected categories
            $productTypes = collect();
            if (!empty($selectedProductCategories)) {
                $productTypes = \App\Models\ProductType::whereIn('product_category_id', $selectedProductCategories)->orderBy('name')->get();
            } elseif (!empty($selectedProductTypes)) {
                $productTypes = \App\Models\ProductType::whereIn('id', $selectedProductTypes)->orderBy('name')->get();
            }
            
            // Cascade: filter product sizes by selected types
            $productSizes = collect();
            if (!empty($selectedProductTypes)) {
                $productSizes = \App\Models\ProductSize::whereIn('product_type_id', $selectedProductTypes)->orderBy('name')->get();
            } elseif (!empty($selectedProductSizes)) {
                $productSizes = \App\Models\ProductSize::whereIn('id', $selectedProductSizes)->orderBy('name')->get();
            }
            
            // Cascade: filter product variants by selected sizes
            $productVariants = collect();
            if (!empty($selectedProductSizes)) {
                $productVariants = \App\Models\ProductVariant::whereIn('product_size_id', $selectedProductSizes)->orderBy('name')->get();
            } elseif (!empty($selectedProductVariants)) {
                $productVariants = \App\Models\ProductVariant::whereIn('id', $selectedProductVariants)->orderBy('name')->get();
            }
            
            return view('analytics.sales_by_master_product_new', [
                'productRows' => $productRows,
                'platforms' => $platforms,
                'productCategories' => $productCategories,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedPlatform' => $request->input('platform_id'),
                'sortBy' => $request->input('sort', 'revenue_highest'),
                'summary' => $summary,
                'brands' => $brands,
                'subBrands' => $subBrands,
                'productTypes' => $productTypes,
                'productSizes' => $productSizes,
                'productVariants' => $productVariants,
                'selectedBrands' => $selectedBrands,
                'selectedSubBrands' => $selectedSubBrands,
                'selectedProductCategories' => $selectedProductCategories,
                'selectedProductTypes' => $selectedProductTypes,
                'selectedProductSizes' => $selectedProductSizes,
                'selectedProductVariants' => $selectedProductVariants,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductReport: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    /**
     * Sales by Master Product Special Report
     */
    public function salesByMasterProductSpecialReport(Request $request)
    {
        try {
            // Increase execution time and memory limit for large datasets
            set_time_limit(120);
            ini_set('memory_limit', '1024M');
            
            // Validate date range
            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            
            if ($startDate > $endDate) {
                return redirect()->back()->with('error', 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
            }
            
            $startDateObj = \Carbon\Carbon::parse($startDate);
            $endDateObj = \Carbon\Carbon::parse($endDate);
            if ($startDateObj->diffInDays($endDateObj) > 90) {
                return redirect()->back()->with('error', 'Rentang tanggal tidak boleh lebih dari 90 hari untuk performa yang optimal.');
            }
            
            // Use Query class - all calculations done in SQL
            $query = new \App\Queries\SalesByMasterProductSpecialQuery($request);
            $productRows = $query->paginate(10);
            $summary = $query->getSummary();
            
            // Get filter data for view
            $platforms = Platform::all();
            $brands = \App\Models\Brand::orderBy('name')->get();
            
            // Get selected values from request
            $selectedBrands = (array) $request->input('brands', []);
            $selectedSubBrands = (array) $request->input('sub_brands', []);
            $selectedProductCategories = (array) $request->input('product_categories', []);
            $selectedProductTypes = (array) $request->input('product_types', []);
            $selectedProductSizes = (array) $request->input('product_sizes', []);
            $selectedProductVariants = (array) $request->input('product_variants', []);
            
            // Cascade: filter sub brands by selected brands
            $subBrands = collect();
            if (!empty($selectedBrands)) {
                $subBrands = \App\Models\SubBrand::whereIn('brand_id', $selectedBrands)->orderBy('name')->get();
            } elseif (!empty($selectedSubBrands)) {
                // If sub brands are selected but no brands, get sub brands anyway
                $subBrands = \App\Models\SubBrand::whereIn('id', $selectedSubBrands)->orderBy('name')->get();
            }
            
            // Cascade: filter product categories by selected sub brands
            $productCategories = collect();
            if (!empty($selectedSubBrands)) {
                // Find distinct category IDs used by products under the selected sub-brands
                $categoryIds = \App\Models\Product::whereIn('sub_brand_id', $selectedSubBrands)
                    ->whereNotNull('product_category_id')
                    ->distinct()
                    ->pluck('product_category_id')
                    ->toArray();
                if (!empty($categoryIds)) {
                    $productCategories = \App\Models\ProductCategory::whereIn('id', $categoryIds)->orderBy('name')->get();
                }
            } elseif (!empty($selectedProductCategories)) {
                // If categories are selected but no sub brands, get categories anyway
                $productCategories = \App\Models\ProductCategory::whereIn('id', $selectedProductCategories)->orderBy('name')->get();
            }
            
            // Cascade: filter product types by selected categories
            $productTypes = collect();
            if (!empty($selectedProductCategories)) {
                $productTypes = \App\Models\ProductType::whereIn('product_category_id', $selectedProductCategories)->orderBy('name')->get();
            } elseif (!empty($selectedProductTypes)) {
                $productTypes = \App\Models\ProductType::whereIn('id', $selectedProductTypes)->orderBy('name')->get();
            }
            
            // Cascade: filter product sizes by selected types
            $productSizes = collect();
            if (!empty($selectedProductTypes)) {
                $productSizes = \App\Models\ProductSize::whereIn('product_type_id', $selectedProductTypes)->orderBy('name')->get();
            } elseif (!empty($selectedProductSizes)) {
                $productSizes = \App\Models\ProductSize::whereIn('id', $selectedProductSizes)->orderBy('name')->get();
            }
            
            // Cascade: filter product variants by selected sizes
            $productVariants = collect();
            if (!empty($selectedProductSizes)) {
                $productVariants = \App\Models\ProductVariant::whereIn('product_size_id', $selectedProductSizes)->orderBy('name')->get();
            } elseif (!empty($selectedProductVariants)) {
                $productVariants = \App\Models\ProductVariant::whereIn('id', $selectedProductVariants)->orderBy('name')->get();
            }
            
            return view('analytics.sales_by_master_product_special', [
                'productRows' => $productRows,
                'platforms' => $platforms,
                'productCategories' => $productCategories,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedPlatform' => $request->input('platform_id'),
                'sortBy' => $request->input('sort', 'revenue_highest'),
                'summary' => $summary,
                'brands' => $brands,
                'subBrands' => $subBrands,
                'productTypes' => $productTypes,
                'productSizes' => $productSizes,
                'productVariants' => $productVariants,
                'selectedBrands' => $selectedBrands,
                'selectedSubBrands' => $selectedSubBrands,
                'selectedProductCategories' => $selectedProductCategories,
                'selectedProductTypes' => $selectedProductTypes,
                'selectedProductSizes' => $selectedProductSizes,
                'selectedProductVariants' => $selectedProductVariants,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductSpecialReport: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    /**
     * AJAX endpoint: Get table HTML only
     */
    public function salesByMasterProductTable(Request $request)
    {
        try {
            // Increase execution time and memory limit for large datasets
            set_time_limit(120);
            ini_set('memory_limit', '1024M');
            
            // Validate date range
            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            
            if ($startDate > $endDate) {
                return response('<div class="alert alert-danger">Tanggal mulai tidak boleh lebih besar dari tanggal akhir.</div>', 400);
            }
            
            $startDateObj = \Carbon\Carbon::parse($startDate);
            $endDateObj = \Carbon\Carbon::parse($endDate);
            if ($startDateObj->diffInDays($endDateObj) > 90) {
                return response('<div class="alert alert-danger">Rentang tanggal tidak boleh lebih dari 90 hari untuk performa yang optimal.</div>', 400);
            }
            
            $query = new \App\Queries\SalesByMasterProductQuery($request);
            $perPage = min((int) $request->input('per_page', 20), 20);
            
            $productRows = $query->paginate($perPage);
            $summary = $query->getSummary();
            
            return view('analytics.partials.sales_master_product_table', compact('productRows', 'summary'));
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductTable: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response('<div class="alert alert-danger">Error loading table: ' . $e->getMessage() . '</div>', 500);
        }
    }

    /**
     * AJAX endpoint: Get modal HTML (lazy load)
     */
    public function salesByMasterProductModal()
    {
        return view('analytics.partials.sales_master_product_modal');
    }

    /**
     * AJAX endpoint: Get table HTML only (Special Report)
     */
    public function salesByMasterProductSpecialTable(Request $request)
    {
        try {
            // Increase execution time and memory limit for large datasets
            set_time_limit(120);
            ini_set('memory_limit', '1024M');
            
            // Validate date range
            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            
            if ($startDate > $endDate) {
                return response('<div class="alert alert-danger">Tanggal mulai tidak boleh lebih besar dari tanggal akhir.</div>', 400);
            }
            
            $startDateObj = \Carbon\Carbon::parse($startDate);
            $endDateObj = \Carbon\Carbon::parse($endDate);
            if ($startDateObj->diffInDays($endDateObj) > 90) {
                return response('<div class="alert alert-danger">Rentang tanggal tidak boleh lebih dari 90 hari untuk performa yang optimal.</div>', 400);
            }
            
            $query = new \App\Queries\SalesByMasterProductSpecialQuery($request);
            $perPage = min((int) $request->input('per_page', 20), 20);
            
            $productRows = $query->paginate($perPage);
            $summary = $query->getSummary();
            
            return view('analytics.partials.sales_master_product_table', compact('productRows', 'summary'));
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductSpecialTable: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response('<div class="alert alert-danger">Error loading table: ' . $e->getMessage() . '</div>', 500);
        }
    }

    /**
     * AJAX endpoint: Get modal HTML (lazy load) - Special Report
     */
    public function salesByMasterProductSpecialModal()
    {
        return view('analytics.partials.sales_master_product_modal');
    }

    /**
     * AJAX endpoint: Get brands with search
     */
    public function getBrands(Request $request)
    {
        $search = $request->input('search', '');
        $query = \App\Models\Brand::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        // Limit to 50 results for performance (TomSelect AJAX remote search)
        $brands = $query->orderBy('name')->limit(50)->get(['id', 'name']);
        return response()->json($brands);
    }
}
