@extends('layouts.app')

@section('title', 'Gross Profit per Produk Platform')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Gross Profit per Produk Platform
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.sales-by-platform-product.export', request()->all()) }}" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Quick Date Range Filters -->
    <div class="mb-4">
        <label class="form-label fw-bold small">Filter Cepat:</label>
        <div class="btn-group" role="group">
            <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '7days'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
               class="btn btn-sm {{ request('quick_range') == '7days' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-calendar-week me-1"></i> 7 Hari
            </a>
            <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '2weeks'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
               class="btn btn-sm {{ request('quick_range') == '2weeks' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-calendar me-1"></i> 2 Minggu
            </a>
            <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '1month'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
               class="btn btn-sm {{ request('quick_range') == '1month' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-calendar-alt me-1"></i> 1 Bulan
            </a>
            <a href="{{ route('analytics.sales-by-platform-product', ['quick_range' => '3months'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
               class="btn btn-sm {{ request('quick_range') == '3months' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="fas fa-calendar-minus me-1"></i> 3 Bulan
            </a>
            @if(request('quick_range'))
                <a href="{{ route('analytics.sales-by-platform-product', request()->except(['quick_range', 'start_date', 'end_date'])) }}"
                   class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times"></i> Reset
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Form -->
    <form method="GET" action="{{ route('analytics.sales-by-platform-product') }}" id="filter-form" class="mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="start_date" class="form-label small fw-bold">Tanggal Mulai</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label small fw-bold">Tanggal Akhir</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
            </div>
            <div class="col-md-3">
                <label for="platform_id" class="form-label small fw-bold">Platform</label>
                <select class="form-select" id="platform_id" name="platform_id">
                    <option value="">Semua Platform</option>
                    @foreach($platforms as $platform)
                        <option value="{{ $platform->id }}" {{ $selectedPlatform == $platform->id ? 'selected' : '' }}>{{ $platform->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label small fw-bold">Cari Produk</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Cari nama produk" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label for="order_number" class="form-label small fw-bold">Cari No Order</label>
                <input type="text" class="form-control" id="order_number" name="order_number" placeholder="Cari nomor order" value="{{ request('order_number') }}">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                    <i class="fas fa-filter me-1"></i> Filter Lanjutan
                </button>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="{{ route('analytics.sales-by-platform-product') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>
        </div>
        <div class="collapse {{ request()->hasAny(['sort']) ? 'show' : '' }}" id="advancedFilters">
            <div class="row mt-3 g-3">
                <div class="col-md-6">
                    <label for="sort" class="form-label small fw-bold">Urutkan Berdasarkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="revenue_highest" {{ $sortBy == 'revenue_highest' ? 'selected' : '' }}>Saldo Masuk Tertinggi</option>
                        <option value="revenue_lowest" {{ $sortBy == 'revenue_lowest' ? 'selected' : '' }}>Saldo Masuk Terendah</option>
                        <option value="profit_highest" {{ $sortBy == 'profit_highest' ? 'selected' : '' }}>Profit Tertinggi</option>
                        <option value="profit_lowest" {{ $sortBy == 'profit_lowest' ? 'selected' : '' }}>Profit Terendah</option>
                        <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>Kuantitas Tertinggi</option>
                        <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>Kuantitas Terendah</option>
                    </select>
                </div>
            </div>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-primary text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_platform_products_after_returns']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Jumlah Pesanan</div>
                            <div class="text-white opacity-50 small">dari {{ number_format($summary['total_platform_products']) }} ({{ number_format($summary['total_platform_products'] - $summary['total_platform_products_after_returns']) }} retur)</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-shopping-cart text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-success text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">Rp {{ number_format($summary['total_revenue'], 0, ',', '.') }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Saldo Masuk</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-money-bill-wave text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-info text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">Rp {{ number_format($summary['total_capital'], 0, ',', '.') }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Modal</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-coins text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-dark text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">Rp {{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</h4>
                            <div class="text-white opacity-75 small mt-1">Gross Profit</div>
                            <div class="text-white opacity-50 small">Margin: {{ number_format($summary['profit_margin'], 2) }}%</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(count($platformProductRows) > 0)
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">
                <i class="fas fa-list me-2"></i>Data Produk Platform
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3">Tanggal</th>
                            <th>No Pesanan</th>
                            <th>No Invoice</th>
                            <th style="min-width: 200px;">Nama Produk</th>
                            <th>Variasi</th>
                            <th class="text-end">QTY</th>
                            <th class="text-end">Saldo Masuk (Rp)</th>
                            <th class="text-end">Modal (Rp)</th>
                            <th class="text-end">GP %</th>
                            <th class="text-end">Margin (Rp)</th>
                            <th class="text-end pe-3">Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($platformProductRows as $row)
                            @php
                                $rowClass = '';
                                if(($row['gross_profit_per_product_rp'] ?? 0) < 0) $rowClass = 'table-danger';
                                if(($row['revenue'] ?? 0) == 0) $rowClass = 'table-warning';
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="ps-3">{{ \Carbon\Carbon::parse($row['order_date'])->format('d/m/Y') }}</td>
                                <td>{{ $row['order_number'] }}</td>
                                <td>{{ $row['invoice_number'] ?? '-' }}</td>
                                <td>
                                    <strong>{{ $row['platform_product_name'] }}</strong>
                                    @if($row['has_multiple_items'])
                                        <span class="badge bg-info ms-1"><i class="fas fa-boxes"></i> Multi</span>
                                    @endif
                                </td>
                                <td>{{ $row['product_variant'] ?? '-' }}</td>
                                <td class="text-end">{{ number_format($row['quantity'], 0) }}</td>
                                <td class="text-end">{{ number_format($row['revenue'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row['capital'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row['gross_profit_per_product_percent'] ?? 0, 1) }}%</td>
                                <td class="text-end fw-bold {{ ($row['margin_per_product_rp'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($row['margin_per_product_rp'] ?? 0, 0, ',', '.') }}
                                </td>
                                <td class="text-end pe-3">{{ number_format($row['margin_per_product_percent'] ?? 0, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-dark text-white">
                        <tr>
                            <td colspan="5" class="ps-3"><strong>TOTAL ({{ number_format($summary['total_rows']) }} rows)</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_quantity']) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_revenue'], 0, ',', '.') }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_capital'], 0, ',', '.') }}</strong></td>
                            <td class="text-end">
                                @php
                                    $totalGrossProfitPercent = $summary['total_revenue_without_ppn'] > 0 ? ($summary['total_gross_profit'] / $summary['total_revenue_without_ppn']) * 100 : 0;
                                @endphp
                                <strong>{{ number_format($totalGrossProfitPercent, 1) }}%</strong>
                            </td>
                            <td class="text-end"><strong>{{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</strong></td>
                            <td class="text-end pe-3"><strong>{{ number_format($totalGrossProfitPercent, 1) }}%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Menampilkan {{ $platformProductRows->firstItem() ?? 0 }} - {{ $platformProductRows->lastItem() ?? 0 }} dari {{ number_format($summary['total_rows']) }} data
        </div>
        <div>
            {{ $platformProductRows->links('pagination::bootstrap-5') }}
        </div>
    </div>
    @else
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> Tidak ada data yang tersedia untuk filter yang dipilih.
    </div>
    @endif

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const today = new Date().toISOString().split('T')[0];

    if (!startDateInput.value) startDateInput.value = today;
    if (!endDateInput.value) endDateInput.value = today;

    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('sales-by-platform-product')) {
        document.getElementById('filter-form').submit();
    }
});
</script>
@endsection
