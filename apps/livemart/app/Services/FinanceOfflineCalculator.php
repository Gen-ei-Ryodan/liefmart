<?php

namespace App\Services;

use App\Helpers\NumberFormatter;
use App\Models\FinanceOffline;
use App\Models\ReturOfflineSale;

/**
 * Satu-satunya sumber kebenaran (single source of truth) untuk perhitungan
 * nilai finansial invoice offline: DPP original, retur, net total, dan status
 * pembayaran. Dipakai bersama oleh controller, export, dan view agar angka
 * selalu konsisten di semua alur.
 */
class FinanceOfflineCalculator
{
    /**
     * Hitung seluruh nilai finansial untuk satu invoice offline.
     *
     * @param  \App\Models\FinanceOffline  $invoice
     * @return array
     */
    public static function calculate(FinanceOffline $invoice): array
    {
        $invoice->loadMissing([
            'barangKeluarItems',
            'barangKeluarItems.warehouseStock',
            'barangKeluarItems.offlineSaleItem',
            'barangKeluarItems.offlineSaleItem.offlineSale',
            'payments',
        ]);

        $firstItem = $invoice->barangKeluarItems->first();
        $taxId = $firstItem && $firstItem->warehouseStock && $firstItem->warehouseStock->tax_id
            ? $firstItem->warehouseStock->tax_id
            : null;

        $returNumbers = [];
        $returAmount = 0;
        $dppOriginal = 0;

        $offlineSale = ($firstItem && $firstItem->offlineSaleItem && $firstItem->offlineSaleItem->offlineSale)
            ? $firstItem->offlineSaleItem->offlineSale
            : null;

        if ($offlineSale) {
            // Pakai retur yang sudah eager-loaded jika ada, biar tidak query ulang
            $returs = $offlineSale->relationLoaded('returOfflineSales')
                ? $offlineSale->returOfflineSales
                : ReturOfflineSale::where('offline_sale_id', $offlineSale->id)
                    ->where('status', 'selesai')
                    ->with('details.offlineSaleItem')
                    ->get();

            // DPP Original = subtotal dari qty original (sebelum retur).
            // Hanya hitung tiap offline_sale_item sekali (bisa terpecah di banyak barang keluar).
            $processedSaleItemIds = [];
            foreach ($invoice->barangKeluarItems as $bk) {
                if (!$bk->offlineSaleItem) {
                    continue;
                }

                $osi = $bk->offlineSaleItem;

                if (in_array($osi->id, $processedSaleItemIds)) {
                    continue;
                }
                $processedSaleItemIds[] = $osi->id;

                $currentQty = $osi->quantity;
                $currentSubtotal = $osi->subtotal ?? 0;

                $returnedQty = 0;
                foreach ($returs as $retur) {
                    foreach ($retur->details as $detail) {
                        if ($detail->offline_sale_item_id == $osi->id) {
                            $returnedQty += $detail->qty;
                        }
                    }
                }

                $originalQty = $currentQty + $returnedQty;

                if ($currentQty > 0) {
                    $originalSubtotal = ($currentSubtotal / $currentQty) * $originalQty;
                } else {
                    $originalSubtotal = $osi->unit_price * $originalQty;
                }

                $dppOriginal += $originalSubtotal;
            }

            // Retur amount = nilai item diretur dengan diskon berjenjang.
            foreach ($returs as $retur) {
                $returNumbers[] = $retur->kode_retur;

                foreach ($retur->details as $detail) {
                    $offlineSaleItem = $detail->offlineSaleItem;
                    if ($offlineSaleItem) {
                        $returAmount += self::calculateItemValue($offlineSaleItem, (float) ($detail->qty ?? 0));
                    }
                }
            }
        } else {
            $dppOriginal = NumberFormatter::roundToWholeNumber($invoice->nominal);
        }

        $dppOriginal = NumberFormatter::roundToWholeNumber($dppOriginal);
        $returAmount = NumberFormatter::roundToWholeNumber($returAmount);

        // NET = DPP original - retur
        $netDPP = NumberFormatter::roundToWholeNumber(max(0, $dppOriginal - $returAmount));

        $netPPN = 0;
        if ($taxId == 3) {
            $netDPP11_12 = NumberFormatter::calculateDPP1112($netDPP);
            $netPPN = NumberFormatter::roundToWholeNumber(NumberFormatter::calculatePPN($netDPP11_12));
        }

        $netTotal = NumberFormatter::roundToWholeNumber($netDPP + $netPPN);

        $totalPaid = NumberFormatter::roundToWholeNumber($invoice->payments->sum('amount'));
        $remainingAmount = NumberFormatter::roundToWholeNumber(max(0, $netTotal - $totalPaid));

        $dbStatus = $invoice->status ?? 'unpaid';
        $isReturFull = in_array($dbStatus, ['refunded', 'retur_full']);
        $isTidakBalance = !$isReturFull && $totalPaid > $netTotal && $totalPaid > 0;

        if ($isReturFull) {
            $paymentStatus = 'retur_full';
        } elseif ($isTidakBalance) {
            $paymentStatus = 'tidak_balance';
        } elseif ($remainingAmount == 0) {
            $paymentStatus = 'lunas';
        } else {
            $paymentStatus = 'belum_lunas';
        }

        return [
            'tax_id' => $taxId,
            'dpp_original' => $dppOriginal,
            'retur_amount' => $returAmount,
            'net_dpp' => $netDPP,
            'net_ppn' => $netPPN,
            'net_total' => $netTotal,
            'total_paid' => $totalPaid,
            'remaining_amount' => $remainingAmount,
            'db_status' => $dbStatus,
            'payment_status' => $paymentStatus,
            'is_fully_paid' => $remainingAmount == 0 && !$isReturFull,
            'is_tidak_balance' => $isTidakBalance,
            'is_retur_full' => $isReturFull,
            'has_partial_return' => $returAmount > 0 && !$isReturFull && $invoice->nominal > 0,
            'retur_numbers' => $returNumbers,
        ];
    }

    /**
     * Hitung nilai satu item dengan semua diskon (persen & nominal, berjenjang).
     *
     * @param  object  $item
     * @param  float  $qty
     * @return float
     */
    private static function calculateItemValue($item, float $qty): float
    {
        $currentTotal = (float) ($item->unit_price ?? 0) * $qty;

        for ($i = 1; $i <= 5; $i++) {
            $discountPercent = (float) ($item->{'discount_percent_' . $i} ?? 0);
            if ($discountPercent > 0) {
                $currentTotal = NumberFormatter::calculatePercentageDiscount($currentTotal, $discountPercent);
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $discountAmount = (float) ($item->{'discount_amount_' . $i} ?? 0);
            if ($discountAmount > 0) {
                $currentTotal = NumberFormatter::calculateNominalDiscount($currentTotal, $discountAmount * $qty);
            }
        }

        return NumberFormatter::formatForDatabase($currentTotal);
    }
}
