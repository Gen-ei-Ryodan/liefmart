@extends('layouts.app')

@section('title', 'Data Penjualan by Platform')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-bar me-2"></i>Data Penjualan by Platform
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.sales-by-platform.export', request()->query()) }}" class="btn btn-sm btn-success">
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

    <!-- Filter Form -->
    <form method="GET" action="{{ route('analytics.sales-by-platform') }}" id="filter-form" class="mb-4">
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
                        <option value="{{ $platform->id }}" {{ $selectedPlatform == $platform->id ? 'selected' : '' }}>
                            {{ $platform->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="sort" class="form-label small fw-bold">Urutkan Berdasarkan</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>Value Tertinggi</option>
                    <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>Value Terendah</option>
                    <option value="volume_highest" {{ $sortBy == 'volume_highest' ? 'selected' : '' }}>Volume Tertinggi</option>
                    <option value="volume_lowest" {{ $sortBy == 'volume_lowest' ? 'selected' : '' }}>Volume Terendah</option>
                    <option value="date_newest" {{ $sortBy == 'date_newest' ? 'selected' : '' }}>Tanggal Terbaru</option>
                    <option value="date_oldest" {{ $sortBy == 'date_oldest' ? 'selected' : '' }}>Tanggal Terlama</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="{{ route('analytics.sales-by-platform') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
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
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-primary text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_orders']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Pesanan</div>
                            <div class="text-white opacity-50 small">
                                @if($startDate && $endDate)
                                {{ $startDate }} - {{ $endDate }}
                                @else Semua Periode @endif
                            </div>
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
                <div class="card-body bg-danger text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_returns']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Retur</div>
                            <div class="text-white opacity-50 small">
                                @if($startDate && $endDate)
                                {{ $startDate }} - {{ $endDate }}
                                @else Semua Periode @endif
                            </div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-undo text-white"></i>
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
                            <h4 class="fw-bold mb-0">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Value</div>
                            <div class="text-white opacity-50 small">Rata-rata: Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }}</div>
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
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_volume']) }} pcs</h4>
                            <div class="text-white opacity-75 small mt-1">Total Volume</div>
                            <div class="text-white opacity-50 small">Rata-rata: {{ number_format($summary['avg_order_volume'], 1) }} pcs</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-cubes text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Summary -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">
                <i class="fas fa-chart-pie me-2"></i>Ringkasan Penjualan Per Platform
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3">Platform</th>
                            <th class="text-end">Jumlah Order</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">Avg Value/Order</th>
                            <th class="text-end">Total Volume</th>
                            <th class="text-end pe-3">Avg Volume/Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($platformSummary as $summary)
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-{{ strtolower($summary['platform']) == 'shopee' ? 'danger' : (strtolower($summary['platform']) == 'tiktok' ? 'dark' : 'secondary') }} px-2 py-1">
                                    {{ $summary['platform'] }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($summary['order_count']) }}</td>
                            <td class="text-end">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($summary['total_volume']) }} pcs</td>
                            <td class="text-end pe-3">{{ number_format($summary['avg_order_volume'], 1) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada data penjualan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order List -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">
                <i class="fas fa-list me-2"></i>Daftar Pesanan
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3" width="50">No</th>
                            <th>Tanggal</th>
                            <th>Order Number</th>
                            <th width="120">Platform</th>
                            <th class="text-end">Value</th>
                            <th class="text-end pe-3">Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td class="ps-3 text-center">{{ ($orders->currentPage() - 1) * $orders->perPage() + $loop->iteration }}</td>
                            <td>{{ $order->tanggal ? $order->tanggal->format('d-m-Y') : 'N/A' }}</td>
                            <td><span class="fw-medium">{{ $order->order_number }}</span></td>
                            <td class="text-center">
                                <span class="badge bg-{{ $order->platform ? (strtolower($order->platform->name) == 'shopee' ? 'danger' : (strtolower($order->platform->name) == 'tiktok' ? 'dark' : 'secondary')) : 'secondary' }} px-2 py-1">
                                    {{ $order->platform ? $order->platform->name : 'N/A' }}
                                </span>
                            </td>
                            <td class="text-end fw-medium">Rp {{ number_format($order->total_value, 0, ',', '.') }}</td>
                            <td class="text-end pe-3">{{ number_format($order->total_volume) }} pcs</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada data pesanan</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    @if(method_exists($orders, 'links'))
    <div class="d-flex justify-content-between align-items-center">
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const today = new Date().toISOString().split('T')[0];

    if (!startDateInput.value) startDateInput.value = today;
    if (!endDateInput.value) endDateInput.value = today;

    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('start_date') && !urlParams.has('end_date')) {
        document.getElementById('filter-form').submit();
    }
});
</script>
@endsection
