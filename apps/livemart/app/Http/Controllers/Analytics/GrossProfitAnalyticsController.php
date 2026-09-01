<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Traits\QueueExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GrossProfitOfflineExport;
use App\Exports\SalesByMasterProductExport;
use App\Exports\SalesByPlatformProductExport;
use App\Models\Order;
use App\Models\Platform;
use App\Models\MainCategory;
use App\Models\Brand;
use App\Models\SubBrand;
use App\Models\ProductCategory;
use App\Models\ProductType;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\PenerimaanDetail;
use App\Models\ReturPenjualanDetail;

class GrossProfitAnalyticsController extends Controller
{
    use QueueExport;
    public function grossProfitOfflineReport(Request $request)
    {
        // Set default date range
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get filter parameters
        $selectedInvoice = $request->input('invoice_number');
        $selectedPO = $request->input('po_number');
        $selectedSKU = $request->input('sku');
        $selectedCustomer = $request->input('customer_id');
        
        // Get all customers for the filter
        $customers = \App\Models\Customer::orderBy('name')->get();
        
        // Build the query for offline sales with finance data
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->where('status', 'paid') // Only show paid sales
            ->with([
                'items', 
                'items.product', 
                'items.warehouseStock',
                'items.warehouseStock.penerimaanDetail',
                'customerInfo',
            ]);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply filters
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        if ($selectedInvoice) {
            $query->whereHas('items.barangKeluar.financeOffline', function($q) use ($selectedInvoice) {
                $q->where('invoice_number', 'like', '%' . $selectedInvoice . '%');
            });
        }
        
        if ($selectedPO) {
            $query->where('surat_jalan_number', 'like', '%' . $selectedPO . '%');
        }
        
        if ($selectedSKU) {
            $query->whereHas('items.product', function($q) use ($selectedSKU) {
                $q->where('sku', 'like', '%' . $selectedSKU . '%');
            });
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Process sales data for profit calculation
        $profitData = $sales->map(function ($sale) {
            $totalPaymentAmount = 0;
            $paymentDate = null;
            
            // Get payment information
            $financeOffline = $sale->finance_offline; // Use the accessor
            if ($financeOffline && $financeOffline->isNotEmpty()) {
                $totalPaymentAmount = $financeOffline->sum(function($invoice) {
                    return $invoice->payments ? $invoice->payments->sum('amount') : 0;
                });
                $paymentDate = $financeOffline->first()?->payments?->first()?->payment_date ?? $sale->sale_date;
            }
            
            // Calculate total cost price for all items in this sale
            $totalCostPriceForSale = 0;
            foreach ($sale->items as $saleItem) {
                if ($saleItem->warehouseStock && $saleItem->warehouseStock->penerimaanDetail) {
                    $penerimaanDetail = $saleItem->warehouseStock->penerimaanDetail;
                    $subtotal = $penerimaanDetail->subtotal ?? 0;
                    $diskon = $penerimaanDetail->diskon ?? 0;
                    $qty = $penerimaanDetail->qty ?? 1;
                    
                    if ($qty > 0) {
                        $costPricePerUnit = ($subtotal - $diskon) / $qty;
                        $totalCostPriceForSale += $costPricePerUnit * $saleItem->quantity;
                    }
                }
            }
            
            // Process each item in the sale
            return $sale->items->map(function ($item) use ($sale, $totalPaymentAmount, $paymentDate, $financeOffline, $totalCostPriceForSale) {
                // Calculate cost price from penerimaan detail
                $costPrice = 0;
                if ($item->warehouseStock && $item->warehouseStock->penerimaanDetail) {
                    $penerimaanDetail = $item->warehouseStock->penerimaanDetail;
                    $subtotal = $penerimaanDetail->subtotal ?? 0;
                    $diskon = $penerimaanDetail->diskon ?? 0;
                    $qty = $penerimaanDetail->qty ?? 1;
                    
                    if ($qty > 0) {
                        $costPrice = ($subtotal - $diskon) / $qty;
                    }
                }
                
                // Check if item is PKP (tax_id == 3)
                $isPKP = false;
                if ($item->warehouseStock && $item->warehouseStock->tax_id == 3) {
                    $isPKP = true;
                }
                
                // Calculate selling price after discounts per unit
                $sellingPriceAfterDiscount = $this->calculatePriceAfterDiscountsPerUnit($item);
                
                // Calculate profit per unit (using price after discount)
                $sellingPrice = $item->unit_price; // Keep original for display
                $profitPerUnit = $sellingPriceAfterDiscount - $costPrice;
                
                // Calculate total cost price
                $totalCostPrice = $costPrice * $item->quantity;
                
                // Calculate profit per invoice (proportional to item value)
                $itemValue = $item->subtotal;
                $totalSaleValue = $sale->total_amount;
                $proportionalPayment = $totalSaleValue > 0 ? ($itemValue / $totalSaleValue) * $totalPaymentAmount : 0;
                $profitPerInvoice = $proportionalPayment - $totalCostPrice;
                
                // Calculate payment per product from proportional payment (actual money received per product)
                // This is the actual payment amount allocated to this product (total, not per unit)
                $paymentPerProduct = $proportionalPayment;
                
                // Calculate payment per invoice without PPN (DPP)
                // For PKP: DPP = Grand Total / 1.11
                // For Non-PKP: DPP = Grand Total (no change)
                $paymentPerInvoiceWithoutPPN = $isPKP ? ($totalPaymentAmount / 1.11) : $totalPaymentAmount;
                $paymentPerInvoiceWithoutPPN = \App\Helpers\NumberFormatter::roundToWholeNumber($paymentPerInvoiceWithoutPPN);
                
                // Calculate payment per product without PPN (DPP total per product, not per unit)
                // This should be calculated from the actual proportional payment, not from selling price
                // For PKP: DPP = Proportional Payment / 1.11
                // For Non-PKP: DPP = Proportional Payment (no change)
                $paymentPerProductWithoutPPN = $isPKP ? ($proportionalPayment / 1.11) : $proportionalPayment;
                $paymentPerProductWithoutPPN = \App\Helpers\NumberFormatter::roundToTwoDecimals($paymentPerProductWithoutPPN);
                
                // Calculate payment per PCS without PPN (DPP per piece)
                // This is the payment per product without PPN divided by quantity (per unit)
                $paymentPerPCSWithoutPPN = $item->quantity > 0 ? ($paymentPerProductWithoutPPN / $item->quantity) : 0;
                $paymentPerPCSWithoutPPN = \App\Helpers\NumberFormatter::roundToTwoDecimals($paymentPerPCSWithoutPPN);
                
                // Calculate profit per product (payment per product without PPN - total cost price)
                $profitPerProduct = $paymentPerProductWithoutPPN - $totalCostPrice;
                
                // Calculate margins
                $marginPerUnit = $sellingPrice > 0 ? (($profitPerUnit / $sellingPrice) * 100) : 0;
                $marginPerProduct = $paymentPerProductWithoutPPN > 0 ? (($profitPerProduct / $paymentPerProductWithoutPPN) * 100) : 0;
                $marginPerInvoice = $paymentPerProduct > 0 ? (($profitPerInvoice / $paymentPerProduct) * 100) : 0;
                
                return [
                    'payment_date' => $paymentDate,
                    'sale_date' => $sale->sale_date,
                    'customer_name' => $sale->customerInfo ? $sale->customerInfo->name : ($sale->customer_name ?? '-'),
                    'po_number' => $sale->surat_jalan_number,
                    'invoice_number' => $financeOffline && $financeOffline->isNotEmpty() ? $financeOffline->first()->invoice_number : '-',
                    'product_name' => $item->product ? $item->product->name : 'Unknown Product',
                    'quantity' => $item->quantity,
                    'sku' => $item->product ? $item->product->sku : '-',
                    'payment_per_invoice' => $totalPaymentAmount,
                    'payment_per_invoice_without_ppn' => $paymentPerInvoiceWithoutPPN,
                    'payment_per_product' => $paymentPerProduct,
                    'payment_per_product_without_ppn' => $paymentPerProductWithoutPPN,
                    'payment_per_pcs_without_ppn' => $paymentPerPCSWithoutPPN,
                    'is_pkp' => $isPKP,
                    'cost_price' => $costPrice,
                    'total_cost_price' => $totalCostPrice,
                    'profit_per_unit' => $profitPerUnit,
                    'profit_per_product' => $profitPerProduct,
                    'profit_per_invoice' => $profitPerInvoice,
                    'margin_per_unit' => $marginPerUnit,
                    'margin_per_product' => $marginPerProduct,
                    'margin_per_invoice' => $marginPerInvoice,
                    'sale' => $sale,
                    'item' => $item
                ];
            });
        })->flatten(1);
        
        // Calculate summary cards
        $totalSales = $sales->count();
        // Use actual payment amount from invoices to match list invoice calculation
        // Calculate total revenue by summing unique payment per sale (not per item)
        // Since each sale's items have the same payment_per_invoice, we need to group by sale
        $totalRevenue = $sales->sum(function($sale) {
            $financeOffline = $sale->finance_offline;
            if ($financeOffline && $financeOffline->isNotEmpty()) {
                return $financeOffline->sum(function($invoice) {
                    return $invoice->payments ? $invoice->payments->sum('amount') : 0;
                });
            }
            return 0;
        });
        
        // Calculate total revenue without PPN from profitData to ensure consistency with filtered data
        // payment_per_product_without_ppn is already total per product (not per unit), so just sum it
        $totalRevenueWithoutPPN = $profitData->sum('payment_per_product_without_ppn');
        $totalRevenueWithoutPPN = \App\Helpers\NumberFormatter::roundToWholeNumber($totalRevenueWithoutPPN);
        
        // Calculate total profit using profit_per_product (total profit per product)
        $totalProfit = $profitData->sum('profit_per_product');
        
        // Calculate average margin using margin_per_product
        $averageMargin = $profitData->avg('margin_per_product');
        
        return view('analytics.gross_profit_offline', compact(
            'profitData',
            'customers',
            'startDate',
            'endDate',
            'selectedInvoice',
            'selectedPO',
            'selectedSKU',
            'selectedCustomer',
            'totalSales',
            'totalRevenue',
            'totalRevenueWithoutPPN',
            'totalProfit',
            'averageMargin'
        ));
    }

    /**
     * Export Gross Profit Offline Analytics
     */
    public function exportGrossProfitOffline(Request $request)
    {
        // Set default date range - same as report method
        $startDate = $request->filled('start_date') ? $request->input('start_date') : date('Y-m-01');
        $endDate = $request->filled('end_date') ? $request->input('end_date') : date('Y-m-d');
        
        // Get filter parameters
        $selectedInvoice = $request->input('invoice_number');
        $selectedPO = $request->input('po_number');
        $selectedSKU = $request->input('sku');
        $selectedCustomer = $request->input('customer_id');
        
        // Build the query for offline sales with finance data
        $query = \App\Models\OfflineSale::withoutGlobalScope('mainCategory')
            ->where('status', 'paid') // Only show paid sales
            ->with([
                'items', 
                'items.product', 
                'items.warehouseStock',
                'items.warehouseStock.penerimaanDetail',
                'items.barangKeluar.financeOffline',
                'customerInfo',
            ]);
            
        // Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }
        
        // Apply filters
        if ($selectedCustomer) {
            $query->where('customer_id', $selectedCustomer);
        }
        
        if ($selectedInvoice) {
            $query->whereHas('items.barangKeluar.financeOffline', function($q) use ($selectedInvoice) {
                $q->where('invoice_number', 'like', '%' . $selectedInvoice . '%');
            });
        }
        
        if ($selectedPO) {
            $query->where('surat_jalan_number', 'like', '%' . $selectedPO . '%');
        }
        
        if ($selectedSKU) {
            $query->whereHas('items.product', function($q) use ($selectedSKU) {
                $q->where('sku', 'like', '%' . $selectedSKU . '%');
            });
        }
        
        // Get the sales
        $sales = $query->get();
        
        // Process sales data for profit calculation
        $profitData = $sales->map(function ($sale) use ($selectedInvoice, $selectedSKU, $selectedPO) {
            $totalPaymentAmount = 0;
            $paymentDate = null;
            
            // Get payment information
            $financeOffline = $sale->finance_offline; // Use the accessor
            if ($financeOffline && $financeOffline->isNotEmpty()) {
                $totalPaymentAmount = $financeOffline->sum(function($invoice) {
                    return $invoice->payments ? $invoice->payments->sum('amount') : 0;
                });
                $paymentDate = $financeOffline->first()?->payments?->first()?->payment_date ?? $sale->sale_date;
            }
            
            // Calculate total cost price for all items in this sale
            $totalCostPriceForSale = 0;
            foreach ($sale->items as $saleItem) {
                if ($saleItem->warehouseStock && $saleItem->warehouseStock->penerimaanDetail) {
                    $penerimaanDetail = $saleItem->warehouseStock->penerimaanDetail;
                    $subtotal = $penerimaanDetail->subtotal ?? 0;
                    $diskon = $penerimaanDetail->diskon ?? 0;
                    $qty = $penerimaanDetail->qty ?? 1;
                    
                    if ($qty > 0) {
                        $costPricePerUnit = ($subtotal - $diskon) / $qty;
                        $totalCostPriceForSale += $costPricePerUnit * $saleItem->quantity;
                    }
                }
            }
            
            // Process each item in the sale with item-level filtering
            // Note: invoice_number and po_number are sale-level, so filtering is done at query level
            // Only SKU needs item-level filtering
            return $sale->items->filter(function ($item) use ($selectedSKU) {
                // Apply item-level filter for SKU
                if ($selectedSKU) {
                    $itemSKU = $item->product ? $item->product->sku : '';
                    if (stripos($itemSKU, $selectedSKU) === false) {
                        return false;
                    }
                }
                return true;
            })->map(function ($item) use ($sale, $totalPaymentAmount, $paymentDate, $financeOffline, $totalCostPriceForSale) {
                // Calculate cost price from penerimaan detail
                $costPrice = 0;
                if ($item->warehouseStock && $item->warehouseStock->penerimaanDetail) {
                    $penerimaanDetail = $item->warehouseStock->penerimaanDetail;
                    $subtotal = $penerimaanDetail->subtotal ?? 0;
                    $diskon = $penerimaanDetail->diskon ?? 0;
                    $qty = $penerimaanDetail->qty ?? 1;
                    
                    if ($qty > 0) {
                        $costPrice = ($subtotal - $diskon) / $qty;
                    }
                }
                
                // Check if item is PKP (tax_id == 3)
                $isPKP = false;
                if ($item->warehouseStock && $item->warehouseStock->tax_id == 3) {
                    $isPKP = true;
                }
                
                // Calculate selling price after discounts per unit
                $sellingPriceAfterDiscount = $this->calculatePriceAfterDiscountsPerUnit($item);
                
                // Calculate profit per unit (using price after discount)
                $sellingPrice = $item->unit_price; // Keep original for display
                $profitPerUnit = $sellingPriceAfterDiscount - $costPrice;
                
                // Calculate total cost price
                $totalCostPrice = $costPrice * $item->quantity;
                
                // Calculate profit per invoice (proportional to item value)
                $itemValue = $item->subtotal;
                $totalSaleValue = $sale->total_amount;
                $proportionalPayment = $totalSaleValue > 0 ? ($itemValue / $totalSaleValue) * $totalPaymentAmount : 0;
                $profitPerInvoice = $proportionalPayment - $totalCostPrice;
                
                // Calculate payment per product from proportional payment (actual money received per product)
                // This is the actual payment amount allocated to this product (total, not per unit)
                $paymentPerProduct = $proportionalPayment;
                
                // Calculate payment per invoice without PPN (DPP)
                // For PKP: DPP = Grand Total / 1.11
                // For Non-PKP: DPP = Grand Total (no change)
                $paymentPerInvoiceWithoutPPN = $isPKP ? ($totalPaymentAmount / 1.11) : $totalPaymentAmount;
                $paymentPerInvoiceWithoutPPN = \App\Helpers\NumberFormatter::roundToWholeNumber($paymentPerInvoiceWithoutPPN);
                
                // Calculate payment per product without PPN (DPP total per product, not per unit)
                // This should be calculated from the actual proportional payment, not from selling price
                // For PKP: DPP = Proportional Payment / 1.11
                // For Non-PKP: DPP = Proportional Payment (no change)
                $paymentPerProductWithoutPPN = $isPKP ? ($proportionalPayment / 1.11) : $proportionalPayment;
                $paymentPerProductWithoutPPN = \App\Helpers\NumberFormatter::roundToTwoDecimals($paymentPerProductWithoutPPN);
                
                // Calculate payment per PCS without PPN (DPP per piece)
                // This is the payment per product without PPN divided by quantity (per unit)
                $paymentPerPCSWithoutPPN = $item->quantity > 0 ? ($paymentPerProductWithoutPPN / $item->quantity) : 0;
                $paymentPerPCSWithoutPPN = \App\Helpers\NumberFormatter::roundToTwoDecimals($paymentPerPCSWithoutPPN);
                
                // Calculate profit per product (payment per product without PPN - total cost price)
                $profitPerProduct = $paymentPerProductWithoutPPN - $totalCostPrice;
                
                // Calculate margins
                $marginPerUnit = $sellingPrice > 0 ? (($profitPerUnit / $sellingPrice) * 100) : 0;
                $marginPerProduct = $paymentPerProductWithoutPPN > 0 ? (($profitPerProduct / $paymentPerProductWithoutPPN) * 100) : 0;
                $marginPerInvoice = $paymentPerProduct > 0 ? (($profitPerInvoice / $paymentPerProduct) * 100) : 0;
                
                return [
                    'payment_date' => $paymentDate,
                    'sale_date' => $sale->sale_date,
                    'customer_name' => $sale->customerInfo ? $sale->customerInfo->name : ($sale->customer_name ?? '-'),
                    'po_number' => $sale->surat_jalan_number,
                    'invoice_number' => $financeOffline && $financeOffline->isNotEmpty() ? $financeOffline->first()->invoice_number : '-',
                    'product_name' => $item->product ? $item->product->name : 'Unknown Product',
                    'quantity' => $item->quantity,
                    'sku' => $item->product ? $item->product->sku : '-',
                    'payment_per_invoice' => $totalPaymentAmount,
                    'payment_per_invoice_without_ppn' => $paymentPerInvoiceWithoutPPN,
                    'payment_per_product' => $paymentPerProduct,
                    'payment_per_product_without_ppn' => $paymentPerProductWithoutPPN,
                    'payment_per_pcs_without_ppn' => $paymentPerPCSWithoutPPN,
                    'is_pkp' => $isPKP,
                    'cost_price' => $costPrice,
                    'total_cost_price' => $totalCostPrice,
                    'profit_per_unit' => $profitPerUnit,
                    'profit_per_product' => $profitPerProduct,
                    'profit_per_invoice' => $profitPerInvoice,
                    'margin_per_unit' => $marginPerUnit,
                    'margin_per_product' => $marginPerProduct,
                    'margin_per_invoice' => $marginPerInvoice,
                    'sale' => $sale,
                    'item' => $item
                ];
            });
        })->flatten(1);
        
        // Sort by sale_date ascending (oldest to newest) for export
        $profitData = $profitData->sortBy(function ($item) {
            return $item['sale_date'] ? \Carbon\Carbon::parse($item['sale_date'])->timestamp : 0;
        })->values();
        
        // Generate filename
        $filename = 'Gross_Profit_Offline_' . $startDate . '_to_' . $endDate . '.xlsx';
        
        return $this->queueExcelExport(
            new GrossProfitOfflineExport($profitData),
            $filename,
            'Export Gross Profit Offline'
        );
    }

    // ========== HELPER METHODS FROM AnalyticController ==========
    
    /**
     * Calculate price per unit after discounts for an offline sale item
     */
    private function calculatePriceAfterDiscountsPerUnit($item)
    {
        $basePrice = (float)($item->unit_price ?? 0);
        $qty = (float)($item->quantity ?? 0);
        
        if ($qty == 0) {
            return $basePrice;
        }
        
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
        
        // Calculate price per unit after discounts
        $pricePerUnit = $currentTotal / $qty;
        return \App\Helpers\NumberFormatter::roundToTwoDecimals($pricePerUnit);
    }
    
    /**
     * Get the latest purchase cost for a product
     */
    private function getLatestPurchaseCost($productId)
    {
        // Get the latest purchase cost for a product from penerimaan details
        $latestPenerimaan = PenerimaanDetail::where('product_id', $productId)
            ->whereHas('penerimaan', function($query) {
                $query->whereIn('status', ['Located', 'approved']); // Both Located and approved
            })
            ->with(['penerimaan.taxCategory'])
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$latestPenerimaan) {
            return 0;
        }
        
        // Calculate final HPP after discounts
        $hppAsli = $latestPenerimaan->harga_hpp;
        $hppSetelahDiskon = $hppAsli;
        
        // Apply percentage discounts in sequence
        if ($latestPenerimaan->diskon_persen_1 > 0) {
            $hppSetelahDiskon -= ($hppSetelahDiskon * $latestPenerimaan->diskon_persen_1 / 100);
        }
        if ($latestPenerimaan->diskon_persen_2 > 0) {
            $hppSetelahDiskon -= ($hppSetelahDiskon * $latestPenerimaan->diskon_persen_2 / 100);
        }
        if ($latestPenerimaan->diskon_persen_3 > 0) {
            $hppSetelahDiskon -= ($hppSetelahDiskon * $latestPenerimaan->diskon_persen_3 / 100);
        }
        if ($latestPenerimaan->diskon_persen_4 > 0) {
            $hppSetelahDiskon -= ($hppSetelahDiskon * $latestPenerimaan->diskon_persen_4 / 100);
        }
        if ($latestPenerimaan->diskon_persen_5 > 0) {
            $hppSetelahDiskon -= ($hppSetelahDiskon * $latestPenerimaan->diskon_persen_5 / 100);
        }
        
        // Apply nominal discounts
        $hppSetelahDiskon -= $latestPenerimaan->diskon_nominal_1;
        $hppSetelahDiskon -= $latestPenerimaan->diskon_nominal_2;
        $hppSetelahDiskon -= $latestPenerimaan->diskon_nominal_3;
        $hppSetelahDiskon -= $latestPenerimaan->diskon_nominal_4;
        $hppSetelahDiskon -= $latestPenerimaan->diskon_nominal_5;
        
        // Ensure price doesn't go negative
        $finalHpp = max(0, $hppSetelahDiskon);
        
        // Skip tax calculation - use HPP directly without tax
        
        return $finalHpp;
    }
    
    /**
     * Calculate COGS per unit for a specific barang keluar record
     */
    private function getCogsPerUnitFromBarangKeluar($barangKeluar)
    {
        if ($barangKeluar->warehouseStock && $barangKeluar->warehouseStock->penerimaanDetail) {
            $penerimaanDetail = $barangKeluar->warehouseStock->penerimaanDetail;
            
            // Calculate HPP after discounts
            $hppAsli = $penerimaanDetail->harga_hpp;
            $hppSetelahDiskon = $hppAsli;
            
            // Apply percentage discounts in sequence
            if ($penerimaanDetail->diskon_persen_1 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_1 / 100);
            }
            if ($penerimaanDetail->diskon_persen_2 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_2 / 100);
            }
            if ($penerimaanDetail->diskon_persen_3 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_3 / 100);
            }
            if ($penerimaanDetail->diskon_persen_4 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_4 / 100);
            }
            if ($penerimaanDetail->diskon_persen_5 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_5 / 100);
            }
            
            // Apply nominal discounts
            $hppSetelahDiskon -= $penerimaanDetail->diskon_nominal_1;
            $hppSetelahDiskon -= $penerimaanDetail->diskon_nominal_2;
            $hppSetelahDiskon -= $penerimaanDetail->diskon_nominal_3;
            $hppSetelahDiskon -= $penerimaanDetail->diskon_nominal_4;
            $hppSetelahDiskon -= $penerimaanDetail->diskon_nominal_5;
            
            // Ensure price doesn't go negative
            return max(0, $hppSetelahDiskon);
        }
        
        return 0;
    }

    /**
     * Calculate total order value for a specific order (without filtering)
     * This ensures percentage calculation is consistent regardless of filters
     */
    private function calculateTotalOrderValue($orderId)
    {
        $totalOrderValue = 0;
        $order = \App\Models\Order::find($orderId);
        $transactionAt = $order?->created_at ?? now();
        
        $barangKeluarItems = \App\Models\BarangKeluar::whereHas('orderItem', function($q) use ($orderId) {
            $q->where('order_id', $orderId);
        })->with(['warehouseStock.product'])->get();
        
        foreach ($barangKeluarItems as $barangKeluar) {
            $product = $barangKeluar->warehouseStock->product;
            if ($product) {
                $masterProductValue = ((float) $product->getInitialPriceAt($transactionAt)) * $barangKeluar->qty;
                $totalOrderValue += $masterProductValue;
            }
        }
        
        return $totalOrderValue;
    }

    /**
     * Calculate consistent revenue distribution for products
     * FIXED: Correct allocation calculation following the 8-step process
     */
    private function calculateConsistentRevenueDistribution($orderMasterProducts, $totalSaldoMasuk, $totalOrderValue)
    {
        // Use provided total order value (constant, not affected by filtering)
        $totalPricelistOrder = $totalOrderValue;
        
        $result = [];
        
        // Calculate allocation for each line item individually following the 8-step process
        foreach ($orderMasterProducts as $masterProductData) {
            $masterValue = $masterProductData['master_value'];
            $qtyMaster = $masterProductData['master_qty'];
            $initialPrice = $masterProductData['selling_price'];
            
            // Step 2: Hitung percent_in_order
            $percentInOrder = $totalPricelistOrder > 0 ? 
                ($masterValue / $totalPricelistOrder) * 100 : 0;
            
            // Step 3: Hitung alloc_total
            $allocTotal = $totalSaldoMasuk * ($percentInOrder / 100);
            
            // Step 4: Bagi alloc_total sesuai qty di baris tersebut
            $allocPerPiece = $qtyMaster > 0 ? $allocTotal / $qtyMaster : 0;
            
            // Step 5: Hitung alloc_per_piece_net (hapus PPN)
            $allocPerPieceNet = $allocPerPiece / 1.11;
            
            // Get COGS per piece
            $cogsPerPiece = $this->getCogsPerUnitFromBarangKeluar($masterProductData['barang_keluar']);
            
            // Step 6: Hitung profit_per_piece
            $profitPerPiece = $allocPerPieceNet - $cogsPerPiece;
            
            // Step 7: Hitung gross_profit
            $grossProfit = $profitPerPiece * $qtyMaster;
            
            // Step 8: Hitung margin_per_piece
            $marginPerPiece = $allocPerPieceNet > 0 ? 
                ($profitPerPiece / $allocPerPieceNet) * 100 : 0;
            
            $result[] = [
                'master_product_data' => $masterProductData,
                'product_percentage' => $percentInOrder,
                'per_unit_allocation' => $allocPerPiece,
                'line_allocation' => $allocTotal,
                'alloc_per_piece_net' => $allocPerPieceNet,
                'cogs_per_unit' => $cogsPerPiece,
                'profit_per_piece' => $profitPerPiece,
                'gross_profit' => $grossProfit,
                'margin_per_piece' => $marginPerPiece
            ];
        }
        
        return $result;
    }

    /**
     * Sort product rows based on sort criteria
     */
    private function sortProductRows($productRows, $sortBy)
    {
        switch ($sortBy) {
            case 'revenue_highest':
                return $productRows->sortByDesc('revenue');
            case 'revenue_lowest':
                return $productRows->sortBy('revenue');
            case 'profit_highest':
                return $productRows->sortByDesc('gross_profit_total');
            case 'profit_lowest':
                return $productRows->sortBy('gross_profit_total');
            case 'quantity_highest':
                return $productRows->sortByDesc('quantity');
            case 'quantity_lowest':
                return $productRows->sortBy('quantity');
            default:
                return $productRows->sortByDesc('revenue');
        }
    }

    /**
     * Calculate average cost for a product across all purchase orders
     */
    private function calculateAverageCostForProduct($productId)
    {
        // Weighted average cost from penerimaan_detail records
        $details = PenerimaanDetail::where('product_id', $productId)
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

    // ========== METHODS FROM ProductAnalyticsController ==========
    
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
            $productRows = $query->paginate(10);
            $summary = $query->getSummary();
            
            // Get filter data for view
            $platforms = Platform::all();
            $productCategories = \App\Models\ProductCategory::orderBy('name')->get();
            $brands = \App\Models\Brand::orderBy('name')->get();
            $subBrands = \App\Models\SubBrand::orderBy('name')->get();
            $productTypes = \App\Models\ProductType::orderBy('name')->get();
            $productSizes = \App\Models\ProductSize::orderBy('name')->get();
            $productVariants = \App\Models\ProductVariant::orderBy('name')->get();
            
            // Cascade: filter sub brands by selected brands
            $selectedBrands = (array) $request->input('brands', []);
            if (!empty($selectedBrands)) {
                $subBrands = \App\Models\SubBrand::whereIn('brand_id', $selectedBrands)->orderBy('name')->get();
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
                'selectedSubBrands' => (array) $request->input('sub_brands', []),
                'selectedProductCategories' => (array) $request->input('product_categories', []),
                'selectedProductTypes' => (array) $request->input('product_types', []),
                'selectedProductSizes' => (array) $request->input('product_sizes', []),
                'selectedProductVariants' => (array) $request->input('product_variants', []),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductReport: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    public function salesByMasterProductSpecialReport(Request $request)
    {
        try {
            set_time_limit(120);
            ini_set('memory_limit', '1024M');

            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
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
            $productCategories = \App\Models\ProductCategory::orderBy('name')->get();
            $brands = \App\Models\Brand::orderBy('name')->get();
            $subBrands = \App\Models\SubBrand::orderBy('name')->get();
            $productTypes = \App\Models\ProductType::orderBy('name')->get();
            $productSizes = \App\Models\ProductSize::orderBy('name')->get();
            $productVariants = \App\Models\ProductVariant::orderBy('name')->get();
            
            // Get selected values
            $selectedPlatform = $request->input('platform_id');
            $sortBy = $request->input('sort', 'revenue_highest');
            $selectedBrands = (array) $request->input('brands', []);
            $selectedSubBrands = (array) $request->input('sub_brands', []);
            $selectedProductCategories = (array) $request->input('product_categories', []);
            $selectedProductTypes = (array) $request->input('product_types', []);
            $selectedProductSizes = (array) $request->input('product_sizes', []);
            $selectedProductVariants = (array) $request->input('product_variants', []);
            
            // Cascade: filter sub brands by selected brands
            if (!empty($selectedBrands)) {
                $subBrands = \App\Models\SubBrand::whereIn('brand_id', $selectedBrands)->orderBy('name')->get();
            }

            return view('analytics.sales_by_master_product_special', compact(
                'productRows', 'platforms', 'productCategories', 'startDate', 'endDate',
                'selectedPlatform', 'sortBy', 'summary', 'brands', 'subBrands',
                'productTypes', 'productSizes', 'productVariants', 'selectedBrands',
                'selectedSubBrands', 'selectedProductCategories', 'selectedProductTypes',
                'selectedProductSizes', 'selectedProductVariants'
            ));
        
        } catch (\Exception $e) {
            \Log::error('Error in salesByMasterProductSpecialReport: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    public function salesByPlatformProductReport(Request $request)
    {
        try {
            set_time_limit(120);
            ini_set('memory_limit', '1024M');

            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            $startDateObj = \Carbon\Carbon::parse($startDate);
            $endDateObj = \Carbon\Carbon::parse($endDate);
            if ($startDateObj->diffInDays($endDateObj) > 90) {
                return redirect()->back()->with('error', 'Rentang tanggal tidak boleh lebih dari 90 hari untuk performa yang optimal.');
            }
            
            // Use Query class - all calculations done in SQL
            $query = new \App\Queries\SalesByPlatformProductQuery($request);
            $platformProductRows = $query->paginate(10);
            $summary = $query->getSummary();
            
            // Get filter data for view
            $platforms = Platform::all();
            $productCategories = \App\Models\ProductCategory::orderBy('name')->get();
            
            // Get selected values
            $selectedPlatform = $request->input('platform_id');
            $sortBy = $request->input('sort', 'revenue_highest');
            $search = $request->input('search');
            $orderNumber = $request->input('order_number');

            return view('analytics.sales_by_platform_product', compact(
                'platformProductRows', 'platforms', 'productCategories', 'startDate', 'endDate',
                'selectedPlatform', 'sortBy', 'summary', 'search', 'orderNumber'
            ));

        } catch (\Exception $e) {
            \Log::error('Error in salesByPlatformProductReport: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    public function exportSalesByPlatformProduct(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '2048M');

            // Use the NEW Query class for consistency with view
            $query = new \App\Queries\SalesByPlatformProductQuery($request);
            
            // Get ALL data without pagination for export
            $platformProductRows = $query->get();
            $summary = $query->getSummary();

            $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->format('Y-m-d');
            $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->format('Y-m-d');
            $selectedPlatform = $request->input('platform_id');
            $sortBy = $request->input('sort', 'revenue_highest');

            $filename = 'laporan-penjualan-platform-produk-' . date('Y-m-d') . '.xlsx';
            
            $filters = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'platform_id' => $selectedPlatform,
                'sort' => $sortBy
            ];
            
            return $this->queueExcelExport(
                new SalesByPlatformProductExport($platformProductRows, $summary, $filters),
                $filename,
                'Export Sales By Platform Product'
            );

        } catch (\Exception $e) {
            \Log::error('Error in exportSalesByPlatformProduct: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan saat mengexport data. Silakan coba lagi atau hubungi administrator.');
        }
    }

    public function exportSalesByMasterProduct(Request $request)
    {
        try {
            set_time_limit(300);
            ini_set('memory_limit', '2048M');

            // Use the NEW Query class for consistency with view
            $query = new \App\Queries\SalesByMasterProductQuery($request);
            
            // Get ALL data without pagination for export
            $productRows = $query->get();
            $summary = $query->getSummary();

            // Get filters from query class to ensure consistency
            $filters = $query->getFilters();
            $filters['sort'] = $request->input('sort', 'revenue_highest');

            $filename = 'laporan-penjualan-master-produk-' . date('Y-m-d') . '.xlsx';
            
            return $this->queueExcelExport(
                new SalesByMasterProductExport($productRows, $summary, $filters),
                $filename,
                'Export Sales By Master Product'
            );

        } catch (\Exception $e) {
            \Log::error('Error in exportSalesByMasterProduct: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan saat export data. Silakan coba lagi atau hubungi administrator.');
        }
    }

}
