<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturPenjualan;
use App\Models\ReturOfflineSale;
use App\Models\ShopeeFinancialTransaction;
use App\Models\Shopee2FinancialTransaction;
use App\Models\Tiktok2FinancialTransaction;
use App\Models\TiktokFinancialTransaction;
use App\Models\FinanceOffline;
use App\Models\MappingBarang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReturFinanceService
{
    /**
     * Handle finance logic for online return
     * 
     * @param ReturPenjualan $returPenjualan
     * @param float $refundAmount
     * @param float $additionalDeduction (ongkir, etc)
     * @return void
     */
    public function handleOnlineReturFinance(ReturPenjualan $returPenjualan, float $refundAmount = null, float $additionalDeduction = 0)
    {
        $order = $returPenjualan->order;

        // Calculate refund amount if not provided
        if ($refundAmount === null) {
            $refundAmount = $this->calculateReturRefundAmount($returPenjualan);
        }

        // Determine platform and normalize
        $platform = $this->normalizePlatform(strtolower($order->platform->name ?? ''));

        // Check if finance transaction exists before processing
        $exists = $this->financeTransactionExists($order, $platform);
        if (!$exists) {
            Log::info("No finance transaction exists for order {$order->order_number}, skipping finance processing");
            return;
        }

        // Get original order total (Current Total in Finance)
        $originalOrderTotal = $this->getCurrentFinanceTotal($order, $platform);

        // Fallback if no transaction found
        if ($originalOrderTotal <= 0) {
             $currentItemsTotal = $this->getOriginalOrderTotal($order);
             $originalOrderTotal = $currentItemsTotal + $refundAmount;
        }

        Log::info("Processing retur finance for order {$order->order_number}", [
            'refund_amount' => $refundAmount,
            'original_total_from_finance' => $originalOrderTotal,
            'additional_deduction' => $additionalDeduction,
            'is_fully_returned' => $order->isFullyReturned()
        ]);

        if ($order->isFullyReturned() && $additionalDeduction == 0) {
            // SCENARIO 1: Full refund (kumulatif) - remove payment and move to unpaid
            $this->handleFullRefund($order, $platform);
        } else {
            // SCENARIO 2: Partial refund or has additional deduction
            $this->handlePartialRefundWithDeduction($order, $platform, $refundAmount, $originalOrderTotal, $additionalDeduction);
        }
    }
    
    /**
     * Handle finance logic for offline return
     * 
     * @param ReturOfflineSale $returOfflineSale
     * @param float $refundAmount
     * @param float $additionalDeduction
     * @return void
     */
    public function handleOfflineReturFinance(ReturOfflineSale $returOfflineSale, float $refundAmount = null, float $additionalDeduction = 0)
    {
        $offlineSale = $returOfflineSale->offlineSale;
        
        // Calculate refund amount if not provided
        if ($refundAmount === null) {
            $refundAmount = $this->calculateOfflineReturRefundAmount($returOfflineSale);
        }
        
        // Get original sale total
        $originalSaleTotal = $offlineSale->total_amount;
        
        Log::info("Processing offline retur finance for sale {$offlineSale->id}", [
            'refund_amount' => $refundAmount,
            'original_total' => $originalSaleTotal,
            'additional_deduction' => $additionalDeduction,
            'has_retur_full' => $offlineSale->hasReturFull()
        ]);

        if ($offlineSale->hasReturFull() && $additionalDeduction == 0) {
            // SCENARIO 1: Full refund (kumulatif) - mark all invoices as returned and create negative entry
            $this->handleOfflineFullRefund($offlineSale);
        } else {
            // SCENARIO 2: Partial refund or has additional deduction
            $this->handleOfflinePartialRefundWithDeduction($offlineSale, $refundAmount, $originalSaleTotal, $additionalDeduction);
        }
    }
    
    /**
     * Get current total from financial transaction
     */
    private function getCurrentFinanceTotal(Order $order, string $platform): float
    {
        $transaction = null;
        switch ($platform) {
            case 'shopee':
                $transaction = ShopeeFinancialTransaction::where('order_id', $order->id)->first();
                break;
            case 'shopee2':
                $transaction = Shopee2FinancialTransaction::where('order_id', $order->id)->first();
                break;
            case 'tiktok':
                $transaction = TiktokFinancialTransaction::where('order_id', $order->id)->first();
                break;
            case 'tiktok2':
                $transaction = Tiktok2FinancialTransaction::where('order_id', $order->id)->first();
                break;
        }

        if ($transaction) {
            return (float) $transaction->nominal_harga;
        }

        return 0.0;
    }

    /**
     * Check if a finance transaction exists for the given order and platform
     */
    private function financeTransactionExists(Order $order, string $platform): bool
    {
        switch ($platform) {
            case 'shopee':
                return ShopeeFinancialTransaction::where('order_id', $order->id)->exists();
            case 'shopee2':
                return Shopee2FinancialTransaction::where('order_id', $order->id)->exists();
            case 'tiktok':
                return TiktokFinancialTransaction::where('order_id', $order->id)->exists();
            case 'tiktok2':
                return Tiktok2FinancialTransaction::where('order_id', $order->id)->exists();
            default:
                return false;
        }
    }

    /**
     * Handle full refund - remove financial transaction and move to unpaid
     */
    private function handleFullRefund(Order $order, string $platform)
    {
        try {
            DB::beginTransaction();
            
            // Delete existing financial transaction based on platform
            switch ($platform) {
                case 'shopee':
                    ShopeeFinancialTransaction::where('order_id', $order->id)->delete();
                    break;
                case 'shopee2':
                    Shopee2FinancialTransaction::where('order_id', $order->id)->delete();
                    break;
                case 'tiktok':
                    TiktokFinancialTransaction::where('order_id', $order->id)->delete();
                    break;
                case 'tiktok2':
                    Tiktok2FinancialTransaction::where('order_id', $order->id)->delete();
                    break;
            }
            
            Log::info("Deleted financial transaction for order {$order->order_number} - moved to unpaid with RETUR status");
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error handling full refund: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle partial refund with deduction - keep in finance with adjusted values
     */
    private function handlePartialRefundWithDeduction(Order $order, string $platform, float $refundAmount, float $originalTotal, float $additionalDeduction)
    {
        try {
            DB::beginTransaction();
            
            // Calculate adjusted values after return
            $adjustedNominalHarga = max(0, $originalTotal - $refundAmount);
            
            // Get existing transaction to preserve discounts
            $existingTransaction = null;
            switch ($platform) {
                case 'shopee':
                    $existingTransaction = ShopeeFinancialTransaction::where('order_id', $order->id)->first();
                    break;
                case 'shopee2':
                    $existingTransaction = Shopee2FinancialTransaction::where('order_id', $order->id)->first();
                    break;
                case 'tiktok':
                    $existingTransaction = TiktokFinancialTransaction::where('order_id', $order->id)->first();
                    break;
                case 'tiktok2':
                    $existingTransaction = Tiktok2FinancialTransaction::where('order_id', $order->id)->first();
                    break;
            }
            
            if (!$existingTransaction) {
                Log::warning("No existing transaction found for order {$order->order_number} during partial refund");
                DB::rollBack();
                return;
            }
            
            // Calculate total discounts from existing transaction with recalculation based on percentages
            $totalDiscounts = 0;
            $discountUpdates = [];
            
            for ($i = 1; $i <= 12; $i++) {
                $nominalCol = "nominal_diskon{$i}";
                $percentCol = "persentase_diskon{$i}";
                
                // Check if percentage exists and is valid
                if (isset($existingTransaction->$percentCol) && $existingTransaction->$percentCol > 0) {
                    // Recalculate nominal discount based on new adjusted price
                    // Discount is negative, so we negate the result
                    $newDiscount = -abs($adjustedNominalHarga * ($existingTransaction->$percentCol / 100));
                    $discountUpdates[$nominalCol] = $newDiscount;
                    $totalDiscounts += $newDiscount;
                } else {
                    // If no percentage, keep the original nominal (assuming fixed amount like voucher)
                    // Or should we adjust proportionally? 
                    // For now, let's keep it but ensure it doesn't exceed the price if it's a deduction
                    $currentDiscount = $existingTransaction->$nominalCol ?? 0;
                    $totalDiscounts += $currentDiscount;
                }
            }
            
            // Calculate adjusted nominal_fix (adjusted price - discounts + adjustment)
            // Note: totalDiscounts is already negative
            $adjustedNominalFix = max(0, $adjustedNominalHarga + $totalDiscounts - $additionalDeduction);
            
            // Calculate outstanding
            $outstanding = $adjustedNominalFix - ($existingTransaction->saldo_masuk ?? 0);
            
            // Calculate adjusted quantity
            $adjustedQty = $this->calculateAdjustedQuantity($order);
            
            // Update existing financial transaction with adjusted values
            $updateData = array_merge($discountUpdates, [
                'nominal_harga' => $adjustedNominalHarga,
                'nominal_fix' => $adjustedNominalFix,
                'qty' => $adjustedQty,
                'outstanding' => $outstanding,
                'adjustment' => ($existingTransaction->adjustment ?? 0) - $additionalDeduction,
                'adjustment_description' => ($existingTransaction->adjustment_description ?? '') . 
                    ($existingTransaction->adjustment_description ? ' | ' : '') .
                    "Retur sebagian: Rp " . number_format($refundAmount, 0, ',', '.') . 
                    ($additionalDeduction > 0 ? " dengan potongan tambahan: Rp " . number_format($additionalDeduction, 0, ',', '.') : '')
            ]);
            
            switch ($platform) {
                case 'shopee':
                    ShopeeFinancialTransaction::where('order_id', $order->id)->update($updateData);
                    break;
                case 'shopee2':
                    Shopee2FinancialTransaction::where('order_id', $order->id)->update($updateData);
                    break;
                case 'tiktok':
                    TiktokFinancialTransaction::where('order_id', $order->id)->update($updateData);
                    break;
                case 'tiktok2':
                    Tiktok2FinancialTransaction::where('order_id', $order->id)->update($updateData);
                    break;
            }
            
            Log::info("Updated financial transaction for partial refund", [
                'order' => $order->order_number,
                'original_total' => $originalTotal,
                'refund_amount' => $refundAmount,
                'adjusted_nominal_harga' => $adjustedNominalHarga,
                'adjusted_nominal_fix' => $adjustedNominalFix,
                'adjusted_qty' => $adjustedQty,
                'outstanding' => $outstanding,
                'adjustment' => $updateData['adjustment']
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error handling partial refund: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate adjusted quantity after returns
     */
    private function calculateAdjustedQuantity(Order $order): float
    {
        if (!$order->relationLoaded('orderItems')) {
            $order->load('orderItems.platformProduct.mappingBarang');
        }
        
        $totalAdjustedQty = 0;
        
        foreach ($order->orderItems as $item) {
            // Current quantity (already reduced by returns)
            $currentQty = (float)($item->quantity ?? 0);
            $totalAdjustedQty += $currentQty;
        }
        
        return $totalAdjustedQty;
    }
    
    /**
     * Handle offline full refund
     */
    private function handleOfflineFullRefund($offlineSale)
    {
        try {
            DB::beginTransaction();
            
            // Get all invoices related to this sale
            $invoices = $offlineSale->getInvoices();
            
            foreach ($invoices as $invoice) {
                // Recalculate nominal based on updated subtotals
                $newNominal = $this->recalculateInvoiceNominal($invoice);
                
                // Set invoice values to 0 and mark as refunded (retur full)
                $invoice->update([
                    'nominal' => 0,
                    'outstanding' => 0,
                    'status' => 'refunded', // Use 'refunded' to match database enum
                    'notes' => 'Retur penuh - invoice dibatalkan'
                ]);
            }
            
            Log::info("Marked offline sale invoices as retur_full for sale {$offlineSale->id}");
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error handling offline full refund: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle offline partial refund with deduction
     */
    private function handleOfflinePartialRefundWithDeduction($offlineSale, float $refundAmount, float $originalTotal, float $additionalDeduction)
    {
        try {
            DB::beginTransaction();
            
            // Get invoices related to this sale
            $invoices = $offlineSale->getInvoices();
            
            // Get all returs for this sale
            $returs = \App\Models\ReturOfflineSale::where('offline_sale_id', $offlineSale->id)
                ->where('status', 'selesai')
                ->with('details.offlineSaleItem')
                ->get();
            
            foreach ($invoices as $invoice) {
                $oldNominal = $invoice->nominal;
                
                Log::info("Processing invoice for partial refund", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'old_nominal' => $oldNominal
                ]);
                
                // Calculate DPP Original (before retur) - tetap sama, tidak berubah
                $dppOriginal = $this->calculateDPPOriginal($invoice, $returs);
                
                // Calculate retur amount (DPP yang diretur dengan diskon)
                $returAmount = $this->calculateReturAmountForInvoice($invoice, $returs);
                
                // Calculate NET DPP = DPP Original - Retur Amount
                $netDPP = max(0, $dppOriginal - $returAmount);
                $netDPP = \App\Helpers\NumberFormatter::roundToWholeNumber($netDPP);
                
                // Get tax_id from first item - refresh to get latest data
                $firstItem = $invoice->barangKeluarItems()->with('warehouseStock')->first();
                $taxId = $firstItem && $firstItem->warehouseStock && $firstItem->warehouseStock->tax_id 
                    ? $firstItem->warehouseStock->tax_id 
                    : null;
                
                // Calculate grand total from NET DPP (NET DPP + PPN)
                $grandTotal = $netDPP;
                
                if ($taxId == 3) {
                    // PKP: Calculate PPN from NET DPP
                    $netDPP11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($netDPP);
                    $netPPN = \App\Helpers\NumberFormatter::calculatePPN($netDPP11_12);
                    $grandTotal = \App\Helpers\NumberFormatter::calculateGrandTotal($netDPP, $netPPN);
                } else {
                    // Non-PKP: Just round NET DPP
                    $grandTotal = \App\Helpers\NumberFormatter::roundToWholeNumber($netDPP);
                }
                
                // Update invoice with grand total (NET DPP + PPN)
                // Set status as 'partial_refund' to indicate nominal already includes PPN
                $updateData = [
                    'nominal' => $grandTotal,
                    'status' => 'partial_refund',
                    'notes' => 'Retur sebagian - nominal sudah termasuk PPN'
                ];
                
                // Only update outstanding if there's additional deduction
                if ($additionalDeduction > 0) {
                    $updateData['outstanding'] = -$additionalDeduction;
                    $updateData['notes'] = 'Retur dengan potongan tambahan: Rp ' . number_format($additionalDeduction, 0, ',', '.') . ' - nominal sudah termasuk PPN';
                }
                
                $invoice->update($updateData);
                
                // Refresh to verify update
                $invoice->refresh();
                
                Log::info("Invoice updated for partial refund", [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'old_nominal' => $oldNominal,
                    'dpp_original' => $dppOriginal,
                    'retur_amount' => $returAmount,
                    'net_dpp' => $netDPP,
                    'new_nominal_grand_total' => $grandTotal,
                    'tax_id' => $taxId,
                    'status' => $invoice->status
                ]);
            }
            
            Log::info("Updated offline sale invoices for partial refund", [
                'sale_id' => $offlineSale->id,
                'additional_deduction' => $additionalDeduction
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error handling offline partial refund: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate refund amount for online return
     */
    private function calculateReturRefundAmount(ReturPenjualan $returPenjualan): float
    {
        $totalRefund = 0;
        
        foreach ($returPenjualan->details as $detail) {
            $orderItem = $detail->orderItem;
            if ($orderItem) {
                // Calculate price per individual unit handling package/mapping
                $pricePerUnit = $orderItem->price_after_discount;
                
                $platformProduct = $orderItem->platformProduct;
                if ($platformProduct) {
                     $orderCreatedAt = $orderItem->order->created_at ?? $orderItem->created_at;
                     $mappings = MappingBarang::getMappingsForOrderCreatedAt($platformProduct->id, $orderCreatedAt);
                     
                     if ($mappings->count() > 0) {
                         $packageQuantity = $mappings->sum('quantity');
                         if ($packageQuantity > 0) {
                             $pricePerUnit = $orderItem->price_after_discount / $packageQuantity;
                         }
                     }
                }
                
                // detail->qty is in individual units
                $itemRefund = $pricePerUnit * $detail->qty;
                $totalRefund += $itemRefund;
            }
        }
        
        return $totalRefund;
    }
    
    /**
     * Calculate refund amount for offline return
     */
    private function calculateOfflineReturRefundAmount(ReturOfflineSale $returOfflineSale): float
    {
        $totalRefund = 0;
        
        foreach ($returOfflineSale->details as $detail) {
            // Get the offline sale item through the relationship
            $offlineSaleItem = \App\Models\OfflineSaleItem::find($detail->offline_sale_item_id);
            if ($offlineSaleItem) {
                // Konsisten dengan perhitungan invoice: refund juga memperhitungkan diskon
                $itemRefund = $this->calculateOfflineItemReturAmount($offlineSaleItem, (float) $detail->qty);
                $totalRefund += $itemRefund;
            }
        }
        
        return $totalRefund;
    }

    /**
     * Calculate retur amount untuk satu offline sale item (termasuk diskon berjenjang).
     * Dipakai bersama oleh calculateOfflineReturRefundAmount dan calculateReturAmountForInvoice
     * agar perhitungan konsisten.
     */
    private function calculateOfflineItemReturAmount($offlineSaleItem, float $qtyRetur): float
    {
        $basePrice = (float) ($offlineSaleItem->unit_price ?? 0);
        
        // Start with base total (price × qty retur)
        $currentTotal = $basePrice * $qtyRetur;
        
        // Apply percentage discounts (1-5) in cascading order
        for ($i = 1; $i <= 5; $i++) {
            $percentField = "discount_percent_" . $i;
            $discountPercent = (float) ($offlineSaleItem->$percentField ?? 0);
            if ($discountPercent > 0) {
                $currentTotal = \App\Helpers\NumberFormatter::calculatePercentageDiscount($currentTotal, $discountPercent);
            }
        }
        
        // Apply nominal discounts (1-5) in cascading order
        for ($i = 1; $i <= 5; $i++) {
            $amountField = "discount_amount_" . $i;
            $discountAmount = (float) ($offlineSaleItem->$amountField ?? 0);
            if ($discountAmount > 0) {
                $currentTotal = \App\Helpers\NumberFormatter::calculateNominalDiscount($currentTotal, $discountAmount * $qtyRetur);
            }
        }
        
        return \App\Helpers\NumberFormatter::formatForDatabase($currentTotal);
    }
    
    /**
     * Get original order total
     */
    private function getOriginalOrderTotal(Order $order): float
    {
        $total = $order->orderItems->sum(function($item) {
            return $item->price_after_discount * $item->quantity;
        });
        
        // Add shipping cost if exists
        $total += $order->shipping_cost ?? 0;
        
        return $total;
    }
    
    /**
     * Recalculate invoice nominal based on updated subtotals
     */
    private function recalculateInvoiceNominal(FinanceOffline $invoice): float
    {
        // Refresh invoice to get latest data
        $invoice->refresh();
        
        // Get all barang keluar items for this invoice with fresh data
        $barangKeluarItems = $invoice->barangKeluarItems()->with('offlineSaleItem')->get();
        
        $totalNominal = 0;
        
        foreach ($barangKeluarItems as $barangKeluar) {
            if ($barangKeluar->offlineSaleItem) {
                // Refresh offlineSaleItem to get updated subtotal
                $barangKeluar->offlineSaleItem->refresh();
                // Use the updated subtotal from offlineSaleItem
                $totalNominal += $barangKeluar->offlineSaleItem->subtotal ?? 0;
            }
        }
        
        Log::info("Recalculated invoice nominal", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'new_nominal_dpp' => $totalNominal
        ]);
        
        return $totalNominal;
    }
    
    /**
     * Calculate DPP Original (before retur) - tetap sama, tidak berubah
     * Menghitung dari quantity original (current qty + returned qty)
     */
    private function calculateDPPOriginal(FinanceOffline $invoice, $returs): float
    {
        $dppOriginal = 0;
        
        foreach ($invoice->barangKeluarItems as $bk) {
            if ($bk->offlineSaleItem) {
                $osi = $bk->offlineSaleItem;
                $currentQty = $osi->quantity;
                $currentSubtotal = $osi->subtotal ?? 0;
                
                // Calculate returned qty from returs
                $returnedQty = 0;
                foreach ($returs as $retur) {
                    foreach ($retur->details as $detail) {
                        if ($detail->offline_sale_item_id == $osi->id) {
                            $returnedQty += $detail->qty;
                        }
                    }
                }
                
                // Calculate original quantity (before retur)
                $originalQty = $currentQty + $returnedQty;
                
                // Calculate original subtotal
                if ($currentQty > 0) {
                    // Calculate subtotal per unit, then multiply by original qty
                    $subtotalPerUnit = $currentSubtotal / $currentQty;
                    $originalSubtotal = $subtotalPerUnit * $originalQty;
                } else {
                    // If current qty is 0, calculate from unit_price
                    $originalSubtotal = $osi->unit_price * $originalQty;
                }
                
                $dppOriginal += $originalSubtotal;
            }
        }
        
        $dppOriginal = \App\Helpers\NumberFormatter::roundToWholeNumber($dppOriginal);
        
        Log::info("Calculated DPP Original", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'dpp_original' => $dppOriginal
        ]);
        
        return $dppOriginal;
    }
    
    /**
     * Calculate retur amount for invoice (DPP yang diretur dengan diskon)
     */
    private function calculateReturAmountForInvoice(FinanceOffline $invoice, $returs): float
    {
        $returAmount = 0;
        
        foreach ($returs as $retur) {
            foreach ($retur->details as $detail) {
                $offlineSaleItem = $detail->offlineSaleItem;
                if ($offlineSaleItem) {
                    // Check if this item belongs to this invoice
                    $belongsToInvoice = false;
                    foreach ($invoice->barangKeluarItems as $bk) {
                        if ($bk->offlineSaleItem && $bk->offlineSaleItem->id == $offlineSaleItem->id) {
                            $belongsToInvoice = true;
                            break;
                        }
                    }
                    
                    if ($belongsToInvoice) {
                        $qtyRetur = (float) ($detail->qty ?? 0);
                        
                        // Konsisten dengan calculateOfflineReturRefundAmount (termasuk diskon)
                        $currentTotal = $this->calculateOfflineItemReturAmount($offlineSaleItem, $qtyRetur);
                        
                        // Add to retur amount (already includes discounts)
                        $returAmount += \App\Helpers\NumberFormatter::formatForDatabase($currentTotal);
                    }
                }
            }
        }
        
        $returAmount = \App\Helpers\NumberFormatter::roundToWholeNumber($returAmount);
        
        Log::info("Calculated retur amount for invoice", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'retur_amount' => $returAmount
        ]);
        
        return $returAmount;
    }

    /**
     * Normalize platform name to standard key
     */
    private function normalizePlatform(string $platform): string
    {
        $platform = strtolower(trim($platform));

        if (str_contains($platform, 'shopee') && (str_contains($platform, 'trueblu') || str_contains($platform, 'trubleu'))) {
            return 'shopee2';
        }
        if (str_contains($platform, 'tiktok') && (str_contains($platform, 'trueblu') || str_contains($platform, 'trubleu'))) {
            return 'tiktok2';
        }
        if (str_contains($platform, 'shopee') && str_contains($platform, 'lamourad')) {
            return 'shopee';
        }
        if (str_contains($platform, 'tiktok') && str_contains($platform, 'lamourad')) {
            return 'tiktok';
        }
        if (str_contains($platform, 'shopee') && str_contains($platform, 'liefmarket')) {
            return 'shopee2';
        }
        if (str_contains($platform, 'tiktok') && str_contains($platform, 'liefmarket')) {
            return 'tiktok2';
        }
        if (str_contains($platform, 'shopee2')) {
            return 'shopee2';
        }
        if (str_contains($platform, 'shopee')) {
            return 'shopee';
        }
        if (str_contains($platform, 'tokopedia')) {
            return 'tokopedia';
        }
        if (str_contains($platform, 'tiktok2')) {
            return 'tiktok2';
        }
        if (str_contains($platform, 'tiktok')) {
            return 'tiktok';
        }
        if (str_contains($platform, 'blibli')) {
            return 'blibli';
        }

        return $platform;
    }
}