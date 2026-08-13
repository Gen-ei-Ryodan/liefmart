<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Retur Penjualan Offline #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 10px;
        }
        
        .invoice-container {
            max-width: 210mm; /* F4 width */
            min-height: 297mm; /* F4 height */
            margin: 0 auto;
            padding: 15mm;
            border: 1px solid #000;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .invoice-info div {
            flex: 1;
        }
        
        .content-area {
            flex: 1;
        }
        
        .table-container {
            margin-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: left;
            font-size: 8px;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #f8f8f8;
        }
        
        .notes {
            margin-top: 20px;
            font-size: 10px;
        }
        
        .signature-section {
            margin-top: auto;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 40px;
            margin-bottom: 3px;
        }
        
        .dotted-line {
            border-top: 2px dotted #000;
            margin: 20px 0;
        }
        
        @media print {
            @page {
                size: F4;
                margin: 15mm;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .invoice-container {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="title">Ini invoice returnya</div>
        </div>
        
        @php
            $firstItem = $invoice->barangKeluarItems->first();
            $offlineSale = $firstItem && $firstItem->offlineSaleItem ? $firstItem->offlineSaleItem->offlineSale : null;
            $customer = $offlineSale && $offlineSale->customerInfo ? $offlineSale->customerInfo->name : ($offlineSale ? $offlineSale->customer_name : 'N/A');
            $sjNumber = $offlineSale ? $offlineSale->surat_jalan_number : 'N/A';
            $saleDate = $offlineSale ? $offlineSale->sale_date->format('d-M-Y') : 'N/A';
            
            // Get actual return reference number from the return data
            $returRef = 'N/A';
            if ($offlineSale) {
                $retur = \App\Models\ReturOfflineSale::where('offline_sale_id', $offlineSale->id)
                    ->where('status', 'selesai')
                    ->first();
                if ($retur) {
                    $returRef = $retur->kode_retur;
                }
            }
        @endphp
        
        <div class="content-area">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 30%;">Nama Barang</th>
                        <th style="width: 8%;">Qty</th>
                        <th style="width: 8%;">Retur</th>
                        <th style="width: 8%;">Kirim</th>
                        <th style="width: 10%;">Harga</th>
                        <th style="width: 10%;">Diskon</th>
                        <th style="width: 12%;">Total Harga</th>
                        <th style="width: 15%;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalQty = 0;
                        $totalRetur = 0;
                        $totalDO = 0;
                        $totalKirim = 0;
                        $grandTotal = 0;
                        $invoiceItems = $invoice->barangKeluarItems;
                        $counter = 1;
                        
                        // Group items by offline_sale_item agar produk dengan harga berbeda
                        // (mis. 16x3333 + 8x3334) dihitung per item, bukan digabung pakai harga item pertama
                        $groupedItems = $invoiceItems->groupBy(function($item) {
                            $offlineSaleItem = $item->offlineSaleItem;
                            return $offlineSaleItem ? 'item_' . $offlineSaleItem->id : 'unknown_' . $item->id;
                        });
                    @endphp
                    
                    @foreach($groupedItems as $itemKey => $items)
                        @php
                            $totalProductQty = $items->sum('qty');
                            $firstProductItem = $items->first();
                            $offlineSaleItem = $firstProductItem->offlineSaleItem;
                            $productName = $offlineSaleItem && $offlineSaleItem->product ? $offlineSaleItem->product->name : 'Unknown Product';
                            
                            if ($offlineSaleItem) {
                                // Get current quantities after any returns
                                $currentQty = $offlineSaleItem->quantity;
                                
                                // Get return quantity for this specific item
                                $itemReturQty = \App\Models\ReturOfflineSaleDetail::where('offline_sale_item_id', $offlineSaleItem->id)
                                    ->whereHas('returOfflineSale', function($q) { $q->where('status', 'selesai'); })
                                    ->sum('qty');
                                
                                // Calculate original quantity (current + returned)
                                $originalQty = $currentQty + $itemReturQty;
                                $returQty = $itemReturQty;
                                $doQty = $currentQty; // DO = remaining quantity after return
                                $kirimQty = $currentQty; // Kirim = same as DO (delivered quantity)
                                
                                // Calculate price with discounts applied
                                $unitPrice = $offlineSaleItem->unit_price;
                                $baseTotal = $unitPrice * $kirimQty;
                                $currentTotal = $baseTotal;
                                
                                // Collect all active discounts
                                $discountInfo = [];
                                $totalDiscountAmount = 0;
                                
                                // Apply percentage discounts (1-5)
                                for($i = 1; $i <= 5; $i++) {
                                    $percentField = "discount_percent_" . $i;
                                    $discountPercent = $offlineSaleItem->$percentField ?? 0;
                                    if($discountPercent > 0) {
                                        $discountAmount = $currentTotal * ($discountPercent / 100);
                                        $currentTotal -= $discountAmount;
                                        $totalDiscountAmount += $discountAmount;
                                        $discountInfo[] = $discountPercent . '%';
                                        $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                    }
                                }
                                
                                // Apply amount discounts (1-5)
                                for($i = 1; $i <= 5; $i++) {
                                    $amountField = "discount_amount_" . $i;
                                    $discountAmount = $offlineSaleItem->$amountField ?? 0;
                                    if($discountAmount > 0) {
                                        $totalDiscountPerItem = $discountAmount * $kirimQty;
                                        $currentTotal -= $totalDiscountPerItem;
                                        $totalDiscountAmount += $totalDiscountPerItem;
                                        $discountInfo[] = 'Rp ' . number_format($discountAmount, 0, ',', '.');
                                        $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                    }
                                }
                                
                                $totalPrice = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                $discountDisplay = !empty($discountInfo) ? implode(' + ', $discountInfo) : '-';
                                
                                // Add to totals
                                $totalQty += $originalQty;
                                $totalRetur += $returQty;
                                $totalDO += $doQty;
                                $totalKirim += $kirimQty;
                                $grandTotal += $totalPrice;
                            }
                        @endphp
                        
                        @if(isset($offlineSaleItem) && $offlineSaleItem)
                        <tr>
                            <td class="text-center">{{ $counter++ }}</td>
                            <td>{{ $productName }}</td>
                            <td class="text-center">{{ number_format($originalQty, 0) }}</td>
                            <td class="text-center">{{ number_format($returQty, 0) }}</td>
                            <td class="text-center">{{ number_format($kirimQty, 0) }}</td>
                            <td class="text-right">{{ number_format($unitPrice, 0, ',', '.') }}</td>
                            <td class="text-right">{{ $discountDisplay }}</td>
                            <td class="text-right">{{ number_format($totalPrice, 0, ',', '.') }}</td>
                            <td>{{ $offlineSale && $offlineSale->customerInfo ? $offlineSale->customerInfo->name : 'N/A' }}</td>
                        </tr>
                        @endif
                    @endforeach
                    
                    <tr class="total-row">
                        <td colspan="2" class="text-center"><strong>Total :</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalQty, 0) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalRetur, 0) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalKirim, 0) }}</strong></td>
                        <td></td>
                        <td></td>
                        <td class="text-right"><strong>{{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
        
        <div class="dotted-line"></div>
        
        <div class="invoice-info">
            <div>
                <strong>No.Retur :</strong> {{ $returRef }}<br>
                <strong>Customer :</strong> {{ strtoupper($customer) }}<br>
                <strong>Tgl Input :</strong> {{ $saleDate }}<br>
                <strong>Tgl Cetak :</strong> {{ now()->format('d-M-Y') }}
            </div>
            <div>
                <strong>Kas / Kredit :</strong> KAS - BESOK LUNAS<br>
                <strong>Ref.Retur :</strong> {{ $returRef }}<br>
            </div>
            <div>
                <strong>Sales :</strong> ADMIN<br>
                <strong>Status Bayar :</strong> LUNAS
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Dibuat Oleh</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Disetujui Oleh</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Diterima Oleh</div>
            </div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 