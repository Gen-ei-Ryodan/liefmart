@extends('layouts.app')

@section('title', 'Data Produk')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-boxes me-2"></i>Data Produk
                    </h5>
                    @php
                        $exportQuery = request()->query();
                        $exportSuffix = count($exportQuery) ? ('?' . http_build_query($exportQuery)) : '';
                        $exportXlsxUrl = route('products.export', ['format' => 'xlsx']) . $exportSuffix;
                        $exportCsvUrl = route('products.export', ['format' => 'csv']) . $exportSuffix;
                        $exportPdfUrl = route('products.export', ['format' => 'pdf']) . $exportSuffix;
                    @endphp
                    <div>
                        <div class="btn-group me-2" role="group">
                            <button class="btn btn-sm btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li>
                                    <a class="dropdown-item" href="{{ $exportXlsxUrl }}">
                                        <i class="fas fa-file-excel me-2 text-success"></i> Excel (.xlsx)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ $exportCsvUrl }}">
                                        <i class="fas fa-file-csv me-2 text-primary"></i> CSV (.csv)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ $exportPdfUrl }}" target="_blank" rel="noopener">
                                        <i class="fas fa-file-pdf me-2 text-danger"></i> PDF (.pdf)
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <a href="{{ route('products.create') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Tambah Produk
                        </a>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <!-- Search and Filters -->
                    <div class="bg-light rounded-3 p-3 mb-4">
                        <form action="{{ route('products.index') }}" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group input-group-seamless">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3">
                                        <i class="fas fa-search text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control ps-0 border-start-0 rounded-end-3" placeholder="Cari nama, SKU, atau deskripsi..." name="search" value="{{ request('search') }}">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <select name="main_category_id" class="form-select rounded-3 border-0 shadow-sm">
                                    <option value="">-- Kategori Utama --</option>
                                    @foreach($mainCategories as $category)
                                    <option value="{{ $category->id }}" {{ request('main_category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <select name="brand_id" class="form-select rounded-3 border-0 shadow-sm">
                                    <option value="">-- Brand --</option>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <select name="status" class="form-select rounded-3 border-0 shadow-sm">
                                    <option value="">-- Status --</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill shadow-sm me-2 flex-grow-1">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                
                                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                                    <i class="fas fa-redo-alt"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Alerts -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <!-- Results Summary -->
                    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                        <div class="bg-soft-primary px-3 py-2 rounded-pill">
                            <p class="text-sm text-primary mb-0 fw-medium">
                                <i class="fas fa-database me-1"></i>
                                Menampilkan {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk
                            </p>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="me-3 bg-white shadow-sm rounded-pill px-3 py-1">
                                <label class="text-sm text-muted me-2 mb-0">Tampilkan:</label>
                                <select class="form-select form-select-sm d-inline-block border-0" style="width: auto; background: transparent;" 
                                        onchange="window.location.href=this.options[this.selectedIndex].value">
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => 15]) }}" 
                                            {{ request('per_page') == 15 || !request('per_page') ? 'selected' : '' }}>15</option>
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => 30]) }}" 
                                            {{ request('per_page') == 30 ? 'selected' : '' }}>30</option>
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => 50]) }}" 
                                            {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => 100]) }}" 
                                            {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                            
                            <div class="btn-group shadow-sm">
                                <a href="{{ request()->fullUrlWithQuery(['order_by' => 'name', 'order_direction' => 'asc']) }}" 
                                   class="btn btn-sm rounded-start-3 {{ (request('order_by') == 'name' && request('order_direction') == 'asc') ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fas fa-sort-alpha-down me-1"></i> A-Z
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['order_by' => 'created_at', 'order_direction' => 'desc']) }}" 
                                   class="btn btn-sm rounded-end-3 {{ (request('order_by') == 'created_at' && request('order_direction') == 'desc') || (!request('order_by') && !request('order_direction')) ? 'btn-primary' : 'btn-outline-primary' }}">
                                    <i class="fas fa-calendar-alt me-1"></i> Terbaru
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive ds-table-container border rounded-3 shadow-sm overflow-hidden">
                        <table class="table align-items-center mb-0 table-hover">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4" style="width: 5%;">No.</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3" style="width: 20%;">Nama Produk</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2" style="width: 12%;">SKU</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2" style="width: 12%;">Barcode</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Main Category</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Brand</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Sub Brand</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Kategori</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Tipe</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Ukuran</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Varian</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Harga Awal</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Diskon (%)</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Harga Akhir</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center" style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $index => $product)
                                <tr>
                                    <td class="ps-4">
                                        <p class="text-xs font-weight-bold mb-0">{{ $products->firstItem() + $index }}</p>
                                    </td>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm fw-semibold text-wrap">{{ $product->name }}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold text-wrap">
                                            {{ $product->sku ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold text-wrap">
                                            {{ $product->barcode ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->mainCategory->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->brand->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->subBrand->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->productCategory->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->productType->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->productSize->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ $product->productVariant->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            Rp {{ number_format($product->initial_price ?? 0, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            {{ number_format($product->discount_percentage ?? 0, 1) }}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-success text-xs font-weight-bold">
                                            @php
                                                $initialPrice = $product->initial_price ?? 0;
                                                $discountPercentage = $product->discount_percentage ?? 0;
                                                $finalPrice = $initialPrice;
                                                if($discountPercentage > 0) {
                                                    $finalPrice = $initialPrice * (1 - $discountPercentage / 100);
                                                }
                                            @endphp
                                            Rp {{ number_format($finalPrice, 0, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge rounded-pill {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $product->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        <div class="btn-group">
                                            <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-info rounded-start-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="{{ route('products.initial-price.show', $product->id) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-tag"></i>
                                            </a>
                                            <a href="{{ route('products.show', $product->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger rounded-end-3" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="12" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="empty-state mb-3">
                                                <i class="fas fa-box-open fa-4x text-secondary opacity-50"></i>
                                            </div>
                                            <h6 class="fw-normal mb-1">Tidak ada data produk</h6>
                                            @if(request('search') || request('main_category_id') || request('brand_id') || request('status'))
                                                <p class="text-sm text-muted mb-3">Coba ubah filter atau kata kunci pencarian Anda</p>
                                                <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                                    <i class="fas fa-redo-alt me-1"></i> Reset Filter
                                                </a>
                                            @else
                                                <p class="text-sm text-muted mb-3">Tambahkan produk baru untuk memulai</p>
                                                <a href="{{ route('products.create') }}" class="btn btn-sm btn-primary rounded-pill px-4">
                                                    <i class="fas fa-plus me-1"></i> Tambah Produk
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Improved Pagination -->
                    <div class="d-flex justify-content-between align-items-center px-4 pt-4">
                        <p class="text-sm text-muted mb-0">
                            Menampilkan {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} data
                        </p>
                        <div class="pagination-container">
                            {{ $products->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .text-wrap {
        white-space: normal !important;
        word-wrap: break-word !important;
        min-width: 100px;
    }

    .input-group-seamless .input-group-text,
    .input-group-seamless .form-control {
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .bg-soft-primary {
        background-color: rgba(59, 113, 239, 0.1);
    }

    .empty-state {
        height: 100px;
        width: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(108, 117, 125, 0.1);
    }

    .bg-gradient-light {
        background: linear-gradient(to right, #f8f9fa, #fff);
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    @media (max-width: 992px) {
        .table-responsive {
            display: block;
            width: 100%;
        }

        .table-responsive .table {
            width: 100%;
            min-width: 1000px;
        }

        .d-flex.align-items-center {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .d-flex.align-items-center > div {
            margin-bottom: 1rem;
            width: 100%;
        }

        .btn-group {
            width: 100%;
            display: flex;
        }

        .btn-group .btn {
            flex: 1;
        }
    }

    @media (max-width: 768px) {
        .d-flex.justify-content-between.align-items-center {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .d-flex.justify-content-between.align-items-center > p {
            margin-bottom: 1rem;
        }

        .pagination-container {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 15px;
        }
    }
</style>
@endsection 
