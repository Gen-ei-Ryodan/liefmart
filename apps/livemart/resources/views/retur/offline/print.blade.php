<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Retur Offline - {{ $returOfflineSale->kode_retur }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-box {
            flex: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 11px;
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
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 60px;
            margin-bottom: 5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            
            .container {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">DOKUMEN RETUR PENJUALAN OFFLINE</div>
            <div>{{ $returOfflineSale->kode_retur }}</div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <strong>Informasi Retur:</strong><br>
                Kode Retur: {{ $returOfflineSale->kode_retur }}<br>
                Tanggal Retur: {{ $returOfflineSale->tanggal_retur->format('d/m/Y') }}<br>
                Status: {{ ucfirst($returOfflineSale->status) }}<br>
                Dibuat oleh: {{ $returOfflineSale->user->name }}
            </div>
            <div class="info-box">
                <strong>Informasi Penjualan Asli:</strong><br>
                No. Surat Jalan: {{ $returOfflineSale->offlineSale->surat_jalan_number }}<br>
                Tanggal Penjualan: {{ $returOfflineSale->offlineSale->sale_date->format('d/m/Y') }}<br>
                Customer: {{ $returOfflineSale->offlineSale->customerInfo->name ?? $returOfflineSale->offlineSale->customer_name ?? 'N/A' }}<br>
                No. PO: {{ $returOfflineSale->offlineSale->No_PO ?? '-' }}
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 30%;">Nama Produk</th>
                    <th style="width: 10%;">Qty Asli</th>
                    <th style="width: 10%;">Qty Retur</th>
                    <th style="width: 10%;">Kondisi</th>
                    <th style="width: 10%;">Harga Satuan</th>
                    <th style="width: 15%;">Diskon</th>
                    <th style="width: 15%;">Subtotal Retur</th>
                    <th style="width: 20%;">Alasan</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalQtyAsli = 0;
                    $totalQtyRetur = 0;
                    $grandTotalRetur = 0;
                    $totalDiskonRetur = 0;
                    
                    // Get tax_id from first detail item's barang keluar
                    $taxId = null;
                    $firstDetail = $returOfflineSale->details->first();
                    if ($firstDetail && $firstDetail->offlineSaleItem) {
                        $barangKeluar = $firstDetail->offlineSaleItem->barangKeluar()->with('warehouseStock')->first();
                        if ($barangKeluar && $barangKeluar->warehouseStock) {
                            $taxId = $barangKeluar->warehouseStock->tax_id;
                        }
                    }
                @endphp
                
                @foreach($returOfflineSale->details as $index => $detail)
                    @php
                        $qtyAsli = $detail->offlineSaleItem ? $detail->offlineSaleItem->quantity : 0;
                        $qtyRetur = $detail->qty;
                        $hargaSatuan = $detail->offlineSaleItem ? $detail->offlineSaleItem->unit_price : 0;
                        $currentTotal = $hargaSatuan * $qtyRetur;
                        $diskonText = [];
                        $diskonRetur = 0;
                        // Hitung diskon persen dan nominal (1-5)
                        for($i = 1; $i <= 5; $i++) {
                            $percentField = "discount_percent_" . $i;
                            $amountField = "discount_amount_" . $i;
                            $percent = $detail->offlineSaleItem ? ($detail->offlineSaleItem->$percentField ?? 0) : 0;
                            $amount = $detail->offlineSaleItem ? ($detail->offlineSaleItem->$amountField ?? 0) : 0;
                            if($percent > 0) {
                                $diskon = $currentTotal * ($percent / 100);
                                $diskonRetur += $diskon;
                                $diskonText[] = number_format($percent, 2, ',', '.') . '%';
                                $currentTotal -= $diskon;
                                $currentTotal = round($currentTotal, 2);
                            }
                            if($amount > 0) {
                                $diskon = $amount * $qtyRetur;
                                $diskonRetur += $diskon;
                                $diskonText[] = 'Rp ' . number_format($amount, 0, ',', '.');
                                $currentTotal -= $diskon;
                                $currentTotal = round($currentTotal, 2);
                            }
                        }
                        $totalQtyAsli += $qtyAsli;
                        $totalQtyRetur += $qtyRetur;
                        $grandTotalRetur += $currentTotal;
                        $totalDiskonRetur += $diskonRetur;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $detail->product->name ?? 'N/A' }}</td>
                        <td class="text-center">{{ number_format($qtyAsli, 0) }}</td>
                        <td class="text-center">{{ number_format($qtyRetur, 0) }}</td>
                        <td class="text-center">
                            @if($detail->kondisi == 'BAGUS')
                                <span style="color: green;">{{ $detail->kondisi }}</span>
                            @elseif($detail->kondisi == 'RUSAK')
                                <span style="color: red;">{{ $detail->kondisi }}</span>
                            @else
                                <span style="color: orange;">{{ $detail->kondisi }}</span>
                            @endif
                        </td>
                        <td class="text-right">Rp {{ number_format($hargaSatuan, 0, ',', '.') }}</td>
                        <td class="text-right">{!! implode('<br>', $diskonText) ?: '-' !!}</td>
                        <td class="text-right">Rp {{ number_format($currentTotal, 0, ',', '.') }}</td>
                        <td>{{ $detail->alasan ?? '-' }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-center"><strong>TOTAL</strong></td>
                    <td class="text-center"><strong>{{ number_format($totalQtyRetur, 0) }}</strong></td>
                    <td></td>
                    <td></td>
                    <td class="text-right"><strong>Rp {{ number_format($totalDiskonRetur, 0, ',', '.') }}</strong></td>
                    <td class="text-right"><strong>Rp {{ number_format($grandTotalRetur, 0, ',', '.') }}</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        @php
            // Calculate DPP, PPN, and Grand Total
            // grandTotalRetur adalah total setelah diskon (DPP retur)
            $dpp = \App\Helpers\NumberFormatter::calculateDPP($grandTotalRetur);
            $ppn = 0;
            $grandTotal = \App\Helpers\NumberFormatter::roundToWholeNumber($grandTotalRetur);
            
            if ($taxId == 3) {
                // PKP: Include PPN
                $dpp = \App\Helpers\NumberFormatter::roundToWholeNumber($grandTotal / 1.11);
                $dpp11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($dpp);
                $ppn = $grandTotal - $dpp;
            } else {
                // Non-PKP: No PPN
                $dpp = $grandTotal;
                $dpp11_12 = 0;
                $ppn = 0;
            }
        @endphp
        
        <div style="margin-top: 20px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; text-align: right; padding-right: 20px; font-weight: bold;">DPP (Dasar Pengenaan Pajak)</td>
                    <td style="width: 50%; text-align: right; font-weight: bold;">Rp {{ number_format($dpp, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="text-align: right; padding-right: 20px; font-weight: bold;">PPN (11%)</td>
                    <td style="text-align: right; font-weight: bold;">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <td style="text-align: right; padding-right: 20px; font-weight: bold;">TOTAL (DPP + PPN)</td>
                    <td style="text-align: right; font-weight: bold;">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
        
        @if($returOfflineSale->catatan)
        <div style="margin-bottom: 20px;">
            <strong>Catatan:</strong><br>
            {{ $returOfflineSale->catatan }}
        </div>
        @endif
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div>Dibuat Oleh</div>
                <div>{{ $returOfflineSale->user->name }}</div>
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
        
        <div style="margin-top: 20px; font-size: 10px; color: #666;">
            <div>Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}</div>
            <div>Dokumen ini digenerate secara otomatis oleh sistem</div>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 