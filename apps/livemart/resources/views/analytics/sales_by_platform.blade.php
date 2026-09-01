<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penjualan by Platform</title>

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

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }

        .table-dark th {
            background-color: var(--dark-color) !important;
            color: white !important;
            font-weight: 500;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Efek hover pada baris tabel */
        .table-row-hover {
            transition: all 0.2s ease;
        }

        .table-row-hover:hover {
            background-color: rgba(99, 102, 241, 0.04) !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        /* Platform box styling */
        .platform-box {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 120px;
            text-align: center;
            letter-spacing: 0.3px;
        }

        .platform-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 90px;
            text-align: center;
            letter-spacing: 0.3px;
            font-size: 0.9rem;
        }

        /* Platform specific colors */
        .platform-shopee {
            background-color: #ee4d2d;
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

        .platform-unknown {
            background-color: #adb5bd;
            color: white;
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

/* Table responsive */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        /* Add hover effect for platform badges in order list */
        .table-row-hover:hover .platform-badge {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }

        .table-row-hover .platform-badge {
            transition: transform 0.2s ease;
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
            <li class="breadcrumb-item active">Data Penjualan by Platform</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Data Penjualan by Platform</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.sales-by-platform') }}" id="filter-form" class="mb-4">
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

                    <!-- Sort Options -->
                    <div class="col-md-3">
                        <label for="sort" class="form-label">Urutkan Berdasarkan</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>
                                Value Tertinggi
                            </option>
                            <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>
                                Value Terendah
                            </option>
                            <option value="volume_highest" {{ $sortBy == 'volume_highest' ? 'selected' : '' }}>
                                Volume Tertinggi
                            </option>
                            <option value="volume_lowest" {{ $sortBy == 'volume_lowest' ? 'selected' : '' }}>
                                Volume Terendah
                            </option>
                            <option value="date_newest" {{ $sortBy == 'date_newest' ? 'selected' : '' }}>
                                Tanggal Terbaru
                            </option>
                            <option value="date_oldest" {{ $sortBy == 'date_oldest' ? 'selected' : '' }}>
                                Tanggal Terlama
                            </option>
                        </select>
                    </div>

                    <!-- Submit and Reset Button -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('analytics.sales-by-platform') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.sales-by-platform.export', request()->query()) }}" class="btn btn-success w-100">
                            <i class="bi bi-download"></i> Export Excel
                        </a>
                    </div>
                </div>
            </form>

            @if($summary['total_orders'] == 0)
            <div class="alert alert-info my-4">
                <h5 class="alert-heading">Tidak ada data</h5>
                <p>Tidak ditemukan data penjualan{{ $startDate && $endDate ? ' untuk periode '.$startDate.' sampai '.$endDate : '' }}.</p>
                @if($startDate && $endDate)
                <p>Silakan ubah filter tanggal atau platform untuk melihat data yang tersedia.</p>
                @endif
            </div>
            @else
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Pesanan</h5>
                            <h2 class="display-5">{{ number_format($summary['total_orders']) }}</h2>
                            <p>
                                @if($startDate && $endDate)
                                Dari {{ $startDate }} hingga {{ $endDate }}
                                @else
                                Semua Periode
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Retur</h5>
                            <h2 class="display-5">{{ number_format($summary['total_returns']) }}</h2>
                            <p>
                                @if($startDate && $endDate)
                                Dari {{ $startDate }} hingga {{ $endDate }}
                                @else
                                Semua Periode
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Value</h5>
                            <h2 class="display-5">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                            <p>Rata-rata: Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }} per order</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Volume</h5>
                            <h2 class="display-5">{{ number_format($summary['total_volume']) }} pcs</h2>
                            <p>Rata-rata: {{ number_format($summary['avg_order_volume'], 1) }} pcs per order</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Platform Summary -->
            <h5 class="mb-3">Ringkasan Penjualan Per Platform</h5>
            <div class="table-responsive disable-fixed-scrollbar mb-4" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Platform</th>
                            <th class="text-end">Jumlah Order</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">Avg Value/Order</th>
                            <th class="text-end">Total Volume</th>
                            <th class="text-end">Avg Volume/Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($platformSummary as $summary)
                        <tr class="table-row-hover">
                            <td>
                                <div class="platform-box platform-{{ strtolower(str_replace(' ', '-', $summary['platform'])) }}">
                                    {{ $summary['platform'] }}
                                </div>
                            </td>
                            <td class="text-end">{{ number_format($summary['order_count']) }}</td>
                            <td class="text-end">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($summary['total_volume']) }} pcs</td>
                            <td class="text-end">{{ number_format($summary['avg_order_volume'], 1) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data penjualan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Order List -->
            <h5 class="mb-3">Daftar Pesanan</h5>
            <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr class="text-center">
                            <th width="50">No</th>
                            <th>Tanggal</th>
                            <th>Order Number</th>
                            <th width="120">Platform</th>
                            <th class="text-end">Value</th>
                            <th class="text-end">Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr class="table-row-hover">
                            <td class="text-center">{{ ($orders->currentPage() - 1) * $orders->perPage() + $loop->iteration }}</td>
                            <td>{{ $order->tanggal ? $order->tanggal->format('d-m-Y') : 'N/A' }}</td>
                            <td><span class="fw-medium">{{ $order->order_number }}</span></td>
                            <td class="text-center">
                                <div class="platform-badge platform-{{ $order->platform ? strtolower(str_replace(' ', '-', $order->platform->name)) : 'unknown' }}">
                                    {{ $order->platform ? $order->platform->name : 'N/A' }}
                                </div>
                            </td>
                            <td class="text-end fw-medium">Rp {{ number_format($order->total_value, 0, ',', '.') }}</td>
                            <td class="text-end">
                                {{ number_format($order->total_volume) }} pcs
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data pesanan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if(method_exists($orders, 'links'))
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} data
                </div>
                <div>
                    {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Aktivasi tooltip
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Set default date to today if not already set
    document.addEventListener('DOMContentLoaded', function() {
        // Get date inputs
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
        if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('sales-by-platform')) {
            document.getElementById('filter-form').submit();
        }
    });
</script>
</body>
</html>