@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1">Data Order Belum Ada Pembayaran</h1>
                    <p class="text-muted mb-0">Kelola dan monitor order yang belum memiliki data pembayaran</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="#" data-export="{{ route('finance.unpaid-orders.export.excel', request()->query()) }}" class="btn btn-success">
                        <i class="fas fa-file-excel me-1"></i> Export Excel
                    </a>
                    <a href="#" data-export="{{ route('finance.unpaid-orders.export.pdf', request()->query()) }}" class="btn btn-danger">
                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ number_format($summary['total_orders']) }}</h4>
                            <p class="mb-0">Total Order</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h4>
                            <p class="mb-0">Total Nilai</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ count($summary['platform_breakdown']) }}</h4>
                            <p class="mb-0">Platform Terlibat</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-store fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">{{ $summary['age_breakdown']['30+_days'] ?? 0 }}</h4>
                            <p class="mb-0">Order 30+ Hari</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Breakdown -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Breakdown per Platform</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Platform</th>
                                    <th>Jumlah Order</th>
                                    <th>Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary['platform_breakdown'] as $platform => $data)
                                <tr>
                                    <td>{{ $platform }}</td>
                                    <td>{{ number_format($data['count']) }}</td>
                                    <td>Rp {{ number_format($data['value'], 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Breakdown per Usia Order</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Usia Order</th>
                                    <th>Jumlah Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>0-7 hari</td>
                                    <td>{{ $summary['age_breakdown']['0-7_days'] ?? 0 }}</td>
                                </tr>
                                <tr>
                                    <td>8-14 hari</td>
                                    <td>{{ $summary['age_breakdown']['8-14_days'] ?? 0 }}</td>
                                </tr>
                                <tr>
                                    <td>15-21 hari</td>
                                    <td>{{ $summary['age_breakdown']['15-21_days'] ?? 0 }}</td>
                                </tr>
                                <tr>
                                    <td>22-30 hari</td>
                                    <td>{{ $summary['age_breakdown']['22-30_days'] ?? 0 }}</td>
                                </tr>
                                <tr class="table-warning">
                                    <td>30+ hari</td>
                                    <td><strong>{{ $summary['age_breakdown']['30+_days'] ?? 0 }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Data</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('finance.unpaid-orders.index') }}" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label for="platform" class="form-label">Platform</label>
                        <select name="platform" id="platform" class="form-select">
                            <option value="">Semua Platform</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->name }}" {{ request('platform') == $platform->name ? 'selected' : '' }}>
                                    {{ $platform->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="from_date" class="form-label">Dari Tanggal</label>
                        <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="to_date" class="form-label">Sampai Tanggal</label>
                        <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="order_number" class="form-label">No. Order</label>
                        <input type="text" name="order_number" id="order_number" class="form-control" value="{{ request('order_number') }}" placeholder="Cari no. order">
                    </div>
                    <div class="col-md-2">
                        <label for="min_value" class="form-label">Min. Nilai</label>
                        <input type="number" name="min_value" id="min_value" class="form-control" value="{{ request('min_value') }}" placeholder="Min. nilai order">
                    </div>
                    <div class="col-md-2">
                        <label for="max_value" class="form-label">Max. Nilai</label>
                        <input type="number" name="max_value" id="max_value" class="form-control" value="{{ request('max_value') }}" placeholder="Max. nilai order">
                    </div>
                    <div class="col-md-2">
                        <label for="min_age" class="form-label">Min. Usia (Hari)</label>
                        <input type="number" name="min_age" id="min_age" class="form-control" value="{{ request('min_age') }}" placeholder="Min. usia order">
                    </div>
                    <div class="col-md-2">
                        <label for="max_age" class="form-label">Max. Usia (Hari)</label>
                        <input type="number" name="max_age" id="max_age" class="form-control" value="{{ request('max_age') }}" placeholder="Max. usia order">
                    </div>
                    <div class="col-md-2">
                        <label for="sort_by" class="form-label">Urutkan Berdasarkan</label>
                        <select name="sort_by" id="sort_by" class="form-select">
                            <option value="tanggal" {{ request('sort_by') == 'tanggal' ? 'selected' : '' }}>Tanggal Order</option>
                            <option value="order_number" {{ request('sort_by') == 'order_number' ? 'selected' : '' }}>No. Order</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort_order" class="form-label">Urutan</label>
                        <select name="sort_order" id="sort_order" class="form-select">
                            <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>Terbaru</option>
                            <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>Terlama</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="per_page" class="form-label">Per Halaman</label>
                        <select name="per_page" id="per_page" class="form-select">
                            <option value="15" {{ request('per_page') == '15' ? 'selected' : '' }}>15</option>
                            <option value="25" {{ request('per_page') == '25' ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <a href="{{ route('finance.unpaid-orders.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Data Order Belum Ada Pembayaran</h5>
                <span class="badge bg-primary">{{ $unpaidOrders->total() }} total order</span>
            </div>
        </div>
        <div class="card-body">
            @if($unpaidOrders->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No. Order</th>
                                <th>Platform</th>
                                <th>Tanggal Order</th>
                                <th>Total Items</th>
                                <th>Total Quantity</th>
                                <th>Total Order Value</th>
                                <th>Status</th>
                                <th>Usia Order</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidOrders as $order)
                                @php
                                    // Use pre-calculated values from SQL
                                    $totalItems = $order->total_items ?? 0;
                                    $totalQuantity = $order->total_quantity ?? 0;
                                    $totalValue = $order->total_value ?? 0;
                                    $daysSinceOrder = $order->days_since_order ?? 0;
                                    
                                    // Check if this order has partial return (retur sebagian)
                                    $isPartialReturn = isset($order->is_return_unpaid) && $order->is_return_unpaid;
                                @endphp
                                <tr class="{{ $daysSinceOrder > 30 ? 'table-warning' : '' }}">
                                    <td>
                                        <strong>{{ $order->order_number }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $order->platform->name ?? 'Unknown' }}</span>
                                    </td>
                                    <td>{{ $order->tanggal ? $order->tanggal->format('d/m/Y') : '-' }}</td>
                                    <td>{{ $totalItems }}</td>
                                    <td>{{ number_format($totalQuantity, 0, ',', '.') }}</td>
                                    <td>
                                        <strong>Rp {{ number_format($totalValue, 0, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        @if($isPartialReturn)
                                            <span class="badge bg-warning">{{ $order->unpaid_reason ?? 'RETUR SEBAGIAN' }}</span>
                                        @else
                                            <span class="badge bg-{{ $order->status == 'completed' ? 'success' : 'warning' }}">
                                                {{ $order->status ?? 'Belum Lunas' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($daysSinceOrder <= 7)
                                            <span class="badge bg-success">{{ $daysSinceOrder }} hari</span>
                                        @elseif($daysSinceOrder <= 14)
                                            <span class="badge bg-info">{{ $daysSinceOrder }} hari</span>
                                        @elseif($daysSinceOrder <= 21)
                                            <span class="badge bg-warning">{{ $daysSinceOrder }} hari</span>
                                        @elseif($daysSinceOrder <= 30)
                                            <span class="badge bg-orange">{{ $daysSinceOrder }} hari</span>
                                        @else
                                            <span class="badge bg-danger">{{ $daysSinceOrder }} hari</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="showOrderDetail('{{ $order->id }}')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="{{ route('sales.list', ['order_number' => $order->order_number]) }}" 
                                               class="btn btn-outline-info" target="_blank">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        {{ $unpaidOrders->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </nav>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>Tidak ada order yang belum ada pembayaran</h4>
                    <p class="text-muted">Semua order sudah memiliki data pembayaran atau tidak ada order yang memenuhi kriteria filter.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.bg-orange {
    background-color: #fd7e14 !important;
}
</style>

<script>
function showOrderDetail(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const modalContent = document.getElementById('orderDetailContent');
    
    // Set loading state
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat data...</p>
        </div>
    `;
    
    // Show the modal
    modal.show();
    
    // Fetch order details
    fetch(`/sales/orders/${orderId}/detail`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(html => {
            modalContent.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Gagal memuat detail pesanan: ${error.message}
                </div>
            `;
        });
}

// Auto-submit form when select elements change
document.querySelectorAll('#filterForm select').forEach(select => {
    select.addEventListener('change', () => {
        document.getElementById('filterForm').submit();
    });
});
</script>
@endsection 
