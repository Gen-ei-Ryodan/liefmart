<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .invoice-container {
            max-width: 210mm; /* F4 width */
            min-height: 297mm; /* F4 height */
            margin: 10px auto;
            padding: 10mm;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        
        .logo {
            width: 180mm;
            height: auto;
            margin-bottom: 5px;
        }
        
        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
            width: 100%;
        }
        
        .company-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            width: 100%;
        }
        
        .company-website {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
            text-align: center;
        }
        
        .horizontal-line {
            border-top: 1px solid #000;
            margin: 5px 0;
        }
        
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        
        .customer-info {
            width: 50%;
        }
        
        .invoice-info {
            width: 40%;
        }
        
        .content-area {
            flex: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            table-layout: fixed;
            page-break-inside: auto;
        }
        
        th, td {
            padding: 3px 4px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 10px;
            page-break-inside: auto;
        }
        
        td.product-description {
            white-space: normal;
            word-wrap: break-word;
        }
        
        td.discount-column {
            white-space: normal;
            word-wrap: break-word;
            font-size: 9px;
            line-height: 1.2;
            text-align: right;
            padding: 2px 4px;
            vertical-align: top;
        }
        
        .discount-item {
            display: block;
            margin-bottom: 1px;
            font-size: 8px;
        }
        
        .discount-item:last-child {
            margin-bottom: 0;
        }
        
        thead th {
            background-color: #f2f2f2;
            font-weight: bold;
            page-break-after: avoid;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .barcode {
            font-family: "Courier New", monospace;
            font-size: 12px;
            letter-spacing: 1px;
        }
        
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .grand-total {
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 20px;
            page-break-inside: avoid;
        }
        
        .signature {
            width: 30%;
            text-align: center;
            padding: 10px 0;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 100px;
            margin-bottom: 10px;
        }
        
        .payment-info {
            margin-top: 15px;
            font-style: italic;
            page-break-inside: avoid;
        }
        
        /* Print styles - keep same as preview */
        @media print {
            @page {
                size: F4;
                margin: 8mm 10mm; /* Margin lebih kecil untuk area lebih luas */
            }
            
            body {
                padding: 0;
                margin: 0;
                font-size: 12px;
            }
            
            .invoice-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
                min-height: auto;
            }
            
            .no-print {
                display: none;
            }
            
            /* Improved page break rules */
            .invoice-container {
                page-break-inside: auto;
            }
            
            /* Table page break rules - more flexible */
            table {
                page-break-inside: auto;
                page-break-before: auto;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            
            tbody {
                display: table-row-group;
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: auto;
                page-break-after: auto;
                page-break-before: auto;
            }
            
            /* Allow natural page breaks in table rows */
            tbody tr {
                page-break-inside: auto;
            }
            
            /* Keep totals and signatures together */
            .totals {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
            
            .signatures {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
            
            .payment-info {
                page-break-before: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="no-print" style="text-align: right; margin-bottom: 10px;">
            <button onclick="window.print();" style="background: #008000; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" style="vertical-align: middle; margin-right: 5px;" viewBox="0 0 16 16">
                    <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
                    <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                </svg>
                Cetak Invoice
            </button>
        </div>
        
        @php
            $printCount = $invoice->print_count ?? 0;
            $isPrintedBefore = $printCount > 0;
            $isReprint = $isPrintedBefore && $invoice->reprint_approved;
            
            // Check if the items are taxable (PKP) and set appropriate logo
            $barangKeluarItems = $invoice->barangKeluarItems;
            $firstItem = $barangKeluarItems->first();
            $isPKP = false;
            $logoFile = 'PKP.jpeg'; // Default logo
            $taxId = null;
            $mainCategoryId = null;
            
            if ($firstItem && $firstItem->warehouseStock && $firstItem->warehouseStock->tax_id) {
                $taxId = $firstItem->warehouseStock->tax_id;
                
                if ($taxId == 3) {
                    // PKP = PKP = PT. HARVEST GLOBAL NIAGA
                    $isPKP = true;
                    $logoFile = 'PKP.jpeg';
                } elseif ($taxId == 4) {
                    // NON PKP = Non-PKP = LaMOURAD
                    $isPKP = false;
                    $logoFile = 'NONPKP.jpeg';
                } else {
                    // Default untuk tax ID lainnya
                    $isPKP = in_array($taxId, [3, 5, 7]);
                    $logoFile = $isPKP ? 'PKP.jpeg' : 'NONPKP.jpeg';
                }
            }

            // Get active bank account
            $activeAccount = \App\Models\BankAccount::getActive();
            
            // Create variables for totals
            $grandTotal = 0;
            $subTotal = 0;
            $totalQty = 0;
            $ppn = 0;
            $totalBeforeDiscount = 0;
            $totalDiscount = 0;
        @endphp
        
        @if($isPrintedBefore)
        <div class="no-print" style="margin-bottom: 15px; padding: 10px; background-color: #ffe9e9; border-radius: 5px; border-left: 4px solid #dc3545;">
            <div style="display: flex; align-items: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#dc3545" style="margin-right: 10px;" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                </svg>
                <div>
                    <strong style="color: #dc3545;">Perhatian!</strong>
                    <p style="margin: 5px 0 0 0;">
                        @if($isReprint)
                            Invoice ini telah dicetak sebelumnya ({{ $printCount }}x). Pencetakan ulang telah disetujui oleh Super Admin.
                        @else
                            Invoice ini telah dicetak sebelumnya ({{ $printCount }}x). Harap cetak dengan hati-hati.
                        @endif
                    </p>
                </div>
            </div>
        </div>
        @endif
        
        <div class="header">
            <div class="company-info">
                <img src="{{ asset('images/INV/' . $logoFile) }}" alt="Logo" class="logo">
            </div>
        </div>
        
        <div class="horizontal-line"></div>
        
        <div class="invoice-details">
            <div class="invoice-info">
                <div><strong>TANGGAL INVOICE</strong> : {{ $invoice->tanggal_invoice->format('d F Y') }}</div>
                <div><strong>NO. INVOICE</strong> : {{ $invoice->invoice_number }}</div>
            </div>
            
            <div class="customer-info">
                <div><strong>CUSTOMER</strong> : 
                @php
                    $offlineSale = null;
                    $customer = null;
                    
                    if ($firstItem && $firstItem->offlineSaleItem) {
                        $offlineSale = $firstItem->offlineSaleItem->offlineSale;
                        if ($offlineSale) {
                            $customer = $offlineSale->customerInfo;
                        }
                    }
                @endphp
                
                @if($customer)
                    {{ $customer->name }}
                </div>
                <div>
                    {{ $customer->address ?? '' }}<br>
                    {{ $customer->phone ? 'Telp: '.$customer->phone : '' }}
                </div>
                @endif
            </div>
        </div>
        
        <div class="horizontal-line"></div>
        
        <div class="content-area">
        <table>
            <thead>
                <tr>
                    <th width="35" class="text-center">No</th>
                    <th width="220">Deskripsi</th>
                    <th width="90">Barcode</th>
                    <th width="55" class="text-center">Qty</th>
                    <th width="90" class="text-right">Harga</th>
                    <th width="80" class="text-right">Diskon</th>
                    <th width="100" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Calculate invoice totals with progressive rounding
                    $invoiceItems = $invoice->barangKeluarItems;
                    
                    $totalBeforeDiscount = 0;
                    $totalDiscount = 0;
                    $totalAfterDiscount = 0;
                    $dpp = 0;
                    $ppn = 0;
                    $grandTotal = 0;
                    $totalQty = 0;
                    $subTotal = 0;
                    
                    // First, calculate total amount before discount and after discount
                    // Use subtotal from database to ensure accuracy
                    // Group by offline_sale_item to handle products with different unit prices
                    // (mis. 16x3333 + 8x3334) dan returns secara akurat
                    $groupedForCalculation = $invoiceItems->groupBy(function($item) {
                        $offlineSaleItem = $item->offlineSaleItem;
                        if (!$offlineSaleItem || !$offlineSaleItem->product) {
                            return 'unknown_' . $item->id;
                        }
                        return 'item_' . $offlineSaleItem->id;
                    });
                    
                    foreach ($groupedForCalculation as $productId => $items) {
                        $firstItem = $items->first();
                        $offlineSaleItem = $firstItem->offlineSaleItem;
                        if (!$offlineSaleItem) continue;
                        
                        // Calculate total qty for this product
                        $sumBarangKeluarQty = 0;
                        foreach ($items as $item) {
                            $sumBarangKeluarQty += $item->qty ?? 0;
                        }
                        
                        // After return, use updated quantity from offlineSaleItem
                        $qty = $offlineSaleItem->quantity < $sumBarangKeluarQty 
                            ? $offlineSaleItem->quantity 
                            : $sumBarangKeluarQty;
                        
                        // Ambil harga dasar
                        $price = $offlineSaleItem->unit_price;
                        $offlineSaleQty = $offlineSaleItem->quantity; // Qty dari offline sale item
                        
                        // Count total pieces (use updated qty after return)
                        $totalQty += $qty;
                        
                        // Tambahkan ke total before discount (harga × qty yang sudah di-update setelah retur)
                        $itemTotal = $price * $qty;
                        $totalBeforeDiscount += $itemTotal;
                        
                        // Calculate discount untuk qty ini (proportional)
                        // Use original quantity for proportion calculation if available
                        $originalQty = $sumBarangKeluarQty; // Original qty before return
                        $proportion = $originalQty > 0 ? $qty / $originalQty : 1;
                        $currentTotal = $itemTotal;
                        
                        // Hitung semua diskon persen (1-5)
                        for($i = 1; $i <= 5; $i++) {
                            $percentField = "discount_percent_" . $i;
                            $amountField = "discount_amount_" . $i;
                            
                            // Diskon persen
                            $discountPercent = $offlineSaleItem->$percentField ?? 0;
                            if($discountPercent > 0) {
                                $discountAmount = $currentTotal * ($discountPercent / 100);
                                $totalDiscount += $discountAmount;
                                $currentTotal -= $discountAmount;
                                // Apply cascading rounding after each discount
                                $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                            }
                            
                            // Diskon nominal (proportional berdasarkan qty)
                            $discountNominal = $offlineSaleItem->$amountField ?? 0;
                            if($discountNominal > 0) {
                                $discountAmount = $discountNominal * $proportion;
                                $totalDiscount += ($discountAmount * $qty);
                                $currentTotal -= ($discountAmount * $qty);
                                // Apply cascading rounding after each discount
                                $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                            }
                        }
                        
                        // Add to total after discount
                        $totalAfterDiscount += $currentTotal;
                    }
                    
                    // Use invoice->nominal which is the sum of all offlineSaleItem->subtotal
                    // This ensures consistency with database
                    $isPKP = $taxId == 3;
                @endphp
                @php
                    // Group items by offline_sale_item to handle products with different unit prices
                    // (mis. 16x3333 + 8x3334) secara akurat dan konsisten dengan perhitungan total
                    $groupedItems = $invoice->barangKeluarItems->groupBy(function($item) {
                        $offlineSaleItem = $item->offlineSaleItem;
                        if (!$offlineSaleItem || !$offlineSaleItem->product) {
                            return 'unknown_' . $item->id;
                        }
                        return 'item_' . $offlineSaleItem->id;
                    });
                    
                    // Calculate total after discount from grouped items (for DPP calculation)
                    $totalAfterDiscountFromGrouped = 0;
                @endphp
                @foreach($groupedItems as $productId => $items)
                    @php
                        // Get first item for product info and price
                        $firstItem = $items->first();
                        $offlineSaleItem = $firstItem->offlineSaleItem;
                        $product = $offlineSaleItem && $offlineSaleItem->product ? $offlineSaleItem->product : null;
                        $price = $offlineSaleItem ? $offlineSaleItem->unit_price : 0;
                        
                        // After return, use updated quantity from offlineSaleItem
                        // This ensures we show the correct quantity after returns
                        // If offlineSaleItem->quantity has been updated (after return), use that
                        // Otherwise, sum qty from barangKeluar
                        $totalQtyForProduct = 0;
                        if ($offlineSaleItem && $offlineSaleItem->quantity > 0) {
                            // Check if this is after a return by comparing with barangKeluar qty sum
                            $sumBarangKeluarQty = 0;
                            foreach ($items as $item) {
                                $sumBarangKeluarQty += $item->qty ?? 0;
                            }
                            
                            // If offlineSaleItem quantity is less than sum of barangKeluar qty, it means there was a return
                            // Use the updated quantity from offlineSaleItem
                            if ($offlineSaleItem->quantity < $sumBarangKeluarQty) {
                                $totalQtyForProduct = $offlineSaleItem->quantity;
                            } else {
                                // No return, use sum from barangKeluar
                                $totalQtyForProduct = $sumBarangKeluarQty;
                            }
                        } else {
                            // Fallback: sum qty from barangKeluar
                            foreach ($items as $item) {
                                $totalQtyForProduct += $item->qty ?? 0;
                            }
                        }
                        
                        // Hitung total sebelum diskon untuk item ini (menggunakan total qty yang sudah digabung)
                        $itemBeforeDiscount = $price * $totalQtyForProduct;
                        $currentTotal = $itemBeforeDiscount;
                        
                        // Hitung diskon persentase (gunakan diskon dari item pertama)
                        $discountItems = [];
                        $hasDiscount = false;
                        for($i = 1; $i <= 5; $i++) {
                            $percentField = "discount_percent_" . $i;
                            $amountField = "discount_amount_" . $i;
                            
                            // Check if either discount exists
                            if($offlineSaleItem->$percentField > 0 || $offlineSaleItem->$amountField > 0) {
                                $hasDiscount = true;
                                $discountText = "D" . $i . ": ";
                                $discountParts = [];
                                
                                if($offlineSaleItem->$percentField > 0) {
                                    $discountParts[] = number_format(\App\Helpers\NumberFormatter::formatForDatabase($offlineSaleItem->$percentField), 0, ',', '.') . '%';
                                }
                                if($offlineSaleItem->$amountField > 0) {
                                    $discountParts[] = 'Rp ' . number_format(\App\Helpers\NumberFormatter::formatForDatabase($offlineSaleItem->$amountField), 0, ',', '.');
                                }
                                
                                $discountText .= implode(' + ', $discountParts);
                                $discountItems[] = '<span class="discount-item">' . $discountText . '</span>';
                                
                                // Calculate discount amount (gunakan total qty yang sudah digabung)
                                if($offlineSaleItem->$percentField > 0) {
                                    $currentTotal = \App\Helpers\NumberFormatter::calculatePercentageDiscount($currentTotal, $offlineSaleItem->$percentField);
                                }
                                
                                if($offlineSaleItem->$amountField > 0) {
                                    $currentTotal = \App\Helpers\NumberFormatter::calculateNominalDiscount($currentTotal, $offlineSaleItem->$amountField * $totalQtyForProduct);
                                }
                            }
                        }
                        
                        // Use subtotal from offlineSaleItem if available (already updated after return)
                        // Otherwise calculate from currentTotal
                        if ($offlineSaleItem && isset($offlineSaleItem->subtotal) && $offlineSaleItem->subtotal > 0) {
                            // Use the updated subtotal from database (already includes return adjustments)
                            $subtotal = \App\Helpers\NumberFormatter::formatForDatabase($offlineSaleItem->subtotal);
                        } else {
                            // Fallback: calculate from currentTotal
                            $subtotal = \App\Helpers\NumberFormatter::formatForDatabase($currentTotal);
                        }
                        
                        $subTotal += $subtotal;
                        $totalAfterDiscountFromGrouped += $subtotal;
                        
                        // Format diskon text
                        $discountDisplay = $hasDiscount ? implode('', $discountItems) : '<span class="discount-item">-</span>';
                    @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="product-description">
                            {{ $product ? $product->name : 'Unknown Product' }}
                        </td>
                        <td>{{ $product && $product->barcode ? $product->barcode : '-' }}</td>
                        <td class="text-center">{{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($totalQtyForProduct), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($price), 2, ',', '.') }}</td>
                        <td class="discount-column">{!! $discountDisplay !!}</td>
                        <td class="text-right">{{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($subtotal), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
          
        </table>
        </div>
        
        @php
            // Calculate DPP from totalAfterDiscountFromGrouped (total harga setelah diskon dari grouped items yang ditampilkan)
            // Ini memastikan DPP sesuai dengan total subtotal yang ditampilkan di invoice
            $dpp = \App\Helpers\NumberFormatter::calculateDPP($totalAfterDiscountFromGrouped);
            $ppn = 0;
            $grandTotal = $dpp;
            
            if ($isPKP) {
                $dpp11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($dpp);
                $ppn = \App\Helpers\NumberFormatter::calculatePPN($dpp11_12);
                $grandTotal = \App\Helpers\NumberFormatter::calculateGrandTotal($dpp, $ppn);
            } else {
                $dpp11_12 = 0;
                $grandTotal = \App\Helpers\NumberFormatter::roundToWholeNumber($dpp);
            }
        @endphp
        
        <div class="horizontal-line"></div>
        
        <div class="totals">
            <div class="total-row">
                <div><strong>TOTAL SEBELUM DISKON :</strong></div>
                <div>{{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($totalBeforeDiscount), 2, ',', '.') }}</div>
            </div>
            
            <div class="total-row">
                <div><strong>TOTAL DISKON :</strong></div>
                <div>({{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($totalDiscount), 2, ',', '.') }})</div>
            </div>
            
            @if($isPKP)
            <div class="horizontal-line" style="margin: 10px 0;"></div>
            
            <div class="total-row">
                <div><strong>DPP :</strong></div>
                <div>{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($dpp) }}</div>
            </div>
            
            <div class="total-row">
                <div><strong>DPP 11/12 :</strong></div>
                <div>{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($dpp11_12) }}</div>
            </div>
            
            <div class="total-row">
                <div><strong>PPN (12% x DPP 11/12) :</strong></div>
                <div>{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($ppn) }}</div>
            </div>
            @endif
        </div>
        
        <div class="horizontal-line"></div>
        
        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
            <div><strong>TOTAL PCS : </strong> {{ number_format(\App\Helpers\NumberFormatter::formatForDatabase($totalQty), 2, ',', '.') }}</div>
            <div style="text-align: right;"><strong>TOTAL Rp. </strong> <span style="border: 1px solid #000; padding: 5px 10px;">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($grandTotal) }}</span></div>
        </div>
        
        <div class="signatures">
            <div class="signature">
                <div>Tanda Terima,</div>
                <div class="signature-line"></div>
                <div>( )</div>
            </div>
            
            <div class="signature" style="text-align: right;">
                <div>Hormat Kami,</div>
                <div class="signature-line"></div>
                <div>
                    @if($taxId == 4)
                        ( NON PKP )
                    @else
                        ( PT. HARVEST GLOBAL NIAGA )
                    @endif
                </div>
            </div>
        </div>
        
        <div class="payment-info">
            <p>Pembayaran transfer ke rekening :<br>
            @if($activeAccount)
                {{ $activeAccount->bank_name }} {{ $activeAccount->account_number }} atas nama {{ $activeAccount->account_name }}
            @else
                @if($taxId == 4)
                    DANAMON ********** atas nama NON PKP
                @else
                    DANAMON ********** atas nama PT. HARVEST GLOBAL NIAGA
                @endif
            @endif
            </p>
        </div>
    </div>
    
</body>
</html>