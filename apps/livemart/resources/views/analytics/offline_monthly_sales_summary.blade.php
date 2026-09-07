@extends('layouts.app')

@section('title', 'Ringkasan Penjualan Bulanan Offline')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="bi-calendar-month me-2"></i>Ringkasan Penjualan Bulanan Offline
                    </h5>
                </div>
                <div class="card-body p-4">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.offline.monthly-sales-summary') }}" id="filter-form" class="mb-4">
                <div class="row g-3 align-items-end">
                    <!-- Year Filter -->
                    <div class="col-md-3">
                        <label for="year" class="form-label">Tahun</label>
                        <select class="form-select" id="year" name="year">
                            @foreach($availableYears as $year => $yearLabel)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                    {{ $yearLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Customer Filter -->
                    <div class="col-md-4">
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

                    <!-- Submit and Reset Button -->
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('analytics.offline.monthly-sales-summary') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.offline.monthly-sales-summary.export', request()->query()) }}" class="btn btn-success w-100">
                            <i class="bi bi-download"></i> Export Excel
                        </a>
                    </div>
                </div>
            </form>

            @if($yearSummary['total_orders'] == 0)
            <div class="alert alert-info my-4">
                <h5 class="alert-heading">Tidak ada data</h5>
                <p>Tidak ditemukan data penjualan offline untuk tahun {{ $selectedYear }}{{ $selectedCustomer ? ' dan customer yang dipilih' : '' }}.</p>
                <p>Silakan ubah filter tahun atau customer untuk melihat data yang tersedia.</p>
            </div>
            @else
            <!-- Year Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Penjualan</h5>
                            <h2 class="display-5">{{ number_format($yearSummary['total_orders']) }}</h2>
                            <p>Penjualan selama {{ $selectedYear }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Value</h5>
                            <h2 class="display-5">Rp {{ number_format($yearSummary['total_value'], 0, ',', '.') }}</h2>
                            <p>Rata-rata: Rp {{ number_format($yearSummary['avg_order_value'], 0, ',', '.') }} per penjualan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Volume</h5>
                            <h2 class="display-5">{{ number_format($yearSummary['total_volume']) }} pcs</h2>
                            <p>Rata-rata: {{ number_format($yearSummary['avg_order_volume'], 1) }} pcs per penjualan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-dark text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Highest Month</h5>
                            @php
                                $highestMonth = $monthlySummary->sortByDesc('total_value')->first();
                            @endphp
                            <h2 class="display-5">{{ $highestMonth['month_name'] }}</h2>
                            <p>Rp {{ number_format($highestMonth['total_value'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart for Monthly Sales -->
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Tren Penjualan Bulanan {{ $selectedYear }}</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlySalesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Monthly Data Table -->
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Data Penjualan Bulanan {{ $selectedYear }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered m-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Bulan</th>
                                    <th class="text-end">Jumlah Penjualan</th>
                                    <th class="text-end">Total Value</th>
                                    <th class="text-end">Avg Value/Order</th>
                                    <th class="text-end">Total Volume</th>
                                    <th class="text-end">Avg Volume/Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlySummary as $monthData)
                                <tr class="table-row-hover">
                                    <td>{{ $monthData['month_name'] }}</td>
                                    <td class="text-end">{{ number_format($monthData['total_orders']) }}</td>
                                    <td class="text-end">Rp {{ number_format($monthData['total_value'], 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format($monthData['avg_order_value'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($monthData['total_volume']) }} pcs</td>
                                    <td class="text-end">{{ number_format($monthData['avg_order_volume'], 1) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- Bootstrap JS Bundle with Popper -->
@if($yearSummary['total_orders'] > 0)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data for charts
        const months = [
            @foreach($monthlySummary as $monthData)
                '{{ $monthData['month_name'] }}',
            @endforeach
        ];

        const orderCounts = [
            @foreach($monthlySummary as $monthData)
                {{ $monthData['total_orders'] }},
            @endforeach
        ];

        const values = [
            @foreach($monthlySummary as $monthData)
                {{ $monthData['total_value'] }},
            @endforeach
        ];

        const volumes = [
            @foreach($monthlySummary as $monthData)
                {{ $monthData['total_volume'] }},
            @endforeach
        ];

        // Create monthly sales chart
        const monthlySalesCtx = document.getElementById('monthlySalesChart').getContext('2d');
        const monthlySalesChart = new Chart(monthlySalesCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Jumlah Penjualan',
                        data: orderCounts,
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1,
                        borderRadius: 5,
                        yAxisID: 'y-orders',
                        order: 2
                    },
                    {
                        label: 'Total Value (Rp)',
                        data: values,
                        backgroundColor: 'rgba(11, 180, 170, 0.5)',
                        borderColor: 'rgba(11, 180, 170, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y-value',
                        tension: 0.3,
                        order: 1
                    },
                    {
                        label: 'Total Volume (pcs)',
                        data: volumes,
                        backgroundColor: 'rgba(76, 201, 240, 0.5)',
                        borderColor: 'rgba(76, 201, 240, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y-volume',
                        tension: 0.3,
                        order: 0
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
                    'y-orders': {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Jumlah Penjualan'
                        },
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                // Format angka dengan titik sebagai pemisah ribuan
                                return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            }
                        }
                    },
                    'y-value': {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Value (Rp)'
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
                    },
                    'y-volume': {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Volume (pcs)'
                        },
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        display: false, // Hidden but data still available for tooltips
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
                                    label += new Intl.NumberFormat('id-ID').format(context.raw);
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
@endsection
