@extends('layouts.app')
@section('title', 'Sales Detail (Mapped)')
@section('content')

<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-file-export me-2"></i>Sales Detail (Mapped)
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.sales-export-mapped.export', request()->all()) }}" class="btn btn-sm btn-success text-white">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('analytics.sales-export-mapped') }}" id="filter-form" class="mb-5 p-3 bg-light rounded">
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

                            <!-- Search Box -->
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari No Order</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    value="{{ $search ?? '' }}" placeholder="Cari no order...">
                            </div>

                            <!-- Submit and Reset Button -->
                            <div class="col-md-3">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Filter
                                        </button>
                                        <a href="{{ route('analytics.sales-export-mapped') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Summary Cards -->
                    <div class="row mb-4 g-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem;">Total Order</h6>
                                            <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">{{ number_format($summary['total_orders_after_returns']) }}</h2>
                                        </div>
                                        <div class="icon-circle bg-white bg-opacity-20 text-white"><i class="bi bi-cart" style="font-size: 1.5rem;"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem;">Total Value</h6>
                                            <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                                        </div>
                                        <div class="icon-circle bg-white bg-opacity-20 text-white"><i class="bi bi-cash-coin" style="font-size: 1.5rem;"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order List -->
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-dark sticky-top">
                                <tr class="text-center">
                                    <th width="40">No</th>
                                    <th width="100">Tanggal</th>
                                    <th width="80">Hari</th>
                                    <th width="150">No Order</th>
                                    <th width="100">Platform</th>
                                    <th>Nama Barang (Platform)</th>
                                    <th width="80">Varian</th>
                                    <th width="60">Qty</th>
                                    <th width="70">QTY Retur</th>
                                    <th width="100">Harga</th>
                                    <th width="120">Total Item</th>

                                    <!-- Internal Product Columns -->
                                    <th class="table-info">Nama Barang (Internal)</th>
                                    <th class="table-info" width="100">SKU (Internal)</th>
                                    <th class="table-info" width="80">Qty (Internal)</th>

                                    <th width="80">Qty Total</th>
                                    <th width="120">Total Invoice</th>
                                    <th width="120">No Resi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $no = ($orders->currentPage() - 1) * $orders->perPage() + 1;
                                @endphp

                                @forelse($orders as $order)
                                    @php
                                        // Calculate Order Rowspan
                                        $orderRowspan = 0;
                                        foreach($order->orderItems as $item) {
                                            $count = 0;
                                            if ($item->platformProduct && $item->platformProduct->mappingBarang) {
                                                $count = $item->platformProduct->mappingBarang->where('is_active', true)->count();
                                            }
                                            $orderRowspan += ($count > 0 ? $count : 1);
                                        }

                                        // Order Totals
                                        $totalOrderValue = 0;
                                        $totalOrderVolume = 0;
                                        foreach($order->orderItems as $item) {
                                            $totalOrderValue += ($item->price_after_discount * $item->quantity);
                                            $totalOrderVolume += $item->quantity;
                                        }

                                        $isFirstOrderRow = true;
                                    @endphp

                                    @foreach($order->orderItems as $item)
                                        @php
                                            $mappings = collect([]);
                                            if ($item->platformProduct && $item->platformProduct->mappingBarang) {
                                                $mappings = $item->platformProduct->mappingBarang->where('is_active', true);
                                            }

                                            $itemRowspan = $mappings->count() > 0 ? $mappings->count() : 1;
                                            $isFirstItemRow = true;
                                        @endphp

                                        @if($mappings->count() > 0)
                                            @foreach($mappings as $mapping)
                                                <tr>
                                                    @if($isFirstOrderRow)
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $no++ }}</td>
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->tanggal ? $order->tanggal->format('d-m-Y') : '-' }}</td>
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->hari }}</td>
                                                        <td rowspan="{{ $orderRowspan }}" class="align-middle">
                                                            {{ $order->order_number }}
                                                            @if($order->status == 'Batal') <span class="badge bg-danger">Batal</span> @endif
                                                        </td>
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->platform->name }}</td>
                                                    @endif

                                                    @if($isFirstItemRow)
                                                        <td rowspan="{{ $itemRowspan }}">{{ $item->platformProduct ? $item->platformProduct->platform_product_name : '-' }}</td>
                                                        <td rowspan="{{ $itemRowspan }}">{{ $item->platformProduct ? $item->platformProduct->variant : '-' }}</td>
                                                        <td rowspan="{{ $itemRowspan }}" class="text-center">{{ $item->quantity }}</td>
                                                        <td rowspan="{{ $itemRowspan }}" class="text-center">{{ $item->returPenjualanDetails->sum('qty') }}</td>
                                                        <td rowspan="{{ $itemRowspan }}" class="text-end">Rp {{ number_format($item->price_after_discount, 0, ',', '.') }}</td>
                                                        <td rowspan="{{ $itemRowspan }}" class="text-end">Rp {{ number_format($item->price_after_discount * $item->quantity, 0, ',', '.') }}</td>
                                                    @endif

                                                    <!-- Internal Columns -->
                                                    <td class="table-info">{{ $mapping->product ? $mapping->product->name : 'Product Not Found' }}</td>
                                                    <td class="table-info text-center">{{ $mapping->product ? $mapping->product->sku : '-' }}</td>
                                                    <td class="table-info text-center">{{ number_format($mapping->quantity * $item->quantity, 2) }}</td>

                                                    @if($isFirstOrderRow)
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ number_format($totalOrderVolume) }}</td>
                                                        <td rowspan="{{ $orderRowspan }}" class="text-end align-middle">Rp {{ number_format($totalOrderValue, 0, ',', '.') }}</td>
                                                        <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->orderItems->pluck('tracking_number')->filter()->first() ?? '-' }}</td>
                                                        @php $isFirstOrderRow = false; @endphp
                                                    @endif

                                                    @php $isFirstItemRow = false; @endphp
                                                </tr>
                                            @endforeach
                                        @else
                                            <!-- No Mapping Case -->
                                            <tr>
                                                @if($isFirstOrderRow)
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $no++ }}</td>
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->tanggal ? $order->tanggal->format('d-m-Y') : '-' }}</td>
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->hari }}</td>
                                                    <td rowspan="{{ $orderRowspan }}" class="align-middle">
                                                        {{ $order->order_number }}
                                                        @if($order->status == 'Batal') <span class="badge bg-danger">Batal</span> @endif
                                                    </td>
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->platform->name }}</td>
                                                @endif

                                                <td>{{ $item->platformProduct ? $item->platformProduct->platform_product_name : '-' }}</td>
                                                <td>{{ $item->platformProduct ? $item->platformProduct->variant : '-' }}</td>
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-center">{{ $item->returPenjualanDetails->sum('qty') }}</td>
                                                <td class="text-end">Rp {{ number_format($item->price_after_discount, 0, ',', '.') }}</td>
                                                <td class="text-end">Rp {{ number_format($item->price_after_discount * $item->quantity, 0, ',', '.') }}</td>

                                                <!-- Empty Internal Columns -->
                                                <td class="table-info text-muted">Belum dimapping</td>
                                                <td class="table-info text-center">-</td>
                                                <td class="table-info text-center">-</td>

                                                @if($isFirstOrderRow)
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ number_format($totalOrderVolume) }}</td>
                                                    <td rowspan="{{ $orderRowspan }}" class="text-end align-middle">Rp {{ number_format($totalOrderValue, 0, ',', '.') }}</td>
                                                    <td rowspan="{{ $orderRowspan }}" class="text-center align-middle">{{ $order->orderItems->pluck('tracking_number')->filter()->first() ?? '-' }}</td>
                                                    @php $isFirstOrderRow = false; @endphp
                                                @endif
                                            </tr>
                                        @endif
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="16" class="text-center py-4">Tidak ada data ditemukan</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-end mt-3">
                        {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
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

    .table th {
        background-color: #212529;
        color: white;
        font-weight: 500;
        vertical-align: middle;
        white-space: nowrap;
    }

    .table-info {
        background-color: #e7f1ff !important;
    }

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

    .table-responsive {
        border-radius: 8px;
        overflow-x: auto;
        overflow-y: auto;
    }

    thead tr th {
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
    }

    #filter-form {
        border-left: 4px solid var(--primary-color);
    }
</style>
@endpush
