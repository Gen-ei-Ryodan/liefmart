<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Penjualan Master Internal</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

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

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .table-dark th {
            background-color: var(--dark-color) !important;
            color: white !important;
            font-weight: 500;
        }

        .table-row-even {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .table-row-odd {
            background-color: #fff;
        }

        .table-row-even:hover, .table-row-odd:hover {
            background-color: rgba(99, 102, 241, 0.04);
        }

        /* Card and Icon styles */
        .icon-circle {
            height: 3rem;
            width: 3rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle i {
            font-size: 1.5rem;
        }

        /* Platform box styling */
        .platform-box {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 80px;
            text-align: center;
            font-size: 0.8rem;
        }

        /* Platform specific colors */
        .platform-shopee {
            background-color: #EE4D2D;
            color: white;
        }
        .platform-tiktok {
            background-color: #000000;
            color: white;
        }

        .platform-offline {
            background-color: #6B7280;
            color: white;
        }

        .platform-unknown {
            background-color: #6c757d;
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

        /* Form styling */
        #filter-form {
            border-left: 4px solid var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table {
                font-size: 0.75rem;
            }

            .platform-box {
                min-width: 60px;
                font-size: 0.7rem;
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
            <li class="breadcrumb-item active">Penjualan Master Internal</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="m-0 font-weight-bold">Analytics Penjualan Master Internal</h5>
            <small class="text-white-50">Laporan penjualan master internal yang terjual melalui platform online</small>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.internal-product-sales') }}" id="filter-form" class="mb-5 p-3 bg-light rounded">
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
                            <option value="qty_highest" {{ $sortBy == 'qty_highest' ? 'selected' : '' }}>
                                Qty Tertinggi
                            </option>
                            <option value="qty_lowest" {{ $sortBy == 'qty_lowest' ? 'selected' : '' }}>
                                Qty Terendah
                            </option>
                            <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>
                                Value Tertinggi
                            </option>
                            <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>
                                Value Terendah
                            </option>
                            <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>
                                Nama A-Z
                            </option>
                            <option value="name_desc" {{ $sortBy == 'name_desc' ? 'selected' : '' }}>
                                Nama Z-A
                            </option>
                        </select>
                    </div>

                    <!-- Submit and Reset Button -->
                    <div class="col-md-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="{{ route('analytics.internal-product-sales') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                            <a href="#" data-export="{{ route('analytics.internal-product-sales.export', request()->query()) }}" class="btn btn-success">
                                <i class="bi bi-download"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Produk</h6>
                                    <h2 class="font-weight-bold mb-0">{{ number_format($summary['total_products']) }}</h2>
                                </div>
                                <div class="icon-circle bg-white text-primary">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                            <div class="mt-2 text-white-50 small">
                                <span>Jenis barang internal terjual</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Order</h6>
                                    <h2 class="font-weight-bold mb-0">{{ number_format($summary['total_orders']) }}</h2>
                                </div>
                                <div class="icon-circle bg-white text-info">
                                    <i class="bi bi-cart"></i>
                                </div>
                            </div>
                            <div class="mt-2 text-white-50 small">
                                <span>Order yang mengandung barang internal</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Value</h6>
                                    <h2 class="font-weight-bold mb-0">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                                </div>
                                <div class="icon-circle bg-white text-success">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                            <div class="mt-2 text-white-50 small">
                                <span>Total nilai penjualan</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-1">Total Qty</h6>
                                    <h2 class="font-weight-bold mb-0">{{ number_format($summary['total_qty']) }}</h2>
                                </div>
                                <div class="icon-circle bg-white text-warning">
                                    <i class="bi bi-box"></i>
                                </div>
                            </div>
                            <div class="mt-2 text-white-50 small">
                                <span>Unit barang internal terjual</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product List -->
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-dark sticky-top">
                        <tr class="text-center">
                            <th width="40">No</th>
                            <th width="120">SKU</th>
                            <th>Nama Barang Internal</th>
                            <th width="100">Jumlah Order</th>
                            <th width="120">Total Qty</th>
                            <th width="150">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $no = ($products->currentPage() - 1) * $products->perPage() + 1;
                        @endphp

                        @forelse($products as $product)
                            <tr class="{{ $no % 2 == 0 ? 'table-row-even' : 'table-row-odd' }}">
                                <td class="text-center">{{ $no++ }}</td>

                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $product->product_sku }}</span>
                                </td>

                                <td>
                                    <strong>{{ $product->product_name }}</strong>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-info text-white">{{ number_format($product->order_count) }} order</span>
                                </td>

                                <td class="text-center fw-medium">
                                    {{ number_format($product->total_qty) }} pcs
                                </td>

                                <td class="text-end fw-bold">
                                    Rp {{ number_format($product->total_value, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">Tidak ada data penjualan master internal</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if(method_exists($products, 'links'))
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} data
                </div>
                <div>
                    {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Set default date to today if not already set
    document.addEventListener('DOMContentLoaded', function() {
        // Get date inputs
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        // Get today's date in YYYY-MM-DD format
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayFormatted = `${year}-${month}-${day}`;

        // Set default values if empty
        if (!startDateInput.value) {
            startDateInput.value = todayFormatted;
        }

        if (!endDateInput.value) {
            endDateInput.value = todayFormatted;
        }

        // If URL doesn't have date parameters, submit the form with today's date
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('internal-product-sales')) {
            document.getElementById('filter-form').submit();
        }
    });
</script>
</body>
</html>

