@extends('layouts.app')

@section('content')
@php
    use Shared\Helpers\NumberFormatter;
@endphp
<div class="container-fluid">
    <div class="ds-page-header">
        <div>
            <h1 class="text-gradient">Daftar Penjualan Offline</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Menu Penjualan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penjualan Offline</li>
                </ol>
            </nav>
            <p class="text-muted mb-0">Kategori: {{ session('main_category_name') }}</p>
        </div>
        <a href="{{ route('sales.offline.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Penjualan Baru
        </a>
    </div>

    <div class="ds-filter-card">
        <div class="ds-filter-title">Filter Pencarian</div>
        <form action="{{ route('sales.offline.list') }}" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="date_start" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="date_start" name="date_start" value="{{ request('date_start') }}">
                </div>
                <div class="col-md-3">
                    <label for="date_end" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="date_end" name="date_end" value="{{ request('date_end') }}">
                </div>
                <div class="col-md-3">
                    <label for="surat_jalan_number" class="form-label">Nomor Surat Jalan</label>
                    <input type="text" class="form-control" id="surat_jalan_number" name="surat_jalan_number" value="{{ request('surat_jalan_number') }}" placeholder="Cari nomor surat jalan...">
                </div>
                <div class="col-md-3">
                    <label for="No_PO" class="form-label">Nomor PO</label>
                    <input type="text" class="form-control" id="No_PO" name="No_PO" value="{{ request('No_PO') }}" placeholder="Cari nomor PO...">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('sales.offline.list') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Total Penjualan</h5>
                    <h2 class="display-5">{{ number_format($summary['total_sales']) }}</h2>
                    <p>
                        <i class="fas fa-calendar me-1"></i>
                        @if(request('date_start') && request('date_end'))
                            {{ \Carbon\Carbon::parse(request('date_start'))->format('d/m/Y') }} - {{ \Carbon\Carbon::parse(request('date_end'))->format('d/m/Y') }}
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
                    <p>Jumlah produk terjual</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Status Breakdown</h5>
                    <div class="d-flex justify-content-between">
                        <div class="text-center">
                            <div class="h6">{{ $summary['status_breakdown']['paid'] }}</div>
                            <small>Lunas</small>
                        </div>
                        <div class="text-center">
                            <div class="h6">{{ $summary['status_breakdown']['partial'] }}</div>
                            <small>Sebagian</small>
                        </div>
                        <div class="text-center">
                            <div class="h6">{{ $summary['status_breakdown']['pending'] }}</div>
                            <small>Pending</small>
                        </div>
                        <div class="text-center">
                            <div class="h6">{{ $summary['status_breakdown']['retur'] }}</div>
                            <small>Retur</small>
                        </div>
                        <div class="text-center">
                            <div class="h6">{{ $summary['status_breakdown']['cancelled'] }}</div>
                            <small>Batal</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="table-responsive disable-fixed-scrollbar">
                <table class="table table-hover">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">No. Surat Jalan</th>
                            <th scope="col">No. PO</th>
                            <th scope="col">Pelanggan</th>
                            <th scope="col">Total</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($offlineSales as $sale)
                        <tr>
                            <td>{{ $loop->iteration + ($offlineSales->currentPage() - 1) * $offlineSales->perPage() }}</td>
                            <td>{{ $sale->sale_date->format('d/m/Y') }}</td>
                            <td>{{ $sale->surat_jalan_number }}</td>
                            <td>{{ $sale->No_PO ?? '-' }}</td>
                            <td>{{ $sale->customer_name }}</td>
                            <td>
                                @php
                                    $hasReturFull = $sale->hasReturFull();
                                @endphp
                                @if ($hasReturFull)
                                    0
                                @else
                                    {{ number_format(\App\Helpers\NumberFormatter::roundToTwoDecimals($sale->tax_amount > 0 ? $sale->total_amount : $sale->subtotal), 0, ',', '.') }}
                                @endif
                            </td>
                            <td>
                                @php
                                    $paymentStatus = $sale->getPaymentStatus();
                                    $hasRetur = $sale->hasReturns();
                                @endphp
                                @if ($sale->status == 'cancelled')
                                    <span class="badge bg-danger">Dibatalkan</span>
                                @elseif ($hasRetur)
                                    @if ($hasReturFull)
                                        <span class="badge bg-danger">Retur Full</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Retur Sebagian</span>
                                    @endif
                                @elseif ($paymentStatus == 'paid')
                                    <span class="badge bg-success">Lunas</span>
                                @elseif ($paymentStatus == 'partial')
                                    <span class="badge bg-info">Sebagian</span>
                                @else
                                    <span class="badge bg-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{ route('sales.offline.show', $sale) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('sales.offline.print.sj', $sale) }}" class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-truck"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-3">Belum ada data penjualan offline</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        @if($offlineSales->hasPages())
        <div class="card-footer">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="small text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Menampilkan <strong>{{ $offlineSales->firstItem() }}</strong> - <strong>{{ $offlineSales->lastItem() }}</strong> dari <strong>{{ $offlineSales->total() }}</strong> data
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end justify-content-center">
                        {{ $offlineSales->appends(request()->query())->links('pagination.clean') }}
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="card-footer">
            <div class="small text-muted text-center">
                <i class="fas fa-info-circle me-1"></i>
                Menampilkan <strong>{{ $offlineSales->count() }}</strong> dari <strong>{{ $offlineSales->total() }}</strong> data
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .table-responsive {
        max-height: 65vh;
        overflow-y: auto;
        overflow-x: auto;
    }

    .table-responsive thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>
@endpush 