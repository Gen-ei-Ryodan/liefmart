@extends('layouts.app')

@section('title', 'Produk Platform Terlaris')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-trophy me-2"></i>Produk Platform Terlaris
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.produk-platform-terlaris.export', request()->query()) }}" class="btn btn-sm btn-success">
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
    <form method="GET" action="{{ route('analytics.produk-platform-terlaris') }}" id="filter-form" class="mb-4">
        <div class="row mb-3">
            <div class="col-12">
                <label class="form-label fw-bold small">Filter Cepat:</label>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="7">7 Hari</button>
                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="14">2 Minggu</button>
                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="30">1 Bulan</button>
                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="90">3 Bulan</button>
                </div>
            </div>
        </div>
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
                <input type="text" class="form-control" id="search" name="search" placeholder="Cari nama produk" value="{{ $search }}">
            </div>
            <div class="col-md-3">
                <label for="sort" class="form-label small fw-bold">Urutkan</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>Qty Terjual Tertinggi</option>
                    <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>Qty Terjual Terendah</option>
                    <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>Value Tertinggi</option>
                    <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>Value Terendah</option>
                    <option value="order_count_highest" {{ $sortBy == 'order_count_highest' ? 'selected' : '' }}>Order Tertinggi</option>
                    <option value="order_count_lowest" {{ $sortBy == 'order_count_lowest' ? 'selected' : '' }}>Order Terendah</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="limit" class="form-label small fw-bold">Jumlah Data</label>
                <select class="form-select" id="limit" name="limit">
                    <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100</option>
                    <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200</option>
                    <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="{{ route('analytics.produk-platform-terlaris') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>
        </div>
    </form>

    @if($summary['total_products'] == 0)
    <div class="alert alert-info my-4">
        <h5 class="alert-heading">Tidak ada data</h5>
        <p>Tidak ditemukan data penjualan{{ $startDate && $endDate ? ' untuk periode '.$startDate.' sampai '.$endDate : '' }}.</p>
    </div>
    @else
    <!-- Summary Cards -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body bg-primary text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_products']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Produk</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-box text-white"></i>
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
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_quantity_with_returns']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Terjual (pcs)</div>
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
                            <div class="text-white opacity-75 small mt-1">Total Retur (pcs)</div>
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
                <div class="card-body bg-info text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_orders']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Orders</div>
                        </div>
                        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-file-invoice text-white"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">
                <i class="fas fa-list me-2"></i>Daftar Produk Platform Terlaris
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3" width="50">No</th>
                            <th>Nama Produk Platform</th>
                            <th>Varian</th>
                            <th>Platform</th>
                            <th class="text-end">Terjual (pcs)</th>
                            <th class="text-end">Retur (pcs)</th>
                            <th class="text-end">Net Terjual</th>
                            <th class="text-end">Jumlah Order</th>
                            <th class="text-end pe-3">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginator as $index => $product)
                        <tr>
                            <td class="ps-3 text-center">{{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}</td>
                            <td><span class="fw-medium">{{ $product['platform_product_name'] }}</span></td>
                            <td>{{ $product['variant'] }}</td>
                            <td>
                                <span class="badge bg-{{ strtolower($product['platform_name']) == 'shopee' ? 'danger' : 'dark' }} px-2 py-1">
                                    {{ $product['platform_name'] }}
                                </span>
                            </td>
                            <td class="text-end fw-bold">{{ number_format($product['total_quantity'], 0) }}</td>
                            <td class="text-end text-danger">{{ number_format($product['qty_retur'], 0) }}</td>
                            <td class="text-end">{{ number_format($product['net_quantity'], 0) }}</td>
                            <td class="text-end">{{ number_format($product['order_count']) }}</td>
                            <td class="text-end pe-3">Rp {{ number_format($product['total_value'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Tidak ada data produk</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-dark text-white">
                        <tr>
                            <td colspan="4" class="ps-3"><strong>TOTAL</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_quantity_with_returns'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_returns'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_quantity'], 0) }}</strong></td>
                            <td class="text-end"><strong>{{ number_format($summary['total_orders']) }}</strong></td>
                            <td class="text-end pe-3"><strong>Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if(method_exists($paginator, 'links'))
    <div class="d-flex justify-content-between align-items-center">
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
    </div>
</div>

@endsection

@push('styles')
<style>
    .quick-filter.active { background-color: #0d6efd; color: white; border-color: #0d6efd; }
</style>
@endpush

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const filterForm = document.getElementById('filter-form');

    filterForm.addEventListener('submit', function(e) {
        if (!startDateInput.value || !endDateInput.value) {
            const urlParams = new URLSearchParams(window.location.search);
            if (!startDateInput.value) startDateInput.value = urlParams.get('start_date') || '';
            if (!endDateInput.value) endDateInput.value = urlParams.get('end_date') || '';
        }
    });

    document.querySelectorAll('.quick-filter').forEach(function(button) {
        button.addEventListener('click', function() {
            const days = parseInt(this.getAttribute('data-days'));
            const today = new Date();
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - days);
            startDateInput.value = startDate.toISOString().split('T')[0];
            endDateInput.value = today.toISOString().split('T')[0];
            document.querySelectorAll('.quick-filter').forEach(function(btn) { btn.classList.remove('active'); });
            this.classList.add('active');
            filterForm.submit();
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('start_date') && !urlParams.has('end_date') && !startDateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        startDateInput.value = today;
        endDateInput.value = today;
        if (!document.referrer.includes('produk-platform-terlaris')) {
            setTimeout(function() { filterForm.submit(); }, 100);
        }
    }
});
</script>
@endsection
