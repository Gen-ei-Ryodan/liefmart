@extends('layouts.app')

@section('title', 'Data Penjualan Offline by Produk')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="bi-box-seam me-2"></i>Data Penjualan Offline by Produk
                    </h5>
                </div>
                <div class="card-body p-4">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.offline.sales-by-product') }}" id="filter-form" class="mb-4">
                <div class="row g-3 align-items-end">
                    <!-- Date Range -->
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date"
                            value="{{ $startDate }}">
                    </div>
                    <div class="col-md-2">
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

                    <!-- Product Filter -->
                    <div class="col-md-3">
                        <label for="product_id" class="form-label">Produk</label>
                        <select class="form-select" id="product_id" name="product_id">
                            <option value="">Semua Produk</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ $selectedProduct == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort Options -->
                    <div class="col-md-2">
                        <label for="sort" class="form-label">Urutkan</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>
                                Value Tertinggi
                            </option>
                            <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>
                                Value Terendah
                            </option>
                            <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>
                                Quantity Tertinggi
                            </option>
                            <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>
                                Quantity Terendah
                            </option>
                            <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>
                                Nama Produk (A-Z)
                            </option>
                            <option value="name_desc" {{ $sortBy == 'name_desc' ? 'selected' : '' }}>
                                Nama Produk (Z-A)
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
                        <a href="{{ route('analytics.offline.sales-by-product') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#" data-export="{{ route('analytics.offline.sales-by-product.export', request()->query()) }}" class="btn btn-success w-100">
                            <i class="bi bi-download"></i> Export Excel
                        </a>
                    </div>
                </div>
            </form>

            @if($summary['total_products'] == 0)
            <div class="alert alert-info my-4">
                <h5 class="alert-heading">Tidak ada data</h5>
                <p>Tidak ditemukan data penjualan{{ $startDate && $endDate ? ' untuk periode '.$startDate.' sampai '.$endDate : '' }}.</p>
                @if($startDate && $endDate)
                <p>Silakan ubah filter tanggal, customer atau produk untuk melihat data yang tersedia.</p>
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
                            <p>Nilai penjualan produk</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Total Quantity</h5>
                            <h2 class="display-5">{{ number_format($summary['total_quantity']) }} pcs</h2>
                            <p>Jumlah produk terjual</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-dark text-white h-100">
                        <div class="card-body">
                            <h5 class="card-title">Top Produk</h5>
                            @php
                                $topProduct = $productSummary->sortByDesc('total_value')->first();
                            @endphp
                            <h2 class="display-5">{{ \Illuminate\Support\Str::limit($topProduct['product_name'], 15) }}</h2>
                            <p>Rp {{ number_format($topProduct['total_value'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart for Product Distribution -->
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Distribusi Penjualan per Produk (Top 10)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="productSalesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Product Summary -->
            <h5 class="mb-3">Ringkasan Penjualan Per Produk</h5>
            <div class="table-responsive disable-fixed-scrollbar mb-4" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th>Produk</th>
                            <th class="text-end">Total Quantity</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">Avg Harga/Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productSummary as $product)
                        <tr class="table-row-hover">
                            <td>
                                <div class="product-badge">
                                    {{ $product['product_name'] }}
                                </div>
                            </td>
                            <td class="text-end">{{ number_format($product['total_quantity']) }} pcs</td>
                            <td class="text-end">Rp {{ number_format($product['total_value'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($product['avg_price'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">Tidak ada data penjualan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
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
@if($summary['total_products'] > 0)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get data for the chart - limiting to top 10 products by value
        const productNames = [
            @foreach($productSummary->sortByDesc('total_value')->take(10) as $product)
                '{{ \Illuminate\Support\Str::limit($product['product_name'], 15) }}',
            @endforeach
        ];

        const productValues = [
            @foreach($productSummary->sortByDesc('total_value')->take(10) as $product)
                {{ $product['total_value'] }},
            @endforeach
        ];

        const productQuantities = [
            @foreach($productSummary->sortByDesc('total_value')->take(10) as $product)
                {{ $product['total_quantity'] }},
            @endforeach
        ];

        // Create chart for product distribution
        const productSalesCtx = document.getElementById('productSalesChart').getContext('2d');
        const productSalesChart = new Chart(productSalesCtx, {
            type: 'bar',
            data: {
                labels: productNames,
                datasets: [
                    {
                        label: 'Total Value (Rp)',
                        data: productValues,
                        backgroundColor: 'rgba(11, 180, 170, 0.7)',
                        borderColor: 'rgba(11, 180, 170, 1)',
                        borderWidth: 1,
                        yAxisID: 'y-value',
                    },
                    {
                        label: 'Total Quantity',
                        data: productQuantities,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(67, 97, 238, 1)',
                        tension: 0.3,
                        yAxisID: 'y-quantity',
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
                    'y-quantity': {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total Quantity (pcs)'
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
@endsection
