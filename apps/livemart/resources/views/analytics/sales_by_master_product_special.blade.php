@extends('layouts.app')

@section('title', 'Gross Profit Average per Master Produk')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="bi bi-graph-up me-2"></i>Gross Profit Average per Master Produk
                    </h5>
                </div>
            <div class="card-body">
                <!-- Quick Date Range Filters -->
                <div class="mb-4">
                    <h6 class="mb-2 fw-bold"><i class="bi bi-calendar3 me-2"></i>Filter Cepat:</h6>
                    <div class="btn-group" role="group">
                        <a href="{{ route('analytics.sales-by-master-product-special', ['quick_range' => '7days'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '7days' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-week me-1"></i> 7 Hari
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product-special', ['quick_range' => '2weeks'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '2weeks' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar2-week me-1"></i> 2 Minggu
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product-special', ['quick_range' => '1month'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '1month' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-month me-1"></i> 1 Bulan
                        </a>
                        <a href="{{ route('analytics.sales-by-master-product-special', ['quick_range' => '3months'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '3months' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar3-range me-1"></i> 3 Bulan
                        </a>
                        @if(request('quick_range'))
                            <a href="{{ route('analytics.sales-by-master-product-special', request()->except(['quick_range', 'start_date', 'end_date'])) }}"
                               class="btn btn-outline-danger">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" action="{{ route('analytics.sales-by-master-product-special') }}" id="filter-form" class="card mb-4">
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
                                    <select class="form-select" id="sub_brands" name="sub_brands[]" multiple {{ empty($selectedBrands) && empty($selectedSubBrands) ? 'disabled' : '' }}>
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
                                <a href="{{ route('analytics.sales-by-master-product-special') }}" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="#" class="btn btn-success w-100" onclick="alert('Export Excel akan segera tersedia'); return false;">
                                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Active Filters Display -->
                @if(request()->hasAny(['start_date', 'end_date', 'platform_id', 'order_number', 'search', 'brands', 'sub_brands', 'product_categories', 'product_types', 'product_sizes', 'product_variants']))
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
</div>
    </div>

    <!-- Modal Container (Lazy loaded) -->
    <div id="modal-container"></div>

@endsection

@section('scripts')
    <script>
        (function() {
            'use strict';

            // Configuration - use route helper (routes are in analytics prefix group)
            const config = {
                tableUrl: '{{ route("analytics.sales-by-master-product-special.table") }}',
                modalUrl: '{{ route("analytics.sales-by-master-product-special.modal") }}',
                getBrandsUrl: '{{ route("analytics.get-brands") }}',
                getSubBrandsUrl: '{{ route("analytics.get-subbrands") }}',
                getCategoriesUrl: '{{ route("analytics.get-product-categories") }}',
                getTypesUrl: '{{ route("analytics.get-product-types") }}',
                getSizesUrl: '{{ route("analytics.get-product-sizes") }}',
                getVariantsUrl: '{{ route("analytics.get-product-variants") }}'
            };

            // Fallback to direct URLs if routes fail
            if (!config.tableUrl || config.tableUrl.includes('analytics.sales-by-master-product-special.table')) {
                const base = window.location.origin + '/analytics';
                config.tableUrl = base + '/sales-by-master-product-special/table';
                config.modalUrl = base + '/sales-by-master-product-special/modal';
                config.getBrandsUrl = base + '/get-brands';
                config.getSubBrandsUrl = base + '/sales-by-master-product-special/subbrands';
                config.getCategoriesUrl = base + '/sales-by-master-product-special/product-categories';
                config.getTypesUrl = base + '/sales-by-master-product-special/product-types';
                config.getSizesUrl = base + '/sales-by-master-product-special/product-sizes';
                config.getVariantsUrl = base + '/sales-by-master-product-special/product-variants';
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

            // TomSelect instances
            let brandTS, subBrandTS, categoryTS, typeTS, sizeTS, variantTS;

            // Reusable function to reload dropdown via AJAX
            function reloadDropdown(url, payload, targetId, callback = null) {
                const targetSelect = document.getElementById(targetId);
                if (!targetSelect) return;

                // Show loading state
                targetSelect.disabled = true;

                // Build query string
                const params = new URLSearchParams();
                Object.keys(payload).forEach(key => {
                    if (Array.isArray(payload[key])) {
                        payload[key].forEach(val => params.append(key, val));
                    } else if (payload[key]) {
                        params.append(key, payload[key]);
                    }
                });

                fetch(url + '?' + params.toString())
                    .then(r => r.json())
                    .then(data => {
                        // Clear existing options except selected ones
                        const selectedValues = targetSelect.tomselect ? targetSelect.tomselect.items : [];
                        const optionsToKeep = Array.from(targetSelect.options).filter(opt => selectedValues.includes(opt.value));

                        // Destroy TomSelect if exists
                        if (targetSelect.tomselect) {
                            targetSelect.tomselect.destroy();
                        }

                        // Clear and rebuild options
                        targetSelect.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(item => {
                                const option = document.createElement('option');
                                option.value = item.id;
                                option.textContent = item.name;
                                if (selectedValues.includes(String(item.id))) {
                                    option.selected = true;
                                }
                                targetSelect.appendChild(option);
                            });
                        }

                        // Reinitialize TomSelect
                        const ts = new TomSelect(targetSelect, {
                            plugins: ['remove_button'],
                            maxItems: null,
                            placeholder: 'Pilih atau ketik untuk mencari...',
                            valueField: 'id',
                            labelField: 'name',
                            searchField: 'name',
                            load: function(query, loadCallback) {
                                if (!query.length) return loadCallback();

                                const searchParams = new URLSearchParams(payload);
                                searchParams.set('search', query);

                                fetch(url + '?' + searchParams.toString())
                                    .then(r => r.json())
                                    .then(searchData => loadCallback(searchData))
                                    .catch(() => loadCallback());
                            },
                            loadThrottle: 300
                        });

                        // Restore selected values
                        if (selectedValues.length > 0) {
                            ts.setValue(selectedValues);
                        }

                        // Store instance
                        targetSelect.tomselect = ts;

                        // Enable select
                        targetSelect.disabled = false;

                        // Call callback if provided
                        if (callback) callback(ts);
                    })
                    .catch(err => {
                        console.error('Error loading dropdown:', err);
                        targetSelect.disabled = false;
                    });
            }

            // Initialize TomSelect for a select element
            function initTomSelect(selectId, url = null, initialData = null) {
                const select = document.getElementById(selectId);
                if (!select) return null;

                // Destroy existing instance
                if (select.tomselect) {
                    select.tomselect.destroy();
                }

                const options = {
                    plugins: ['remove_button'],
                    maxItems: null,
                    placeholder: 'Pilih atau ketik untuk mencari...',
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                };

                // Add AJAX load function if URL provided
                if (url) {
                    options.load = function(query, callback) {
                        if (!query.length && initialData) {
                            // Use initial data if no search query
                            return callback(initialData);
                        }
                        if (!query.length) return callback();

                        const params = new URLSearchParams({ search: query });
                        fetch(url + '?' + params.toString())
                            .then(r => r.json())
                            .then(data => callback(data))
                            .catch(() => callback());
                    };
                    options.loadThrottle = 300;
                }

                const ts = new TomSelect(select, options);
                select.tomselect = ts;
                return ts;
            }

            // Initialize cascading selects
            function initCascadingSelects() {
                // Initialize brands (no dependency)
                const brandsData = @json($brands->map(fn($b) => ['id' => $b->id, 'name' => $b->name]));
                brandTS = initTomSelect('brands', config.getBrandsUrl, brandsData);

                // Setup cascade: Brand → Sub Brand
                if (brandTS) {
                    brandTS.on('change', debounce(() => {
                        const brandIds = brandTS.items;
                        const subBrandSelect = document.getElementById('sub_brands');

                        if (brandIds.length > 0) {
                            subBrandSelect.disabled = false;
                            reloadDropdown(config.getSubBrandsUrl, { brand_ids: brandIds }, 'sub_brands', (ts) => {
                                subBrandTS = ts;
                            });
                        } else {
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
                    document.getElementById('sub_brands').disabled = false;
                @else
                    // Initialize empty sub brands select
                    subBrandTS = initTomSelect('sub_brands');
                @endif

                // Setup cascade: Sub Brand → Product Category (always set up)
                if (subBrandTS) {
                    subBrandTS.on('change', debounce(() => {
                        const subBrandIds = subBrandTS.items;
                        const catSelect = document.getElementById('product_categories');

                        if (subBrandIds.length > 0) {
                            catSelect.disabled = false;
                            reloadDropdown(config.getCategoriesUrl, { sub_brand_ids: subBrandIds }, 'product_categories', (ts) => {
                                categoryTS = ts;
                                setupCategoryCascade();
                            });
                        } else {
                            catSelect.disabled = true;
                            if (categoryTS) {
                                categoryTS.clear();
                                categoryTS.clearOptions();
                            }
                            clearChildSelects('product_categories');
                        }
                    }, 300));
                }

                // Initialize product categories if data exists
                @if(!empty($selectedSubBrands) || !empty($selectedProductCategories))
                    const categoriesData = @json($productCategories->map(fn($cat) => ['id' => $cat->id, 'name' => $cat->name]));
                    categoryTS = initTomSelect('product_categories', null, categoriesData);
                    document.getElementById('product_categories').disabled = false;
                @else
                    categoryTS = initTomSelect('product_categories');
                @endif

                // Setup cascade: Product Category → Product Type
                function setupCategoryCascade() {
                    if (categoryTS) {
                        categoryTS.on('change', debounce(() => {
                            const categoryIds = categoryTS.items;
                            const typeSelect = document.getElementById('product_types');

                            if (categoryIds.length > 0) {
                                typeSelect.disabled = false;
                                reloadDropdown(config.getTypesUrl, { category_ids: categoryIds }, 'product_types', (ts) => {
                                    typeTS = ts;
                                    setupTypeCascade();
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
                }
                if (categoryTS) setupCategoryCascade();

                // Initialize product types if data exists
                @if(!empty($selectedProductCategories) || !empty($selectedProductTypes))
                    const typesData = @json($productTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));
                    typeTS = initTomSelect('product_types', null, typesData);
                    document.getElementById('product_types').disabled = false;
                @else
                    typeTS = initTomSelect('product_types');
                @endif

                // Setup cascade: Product Type → Product Size
                function setupTypeCascade() {
                    if (typeTS) {
                        typeTS.on('change', debounce(() => {
                            const typeIds = typeTS.items;
                            const sizeSelect = document.getElementById('product_sizes');

                            if (typeIds.length > 0) {
                                sizeSelect.disabled = false;
                                reloadDropdown(config.getSizesUrl, { type_ids: typeIds }, 'product_sizes', (ts) => {
                                    sizeTS = ts;
                                    setupSizeCascade();
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
                }
                if (typeTS) setupTypeCascade();

                // Initialize product sizes if data exists
                @if(!empty($selectedProductTypes) || !empty($selectedProductSizes))
                    const sizesData = @json($productSizes->map(fn($s) => ['id' => $s->id, 'name' => $s->name]));
                    sizeTS = initTomSelect('product_sizes', null, sizesData);
                    document.getElementById('product_sizes').disabled = false;
                @else
                    sizeTS = initTomSelect('product_sizes');
                @endif

                // Setup cascade: Product Size → Product Variant
                function setupSizeCascade() {
                    if (sizeTS) {
                        sizeTS.on('change', debounce(() => {
                            const sizeIds = sizeTS.items;
                            const variantSelect = document.getElementById('product_variants');

                            if (sizeIds.length > 0) {
                                variantSelect.disabled = false;
                                reloadDropdown(config.getVariantsUrl, { size_ids: sizeIds }, 'product_variants', (ts) => {
                                    variantTS = ts;
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
                }
                if (sizeTS) setupSizeCascade();

                // Initialize product variants if data exists
                @if(!empty($selectedProductSizes) || !empty($selectedProductVariants))
                    const variantsData = @json($productVariants->map(fn($v) => ['id' => $v->id, 'name' => $v->name]));
                    variantTS = initTomSelect('product_variants', null, variantsData);
                    document.getElementById('product_variants').disabled = false;
                @else
                    variantTS = initTomSelect('product_variants');
                @endif
            }

            // Helper function to clear child selects
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
                        }
                    }
                });
            }

            // Form submission
            document.getElementById('filter-form')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const params = new URLSearchParams(formData);
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
@endsection
