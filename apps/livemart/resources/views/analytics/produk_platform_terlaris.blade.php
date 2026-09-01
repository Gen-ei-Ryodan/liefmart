<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk Platform Terlaris</title>

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

        /* Quick filter buttons */
        .quick-filter {
            transition: all 0.3s;
        }

        .quick-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .quick-filter.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
            <li class="breadcrumb-item active">Produk Platform Terlaris</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Produk Platform Terlaris</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.produk-platform-terlaris') }}" id="filter-form" class="mb-4">
                <!-- Quick Filter Buttons -->
                <div class="row mb-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Filter Cepat:</label>
                        <div class="btn-group" role="group" aria-label="Quick date filters">
                            <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="7">
                                7 Hari Terakhir
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="14">
                                2 Minggu Terakhir
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="30">
                                1 Bulan Terakhir
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="90">
                                3 Bulan Terakhir
                            </button>
                        </div>
                    </div>
                </div>

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
                        <label for="search" class="form-label">Cari Produk</label>
                        <input type="text" class="form-control" id="search" name="search"
                            placeholder="Cari nama produk" value="{{ $search }}">
                    </div>

                    <!-- Sort Options -->
                    <div class="col-md-3">
                        <label for="sort" class="form-label">Urutkan Berdasarkan</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>
                                Jumlah Terjual Tertinggi
                            </option>
                            <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>
                                Jumlah Terjual Terendah
                            </option>
                            <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>
                                Total Value Tertinggi
                            </option>
                            <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>
                                Total Value Terendah
                            </option>
                            <option value="order_count_highest" {{ $sortBy == 'order_count_highest' ? 'selected' : '' }}>
                                Jumlah Order Tertinggi
                            </option>
                            <option value="order_count_lowest" {{ $sortBy == 'order_count_lowest' ? 'selected' : '' }}>
                                Jumlah Order Terendah
                            </option>
                        </select>
                    </div>

                    <!-- Limit -->
                    <div class="col-md-3">
                        <label for="limit" class="form-label">Jumlah Data per Halaman</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200</option>
                            <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500</option>
                        </select>
                    </div>

                    <!-- Submit, Reset, and Export Button -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('analytics.produk-platform-terlaris') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.produk-platform-terlaris.export', request()->query()) }}" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
            </form>

            @if($summary['total_products'] == 0)
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
                            <h5 class="card-title">Total Produk</h5>
                            <h2 class="display-5">{{ number_format($summary['total_products']) }}</h2>
                            <p>Jumlah produk platform berbeda</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Terjual</h5>
                            <h2 class="display-5">{{ number_format($summary['total_quantity_with_returns']) }}</h2>
                            <p>pcs (sebelum retur)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Retur</h5>
                            <h2 class="display-5">{{ number_format($summary['total_returns']) }}</h2>
                            <p>pcs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Orders</h5>
                            <h2 class="display-5">{{ number_format($summary['total_orders']) }}</h2>
                            <p>jumlah pesanan</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <h5 class="mb-3">Daftar Produk Platform Terlaris</h5>
            <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Produk Platform</th>
                            <th>Varian</th>
                            <th>Platform</th>
                            <th class="text-end">Terjual (pcs)</th>
                            <th class="text-end">Retur (pcs)</th>
                            <th class="text-end">Net Terjual (pcs)</th>
                            <th class="text-end">Jumlah Order</th>
                            <th class="text-end">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginator as $index => $product)
                        <tr class="table-row-hover">
                            <td class="text-center">{{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}</td>
                            <td><span class="fw-medium">{{ $product['platform_product_name'] }}</span></td>
                            <td>{{ $product['variant'] }}</td>
                            <td>
                                <div class="platform-box platform-{{ strtolower(str_replace(' ', '-', $product['platform_name'])) }}">
                                    {{ $product['platform_name'] }}
                                </div>
                            </td>
                            <td class="text-end fw-bold">{{ number_format($product['total_quantity'], 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($product['qty_retur'], 0) }}</td>
                            <td class="text-end">{{ number_format($product['net_quantity'], 0) }}</td>
                            <td class="text-end">{{ number_format($product['order_count']) }}</td>
                            <td class="text-end">Rp {{ number_format($product['total_value'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">Tidak ada data produk</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="4"><strong>TOTAL</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_quantity_with_returns'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_returns'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_quantity'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_orders']) }}</strong></td>
                            <td class="text-end"><strong>Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            @if(method_exists($paginator, 'links'))
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} data
                </div>
                <div>
                    {{ $paginator->appends(request()->query())->links('pagination::bootstrap-5') }}
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
<script src="{{ asset('js/date-format.js') }}"></script>
<script>
    // Quick filter handler
    document.addEventListener('DOMContentLoaded', function() {
        // Get date inputs
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        // Ensure dates are always included when form is submitted
        // This prevents dates from being lost when changing other filters
        const filterForm = document.getElementById('filter-form');
        filterForm.addEventListener('submit', function(e) {
            // Ensure date inputs have values before submitting
            // If they're empty but we have values from URL, use those
            if (!startDateInput.value || !endDateInput.value) {
                const urlParams = new URLSearchParams(window.location.search);
                const urlStartDate = urlParams.get('start_date');
                const urlEndDate = urlParams.get('end_date');

                if (!startDateInput.value && urlStartDate) {
                    startDateInput.value = urlStartDate;
                }
                if (!endDateInput.value && urlEndDate) {
                    endDateInput.value = urlEndDate;
                }
            }
        });

        // Quick filter buttons - set end date to today when clicked
        const quickFilterButtons = document.querySelectorAll('.quick-filter');
        quickFilterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const days = parseInt(this.getAttribute('data-days'));
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(today.getDate() - days);

                // Format dates
                const startDateFormatted = startDate.getFullYear() + '-' +
                    String(startDate.getMonth() + 1).padStart(2, '0') + '-' +
                    String(startDate.getDate()).padStart(2, '0');
                const todayFormatted = getTodayYYYYMMDD();

                // Set dates - end date always today when using quick filter
                startDateInput.value = startDateFormatted;
                endDateInput.value = todayFormatted;

                // Remove active class from all buttons
                quickFilterButtons.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                // Add active class to clicked button
                this.classList.add('active');

                // Submit form
                filterForm.submit();
            });
        });

        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const hasStartDate = urlParams.has('start_date');
        const hasEndDate = urlParams.has('end_date');

        // CRITICAL: Only set default if BOTH conditions are true:
        // 1. URL has NO date parameters at all
        // 2. Input fields are EMPTY (no value from server/Blade)
        // This ensures dates are NEVER changed if they already have values (from Blade or previous submission)
        const inputHasStartDate = startDateInput.value && startDateInput.value.trim() !== '';
        const inputHasEndDate = endDateInput.value && endDateInput.value.trim() !== '';

        // Only modify dates if URL has no parameters AND inputs are truly empty
        if (!hasStartDate && !hasEndDate && !inputHasStartDate && !inputHasEndDate) {
            // This is truly the first load with no date values at all
            // Get today's date in YYYY-MM-DD format
            const todayFormatted = getTodayYYYYMMDD();

            // Set default values (both to today for first load)
            startDateInput.value = todayFormatted;
            endDateInput.value = todayFormatted;

            // Only auto-submit on first load (not when coming from same page)
            const isFormSubmission = document.referrer && document.referrer.includes('produk-platform-terlaris');
            if (!isFormSubmission) {
                // Small delay to ensure form is ready
                setTimeout(function() {
                    filterForm.submit();
                }, 100);
            }
        }
        // In ALL other cases, DO NOTHING - preserve existing values
        // - If URL has date parameters: values are already set from Blade
        // - If inputs have values: preserve them (from Blade or user input)
        // This ensures dates are NEVER reset when changing filters (platform, sort, limit, search, etc.)
    });
</script>
</body>
</html>
