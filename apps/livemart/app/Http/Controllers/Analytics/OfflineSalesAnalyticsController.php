<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Traits\QueueExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OfflineMonthlySalesExport;
use App\Exports\OfflineSalesByCustomerExport;
use App\Exports\OfflineSalesByProductExport;
use App\Exports\OfflineSalesDetailReportExport;

class OfflineSalesAnalyticsController extends Controller
{
    use QueueExport;
    private function calculateTotalAfterDiscounts($item)
    {
        $basePrice = (float)($item->unit_price ?? 0);
        $qty = (float)($item->quantity ?? 0);
        
        // Start with base total (price × quantity)
        $currentTotal = $basePrice * $qty;
        
        // Apply percentage discounts (1-5) in cascading order
        for($i = 1; $i <= 5; $i++) {
            $percentField = "discount_percent_" . $i;
            $discountPercent = (float)($item->$percentField ?? 0);
            if($discountPercent > 0) {
                $currentTotal = \App\Helpers\NumberFormatter::calculatePercentageDiscount($currentTotal, $discountPercent);
            }
        }
        
        // Apply nominal discounts (1-5) in cascading order
        for($i = 1; $i <= 5; $i++) {
            $amountField = "discount_amount_" . $i;
            $discountAmount = (float)($item->$amountField ?? 0);
            if($discountAmount > 0) {
                $currentTotal = \App\Helpers\NumberFormatter::calculateNominalDiscount($currentTotal, $discountAmount * $qty);
            }
        }
        
        return \App\Helpers\NumberFormatter::formatForDatabase($currentTotal);
    }

    /**
     * Display detailed sales report for offline sales
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function offlineMonthlySalesSummaryReport(Request $request)
    {
        // Get default date range - current year
        $currentYear = date('Y');
        $selectedYear = $request->input('year', $currentYear);
        $selectedCustomer = $request->input('customer_id');
        
        // Get all customers for the filter
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        // Build the query for offline sales
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'customerInfo'])
            ->whereYear('sale_date', $selectedYear);
            
        // Apply customer filter if selected
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Initialize monthly summary data
        $monthlySummary = collect();
        
        // Process each month
        for ($month = 1; $month <= 12; $month++) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1, $selectedYear));
            
            // Filter sales for this month
            $monthSales = $sales->filter(function ($sale) use ($month) {
                return $sale->sale_date->month == $month;
            });
            
            // Calculate metrics for this month
            $totalOrders = $monthSales->count();
            $totalValue = $monthSales->sum('total_amount');
            
            $totalVolume = 0;
            foreach ($monthSales as $sale) {
                foreach ($sale->items as $item) {
                    $totalVolume += $item->quantity;
                }
            }
            
            // Get average metrics
            $avgOrderValue = $totalOrders > 0 ? $totalValue / $totalOrders : 0;
            $avgOrderVolume = $totalOrders > 0 ? $totalVolume / $totalOrders : 0;
            
            // Add to monthly summary
            $monthlySummary->push([
                'month' => $month,
                'month_name' => $monthName,
                'total_orders' => $totalOrders,
                'total_value' => $totalValue,
                'total_volume' => $totalVolume,
                'avg_order_value' => $avgOrderValue,
                'avg_order_volume' => $avgOrderVolume,
            ]);
        }
        
        // Calculate year totals for summary
        $yearSummary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('total_amount'),
            'total_volume' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];
        
        // Calculate averages
        $yearSummary['avg_order_value'] = $yearSummary['total_orders'] > 0 
            ? $yearSummary['total_value'] / $yearSummary['total_orders'] 
            : 0;
            
        $yearSummary['avg_order_volume'] = $yearSummary['total_orders'] > 0 
            ? $yearSummary['total_volume'] / $yearSummary['total_orders'] 
            : 0;
        
        // Available years for dropdown (last 5 years)
        $availableYears = [];
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $availableYears[$year] = $year;
        }
        
        return view('analytics.offline_monthly_sales_summary', [
            'monthlySummary' => $monthlySummary,
            'yearSummary' => $yearSummary,
            'selectedYear' => $selectedYear,
            'selectedCustomer' => $selectedCustomer,
            'availableYears' => $availableYears,
            'customers' => $customers,
        ]);
    }

    /**
     * Display sales data by customer for offline sales
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function offlineSalesByCustomerReport(Request $request)
    {
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get selected customer if any
        $selectedCustomer = $request->input('customer_id');
        
        // Get all customers for the filter
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        // Build the query for offline sales
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'customerInfo']);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply customer filter if selected
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Group sales by customer
        $customerSummary = $sales->groupBy('customer_id')->map(function ($customerSales, $customerId) {
            $customer = $customerSales->first()->customerInfo;
            $customerName = $customer ? $customer->name : 'Unknown';
            
            $totalOrders = $customerSales->count();
            $totalValue = $customerSales->sum('total_amount');
            
            $totalVolume = 0;
            foreach ($customerSales as $sale) {
                foreach ($sale->items as $item) {
                    $totalVolume += $item->quantity;
                }
            }
            
            return [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'total_orders' => $totalOrders,
                'total_value' => $totalValue,
                'total_volume' => $totalVolume,
                'avg_order_value' => $totalOrders > 0 ? $totalValue / $totalOrders : 0,
                'avg_order_volume' => $totalOrders > 0 ? $totalVolume / $totalOrders : 0,
            ];
        });
        
        // Sort by the selected criteria
        $sortBy = $request->input('sort', 'value_highest');
        
        switch ($sortBy) {
            case 'value_highest':
                $customerSummary = $customerSummary->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $customerSummary = $customerSummary->sortBy('total_value');
                break;
            case 'volume_highest':
                $customerSummary = $customerSummary->sortByDesc('total_volume');
                break;
            case 'volume_lowest':
                $customerSummary = $customerSummary->sortBy('total_volume');
                break;
            case 'orders_highest':
                $customerSummary = $customerSummary->sortByDesc('total_orders');
                break;
            case 'orders_lowest':
                $customerSummary = $customerSummary->sortBy('total_orders');
                break;
            case 'name_asc':
                $customerSummary = $customerSummary->sortBy('customer_name');
                break;
            case 'name_desc':
                $customerSummary = $customerSummary->sortByDesc('customer_name');
                break;
            default:
                $customerSummary = $customerSummary->sortByDesc('total_value');
        }
        
        // Calculate overall summary
        $summary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('total_amount'),
            'total_volume' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 
            ? $summary['total_value'] / $summary['total_orders'] 
            : 0;
            
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 
            ? $summary['total_volume'] / $summary['total_orders'] 
            : 0;
        
        return view('analytics.offline_sales_by_customer', [
            'customerSummary' => $customerSummary,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCustomer' => $selectedCustomer,
            'sortBy' => $sortBy,
            'customers' => $customers,
        ]);
    }

    /**
     * Display detailed sales report for offline sales
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function offlineSalesDetailReport(Request $request)
    {
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get selected customer if any
        $selectedCustomer = $request->input('customer_id');
        
        // Get all customers for the filter
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        // Build the query for offline sales
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'customerInfo']);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply customer filter if selected
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Sort by the selected criteria
        $sortBy = $request->input('sort', 'date_newest');
        
        switch ($sortBy) {
            case 'value_highest':
                $sales = $sales->sortByDesc('total_amount');
                break;
            case 'value_lowest':
                $sales = $sales->sortBy('total_amount');
                break;
            case 'date_newest':
                $sales = $sales->sortByDesc('sale_date');
                break;
            case 'date_oldest':
                $sales = $sales->sortBy('sale_date');
                break;
            default:
                $sales = $sales->sortByDesc('sale_date');
        }
        
        // Calculate total volume for each sale
        $sales = $sales->map(function ($sale) {
            $sale->total_volume = $sale->items->sum('quantity');
            
            // Calculate actual value and volume after returns
            $qtyRetur = 0;
            $valueAfterReturns = 0;
            
            foreach ($sale->items as $item) {
                // Get return quantity for this specific item
                $itemQtyRetur = \App\Models\ReturOfflineSaleDetail::where('offline_sale_item_id', $item->id)
                    ->whereHas('returOfflineSale', function($q) { $q->where('status', 'selesai'); })
                    ->sum('qty');
                
                $itemQtyRetur = (float) $itemQtyRetur;
                $qtyRetur += $itemQtyRetur;
                
                // Calculate quantity after return for this item
                $qtyAfterRetur = max(0, $item->quantity - $itemQtyRetur);
                
                // ✅ Calculate total value after ALL DISCOUNTS for this item
                $itemTotalAfterDiscounts = $this->calculateTotalAfterDiscounts($item);
                
                // ✅ Use full value regardless of returns - VALUE should show original total with discounts
                $valueAfterReturns += $itemTotalAfterDiscounts;
            }
            
            // Set calculated values
            $sale->total_retur_qty = $qtyRetur;
            $sale->total_volume_after_returns = $sale->total_volume; // ✅ Use original volume, not reduced by returns
            $sale->value_after_returns = $valueAfterReturns;
            
            return $sale;
        });
        
        // Calculate overall summary using corrected values
        $summary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('value_after_returns'), // Use value after returns
            'total_volume' => $sales->sum('total_volume_after_returns'), // Use original volume (not reduced by returns)
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 
            ? $summary['total_value'] / $summary['total_orders'] 
            : 0;
            
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 
            ? $summary['total_volume'] / $summary['total_orders'] 
            : 0;
        
        return view('analytics.offline_sales_detail_report', [
            'sales' => $sales,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCustomer' => $selectedCustomer,
            'sortBy' => $sortBy,
            'customers' => $customers,
        ]);
    }

    /**
     * Display sales data by product for offline sales
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function offlineSalesByProductReport(Request $request)
    {
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get selected customer and product if any
        $selectedCustomer = $request->input('customer_id');
        $selectedProduct = $request->input('product_id');
        
        // Get all customers and products for the filter
        $customers = \App\Models\Customer::orderBy('name')->get();
        $products = \App\Models\Product::orderBy('name')->get();
        
        // Build the query for offline sales
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items.product', 'customerInfo']);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply customer filter if selected
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Filter sale items by product if selected
        $allSaleItems = collect();
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                if (!$selectedProduct || $item->product_id == $selectedProduct) {
                    // Add sale data to the item
                    $item->sale_date = $sale->sale_date;
                    $item->customer_name = $sale->customerInfo ? $sale->customerInfo->name : 'Unknown';
                    $item->surat_jalan_number = $sale->surat_jalan_number;
                    
                    $allSaleItems->push($item);
                }
            }
        }
        
        // Group items by product
        $productSummary = $allSaleItems->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first()->product;
            $productName = $product ? $product->name : 'Unknown';
            
            $totalQuantity = $items->sum('quantity');
            $totalValue = $items->sum('subtotal');
            
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'avg_price' => $totalQuantity > 0 ? $totalValue / $totalQuantity : 0,
            ];
        });
        
        // Sort by the selected criteria
        $sortBy = $request->input('sort', 'value_highest');
        
        switch ($sortBy) {
            case 'value_highest':
                $productSummary = $productSummary->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $productSummary = $productSummary->sortBy('total_value');
                break;
            case 'quantity_highest':
                $productSummary = $productSummary->sortByDesc('total_quantity');
                break;
            case 'quantity_lowest':
                $productSummary = $productSummary->sortBy('total_quantity');
                break;
            case 'name_asc':
                $productSummary = $productSummary->sortBy('product_name');
                break;
            case 'name_desc':
                $productSummary = $productSummary->sortByDesc('product_name');
                break;
            default:
                $productSummary = $productSummary->sortByDesc('total_value');
        }
        
        // Calculate overall summary
        $summary = [
            'total_products' => $productSummary->count(),
            'total_value' => $productSummary->sum('total_value'),
            'total_quantity' => $productSummary->sum('total_quantity'),
        ];
        
        return view('analytics.offline_sales_by_product', [
            'productSummary' => $productSummary,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedCustomer' => $selectedCustomer,
            'selectedProduct' => $selectedProduct,
            'sortBy' => $sortBy,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    /**
     * Export monthly sales summary to Excel
     */
    public function exportOfflineMonthlySales(Request $request)
    {
        // Get the same data as the view
        $selectedYear = $request->input('year', date('Y'));
        $selectedCustomer = $request->input('customer_id');
        
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'customerInfo'])
            ->whereYear('sale_date', $selectedYear);
            
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        $sales = $query->get();
        
        // Group by month
        $monthlySummary = $sales->groupBy(function ($sale) {
            return $sale->sale_date->format('Y-m');
        })->map(function ($monthSales, $yearMonth) {
            $totalVolume = $monthSales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            });
            
            return [
                'year_month' => $yearMonth,
                'month_name' => Carbon::createFromFormat('Y-m', $yearMonth)->format('M Y'),
                'total_orders' => $monthSales->count(),
                'total_value' => $monthSales->sum('total_amount'),
                'total_volume' => $totalVolume,
                'avg_order_value' => $monthSales->count() > 0 ? $monthSales->sum('total_amount') / $monthSales->count() : 0,
                'avg_order_volume' => $monthSales->count() > 0 ? $totalVolume / $monthSales->count() : 0,
            ];
        })->sortBy('year_month')->values();

        // Calculate year summary
        $yearSummary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('total_amount'),
            'total_volume' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];
        
        $yearSummary['avg_order_value'] = $yearSummary['total_orders'] > 0 ? $yearSummary['total_value'] / $yearSummary['total_orders'] : 0;
        $yearSummary['avg_order_volume'] = $yearSummary['total_orders'] > 0 ? $yearSummary['total_volume'] / $yearSummary['total_orders'] : 0;

        $customerName = $selectedCustomer ? $customers->where('id', $selectedCustomer)->first()->name ?? 'Unknown' : null;
        
        $filename = 'penjualan-bulanan-offline-' . $selectedYear . '-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new OfflineMonthlySalesExport($monthlySummary, $yearSummary, $selectedYear, $customerName),
            $filename,
            'Export Penjualan Bulanan Offline'
        );
    }

    /**
     * Export offline sales by customer to Excel
     */
    public function exportOfflineSalesByCustomer(Request $request)
    {
        // Get the same data as the view
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        $selectedCustomer = $request->input('customer_id');
        $sortBy = $request->input('sort', 'value_highest');
        
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'customerInfo']);
            
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        $sales = $query->get();
        
        // Group by customer
        $customerSummary = $sales->groupBy('customer_id')->map(function ($customerSales, $customerId) {
            $customer = $customerSales->first()->customerInfo;
            $customerName = $customer ? $customer->name : 'Unknown';
            
            $totalVolume = $customerSales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            });
            
            return [
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'total_orders' => $customerSales->count(),
                'total_value' => $customerSales->sum('total_amount'),
                'total_volume' => $totalVolume,
                'avg_order_value' => $customerSales->count() > 0 ? $customerSales->sum('total_amount') / $customerSales->count() : 0,
                'avg_order_volume' => $customerSales->count() > 0 ? $totalVolume / $customerSales->count() : 0,
            ];
        });

        // Sort data
        switch ($sortBy) {
            case 'value_highest':
                $customerSummary = $customerSummary->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $customerSummary = $customerSummary->sortBy('total_value');
                break;
            case 'volume_highest':
                $customerSummary = $customerSummary->sortByDesc('total_volume');
                break;
            case 'volume_lowest':
                $customerSummary = $customerSummary->sortBy('total_volume');
                break;
            case 'orders_highest':
                $customerSummary = $customerSummary->sortByDesc('total_orders');
                break;
            case 'orders_lowest':
                $customerSummary = $customerSummary->sortBy('total_orders');
                break;
            case 'name_asc':
                $customerSummary = $customerSummary->sortBy('customer_name');
                break;
            case 'name_desc':
                $customerSummary = $customerSummary->sortByDesc('customer_name');
                break;
            default:
                $customerSummary = $customerSummary->sortByDesc('total_value');
        }

        // Calculate summary
        $summary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('total_amount'),
            'total_volume' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 ? $summary['total_value'] / $summary['total_orders'] : 0;
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 ? $summary['total_volume'] / $summary['total_orders'] : 0;

        $customerName = $selectedCustomer ? $customers->where('id', $selectedCustomer)->first()->name ?? 'Unknown' : null;
        
        $filename = 'penjualan-offline-by-customer-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new OfflineSalesByCustomerExport($customerSummary, $summary, $startDate, $endDate, $customerName),
            $filename,
            'Export Penjualan Offline By Customer'
        );
    }

    /**
     * Export offline sales by product to Excel
     */
    public function exportOfflineSalesByProduct(Request $request)
    {
        // Get the same data as the view
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        $selectedCustomer = $request->input('customer_id');
        $selectedProduct = $request->input('product_id');
        $sortBy = $request->input('sort', 'value_highest');
        
        $customers = \App\Models\Customer::orderBy('name')->get();
        $products = \App\Models\Product::orderBy('name')->get();
        
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items.product', 'customerInfo']);
            
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        $sales = $query->get();
        
        // Filter sale items by product if selected
        $allSaleItems = collect();
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                if (!$selectedProduct || $item->product_id == $selectedProduct) {
                    $allSaleItems->push($item);
                }
            }
        }
        
        // Group items by product
        $productSummary = $allSaleItems->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first()->product;
            $productName = $product ? $product->name : 'Unknown';
            
            $totalQuantity = $items->sum('quantity');
            $totalValue = $items->sum('subtotal');
            
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'avg_price' => $totalQuantity > 0 ? $totalValue / $totalQuantity : 0,
            ];
        });
        
        // Sort data
        switch ($sortBy) {
            case 'value_highest':
                $productSummary = $productSummary->sortByDesc('total_value');
                break;
            case 'value_lowest':
                $productSummary = $productSummary->sortBy('total_value');
                break;
            case 'quantity_highest':
                $productSummary = $productSummary->sortByDesc('total_quantity');
                break;
            case 'quantity_lowest':
                $productSummary = $productSummary->sortBy('total_quantity');
                break;
            case 'name_asc':
                $productSummary = $productSummary->sortBy('product_name');
                break;
            case 'name_desc':
                $productSummary = $productSummary->sortByDesc('product_name');
                break;
            default:
                $productSummary = $productSummary->sortByDesc('total_value');
        }

        // Calculate summary
        $summary = [
            'total_products' => $productSummary->count(),
            'total_value' => $productSummary->sum('total_value'),
            'total_quantity' => $productSummary->sum('total_quantity'),
        ];

        $customerName = $selectedCustomer ? $customers->where('id', $selectedCustomer)->first()->name ?? 'Unknown' : null;
        $productName = $selectedProduct ? $products->where('id', $selectedProduct)->first()->name ?? 'Unknown' : null;
        
        $filename = 'penjualan-offline-by-product-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new OfflineSalesByProductExport($productSummary, $summary, $startDate, $endDate, $customerName, $productName),
            $filename,
            'Export Penjualan Offline By Product'
        );
    }

    /**
     * Export sales by day of week to Excel
     */
    public function exportOfflineSalesDetailReport(Request $request)
    {
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get selected customer if any
        $selectedCustomer = $request->input('customer_id');
        
        // Get selected product if any
        $selectedProduct = $request->input('product_id');
        
        // Build the query for offline sales
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items', 'items.product', 'customerInfo']);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply customer filter if selected
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Sort by the selected criteria
        $sortBy = $request->input('sort', 'date_newest');
        
        switch ($sortBy) {
            case 'value_highest':
                $sales = $sales->sortByDesc('total_amount');
                break;
            case 'value_lowest':
                $sales = $sales->sortBy('total_amount');
                break;
            case 'date_newest':
                $sales = $sales->sortByDesc('sale_date');
                break;
            case 'date_oldest':
                $sales = $sales->sortBy('sale_date');
                break;
            default:
                $sales = $sales->sortByDesc('sale_date');
        }
        
        // Calculate total volume for each sale and value after returns
        $sales = $sales->map(function ($sale) {
            $sale->total_volume = $sale->items->sum('quantity');
            
            // Calculate actual value and volume after returns (same logic as display)
            $qtyRetur = 0;
            $valueAfterReturns = 0;
            
            foreach ($sale->items as $item) {
                // Get return quantity for this specific item
                $itemQtyRetur = \App\Models\ReturOfflineSaleDetail::where('offline_sale_item_id', $item->id)
                    ->whereHas('returOfflineSale', function($q) { $q->where('status', 'selesai'); })
                    ->sum('qty');
                
                $itemQtyRetur = (float) $itemQtyRetur;
                $qtyRetur += $itemQtyRetur;
                
                // Calculate quantity after return for this item
                $qtyAfterRetur = max(0, $item->quantity - $itemQtyRetur);
                
                // ✅ Calculate total value after ALL DISCOUNTS for this item (export method)
                $itemTotalAfterDiscounts = $this->calculateTotalAfterDiscounts($item);
                
                // ✅ Use full value regardless of returns - VALUE should show original total with discounts (export)
                $valueAfterReturns += $itemTotalAfterDiscounts;
            }
            
            // Set calculated values
            $sale->total_retur_qty = $qtyRetur;
            $sale->total_volume_after_returns = $sale->total_volume; // ✅ Use original volume, not reduced by returns (export)
            $sale->value_after_returns = $valueAfterReturns;
            
            return $sale;
        });
        
        // Calculate overall summary using corrected values
        $summary = [
            'total_orders' => $sales->count(),
            'total_value' => $sales->sum('value_after_returns'), // Use value after returns
            'total_volume' => $sales->sum('total_volume_after_returns'), // Use original volume (not reduced by returns) (export)
        ];
        
        $summary['avg_order_value'] = $summary['total_orders'] > 0 
            ? $summary['total_value'] / $summary['total_orders'] 
            : 0;
            
        $summary['avg_order_volume'] = $summary['total_orders'] > 0 
            ? $summary['total_volume'] / $summary['total_orders'] 
            : 0;
        
        $filename = 'laporan-detail-penjualan-offline-' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(
            new OfflineSalesDetailReportExport($sales, $summary, $startDate, $endDate, $selectedCustomer, $selectedProduct),
            $filename,
            'Export Laporan Detail Penjualan Offline'
        );
    }
}
