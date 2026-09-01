<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Detail Penjualan Offline</title>

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

        /* Customer badge styling */
        .customer-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 120px;
            text-align: center;
            letter-spacing: 0.3px;
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

        /* Analytics menu cards */
        .analytics-menu-card {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .analytics-menu-card:hover {
            color: inherit;
            text-decoration: none;
        }

        .analytics-menu-card .card-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .analytics-menu-card .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .analytics-menu-card:hover .card-icon {
            transform: scale(1.1);
            transition: transform 0.3s ease;
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
            <li class="breadcrumb-item active">Penjualan Offline</li>
        </ol>
    </nav>

    <!-- Analytics Menu -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Menu Analisis Penjualan Offline</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <a href="{{ route('analytics.offline.monthly-sales-summary') }}" class="analytics-menu-card">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="card-icon">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <h5 class="card-title">Ringkasan Bulanan</h5>
                                        <p class="card-text">Analisis penjualan per bulan dalam satu tahun</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-4">
                            <a href="{{ route('analytics.offline.sales-by-customer') }}" class="analytics-menu-card">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="card-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <h5 class="card-title">Penjualan per Customer</h5>
                                        <p class="card-text">Analisis penjualan berdasarkan customer</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-4">
                            <a href="{{ route('analytics.offline.sales-by-product') }}" class="analytics-menu-card">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="card-icon">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <h5 class="card-title">Penjualan per Produk</h5>
                                        <p class="card-text">Analisis penjualan berdasarkan produk</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-4">
                            <a href="{{ route('analytics.offline.sales-detail-report') }}" class="analytics-menu-card">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="card-icon">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </div>
                                        <h5 class="card-title">Laporan Detail</h5>
                                        <p class="card-text">Laporan detail penjualan offline</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3 mb-4">
                            <a href="{{ route('analytics.offline.gross-profit') }}" class="analytics-menu-card">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="card-icon">
                                            <i class="bi bi-graph-up"></i>
                                        </div>
                                        <h5 class="card-title">Gross Profit</h5>
                                        <p class="card-text">Analisis profit dan margin penjualan offline</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Laporan Detail Penjualan Offline</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.offline.sales-detail-report') }}" id="filter-form" class="mb-4">
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

                    <!-- Customer Filter -->
                    <div class="col-md-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">Semua Customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    {{ $selectedCustomer == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort Options -->
                    <div class="col-md-3">
                        <label for="sort" class="form-label">Urutkan Berdasarkan</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="date_newest" {{ $sortBy == 'date_newest' ? 'selected' : '' }}>
                                Tanggal Terbaru
                            </option>
                            <option value="date_oldest" {{ $sortBy == 'date_oldest' ? 'selected' : '' }}>
                                Tanggal Terlama
                            </option>
                            <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>
                                Value Tertinggi
                            </option>
                            <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>
                                Value Terendah
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
                        <a href="{{ route('analytics.offline.sales-detail-report') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.offline.sales-detail-report.export', request()->query()) }}" class="btn btn-success w-100">
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
                <p>Silakan ubah filter tanggal atau customer untuk melihat data yang tersedia.</p>
                @endif
            </div>
            @else
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Penjualan</h5>
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
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Value</h5>
                            <h2 class="display-5">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                            <p>Rata-rata: Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }} per penjualan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Volume</h5>
                            <h2 class="display-5">{{ number_format($summary['total_volume']) }} pcs</h2>
                            <p>Rata-rata: {{ number_format($summary['avg_order_volume'], 1) }} pcs per penjualan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-dark text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Daily Average</h5>
                            @php
                                $dayCount = $startDate && $endDate ?
                                    max(1, Carbon\Carbon::parse($endDate)->diffInDays(Carbon\Carbon::parse($startDate)) + 1) : 30;
                                $dailyAvg = $summary['total_orders'] / $dayCount;
                            @endphp
                            <h2 class="display-5">{{ number_format($dailyAvg, 1) }}</h2>
                            <p>Penjualan per hari</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Summary Row for PPN -->
            <div class="row mb-4">
                @php
                    // Calculate PPN for HGN items only
                    $hgnValue = 0;
                    $lmValue = 0;
                    $totalPPNAmount = 0;

                    foreach($sales as $sale) {
                        foreach($sale->items as $item) {
                            if($item->warehouseStock && $item->warehouseStock->tax_id == 3) {
                                // HGN/PKP items - add PPN
                                $hgnValue += $item->subtotal ?? 0;
                            } else {
                                // LM/Non-PKP items - no PPN
                                $lmValue += $item->subtotal ?? 0;
                            }
                        }
                    }

                    // Calculate PPN amount for HGN items only
                    $totalPPNAmount = $hgnValue * 0.11;
                    $totalWithPPN = $hgnValue + $totalPPNAmount + $lmValue; // HGN + PPN + LM
                @endphp
                <div class="col-md-4">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total + PPN</h5>
                            <h2 class="display-5">Rp {{ number_format($totalWithPPN, 0, ',', '.') }}</h2>
                            <p>HGN + PPN + LM</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">PPN Amount</h5>
                            <h2 class="display-5">Rp {{ number_format($totalPPNAmount, 0, ',', '.') }}</h2>
                            <p>11% dari HGN items</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Base Value (DPP)</h5>
                            <h2 class="display-5">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                            <p>HGN: Rp {{ number_format($hgnValue, 0, ',', '.') }} | LM: Rp {{ number_format($lmValue, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales List - All Items with PPN Column -->
            <h5 class="mb-3">Daftar Penjualan Offline</h5>
            <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr class="text-center">
                            <th width="50">No</th>
                            <th>Tanggal</th>
                            <th>No. Invoice</th>
                            <th>Customer</th>
                            <th class="text-end">Value (DPP)</th>
                            <th class="text-end">Nominal + PPN</th>
                            <th class="text-end">Volume</th>
                            <th class="text-end">QTY Retur</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $index => $sale)
                        @php
                            // Calculate PPN for this sale
                            $hgnValue = 0;
                            $lmValue = 0;
                            $totalValue = 0;
                            $ppnAmount = 0;
                            $totalWithPPN = 0;

                            foreach($sale->items as $item) {
                                $itemValue = $item->subtotal ?? 0;
                                $totalValue += $itemValue;

                                if($item->warehouseStock && $item->warehouseStock->tax_id == 3) {
                                    // HGN/PKP items - add PPN
                                    $hgnValue += $itemValue;
                                } else {
                                    // LM/Non-PKP items - no PPN
                                    $lmValue += $itemValue;
                                }
                            }

                            // Calculate PPN for HGN items only
                            $ppnAmount = $hgnValue * 0.11;
                            $totalWithPPN = $hgnValue + $ppnAmount + $lmValue; // HGN + PPN + LM

                            // Use pre-calculated values from controller
                            $qtyRetur = $sale->total_retur_qty ?? 0;
                            $hargaTotal = $sale->value_after_returns ?? 0;
                            $totalVolumeAfterRetur = $sale->total_volume_after_returns ?? $sale->total_volume;

                            // Get invoice numbers for this sale
                            $invoices = $sale->getInvoices();
                            $invoiceNumbers = $invoices->pluck('invoice_number')->filter()->unique()->values();
                        @endphp
                        <tr class="table-row-hover">
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $sale->sale_date ? $sale->sale_date->format('d-m-Y') : 'N/A' }}</td>
                            <td>
                                @if($invoiceNumbers->isNotEmpty())
                                    @foreach($invoiceNumbers as $invoiceNumber)
                                        <span class="fw-medium">{{ $invoiceNumber }}</span>
                                        @if(!$loop->last)
                                            <br>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="customer-badge">
                                    {{ $sale->customerInfo ? $sale->customerInfo->name : 'Unknown' }}
                                </div>
                            </td>
                            <td class="text-end fw-medium">Rp {{ number_format($hargaTotal, 0, ',', '.') }}</td>
                            <td class="text-end fw-medium">Rp {{ number_format($totalWithPPN, 0, ',', '.') }}</td>
                            <td class="text-end">
                                {{ number_format($totalVolumeAfterRetur) }} pcs
                                @if($qtyRetur > 0)
                                    <small class="text-muted d-block">({{ number_format($sale->total_volume) }} - {{ number_format($qtyRetur) }})</small>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($qtyRetur) }} pcs</td>
                            <td class="text-center">
                                @if($sale->status == 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($sale->status == 'completed')
                                    <span class="badge bg-success">Completed</span>
                                @elseif($sale->status == 'cancelled')
                                    <span class="badge bg-danger">Cancelled</span>
                                @else
                                    <span class="badge bg-secondary">{{ $sale->status }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center">Tidak ada data penjualan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
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
        const todayFormatted = getTodayYYYYMMDD();

        // Set default values if empty
        if (!startDateInput.value) {
            // First day of current month
            startDateInput.value = `${year}-${month}-01`;
        }

        if (!endDateInput.value) {
            endDateInput.value = todayFormatted;
        }
    });
</script>
</body>
</html>