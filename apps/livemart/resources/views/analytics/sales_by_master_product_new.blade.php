<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gross Profit per Master Internal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <!-- TomSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

    <style>
        :root { --primary-color: #4361ee; --secondary-color: #3f37c9; --success-color: #0bb4aa; --info-color: #4cc9f0; --warning-color: #f72585; --dark-color: #212529; }
        body { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container-fluid { padding: 20px; max-width: 1440px; margin: 0 auto; }
        .card-header { border-radius: 8px 8px 0 0; font-weight: 600; padding: 15px 20px; background-color: var(--primary-color); color: white; }
        .card-body { padding: 20px; }
        .table-dark th { background-color: var(--dark-color); color: white; font-weight: 500; }
        .table-responsive { max-height: 800px; overflow-y: auto; overflow-x: auto; }
        .table tbody tr:hover { background-color: rgba(0, 0, 0, 0.03); }
        .table th:nth-child(4), .table td:nth-child(4) { min-width: 300px; word-wrap: break-word; }
        .table th:nth-child(8), .table td:nth-child(8) { min-width: 300px; word-wrap: break-word; }
        .summary-card { border-radius: 8px; color: white; height: 100%; min-height: 120px; }
        .summary-card .card-body { padding: 1rem; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .summary-card h6 { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-align: center; }
        .summary-card h3, .summary-card h4 { font-weight: 700; margin-bottom: 0.25rem; text-align: center; }
        .platform-box { display: inline-block; padding: 5px 10px; border-radius: 6px; font-weight: 500; }
        .platform-shopee { background-color: #f53d2d; color: white; }
        .platform-tiktok { background-color: #000000; color: white; }
        .ts-wrapper { border-radius: 6px; }
        .ts-wrapper .ts-control { border: 1px solid #ced4da; border-radius: 6px; padding: 10px 15px; }
        .skeleton-loader { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s ease-in-out infinite; height: 40px; border-radius: 4px; margin: 5px 0; }
        @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        #table-container { min-height: 400px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb" style="background-color: transparent; padding: 0; margin-bottom: 20px;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item">Analytics</li>
                <li class="breadcrumb-item active">Gross Profit per Master Internal</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Gross Profit per Master Internal</h5>
            </div>
            <div class="card-body">
                <!-- Quick Date Range Filters -->
                <div class="mb-4">
                    <h6 class="mb-2 fw-bold"><i class="bi bi-calendar3 me-2"></i>Filter Cepat:</h6>
                    <div class="btn-group" role="group">
                        <a href="{{ route('analytics.sales-by-master-product', ['quick_range' => '7days'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '7days' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-week me-1"></i> 7 Hari
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product', ['quick_range' => '2weeks'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '2weeks' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar2-week me-1"></i> 2 Minggu
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product', ['quick_range' => '1month'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '1month' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-month me-1"></i> 1 Bulan
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product', ['quick_range' => '3months'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '3months' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar3-range me-1"></i> 3 Bulan
                        </a>
                        @if(request('quick_range'))
                            <a href="{{ route('analytics.sales-by-master-product', request()->except(['quick_range', 'start_date', 'end_date'])) }}"
                               class="btn btn-outline-danger">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" action="{{ route('analytics.sales-by-master-product') }}" id="filter-form" class="card mb-4">
                    <div class="card-header bg-light" style="background-color: #f8f9fa !important; color: #333;">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Filter Custom</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Tanggal Mulai</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="platform_id" class="form-label">Platform</label>
                                <select class="form-select" id="platform_id" name="platform_id">
                                    <option value="">Semua Platform</option>
                                    @foreach($platforms as $platform)
                                        <option value="{{ $platform->id }}" {{ $selectedPlatform == $platform->id ? 'selected' : '' }}>
                                            {{ $platform->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari Produk</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Cari nama atau SKU" value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                                    <i class="bi bi-funnel me-1"></i> Filter Lanjutan
                                </button>
                            </div>
                        </div>

                        <div class="collapse {{ request()->hasAny(['main_categories', 'brands', 'sub_brands', 'product_categories', 'product_types', 'product_sizes', 'product_variants', 'order_number', 'sort']) ? 'show' : '' }}" id="advancedFilters">
                            <div class="row mt-3 g-3">
                                <div class="col-md-6">
                                    <label for="order_number" class="form-label">Nomor Order</label>
                                    <input type="text" class="form-control" id="order_number" name="order_number" placeholder="Masukkan nomor order" value="{{ request('order_number') }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="sort" class="form-label">Urutkan</label>
                                    <select class="form-select" id="sort" name="sort">
                                        <option value="revenue_highest" {{ $sortBy == 'revenue_highest' ? 'selected' : '' }}>Saldo Masuk Tertinggi</option>
                                        <option value="revenue_lowest" {{ $sortBy == 'revenue_lowest' ? 'selected' : '' }}>Saldo Masuk Terendah</option>
                                        <option value="profit_highest" {{ $sortBy == 'profit_highest' ? 'selected' : '' }}>Profit Tertinggi</option>
                                        <option value="profit_lowest" {{ $sortBy == 'profit_lowest' ? 'selected' : '' }}>Profit Terendah</option>
                                        <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>Kuantitas Tertinggi</option>
                                        <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>Kuantitas Terendah</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="brands" class="form-label">Brand</label>
                                    <select class="form-select" id="brands" name="brands[]" multiple>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->id }}" {{ in_array($brand->id, $selectedBrands) ? 'selected' : '' }}>
                                                {{ $brand->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="sub_brands" class="form-label">Sub Brand</label>
                                    <select class="form-select" id="sub_brands" name="sub_brands[]" multiple>
                                        @foreach($subBrands as $subBrand)
                                            <option value="{{ $subBrand->id }}" {{ in_array($subBrand->id, $selectedSubBrands) ? 'selected' : '' }}>
                                                {{ $subBrand->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="product_categories" class="form-label">Kategori Produk</label>
                                    <select class="form-select" id="product_categories" name="product_categories[]" multiple {{ empty($selectedSubBrands) && empty($selectedProductCategories) ? 'disabled' : '' }}>
                                        @foreach($productCategories as $category)
                                            <option value="{{ $category->id }}" {{ in_array($category->id, $selectedProductCategories) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="product_types" class="form-label">Tipe Produk</label>
                                    <select class="form-select" id="product_types" name="product_types[]" multiple {{ empty($selectedProductCategories) && empty($selectedProductTypes) ? 'disabled' : '' }}>
                                        @foreach($productTypes as $type)
                                            <option value="{{ $type->id }}" {{ in_array($type->id, $selectedProductTypes) ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="product_sizes" class="form-label">Ukuran Produk</label>
                                    <select class="form-select" id="product_sizes" name="product_sizes[]" multiple {{ empty($selectedProductTypes) && empty($selectedProductSizes) ? 'disabled' : '' }}>
                                        @foreach($productSizes as $size)
                                            <option value="{{ $size->id }}" {{ in_array($size->id, $selectedProductSizes) ? 'selected' : '' }}>
                                                {{ $size->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="product_variants" class="form-label">Varian Produk</label>
                                    <select class="form-select" id="product_variants" name="product_variants[]" multiple {{ empty($selectedProductSizes) && empty($selectedProductVariants) ? 'disabled' : '' }}>
                                        @foreach($productVariants as $variant)
                                            <option value="{{ $variant->id }}" {{ in_array($variant->id, $selectedProductVariants) ? 'selected' : '' }}>
                                                {{ $variant->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100" id="filter-submit-btn">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('analytics.sales-by-master-product') }}" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="#" data-export="{{ route('analytics.sales-by-master-product.export', request()->all()) }}" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Active Filters Display -->
                @if(request()->hasAny(['start_date', 'end_date', 'platform_id', 'order_number', 'search', 'brands', 'sub_brands', 'product_categories', 'product_types', 'product_sizes', 'product_variants', 'outstanding_status']))
                <div class="bg-light p-3 rounded mb-4 border">
                    <h6 class="fw-bold mb-3"><i class="bi bi-funnel-fill me-2"></i>Filter Aktif:</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <span class="fw-semibold">Periode:</span>
                            {{ date('d/m/Y', strtotime($startDate)) }}
                            @if($startDate != $endDate) - {{ date('d/m/Y', strtotime($endDate)) }} @endif
                        </div>
                        <div class="col-md-3">
                            <span class="fw-semibold">Platform:</span>
                            @if($selectedPlatform)
                                @php $platformName = $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown'; @endphp
                                <span class="platform-box platform-{{ strtolower(str_replace(' ', '-', $platformName)) }}">{{ $platformName }}</span>
                            @else
                                Semua Platform
                            @endif
                        </div>
                        @if(request('order_number'))
                        <div class="col-md-3">
                            <span class="fw-semibold">Nomor Order:</span>
                            <span class="badge bg-info">{{ request('order_number') }}</span>
                        </div>
                        @endif
                        @if(request('search'))
                        <div class="col-md-3">
                            <span class="fw-semibold">Cari Produk:</span>
                            <span class="badge bg-info">{{ request('search') }}</span>
                        </div>
                        @endif
                        @if(!empty($selectedBrands))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Brand:</span>
                            @foreach($brands->whereIn('id', $selectedBrands) as $brand)
                                <span class="badge bg-secondary">{{ $brand->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($selectedSubBrands))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Sub Brand:</span>
                            @foreach($subBrands->whereIn('id', $selectedSubBrands) as $subBrand)
                                <span class="badge bg-secondary">{{ $subBrand->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($selectedProductCategories))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Kategori:</span>
                            @foreach($productCategories->whereIn('id', $selectedProductCategories) as $category)
                                <span class="badge bg-secondary">{{ $category->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($selectedProductTypes))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Tipe:</span>
                            @foreach($productTypes->whereIn('id', $selectedProductTypes) as $type)
                                <span class="badge bg-secondary">{{ $type->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($selectedProductSizes))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Ukuran:</span>
                            @foreach($productSizes->whereIn('id', $selectedProductSizes) as $size)
                                <span class="badge bg-secondary">{{ $size->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($selectedProductVariants))
                        <div class="col-md-3 mt-2">
                            <span class="fw-semibold">Varian:</span>
                            @foreach($productVariants->whereIn('id', $selectedProductVariants) as $variant)
                                <span class="badge bg-secondary">{{ $variant->name }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Summary Cards -->
                <div class="row mb-4 g-2">
                    <div class="col">
                        <div class="card bg-primary summary-card h-100" style="background-color: var(--primary-color) !important;">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2">Barang Keluar</h6>
                                <h3 class="mb-1">{{ number_format($summary['total_products']) }}</h3>
                                <small class="opacity-75">Jumlah barang keluar dari gudang</small>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-success summary-card h-100" style="background-color: var(--success-color) !important;">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2">Total Saldo Masuk</h6>
                                <h4 class="mb-1" style="font-size: 1.1rem;">Rp {{ $summary['total_revenue_formatted'] ?? number_format($summary['total_revenue'], 0, ',', '.') }}</h4>
                                <small class="opacity-75">Uang Masuk</small>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-warning summary-card h-100" style="background-color: #f59e0b !important;">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2">Saldo Masuk - PPN</h6>
                                <h4 class="mb-1" style="font-size: 1.1rem;">Rp {{ $summary['total_revenue_without_ppn_formatted'] ?? number_format($summary['total_revenue_without_ppn'], 0, ',', '.') }}</h4>
                                <small class="opacity-75">Setelah PPN 11%</small>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-info summary-card h-100" style="background-color: var(--info-color) !important;">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2">Total Modal</h6>
                                <h4 class="mb-1" style="font-size: 1.1rem;">Rp {{ $summary['total_capital_formatted'] ?? number_format($summary['total_capital'], 0, ',', '.') }}</h4>
                                <small class="opacity-75">Modal</small>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card bg-dark summary-card h-100" style="background-color: var(--dark-color) !important;">
                            <div class="card-body text-center">
                                <h6 class="card-title mb-2">Gross Profit</h6>
                                <h4 class="mb-1" style="font-size: 1.1rem;">Rp {{ $summary['total_gross_profit_formatted'] ?? number_format($summary['total_gross_profit'], 0, ',', '.') }}</h4>
                                <small class="opacity-75">Margin: {{ $summary['profit_margin_formatted'] ?? number_format($summary['profit_margin'], 2) }}%</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Container (AJAX loaded) -->
                <div id="table-container">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Container (Lazy loaded) -->
    <div id="modal-container"></div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        (function() {
            'use strict';

            // Configuration - use route helper (routes are in analytics prefix group)
            const config = {
                tableUrl: '{{ route("analytics.sales-by-master-product.table") }}',
                modalUrl: '{{ route("analytics.sales-by-master-product.modal") }}',
                getBrandsUrl: '{{ route("analytics.get-brands") }}',
                getSubBrandsUrl: '{{ route("analytics.get-subbrands") }}',
                getCategoriesUrl: '{{ route("analytics.get-product-categories") }}',
                getTypesUrl: '{{ route("analytics.get-product-types") }}',
                getSizesUrl: '{{ route("analytics.get-product-sizes") }}',
                getVariantsUrl: '{{ route("analytics.get-product-variants") }}'
            };

            // Fallback to direct URLs if routes fail
            if (!config.tableUrl || config.tableUrl.includes('analytics.sales-by-master-product.table')) {
                const base = window.location.origin + '/analytics';
                config.tableUrl = base + '/sales-by-master-product/table';
                config.modalUrl = base + '/sales-by-master-product/modal';
                config.getBrandsUrl = base + '/get-brands';
                config.getSubBrandsUrl = base + '/sales-by-master-product/subbrands';
                config.getCategoriesUrl = base + '/sales-by-master-product/product-categories';
                config.getTypesUrl = base + '/sales-by-master-product/product-types';
                config.getSizesUrl = base + '/sales-by-master-product/product-sizes';
                config.getVariantsUrl = base + '/sales-by-master-product/product-variants';
            }

            // Debounce utility
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => { clearTimeout(timeout); func(...args); };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Load table via AJAX
            function loadTable() {
                const container = document.getElementById('table-container');
                const params = new URLSearchParams(window.location.search);
                params.set('ajax', '1');

                fetch(config.tableUrl + '?' + params.toString())
                    .then(r => r.text())
                    .then(html => {
                        container.innerHTML = html;
                        attachTableEvents();
                    })
                    .catch(err => {
                        container.innerHTML = '<div class="alert alert-danger">Error loading table: ' + err.message + '</div>';
                    });
            }

            // Attach events to dynamically loaded table
            function attachTableEvents() {
                // Pagination links
                document.querySelectorAll('#table-container .pagination a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        window.history.pushState({}, '', url);
                        loadTable();
                    });
                });

                // Detail buttons (lazy load modal)
                document.querySelectorAll('[data-detail-row]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const rowData = JSON.parse(this.dataset.detailRow);
                        loadModal(rowData);
                    });
                });
            }

            // Lazy load modal
            let modalLoaded = false;
            function loadModal(rowData) {
                const container = document.getElementById('modal-container');

                if (!modalLoaded) {
                    fetch(config.modalUrl)
                        .then(r => r.text())
                        .then(html => {
                            container.innerHTML = html;
                            modalLoaded = true;
                            showModal(rowData);
                        });
                } else {
                    showModal(rowData);
                }
            }

            function showModal(rowData) {
                // Populate modal with rowData
                document.getElementById('modal-sku').textContent = rowData.sku || '-';
                document.getElementById('modal-product-name').textContent = rowData.product_name || '-';
                document.getElementById('modal-platform-name').textContent = rowData.platform_product_name || '-';
                document.getElementById('modal-order-number').textContent = rowData.order_number || '-';
                document.getElementById('modal-platform').innerHTML = rowData.platform ?
                    '<span class="platform-box platform-' + rowData.platform.toLowerCase().replace(' ', '-') + '">' + rowData.platform + '</span>' : '-';
                document.getElementById('modal-order-date').textContent = rowData.order_date_formatted || '-';

                // Format numbers
                const formatRupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0);
                const formatPercent = (n) => new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(n || 0) + '%';

                document.getElementById('modal-platform-qty').textContent = new Intl.NumberFormat('id-ID').format(rowData.platform_quantity || 0);
                document.getElementById('modal-qty').textContent = new Intl.NumberFormat('id-ID').format(rowData.quantity || 0);
                document.getElementById('modal-payment-amount').textContent = formatRupiah(rowData.order_total_payment || 0);
                document.getElementById('modal-payment-amount-no-ppn').textContent = formatRupiah(rowData.order_total_payment_without_ppn || 0);
                document.getElementById('modal-proportion').textContent = formatPercent(rowData.proportion_percent || 0);
                document.getElementById('modal-payment-per-product').textContent = formatRupiah(rowData.payment_per_product_per_pcs || 0);
                document.getElementById('modal-payment-per-product-no-ppn').textContent = formatRupiah(rowData.payment_per_product_without_ppn || 0);
                document.getElementById('modal-capital-per-unit').textContent = formatRupiah(rowData.unit_cost || 0);
                document.getElementById('modal-profit-per-pcs').textContent = formatRupiah(rowData.profit_per_pcs || 0);
                document.getElementById('modal-profit-total').textContent = formatRupiah(rowData.gross_profit_total || 0);
                document.getElementById('modal-margin-per-pcs').textContent = formatPercent(rowData.margin_per_pcs || 0);
                document.getElementById('modal-margin-per-item').textContent = formatPercent(rowData.margin_per_item || 0);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('calculationModal'));
                modal.show();
            }

            // ============================================
            // CASCADING FILTERS - CLEAN IMPLEMENTATION
            // ============================================

            // TomSelect instances
            let brandTS, subBrandTS, categoryTS, typeTS, sizeTS, variantTS;

            // ✅ FIX: Flag to prevent cascade during initial load
            let isInitialLoad = true;

            // Reusable function to reload dropdown via AJAX
            function reloadDropdown(url, payload, targetId, callback = null) {
                const targetSelect = document.getElementById(targetId);
                if (!targetSelect) {
                    console.error('Target select not found:', targetId);
                    return;
                }

                console.log('🔄 Reloading dropdown:', { url, payload, targetId });

                // CRITICAL: Enable select FIRST before reloading
                targetSelect.disabled = false;

                // Show loading state (but don't disable - just show loading)
                // targetSelect.disabled = true; // REMOVED - don't disable during reload

                // Build query string
                const params = new URLSearchParams();
                Object.keys(payload).forEach(key => {
                    if (Array.isArray(payload[key])) {
                        payload[key].forEach(val => params.append(key, val));
                    } else if (payload[key]) {
                        params.append(key, payload[key]);
                    }
                });

                const fullUrl = url + '?' + params.toString();
                console.log('Fetching URL:', fullUrl);

                fetch(fullUrl)
                    .then(r => {
                        console.log('Response status:', r.status);
                        if (!r.ok) {
                            throw new Error(`HTTP error! status: ${r.status}`);
                        }
                        return r.json();
                    })
                    .then(data => {
                        console.log('Received data:', data);

                        // ✅ FIX: Get selected values from native select element FIRST (before destroy)
                        // This ensures we don't lose selected values after filter
                        const selectedValues = [];
                        if (targetSelect.tomselect) {
                            // Get from TomSelect if available
                            selectedValues.push(...(targetSelect.tomselect.items || []));
                        } else {
                            // Get from native select element
                            Array.from(targetSelect.selectedOptions).forEach(opt => {
                                if (opt.value) {
                                    selectedValues.push(opt.value);
                                }
                            });
                        }
                        console.log('Preserving selected values:', selectedValues);

                        // Destroy TomSelect if exists
                        if (targetSelect.tomselect) {
                            targetSelect.tomselect.destroy();
                            targetSelect.tomselect = null;
                        }

                        // Filter out empty objects and ensure data has required fields
                        let validData = [];
                        if (data && Array.isArray(data) && data.length > 0) {
                            validData = data.filter(item => {
                                // More lenient filter - just check if id and name exist and are not empty
                                const hasId = item && (item.id !== null && item.id !== undefined && item.id !== '');
                                const hasName = item && (item.name !== null && item.name !== undefined && item.name !== '');

                                const isValid = hasId && hasName;
                                if (!isValid) {
                                    console.warn('Filtered out invalid item:', item);
                                }
                                return isValid;
                            });
                        }

                        console.log('Valid data after filtering:', validData);
                        console.log('Original data received:', data);

                        // Clear and rebuild options in select element FIRST
                        targetSelect.innerHTML = '';

                        // Add options directly to select element
                        if (validData.length > 0) {
                            validData.forEach(item => {
                                const option = document.createElement('option');
                                option.value = String(item.id);
                                option.textContent = String(item.name);
                                if (selectedValues.includes(String(item.id))) {
                                    option.selected = true;
                                }
                                targetSelect.appendChild(option);
                            });
                            console.log('Added', validData.length, 'options to select element');
                        }

                        // Store options data for load function
                        const optionsData = validData.map(item => ({
                            id: String(item.id),
                            name: String(item.name)
                        }));

                        // Prepare options data for TomSelect (format: id and name)
                        const initialData = validData.map(item => ({
                            id: String(item.id),
                            name: String(item.name)
                        }));

                        console.log('TomSelect initial data:', initialData);

                        // CRITICAL FIX: Add options to select element FIRST before initializing TomSelect
                        // This ensures options are available when dropdown is clicked
                        if (validData.length > 0) {
                            validData.forEach(item => {
                                const option = document.createElement('option');
                                option.value = String(item.id);
                                option.textContent = String(item.name);
                                if (selectedValues.includes(String(item.id))) {
                                    option.selected = true;
                                }
                                targetSelect.appendChild(option);
                            });
                        }

                        // Reinitialize TomSelect
                        // ✅ FIX: Use 'id' and 'name' consistently (matches backend format)
                        const ts = new TomSelect(targetSelect, {
                            plugins: ['remove_button'],
                            maxItems: null,
                            placeholder: 'Pilih atau ketik untuk mencari...',
                            valueField: 'id',      // ✅ FIX: Use 'id' (matches backend)
                            labelField: 'name',    // ✅ FIX: Use 'name' (matches backend)
                            searchField: 'name',   // ✅ FIX: Search in name
                            load: function(query, callback) {
                                // ✅ CRITICAL FIX: If no query, ALWAYS return initial data
                                // This ensures dropdown shows options when clicked
                                if (!query.length) {
                                    if (initialData.length > 0) {
                                        console.log('📋 Loading initial data (no query):', initialData.length, 'items');
                                        return callback(initialData);
                                    }
                                    // If no initial data, return empty (don't fetch)
                                    return callback();
                                }

                                // Search mode - fetch from server
                                console.log('🔍 Searching with query:', query);
                                const searchParams = new URLSearchParams();
                                Object.keys(payload).forEach(key => {
                                    if (Array.isArray(payload[key])) {
                                        payload[key].forEach(val => searchParams.append(key + '[]', val));
                                    } else if (payload[key]) {
                                        searchParams.append(key, payload[key]);
                                    }
                                });
                                searchParams.set('search', query);

                                fetch(url + '?' + searchParams.toString())
                                    .then(r => r.json())
                                    .then(searchData => {
                                        console.log('🔍 Search results:', searchData);
                                        const validSearchData = (searchData || []).filter(item => {
                                            return item && item.id && item.name;
                                        }).map(item => ({
                                            id: String(item.id),
                                            name: String(item.name)
                                        }));
                                        callback(validSearchData);
                                    })
                                    .catch(err => {
                                        console.error('Search error:', err);
                                        callback();
                                    });
                            },
                            loadThrottle: 300,
                            // ✅ FIX: Preload on focus to ensure options are available
                            onFocus: function() {
                                console.log('👆 Dropdown focused:', targetId);
                                // If we have initial data but no options loaded, load them
                                if (initialData.length > 0) {
                                    const currentOptionsCount = Object.keys(ts.options).length;
                                    if (currentOptionsCount === 0) {
                                        console.log('📋 Preloading options on focus...');
                                        initialData.forEach(item => {
                                            ts.addOption({
                                                id: String(item.id),
                                                name: String(item.name)
                                            }, true); // silent
                                        });
                                        ts.refreshOptions(false);
                                        console.log('✅ Options preloaded. Total:', Object.keys(ts.options).length);
                                    }
                                }
                            }
                        });

                        console.log('✅ TomSelect initialized for', targetId);
                        console.log('Initial data available:', initialData.length, 'items');
                        console.log('Native options count:', targetSelect.options.length);

                        // ✅ CRITICAL FIX: Add initial data to TomSelect IMMEDIATELY
                        // This ensures options are available when dropdown is clicked
                        if (initialData.length > 0) {
                            console.log('📋 Adding initial options to TomSelect...');
                            initialData.forEach(item => {
                                ts.addOption({
                                    id: String(item.id),
                                    name: String(item.name)
                                }, true); // true = silent (don't trigger events)
                            });

                            // ✅ FIX: Refresh to make options visible in dropdown
                            ts.refreshOptions(false);
                            console.log('✅ Options added and refreshed. Total in TomSelect:', Object.keys(ts.options).length);

                            // ✅ FIX: Also ensure dropdown menu is ready
                            setTimeout(() => {
                                if (ts.dropdown) {
                                    console.log('✅ Dropdown menu ready');
                                }
                            }, 100);
                        }

                        // ✅ FIX: Restore selected values AFTER options are added
                        if (selectedValues.length > 0) {
                            setTimeout(() => {
                                console.log('Restoring selected values:', selectedValues);
                                // Ensure options exist for selected values
                                selectedValues.forEach(val => {
                                    const valStr = String(val);
                                    if (!ts.options[valStr]) {
                                        // Try to find in initialData
                                        const option = initialData.find(item => String(item.id) === valStr);
                                        if (option) {
                                            ts.addOption({
                                                id: valStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                ts.setValue(selectedValues);
                                ts.refreshOptions(false);
                                console.log('✅ Selected values restored');
                            }, 100);
                        }

                        // Store instance
                        targetSelect.tomselect = ts;

                        // CRITICAL: Enable select (both native and TomSelect)
                        targetSelect.disabled = false;

                        // CRITICAL: Remove disabled class and ensure wrapper is clickable
                        if (ts && ts.wrapper) {
                            ts.wrapper.classList.remove('disabled');
                            ts.wrapper.style.pointerEvents = 'auto';
                            ts.wrapper.style.opacity = '1';

                            const input = ts.wrapper.querySelector('input');
                            if (input) {
                                input.disabled = false;
                                input.readOnly = false;
                                input.style.pointerEvents = 'auto';
                                input.style.cursor = 'text';
                            }

                            // Also check for control element (the clickable area)
                            const control = ts.wrapper.querySelector('.ts-control');
                            if (control) {
                                control.style.pointerEvents = 'auto';
                                control.style.cursor = 'pointer';
                            }
                        }

                        console.log('✅ TomSelect reinitialized for', targetId);
                        console.log('Select disabled:', targetSelect.disabled);
                        console.log('Wrapper disabled class:', ts.wrapper?.classList.contains('disabled'));
                        console.log('Wrapper pointer-events:', ts.wrapper?.style.pointerEvents);
                        console.log('Input disabled:', ts.wrapper?.querySelector('input')?.disabled);
                        console.log('Input readonly:', ts.wrapper?.querySelector('input')?.readOnly);

                        // Call callback if provided
                        if (callback) {
                            // Use setTimeout to ensure TomSelect is fully ready
                            setTimeout(() => {
                                callback(ts);
                            }, 50);
                        }
                    })
                    .catch(err => {
                        console.error('Error loading dropdown:', err);
                        targetSelect.disabled = false;
                        // Show error message
                        if (targetSelect.tomselect) {
                            targetSelect.tomselect.destroy();
                        }
                        targetSelect.innerHTML = '<option value="">Error loading data</option>';
                        const ts = new TomSelect(targetSelect, {
                            plugins: ['remove_button'],
                            maxItems: null,
                            placeholder: 'Error loading data',
                        });
                        targetSelect.tomselect = ts;
                    });
            }

            // Initialize TomSelect for a select element
            function initTomSelect(selectId, url = null, initialData = null) {
                const select = document.getElementById(selectId);
                if (!select) {
                    console.warn('initTomSelect: Select element not found:', selectId);
                    return null;
                }

                // CRITICAL: Enable select before initializing TomSelect
                select.disabled = false;

                // Destroy existing instance
                if (select.tomselect) {
                    select.tomselect.destroy();
                    select.tomselect = null;
                }

                // Get selected values from the select element before initializing TomSelect
                const selectedValues = Array.from(select.selectedOptions).map(opt => opt.value).filter(v => v);

                const options = {
                    plugins: ['remove_button'],
                    maxItems: null,
                    placeholder: 'Pilih atau ketik untuk mencari...',
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                };

                // ✅ FIX: Add initial data as native options FIRST
                if (initialData && initialData.length > 0) {
                    initialData.forEach(item => {
                        const option = document.createElement('option');
                        option.value = String(item.id);
                        option.textContent = String(item.name);
                        if (selectedValues.includes(String(item.id))) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                }

                // Add AJAX load function if URL provided
                if (url) {
                    options.load = function(query, callback) {
                        if (!query.length && initialData && initialData.length > 0) {
                            // Use initial data if no search query
                            console.log('Loading initial data (no query):', initialData);
                            return callback(initialData);
                        }
                        if (!query.length) {
                            // Return empty if no initial data
                            return callback();
                        }

                        const params = new URLSearchParams({ search: query });
                        fetch(url + '?' + params.toString())
                            .then(r => r.json())
                            .then(data => {
                                // Filter out invalid items
                                const validData = (data || []).filter(item => item && item.id && item.name);
                                callback(validData);
                            })
                            .catch(() => callback());
                    };
                    options.loadThrottle = 300;
                } else if (initialData && initialData.length > 0) {
                    // If no URL but we have initialData, add options directly
                    options.load = function(query, callback) {
                        if (!query.length) {
                            return callback(initialData);
                        }
                        // Filter initialData by search query
                        const filtered = initialData.filter(item =>
                            item.name && item.name.toLowerCase().includes(query.toLowerCase())
                        );
                        callback(filtered);
                    };
                }

                const ts = new TomSelect(select, options);
                select.tomselect = ts;

                // CRITICAL: Ensure TomSelect wrapper is not disabled
                if (ts.wrapper) {
                    ts.wrapper.classList.remove('disabled');
                    const input = ts.wrapper.querySelector('input');
                    if (input) {
                        input.disabled = false;
                        input.readOnly = false;
                    }
                }

                // ✅ CRITICAL FIX: Add initial data to TomSelect IMMEDIATELY if available
                // This ensures options are available when dropdown is clicked
                if (initialData && initialData.length > 0) {
                    console.log('📋 Adding initial data to TomSelect:', initialData.length, 'items');
                    initialData.forEach(item => {
                        ts.addOption({
                            id: String(item.id),
                            name: String(item.name)
                        }, true); // true = silent (don't trigger events)
                    });
                    ts.refreshOptions(false);
                    console.log('✅ Options added to TomSelect. Total:', Object.keys(ts.options).length);
                }

                // ✅ FIX: Preload options on focus (backup - after ts is created)
                ts.on('focus', function() {
                    console.log('👆 Dropdown focused:', selectId);
                    // If we have initial data but no options loaded, load them
                    if (initialData && initialData.length > 0) {
                        const currentOptionsCount = Object.keys(ts.options).length;
                        if (currentOptionsCount === 0) {
                            console.log('📋 Preloading options on focus...');
                            initialData.forEach(item => {
                                ts.addOption({
                                    id: String(item.id),
                                    name: String(item.name)
                                }, true);
                            });
                            ts.refreshOptions(false);
                            console.log('✅ Options preloaded. Total:', Object.keys(ts.options).length);
                        }
                    }
                });

                // Restore selected values after initialization
                if (selectedValues.length > 0) {
                    // Use setTimeout to ensure TomSelect is fully initialized
                    setTimeout(() => {
                        // Add options first if they don't exist
                        selectedValues.forEach(val => {
                            if (!ts.options[val]) {
                                // Try to find option in initialData or select element
                                const option = select.querySelector(`option[value="${val}"]`);
                                if (option) {
                                    ts.addOption({
                                        id: val,
                                        name: option.textContent
                                    });
                                }
                            }
                        });
                        ts.setValue(selectedValues);
                    }, 100);
                }

                console.log('✅ TomSelect initialized for:', selectId);
                console.log('Select disabled:', select.disabled);
                console.log('Wrapper disabled class:', ts.wrapper?.classList.contains('disabled'));

                return ts;
            }

            // ============================================
            // CASCADING FILTERS - CLEAN REIMPLEMENTATION
            // ============================================

            // Helper: Clear all child selects
            function clearChildSelects(parentId) {
                const cascadeMap = {
                    'brands': ['sub_brands', 'product_categories', 'product_types', 'product_sizes', 'product_variants'],
                    'sub_brands': ['product_categories', 'product_types', 'product_sizes', 'product_variants'],
                    'product_categories': ['product_types', 'product_sizes', 'product_variants'],
                    'product_types': ['product_sizes', 'product_variants'],
                    'product_sizes': ['product_variants']
                };

                const children = cascadeMap[parentId] || [];
                children.forEach(childId => {
                    const childSelect = document.getElementById(childId);
                    if (childSelect) {
                        childSelect.disabled = true;
                        if (childSelect.tomselect) {
                            childSelect.tomselect.clear();
                            childSelect.tomselect.clearOptions();
                            childSelect.tomselect.destroy();
                            childSelect.tomselect = null;
                        }
                        childSelect.innerHTML = '';
                    }
                });
            }

            // Setup cascade: Sub Brand → Product Category
            function setupSubBrandCascade(tsInstance) {
                if (!tsInstance) {
                    console.warn('setupSubBrandCascade: No instance provided');
                    return;
                }

                console.log('🔧 Setting up Sub Brand cascade with instance:', tsInstance);
                console.log('Instance wrapper:', tsInstance.wrapper);
                console.log('Instance input:', tsInstance.wrapper?.querySelector('input'));

                // CRITICAL: Remove existing listeners FIRST
                tsInstance.off('change');
                console.log('✅ Removed existing change listeners');

                // CRITICAL: Ensure instance is enabled
                const subBrandSelect = document.getElementById('sub_brands');
                if (subBrandSelect) {
                    subBrandSelect.disabled = false;
                }
                if (tsInstance.wrapper) {
                    tsInstance.wrapper.classList.remove('disabled');
                    const input = tsInstance.wrapper.querySelector('input');
                    if (input) {
                        input.disabled = false;
                        input.readOnly = false;
                    }
                }

                // Add new listener
                const changeHandler = debounce(function() {
                    // ✅ FIX: Skip cascade during initial load
                    if (isInitialLoad) {
                        console.log('⏸️ Skipping cascade during initial load');
                        return;
                    }

                    const subBrandIds = tsInstance.items || [];
                    const catSelect = document.getElementById('product_categories');

                    console.log('🔵 SUB BRAND CHANGED EVENT TRIGGERED!', subBrandIds);
                    console.log('Event triggered on instance:', tsInstance);
                    console.log('Items:', tsInstance.items);
                    console.log('This context:', this);

                    if (subBrandIds.length > 0) {
                        // Clear child
                        if (categoryTS) {
                            categoryTS.clear();
                            categoryTS.clearOptions();
                        }
                        catSelect.disabled = true;

                        // Fetch & rebuild
                        const subBrandIdsArray = Array.isArray(subBrandIds)
                            ? subBrandIds.map(id => String(id))
                            : [String(subBrandIds)];

                        console.log('Loading categories for sub brand IDs:', subBrandIdsArray);

                        reloadDropdown(config.getCategoriesUrl, { sub_brand_ids: subBrandIdsArray }, 'product_categories', (ts) => {
                            categoryTS = ts;
                            catSelect.disabled = false;
                            if (ts && ts.wrapper) {
                                ts.wrapper.classList.remove('disabled');
                                const input = ts.wrapper.querySelector('input');
                                if (input) input.disabled = false;
                            }
                            setupCategoryCascade(categoryTS);
                        });
                    } else {
                        catSelect.disabled = true;
                        if (categoryTS) {
                            categoryTS.clear();
                            categoryTS.clearOptions();
                        }
                        clearChildSelects('product_categories');
                    }
                }, 300);

                // ✅ FIX: Attach handler to TomSelect with proper verification
                tsInstance.on('change', changeHandler);
                console.log('✅ Change handler attached to TomSelect');

                // ✅ FIX: Verify handler is attached (TomSelect may store handlers differently)
                // Try multiple ways to verify
                let handlerCount = 0;
                if (tsInstance.handlers && tsInstance.handlers.change) {
                    handlerCount = tsInstance.handlers.change.length;
                } else if (tsInstance.eventHandlers && tsInstance.eventHandlers.change) {
                    handlerCount = tsInstance.eventHandlers.change.length;
                } else if (tsInstance._handlers && tsInstance._handlers.change) {
                    handlerCount = tsInstance._handlers.change.length;
                }

                if (handlerCount > 0) {
                    console.log('✅ Handler verified - count:', handlerCount);
                } else {
                    // Handler might be attached but not in handlers object (TomSelect internal)
                    // Test by checking if on method worked
                    console.log('⚠️ Handler not found in handlers object, but may be attached internally');
                    console.log('TomSelect instance methods:', Object.keys(tsInstance).filter(k => k.includes('handler') || k.includes('event')));
                }

                // ✅ FIX: Also listen to TomSelect add/remove events (more reliable than change)
                tsInstance.on('add', function(value) {
                    console.log('🟢 TOMSELECT ADD EVENT!', value);
                    if (!isInitialLoad) {
                        changeHandler.call(tsInstance);
                    }
                });

                tsInstance.on('remove', function(value) {
                    console.log('🟢 TOMSELECT REMOVE EVENT!', value);
                    if (!isInitialLoad) {
                        changeHandler.call(tsInstance);
                    }
                });

                // ✅ FIX: Also listen to item_add and item_remove (TomSelect specific events)
                if (tsInstance.on) {
                    tsInstance.on('item_add', function(value) {
                        console.log('🟢 TOMSELECT ITEM_ADD EVENT!', value);
                        if (!isInitialLoad) {
                            changeHandler.call(tsInstance);
                        }
                    });

                    tsInstance.on('item_remove', function(value) {
                        console.log('🟢 TOMSELECT ITEM_REMOVE EVENT!', value);
                        if (!isInitialLoad) {
                            changeHandler.call(tsInstance);
                        }
                    });
                }

                console.log('✅ All event listeners attached');
                console.log('✅ Sub Brand cascade setup complete');

                // ✅ FIX: Test manual trigger to verify handler works
                setTimeout(() => {
                    console.log('🧪 Testing Sub Brand handler...');
                    console.log('Current items:', tsInstance.items);
                    console.log('Instance ready:', !!tsInstance);
                    console.log('Has on method:', typeof tsInstance.on === 'function');
                    console.log('Has addItem method:', typeof tsInstance.addItem === 'function');

                    // Test if we can manually trigger (for debugging)
                    if (tsInstance.items && tsInstance.items.length > 0) {
                        console.log('✅ Sub Brand has selected items:', tsInstance.items);
                    } else {
                        console.log('ℹ️ Sub Brand has no selected items yet');
                    }
                }, 500);
            }

            // Setup cascade: Product Category → Product Type
            function setupCategoryCascade(tsInstance) {
                if (!tsInstance) {
                    console.warn('setupCategoryCascade: No instance provided');
                    return;
                }

                console.log('🔧 Setting up Category cascade');

                tsInstance.off('change');

                tsInstance.on('change', debounce(function() {
                    // ✅ FIX: Skip cascade during initial load
                    if (isInitialLoad) {
                        console.log('⏸️ Skipping cascade during initial load');
                        return;
                    }

                    const categoryIds = tsInstance.items || [];
                    const typeSelect = document.getElementById('product_types');

                    console.log('🔵 CATEGORY CHANGED!', categoryIds);

                    if (categoryIds.length > 0) {
                        if (typeTS) {
                            typeTS.clear();
                            typeTS.clearOptions();
                        }
                        typeSelect.disabled = true;

                        const categoryIdsArray = Array.isArray(categoryIds)
                            ? categoryIds.map(id => String(id))
                            : [String(categoryIds)];

                        reloadDropdown(config.getTypesUrl, { category_ids: categoryIdsArray }, 'product_types', (ts) => {
                            typeTS = ts;
                            typeSelect.disabled = false;
                            if (ts && ts.wrapper) {
                                ts.wrapper.classList.remove('disabled');
                                const input = ts.wrapper.querySelector('input');
                                if (input) input.disabled = false;
                            }
                            setupTypeCascade(typeTS);
                        });
                    } else {
                        typeSelect.disabled = true;
                        if (typeTS) {
                            typeTS.clear();
                            typeTS.clearOptions();
                        }
                        clearChildSelects('product_types');
                    }
                }, 300));
            }

            // Setup cascade: Product Type → Product Size
            function setupTypeCascade(tsInstance) {
                if (!tsInstance) {
                    console.warn('setupTypeCascade: No instance provided');
                    return;
                }

                console.log('🔧 Setting up Type cascade');

                tsInstance.off('change');

                tsInstance.on('change', debounce(function() {
                    // ✅ FIX: Skip cascade during initial load
                    if (isInitialLoad) {
                        console.log('⏸️ Skipping cascade during initial load');
                        return;
                    }

                    const typeIds = tsInstance.items || [];
                    const sizeSelect = document.getElementById('product_sizes');

                    console.log('🔵 TYPE CHANGED!', typeIds);

                    if (typeIds.length > 0) {
                        if (sizeTS) {
                            sizeTS.clear();
                            sizeTS.clearOptions();
                        }
                        sizeSelect.disabled = true;

                        const typeIdsArray = Array.isArray(typeIds)
                            ? typeIds.map(id => String(id))
                            : [String(typeIds)];

                        reloadDropdown(config.getSizesUrl, { type_ids: typeIdsArray }, 'product_sizes', (ts) => {
                            sizeTS = ts;
                            sizeSelect.disabled = false;
                            if (ts && ts.wrapper) {
                                ts.wrapper.classList.remove('disabled');
                                const input = ts.wrapper.querySelector('input');
                                if (input) input.disabled = false;
                            }
                            setupSizeCascade(sizeTS);
                        });
                    } else {
                        sizeSelect.disabled = true;
                        if (sizeTS) {
                            sizeTS.clear();
                            sizeTS.clearOptions();
                        }
                        clearChildSelects('product_sizes');
                    }
                }, 300));
            }

            // Setup cascade: Product Size → Product Variant
            function setupSizeCascade(tsInstance) {
                if (!tsInstance) {
                    console.warn('setupSizeCascade: No instance provided');
                    return;
                }

                console.log('🔧 Setting up Size cascade');

                tsInstance.off('change');

                tsInstance.on('change', debounce(function() {
                    // ✅ FIX: Skip cascade during initial load
                    if (isInitialLoad) {
                        console.log('⏸️ Skipping cascade during initial load');
                        return;
                    }

                    const sizeIds = tsInstance.items || [];
                    const variantSelect = document.getElementById('product_variants');

                    console.log('🔵 SIZE CHANGED!', sizeIds);

                    if (sizeIds.length > 0) {
                        if (variantTS) {
                            variantTS.clear();
                            variantTS.clearOptions();
                        }
                        variantSelect.disabled = true;

                        const sizeIdsArray = Array.isArray(sizeIds)
                            ? sizeIds.map(id => String(id))
                            : [String(sizeIds)];

                        reloadDropdown(config.getVariantsUrl, { size_ids: sizeIdsArray }, 'product_variants', (ts) => {
                            variantTS = ts;
                            variantSelect.disabled = false;
                            if (ts && ts.wrapper) {
                                ts.wrapper.classList.remove('disabled');
                                const input = ts.wrapper.querySelector('input');
                                if (input) input.disabled = false;
                            }
                        });
                    } else {
                        variantSelect.disabled = true;
                        if (variantTS) {
                            variantTS.clear();
                            variantTS.clearOptions();
                        }
                    }
                }, 300));
            }

            // Initialize cascading selects
            function initCascadingSelects() {
                // ✅ FIX: Set flag to prevent cascade during initial load
                isInitialLoad = true;

                // Initialize brands (no dependency)
                const brandsData = @json($brands->map(fn($b) => ['id' => $b->id, 'name' => $b->name]));
                brandTS = initTomSelect('brands', config.getBrandsUrl, brandsData);

                // Set selected values from URL if they exist
                @if(!empty($selectedBrands))
                    if (brandTS) {
                        brandTS.setValue(@json($selectedBrands));
                    }
                @endif

                // Setup cascade: Brand → Sub Brand
                if (brandTS) {
                    // Remove any existing listeners first
                    brandTS.off('change');

                    brandTS.on('change', debounce(function() {
                        const brandIds = brandTS.items || [];
                        const subBrandSelect = document.getElementById('sub_brands');

                        console.log('Brand changed:', brandIds);
                        console.log('Brand TS items type:', typeof brandIds, Array.isArray(brandIds));

                        if (brandIds && brandIds.length > 0) {
                            // Enable sub brand select FIRST before loading
                            subBrandSelect.disabled = false;
                            // Also enable TomSelect wrapper if it exists
                            if (subBrandTS && subBrandTS.wrapper) {
                                subBrandTS.wrapper.classList.remove('disabled');
                                const input = subBrandTS.wrapper.querySelector('input');
                                if (input) {
                                    input.disabled = false;
                                }
                            }
                            console.log('Sub brand select enabled');

                            // Ensure brandIds is an array of strings/numbers
                            const brandIdsArray = Array.isArray(brandIds)
                                ? brandIds.map(id => String(id))
                                : [String(brandIds)];

                            console.log('Loading sub brands for brand IDs:', brandIdsArray);
                            console.log('Using URL:', config.getSubBrandsUrl);

                            reloadDropdown(config.getSubBrandsUrl, { brand_ids: brandIdsArray }, 'sub_brands', (ts) => {
                                // ✅ FIX: Assign instance FIRST
                                subBrandTS = ts;
                                console.log('✅ Sub brand TS initialized:', subBrandTS);
                                console.log('Instance type:', typeof subBrandTS);
                                console.log('Instance has on method:', typeof subBrandTS.on);

                                // CRITICAL: Ensure select is enabled (both native and TomSelect)
                                subBrandSelect.disabled = false;
                                if (ts && ts.wrapper) {
                                    ts.wrapper.classList.remove('disabled');
                                    const input = ts.wrapper.querySelector('input');
                                    if (input) {
                                        input.disabled = false;
                                        input.readOnly = false;
                                        console.log('✅ Input enabled and not readonly');
                                    } else {
                                        console.warn('⚠️ Input not found in wrapper');
                                    }
                                } else {
                                    console.warn('⚠️ TomSelect wrapper not found');
                                }

                                console.log('✅ Sub brand select enabled after reload');
                                console.log('Native select disabled:', subBrandSelect.disabled);

                                // ✅ FIX: Setup cascade AFTER instance is assigned
                                // ✅ FIX: Pass instance explicitly to ensure correct reference
                                // ✅ FIX: Use setTimeout to ensure TomSelect is fully ready
                                setTimeout(() => {
                                    console.log('⏰ Setting up cascade after delay...');
                                    console.log('subBrandTS at setup time:', subBrandTS);
                                    if (subBrandTS) {
                                        setupSubBrandCascade(subBrandTS);

                                        // DEBUG: Verify setup worked
                                        setTimeout(() => {
                                            console.log('🔍 Verifying Sub Brand setup...');
                                            console.log('subBrandTS instance:', subBrandTS);
                                            console.log('Has on method:', typeof subBrandTS.on);
                                            console.log('Wrapper exists:', !!subBrandTS.wrapper);
                                            const input = subBrandTS.wrapper?.querySelector('input');
                                            console.log('Input exists:', !!input);
                                            console.log('Input disabled:', input?.disabled);
                                            console.log('Input readonly:', input?.readOnly);
                                        }, 200);
                                    } else {
                                        console.error('❌ subBrandTS is null!');
                                    }
                                }, 100);
                            });
                        } else {
                            console.log('No brands selected, disabling sub brands');
                            subBrandSelect.disabled = true;
                            if (subBrandTS) {
                                subBrandTS.clear();
                                subBrandTS.clearOptions();
                            }
                            // Cascade clear to children
                            clearChildSelects('sub_brands');
                        }
                    }, 300));
                }

                // Initialize sub brands if data exists or if brands are selected
                @if(!empty($selectedBrands) || !empty($selectedSubBrands))
                    const subBrandsData = @json($subBrands->map(fn($sb) => ['id' => $sb->id, 'name' => $sb->name]));
                    subBrandTS = initTomSelect('sub_brands', null, subBrandsData);
                    const subBrandSelectEl = document.getElementById('sub_brands');
                    subBrandSelectEl.disabled = false;
                    if (subBrandTS && subBrandTS.enable) {
                        subBrandTS.enable();
                    }
                    // ✅ FIX: Set selected values from URL AFTER options are added
                    @if(!empty($selectedSubBrands))
                        if (subBrandTS) {
                            // Wait for options to be added
                            setTimeout(() => {
                                const selectedSubBrands = @json($selectedSubBrands);
                                console.log('Setting Sub Brand values:', selectedSubBrands);
                                // Ensure options exist before setting value
                                selectedSubBrands.forEach(id => {
                                    const idStr = String(id);
                                    if (!subBrandTS.options[idStr]) {
                                        // Find option in data
                                        const option = subBrandsData.find(item => String(item.id) === idStr);
                                        if (option) {
                                            subBrandTS.addOption({
                                                id: idStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                // ✅ FIX: Set value without triggering change event during initial load
                                // Temporarily remove change handler, set value, then reattach
                                subBrandTS.off('change');
                                subBrandTS.setValue(selectedSubBrands);
                                subBrandTS.refreshOptions(false);
                                // Reattach handler after a short delay
                                setTimeout(() => {
                                    setupSubBrandCascade(subBrandTS);
                                }, 100);
                                console.log('✅ Sub Brand values set (without triggering cascade)');
                            }, 200);
                        }
                    @endif
                    // ✅ FIX: Setup cascade AFTER initialization with explicit instance
                    // ✅ FIX: Use setTimeout to ensure TomSelect is fully ready
                    setTimeout(() => {
                        console.log('Setting up Sub Brand cascade (initial load)...');
                        setupSubBrandCascade(subBrandTS);
                    }, 100);
                @else
                    // Initialize empty sub brands select (disabled by default)
                    subBrandTS = initTomSelect('sub_brands');
                    const subBrandSelectEl = document.getElementById('sub_brands');
                    // Keep disabled if no brands selected
                    if (subBrandSelectEl) {
                        subBrandSelectEl.disabled = true;
                        // Disable TomSelect wrapper
                        if (subBrandTS && subBrandTS.wrapper) {
                            subBrandTS.wrapper.classList.add('disabled');
                            const input = subBrandTS.wrapper.querySelector('input');
                            if (input) {
                                input.disabled = true;
                            }
                        }
                    }
                    // ✅ FIX: Setup cascade even if empty (will be enabled when brand is selected)
                    // ✅ FIX: Pass instance explicitly
                    // Note: Don't setup cascade if disabled - it will be setup when brand is selected
                    // if (subBrandTS) {
                    //     setupSubBrandCascade(subBrandTS);
                    // }
                @endif

                // Initialize product categories if data exists
                @if(!empty($selectedSubBrands) || !empty($selectedProductCategories))
                    const categoriesData = @json($productCategories->map(fn($cat) => ['id' => $cat->id, 'name' => $cat->name]));
                    categoryTS = initTomSelect('product_categories', null, categoriesData);
                    document.getElementById('product_categories').disabled = false;
                    // ✅ FIX: Set selected values from URL AFTER options are added
                    @if(!empty($selectedProductCategories))
                        if (categoryTS) {
                            setTimeout(() => {
                                const selectedCategories = @json($selectedProductCategories);
                                console.log('Setting Category values:', selectedCategories);
                                // Ensure options exist before setting value
                                selectedCategories.forEach(id => {
                                    const idStr = String(id);
                                    if (!categoryTS.options[idStr]) {
                                        const option = categoriesData.find(item => String(item.id) === idStr);
                                        if (option) {
                                            categoryTS.addOption({
                                                id: idStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                // ✅ FIX: Set value without triggering change event during initial load
                                categoryTS.off('change');
                                categoryTS.setValue(selectedCategories);
                                categoryTS.refreshOptions(false);
                                setTimeout(() => {
                                    setupCategoryCascade(categoryTS);
                                }, 100);
                                console.log('✅ Category values set (without triggering cascade)');
                            }, 200);
                        }
                    @endif
                @else
                    categoryTS = initTomSelect('product_categories');
                @endif

                // Setup cascade for categories if exists
                if (categoryTS) {
                    setupCategoryCascade(categoryTS);
                }

                // Initialize product types if data exists
                @if(!empty($selectedProductCategories) || !empty($selectedProductTypes))
                    const typesData = @json($productTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));
                    typeTS = initTomSelect('product_types', null, typesData);
                    document.getElementById('product_types').disabled = false;
                    // ✅ FIX: Set selected values from URL AFTER options are added
                    @if(!empty($selectedProductTypes))
                        if (typeTS) {
                            setTimeout(() => {
                                const selectedTypes = @json($selectedProductTypes);
                                console.log('Setting Type values:', selectedTypes);
                                selectedTypes.forEach(id => {
                                    const idStr = String(id);
                                    if (!typeTS.options[idStr]) {
                                        const option = typesData.find(item => String(item.id) === idStr);
                                        if (option) {
                                            typeTS.addOption({
                                                id: idStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                // ✅ FIX: Set value without triggering change event during initial load
                                typeTS.off('change');
                                typeTS.setValue(selectedTypes);
                                typeTS.refreshOptions(false);
                                setTimeout(() => {
                                    setupTypeCascade(typeTS);
                                }, 100);
                                console.log('✅ Type values set (without triggering cascade)');
                            }, 200);
                        }
                    @endif
                @else
                    typeTS = initTomSelect('product_types');
                @endif

                // Setup cascade for types if exists
                if (typeTS) {
                    setupTypeCascade(typeTS);
                }

                // Initialize product sizes if data exists
                @if(!empty($selectedProductTypes) || !empty($selectedProductSizes))
                    const sizesData = @json($productSizes->map(fn($s) => ['id' => $s->id, 'name' => $s->name]));
                    sizeTS = initTomSelect('product_sizes', null, sizesData);
                    document.getElementById('product_sizes').disabled = false;
                    // ✅ FIX: Set selected values from URL AFTER options are added
                    @if(!empty($selectedProductSizes))
                        if (sizeTS) {
                            setTimeout(() => {
                                const selectedSizes = @json($selectedProductSizes);
                                console.log('Setting Size values:', selectedSizes);
                                selectedSizes.forEach(id => {
                                    const idStr = String(id);
                                    if (!sizeTS.options[idStr]) {
                                        const option = sizesData.find(item => String(item.id) === idStr);
                                        if (option) {
                                            sizeTS.addOption({
                                                id: idStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                // ✅ FIX: Set value without triggering change event during initial load
                                sizeTS.off('change');
                                sizeTS.setValue(selectedSizes);
                                sizeTS.refreshOptions(false);
                                setTimeout(() => {
                                    setupSizeCascade(sizeTS);
                                }, 100);
                                console.log('✅ Size values set (without triggering cascade)');
                            }, 200);
                        }
                    @endif
                @else
                    sizeTS = initTomSelect('product_sizes');
                @endif

                // Setup cascade for sizes if exists
                if (sizeTS) {
                    setupSizeCascade(sizeTS);
                }

                // Initialize product variants if data exists
                @if(!empty($selectedProductSizes) || !empty($selectedProductVariants))
                    const variantsData = @json($productVariants->map(fn($v) => ['id' => $v->id, 'name' => $v->name]));
                    variantTS = initTomSelect('product_variants', null, variantsData);
                    document.getElementById('product_variants').disabled = false;
                    // ✅ FIX: Set selected values from URL AFTER options are added
                    @if(!empty($selectedProductVariants))
                        if (variantTS) {
                            setTimeout(() => {
                                const selectedVariants = @json($selectedProductVariants);
                                console.log('Setting Variant values:', selectedVariants);
                                selectedVariants.forEach(id => {
                                    const idStr = String(id);
                                    if (!variantTS.options[idStr]) {
                                        const option = variantsData.find(item => String(item.id) === idStr);
                                        if (option) {
                                            variantTS.addOption({
                                                id: idStr,
                                                name: option.name
                                            }, true);
                                        }
                                    }
                                });
                                // ✅ FIX: Set value without triggering change event during initial load
                                variantTS.off('change');
                                variantTS.setValue(selectedVariants);
                                variantTS.refreshOptions(false);
                                console.log('✅ Variant values set (without triggering cascade)');
                            }, 200);
                        }
                    @endif
                @else
                    variantTS = initTomSelect('product_variants');
                @endif

                // ✅ FIX: After all initializations complete, allow cascade
                setTimeout(() => {
                    isInitialLoad = false;
                    console.log('✅ Initial load complete, cascade enabled');
                }, 1000);
            }

            // Form submission
            document.getElementById('filter-form')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const params = new URLSearchParams();

                // Add basic form fields (non-TomSelect)
                const startDate = document.getElementById('start_date')?.value;
                const endDate = document.getElementById('end_date')?.value;
                const platformId = document.getElementById('platform_id')?.value;
                const orderNumber = document.getElementById('order_number')?.value;
                const search = document.getElementById('search')?.value;
                const sort = document.getElementById('sort')?.value;

                if (startDate) params.append('start_date', startDate);
                if (endDate) params.append('end_date', endDate);
                if (platformId) params.append('platform_id', platformId);
                if (orderNumber) params.append('order_number', orderNumber);
                if (search) params.append('search', search);
                if (sort) params.append('sort', sort);

                // Get values from TomSelect instances (for multiple selects)
                const tomSelectFields = {
                    'brands': brandTS,
                    'sub_brands': subBrandTS,
                    'product_categories': categoryTS,
                    'product_types': typeTS,
                    'product_sizes': sizeTS,
                    'product_variants': variantTS
                };

                // Add TomSelect values - use getValue() method which returns array
                Object.keys(tomSelectFields).forEach(fieldName => {
                    const ts = tomSelectFields[fieldName];
                    if (ts) {
                        // Use getValue() method which returns array of selected values
                        const values = ts.getValue();
                        if (values && Array.isArray(values) && values.length > 0) {
                            // Add each selected value
                            values.forEach(item => {
                                params.append(fieldName + '[]', String(item));
                            });
                        }
                    }
                });

                // Preserve quick_range if it exists in URL
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('quick_range')) {
                    params.set('quick_range', urlParams.get('quick_range'));
                }

                // Redirect with all parameters
                window.location.href = this.action + '?' + params.toString();
            });

            // Initial load
            document.addEventListener('DOMContentLoaded', function() {
                loadTable();
                initCascadingSelects();

                // Set default dates if empty
                const startDate = document.getElementById('start_date');
                const endDate = document.getElementById('end_date');
                if (startDate && !startDate.value) startDate.value = new Date().toISOString().split('T')[0];
                if (endDate && !endDate.value) endDate.value = new Date().toISOString().split('T')[0];
            });
        })();
    </script>
</body>
</html>
