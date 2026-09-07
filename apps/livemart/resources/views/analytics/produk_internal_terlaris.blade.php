@extends('layouts.app')
@section('title', 'Produk Internal Terlaris')
@section('content')

<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-star me-2"></i>Produk Internal Terlaris
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.produk-internal-terlaris.export', request()->query()) }}" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('analytics.produk-internal-terlaris') }}" id="filter-form" class="mb-4">
                        <!-- Quick Filter Buttons -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Filter Cepat:</label>
                                <div class="btn-group" role="group" aria-label="Quick date filters">
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="7">
                                        7 Hari Terakhir
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="14">
                                        2 Minggu Terakhir
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="30">
                                        1 Bulan Terakhir
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm quick-filter" data-days="90">
                                        3 Bulan Terakhir
                                    </button>
                                </div>
                            </div>
                        </div>

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

                            <!-- Search -->
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari Produk</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Cari nama atau SKU produk" value="{{ $search }}">
                            </div>

                            <!-- Sort Options -->
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Urutkan Berdasarkan</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="quantity_highest" {{ $sortBy == 'quantity_highest' ? 'selected' : '' }}>
                                        Jumlah Terjual Tertinggi
                                    </option>
                                    <option value="quantity_lowest" {{ $sortBy == 'quantity_lowest' ? 'selected' : '' }}>
                                        Jumlah Terjual Terendah
                                    </option>
                                    <option value="order_count_highest" {{ $sortBy == 'order_count_highest' ? 'selected' : '' }}>
                                        Jumlah Order Tertinggi
                                    </option>
                                    <option value="order_count_lowest" {{ $sortBy == 'order_count_lowest' ? 'selected' : '' }}>
                                        Jumlah Order Terendah
                                    </option>
                                </select>
                            </div>

                            <!-- Limit -->
                            <div class="col-md-3">
                                <label for="limit" class="form-label">Jumlah Data per Halaman</label>
                                <select class="form-select" id="limit" name="limit">
                                    <option value="50" {{ $limit == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $limit == 100 ? 'selected' : '' }}>100</option>
                                    <option value="200" {{ $limit == 200 ? 'selected' : '' }}>200</option>
                                    <option value="500" {{ $limit == 500 ? 'selected' : '' }}>500</option>
                                </select>
                            </div>

                            <!-- Submit, Reset, and Export Button -->
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('analytics.produk-internal-terlaris') }}" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    @if($summary['total_products'] == 0)
                    <div class="alert alert-info my-4">
                        <h5 class="alert-heading">Tidak ada data</h5>
                        <p>Tidak ditemukan data penjualan{{ $startDate && $endDate ? ' untuk periode '.$startDate.' sampai '.$endDate : '' }}.</p>
                        @if($startDate && $endDate)
                        <p>Silakan ubah filter tanggal atau platform untuk melihat data yang tersedia.</p>
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
                                    <p>Jumlah produk internal berbeda</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Terjual</h5>
                                    <h2 class="display-5">{{ number_format($summary['total_quantity']) }}</h2>
                                    <p>pcs (setelah retur)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Retur</h5>
                                    <h2 class="display-5">{{ number_format($summary['total_returns']) }}</h2>
                                    <p>pcs</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Orders</h5>
                                    <h2 class="display-5">{{ number_format($summary['total_orders']) }}</h2>
                                    <p>jumlah pesanan</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <h5 class="mb-3">Daftar Produk Internal Terlaris</h5>
                    <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th width="50">No</th>
                                    <th>Nama Produk</th>
                                    <th>SKU</th>
                                    <th>Platform</th>
                                    <th class="text-end">Jumlah Terjual (pcs)</th>
                                    <th class="text-end">Retur (pcs)</th>
                                    <th class="text-end">Net Terjual (pcs)</th>
                                    <th class="text-end">Jumlah Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paginator as $index => $product)
                                <tr class="table-row-hover">
                                    <td class="text-center">{{ ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1 }}</td>
                                    <td><span class="fw-medium">{{ $product['product_name'] }}</span></td>
                                    <td>{{ $product['product_sku'] }}</td>
                                    <td><small>{{ $product['platforms'] ?: '-' }}</small></td>
                                    <td class="text-end">{{ number_format($product['total_quantity'], 0) }}</td>
                                    <td class="text-end text-danger">{{ number_format($product['qty_retur'], 0) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($product['net_quantity'], 0) }}</td>
                                    <td class="text-end">{{ number_format($product['order_count']) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">Tidak ada data produk</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <td colspan="4"><strong>TOTAL</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_quantity_with_returns'], 0) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_returns'], 0) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_quantity'], 0) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($summary['total_orders']) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if(method_exists($paginator, 'links'))
                    <div class="d-flex justify-content-between align-items-center mt-4">
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
    :root {
        --primary-color: #4361ee;
        --dark-color: #212529;
    }

    .table-dark th {
        background-color: var(--dark-color) !important;
        color: white !important;
        font-weight: 500;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .table-row-hover {
        transition: all 0.2s ease;
    }

    .table-row-hover:hover {
        background-color: rgba(99, 102, 241, 0.04) !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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
        overflow: hidden;
    }

    .quick-filter {
        transition: all 0.3s;
    }

    .quick-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .quick-filter.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
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
                const urlStartDate = urlParams.get('start_date');
                const urlEndDate = urlParams.get('end_date');

                if (!startDateInput.value && urlStartDate) {
                    startDateInput.value = urlStartDate;
                }
                if (!endDateInput.value && urlEndDate) {
                    endDateInput.value = urlEndDate;
                }
            }
        });

        const quickFilterButtons = document.querySelectorAll('.quick-filter');
        quickFilterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const days = parseInt(this.getAttribute('data-days'));
                const today = new Date();
                const startDate = new Date(today);
                startDate.setDate(today.getDate() - days);

                const startDateFormatted = startDate.getFullYear() + '-' +
                    String(startDate.getMonth() + 1).padStart(2, '0') + '-' +
                    String(startDate.getDate()).padStart(2, '0');
                const todayFormatted = new Date().toISOString().split('T')[0];

                startDateInput.value = startDateFormatted;
                endDateInput.value = todayFormatted;

                quickFilterButtons.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                this.classList.add('active');

                filterForm.submit();
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        const hasStartDate = urlParams.has('start_date');
        const hasEndDate = urlParams.has('end_date');

        const inputHasStartDate = startDateInput.value && startDateInput.value.trim() !== '';
        const inputHasEndDate = endDateInput.value && endDateInput.value.trim() !== '';

        if (!hasStartDate && !hasEndDate && !inputHasStartDate && !inputHasEndDate) {
            const todayFormatted = new Date().toISOString().split('T')[0];
            startDateInput.value = todayFormatted;
            endDateInput.value = todayFormatted;

            const isFormSubmission = document.referrer && document.referrer.includes('produk-internal-terlaris');
            if (!isFormSubmission) {
                setTimeout(function() {
                    filterForm.submit();
                }, 100);
            }
        }
    });
</script>
@endsection
