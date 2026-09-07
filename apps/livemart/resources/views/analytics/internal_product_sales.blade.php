@extends('layouts.app')

@section('title', 'Analytics Penjualan Master Internal')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-boxes me-2"></i>Analytics Penjualan Master Internal
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.internal-product-sales.export', request()->query()) }}" class="btn btn-sm btn-success">
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
    <form method="GET" action="{{ route('analytics.internal-product-sales') }}" id="filter-form" class="mb-4">
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
                <label for="sort" class="form-label small fw-bold">Urutkan</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="qty_highest" {{ $sortBy == 'qty_highest' ? 'selected' : '' }}>Qty Tertinggi</option>
                    <option value="qty_lowest" {{ $sortBy == 'qty_lowest' ? 'selected' : '' }}>Qty Terendah</option>
                    <option value="value_highest" {{ $sortBy == 'value_highest' ? 'selected' : '' }}>Value Tertinggi</option>
                    <option value="value_lowest" {{ $sortBy == 'value_lowest' ? 'selected' : '' }}>Value Terendah</option>
                    <option value="name_asc" {{ $sortBy == 'name_asc' ? 'selected' : '' }}>Nama A-Z</option>
                    <option value="name_desc" {{ $sortBy == 'name_desc' ? 'selected' : '' }}>Nama Z-A</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary me-2">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="{{ route('analytics.internal-product-sales') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
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
                <div class="card-body bg-info text-white rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_orders']) }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Order</div>
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
                            <h4 class="fw-bold mb-0">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h4>
                            <div class="text-white opacity-75 small mt-1">Total Value</div>
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
                <div class="card-body bg-warning text-dark rounded-3 py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0">{{ number_format($summary['total_qty']) }}</h4>
                            <div class="text-dark opacity-75 small mt-1">Total Qty</div>
                        </div>
                        <div class="rounded-circle bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                            <i class="fas fa-cubes text-dark"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product List -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-primary">
                <i class="fas fa-list me-2"></i>Daftar Produk Internal
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="ps-3" width="40">No</th>
                            <th width="120">SKU</th>
                            <th>Nama Barang Internal</th>
                            <th width="100">Jumlah Order</th>
                            <th width="120">Total Qty</th>
                            <th class="text-end pe-3" width="150">Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $no = ($products->currentPage() - 1) * $products->perPage() + 1;
                        @endphp
                        @forelse($products as $product)
                            <tr>
                                <td class="ps-3 text-center">{{ $no++ }}</td>
                                <td class="text-center"><span class="badge bg-secondary">{{ $product->product_sku }}</span></td>
                                <td><strong>{{ $product->product_name }}</strong></td>
                                <td class="text-center"><span class="badge bg-info text-white">{{ number_format($product->order_count) }} order</span></td>
                                <td class="text-center fw-medium">{{ number_format($product->total_qty) }} pcs</td>
                                <td class="text-end fw-bold pe-3">Rp {{ number_format($product->total_value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Tidak ada data penjualan master internal</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(method_exists($products, 'links'))
    <div class="d-flex justify-content-between align-items-center">
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
    if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('internal-product-sales')) {
        document.getElementById('filter-form').submit();
    }
});
</script>
@endsection
