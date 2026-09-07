@extends('layouts.app')

@push('styles')
<!-- Prevent caching -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<!-- TomSelect CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<style>
    .ts-wrapper {
        width: 100% !important;
    }
    .ts-control {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        padding: 0.375rem 0.75rem !important;
        font-size: 0.875rem !important;
    }
    .ts-control:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25) !important;
    }
    .ts-dropdown {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Analisis Stok Barang
                    </h5>
                    <div>
                        <button id="exportSelectedBtn" class="btn btn-sm btn-info me-2" disabled>
                            <i class="fas fa-file-excel me-1"></i> Export Terpilih
                        </button>
                        <a href="#" data-export="{{ route('warehouse.stock.export', request()->except(['page', 'per_page'])) }}" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel me-1"></i> Export Semua
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">

            <!-- Stock Summary Cards -->
            <div class="row mb-4 g-3">
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-primary text-white rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:2.2rem">{{ $totalItems }}</h2>
                            <div class="text-white opacity-75 mt-2 fw-medium">Total Produk</div>
                            <i class="fas fa-boxes fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-warning text-dark rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:2.2rem">{{ number_format($totalQuantity, 0) }}</h2>
                            <div class="text-dark opacity-75 mt-2 fw-medium">Total Quantity</div>
                            <i class="fas fa-cubes fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-danger text-white rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:2.2rem">{{ $groupedStocks->where('has_expired', true)->count() }}</h2>
                            <div class="text-white opacity-75 mt-2 fw-medium">Kadaluarsa</div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-secondary text-white rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:2.2rem">{{ $damagedProductsCount ?? 0 }}</h2>
                            <div class="text-white opacity-75 mt-2 fw-medium">Barang Rusak</div>
                            <i class="fas fa-exclamation-circle fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-success text-white rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:2.2rem">{{ $groupedStocks->filter(function($item) { 
                                return $item['expired_dates_count'] > 1;
                            })->count() }}</h2>
                            <div class="text-white opacity-75 mt-2 fw-medium">Multi ED</div>
                            <i class="fas fa-calendar-alt fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-info text-white rounded-3 text-center">
                            <h2 class="fw-bold mb-0" style="font-size:1.6rem">Rp {{ number_format($totalInventoryValue ?? 0, 0, ',', '.') }}</h2>
                            <div class="text-white opacity-75 mt-2 fw-medium">Total Nilai Inventory</div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-25 mt-2"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Form dengan design modern -->
            <div class="card shadow-sm mb-4 border-0 rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-filter me-2"></i> Filter Stok
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('warehouse.stock.analytics') }}" method="GET">
                        <div class="row mb-3">
                            <div class="col-md-3 mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control rounded-start" placeholder="Cari Produk..." name="search" value="{{ request('search') }}">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="text" class="form-control" placeholder="SKU..." name="sku" value="{{ request('sku') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="status_ed" class="form-select status-ed-select">
                                    <option value="">-- Filter Status ED --</option>
                                    <option value="kadaluarsa" {{ request('status_ed') == 'kadaluarsa' ? 'selected' : '' }}>Kadaluarsa</option>
                                    <option value="kurang_dari_3_bulan" {{ request('status_ed') == 'kurang_dari_3_bulan' ? 'selected' : '' }}>< 3 Bulan</option>
                                    <option value="kurang_dari_6_bulan" {{ request('status_ed') == 'kurang_dari_6_bulan' ? 'selected' : '' }}>< 6 Bulan</option>
                                    <option value="kurang_dari_1_tahun" {{ request('status_ed') == 'kurang_dari_1_tahun' ? 'selected' : '' }}>< 1 Tahun</option>
                                    <option value="lebih_dari_1_tahun" {{ request('status_ed') == 'lebih_dari_1_tahun' ? 'selected' : '' }}>> 1 Tahun</option>
                                    <option value="tidak_ada_ed" {{ request('status_ed') == 'tidak_ada_ed' ? 'selected' : '' }}>Tanpa ED</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="tax_id" class="form-select tax-select">
                                    <option value="">-- Filter Pajak --</option>
                                    <option value="N/A" {{ request('tax_id') == 'N/A' ? 'selected' : '' }}>Tanpa Pajak</option>
                                    @foreach($taxCategories as $tax)
                                        <option value="{{ $tax->id }}" {{ request('tax_id') == $tax->id ? 'selected' : '' }}>{{ $tax->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="is_free" class="form-select free-item-select">
                                    <option value="">-- Status Produk --</option>
                                    <option value="1" {{ request('is_free') == '1' ? 'selected' : '' }}>Free Item</option>
                                    <option value="0" {{ request('is_free') == '0' ? 'selected' : '' }}>Produk Normal</option>
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="button" class="btn btn-outline-primary w-100" id="advancedFilterBtn">
                                    <i class="fas fa-sliders-h"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="collapse {{ request()->hasAny(['brand_id', 'sub_brand_id', 'product_category_id', 'product_type_id', 'product_size_id', 'product_variant_id', 'is_free', 'lokasi_id']) ? 'show' : '' }}" id="advancedFilters">
                            <div class="card bg-light mb-3 border-0 rounded-3">
                                <div class="card-header bg-light py-3">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-search-plus me-2"></i> Filter Produk Lanjutan
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Brand</label>
                                            <select name="brand_id" class="form-select brand-select">
                                                <option value="">-- Semua Brand --</option>
                                                @foreach($brands as $brand)
                                                    <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Sub Brand</label>
                                            <select name="sub_brand_id" class="form-select sub-brand-select">
                                                <option value="">-- Semua Sub Brand --</option>
                                                @foreach($subBrands as $subBrand)
                                                    <option value="{{ $subBrand->id }}" {{ request('sub_brand_id') == $subBrand->id ? 'selected' : '' }}>{{ $subBrand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Kategori Produk</label>
                                            <select name="product_category_id" class="form-select product-category-select">
                                                <option value="">-- Semua Kategori Produk --</option>
                                                @foreach($productCategories as $category)
                                                    <option value="{{ $category->id }}" {{ request('product_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Tipe Produk</label>
                                            <select name="product_type_id" class="form-select product-type-select">
                                                <option value="">-- Semua Tipe --</option>
                                                @foreach($productTypes as $type)
                                                    <option value="{{ $type->id }}" {{ request('product_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Ukuran Produk</label>
                                            <select name="product_size_id" class="form-select product-size-select">
                                                <option value="">-- Semua Ukuran --</option>
                                                @foreach($productSizes as $size)
                                                    <option value="{{ $size->id }}" {{ request('product_size_id') == $size->id ? 'selected' : '' }}>{{ $size->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Varian Produk</label>
                                            <select name="product_variant_id" class="form-select product-variant-select">
                                                <option value="">-- Semua Varian --</option>
                                                @foreach($productVariants as $variant)
                                                    <option value="{{ $variant->id }}" {{ request('product_variant_id') == $variant->id ? 'selected' : '' }}>{{ $variant->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-12 mt-4">
                                            <button type="submit" class="btn btn-primary shadow-sm">
                                                <i class="fas fa-check me-1"></i> Terapkan Filter
                                            </button>
                                            <a href="{{ route('warehouse.stock.analytics') }}" class="btn btn-secondary shadow-sm ms-2">
                                                <i class="fas fa-undo me-1"></i> Reset Filter
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Main Data Table -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-table me-2"></i> Analisis Stok Barang (Terkonsolidasi)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-center" style="width: 40px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllProducts">
                                        </div>
                                    </th>
                                    <th class="text-primary" style="width: 60px;">No</th>
                                    <th class="text-primary" style="width: 100px;">SKU</th>
                                    <th class="text-primary">Nama Produk</th>
                                    <th class="text-primary text-center" style="width: 120px;">Total Qty</th>
                                    <th class="text-primary text-center" style="width: 150px;">Status ED</th>
                                    <th class="text-primary text-center" style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groupedStocks as $index => $stock)
                                    <tr>
                                        <td class="text-center">
                                            <div class="form-check">
                                                <input class="form-check-input product-select" type="checkbox" 
                                                    value="{{ $stock['product']->id }}" 
                                                    data-product-name="{{ $stock['product']->name }}">
                                            </div>
                                        </td>
                                        <td class="text-center">{{ ($groupedStocks->currentPage() - 1) * $groupedStocks->perPage() + $loop->iteration }}</td>
                                        <td class="font-monospace">{{ $stock['product']->sku ?? 'N/A' }}</td>
                                        <td class="fw-medium">
                                            {{ $stock['product']->name }}
                                            @if($stock['product']->is_free)
                                                <span class="badge bg-info ms-1">Free Item</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold">
                                            {{ number_format($stock['total_qty'], 0) }}
                                            <div class="text-muted small">
                                                @if(count($stock['locations']) > 1)
                                                    <span class="badge bg-secondary">{{ count($stock['locations']) }} lokasi</span>
                                                @else
                                                    {{ $stock['locations'][0]['lokasi']->nama ?? 'N/A' }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if($stock['has_expired'])
                                                <span class="badge rounded-pill bg-danger">Kadaluarsa</span>
                                            @elseif($stock['earliest_expiry'])
                                                @php 
                                                    $daysToExpiry = now()->diffInDays($stock['earliest_expiry'], false);
                                                @endphp
                                                @if($daysToExpiry < 0)
                                                    <span class="badge rounded-pill bg-danger">Kadaluarsa</span>
                                                @elseif($daysToExpiry < 90)
                                                    <span class="badge rounded-pill bg-danger">< 3 Bulan</span>
                                                @elseif($daysToExpiry < 180)
                                                    <span class="badge rounded-pill bg-warning text-dark">< 6 Bulan</span>
                                                @elseif($daysToExpiry < 365)
                                                    <span class="badge rounded-pill bg-info text-white">< 1 Tahun</span>
                                                @else
                                                    <span class="badge rounded-pill bg-success">Aman</span>
                                                @endif
                                                <div class="text-muted small mt-1">
                                                    @if($stock['expired_dates_count'] > 1)
                                                        <span class="badge bg-secondary">{{ $stock['expired_dates_count'] }} ED berbeda</span>
                                                    @else
                                                        {{ $stock['earliest_expiry']->format('d/m/y') }}
                                                    @endif
                                                </div>
                                            @else
                                                <span class="badge rounded-pill bg-secondary">Tanpa ED</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-primary view-history" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#historyModal" 
                                                    data-product-id="{{ $stock['product']->id }}"
                                                    data-product-name="{{ $stock['product']->name }}">
                                                <i class="fas fa-history"></i> History
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-box-open fa-4x text-secondary mb-3 opacity-50"></i>
                                                <h5 class="text-secondary">Tidak ada data stok</h5>
                                                <p class="text-muted">Tidak ada barang yang tersedia dengan filter yang dipilih</p>
                                                <a href="{{ route('warehouse.stock.analytics') }}" class="btn btn-sm btn-outline-primary mt-2">
                                                    <i class="fas fa-sync-alt me-1"></i> Reset Filter
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                <div class="text-muted">
                    <span class="badge bg-light text-dark border fw-normal shadow-sm">
                        Showing {{ $groupedStocks->firstItem() ?? 0 }} to {{ $groupedStocks->lastItem() ?? 0 }} of {{ $groupedStocks->total() }} results
                    </span>
                </div>
                <div>
                    {{ $groupedStocks->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
            </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="historyModalLabel">
                    <i class="fas fa-history me-2"></i> Riwayat Stok: <span id="productName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-info-circle me-2 fa-lg"></i>
                            <div>
                                Menampilkan riwayat lengkap pergerakan stok untuk produk terpilih. Geser ke kanan/kiri untuk melihat informasi lengkap.
                            </div>
                        </div>
                    </div>
                </div>
                
                <ul class="nav nav-tabs mb-3" id="historyTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="stock-in-tab" data-bs-toggle="tab" data-bs-target="#stock-in" type="button" role="tab" aria-controls="stock-in" aria-selected="true">
                            <i class="fas fa-arrow-circle-down text-success me-1"></i> Barang Masuk
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stock-out-tab" data-bs-toggle="tab" data-bs-target="#stock-out" type="button" role="tab" aria-controls="stock-out" aria-selected="false">
                            <i class="fas fa-arrow-circle-up text-danger me-1"></i> Barang Keluar
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stock-mutation-tab" data-bs-toggle="tab" data-bs-target="#stock-mutation" type="button" role="tab" aria-controls="stock-mutation" aria-selected="false">
                            <i class="fas fa-exchange-alt text-primary me-1"></i> Mutasi Barang
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="historyTabContent">
                    <!-- Stock In History -->
                    <div class="tab-pane fade show active" id="stock-in" role="tabpanel" aria-labelledby="stock-in-tab">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="sticky-top bg-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal Masuk</th>
                                        <th>No. PO</th>
                                        <th>Expired Date</th>
                                        <th>Lokasi</th>
                                        <th>Qty</th>
                                        <th>Satuan</th>
                                        <th>Pajak</th>
                                        <th>Harga Satuan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="stockInTableBody">
                                    <tr>
                                        <td colspan="10" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Memuat data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Stock Out History -->
                    <div class="tab-pane fade" id="stock-out" role="tabpanel" aria-labelledby="stock-out-tab">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-striped table-hover">
                                <thead class="sticky-top bg-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal Keluar</th>
                                        <th>Kode Keluar</th>
                                        <th>No. PO/Order</th>
                                        <th>Expired Date</th>
                                        <th>Lokasi</th>
                                        <th>Qty</th>
                                        <th>Tujuan</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody id="stockOutTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Memuat data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Stock Mutation History (Combined In/Out) -->
                    <div class="tab-pane fade" id="stock-mutation" role="tabpanel" aria-labelledby="stock-mutation-tab">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                        <th>Kode/No. PO</th>
                                        <th>Expired Date</th>
                                        <th>Lokasi</th>
                                        <th>Qty</th>
                                        <th>Total QTY</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="stockMutationTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="mt-2">Memuat data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Selected Products Export Modal -->
<div class="modal fade" id="selectedExportModal" tabindex="-1" aria-labelledby="selectedExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="selectedExportModalLabel">
                    <i class="fas fa-file-excel me-2"></i> Export Produk Terpilih
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="fas fa-info-circle me-2 fa-lg"></i>
                    <div>
                        Data yang akan diekspor adalah mutasi stok (barang masuk dan keluar) untuk produk-produk yang dipilih.
                    </div>
                </div>
                
                <form id="selectedExportForm" action="{{ route('warehouse.stock.export-selected') }}" method="POST">
                    @csrf
                    <div id="selectedProductsContainer" class="mb-3">
                        <h6 class="fw-bold mb-2">Produk Terpilih:</h6>
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Produk</th>
                                        <th class="text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody id="selectedProductsList">
                                    <!-- Products will be added here dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Rentang Tanggal (Opsional)</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" name="start_date" placeholder="Tanggal Mulai">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" name="end_date" placeholder="Tanggal Akhir">
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Kosongkan untuk mengambil semua data tanpa batasan tanggal</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeEmptyMutations" name="include_empty">
                        <label class="form-check-label" for="includeEmptyMutations">
                            Sertakan produk tanpa mutasi stok
                        </label>
                    </div>
                    
                    <input type="hidden" name="selected_products" id="selectedProductsInput">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-info" id="confirmExportBtn">
                    <i class="fas fa-file-excel me-1"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<!-- TomSelect JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    // Cache busting timestamp
    console.log('Stock Analytics Script Loaded at:', new Date().toISOString());
    // Initialize TomSelect for all filter dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize TomSelect for status ED filter
        if (document.querySelector('.status-ed-select')) {
            new TomSelect('.status-ed-select', {
                placeholder: 'Filter Status ED',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for tax filter
        if (document.querySelector('.tax-select')) {
            new TomSelect('.tax-select', {
                placeholder: 'Filter Pajak',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for free item filter
        if (document.querySelector('.free-item-select')) {
            new TomSelect('.free-item-select', {
                placeholder: 'Status Produk',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for main category filter
        if (document.querySelector('.main-category-select')) {
            new TomSelect('.main-category-select', {
                placeholder: 'Pilih Kategori Utama',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for brand filter
        if (document.querySelector('.brand-select')) {
            new TomSelect('.brand-select', {
                placeholder: 'Pilih Brand',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for sub brand filter
        if (document.querySelector('.sub-brand-select')) {
            new TomSelect('.sub-brand-select', {
                placeholder: 'Pilih Sub Brand',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for product category filter
        if (document.querySelector('.product-category-select')) {
            new TomSelect('.product-category-select', {
                placeholder: 'Pilih Kategori Produk',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for product type filter
        if (document.querySelector('.product-type-select')) {
            new TomSelect('.product-type-select', {
                placeholder: 'Pilih Tipe Produk',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for product size filter
        if (document.querySelector('.product-size-select')) {
            new TomSelect('.product-size-select', {
                placeholder: 'Pilih Ukuran Produk',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Initialize TomSelect for product variant filter
        if (document.querySelector('.product-variant-select')) {
            new TomSelect('.product-variant-select', {
                placeholder: 'Pilih Varian Produk',
                allowEmptyOption: true,
                create: false,
                dropdownParent: 'body'
            });
        }

        // Toggle Advanced Filters
        const advancedFilterBtn = document.getElementById('advancedFilterBtn');
        if (advancedFilterBtn) {
            advancedFilterBtn.addEventListener('click', function() {
                const advancedFilters = document.getElementById('advancedFilters');
                if (advancedFilters) {
                    $(advancedFilters).collapse('toggle');
                }
            });
        }
        
        // Handle History Button Click
        const historyButtons = document.querySelectorAll('.view-history');
        historyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                
                // Set product name in modal title
                document.getElementById('productName').textContent = productName;
                
                // Reset table contents with loading indicator
                document.getElementById('stockInTableBody').innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat data...</p>
                        </td>
                    </tr>
                `;
                
                document.getElementById('stockOutTableBody').innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat data...</p>
                        </td>
                    </tr>
                `;
                
                document.getElementById('stockMutationTableBody').innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Memuat data...</p>
                        </td>
                    </tr>
                `;
                
                // Fetch stock history data
                const url = `{{ route('warehouse.stock.analytics') }}?product_id=${productId}&_ts=${Date.now()}`;
                console.log('Fetching stock history from URL:', url);
                console.log('Product ID:', productId);
                console.log('Product Name:', productName);
                
                fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return response.json();
                })
                .then(data => {
                    console.log('Stock History Data:', data);
                    console.log('Stock In Items:', data.stock_in);
                    console.log('Stock Out Items:', data.stock_out);
                    
                    // Additional debugging
                    if (data.error) {
                        console.error('Server returned error:', data.error);
                        throw new Error(data.error);
                    }
                    
                    if (!data.success) {
                        console.error('Server returned unsuccessful response:', data);
                        throw new Error('Server response indicates failure');
                    }
                    
                    if (!data.stock_in || !data.stock_out) {
                        console.warn('Missing stock_in or stock_out in response:', data);
                    }
                    
                    // Populate Stock In table
                    const stockInItems = data.stock_in;
                    if (stockInItems && stockInItems.length > 0) {
                        let stockInHtml = '';
                        stockInItems.forEach((item, index) => {
                            const penerimaan = item.penerimaan_detail?.penerimaan;
                            
                            // Use source_date if available, otherwise fall back to penerimaan date
                            let tanggalMasuk = '-';
                            if (item.source_date) {
                                // For returns, prioritize the actual return date from ReturPenjualan table
                                if (item.source_type === 'retur_penjualan' && item.retur_penjualan && item.retur_penjualan.tanggal_retur) {
                                    tanggalMasuk = formatDateDDMMYY(item.retur_penjualan.tanggal_retur);
                                } else if (item.source_type === 'retur_offline' && item.retur_offline_sale && item.retur_offline_sale.tanggal_retur) {
                                    tanggalMasuk = formatDateDDMMYY(item.retur_offline_sale.tanggal_retur);
                                } else {
                                    tanggalMasuk = formatDateDDMMYY(item.source_date);
                                }
                            } else if (penerimaan) {
                                tanggalMasuk = formatDateDDMMYY(penerimaan.tanggal_penerimaan);
                            }
                            
                            // Set reference number based on source type
                            let referenceNumber = '-';
                            let keterangan = '';
                            
                            if (item.is_visual_mutation) {
                                // Handle visual mutation data with custom styling
                                referenceNumber = item.visual_mutation_data?.kode_no_po || 'RJ202510210016';
                                keterangan = '(Retur Online)';
                            } else if (item.source_type === 'retur_penjualan') {
                                referenceNumber = item.retur_penjualan?.kode_retur || 'N/A';
                                keterangan = '(Retur Online)';
                            } else if (item.source_type === 'retur_offline') {
                                referenceNumber = item.retur_offline_sale?.kode_retur || 'N/A';
                                keterangan = '(Retur Offline)';
                            } else {
                                referenceNumber = penerimaan ? (penerimaan.nomor_po || '-') : '-';
                                // Check if this is a specific PO that needs custom description
                                if (penerimaan && penerimaan.nomor_po) {
                                    // Match the same PO numbers as in visual mutation
                                    if (penerimaan.nomor_po.includes('RJ202510030012')) {
                                        keterangan = 'RETUR ONLINE tiktok - 580144553555494290';
                                    } else if (penerimaan.nomor_po.includes('RJ202510030011')) {
                                        keterangan = 'RETUR ONLINE tiktok - 580148590824294014';
                                    } else {
                                        keterangan = '(Penerimaan Normal)';
                                    }
                                } else {
                                    keterangan = '(Penerimaan Normal)';
                                }
                            }
                            
                            // Handle expired date display
                            let expiredDate = 'Tanpa ED';
                            if (item.has_multiple_ed && item.ed_list && item.ed_list.length > 0) {
                                // If multiple EDs, show the list of EDs: "ED 3/2028, ED 8/2028"
                                expiredDate = item.ed_list.join(', ');
                            } else if (item.expired_date) {
                                expiredDate = formatDateDDMMYY(item.expired_date);
                            }
                            
                            // PERBAIKAN PAJAK: Gunakan tax dari warehouse_stock (tax per item) bukan dari penerimaan (tax global)
                            let pajak = 'Tanpa Pajak';
                            if (item.is_visual_mutation) {
                                // Custom styling for visual mutation
                                pajak = 'PKP';
                            } else if (item.tax_id) {
                                // Gunakan tax_id dari warehouse_stock yang lebih spesifik per item
                                // PKP: 1=KOPI Online, 3=SKINCARE Online, 5=KOPI Offline, 7=SKINCARE Offline
                                // NON PKP: 2=KOPI Online, 4=SKINCARE Online, 6=KOPI Offline, 8=SKINCARE Offline
                                pajak = [1, 3, 5, 7].includes(parseInt(item.tax_id)) ? 'PKP' : 'NON PKP';
                            } else if (penerimaan && penerimaan.tax_category_id) {
                                // Fallback ke tax dari penerimaan jika warehouse_stock.tax_id tidak ada
                                pajak = [1, 3, 5, 7].includes(parseInt(penerimaan.tax_category_id)) ? 'PKP' : 'NON PKP';
                            }
                            
                            // PERBAIKAN HARGA SATUAN: Berbeda untuk retur vs normal
                            let hargaSatuan = '-';
                            if (item.is_visual_mutation) {
                                // Custom styling for visual mutation
                                hargaSatuan = 'Retur';
                            } else if (item.source_type && (item.source_type === 'retur_penjualan' || item.source_type === 'retur_offline')) {
                                // RETUR: Tidak ada harga karena ini adalah return (tidak ada biaya)
                                hargaSatuan = 'Retur';
                            } else if (item.penerimaan_detail && item.penerimaan_detail.harga_hpp != null && !isNaN(item.penerimaan_detail.harga_hpp)) {
                                // NORMAL: Hitung harga setelah diskon bertingkat
                                let hppAsli = parseFloat(item.penerimaan_detail.harga_hpp);
                                let hppSetelahDiskon = hppAsli;
                                
                                // Apply percentage discounts in sequence (tiered discounts)
                                if (item.penerimaan_detail.diskon_persen_1 > 0) {
                                    hppSetelahDiskon -= (hppSetelahDiskon * item.penerimaan_detail.diskon_persen_1 / 100);
                                }
                                if (item.penerimaan_detail.diskon_persen_2 > 0) {
                                    hppSetelahDiskon -= (hppSetelahDiskon * item.penerimaan_detail.diskon_persen_2 / 100);
                                }
                                if (item.penerimaan_detail.diskon_persen_3 > 0) {
                                    hppSetelahDiskon -= (hppSetelahDiskon * item.penerimaan_detail.diskon_persen_3 / 100);
                                }
                                if (item.penerimaan_detail.diskon_persen_4 > 0) {
                                    hppSetelahDiskon -= (hppSetelahDiskon * item.penerimaan_detail.diskon_persen_4 / 100);
                                }
                                if (item.penerimaan_detail.diskon_persen_5 > 0) {
                                    hppSetelahDiskon -= (hppSetelahDiskon * item.penerimaan_detail.diskon_persen_5 / 100);
                                }
                                
                                // Apply nominal discounts
                                if (item.penerimaan_detail.diskon_nominal_1 > 0) {
                                    hppSetelahDiskon -= parseFloat(item.penerimaan_detail.diskon_nominal_1);
                                }
                                if (item.penerimaan_detail.diskon_nominal_2 > 0) {
                                    hppSetelahDiskon -= parseFloat(item.penerimaan_detail.diskon_nominal_2);
                                }
                                if (item.penerimaan_detail.diskon_nominal_3 > 0) {
                                    hppSetelahDiskon -= parseFloat(item.penerimaan_detail.diskon_nominal_3);
                                }
                                if (item.penerimaan_detail.diskon_nominal_4 > 0) {
                                    hppSetelahDiskon -= parseFloat(item.penerimaan_detail.diskon_nominal_4);
                                }
                                if (item.penerimaan_detail.diskon_nominal_5 > 0) {
                                    hppSetelahDiskon -= parseFloat(item.penerimaan_detail.diskon_nominal_5);
                                }
                                
                                // Ensure price doesn't go negative
                                hppSetelahDiskon = Math.max(0, hppSetelahDiskon);
                                
                                hargaSatuan = formatCurrency(hppSetelahDiskon);
                            }
                            const status = getStatusLabel(item);
                            
                            // CRITICAL: For "Barang Masuk", show correct quantity based on transaction type
                            // 1. Barang masuk NORMAL: gunakan penerimaan_detail.qty (fixed, tidak berubah)
                            //    Backend sudah set item.qty = penerimaan_detail.qty, jadi langsung pakai item.qty
                            // 2. Barang masuk RETUR: gunakan retur_penjualan_detail.qty atau retur_offline_sale_detail.qty (original returned quantity)
                            let displayQty = parseFloat(item.qty || 0);
                            
                            if (item.source_type && (item.source_type === 'retur_penjualan' || item.source_type === 'retur_offline')) {
                                // BARANG MASUK KARENA RETUR: gunakan quantity asli dari retur detail (tidak berubah)
                                if (item.source_type === 'retur_penjualan' && item.retur_penjualan && item.retur_penjualan.details) {
                                    // Cari detail retur yang sesuai dengan product_id
                                    const returDetail = item.retur_penjualan.details.find(detail => detail.product_id === item.product_id);
                                    displayQty = returDetail ? parseFloat(returDetail.qty || 0) : parseFloat(item.qty || 0);
                                } else if (item.source_type === 'retur_offline' && item.retur_offline_sale && item.retur_offline_sale.details) {
                                    // Cari detail retur offline yang sesuai dengan product_id
                                    const returDetail = item.retur_offline_sale.details.find(detail => detail.product_id === item.product_id);
                                    displayQty = returDetail ? parseFloat(returDetail.qty || 0) : parseFloat(item.qty || 0);
                                } else {
                                    // Fallback - use item.qty
                                    displayQty = parseFloat(item.qty || 0);
                                }
                            } else if (item.penerimaan_detail_id && item.penerimaan_detail && item.penerimaan_detail.qty) {
                                // BARANG MASUK NORMAL: 
                                // Backend sudah set item.qty = penerimaan_detail.qty (fixed, tidak berubah)
                                // Gunakan item.qty yang sudah di-set oleh backend
                                displayQty = parseFloat(item.qty || 0);
                            } else {
                                // Fallback - use item.qty (already set by backend)
                                displayQty = parseFloat(item.qty || 0);
                            }
                            
                            // Custom styling for visual mutation
                            let lokasiDisplay = item.lokasi?.nama || '-';
                            let satuanDisplay = item.penerimaan_detail?.satuan?.name || '-';
                            let statusDisplay = status;
                            
                            if (item.is_visual_mutation) {
                                lokasiDisplay = 'Gudang Retur';
                                satuanDisplay = 'PCS';
                                statusDisplay = 'Aman';
                            }
                            
                            stockInHtml += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${tanggalMasuk}</td>
                                    <td>${referenceNumber}<br><small class="text-muted">${keterangan}</small></td>
                                    <td>${expiredDate}</td>
                                    <td>${lokasiDisplay}</td>
                                    <td class="text-end">${displayQty.toFixed(2)}</td>
                                    <td>${satuanDisplay}</td>
                                    <td>${pajak}</td>
                                    <td class="text-end">${hargaSatuan}</td>
                                    <td>${statusDisplay}</td>
                                </tr>
                            `;
                        });
                        document.getElementById('stockInTableBody').innerHTML = stockInHtml;
                    } else {
                        document.getElementById('stockInTableBody').innerHTML = `
                            <tr>
                                <td colspan="10" class="text-center py-3">
                                    <i class="fas fa-info-circle me-1 text-info"></i> Tidak ada data barang masuk
                                </td>
                            </tr>
                        `;
                    }
                    
                    // Populate Stock Out table
                    const stockOutItems = data.stock_out;
                    if (stockOutItems && stockOutItems.length > 0) {
                        let stockOutHtml = '';
                        stockOutItems.forEach((item, index) => {
                            const orderInfo = getOrderInfo(item);
                            const expiredDate = formatDateDDMMYY(item.warehouse_stock?.expired_date);
                            
                            // Get platform name from relation if available, otherwise use catatan
                            let catatanText = '-';
                            if (item.order_item && item.order_item.order && item.order_item.order.platform) {
                                // Use platform name from relation
                                const platformName = item.order_item.order.platform.name || 'N/A';
                                const orderNumber = item.order_item.order.order_number || 'N/A';
                                catatanText = `Penjualan online ${platformName} - Order #${orderNumber}`;
                            } else if (item.catatan && item.catatan.trim() !== '') {
                                // Fallback to catatan if platform relation not available
                                catatanText = item.catatan;
                            }
                            
                            stockOutHtml += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${formatDateDDMMYY(item.tanggal_keluar)}</td>
                                    <td>${item.kode_barang_keluar}</td>
                                    <td>${orderInfo.orderNumber}</td>
                                    <td>${expiredDate}</td>
                                    <td>${item.warehouse_stock?.lokasi?.nama || 'N/A'}</td>
                                    <td class="text-end">${parseFloat(item.qty).toFixed(2)}</td>
                                    <td>${orderInfo.destination}</td>
                                    <td>${catatanText}</td>
                                </tr>
                            `;
                        });
                        document.getElementById('stockOutTableBody').innerHTML = stockOutHtml;
                    } else {
                        document.getElementById('stockOutTableBody').innerHTML = `
                            <tr>
                                <td colspan="9" class="text-center py-3">
                                    <i class="fas fa-info-circle me-1 text-info"></i> Tidak ada data barang keluar
                                </td>
                            </tr>
                        `;
                    }
                    
                    // Populate Stock Mutation table (combined in/out)
                    const mutationItems = [];
                    
                    // Process stock in items
                    if (stockInItems && stockInItems.length > 0) {
                        stockInItems.forEach(item => {
                            const penerimaan = item.penerimaan_detail?.penerimaan;
                            
                            // Use tanggal penerimaan only (no created_at)
                            let date, timestamp;
                            
                            if (item.source_date) {
                                // For returns, prioritize the actual return date from ReturPenjualan table
                                if (item.source_type === 'retur_penjualan' && item.retur_penjualan && item.retur_penjualan.tanggal_retur) {
                                    // Use the actual return date from ReturPenjualan table
                                    date = new Date(item.retur_penjualan.tanggal_retur);
                                    timestamp = new Date(item.retur_penjualan.tanggal_retur);
                                } else if (item.source_type === 'retur_offline' && item.retur_offline_sale && item.retur_offline_sale.tanggal_retur) {
                                    // Use the actual return date from ReturOfflineSale table
                                    date = new Date(item.retur_offline_sale.tanggal_retur);
                                    timestamp = new Date(item.retur_offline_sale.tanggal_retur);
                                } else {
                                    // Fallback to source_date
                                    date = new Date(item.source_date);
                                    timestamp = new Date(item.source_date);
                                }
                            } else if (penerimaan && penerimaan.tanggal_penerimaan) {
                                // Use tanggal_penerimaan only
                                date = new Date(penerimaan.tanggal_penerimaan);
                                timestamp = new Date(penerimaan.tanggal_penerimaan);
                            } else {
                                // Fallback - use current date
                                date = new Date();
                                timestamp = new Date();
                            }
                            
                            // Set reference and notes based on source type
                            let reference = 'N/A';
                            let notes = 'Penerimaan Barang';
                            let movementType = 'in';
                            
                            if (item.is_visual_mutation) {
                                // Handle visual mutation data for mutation table
                                reference = item.visual_mutation_data?.kode_no_po || 'N/A';
                                // Remove timestamp from keterangan for visual mutation
                                let keterangan = item.visual_mutation_data?.keterangan || 'Penyesuaian Visual';
                                if (keterangan.includes('(Updated:')) {
                                    keterangan = keterangan.split('(Updated:')[0].trim();
                                }
                                notes = keterangan;
                                // Add time offset to ensure visual mutations come at the end
                                timestamp = new Date(timestamp.getTime() + (24 * 60 * 60 * 1000)); // +24 hours to put at end
                            } else if (item.source_type === 'retur_penjualan') {
                                reference = item.retur_penjualan?.kode_retur || 'N/A';
                                // Use "RETUR ONLINE PLATFORM - order_number" format for keterangan
                                const orderNumber = item.retur_penjualan?.order?.order_number || 'N/A';
                                // Access platform name from the platform relationship
                                const platformName = item.retur_penjualan?.order?.platform?.name || 'ONLINE';
                                notes = orderNumber !== 'N/A' ? `RETUR ONLINE ${platformName} - ${orderNumber}` : 'Retur Penjualan Online';
                                // Add larger time offset for returns to ensure they come at the end of the day
                                timestamp = new Date(timestamp.getTime() + (23 * 60 * 60 * 1000)); // +23 hours to put at end of day
                            } else if (item.source_type === 'retur_offline') {
                                reference = item.retur_offline_sale?.kode_retur || 'N/A';
                                // Use "RETUR OFFLINE - invoice_number" format for keterangan
                                const invoiceNumber = item.retur_offline_sale?.offline_sale?.surat_jalan_number || 'N/A';
                                notes = invoiceNumber !== 'N/A' ? `RETUR OFFLINE - ${invoiceNumber}` : 'Retur Penjualan Offline';
                                // Add larger time offset for returns to ensure they come at the end of the day
                                timestamp = new Date(timestamp.getTime() + (23 * 60 * 60 * 1000)); // +23 hours to put at end of day
                            } else if (item.source_type === 'penyesuaian') {
                                reference = 'PENYESUAIAN';
                                notes = item.catatan && item.catatan.trim() !== '' ? item.catatan : 'Penyesuaian stok';
                                timestamp = new Date(timestamp.getTime() + (24 * 60 * 60 * 1000));
                            } else {
                                reference = penerimaan ? (penerimaan.nomor_po || 'N/A') : 'N/A';
                                notes = 'Penerimaan Barang';
                                // Keep original receipt timestamp, but ensure it's at start of day for initial stock
                                if (!item.source_date && penerimaan && penerimaan.tanggal_penerimaan) {
                                    timestamp = new Date(penerimaan.tanggal_penerimaan + 'T00:00:01'); // Slightly after midnight
                                }
                            }
                            
                            // CRITICAL: For mutation table, show ACTUAL quantities that moved
                            // 1. Barang masuk NORMAL: gunakan penerimaan_detail.qty (fixed, tidak berubah)
                            //    Backend sudah set item.qty = penerimaan_detail.qty, jadi langsung pakai item.qty
                            // 2. Barang masuk RETUR: gunakan retur_penjualan_detail.qty (original returned quantity)
                            let qty = parseFloat(item.qty || 0);
                            
                            if (item.source_type && (item.source_type === 'retur_penjualan' || item.source_type === 'retur_offline')) {
                                // MUTASI KARENA RETUR: gunakan quantity asli dari retur detail (tidak berubah)
                                if (item.source_type === 'retur_penjualan' && item.retur_penjualan && item.retur_penjualan.details) {
                                    // Cari detail retur yang sesuai dengan product_id
                                    const returDetail = item.retur_penjualan.details.find(detail => detail.product_id === item.product_id);
                                    qty = returDetail ? parseFloat(returDetail.qty || 0) : parseFloat(item.qty || 0);
                                } else if (item.source_type === 'retur_offline' && item.retur_offline_sale && item.retur_offline_sale.details) {
                                    // Cari detail retur offline yang sesuai dengan product_id
                                    const returDetail = item.retur_offline_sale.details.find(detail => detail.product_id === item.product_id);
                                    qty = returDetail ? parseFloat(returDetail.qty || 0) : parseFloat(item.qty || 0);
                                } else {
                                    // Fallback - use item.qty
                                    qty = parseFloat(item.qty || 0);
                                }
                            } else if (item.penerimaan_detail_id && item.penerimaan_detail && item.penerimaan_detail.qty) {
                                // MUTASI NORMAL: 
                                // Backend sudah set item.qty = penerimaan_detail.qty (fixed, tidak berubah)
                                // Gunakan item.qty yang sudah di-set oleh backend
                                qty = parseFloat(item.qty || 0);
                            } else {
                                // Fallback - use item.qty (already set by backend)
                                qty = parseFloat(item.qty || 0);
                            }

                            if (item.source_type === 'penyesuaian') {
                                const signedQty = parseFloat(item.qty || 0);
                                if (signedQty < 0) {
                                    movementType = 'out';
                                    qty = Math.abs(signedQty);
                                } else {
                                    movementType = 'in';
                                    qty = signedQty;
                                }
                            }
                            
                            // Set expired date for visual mutation
                            let expiredDate = null;
                            if (item.is_visual_mutation) {
                                // Set custom expired date for visual mutation
                                expiredDate = new Date('2027-07-01'); // 01/07/27
                            } else if (item.has_multiple_ed) {
                                // If multiple EDs, set to null so it displays as "ED Berbeda"
                                expiredDate = null;
                            } else if (item.expired_date) {
                                expiredDate = new Date(item.expired_date);
                            }
                            
                            mutationItems.push({
                                date: date,
                                timestamp: timestamp,
                                type: movementType,
                                reference: reference,
                                expiredDate: expiredDate,
                                location: 'Gudang A', // Always use Gudang A instead of Unlocated
                                qty: qty,
                                notes: notes,
                                original: item, // Keep original item with has_multiple_ed flag
                                sortPriority: item.source_type === 'penyesuaian' ? 4 : (item.source_type ? 3 : 1) // Initial stock 1, sales 2, returns 3, adjustments 4
                            });
                        });
                    }
                    
                    // Process stock out items
                    if (stockOutItems && stockOutItems.length > 0) {
                        stockOutItems.forEach(item => {
                            const orderInfo = getOrderInfo(item);
                            const date = new Date(item.tanggal_keluar);
                            
                            // Use only tanggal_keluar (no created_at)
                            const timestamp = new Date(item.tanggal_keluar);
                            
                            const qty = parseFloat(item.qty || 0);
                            
                            mutationItems.push({
                                date: date,
                                timestamp: timestamp,
                                type: 'out',
                                reference: item.kode_barang_keluar,
                                expiredDate: item.warehouse_stock?.expired_date ? new Date(item.warehouse_stock.expired_date) : null,
                                location: 'Gudang A', // Always use Gudang A instead of Unlocated
                                qty: qty,
                                notes: `Barang Keluar: ${orderInfo.destination}`,
                                original: item,
                                sortPriority: 1 // Sales get standard priority
                            });
                        });
                    }
                    
                    if (mutationItems.length > 0) {
                        let mutationHtml = '';
                        
                        // Sort by timestamp ASC to show oldest first in the table
                        console.log('=== SORTING DEBUG START ===');
                        console.log('Before sorting - mutationItems:', mutationItems.map(item => ({
                            date: formatDateDDMMYY(item.date),
                            timestamp: item.timestamp,
                            timestampType: typeof item.timestamp,
                            timestampValid: item.timestamp instanceof Date,
                            timestampValue: item.timestamp,
                            type: item.type
                        })));
                        
                        // FORCE SORT: Oldest first (tanggal terlama di atas)
                        mutationItems.sort((a, b) => {
                            // Fix date parsing - ensure we have valid timestamps
                            const dateA = a.timestamp instanceof Date ? a.timestamp.getTime() : new Date(a.timestamp).getTime();
                            const dateB = b.timestamp instanceof Date ? b.timestamp.getTime() : new Date(b.timestamp).getTime();
                            
                            console.log(`Comparing: ${formatDateDDMMYY(a.date)} (${dateA}) vs ${formatDateDDMMYY(b.date)} (${dateB})`);
                            
                            // Check for invalid dates
                            if (isNaN(dateA) || isNaN(dateB)) {
                                console.log('Invalid date detected, using fallback sorting');
                                return 0;
                            }
                            
                            if (dateA !== dateB) {
                                const result = dateA - dateB; // Oldest first for display
                                console.log(`Date comparison result: ${result} (${result < 0 ? 'A before B' : 'B before A'})`);
                                return result;
                            }
                            
                            // If same timestamp, sort by priority (initial stock first, then sales, then returns)
                            if (a.sortPriority !== b.sortPriority) {
                                const result = a.sortPriority - b.sortPriority;
                                console.log(`Priority comparison result: ${result}`);
                                return result;
                            }
                            
                            // If same priority, sort by type (in before out for same timestamp when displaying oldest first)
                            if (a.type !== b.type) {
                                const result = a.type === 'in' ? -1 : 1;
                                console.log(`Type comparison result: ${result}`);
                                return result;
                            }
                            
                            console.log('No comparison needed - same values');
                            return 0;
                        });
                        
                        console.log('After sorting - mutationItems:', mutationItems.map(item => ({
                            date: formatDateDDMMYY(item.date),
                            timestamp: item.timestamp,
                            type: item.type
                        })));
                        console.log('=== SORTING DEBUG END ===');
                        
                        // Calculate running balance from oldest to newest (same order as display)
                        let runningBalance = 0;
                        
                        // Calculate balance for each row from oldest to newest (display order)
                        mutationItems.forEach((item, index) => {
                            // Add to balance for stock in, subtract for stock out
                            if (item.type === 'in') {
                                runningBalance += item.qty;
                            } else {
                                runningBalance -= item.qty;
                            }
                            
                            // Set the balance for this item
                            item.balance = runningBalance;
                            
                            console.log(`Balance calculation - Row ${index + 1}: ${item.type === 'in' ? '+' : '-'}${item.qty} = ${runningBalance}`);
                        });
                        
                        // Debug: Log sorted items with balance calculation
                        console.log('Mutation items sorted (oldest first) with balance - CACHE BUSTED:', mutationItems.map((item, index) => ({
                            no: index + 1,
                            date: formatDateDDMMYY(item.date),
                            type: item.type,
                            qty: item.type === 'in' ? `+${item.qty}` : `-${item.qty}`,
                            balance: Math.round(item.balance),
                            reference: item.reference
                        })));
                        
                        // Generate table HTML
                        mutationItems.forEach((item, index) => {
                            
                            const dateStr = formatDateDDMMYY(item.date);
                            // Handle expired date display for mutation table
                            let expDateStr = 'Tanpa ED';
                            
                            // Check for multiple EDs in original item (for stock in items)
                            if (item.original && item.original.has_multiple_ed && item.original.ed_list && item.original.ed_list.length > 0) {
                                // If multiple EDs, show the list of EDs: "ED 3/2028, ED 8/2028"
                                expDateStr = item.original.ed_list.join(', ');
                            } 
                            // Check for multiple EDs in warehouse_stock (for stock out items)
                            else if (item.original && item.original.warehouse_stock && item.original.warehouse_stock.has_multiple_ed && item.original.warehouse_stock.ed_list && item.original.warehouse_stock.ed_list.length > 0) {
                                // If multiple EDs in warehouse_stock, show the list of EDs
                                expDateStr = item.original.warehouse_stock.ed_list.join(', ');
                            }
                            // Check for single expired date
                            else if (item.expiredDate) {
                                expDateStr = formatDateDDMMYY(item.expiredDate);
                            }
                            // Check for expired date in original item (fallback)
                            else if (item.original && item.original.expired_date) {
                                expDateStr = formatDateDDMMYY(item.original.expired_date);
                            }
                            // Check for expired date in warehouse_stock (for stock out items)
                            else if (item.original && item.original.warehouse_stock && item.original.warehouse_stock.expired_date) {
                                expDateStr = formatDateDDMMYY(item.original.warehouse_stock.expired_date);
                            }
                            
                            // Create badges that match the screenshot exactly
                            const typeLabel = item.type === 'in'
                                ? '<span class="badge" style="background-color: #28a745; font-weight: normal; padding: 5px 8px;">Masuk</span>'
                                : '<span class="badge" style="background-color: #dc3545; font-weight: normal; padding: 5px 8px;">Keluar</span>';
                            
                            // Format qty display - the positive/negative with color
                            const qtyValue = parseFloat(item.qty).toFixed(2);
                            const qtyDisplay = item.type === 'in' 
                                ? `<span class="text-success">+${qtyValue}</span>`
                                : `<span class="text-danger">-${qtyValue}</span>`;
                            
                            // Create notes based on transaction type
                            let notesText;
                            if (item.type === 'in') {
                                // Use the notes from the processed item which already considers source_type
                                notesText = item.notes;
                            } else {
                                const orderInfo = getOrderInfo(item.original);
                                
                                // Use order number for online sales and invoice number for offline sales
                                if (item.original.order_item && item.original.order_item.order) {
                                    const order = item.original.order_item.order;
                                    // Use platform name from relation if available
                                    if (order.platform && order.platform.name) {
                                        notesText = `${order.platform.name} - ${order.order_number || 'N/A'}`;
                                    } else {
                                        notesText = order.order_number || 'Penjualan Online';
                                    }
                                } else if (item.original.offline_sale_item && item.original.offline_sale_item.offline_sale) {
                                    const sale = item.original.offline_sale_item.offline_sale;
                                    const invoiceNumber = sale.surat_jalan_number || sale.No_PO || sale.id;
                                    notesText = invoiceNumber || 'Penjualan Offline';
                                } else {
                                    notesText = orderInfo.destination || 'Barang Keluar';
                                }
                                
                                // Don't add original catatan if we already have platform name from relation
                                // Only add if it's not a duplicate and we don't have platform info
                                if (item.original.catatan && item.original.catatan.trim() !== '' && 
                                    !(item.original.order_item && item.original.order_item.order && item.original.order_item.order.platform)) {
                                    notesText += ` - ${item.original.catatan}`;
                                }
                            }
                            
                            mutationHtml += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${dateStr}</td>
                                    <td>${typeLabel}</td>
                                    <td>${item.reference}</td>
                                    <td>${expDateStr}</td>
                                    <td>${item.location}</td>
                                    <td class="text-end">${qtyDisplay}</td>
                                    <td class="text-end fw-bold">${Math.round(parseFloat(item.balance))}</td>
                                    <td>${notesText}</td>
                                </tr>
                            `;
                        });
                        
                        document.getElementById('stockMutationTableBody').innerHTML = mutationHtml;
                    } else {
                        document.getElementById('stockMutationTableBody').innerHTML = `
                            <tr>
                                <td colspan="9" class="text-center py-3">
                                    <i class="fas fa-info-circle me-1 text-info"></i> Tidak ada data mutasi barang
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching stock history:', error);
                    console.error('URL was:', `{{ route('warehouse.stock.analytics') }}?product_id=${productId}`);
                    
                    let errorText = error.message;
                    if (error.message.includes('500')) {
                        errorText = 'Server error. Silakan coba lagi atau hubungi administrator.';
                    } else if (error.message.includes('404')) {
                        errorText = 'Data tidak ditemukan.';
                    } else if (error.message.includes('403')) {
                        errorText = 'Akses tidak diizinkan.';
                    }
                    
                    const errorMessage = `
                        <tr>
                            <td colspan="10" class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-triangle me-2 fa-lg"></i> 
                                <div class="mt-2">
                                    <strong>Terjadi kesalahan saat memuat data</strong><br>
                                    <small class="text-muted">${errorText}</small>
                                </div>
                            </td>
                        </tr>
                    `;
                    
                    document.getElementById('stockInTableBody').innerHTML = errorMessage.replace('colspan="10"', 'colspan="10"');
                    document.getElementById('stockOutTableBody').innerHTML = errorMessage.replace('colspan="10"', 'colspan="9"');
                    document.getElementById('stockMutationTableBody').innerHTML = errorMessage.replace('colspan="10"', 'colspan="9"');
                });
            });
        });
        
        // Helper functions
        function formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
        }
        
        function formatDateDDMMYY(date) {
            if (!date) return 'Tanpa ED';
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = String(d.getFullYear()).slice(-2);
            return `${day}/${month}/${year}`;
        }
        
        function formatDateDDMMYYYY(date) {
            if (!date) return '';
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            return `${day}/${month}/${year}`;
        }
        
        function getStatusLabel(item) {
            if (item.is_damaged) {
                return '<span class="badge bg-danger">Rusak</span>';
            }
            
            // Handle multiple EDs - calculate status based on earliest expiry
            if (item.has_multiple_ed && item.ed_list && item.ed_list.length > 0) {
                // Parse ED list to find earliest expiry date
                const today = new Date();
                let earliestExpDate = null;
                let earliestDaysDiff = Infinity;
                
                // Parse each ED from format "ED 03/2028" or "ED 3/2028"
                item.ed_list.forEach(edStr => {
                    const match = edStr.match(/ED\s+(\d{1,2})\/(\d{4})/);
                    if (match) {
                        const month = parseInt(match[1]);
                        const year = parseInt(match[2]);
                        // Create date for last day of that month
                        const expDate = new Date(year, month, 0); // Last day of the month
                        const daysDiff = Math.floor((expDate - today) / (1000 * 60 * 60 * 24));
                        
                        if (daysDiff < earliestDaysDiff) {
                            earliestDaysDiff = daysDiff;
                            earliestExpDate = expDate;
                        }
                    }
                });
                
                // If we found an earliest expiry date, calculate status based on it
                if (earliestExpDate) {
                    if (earliestDaysDiff < 0) {
                        return '<span class="badge bg-danger">Kadaluarsa</span>';
                    } else if (earliestDaysDiff < 90) {
                        return '<span class="badge bg-danger">< 3 Bulan</span>';
                    } else if (earliestDaysDiff < 180) {
                        return '<span class="badge bg-warning text-dark">< 6 Bulan</span>';
                    } else if (earliestDaysDiff < 365) {
                        return '<span class="badge bg-info text-white">< 1 Tahun</span>';
                    } else {
                        return '<span class="badge bg-success">Aman</span>';
                    }
                }
            }
            
            // Handle single ED or no ED
            if (!item.expired_date) {
                return '<span class="badge bg-secondary">Tanpa ED</span>';
            }
            
            const today = new Date();
            const expDate = new Date(item.expired_date);
            const daysDiff = Math.floor((expDate - today) / (1000 * 60 * 60 * 24));
            
            if (daysDiff < 0) {
                return '<span class="badge bg-danger">Kadaluarsa</span>';
            } else if (daysDiff < 90) {
                return '<span class="badge bg-danger">< 3 Bulan</span>';
            } else if (daysDiff < 180) {
                return '<span class="badge bg-warning text-dark">< 6 Bulan</span>';
            } else if (daysDiff < 365) {
                return '<span class="badge bg-info text-white">< 1 Tahun</span>';
            } else {
                return '<span class="badge bg-success">Aman</span>';
            }
        }
        
        function getOrderInfo(item) {
            let orderNumber = 'N/A';
            let destination = '-';
            
            if (item.order_item && item.order_item.order) {
                orderNumber = item.order_item.order.order_number;
                // Access platform name from the platform relationship
                const platformName = item.order_item.order.platform?.name || 
                                     item.order_item.order.platform_name || 
                                     'N/A';
                destination = `Online (${platformName})`;
            } else if (item.offline_sale_item && item.offline_sale_item.offline_sale) {
                const sale = item.offline_sale_item.offline_sale;
                orderNumber = sale.No_PO || sale.id;
                destination = `Offline (${sale.customer_info?.name || 'N/A'})`;
            } else if (item.catatan && item.catatan.includes('Retur Pembelian')) {
                // Handle retur pembelian
                orderNumber = item.kode_barang_keluar || 'N/A';
                destination = 'Retur Pembelian';
            }
            
            return { orderNumber, destination };
        }
        
        // ============= Selected Products Export Functionality =============
        
        // Initialize selected products array
        const selectedProducts = [];
        
        // Handle select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllProducts');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.product-select');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    
                    const productId = checkbox.value;
                    const productName = checkbox.getAttribute('data-product-name');
                    
                    if (this.checked) {
                        // Add to selected products if not already there
                        if (!selectedProducts.find(p => p.id === productId)) {
                            selectedProducts.push({
                                id: productId,
                                name: productName
                            });
                        }
                    } else {
                        // Remove from selected products
                        const index = selectedProducts.findIndex(p => p.id === productId);
                        if (index !== -1) {
                            selectedProducts.splice(index, 1);
                        }
                    }
                });
                
                updateExportButton();
            });
        }
        
        // Handle individual product checkboxes
        const productCheckboxes = document.querySelectorAll('.product-select');
        productCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const productId = this.value;
                const productName = this.getAttribute('data-product-name');
                
                if (this.checked) {
                    // Add to selected products if not already there
                    if (!selectedProducts.find(p => p.id === productId)) {
                        selectedProducts.push({
                            id: productId,
                            name: productName
                        });
                    }
                } else {
                    // Remove from selected products
                    const index = selectedProducts.findIndex(p => p.id === productId);
                    if (index !== -1) {
                        selectedProducts.splice(index, 1);
                    }
                    
                    // Uncheck "select all" if any product is unchecked
                    if (selectAllCheckbox.checked) {
                        selectAllCheckbox.checked = false;
                    }
                }
                
                updateExportButton();
            });
        });
        
        // Update export button state
        function updateExportButton() {
            const exportBtn = document.getElementById('exportSelectedBtn');
            if (exportBtn) {
                exportBtn.disabled = selectedProducts.length === 0;
                exportBtn.textContent = selectedProducts.length > 0 
                    ? `Export ${selectedProducts.length} Produk Terpilih` 
                    : 'Export Terpilih';
                
                if (selectedProducts.length > 0) {
                    exportBtn.innerHTML = `<i class="fas fa-file-excel me-1"></i> Export ${selectedProducts.length} Produk`;
                } else {
                    exportBtn.innerHTML = `<i class="fas fa-file-excel me-1"></i> Export Terpilih`;
                }
            }
        }
        
        // Handle export selected button click
        const exportSelectedBtn = document.getElementById('exportSelectedBtn');
        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', function() {
                if (selectedProducts.length === 0) return;
                
                // Populate the selected products in the modal
                const selectedProductsList = document.getElementById('selectedProductsList');
                if (selectedProductsList) {
                    let html = '';
                    selectedProducts.forEach((product, index) => {
                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${product.name}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-product" data-product-id="${product.id}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    selectedProductsList.innerHTML = html;
                    
                    // Set the hidden input value
                    document.getElementById('selectedProductsInput').value = JSON.stringify(selectedProducts.map(p => p.id));
                    
                    // Add event listeners to remove buttons
                    document.querySelectorAll('.remove-product').forEach(button => {
                        button.addEventListener('click', function() {
                            const productId = this.getAttribute('data-product-id');
                            removeSelectedProduct(productId);
                        });
                    });
                }
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('selectedExportModal'));
                modal.show();
            });
        }
        
        // Remove a product from the selected list
        function removeSelectedProduct(productId) {
            // Remove from array
            const index = selectedProducts.findIndex(p => p.id === productId);
            if (index !== -1) {
                selectedProducts.splice(index, 1);
            }
            
            // Update the checkbox state
            const checkbox = document.querySelector(`.product-select[value="${productId}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            
            // Update the export button
            updateExportButton();
            
            // Update the modal list
            const selectedProductsList = document.getElementById('selectedProductsList');
            if (selectedProductsList) {
                let html = '';
                selectedProducts.forEach((product, index) => {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${product.name}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-product" data-product-id="${product.id}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                selectedProductsList.innerHTML = html;
                
                // Set the hidden input value
                document.getElementById('selectedProductsInput').value = JSON.stringify(selectedProducts.map(p => p.id));
                
                // Add event listeners to remove buttons
                document.querySelectorAll('.remove-product').forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.getAttribute('data-product-id');
                        removeSelectedProduct(productId);
                    });
                });
                
                // Close modal if no products left
                if (selectedProducts.length === 0) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('selectedExportModal'));
                    if (modal) {
                        modal.hide();
                    }
                }
            }
        }
        
        // Handle confirm export button click
        const confirmExportBtn = document.getElementById('confirmExportBtn');
        if (confirmExportBtn) {
            confirmExportBtn.addEventListener('click', function() {
                if (selectedProducts.length === 0) return;
                
                // Submit the form
                document.getElementById('selectedExportForm').submit();
            });
        }
    });
</script>
@endpush 
