<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Penjualan Offline (After Retur) #{{ $invoice->invoice_number }}</title>
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
            <div class="title">Ini di invoice penjualan offline,</div>
        </div>
        
        @php
            $firstItem = $invoice->barangKeluarItems->first();
            $offlineSale = $firstItem && $firstItem->offlineSaleItem ? $firstItem->offlineSaleItem->offlineSale : null;
            $customer = $offlineSale && $offlineSale->customerInfo ? $offlineSale->customerInfo->name : ($offlineSale ? $offlineSale->customer_name : 'N/A');
            $sjNumber = $offlineSale ? $offlineSale->surat_jalan_number : 'N/A';

            // Satu sumber kebenaran untuk perhitungan nilai finansial
            $calcs = \App\Services\FinanceOfflineCalculator::calculate($invoice);

            $statusBayar = $calcs['is_retur_full']
                ? 'RETUR FULL'
                : ($calcs['remaining_amount'] == 0 ? 'LUNAS' : 'BELUM LUNAS');
        @endphp
        
        <div class="invoice-info">
            <div>
                <strong>No.Invoice :</strong> {{ $invoice->invoice_number }}<br>
                <strong>Tgl Input :</strong> {{ $invoice->tanggal_invoice->format('d-M-Y') }}<br>
                <strong>Tgl Cetak :</strong> {{ now()->format('d-M-Y') }}
            </div>
            <div>
                <strong>Kas / Kredit :</strong> KAS<br>
                <strong>Ref.Retur :</strong> {{ $sjNumber }}<br>
                <strong>Sales :</strong> ADMIN<br>
                <strong>Status Bayar :</strong> {{ $statusBayar }}
            </div>
        </div>
        
        <div class="content-area">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 45%;">Nama Barang</th>
                        <th style="width: 8%;">Qty</th>
                        <th style="width: 8%;">Retur</th>
                        <th style="width: 8%;">DO</th>
                        <th style="width: 8%;">Kirim</th>
                        <th style="width: 8%;">Satuan</th>
                        <th style="width: 10%;">Harga</th>
                        <th style="width: 10%;">Total Harga</th>
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
                        
                        // Group items by product
                        $groupedItems = $invoiceItems->groupBy(function($item) {
                            return $item->warehouseStock && $item->warehouseStock->product ? 
                                $item->warehouseStock->product->name : 'Unknown Product';
                        });
                    @endphp
                    
                    @foreach($groupedItems as $productName => $items)
                        @php
                            $totalProductQty = $items->sum('qty');
                            $firstProductItem = $items->first();
                            $offlineSaleItem = $firstProductItem->offlineSaleItem;
                            $originalQty = $offlineSaleItem ? $offlineSaleItem->quantity : 0;
                            
                            // Calculate returned quantity (original - current)
                            $returQty = max(0, $originalQty - $totalProductQty);
                            $doQty = $totalProductQty; // DO = current quantity
                            $kirimQty = $totalProductQty; // Kirim = current quantity
                            
                            // Calculate pricing
                            $unitPrice = $offlineSaleItem ? $offlineSaleItem->unit_price : 0;
                            $totalPrice = $unitPrice * $kirimQty;
                            
                            // Add to totals
                            $totalQty += $originalQty;
                            $totalRetur += $returQty;
                            $totalDO += $doQty;
                            $totalKirim += $kirimQty;
                            $grandTotal += $totalPrice;
                        @endphp
                        
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td>{{ $productName }}</td>
                            <td class="text-center">{{ number_format($originalQty, 0) }}</td>
                            <td class="text-center">{{ number_format($returQty, 0) }}</td>
                            <td class="text-center">{{ number_format($doQty, 0) }}</td>
                            <td class="text-center">{{ number_format($kirimQty, 0) }}</td>
                            <td class="text-center">CARTON</td>
                            <td class="text-right">{{ number_format($unitPrice, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($totalPrice, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    
                    <tr class="total-row">
                        <td colspan="2" class="text-center"><strong>Total :</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalQty, 0) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalRetur, 0) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalDO, 0) }}</strong></td>
                        <td class="text-center"><strong>{{ number_format($totalKirim, 0) }}</strong></td>
                        <td></td>
                        <td></td>
                        <td class="text-right"><strong>{{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
        
        <div class="notes">
            <strong>Keterangan :</strong><br>
            Customer: {{ $customer }}<br>
            SJ Number: {{ $sjNumber }}<br>
            @if($offlineSale && $offlineSale->notes)
                Notes: {{ $offlineSale->notes }}
            @endif
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
