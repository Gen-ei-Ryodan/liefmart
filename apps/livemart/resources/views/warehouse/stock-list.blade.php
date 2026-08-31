@extends('layouts.app')

@section('title', 'Daftar Stok Barang')

@section('content')
<script>
    // Immediate execution script to enforce table height
    (function() {
        console.log("Stock list immediate table fix running");
        document.addEventListener('DOMContentLoaded', function() {
            // Force table height constraint
            const tableContainer = document.querySelector('.table-responsive');
            if (tableContainer) {
                tableContainer.style.maxHeight = '500px';
                tableContainer.style.overflowY = 'auto';
                console.log("Applied immediate stock table height fix");
            }
        });
    })();
</script>
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold {{ isset($isDamaged) && $isDamaged ? 'text-danger' : 'text-primary' }}">
                        <i class="fas {{ isset($isDamaged) && $isDamaged ? 'fa-exclamation-triangle' : 'fa-boxes' }} me-2"></i>
                        {{ isset($isDamaged) && $isDamaged ? 'Daftar Barang Rusak di Gudang' : 'Daftar Stok Barang di Gudang' }}
                    </h5>
                    <div>
                        @if(isset($isDamaged) && $isDamaged)
                            <a href="{{ route('warehouse.stock.list') }}" class="btn btn-sm btn-primary me-2">
                                <i class="fas fa-boxes me-1"></i> Lihat Stok Normal
                            </a>
                            <a href="{{ route('warehouse.stock.export', array_merge(request()->except(['page', 'per_page']), ['is_damaged' => true])) }}" class="btn btn-sm btn-danger">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </a>
                        @else
                            <a href="{{ route('warehouse.stock.damaged') }}" class="btn btn-sm btn-danger me-2">
                                <i class="fas fa-exclamation-triangle me-1"></i> Lihat Barang Rusak
                            </a>
                            <a href="{{ route('warehouse.stock.export', request()->except(['page', 'per_page'])) }}" class="btn btn-sm btn-success">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body p-4">

            <!-- Filter Form dengan design modern -->
            <div class="card shadow-sm mb-4 border-0 rounded-3 {{ isset($isDamaged) && $isDamaged ? 'border-danger border' : '' }}">
                <div class="card-header {{ isset($isDamaged) && $isDamaged ? 'bg-danger text-white' : 'bg-white' }} py-3">
                    <h5 class="mb-0 {{ isset($isDamaged) && $isDamaged ? 'text-white' : 'text-primary' }}">
                        <i class="fas fa-filter me-2"></i> Filter {{ isset($isDamaged) && $isDamaged ? 'Barang Rusak' : 'Stok' }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($isDamaged) && $isDamaged ? route('warehouse.stock.damaged') : route('warehouse.stock.list') }}" method="GET">
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
                                <select name="status_ed" class="form-select">
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
                                <select name="tax_id" class="form-select">
                                    <option value="">-- Filter Pajak --</option>
                                    <option value="N/A" {{ request('tax_id') == 'N/A' ? 'selected' : '' }}>Tanpa Pajak</option>
                                    @foreach($taxCategories as $tax)
                                        <option value="{{ $tax->id }}" {{ request('tax_id') == $tax->id ? 'selected' : '' }}>{{ $tax->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="is_free" class="form-select">
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
                        
                        <div class="collapse {{ request()->hasAny(['main_category_id', 'brand_id', 'sub_brand_id', 'product_category_id', 'product_type_id', 'product_size_id', 'product_variant_id', 'is_free', 'lokasi_id']) ? 'show' : '' }}" id="advancedFilters">
                            <div class="card bg-light mb-3 border-0 rounded-3">
                                <div class="card-header bg-light py-3">
                                    <h5 class="mb-0 text-primary">
                                        <i class="fas fa-search-plus me-2"></i> Filter Produk Lanjutan
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Brand</label>
                                            <select name="brand_id" class="form-select">
                                                <option value="">-- Semua Brand --</option>
                                                @foreach($brands as $brand)
                                                    <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Sub Brand</label>
                                            <select name="sub_brand_id" class="form-select">
                                                <option value="">-- Semua Sub Brand --</option>
                                                @foreach($subBrands as $subBrand)
                                                    <option value="{{ $subBrand->id }}" {{ request('sub_brand_id') == $subBrand->id ? 'selected' : '' }}>{{ $subBrand->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Kategori Produk</label>
                                            <select name="product_category_id" class="form-select">
                                                <option value="">-- Semua Kategori Produk --</option>
                                                @foreach($productCategories as $category)
                                                    <option value="{{ $category->id }}" {{ request('product_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Tipe Produk</label>
                                            <select name="product_type_id" class="form-select">
                                                <option value="">-- Semua Tipe --</option>
                                                @foreach($productTypes as $type)
                                                    <option value="{{ $type->id }}" {{ request('product_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Ukuran Produk</label>
                                            <select name="product_size_id" class="form-select">
                                                <option value="">-- Semua Ukuran --</option>
                                                @foreach($productSizes as $size)
                                                    <option value="{{ $size->id }}" {{ request('product_size_id') == $size->id ? 'selected' : '' }}>{{ $size->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Varian Produk</label>
                                            <select name="product_variant_id" class="form-select">
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
                                            <a href="{{ route('warehouse.stock.list') }}" class="btn btn-secondary shadow-sm ms-2">
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
            
            <!-- Stock Summary Cards dengan tampilan yang menarik-->
            <div class="row mb-4 g-3">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body {{ isset($isDamaged) && $isDamaged ? 'bg-danger' : 'bg-primary' }} text-white rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="display-5 fw-bold mb-0">{{ $filteredStocks->count() }}</h2>
                                    <div class="text-white opacity-75 mt-2 fw-medium">Total Items</div>
                                </div>
                                <i class="fas {{ isset($isDamaged) && $isDamaged ? 'fa-exclamation-triangle' : 'fa-boxes' }} fa-3x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                                <div class="col-md-3">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-warning text-dark rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="display-5 fw-bold mb-0">{{ number_format($correctTotalQty ?? $filteredStocks->sum(function($stock) { return max(0, $stock->qty); }), 0) }}</h2>
                                    <div class="text-dark opacity-75 mt-2 fw-medium">Total Quantity</div>
                                </div>
                                <i class="fas fa-cubes fa-3x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-danger text-white rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="display-5 fw-bold mb-0">{{ $filteredStocks->where('ed_status', 'kadaluarsa')->count() }}</h2>
                                    <div class="text-white opacity-75 mt-2 fw-medium">Kadaluarsa</div>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-3x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient h-100">
                        <div class="card-body bg-success text-white rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="display-5 fw-bold mb-0">{{ $filteredStocks->where('ed_status', 'lebih_dari_1_tahun')->count() }}</h2>
                                    <div class="text-white opacity-75 mt-2 fw-medium">Stok Aman</div>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Data Table -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-table me-2"></i> Data Stok Barang
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto; overflow-x: auto; border: 1px solid #dee2e6;">
                        <table class="table table-bordered table-striped wide-table mb-0" style="min-width: 1200px;">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">No</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">SKU</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Nama Produk</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Brand</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Sub Brand</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Tipe</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Ukuran</th>
                                    <th class="text-primary" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Varian</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Jumlah</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Satuan</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Expired</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Status ED</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Pajak</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Tanggal Penerimaan</th>
                                    <th class="text-primary text-center" style="position: sticky; top: 0; background-color: #f8f9fa; z-index: 1;">Nomor PO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stocks as $index => $stock)
                                    <tr class="{{ $stock->is_free ? 'bg-info bg-opacity-10' : ($loop->even ? 'bg-light bg-opacity-50' : '') }}">
                                        <td class="px-3 py-2 fw-medium">{{ ($stocks->currentPage() - 1) * $stocks->perPage() + $loop->iteration }}</td>
                                        <td class="px-3 py-2 font-monospace">{{ $stock->product->sku ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 fw-medium">
                                            {{ $stock->product->name }}
                                            @if($stock->is_free)
                                                <span class="badge bg-info ms-1">Free Item</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">{{ $stock->product->brand->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ $stock->product->subBrand->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ $stock->product->productType->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ $stock->product->productSize->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2">{{ $stock->product->productVariant->name ?? 'N/A' }}</td>
                                       
                                        <td class="px-3 py-2 text-center fw-bold">{{ number_format($stock->qty, 2) }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if($stock->penerimaanDetail && $stock->penerimaanDetail->satuan)
                                                {{ $stock->penerimaanDetail->satuan->name }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center {{ $stock->ed_status == 'kadaluarsa' ? 'text-danger fw-bold' : '' }}">
                                            {{ $stock->expired_date ? $stock->expired_date->format('d/m/y') : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($stock->expired_date)
                                                @if($stock->ed_status == 'kadaluarsa')
                                                    <span class="badge rounded-pill bg-danger">Kadaluarsa</span>
                                                @elseif($stock->ed_status == 'kurang_dari_3_bulan')
                                                    <span class="badge rounded-pill bg-danger">< 3 Bulan</span>
                                                @elseif($stock->ed_status == 'kurang_dari_6_bulan')
                                                    <span class="badge rounded-pill bg-warning text-dark">< 6 Bulan</span>
                                                @elseif($stock->ed_status == 'kurang_dari_1_tahun')
                                                    <span class="badge rounded-pill bg-info text-white">< 1 Tahun</span>
                                                @elseif($stock->ed_status == 'lebih_dari_1_tahun')
                                                    <span class="badge rounded-pill bg-success">Aman</span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">{{ $stock->tax ? $stock->tax->name : 'N/A' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if($stock->penerimaanDetail && $stock->penerimaanDetail->penerimaan)
                                                {{ $stock->penerimaanDetail->penerimaan->tanggal_penerimaan ? $stock->penerimaanDetail->penerimaan->tanggal_penerimaan->format('d/m/y') : '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center font-monospace">
                                            @if($stock->penerimaanDetail && $stock->penerimaanDetail->penerimaan)
                                                {{ $stock->penerimaanDetail->penerimaan->nomor_po ?? '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="16" class="text-center py-5">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-box-open fa-4x text-secondary mb-3 opacity-50"></i>
                                                <h5 class="text-secondary">Tidak ada data stok</h5>
                                                <p class="text-muted">Tidak ada barang yang tersedia dengan filter yang dipilih</p>
                                                <a href="{{ route('warehouse.stock.list') }}" class="btn btn-sm btn-outline-primary mt-2">
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
                        Showing {{ $stocks->firstItem() ?? 0 }} to {{ $stocks->lastItem() ?? 0 }} of {{ $stocks->total() }} results
                    </span>
                </div>
                <div>
                    {{ $stocks->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="fas fa-info-circle me-2"></i> Keterangan Status Expired Date (ED)
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#edInfo">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="collapse show" id="edInfo">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Kadaluarsa</span>
                                    <span class="badge bg-danger rounded-pill">Produk sudah melewati tanggal expired</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Kurang dari 3 Bulan</span>
                                    <span class="badge bg-danger rounded-pill">Akan expired dalam 3 bulan</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Kurang dari 6 Bulan</span>
                                    <span class="badge bg-warning text-dark rounded-pill">Akan expired dalam 6 bulan</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Kurang dari 1 Tahun</span>
                                    <span class="badge bg-info text-white rounded-pill">Akan expired dalam 1 tahun</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Lebih dari 1 Tahun</span>
                                    <span class="badge bg-success rounded-pill">Masa expired masih lama</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-3 mb-2 rounded bg-light">
                                    <span class="fw-medium">Tanpa ED</span>
                                    <span class="badge bg-secondary rounded-pill">Tidak ada tanggal expired</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Returns Information Section -->
            @if(isset($isDamaged) && $isDamaged && isset($returPenjualanDetails) && isset($returOfflineDetails))
            <div class="card shadow-sm border-0 rounded-3 mt-4">
                <div class="card-header bg-light py-3">
                    <h5 class="mb-0 text-danger">
                        <i class="fas fa-history me-2"></i> Riwayat Barang Retur (Rusak)
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="returTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="online-tab" data-bs-toggle="tab" data-bs-target="#online-returns" type="button" role="tab" aria-controls="online-returns" aria-selected="true">
                                <i class="fas fa-globe me-1"></i> Retur Online
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="offline-tab" data-bs-toggle="tab" data-bs-target="#offline-returns" type="button" role="tab" aria-controls="offline-returns" aria-selected="false">
                                <i class="fas fa-store me-1"></i> Retur Offline
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="returTabsContent">
                        <!-- Online Returns -->
                        <div class="tab-pane fade show active" id="online-returns" role="tabpanel" aria-labelledby="online-tab">
                            @if(count($returPenjualanDetails) > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Kode Retur</th>
                                                <th>Produk</th>
                                                <th>Qty</th>
                                                <th>Platform</th>
                                                <th>Tanggal Retur</th>
                                                <th>Alasan</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($returPenjualanDetails as $detail)
                                                <tr>
                                                    <td>{{ $detail->returPenjualan->kode_retur }}</td>
                                                    <td>{{ $detail->product->name ?? 'Produk tidak ditemukan' }}</td>
                                                    <td class="text-center">{{ $detail->qty }}</td>
                                                    <td>{{ $detail->returPenjualan->order->platform->name ?? '-' }}</td>
                                                    <td>{{ $detail->returPenjualan->tanggal_retur->format('d/m/y') }}</td>
                                                    <td>{{ $detail->alasan ?? '-' }}</td>
                                                    <td>
                                                        <a href="{{ route('retur-penjualan.show', $detail->returPenjualan->id) }}" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Tidak ada data retur penjualan online (rusak).
                                </div>
                            @endif
                        </div>
                        
                        <!-- Offline Returns -->
                        <div class="tab-pane fade" id="offline-returns" role="tabpanel" aria-labelledby="offline-tab">
                            @if(count($returOfflineDetails) > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Kode Retur</th>
                                                <th>Produk</th>
                                                <th>Qty</th>
                                                <th>No. SJ</th>
                                                <th>Tanggal Retur</th>
                                                <th>Alasan</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($returOfflineDetails as $detail)
                                                <tr>
                                                    <td>{{ $detail->returOfflineSale->kode_retur }}</td>
                                                    <td>{{ $detail->product->name ?? 'Produk tidak ditemukan' }}</td>
                                                    <td class="text-center">{{ $detail->qty }}</td>
                                                    <td>{{ $detail->returOfflineSale->offlineSale->surat_jalan_number ?? '-' }}</td>
                                                    <td>{{ $detail->returOfflineSale->tanggal_retur->format('d/m/y') }}</td>
                                                    <td>{{ $detail->alasan ?? '-' }}</td>
                                                    <td>
                                                        <a href="{{ route('retur-offline.show', $detail->returOfflineSale->id) }}" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> Tidak ada data retur penjualan offline (rusak).
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
            <!-- End of Returns Information Section -->
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
<style>
    /* Styling untuk tabel */
    .table {
        font-size: 13px;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .table th {
        font-weight: 600;
        padding: 12px 8px;
        border: 1px solid #e3e6f0;
        vertical-align: middle;
    }
    
    .table td {
        padding: 8px;
        border: 1px solid #e3e6f0;
        vertical-align: middle;
    }
    
    /* Pastikan tabel dapat scrolling */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    console.log("Stock list page script loaded");
    
    // Monitor table size
    function logTableDimensions() {
        const table = document.querySelector('.wide-table');
        const container = document.querySelector('.table-responsive');
        if (table && container) {
            console.log("Stock table dimensions:", {
                tableHeight: table.offsetHeight,
                tableWidth: table.offsetWidth,
                containerHeight: container.offsetHeight,
                containerWidth: container.offsetWidth,
                containerScrollHeight: container.scrollHeight,
                containerMaxHeight: container.style.maxHeight
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Stock list page DOM loaded");
        logTableDimensions();
        
        // Initialize TomSelect for filter dropdowns
        try {
            // Initialize TomSelect for all select elements in filter forms
            const selectElements = document.querySelectorAll('select[name="brand_id"], select[name="sub_brand_id"], select[name="product_category_id"], select[name="product_type_id"], select[name="product_size_id"], select[name="product_variant_id"], select[name="tax_id"], select[name="status_ed"], select[name="is_free"]');
            
            selectElements.forEach(function(selectElement) {
                new TomSelect(selectElement, {
                    placeholder: selectElement.querySelector('option[value=""]')?.textContent || 'Pilih...',
                    allowEmptyOption: true,
                    searchField: ['text'],
                    plugins: ['remove_button'],
                    maxItems: 1
                });
            });
            
            console.log("TomSelect initialized for filter dropdowns");
        } catch (error) {
            console.error("Error initializing TomSelect:", error);
        }
        
        // Log dimensions periodically
        setInterval(logTableDimensions, 2000);
        
        // Filter lanjutan toggle
        document.getElementById('advancedFilterBtn').addEventListener('click', function() {
            const advancedFilters = document.getElementById('advancedFilters');
            if (advancedFilters.classList.contains('show')) {
                advancedFilters.classList.remove('show');
                this.innerHTML = '<i class="fas fa-sliders-h"></i>';
            } else {
                advancedFilters.classList.add('show');
                this.innerHTML = '<i class="fas fa-times"></i>';
            }
        });

        // Toggle keterangan ED
        const edInfoBtn = document.querySelector('[data-bs-target="#edInfo"]');
        const edInfoSection = document.getElementById('edInfo');
        
        if (edInfoBtn && edInfoSection) {
        edInfoBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (edInfoSection.classList.contains('show')) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        });
        }
    });
</script>
@endpush
@endsection