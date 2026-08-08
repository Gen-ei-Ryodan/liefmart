@extends('layouts.app')

@push('styles')
<style>
    .info-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    
    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .info-item {
        border-bottom: 1px solid #f1f3f4;
        padding: 1rem 0;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 1rem;
        color: #212529;
        font-weight: 600;
        margin-bottom: 0;
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.375rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .discount-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        margin: 0.125rem;
        border-radius: 0.25rem;
    }
    
    .product-table {
        border: none;
    }
    
    .product-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border: none;
        padding: 1rem 0.75rem;
    }
    
    .product-table tbody tr {
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.15s ease-in-out;
    }
    
    .product-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .product-table tbody td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border: none;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .summary-item:last-child {
        border-bottom: none;
        padding-top: 1rem;
        margin-top: 0.5rem;
        border-top: 2px solid #dee2e6;
        font-size: 1.125rem;
        font-weight: 700;
    }
    
    .action-btn {
        border-radius: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.2s ease-in-out;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #495057;
        margin-bottom: 0;
        display: flex;
        align-items: center;
    }
    
    .section-title i {
        margin-right: 0.5rem;
        color: #6f42c1;
    }
    
    .main-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
    }
    
    .category-badge {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
</style>
@endpush

@section('content')
@php
    use Shared\Helpers\NumberFormatter;
@endphp

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="main-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-2">Detail Penjualan Offline</h1>
                <div class="d-flex align-items-center gap-3">
                    <span class="category-badge">
                        <i class="fas fa-tag me-1"></i>
                        {{ $offlineSale->mainCategory ? $offlineSale->mainCategory->name : session('main_category_name') }}
                    </span>
                    <span class="text-white-50">|</span>
                    <span class="h5 mb-0">{{ $offlineSale->surat_jalan_number }}</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('sales.offline.list') }}" class="btn btn-light action-btn">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
                <a href="{{ route('sales.offline.print.sj', $offlineSale) }}" class="btn btn-warning action-btn" target="_blank">
                    <i class="fas fa-truck me-2"></i>Cetak SJ
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row g-4">
        <!-- Left Column - Sale Information & Products -->
        <div class="col-lg-8">
            <!-- Sale Information Card -->
            <div class="card info-card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Penjualan
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Nomor Surat Jalan</div>
                                <div class="info-value">{{ $offlineSale->surat_jalan_number }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Tanggal Penjualan</div>
                                <div class="info-value">
                                    <i class="fas fa-calendar me-2 text-primary"></i>
                                    {{ $offlineSale->sale_date->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Nomor PO</div>
                                <div class="info-value">{{ $offlineSale->No_PO ?? 'Tidak ada' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Nama Pelanggan</div>
                                <div class="info-value">
                                    <i class="fas fa-user me-2 text-success"></i>
                                    {{ $offlineSale->customer_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Status Pembayaran</div>
                                <div class="info-value">
                                    @if ($offlineSale->status == 'pending')
                                    <span class="status-badge bg-warning text-dark">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                    @elseif ($offlineSale->status == 'paid')
                                    <span class="status-badge bg-success">
                                        <i class="fas fa-check me-1"></i>Lunas
                                    </span>
                                    @elseif ($offlineSale->status == 'cancelled')
                                    <span class="status-badge bg-danger">
                                        <i class="fas fa-times me-1"></i>Dibatalkan
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Retur</div>
                                <div class="info-value">
                                    @if ($offlineSale->hasReturFull())
                                    <span class="status-badge bg-danger">
                                        <i class="fas fa-undo me-1"></i>Retur Full
                                    </span>
                                    @elseif ($offlineSale->hasReturns())
                                    <span class="status-badge bg-warning text-dark">
                                        <i class="fas fa-undo me-1"></i>Retur Sebagian
                                    </span>
                                    @else
                                    <span class="status-badge bg-secondary">
                                        <i class="fas fa-minus me-1"></i>Tidak Ada
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <div class="info-label">Dibuat Oleh</div>
                                <div class="info-value">
                                    <i class="fas fa-user-tie me-2 text-info"></i>
                                    {{ $offlineSale->createdBy ? $offlineSale->createdBy->name : 'N/A' }}
                                </div>
                            </div>
                        </div>
                        @if($offlineSale->notes)
                        <div class="col-12">
                            <div class="info-item">
                                <div class="info-label">Catatan</div>
                                <div class="info-value">
                                    <i class="fas fa-sticky-note me-2 text-warning"></i>
                                    {{ $offlineSale->notes }}
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Products Card -->
            <div class="card info-card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="section-title">
                        <i class="fas fa-boxes"></i>
                        Daftar Produk
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table product-table mb-0">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Harga</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Diskon</th>
                                    <th class="text-center">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($offlineSale->items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fas fa-cube text-primary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">{{ $item->product->name ?? 'N/A' }}</div>
                                                @if($item->notes)
                                                <small class="text-muted">
                                                    <i class="fas fa-comment me-1"></i>{{ $item->notes }}
                                                </small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-semibold">Rp {{ number_format(NumberFormatter::formatForDatabase($item->unit_price), 2, ',', '.') }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">{{ number_format(NumberFormatter::formatForDatabase($item->quantity), 2, ',', '.') }}</span>
                                    </td>
                                    <td class="text-center">
                                        @php 
                                            $hasDiscount = false;
                                            $activeDiscounts = [];
                                            
                                            // First collect all active discounts with their values
                                            for ($i = 1; $i <= 5; $i++) {
                                                $percentField = "discount_percent_{$i}";
                                                $amountField = "discount_amount_{$i}";
                                                
                                                if (($item->$percentField > 0) || ($item->$amountField > 0)) {
                                                    $hasDiscount = true;
                                                    $activeDiscounts[] = [
                                                        'index' => $i,
                                                        'percent' => $item->$percentField,
                                                        'amount' => $item->$amountField
                                                    ];
                                                }
                                            }

                                            // Sort discounts in ascending order by index (1, 2, 3, 4, 5)
                                            usort($activeDiscounts, function($a, $b) {
                                                return $a['index'] - $b['index'];
                                            });
                                        @endphp
                                        
                                        @if ($hasDiscount)
                                            <div class="d-flex flex-wrap justify-content-center gap-1">
                                                @foreach ($activeDiscounts as $discount)
                                                    <div class="d-flex flex-column align-items-center">
                                                        <span class="discount-badge bg-secondary text-white">D{{ $discount['index'] }}</span>
                                                        <div class="d-flex gap-1 mt-1">
                                                                                                        @if ($discount['percent'] > 0)
                                                <span class="discount-badge bg-primary text-white">{{ number_format(NumberFormatter::formatForDatabase($discount['percent']), 0, ',', '.') }}%</span>
                                            @endif
                                            @if ($discount['amount'] > 0)
                                                <span class="discount-badge bg-info text-white">Rp {{ number_format(NumberFormatter::formatForDatabase($discount['amount']), 2, ',', '.') }}</span>
                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            // Hitung subtotal berdasarkan quantity setelah retur
                                            $basePrice = $item->unit_price;
                                            $currentQty = $item->quantity;
                                            
                                            // Hitung total sebelum diskon
                                            $totalBeforeDiscount = $basePrice * $currentQty;
                                            $currentTotal = $totalBeforeDiscount;
                                            
                                            // Hitung semua diskon persen (1-5)
                                            for($i = 1; $i <= 5; $i++) {
                                                $percentField = "discount_percent_" . $i;
                                                $discountPercent = $item->$percentField ?? 0;
                                                if($discountPercent > 0) {
                                                    $discountAmount = $currentTotal * ($discountPercent / 100);
                                                    $currentTotal -= $discountAmount;
                                                    // Apply cascading rounding after each discount
                                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                                }
                                            }
                                            
                                            // Hitung semua diskon nominal (1-5)
                                            for($i = 1; $i <= 5; $i++) {
                                                $amountField = "discount_amount_" . $i;
                                                $discountAmount = $item->$amountField ?? 0;
                                                if($discountAmount > 0) {
                                                    $currentTotal -= ($discountAmount * $currentQty);
                                                    // Apply cascading rounding after each discount
                                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                                }
                                            }
                                            
                                            $calculatedSubtotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                        @endphp
                                        <span class="fw-bold text-success">Rp {{ number_format($calculatedSubtotal, 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-inbox fa-2x mb-3"></i>
                                            <p class="mb-0">Tidak ada produk</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Summary & Actions -->
        <div class="col-lg-4">
            <!-- Summary Card -->
            <div class="card info-card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="section-title">
                        <i class="fas fa-calculator"></i>
                        Ringkasan Penjualan
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        // Hitung ulang subtotal berdasarkan quantity setelah retur
                        $recalculatedSubtotal = 0;
                        
                        foreach ($offlineSale->items as $item) {
                            $basePrice = $item->unit_price;
                            $currentQty = $item->quantity;
                            
                            // Hitung total sebelum diskon
                            $totalBeforeDiscount = $basePrice * $currentQty;
                            $currentTotal = $totalBeforeDiscount;
                            
                            // Hitung semua diskon persen (1-5)
                            for($i = 1; $i <= 5; $i++) {
                                $percentField = "discount_percent_" . $i;
                                $discountPercent = $item->$percentField ?? 0;
                                if($discountPercent > 0) {
                                    $discountAmount = $currentTotal * ($discountPercent / 100);
                                    $currentTotal -= $discountAmount;
                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                }
                            }
                            
                            // Hitung semua diskon nominal (1-5)
                            for($i = 1; $i <= 5; $i++) {
                                $amountField = "discount_amount_" . $i;
                                $discountAmount = $item->$amountField ?? 0;
                                if($discountAmount > 0) {
                                    $currentTotal -= ($discountAmount * $currentQty);
                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                }
                            }
                            
                            $recalculatedSubtotal += \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                        }
                        
                        // Hitung ulang total pembayaran (subtotal + tax jika ada)
                        $recalculatedTotal = $recalculatedSubtotal + ($offlineSale->tax_amount > 0 ? $offlineSale->tax_amount : 0);
                    @endphp
                    
                    <div class="summary-item">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold">Rp {{ number_format($recalculatedSubtotal, 0, ',', '.') }}</span>
                    </div>

                    @if ($offlineSale->tax_amount > 0)
                    <div class="summary-item">
                        <span class="text-muted">
                            <i class="fas fa-receipt me-1"></i>Pajak
                        </span>
                        <span class="fw-semibold">Rp {{ number_format(round($offlineSale->tax_amount), 0, ',', '.') }}</span>
                    </div>
                    @endif

                    <div class="summary-item">
                        <span class="text-primary">
                            <i class="fas fa-money-bill-wave me-1"></i>Total Pembayaran
                        </span>
                        <span class="text-primary h5 mb-0">Rp {{ number_format($recalculatedTotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card info-card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="section-title">
                        <i class="fas fa-cogs"></i>
                        Tindakan
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-3">
                        <a href="{{ route('sales.offline.print.sj', $offlineSale) }}" class="btn btn-primary action-btn" target="_blank">
                            <i class="fas fa-print me-2"></i>
                            Cetak Surat Jalan
                        </a>
                        
                    </div>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="card info-card shadow-sm border-0 mt-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="section-title">
                        <i class="fas fa-chart-line"></i>
                        Statistik Cepat
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <div class="h4 text-primary mb-1">{{ $offlineSale->items->count() }}</div>
                                <small class="text-muted">Item Produk</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h4 text-success mb-1">{{ round($offlineSale->items->sum('quantity')) }}</div>
                            <small class="text-muted">Total Qty</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
 