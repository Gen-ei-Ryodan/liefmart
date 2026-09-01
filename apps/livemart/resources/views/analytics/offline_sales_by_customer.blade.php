<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penjualan Offline by Customer</title>

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

        /* Chart container */
        .chart-container {
            position: relative;
            margin: 20px 0;
            height: 350px;
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
            <li class="breadcrumb-item"><a href="{{ route('analytics.offline.index') }}">Offline Sales</a></li>
            <li class="breadcrumb-item active">Data Penjualan by Customer</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Data Penjualan Offline by Customer</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.offline.sales-by-customer') }}" id="filter-form" class="mb-4">
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
                            <option value="orders_highest" {{ $sortBy == 'orders_highest' ? 'selected' : '' }}>
                                Jumlah Order Terbanyak
                            </option>
                            <option value="orders_lowest" {{ $sortBy == 'orders_lowest' ? 'selected' : '' }}>
                                Jumlah Order Terendah
                            </option>
                            <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>
                                Nama Customer (A-Z)
                            </option>
                            <option value="name_desc" {{ $sortBy == 'name_desc' ? 'selected' : '' }}>
                                Nama Customer (Z-A)
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
                        <a href="{{ route('analytics.offline.sales-by-customer') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.offline.sales-by-customer.export', request()->query()) }}" class="btn btn-success w-100">
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
                            <h5 class="card-title">Top Customer</h5>
                            @php
                                $topCustomer = $customerSummary->sortByDesc('total_value')->first();
                            @endphp
                            <h2 class="display-5">{{ \Illuminate\Support\Str::limit($topCustomer['customer_name'], 15) }}</h2>
                            <p>Rp {{ number_format($topCustomer['total_value'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart for Customer Distribution -->
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Distribusi Penjualan per Customer</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="customerSalesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Customer Summary -->
            <h5 class="mb-3">Ringkasan Penjualan Per Customer</h5>
            <div class="table-responsive disable-fixed-scrollbar mb-4" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Customer</th>
                            <th class="text-end">Jumlah Penjualan</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">Avg Value/Order</th>
                            <th class="text-end">Total Volume</th>
                            <th class="text-end">Avg Volume/Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customerSummary as $customer)
                        <tr class="table-row-hover">
                            <td>
                                <div class="customer-badge">
                                    {{ $customer['customer_name'] }}
                                </div>
                            </td>
                            <td class="text-end">{{ number_format($customer['total_orders']) }}</td>
                            <td class="text-end">Rp {{ number_format($customer['total_value'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($customer['avg_order_value'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($customer['total_volume']) }} pcs</td>
                            <td class="text-end">{{ number_format($customer['avg_order_volume'], 1) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data penjualan</td>
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

@if($summary['total_orders'] > 0)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get data for the chart
        const customerNames = [
            @foreach($customerSummary->take(10) as $customer)
                '{{ \Illuminate\Support\Str::limit($customer['customer_name'], 15) }}',
            @endforeach
        ];

        const customerValues = [
            @foreach($customerSummary->take(10) as $customer)
                {{ $customer['total_value'] }},
            @endforeach
        ];

        const customerVolumes = [
            @foreach($customerSummary->take(10) as $customer)
                {{ $customer['total_volume'] }},
            @endforeach
        ];

        // Create pie chart for customer distribution
        const customerSalesCtx = document.getElementById('customerSalesChart').getContext('2d');
        const customerSalesChart = new Chart(customerSalesCtx, {
            type: 'bar',
            data: {
                labels: customerNames,
                datasets: [
                    {
                        label: 'Total Value (Rp)',
                        data: customerValues,
                        backgroundColor: [
                            'rgba(67, 97, 238, 0.7)',
                            'rgba(76, 201, 240, 0.7)',
                            'rgba(11, 180, 170, 0.7)',
                            'rgba(247, 37, 133, 0.7)',
                            'rgba(114, 9, 183, 0.7)',
                            'rgba(58, 12, 163, 0.7)',
                            'rgba(63, 55, 201, 0.7)',
                            'rgba(67, 97, 238, 0.7)',
                            'rgba(76, 201, 240, 0.7)',
                            'rgba(11, 180, 170, 0.7)'
                        ],
                        borderColor: [
                            'rgba(67, 97, 238, 1)',
                            'rgba(76, 201, 240, 1)',
                            'rgba(11, 180, 170, 1)',
                            'rgba(247, 37, 133, 1)',
                            'rgba(114, 9, 183, 1)',
                            'rgba(58, 12, 163, 1)',
                            'rgba(63, 55, 201, 1)',
                            'rgba(67, 97, 238, 1)',
                            'rgba(76, 201, 240, 1)',
                            'rgba(11, 180, 170, 1)'
                        ],
                        borderWidth: 1,
                        yAxisID: 'y-value',
                    },
                    {
                        label: 'Total Volume',
                        data: customerVolumes,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(247, 37, 133, 1)',
                        tension: 0.3,
                        yAxisID: 'y-volume',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    'y-value': {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Total Value (Rp)'
                        },
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                // Format angka dengan titik sebagai pemisah ribuan
                                return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            }
                        }
                    },
                    'y-volume': {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Volume'
                        },
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value, index, values) {
                                // Format angka dengan titik sebagai pemisah ribuan
                                return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y-value') {
                                    label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                                } else {
                                    label += new Intl.NumberFormat('id-ID').format(context.raw) + ' pcs';
                                }
                                return label;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                }
            }
        });
    });
</script>
@endif
</body>
</html>