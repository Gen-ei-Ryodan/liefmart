@extends('layouts.app')

@section('content')
<div class="ds-page-header">
    <div>
        <h1 class="text-gradient">{{ __('Daftar Barang Penjualan Offline') }}</h1>
    </div>
    <div>
        <a href="{{ route('finance.offline.invoices') }}" class="btn btn-sm btn-outline-primary me-2">
            <i class="fas fa-list-alt me-1"></i> List Invoice
        </a>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-home me-1"></i> Dashboard
        </a>
    </div>
</div>

<div class="container-fluid px-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">

                <div class="card-body">
                    <!-- Session Messages -->
                    @if(session('success') || session('error'))
                        <div class="alert alert-{{ session('success') ? 'success' : 'danger' }} alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-{{ session('success') ? 'check-circle' : 'exclamation-circle' }} me-2"></i>
                                <strong>{{ session('success') ? 'Sukses!' : 'Error!' }}</strong>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <p class="mb-0 mt-2">{!! session('success') ?? session('error') !!}</p>
                        </div>
                    @endif

                    <!-- Filter Section -->
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-primary text-white py-2">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-filter me-2"></i> Filter Data
                            </h6>
                        </div>
                            <div class="card-body py-3">
                            <form action="{{ route('finance.offline.index') }}" method="GET">
                                <div class="row g-3">
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="date_start" class="form-label small fw-bold">Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-sm" id="date_start" name="date_start" value="{{ request('date_start') }}">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="date_end" class="form-label small fw-bold">Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-sm" id="date_end" name="date_end" value="{{ request('date_end') }}">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="no_po" class="form-label small fw-bold">No. PO</label>
                                        <input type="text" class="form-control form-control-sm" id="no_po" name="no_po" placeholder="Cari PO..." value="{{ request('no_po') }}">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="sj_number" class="form-label small fw-bold">No. Surat Jalan</label>
                                        <input type="text" class="form-control form-control-sm" id="sj_number" name="sj_number" placeholder="Cari SJ..." value="{{ request('sj_number') }}">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="customer" class="form-label small fw-bold">Customer</label>
                                        <input type="text" class="form-control form-control-sm" id="customer" name="customer" placeholder="Cari customer..." value="{{ request('customer') }}">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-6">
                                        <label for="tax_id" class="form-label small fw-bold">Kategori Pajak</label>
                                        <select class="form-select form-select-sm" id="tax_id" name="tax_id">
                                            <option value="">Semua</option>
                                            <option value="3" {{ request('tax_id') == '3' ? 'selected' : '' }}>PKP</option>
                                            <option value="4" {{ request('tax_id') == '4' ? 'selected' : '' }}>Non-PKP</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label for="product_name" class="form-label small fw-bold">Nama Produk</label>
                                        <input type="text" class="form-control form-control-sm" id="product_name" name="product_name" placeholder="Cari produk..." value="{{ request('product_name') }}">
                                        <small class="form-text text-muted">Cari berdasarkan nama produk</small>
                                    </div>
                                    <div class="col-lg-2 col-md-3 col-sm-6">
                                        <label for="invoice_status" class="form-label small fw-bold">Status Invoice</label>
                                        <select class="form-select form-select-sm" id="invoice_status" name="invoice_status">
                                            <option value="">Semua</option>
                                            <option value="with_invoice" {{ request('invoice_status') == 'with_invoice' ? 'selected' : '' }}>Sudah Ada Invoice</option>
                                            <option value="no_invoice" {{ request('invoice_status') == 'no_invoice' ? 'selected' : '' }}>Belum Ada Invoice</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-1 col-md-3 col-sm-6 d-flex align-items-end">
                                        <div class="d-grid w-100 gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-search me-1"></i> Filter
                                            </button>
                                            <a href="{{ route('finance.offline.index') }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-times me-1"></i> Reset
                                            </a>
                                        </div>
                                        </div>
                                    </div>
                                </form>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0 fw-bold text-primary">
                                <i class="fas fa-list me-2"></i> Daftar Barang Penjualan
                            </h6>
                            <span class="badge bg-primary">{{ $groupedItems->total() }} data</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>No. PO</th>
                                            <th>Customer</th>
                                            <th>Tanggal</th>
                                            <th>No. SJ</th>
                                            <th>Tax ID</th>
                                            <th>Kategori</th>
                                            <th>Produk</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Sub Total (Rp)</th>
                                            <th>Invoice</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $counter = 0; @endphp
                                        @forelse($groupedItems as $offlineSaleId => $items)
                                            @php
                                                $firstItem = $items->first();
                                                $offlineSale = $firstItem->offlineSaleItem && $firstItem->offlineSaleItem->offlineSale ? 
                                                    $firstItem->offlineSaleItem->offlineSale : null;
                                                
                                                // Get unique invoice IDs and numbers for this PO
                                                $invoiceData = collect($items->map(function($item) {
                                                    return $item->financeOffline ? [
                                                        'id' => $item->financeOffline->id,
                                                        'number' => $item->financeOffline->invoice_number
                                                    ] : null;
                                                })->filter()->all())->unique('id')->values()->all();
                                                $invoiceNumbers = collect($invoiceData)->pluck('number')->all();
                                                
                                                // Check if all items have invoices
                                                $allHaveInvoices = $items->every(function($item) {
                                                    return $item->financeOffline !== null;
                                                });
                                                
                                                $poNumber = $offlineSale ? $offlineSale->No_PO : 'No PO';
                                                $customer = $offlineSale && $offlineSale->customerInfo ? $offlineSale->customerInfo->name : 'No Customer';
                                                $saleDate = $offlineSale && $offlineSale->sale_date ? $offlineSale->sale_date->format('d/m/Y') : '-';
                                                $sjNumber = $offlineSale ? $offlineSale->surat_jalan_number : '-';
                                                
                                                // Get main category information
                                                $mainCategoryName = session('main_category_name', 'Kategori tidak diketahui');
                                                if ($offlineSale && $offlineSale->mainCategory) {
                                                    $mainCategoryName = $offlineSale->mainCategory->name;
                                                }
                                            @endphp
                                            
                                            @php
                                                // Group items by product_id (same as print_invoice.blade.php)
                                                $groupedByProduct = $items->groupBy(function($item) {
                                                    $offlineSaleItem = $item->offlineSaleItem;
                                                    if (!$offlineSaleItem || !$offlineSaleItem->product) {
                                                        return 'unknown_' . $item->id;
                                                    }
                                                    return $offlineSaleItem->product->id;
                                                });
                                            @endphp
                                            
                                            @foreach($groupedByProduct as $productId => $productItems)
                                                @php 
                                                    $counter++;
                                                    $isFirstProduct = $loop->first;
                                                    $rowClass = $isFirstProduct ? 'border-top border-primary' : '';
                                                    
                                                    // Get first item for product info
                                                    $firstItem = $productItems->first();
                                                    $offlineSaleItem = $firstItem->offlineSaleItem;
                                                    
                                                    // Tax ID badge (use first item's tax_id)
                                                    $taxId = '-';
                                                    $taxBadgeClass = 'bg-secondary';
                                                    $taxLabel = '-';
                                                    
                                                    if($firstItem->warehouseStock && $firstItem->warehouseStock->tax_id) {
                                                        $taxId = $firstItem->warehouseStock->tax_id;
                                                        if($taxId == 3) {
                                                            $taxBadgeClass = 'bg-primary';
                                                            $taxLabel = 'PKP';
                                                        } elseif($taxId == 4) {
                                                            $taxBadgeClass = 'bg-info';
                                                            $taxLabel = 'Non-PKP';
                                                        }
                                                    }
                                                    
                                                    // Product data
                                                    $productName = '-';
                                                    $productCode = '';
                                                    $product = $offlineSaleItem && $offlineSaleItem->product ? $offlineSaleItem->product : null;
                                                    if($product) {
                                                        $productName = $product->name;
                                                        $productCode = $product->code ? ' ('.$product->code.')' : '';
                                                    } elseif($firstItem->warehouseStock && $firstItem->warehouseStock->product) {
                                                        $productName = $firstItem->warehouseStock->product->name;
                                                        $productCode = $firstItem->warehouseStock->product->code ? ' ('.$firstItem->warehouseStock->product->code.')' : '';
                                                    }
                                                    
                                                    // Calculate total qty for this product (sum all qty from all items)
                                                    $totalQty = 0;
                                                    foreach($productItems as $item) {
                                                        $totalQty += $item->qty ?? 0;
                                                    }
                                                    
                                                    // Calculate total subtotal for this product
                                                    // Sum subtotal dari setiap offline_sale_item unik, bukan re-kalkulasi
                                                    // dari basePrice * totalQty, agar item se-produk dengan unit_price
                                                    // berbeda tidak kehilangan selisihnya.
                                                    $totalSubTotal = 0;
                                                    $processedSubtotalItemIds = [];
                                                    foreach($productItems as $subItem) {
                                                        $subSaleItem = $subItem->offlineSaleItem;
                                                        if(!$subSaleItem) {
                                                            continue;
                                                        }
                                                        if(in_array($subSaleItem->id, $processedSubtotalItemIds, true)) {
                                                            continue;
                                                        }
                                                        $processedSubtotalItemIds[] = $subSaleItem->id;

                                                        $itemSubtotal = $subSaleItem->subtotal ?? 0;
                                                        if($itemSubtotal <= 0) {
                                                            // Fallback: subtotal belum terisi (data lama), hitung dari
                                                            // unit_price * qty (qty dari barang_keluar) dikurangi diskon.
                                                            $itemQty = 0;
                                                            foreach($productItems as $bk) {
                                                                if($bk->offlineSaleItem && $bk->offlineSaleItem->id === $subSaleItem->id) {
                                                                    $itemQty += $bk->qty ?? 0;
                                                                }
                                                            }
                                                            $currentTotal = ($subSaleItem->unit_price ?? 0) * $itemQty;

                                                            for($i = 1; $i <= 5; $i++) {
                                                                $percentField = "discount_percent_" . $i;
                                                                $discountPercent = $subSaleItem->$percentField ?? 0;
                                                                if($discountPercent > 0) {
                                                                    $currentTotal -= $currentTotal * ($discountPercent / 100);
                                                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                                                }
                                                            }
                                                            for($i = 1; $i <= 5; $i++) {
                                                                $amountField = "discount_amount_" . $i;
                                                                $discountAmount = $subSaleItem->$amountField ?? 0;
                                                                if($discountAmount > 0) {
                                                                    $currentTotal -= ($discountAmount * $itemQty);
                                                                    $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                                                }
                                                            }

                                                            $itemSubtotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
                                                        }

                                                        $totalSubTotal += $itemSubtotal;
                                                    }
                                                    $totalSubTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($totalSubTotal);
                                                    
                                                    // Get invoice info (use first item's invoice found)
                                                    $invoiceInfo = null;
                                                    foreach($productItems as $item) {
                                                        if($item->financeOffline) {
                                                            $invoiceInfo = $item->financeOffline;
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                
                                                <tr class="{{ $rowClass }}">
                                                    <td class="text-center">{{ $counter }}</td>
                                                    @if($isFirstProduct)
                                                        <td rowspan="{{ count($groupedByProduct) }}">{{ $poNumber }}</td>
                                                        <td rowspan="{{ count($groupedByProduct) }}">{{ $customer }}</td>
                                                        <td rowspan="{{ count($groupedByProduct) }}">{{ $saleDate }}</td>
                                                        <td rowspan="{{ count($groupedByProduct) }}" class="text-wrap">{{ $sjNumber }}</td>
                                                    @endif
                                                    <td class="text-center">
                                                        <span class="badge {{ $taxBadgeClass }}">{{ $taxLabel }}</span>
                                                    </td>
                                                    @if($isFirstProduct)
                                                        <td rowspan="{{ count($groupedByProduct) }}">{{ $mainCategoryName }}</td>
                                                    @endif
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="product-name" title="{{ $productName }}">{{ $productName }}</span>
                                                            @if($productCode)
                                                                <small class="text-muted">{{ $productCode }}</small>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-center">{{ number_format($totalQty, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format(round($totalSubTotal), 0, ',', '.') }}</td>
                                                    <td>
                                                        @if($invoiceInfo)
                                                            <a href="{{ route('finance.offline.print-invoice', $invoiceInfo->id) }}" 
                                                               class="badge bg-success text-decoration-none" 
                                                               target="_blank">
                                                                {{ $invoiceInfo->invoice_number }}
                                                            </a>
                                                        @else
                                                            <span class="badge bg-secondary">Belum Ada</span>
                                                        @endif
                                                    </td>
                                                    @if($isFirstProduct)
                                                        <td rowspan="{{ count($groupedByProduct) }}" class="text-center align-middle">
                                                            @if(!$allHaveInvoices)
                                                                <a href="{{ route('finance.offline.generate-invoice', $offlineSaleId) }}" 
                                                                   class="btn btn-sm btn-primary" 
                                                                   title="Generate Invoice">
                                                                    <i class="fas fa-file-invoice"></i>
                                                                </a>
                                                                                                        @elseif(count($invoiceNumbers) > 0)
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-success dropdown-toggle" type="button" aria-expanded="false">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        @foreach($invoiceData as $invoice)
                                                            <li>
                                                                <a href="{{ route('finance.offline.print-invoice', $invoice['id']) }}" 
                                                                   class="dropdown-item" 
                                                                   target="_blank">
                                                                    <i class="fas fa-file-invoice text-success me-2"></i>
                                                                    {{ $invoice['number'] }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                        @if($offlineSale && $offlineSale->hasReturns())
                                                            <li><hr class="dropdown-divider"></li>
                                                            @foreach($invoiceNumbers as $invoiceNumber)
                                                                <li>
                                                                    <a href="{{ route('finance.offline.print-return-invoice', $invoiceNumber) }}" 
                                                                       class="dropdown-item" 
                                                                       target="_blank">
                                                                        <i class="fas fa-undo text-warning me-2"></i>
                                                                        PRINT INV Retur - {{ $invoiceNumber }}
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        @endif
                                                    </ul>
                                                </div>
                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="11" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <h6 class="fw-normal mb-1">Data tidak ditemukan</h6>
                                                        <p class="text-muted small">Tidak ada barang penjualan offline yang sesuai dengan filter</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($groupedItems->hasPages())
                                <div class="card-footer bg-white border-top">
                                    <div class="row align-items-center">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <div class="small text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Menampilkan <strong>{{ $groupedItems->firstItem() }}</strong> - <strong>{{ $groupedItems->lastItem() }}</strong> dari <strong>{{ $groupedItems->total() }}</strong> data
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-md-end justify-content-center">
                                                {{ $groupedItems->appends(request()->query())->links('pagination.clean') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="card-footer bg-white border-top">
                                    <div class="small text-muted text-center">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Menampilkan <strong>{{ $groupedItems->count() }}</strong> dari <strong>{{ $groupedItems->total() }}</strong> data
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .table-responsive {
        overflow-x: auto !important;
    }

    .table td:last-child {
        position: relative;
        overflow: visible;
    }

    .table td .dropdown {
        position: static;
    }

    .table td .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        transform: none !important;
        will-change: auto !important;
    }

    .border-top.border-primary {
        border-top-width: 2px !important;
    }

    .product-name {
        word-wrap: break-word;
        word-break: break-word;
        max-width: 100%;
        display: block;
    }

    .card-footer {
        padding: 1rem 1.5rem;
    }

    @media (max-width: 992px) {
        .product-name {
            max-width: 250px;
        }
    }

    @media (max-width: 768px) {
        .product-name {
            max-width: 150px;
        }

        .card-footer {
            padding: 0.75rem 1rem;
        }

        .card-footer .row > div {
            text-align: center !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips if they exist
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (tooltipTriggerList.length > 0) {
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Simple dropdown handling without complex positioning
        document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close all other dropdowns first
                document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                    if (menu !== toggle.nextElementSibling) {
                        menu.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                const menu = toggle.nextElementSibling;
                if (menu && menu.classList.contains('dropdown-menu')) {
                    menu.classList.toggle('show');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-menu.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
        });
        
        // Only initialize filter collapse if the element exists
        const filterCollapse = document.getElementById('filterCollapse');
        if (filterCollapse) {
            const bsCollapse = new bootstrap.Collapse(filterCollapse, {
                toggle: localStorage.getItem('financeOfflineFilterShown') !== 'false'
            });
            
            filterCollapse.addEventListener('hidden.bs.collapse', function () {
                localStorage.setItem('financeOfflineFilterShown', 'false');
            });
            
            filterCollapse.addEventListener('shown.bs.collapse', function () {
                localStorage.setItem('financeOfflineFilterShown', 'true');
            });
        }
        
        // Date shortcuts - only if elements exist
        const dateButtons = document.querySelectorAll('.btn-date');
        if (dateButtons.length > 0) {
            dateButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const days = parseInt(this.getAttribute('data-days'));
                    const endDate = new Date();
                    const startDate = new Date();
                    startDate.setDate(startDate.getDate() - days);
                    
                    const dateEndEl = document.getElementById('date_end');
                    const dateStartEl = document.getElementById('date_start');
                    
                    if (dateEndEl) dateEndEl.value = formatDate(endDate);
                    if (dateStartEl) dateStartEl.value = formatDate(startDate);
                });
            });
        }
        
        // Month shortcuts - only if elements exist
        const monthButtons = document.querySelectorAll('.btn-month');
        if (monthButtons.length > 0) {
            monthButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const monthType = this.getAttribute('data-month');
                    const today = new Date();
                    
                    if (monthType === 'current') {
                        const startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                        const dateStartEl = document.getElementById('date_start');
                        const dateEndEl = document.getElementById('date_end');
                        
                        if (dateStartEl) dateStartEl.value = formatDate(startDate);
                        if (dateEndEl) dateEndEl.value = formatDate(today);
                    }
                });
            });
        }
        
        // Helper to format date as YYYY-MM-DD
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        // Form enhancement: Auto-submit on date change (optional)
        const dateInputs = document.querySelectorAll('#date_start, #date_end');
        dateInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Optional: You can add auto-submit functionality here
                // this.closest('form').submit();
            });
        });
        
        // Form validation
        const filterForm = document.querySelector('form[action*="finance.offline.index"]');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                const dateStart = document.getElementById('date_start');
                const dateEnd = document.getElementById('date_end');
                
                if (dateStart && dateEnd && dateStart.value && dateEnd.value) {
                    if (new Date(dateStart.value) > new Date(dateEnd.value)) {
                        e.preventDefault();
                        alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir');
                        return false;
                    }
                }
            });
        }
    });
</script>
@endpush