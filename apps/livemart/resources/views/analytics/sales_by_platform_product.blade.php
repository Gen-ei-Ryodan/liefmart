<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gross Profit per Produk Platform</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #0bb4aa;
            --info-color: #4cc9f0;
            --warning-color: #f72585;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container-fluid {
            padding: 20px;
            max-width: 1440px;
            margin: 0 auto;
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }

        .card-body {
            padding: 20px;
        }

        .table-dark th {
            background-color: var(--dark-color) !important;
            color: white !important;
            font-weight: 500;
        }

        /* Make the table header sticky */
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: #212529;
        }

        /* Make the hover effect more visible */
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.075);
        }

        /* Improve badge appearance */
        .badge {
            font-size: 0.8rem;
            font-weight: normal;
            border: 1px solid #dee2e6;
        }

        /* Summary cards */
        .summary-card {
            border-radius: 10px;
            color: white;
            height: 100%;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .bg-success {
            background-color: var(--success-color) !important;
        }

        .bg-info {
            background-color: var(--info-color) !important;
        }

        .bg-dark {
            background-color: var(--dark-color) !important;
        }

        /* Platform badges */
        .platform-box {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        .platform-shopee {
            background-color: #f53d2d;
            color: white;
        }

        .platform-tiktok {
            background-color: #000000;
            color: white;
        }

        .platform-offline {
            background-color: #6c757d;
            color: white;
        }

        /* Chart container */
        .chart-container {
            position: relative;
            margin: 20px 0;
            height: 300px;
        }

        /* Breadcrumb */
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

/* Custom display text */
        .display-5 {
            font-size: 2.5rem;
            font-weight: 600;
        }

        /* Small device adjustments */
        @media (max-width: 768px) {
            .container-fluid {
                padding: 15px;
            }

            .display-5 {
                font-size: 2rem;
            }

            .card-body {
                padding: 15px;
            }

            .btn-group {
                flex-wrap: wrap;
            }

            .btn-group .btn {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item">Analytics</li>
                <li class="breadcrumb-item active">Gross Profit per Produk Platform</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Gross Profit per Produk Platform</h5>
            </div>
            <div class="card-body">
                <!-- Quick Date Range Filters -->
                <div class="mb-4">
                    <h6 class="mb-2 fw-bold"><i class="bi bi-calendar3 me-2"></i>Filter Cepat:</h6>
                    <div class="btn-group" role="group" aria-label="Quick date filters">
                        <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '7days'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '7days' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-week me-1"></i> 7 Hari Terakhir
                        </a>
                        <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '2weeks'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '2weeks' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar2-week me-1"></i> 2 Minggu Terakhir
                        </a>
                        <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '1month'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '1month' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar-month me-1"></i> 1 Bulan Terakhir
                        </a>
                        <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '3months'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                           class="btn {{ request('quick_range') == '3months' ? 'btn-primary' : 'btn-outline-primary' }}">
                            <i class="bi bi-calendar3-range me-1"></i> 3 Bulan Terakhir
                        </a>
                        @if(request('quick_range'))
                            <a href="{{ route('analytics.sales-by-platform-product', request()->except(['quick_range', 'start_date', 'end_date'])) }}"
                               class="btn btn-outline-danger">
                                <i class="bi bi-x-circle"></i> Reset Filter Cepat
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" action="{{ route('analytics.sales-by-platform-product') }}" id="filter-form" class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Filter Custom</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <!-- Date Range -->
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Tanggal Mulai</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                    value="{{ $startDate }}">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                    value="{{ $endDate }}">
                            </div>

                            <!-- Platform Filter -->
                            <div class="col-md-3">
                                <label for="platform_id" class="form-label">Platform</label>
                                <select class="form-select" id="platform_id" name="platform_id">
                                    <option value="">Semua Platform</option>
                                    @foreach($platforms as $platform)
                                        <option value="{{ $platform->id }}"
                                            {{ $selectedPlatform == $platform->id ? 'selected' : '' }}>
                                            {{ $platform->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Search -->
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari Produk Platform</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Cari nama produk platform" value="{{ request('search') }}">
                            </div>
                            <!-- Search No Order -->
                            <div class="col-md-3">
                                <label for="order_number" class="form-label">Cari No Order</label>
                                <input type="text" class="form-control" id="order_number" name="order_number"
                                    placeholder="Cari nomor order" value="{{ request('order_number') }}">
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse"
                                        data-bs-target="#advancedFilters">
                                    <i class="bi bi-funnel me-1"></i> Filter Lanjutan
                                </button>
                            </div>
                        </div>

                        <div class="collapse {{ request()->hasAny(['sort']) ? 'show' : '' }}" id="advancedFilters">
                            <div class="row mt-3 g-3">
                                <!-- Sort Options -->
                                <div class="col-md-6">
                                    <label for="sort" class="form-label">Urutkan Berdasarkan</label>
                                    <select class="form-select" id="sort" name="sort">
                                        <option value="revenue_highest" {{ $sortBy == 'revenue_highest' ? 'selected' : '' }}>
                                            Saldo Masuk Tertinggi
                                        </option>
                                        <option value="revenue_lowest" {{ $sortBy == 'revenue_lowest' ? 'selected' : '' }}>
                                            Saldo Masuk Terendah
                                        </option>
                                        <option value="profit_highest" {{ $sortBy == 'profit_highest' ? 'selected' : '' }}>
                                            Profit Tertinggi
                                        </option>
                                        <option value="profit_lowest" {{ $sortBy == 'profit_lowest' ? 'selected' : '' }}>
                                            Profit Terendah
                                        </option>
                                        <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>
                                            Kuantitas Tertinggi
                                        </option>
                                        <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>
                                            Kuantitas Terendah
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ route('analytics.sales-by-platform-product') }}" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="#" data-export="{{ route('analytics.sales-by-platform-product.export', request()->all()) }}" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Active Filters Display -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <h6 class="fw-bold mb-3"><i class="bi bi-funnel-fill me-2"></i>Filter Aktif:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-2">
                                <span class="fw-semibold">Periode:</span>
                                {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                                @if($startDate != $endDate)
                                 - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <span class="fw-semibold">Platform:</span>
                                @if($selectedPlatform)
                                    @php
                                        $platformName = $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown';
                                    @endphp
                                    <span class="platform-box platform-{{ strtolower(str_replace(' ', '-', $platformName)) }}">
                                        {{ $platformName }}
                                    </span>
                                @else
                                    Semua Platform
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            @if(request()->hasAny(['start_date', 'end_date', 'platform_id', 'search', 'sort']))
                                <a href="{{ route('analytics.sales-by-platform-product') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Reset Semua Filter
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-4">
                    <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Informasi Data</h5>
                    <p class="mb-0">
                        Data yang ditampilkan mencakup <strong>semua transaksi</strong> dari platform:
                        <span class="platform-badge platform-shopee">Shopee</span>
                        <span class="platform-badge platform-tiktok">TikTok</span>
                    </p>
                    <hr>
                    <p class="mb-0">
                        <strong>Catatan:</strong>
                        <ul class="mb-0">
                            <li>Untuk transaksi yang belum memiliki catatan pembayaran, nilai <strong>Saldo Masuk</strong> dihitung berdasarkan harga produk.</li>
                            <li><strong>Jumlah Masuk Pembayaran</strong> dihitung secara proporsional per produk berdasarkan harga produk. Misalnya, jika Order berisi Produk A (Rp 70.000) dan Produk B (Rp 50.000), dan total saldo masuk Rp 100.000, maka Produk A mendapat Rp 58.333 (58.33%) dan Produk B mendapat Rp 41.667 (41.67%).</li>
                        </ul>
                    </p>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Jumlah Pesanan (Setelah Retur)</h5>
                                <h2 class="display-5">{{ number_format($summary['total_platform_products_after_returns']) }}</h2>
                                <p>dari {{ number_format($summary['total_platform_products']) }} pesanan ({{ number_format($summary['total_platform_products'] - $summary['total_platform_products_after_returns']) }} retur)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Saldo Masuk</h5>
                                <h2 class="display-5">Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</h2>
                                <p>Uang Masuk</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Modal</h5>
                                <h2 class="display-5">Rp {{ number_format($summary['total_capital'], 0, ',', '.') }}</h2>
                                <p>Modal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-dark summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Gross Profit</h5>
                                <h2 class="display-5">Rp {{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</h2>
                                <p>Margin: {{ number_format($summary['profit_margin'], 2) }}%</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($platformProductRows) > 0)
                    <!-- Calculation Method Info -->
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Informasi Perhitungan</h5>
                        <p>
                            <strong>Keterangan Kolom:</strong>
                        </p>
                        <ul>
                            <li><strong>Jumlah Masuk Pembayaran (per Produk):</strong> Uang yang masuk ke rekening dari penjualan dibagi secara proporsional berdasarkan harga produk (dari finance)</li>
                            <li><strong>Jumlah Masuk Pembayaran - PPN (per Produk):</strong> Jumlah masuk pembayaran per produk ÷ 1.11</li>
                            <li><strong>Harga Modal Total (COGS):</strong> Total harga beli setiap produk dalam 1 no order</li>
                            <li><strong>Gross Profit per Produk (%):</strong> (Gross profit per produk ÷ jumlah masuk pembayaran-PPN per produk) × 100%</li>
                            <li><strong>Margin per Produk (Rp):</strong> Jumlah masuk pembayaran-PPN per produk - harga modal total</li>
                            <li><strong>Margin per Produk (%):</strong> (Margin per produk ÷ jumlah masuk pembayaran-PPN per produk) × 100%</li>
                            <li><strong>Gross Profit per Order (Rp):</strong> Total semua gross profit produk dalam 1 order</li>
                            <li><strong>Margin per Order (%):</strong> (Total margin per order ÷ total jumlah masuk pembayaran-PPN per order) × 100%</li>
                        </ul>
                    </div>

                    <!-- Platform Products Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 4%;">Tanggal</th>
                                    <th style="width: 6%;">No Pesanan</th>
                                    <th style="width: 6%;">No Invoice</th>
                                    <th style="width: 40%; min-width: 400px;">Nama Produk (Platform)</th>
                                    <th style="width: 7%;">Variasi (Platform)</th>
                                    <th class="text-end" style="width: 3%;">QTY</th>
                                    <th class="text-end" style="width: 5%;">Jumlah Masuk Pembayaran per Produk (Rp)</th>
                                    <th class="text-end" style="width: 5%;">Jumlah Masuk Pembayaran - PPN per Produk (Rp)</th>
                                    <th class="text-end" style="width: 5%;">Harga Modal Total (COGS) (Rp)</th>
                                    <th class="text-end" style="width: 4%;">Gross Profit per Produk (%)</th>
                                    <th class="text-end" style="width: 5%;">Margin per Produk (Rp)</th>
                                    <th class="text-end" style="width: 4%;">Margin per Produk (%)</th>
                                    <th class="text-end" style="width: 5%;">Gross Profit per Order (Rp)</th>
                                    <th class="text-end" style="width: 5%;">Margin per Order (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($platformProductRows as $row)
                                    @php
                                        $rowClass = '';
                                        $grossProfitPerProduct = $row['gross_profit_per_product_rp'] ?? 0;
                                        if($grossProfitPerProduct < 0) {
                                            $rowClass = 'table-danger';
                                        }
                                        if(($row['revenue'] ?? 0) == 0) {
                                            $rowClass = 'table-warning';
                                        }
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td>{{ \Carbon\Carbon::parse($row['order_date'])->format('d/m/Y') }}</td>
                                        <td>{{ $row['order_number'] }}</td>
                                        <td>{{ $row['invoice_number'] ?? '-' }}</td>
                                        <td>
                                            <strong>{{ $row['platform_product_name'] }}</strong>
                                            @if($row['has_multiple_items'])
                                                <span class="badge bg-info ms-1" title="Order berisi multiple item">
                                                    <i class="bi bi-boxes"></i> Multi-item
                                                </span>
                                            @endif
                                        </td>
                                        <td>{{ $row['product_variant'] ?? '-' }}</td>
                                        <td class="text-end">{{ number_format($row['quantity'], 0) }}</td>
                                        <td class="text-end">{{ number_format($row['revenue'] ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($row['revenue_without_ppn'] ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($row['capital'] ?? 0, 0, ',', '.') }}</td>
                                        <td class="text-end">
                                            {{ number_format($row['gross_profit_per_product_percent'] ?? 0, 1) }}%
                                        </td>
                                        <td class="text-end fw-bold {{ ($row['margin_per_product_rp'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($row['margin_per_product_rp'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="text-end">
                                            {{ number_format($row['margin_per_product_percent'] ?? 0, 1) }}%
                                        </td>
                                        <td class="text-end fw-bold">
                                            {{ number_format($row['gross_profit_per_order_rp'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="text-end fw-bold">
                                            {{ number_format($row['margin_per_order_percent'] ?? 0, 1) }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <td colspan="5">
                                        <strong>TOTAL ({{ number_format($summary['total_rows']) }} rows, {{ number_format($summary['total_platform_products']) }} pesanan)</strong>
                                    </td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_quantity']) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_revenue'], 0, ',', '.') }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_revenue_without_ppn'], 0, ',', '.') }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_capital'], 0, ',', '.') }}</strong></td>
                                    <td class="text-end">
                                        @php
                                            $totalGrossProfitPercent = $summary['total_revenue_without_ppn'] > 0 ? ($summary['total_gross_profit'] / $summary['total_revenue_without_ppn']) * 100 : 0;
                                        @endphp
                                        <strong>{{ number_format($totalGrossProfitPercent, 1) }}%</strong>
                                    </td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalGrossProfitPercent, 1) }}%</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalGrossProfitPercent, 1) }}%</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted small">
                            Menampilkan {{ $platformProductRows->firstItem() ?? 0 }} - {{ $platformProductRows->lastItem() ?? 0 }} dari {{ number_format($summary['total_rows']) }} data ({{ number_format($summary['total_platform_products']) }} pesanan)
                        </div>
                        <div>
                            {{ $platformProductRows->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Tidak ada data yang tersedia untuk kriteria filter yang dipilih.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enable tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            // Set default date to today if not already set
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            // Get today's date in YYYY-MM-DD format
            const today = new Date();
            const todayFormatted = getTodayYYYYMMDD();

            // Set default values if empty
            if (!startDateInput.value) {
                startDateInput.value = todayFormatted;
            }

            if (!endDateInput.value) {
                endDateInput.value = todayFormatted;
            }

            // If URL doesn't have date parameters, submit the form with today's date
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('sales-by-platform-product')) {
                document.getElementById('filter-form').submit();
            }
        });
    </script>
</body>
</html>