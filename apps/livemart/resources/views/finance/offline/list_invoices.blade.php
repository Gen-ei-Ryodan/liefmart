@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-file-invoice-dollar me-2"></i>{{ __('Daftar Invoice Offline') }}
                    </h5>
                    <div>
                        <a href="{{ route('finance.offline.export', request()->query()) }}" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </a>
                        <a href="{{ route('finance.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Session Messages -->
                    @if(session('success') || session('error'))
                        <div class="alert alert-{{ session('success') ? 'success' : 'danger' }} alert-dismissible fade show mb-4" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-{{ session('success') ? 'check-circle' : 'exclamation-circle' }} me-2"></i>
                                <strong>{{ session('success') ? 'Sukses!' : 'Error!' }}</strong>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <p class="mb-0 mt-2">{!! session('success') ?? session('error') !!}</p>
                        </div>
                    @endif

                    <!-- Filter Card -->
                    <div class="card bg-light mb-4 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i> Filter Data</h6>
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="filterCollapse">
                            <div class="card-body py-3">
                                <form action="{{ route('finance.offline.invoices') }}" method="GET" class="row g-2">
                                    <div class="col-md-3">
                                        <label for="date_start" class="form-label small">Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-sm" id="date_start" name="date_start" value="{{ request('date_start') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_end" class="form-label small">Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-sm" id="date_end" name="date_end" value="{{ request('date_end') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="invoice_number" class="form-label small">No. Invoice</label>
                                        <input type="text" class="form-control form-control-sm" id="invoice_number" name="invoice_number" placeholder="Cari..." value="{{ request('invoice_number') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="sj_number" class="form-label small">No. SJ</label>
                                        <input type="text" class="form-control form-control-sm" id="sj_number" name="sj_number" placeholder="Cari..." value="{{ request('sj_number') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="customer" class="form-label small">Customer</label>
                                        <input type="text" class="form-control form-control-sm" id="customer" name="customer" placeholder="Cari..." value="{{ request('customer') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="payment_status" class="form-label small">Status Pembayaran</label>
                                        <select class="form-select form-select-sm" id="payment_status" name="payment_status">
                                            <option value="">Semua</option>
                                            <option value="lunas" {{ request('payment_status') == 'lunas' ? 'selected' : '' }}>Lunas</option>
                                            <option value="belum_lunas" {{ request('payment_status') == 'belum_lunas' ? 'selected' : '' }}>Belum Lunas</option>
                                            <option value="retur_full" {{ request('payment_status') == 'retur_full' ? 'selected' : '' }}>Retur Full</option>
                                            <option value="tidak_balance" {{ request('payment_status') == 'tidak_balance' ? 'selected' : '' }}>Tidak Balance</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="tax_id" class="form-label small">Tax ID</label>
                                        <select class="form-select form-select-sm" id="tax_id" name="tax_id">
                                            <option value="">Semua</option>
                                            <option value="3" {{ request('tax_id') == '3' ? 'selected' : '' }}>PKP</option>
                                            <option value="4" {{ request('tax_id') == '4' ? 'selected' : '' }}>Non-PKP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="main_category_id" class="form-label small">Kategori Utama</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light border-0">
                                                <i class="fas fa-layer-group text-primary"></i>
                                            </span>
                                            <input type="text" class="form-control bg-light border-0 ps-1" value="{{ session('main_category_name', 'Semua Kategori') }}" readonly>
                                            <input type="hidden" name="main_category_id" id="main_category_id" value="{{ session('main_category_id') }}">
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="form-label small text-muted d-block">Quick Dates</label>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary btn-date" data-days="7">7 Hari</button>
                                            <button type="button" class="btn btn-outline-secondary btn-date" data-days="30">30 Hari</button>
                                            <button type="button" class="btn btn-outline-secondary btn-date" data-days="90">90 Hari</button>
                                            <button type="button" class="btn btn-outline-secondary btn-month" data-month="current">Bulan Ini</button>
                                        </div>
                                    </div>
                                    <div class="col-12 text-end mt-3">
                                        <a href="{{ route('finance.offline.invoices') }}" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-redo me-1"></i> Reset
                                        </a>
                                        <button type="submit" class="btn btn-sm btn-primary ms-2">
                                            <i class="fas fa-search me-1"></i> Cari
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <!-- Row 1: Main Overview -->
                        <div class="col-md-3">
                            <div class="card bg-primary text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title mb-2"><i class="fas fa-file-invoice me-2"></i>Total Invoice</h6>
                                    <h2 class="display-6 mb-2">{{ number_format($summary['total_invoices']) }}</h2>
                                    <small class="text-white-50">
                                        <i class="fas fa-calendar me-1"></i>
                                        @if(request('date_start') && request('date_end'))
                                            {{ \Carbon\Carbon::parse(request('date_start'))->format('d/m/Y') }} - {{ \Carbon\Carbon::parse(request('date_end'))->format('d/m/Y') }}
                                        @else
                                            Semua Periode
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title mb-2"><i class="fas fa-calculator me-2"></i>GRAND TOTAL</h6>
                                    <h2 class="display-6 mb-2">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                                    <small class="text-white-50">Rata-rata: Rp {{ number_format($summary['avg_invoice_value'], 0, ',', '.') }}/invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title mb-2"><i class="fas fa-money-bill-wave me-2"></i>Total Terbayar</h6>
                                    <h2 class="display-6 mb-2">Rp {{ number_format($summary['total_paid'], 0, ',', '.') }}</h2>
                                    <small class="text-white-50">Jumlah sudah diterima</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white h-100 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title mb-2"><i class="fas fa-exclamation-circle me-2"></i>Sisa Tagihan</h6>
                                    <h2 class="display-6 mb-2">Rp {{ number_format($summary['total_unpaid'], 0, ',', '.') }}</h2>
                                    <small class="text-white-50">Masih harus dibayar</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Row 2: Status Counts -->
                        <div class="col-md-2">
                            <div class="card bg-success text-white h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-check-circle me-1"></i>Lunas</h6>
                                    <h3 class="mb-1">{{ $summary['paid_count'] }}</h3>
                                    <small class="text-white-50">Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-info text-white h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-hourglass-half me-1"></i>Sebagian</h6>
                                    <h3 class="mb-1">{{ $summary['partial_count'] ?? 0 }}</h3>
                                    <small class="text-white-50">Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-dark h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-clock me-1"></i>Belum Bayar</h6>
                                    <h3 class="mb-1">{{ $summary['unpaid_count'] }}</h3>
                                    <small class="text-muted">Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-secondary text-white h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-undo me-1"></i>Retur Full</h6>
                                    <h3 class="mb-1">{{ $summary['retur_full_count'] ?? 0 }}</h3>
                                    <small class="text-white-50">Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-dark h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-balance-scale me-1"></i>Tidak Balance</h6>
                                    <h3 class="mb-1">{{ $summary['tidak_balance_count'] ?? 0 }}</h3>
                                    <small class="text-muted">Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-danger text-white h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <h6 class="card-title mb-2"><i class="fas fa-arrow-left me-1"></i>Total Retur</h6>
                                    <h4 class="mb-1">Rp {{ number_format($summary['total_retur'] ?? 0, 0, ',', '.') }}</h4>
                                    <small class="text-white-50">Jumlah diretur</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="card shadow-sm border-0 mt-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0 fw-bold text-primary">
                                <i class="fas fa-list me-2"></i> Daftar Invoice
                            </h6>
                            <span class="badge bg-primary">{{ $invoices->total() }} data</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                                <table class="table table-bordered table-hover table-striped mb-0">
                                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr class="bg-white">
                                            <th width="40" class="text-center">#</th>
                                            <th width="130">No. Invoice</th>
                                            <th width="90">Tanggal</th>
                                            <th width="180">No. SJ</th>
                                            <th width="70">Tax ID</th>
                                            <th width="100">Kategori</th>
                                            <th>Customer</th>
                                            <th width="120" class="text-end">DPP (Rp)</th>
                                            <th width="120" class="text-end">Retur (Rp)</th>
                                            <th width="120" class="text-end">Net (Rp)</th>
                                            <th width="120" class="text-end">PPN (Rp)</th>
                                            <th width="120" class="text-end">Total (Rp)</th>
                                            <th width="120" class="text-end">Dibayar (Rp)</th>
                                            <th width="120" class="text-end">Sisa Tagihan (Rp)</th>
                                            <th width="90" class="text-center">Status</th>
                                            <th width="80" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($invoices as $index => $invoice)
                                        @php
                                            // Satu sumber kebenaran untuk perhitungan nilai finansial
                                            $calcs = \App\Services\FinanceOfflineCalculator::calculate($invoice);

                                            $firstItem = $invoice->barangKeluarItems->first();
                                            $taxId = $calcs['tax_id'];
                                            $dppOriginal = $calcs['dpp_original'];
                                            $returAmount = $calcs['retur_amount'];
                                            $netDPP = $calcs['net_dpp'];
                                            $netPPN = $calcs['net_ppn'];
                                            $netTotal = $calcs['net_total'];
                                            $totalPaid = $calcs['total_paid'];
                                            $remainingAmount = $calcs['remaining_amount'];
                                            $isTidakBalance = $calcs['is_tidak_balance'];
                                            $isFullyPaid = $calcs['is_fully_paid'];
                                            $hasPartialReturn = $calcs['has_partial_return'];
                                            $dbStatus = $calcs['db_status'];

                                            // Get SJ numbers, tax_id and customer
                                            $sjNumber = '-';
                                            $customer = '-';
                                            $taxBadgeClass = 'bg-secondary';
                                            $taxLabel = '-';

                                            if ($firstItem) {
                                                if ($firstItem->offlineSaleItem && $firstItem->offlineSaleItem->offlineSale) {
                                                    $sjNumber = $firstItem->offlineSaleItem->offlineSale->surat_jalan_number;
                                                    $customer = $firstItem->offlineSaleItem->offlineSale->customer_name;
                                                }

                                                if ($taxId == 3) {
                                                    $taxBadgeClass = 'bg-primary';
                                                    $taxLabel = 'PKP';
                                                } elseif ($taxId == 4) {
                                                    $taxBadgeClass = 'bg-info';
                                                    $taxLabel = 'Non-PKP';
                                                }
                                            }

                                            // Jika status refunded/retur_full, force semua nilai finansial ke 0
                                            if ($calcs['is_retur_full']) {
                                                $dppOriginal = 0;
                                                $returAmount = 0;
                                                $netDPP = 0;
                                                $netPPN = 0;
                                                $netTotal = 0;
                                                $remainingAmount = 0;
                                                $totalPaid = 0;
                                            }

                                            if ($calcs['is_retur_full']) {
                                                $statusBadgeClass = 'bg-secondary';
                                                $statusLabel = 'Retur Full';
                                            } elseif ($isFullyPaid) {
                                                $statusBadgeClass = 'bg-success';
                                                $statusLabel = $hasPartialReturn ? 'Lunas (Retur Sebagian)' : 'Lunas';
                                            } else {
                                                $statusBadgeClass = $totalPaid > 0 ? 'bg-warning text-dark' : 'bg-danger';
                                                $statusLabel = $hasPartialReturn ? 'Belum Lunas (Retur Sebagian)' : 'Belum Lunas';
                                            }
                                        @endphp
                                        <tr data-invoice-id="{{ $invoice->id }}" data-is-fully-paid="{{ $isFullyPaid ? 1 : 0 }}">
                                            <td class="text-center">{{ $invoices->firstItem() + $index }}</td>
                                            <td><span class="badge bg-success">{{ $invoice->invoice_number }}</span></td>
                                            <td>{{ $invoice->tanggal_invoice->format('d/m/Y') }}</td>
                                            <td>{{ $sjNumber }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $taxBadgeClass }}">{{ $taxLabel }}</span>
                                            </td>
                                            <td>
                                                @php
                                                    $mainCategoryName = 'N/A';
                                                    if ($firstItem && $firstItem->offlineSaleItem && $firstItem->offlineSaleItem->offlineSale && $firstItem->offlineSaleItem->offlineSale->mainCategory) {
                                                        $mainCategoryName = $firstItem->offlineSaleItem->offlineSale->mainCategory->name;
                                                    } elseif ($firstItem && $firstItem->warehouseStock && $firstItem->warehouseStock->product && $firstItem->warehouseStock->product->mainCategory) {
                                                        $mainCategoryName = $firstItem->warehouseStock->product->mainCategory->name;
                                                    } elseif (session()->has('main_category_name')) {
                                                        $mainCategoryName = session('main_category_name');
                                                    }
                                                @endphp
                                                {{ $mainCategoryName }}
                                            </td>
                                            <td>{{ Str::limit($customer, 20) }}</td>
                                            <td class="text-end">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($dppOriginal) }}</td>
                                            <td class="text-end">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($returAmount) }}</td>
                                            <td class="text-end"><strong>{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($netDPP) }}</strong></td>
                                            <td class="text-end">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($netPPN) }}</td>
                                            <td class="text-end"><strong>{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($netTotal) }}</strong></td>
                                            <td class="text-end">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($totalPaid) }}</td>
                                            <td class="text-end">{{ \App\Helpers\NumberFormatter::formatInvoiceAmount($remainingAmount) }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                                @if($isTidakBalance)
                                                    <br><small class="text-muted">Tidak Balance</small>
                                                @endif
                                                @if($invoice->print_count > 0)
                                                <br><small class="text-muted">Dicetak {{ $invoice->print_count }}x</small>
                                                @endif
                                                @if($invoice->reprint_requested && !$invoice->reprint_approved)
                                                    <br><span class="badge bg-warning text-dark">Menunggu Persetujuan</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button id="action-btn-{{ $invoice->id }}" 
                                                        class="btn btn-sm btn-light action-btn"
                                                        type="button"
                                                        aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="14" class="text-center py-4">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                                    <h6 class="fw-normal mb-1">Tidak ada data invoice</h6>
                                                    <p class="text-muted small">Tidak ada invoice yang sesuai dengan kriteria pencarian</p>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($invoices->hasPages())
                            <div class="card-footer bg-white border-top">
                                <div class="row align-items-center">
                                    <div class="col-md-6 mb-2 mb-md-0">
                                        <div class="small text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Menampilkan <strong>{{ $invoices->firstItem() }}</strong> - <strong>{{ $invoices->lastItem() }}</strong> dari <strong>{{ $invoices->total() }}</strong> data
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-md-end justify-content-center">
                                            {{ $invoices->appends(request()->query())->links('pagination.clean') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="card-footer bg-white border-top">
                                <div class="small text-muted text-center">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Menampilkan <strong>{{ $invoices->count() }}</strong> dari <strong>{{ $invoices->total() }}</strong> data
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal section - place these outside the table but within the main container div -->
@forelse($invoices as $invoice)
@php
    // Satu sumber kebenaran untuk perhitungan nilai finansial
    $calcs = \App\Services\FinanceOfflineCalculator::calculate($invoice);

    $firstItem = $invoice->barangKeluarItems->first();
    $taxId = $calcs['tax_id'];
    $netTotalModal = $calcs['net_total'];
    $totalPaid = $calcs['total_paid'];
    $remainingAmount = $calcs['remaining_amount'];
    $isTidakBalance = $calcs['is_tidak_balance'];
    $paymentAdjustmentNeeded = $isTidakBalance ? ($totalPaid - $netTotalModal) : 0;
    $isFullyPaidModal = $calcs['is_fully_paid'];
    $hasPartialReturnModal = $calcs['has_partial_return'];
    $dbStatusModal = $calcs['db_status'];

    // Get SJ numbers, tax_id and customer
    $sjNumber = '-';
    $customer = '-';
    $taxBadgeClass = 'bg-secondary';
    $taxLabel = '-';

    if ($firstItem) {
        if ($firstItem->offlineSaleItem && $firstItem->offlineSaleItem->offlineSale) {
            $sjNumber = $firstItem->offlineSaleItem->offlineSale->surat_jalan_number;
            $customer = $firstItem->offlineSaleItem->offlineSale->customer_name;
        }

        if ($taxId == 3) {
            $taxBadgeClass = 'bg-primary';
            $taxLabel = 'PKP';
        } elseif ($taxId == 4) {
            $taxBadgeClass = 'bg-info';
            $taxLabel = 'Non-PKP';
        }
    }

    // Jika status refunded/retur_full, force semua nilai finansial ke 0
    if ($calcs['is_retur_full']) {
        $netTotalModal = 0;
        $totalPaid = 0;
        $remainingAmount = 0;
        $paymentAdjustmentNeeded = 0;
        $isTidakBalance = false;
    }

    if ($calcs['is_retur_full']) {
        $statusBadgeClass = 'bg-secondary';
        $statusLabel = 'Retur Full';
    } elseif ($isFullyPaidModal) {
        $statusBadgeClass = 'bg-success';
        $statusLabel = $hasPartialReturnModal ? 'Lunas (Retur Sebagian)' : 'Lunas';
    } else {
        $statusBadgeClass = $totalPaid > 0 ? 'bg-warning text-dark' : 'bg-danger';
        $statusLabel = $hasPartialReturnModal ? 'Belum Lunas (Retur Sebagian)' : 'Belum Lunas';
    }
@endphp

<!-- Modal Pembayaran -->
<div class="modal fade" id="modalBayar{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('finance.offline.pay', $invoice->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Pembayaran Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="row mb-3">
                        <div class="col-12 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">No. Invoice:</span>
                                <span class="fw-bold">{{ $invoice->invoice_number }}</span>
                            </div>
                        </div>
                        <div class="col-12 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total Tagihan:</span>
                                <span class="fw-bold">Rp {{ number_format($netTotalModal, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Sisa Tagihan:</span>
                                <span class="fw-bold text-danger">Rp {{ number_format($remainingAmount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    <hr>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nominal Pembayaran</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" name="payment_amount" class="form-control" required min="1" step="1" max="{{ $remainingAmount }}" value="{{ $remainingAmount }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Pembayaran</label>
                    <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="payment_method" class="form-select">
                        <option value="transfer">Transfer Bank</option>
                        <option value="cash">Tunai</option>
                        <option value="check">Cek/Giro</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Catatan (Opsional)</label>
                    <textarea name="payment_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle me-1"></i> Bayar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail{{ $invoice->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice me-2"></i> Detail Invoice
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($isTidakBalance)
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Tidak Balance!</h6>
                    <p class="mb-2">Pembayaran melebihi Net Total setelah retur.</p>
                    <hr>
                    <p class="mb-1"><strong>Net Total (setelah retur):</strong> Rp {{ number_format($netTotalModal, 0, ',', '.') }}</p>
                    <p class="mb-1"><strong>Total Dibayar:</strong> Rp {{ number_format($totalPaid, 0, ',', '.') }}</p>
                    <p class="mb-2"><strong>Kelebihan Pembayaran:</strong> Rp {{ number_format($paymentAdjustmentNeeded, 0, ',', '.') }}</p>
                    <p class="mb-2 small">Pembayaran perlu disesuaikan menjadi <strong>Rp {{ number_format($netTotalModal, 0, ',', '.') }}</strong> untuk menyeimbangkan invoice.</p>
                    @if(Auth::check() && Auth::user()->isSuperAdmin())
                    <form action="{{ route('finance.offline.adjust-payment', $invoice->id) }}" method="POST" class="mt-2">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Apakah Anda yakin ingin menyesuaikan pembayaran? Kelebihan pembayaran sebesar Rp {{ number_format($paymentAdjustmentNeeded, 0, ',', '.') }} akan dikurangi.')">
                            <i class="fas fa-balance-scale me-1"></i> Sesuaikan Pembayaran
                        </button>
                    </form>
                    @endif
                </div>
                @endif
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <small class="text-muted d-block">No. Invoice</small>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success me-2">{{ $invoice->invoice_number }}</span>
                                        <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Tanggal Invoice</small>
                                    <strong>{{ $invoice->tanggal_invoice->format('d F Y') }}</strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Nomor SJ</small>
                                    <strong>{{ $sjNumber }}</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <small class="text-muted d-block">Customer</small>
                                    <strong>{{ $customer }}</strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Status Pajak</small>
                                    <span class="badge {{ $taxBadgeClass }}">{{ $taxLabel }}</span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Total Invoice</small>
                                    <strong>Rp {{ number_format($netTotalModal, 0, ',', '.') }}</strong>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Status Cetak</small>
                                    @if($invoice->print_count === 0)
                                        <span class="badge bg-info">Belum Dicetak</span>
                                    @else
                                        <span class="badge bg-secondary">Dicetak {{ $invoice->print_count }}x</span>
                                        @if($invoice->last_printed_at)
                                            <small class="text-muted d-block mt-1">Terakhir: {{ $invoice->last_printed_at->format('d/m/Y H:i') }}</small>
                                        @endif
                                        @if($invoice->reprint_requested && !$invoice->reprint_approved)
                                            <span class="badge bg-warning text-dark mt-1 d-inline-block">Menunggu Persetujuan</span>
                                        @elseif($invoice->reprint_approved)
                                            <span class="badge bg-success mt-1 d-inline-block">Disetujui untuk Cetak Ulang</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($invoice->payments->count() > 0)
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i> Riwayat Pembayaran
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Metode</th>
                                        <th class="text-end">Nominal</th>
                                        <th>Catatan</th>
                                        @if(Auth::check() && Auth::user()->isSuperAdmin())
                                        <th class="text-center">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoice->payments as $payment)
                                    <tr class="{{ $payment->amount < 0 ? 'table-warning' : '' }}">
                                        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                        <td>
                                            @if($payment->payment_method == 'adjustment')
                                                <i class="fas fa-balance-scale text-warning me-1"></i> <strong>Penyesuaian</strong>
                                            @elseif($payment->payment_method == 'transfer')
                                                <i class="fas fa-university text-primary me-1"></i> Transfer
                                            @elseif($payment->payment_method == 'cash')
                                                <i class="fas fa-money-bill text-success me-1"></i> Tunai
                                            @elseif($payment->payment_method == 'check')
                                                <i class="fas fa-money-check text-info me-1"></i> Cek/Giro
                                            @else
                                                {{ ucfirst($payment->payment_method) }}
                                            @endif
                                        </td>
                                        <td class="text-end {{ $payment->amount < 0 ? 'text-danger fw-bold' : '' }}">
                                            @if($payment->amount < 0)
                                                - Rp {{ number_format(abs(round($payment->amount)), 0, ',', '.') }}
                                            @else
                                                Rp {{ number_format(round($payment->amount), 0, ',', '.') }}
                                            @endif
                                        </td>
                                        <td>{{ $payment->notes ?? '-' }}</td>
                                        @if(Auth::check() && Auth::user()->isSuperAdmin())
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDeletePayment({{ $payment->id }}, '{{ number_format(round($payment->amount), 0, ',', '.') }}')"
                                                data-bs-toggle="tooltip" 
                                                title="Hapus Pembayaran">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="2">Total Dibayar</th>
                                        <th class="text-end">Rp {{ number_format($totalPaid, 0, ',', '.') }}</th>
                                        <th></th>
                                        @if(Auth::check() && Auth::user()->isSuperAdmin())
                                        <th></th>
                                        @endif
                                    </tr>
                                    <tr>
                                        <th colspan="2">Sisa</th>
                                        <th class="text-end {{ $remainingAmount > 0 ? 'text-danger' : 'text-success' }}">
                                            Rp {{ number_format($remainingAmount, 0, ',', '.') }}
                                        </th>
                                        <th></th>
                                        @if(Auth::check() && Auth::user()->isSuperAdmin())
                                        <th></th>
                                        @endif
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                @else
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Belum ada pembayaran untuk invoice ini
                </div>
                @endif
            </div>
            <div class="modal-footer">
                @if($invoice->status != 'paid')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBayar{{ $invoice->id }}" data-bs-dismiss="modal">
                    <i class="fas fa-money-bill-wave me-1"></i> Bayar
                </button>
                @endif
                
                @if($invoice->reprint_requested && !$invoice->reprint_approved && auth()->user()->isSuperAdmin())
                <form action="{{ route('finance.offline.approve-reprint', $invoice->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check-circle me-1"></i> Setujui Cetak Ulang
                    </button>
                </form>
                @endif
                
                @if($invoice->print_count === 0 || auth()->user()->isSuperAdmin() || $invoice->reprint_approved)
                <a href="{{ route('finance.offline.print-invoice', $invoice->id) }}" class="btn btn-success" target="_blank">
                    <i class="fas fa-print me-1"></i> Cetak Invoice
                </a>
                @elseif($invoice->print_count > 0 && !$invoice->reprint_requested)
                <button type="button" class="btn btn-warning" onclick="requestReprint({{ $invoice->id }})">
                    <i class="fas fa-print me-1"></i> Minta Cetak Ulang
                </button>
                @else
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="fas fa-print me-1"></i> Menunggu Persetujuan
                </button>
                @endif
                

                
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@empty
@endforelse
@endsection

@push('styles')
<style>
    .table {
        font-size: 13px;
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .table th {
        font-weight: 600;
        padding: 10px 8px;
        border: 1px solid #e3e6f0;
        background-color: #f8f9fa;
        vertical-align: middle;
    }
    
    .table td {
        padding: 8px;
        border: 1px solid #e3e6f0;
        vertical-align: middle;
    }
    
    .dropdown-menu {
        font-size: 13px;
        min-width: 10rem;
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        border-radius: 0.5rem;
    }
    
    .dropdown-item {
        padding: 0.5rem 1rem;
    }
    
    .badge {
        font-weight: 500;
        font-size: 85%;
    }
    
    .btn-icon {
        padding: 0.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.25rem;
        width: 32px;
        height: 32px;
    }
    
    /* Custom action menu */
    .action-menu {
        position: absolute;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        z-index: 1050;
        min-width: 180px;
        display: none;
        padding: 8px 0;
    }
    
    .action-menu-item {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        cursor: pointer;
        color: #333;
        text-decoration: none;
    }
    
    .action-menu-item:hover {
        background-color: #f8f9fa;
    }
    
    .action-menu-item i {
        margin-right: 10px;
        width: 16px;
        text-align: center;
    }
    
    .action-menu hr {
        margin: 8px 0;
        border-top: 1px solid #e9ecef;
    }
    
    .dropdown-header {
        padding: 8px 16px 4px 16px;
        font-weight: 600;
        font-size: 0.875rem;
        color: #6c757d;
        border-bottom: 1px solid #e9ecef;
        margin-bottom: 4px;
    }
    
    .dropdown-item-group {
        background-color: #f8f9fa;
        margin: 4px 0;
        border-radius: 4px;
    }
    
    /* Pagination styling */
    .pagination {
        margin-bottom: 0;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .pagination .page-link {
        color: #495057;
        background-color: #fff;
        border: 1px solid #dee2e6;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        min-width: 38px;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .pagination .page-link:hover:not(.disabled):not(.active) {
        color: #0056b3;
        background-color: #e9ecef;
        border-color: #dee2e6;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
        font-weight: 600;
        z-index: 1;
    }
    
    .pagination .page-item.active .page-link:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
    }
    
    .pagination .page-item.disabled .page-link {
        color: #6c757d;
        background-color: #fff;
        border-color: #dee2e6;
        cursor: not-allowed;
        opacity: 0.5;
    }
    
    .pagination .page-item.disabled .page-link:hover {
        transform: none;
        box-shadow: none;
    }
    
    .pagination .page-link i {
        font-size: 0.75rem;
    }
    
    .card-footer {
        padding: 1rem 1.5rem;
    }
    
    /* Media queries for responsive design */
    @media (max-width: 992px) {
        .table {
            font-size: 12px;
        }
        
        .table th, 
        .table td {
            padding: 8px 5px;
        }
        
        .pagination .page-link {
            padding: 0.375rem 0.5rem;
            font-size: 0.8125rem;
        }
    }
    
    @media (max-width: 768px) {
        .table {
            font-size: 11px;
        }
        
        .table th, 
        .table td {
            padding: 6px 4px;
        }
        
        .pagination {
            flex-wrap: wrap;
        }
        
        .pagination .page-link {
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
        }
        
        .card-footer {
            padding: 0.75rem 1rem;
        }
        
        .card-footer .row > div {
            text-align: center !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Keep filter collapse state in session storage
        const filterCollapse = document.getElementById('filterCollapse');
        const bsCollapse = new bootstrap.Collapse(filterCollapse, {
            toggle: localStorage.getItem('financeOfflineFilterShown') !== 'false'
        });
        
        filterCollapse.addEventListener('hidden.bs.collapse', function () {
            localStorage.setItem('financeOfflineFilterShown', 'false');
        });
        
        filterCollapse.addEventListener('shown.bs.collapse', function () {
            localStorage.setItem('financeOfflineFilterShown', 'true');
        });
        
        // Quick date filter buttons
        document.querySelectorAll('.btn-date').forEach(btn => {
            btn.addEventListener('click', function() {
                const days = parseInt(this.getAttribute('data-days'));
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - days);
                
                document.getElementById('date_end').value = formatDate(endDate);
                document.getElementById('date_start').value = formatDate(startDate);
            });
        });
        
        // Month shortcuts
        document.querySelectorAll('.btn-month').forEach(btn => {
            btn.addEventListener('click', function() {
                const monthType = this.getAttribute('data-month');
                const today = new Date();
                
                if (monthType === 'current') {
                    const startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    document.getElementById('date_start').value = formatDate(startDate);
                    document.getElementById('date_end').value = formatDate(today);
                }
            });
        });
        
        // Helper to format date as YYYY-MM-DD
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        // Custom action menu implementation
        let currentMenuId = null;
        
        // Create action menus for each invoice
        document.querySelectorAll('.action-btn').forEach(button => {
            const invoiceId = button.id.replace('action-btn-', '');
            
            // Create menu container
            const menu = document.createElement('div');
            menu.id = `action-menu-${invoiceId}`;
            menu.className = 'action-menu';
            
            // Get invoice status for conditional items
            const row = button.closest('tr');
            const isFullyPaid = row?.dataset?.isFullyPaid === '1';
            
            // Check print status
            const printInfo = row.querySelector('td:nth-last-child(2)');
            const printCount = printInfo.textContent.includes('Dicetak') ? 
                parseInt(printInfo.textContent.match(/Dicetak (\d+)x/)[1]) : 0;
            const isAwaitingApproval = printInfo.textContent.includes('Menunggu Persetujuan');
            const isSuperAdmin = {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }};
            
            // Get invoice number from the row
            const invoiceNumberCell = row.querySelector('td:nth-child(2)');
            const invoiceNumber = invoiceNumberCell ? invoiceNumberCell.textContent.trim() : '';
            
            // Create menu HTML
            let menuHTML = '';
            
            // Print button condition - use invoice ID instead of invoice number
            if (printCount === 0 || isSuperAdmin || (printCount > 0 && row.querySelector('td:nth-last-child(2)').textContent.includes('reprint_approved'))) {
                menuHTML += `
                <a href="{{ url('finance/offline/print-invoice') }}/${invoiceId}" class="action-menu-item" target="_blank">
                    <i class="fas fa-print text-success"></i> Cetak Invoice
                </a>`;
            } else if (printCount > 0 && !isAwaitingApproval) {
                menuHTML += `
                <a href="#" class="action-menu-item" onclick="event.preventDefault(); requestReprint(${invoiceId})">
                    <i class="fas fa-print text-warning"></i> Minta Cetak Ulang
                </a>`;
            }
                
            if (!isFullyPaid) {
                menuHTML += `
                <a href="#" class="action-menu-item" onclick="event.preventDefault(); document.getElementById('modalBayar${invoiceId}').dispatchEvent(new CustomEvent('show-modal'))">
                    <i class="fas fa-money-bill-wave text-primary"></i> Bayar
                </a>`;
            }
            
            menuHTML += `
                <a href="#" class="action-menu-item" onclick="event.preventDefault(); document.getElementById('modalDetail${invoiceId}').dispatchEvent(new CustomEvent('show-modal'))">
                    <i class="fas fa-info-circle text-info"></i> Detail
                </a>`;
            
            menu.innerHTML = menuHTML;
            document.body.appendChild(menu);
            
            // Custom event listeners for modals
            document.getElementById(`modalBayar${invoiceId}`)?.addEventListener('show-modal', function() {
                new bootstrap.Modal(this).show();
            });
            
            document.getElementById(`modalDetail${invoiceId}`)?.addEventListener('show-modal', function() {
                new bootstrap.Modal(this).show();
            });
            
            // Button click handler
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Hide any open menu
                if (currentMenuId && currentMenuId !== menu.id) {
                    document.getElementById(currentMenuId).style.display = 'none';
                }
                
                // Toggle current menu
                if (menu.style.display === 'block') {
                    menu.style.display = 'none';
                    currentMenuId = null;
                } else {
                    const rect = button.getBoundingClientRect();
                    menu.style.top = `${rect.bottom + window.scrollY}px`;
                    menu.style.left = `${rect.left + window.scrollX}px`;
                    menu.style.display = 'block';
                    currentMenuId = menu.id;
                }
            });
        });
        
        // Close menu when clicking elsewhere
        document.addEventListener('click', function() {
            if (currentMenuId) {
                document.getElementById(currentMenuId).style.display = 'none';
                currentMenuId = null;
            }
        });
    });
    
    // Function to request reprint - use invoice ID instead of invoice number
    function requestReprint(invoiceId) {
        if (confirm('Anda telah mencapai batas cetak. Ajukan permintaan cetak ulang ke Super Admin?')) {
            window.location.href = '{{ url('finance/offline/print-invoice') }}/' + invoiceId;
        }
    }
    
    // Function to approve reprint
    function approveReprint(invoiceId) {
        if (confirm('Anda yakin ingin menyetujui permintaan cetak ulang ini?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('finance.offline.approve-reprint', '') }}/' + invoiceId;
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            
            form.appendChild(csrfToken);
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Helper function to get route URL
    function route(name, params) {
        // Use the actual href from an existing link with the same invoice number
        const link = document.querySelector(`a[href*="${params}"][target="_blank"]`);
        return link ? link.href : '#';
    }

    // Function to confirm and delete payment (superadmin only)
    function confirmDeletePayment(paymentId, paymentAmount) {
        if (confirm(`Anda yakin ingin menghapus pembayaran sebesar Rp ${paymentAmount}?\n\nPeringatan: Tindakan ini tidak dapat dibatalkan dan akan mempengaruhi status invoice.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('finance.offline.delete-payment', '') }}/' + paymentId;
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            
            form.appendChild(csrfToken);
            form.appendChild(methodInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
@endpush
