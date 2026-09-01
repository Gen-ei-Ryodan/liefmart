@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800">Analytic Keuangan TikTok Liefmarket</h1>
            <p class="text-muted small mb-0">Analisis data transaksi keuangan TikTok Liefmarket</p>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group shadow-sm" role="group">
                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" data-export="{{ route('analytics.finance.tiktok2.export', request()->all()) }}"><i class="far fa-file-excel me-2"></i>Excel</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>  

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(isset($dataSource) && $dataSource === 'orders')
    <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle me-2"></i>
            <div>
                <strong>Data dari Orders</strong>
                <p class="mb-0 small">Menampilkan data dari tabel Orders karena belum ada data Financial Transactions. Untuk data yang lebih akurat, silakan import data Financial Transactions melalui menu Finance.</p>
            </div>
            <a href="{{ route('finance.tiktok2.index') }}" class="btn btn-sm btn-outline-primary ms-3">
                <i class="fas fa-external-link-alt me-1"></i> Import Financial Data
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-primary fw-bold small">Total Transaksi</div>
                        <div class="card-icon-container bg-primary-soft">
                            <i class="fas fa-receipt text-primary"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        <h3 class="fw-bold mb-0 text-primary">{{ number_format($totalCount ?? $transactions->total(), 0, ',', '.') }}</h3>
                        <div class="ms-2 small text-muted">transaksi</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-success fw-bold small">Total Nominal Fix</div>
                        <div class="card-icon-container bg-success-soft">
                            <i class="fas fa-money-bill-wave text-success"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        @php
                            $totalNominalFix = $totalNominalFix ?? $transactions->sum('nominal_fix');
                        @endphp
                        <h3 class="fw-bold mb-0 text-success">Rp {{ number_format($totalNominalFix, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-info fw-bold small">Total Saldo Masuk</div>
                        <div class="card-icon-container bg-info-soft">
                            <i class="fas fa-hand-holding-usd text-info"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        @php
                            $totalSaldoMasuk = $totalSaldoMasuk ?? $transactions->sum('saldo_masuk');
                        @endphp
                        <h3 class="fw-bold mb-0 text-info">Rp {{ number_format($totalSaldoMasuk, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-danger fw-bold small">Total Outstanding</div>
                        <div class="card-icon-container bg-danger-soft">
                            <i class="fas fa-exclamation-circle text-danger"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        @php
                            $totalOutstanding = $totalOutstanding ?? $transactions->sum('outstanding');
                        @endphp
                        <h3 class="fw-bold mb-0 text-danger">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-secondary fw-bold small">Analytics View</div>
                        <div class="card-icon-container bg-secondary-soft">
                            <i class="fas fa-chart-bar text-secondary"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        <h3 class="fw-bold mb-0 text-secondary">-</h3>
                        <div class="ms-2 small text-muted">only</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card dashboard-card shadow-hover h-100 border-start border-secondary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-uppercase text-secondary fw-bold small">Paid Transactions</div>
                        <div class="card-icon-container bg-secondary-soft">
                            <i class="fas fa-check-circle text-secondary"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-end">
                        <h3 class="fw-bold mb-0 text-secondary">-</h3>
                        <div class="ms-2 small text-muted">focus</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 fw-bold text-primary">Daftar Transaksi</h6>
            @if(request()->has('from_date') || request()->has('to_date') || request()->has('from_order_date') || request()->has('to_order_date') || request()->has('order_number') || request()->has('invoice_number') || request()->has('min_nominal') || request()->has('max_nominal') || request()->has('outstanding_status'))
                <div class="d-flex align-items-center">
                    <div class="me-3 small text-muted">
                        <i class="fas fa-filter me-1"></i> Filter aktif
                    </div>
                    <a href="{{ route('analytics.finance.tiktok2') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                </div>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tiktokTransactionsTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3" style="width: 40px;">#</th>
                            <th style="width: 120px;">Tanggal Order</th>
                            <th style="width: 150px;">No. Order</th>
                            <th style="width: 200px;">No. Invoice</th>
                            <th style="width: 80px;">Status</th>
                            <th class="text-end" style="width: 120px;">HPP (Rp)</th>
                            <th class="text-end" style="width: 100px;">Voucher</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Komisi</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya Admin</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya Layanan</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 5</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 6</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 7</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 8</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 9</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 10</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 11</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Biaya 12</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Adjustment</th>
                            <th class="text-end" style="width: 60px;">%</th>
                            <th class="text-end" style="width: 100px;">Total %</th>
                            <th class="text-end" style="width: 120px;">Nominal Fix</th>
                            <th class="text-end" style="width: 120px;">Saldo Masuk</th>
                            <th style="width: 120px;">Tgl Pembayaran</th>
                            <th class="text-end" style="width: 120px;">Outstanding</th>
                            <th class="text-center pe-3" style="width: 100px;">Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedTransactions as $orderNumber => $orderTransactions)
                            @foreach($orderTransactions as $index => $transaction)
                            @php
                                $isUnpaid = $transaction->outstanding > 0;
                                $isOlderThan3Weeks = false;
                                if ($transaction->order && $transaction->order->tanggal) {
                                    $isOlderThan3Weeks = $transaction->order->tanggal->diffInDays(now()) > 21;
                                } elseif ($transaction->tanggal_order) {
                                    $isOlderThan3Weeks = $transaction->tanggal_order->diffInDays(now()) > 21;
                                }
                                // Set bg-danger-soft for all unpaid transactions regardless of age
                                $rowClass = $isUnpaid ? 'bg-danger-soft' : '';
                                $rowClass .= $index === 0 ? ' order-header' : ' order-item';
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="ps-3">{{ $loop->parent->index * count($orderTransactions) + $loop->index + $transactions->firstItem() }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        @if($transaction->order && $transaction->order->tanggal)
                                            <span>{{ $transaction->order->tanggal->format('d-m-Y') }}</span>
                                            <small class="text-muted">{{ $transaction->order->hari }}</small>
                                            @if($isUnpaid && $isOlderThan3Weeks)
                                                <span class="badge bg-danger mt-1">Telat {{ $transaction->order->tanggal->diffInDays(now()) }} hari</span>
                                            @endif
                                        @elseif($transaction->tanggal_order)
                                            <span>{{ $transaction->tanggal_order->format('d-m-Y') }}</span>
                                            <small class="text-muted">{{ $transaction->hari_order }}</small>
                                            @if($isUnpaid && $isOlderThan3Weeks)
                                                <span class="badge bg-danger mt-1">Telat {{ $transaction->tanggal_order->diffInDays(now()) }} hari</span>
                                            @endif
                                        @else
                                            <span>-</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="text-nowrap">{{ $transaction->no_order }}</span>
                                        @if($index === 0 && count($orderTransactions) > 1)
                                            <span class="badge bg-info ms-2">{{ count($orderTransactions) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="text-nowrap">{{ $transaction->no_invoice }}</span>
                                </td>
                                <td>
                                    @php
                                        $taxId = null;
                                        if (strpos($transaction->no_invoice, 'HPNSDA-OLK/01') !== false) {
                                            $taxId = 1; // PKP - Coffee
                                        } elseif (strpos($transaction->no_invoice, 'HPNSDA-OLK/02') !== false) {
                                            $taxId = 2; // Non PKP - Coffee
                                        } elseif (strpos($transaction->no_invoice, 'HGNSDA-OL/01') !== false) {
                                            $taxId = 3; // PKP - Skincare
                                        } elseif (strpos($transaction->no_invoice, 'HGNSDA-OL/02') !== false) {
                                            $taxId = 4; // Non PKP - Skincare
                                        } else {
                                            // Extract last two digits if possible
                                            if (preg_match('/\/(\d{2})/', $transaction->no_invoice, $matches)) {
                                                $taxId = (int)$matches[1];
                                            }
                                        }
                                        $isPKP = in_array($taxId, [1, 3, 5, 7]);
                                    @endphp
                                    @if($taxId)
                                        <span class="badge {{ $isPKP ? 'bg-primary' : 'bg-secondary' }}">
                                            {{ $isPKP ? 'PKP' : 'Non-PKP' }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-end fw-medium">{{ number_format($transaction->nominal_harga, 0, ',', '.') }}</td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon1, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase1 = $transaction->persentase_diskon1 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon1 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase1, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon2, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase2 = $transaction->persentase_diskon2 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon2 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase2, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon3, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase3 = $transaction->persentase_diskon3 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon3 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase3, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon4, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase4 = $transaction->persentase_diskon4 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon4 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase4, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon5, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase5 = $transaction->persentase_diskon5 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon5 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase5, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon6, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase6 = $transaction->persentase_diskon6 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon6 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase6, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon7, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase7 = $transaction->persentase_diskon7 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon7 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase7, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon8, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase8 = $transaction->persentase_diskon8 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon8 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase8, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon9, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase9 = $transaction->persentase_diskon9 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon9 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase9, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon10, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase10 = $transaction->persentase_diskon10 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon10 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase10, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon11, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase11 = $transaction->persentase_diskon11 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon11 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase11, 2) }}%
                                </td>
                                <td class="text-end text-danger">{{ number_format($transaction->nominal_diskon12, 0, ',', '.') }}</td>
                                <td class="text-end text-danger small">
                                    @php
                                        $persentase12 = $transaction->persentase_diskon12 ?? 
                                            ($transaction->nominal_harga > 0 ? abs(($transaction->nominal_diskon12 / $transaction->nominal_harga) * 100) : 0);
                                    @endphp
                                    {{ number_format($persentase12, 2) }}%
                                </td>
                                <td class="text-end {{ $transaction->adjustment != 0 ? ($transaction->adjustment > 0 ? 'text-success' : 'text-danger') : '' }}">
                                    {{ number_format($transaction->adjustment, 0, ',', '.') }}
                                </td>
                                <td class="text-end small {{ $transaction->adjustment != 0 ? ($transaction->adjustment > 0 ? 'text-success' : 'text-danger') : '' }}">
                                    @php
                                        $adjustmentPercent = ($transaction->adjustment != 0 && $transaction->nominal_harga > 0) ? 
                                            abs(($transaction->adjustment / $transaction->nominal_harga) * 100) : 0;
                                    @endphp
                                    {{ number_format($adjustmentPercent, 2) }}%
                                </td>
                                <td class="text-end fw-bold {{ $transaction->total_persentase > 0 ? 'text-danger' : '' }}">
                                    @php
                                        $totalPercent = $transaction->total_persentase ?? 
                                            ($persentase1 + $persentase2 + $persentase3 + $persentase4 + $persentase5 + $persentase6 + $persentase7 + $persentase8 + $persentase9 + $persentase10 + $persentase11 + $persentase12);
                                    @endphp
                                    {{ number_format(abs($totalPercent), 2) }}%
                                </td>
                                <td class="text-end fw-bold">{{ number_format($transaction->nominal_fix, 0, ',', '.') }}</td>
                                <td class="text-end text-success fw-medium">{{ number_format($transaction->saldo_masuk, 0, ',', '.') }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>{{ $transaction->tanggal_masuk_pembayaran ? $transaction->tanggal_masuk_pembayaran->format('d-m-Y') : '' }}</span>
                                        <small class="text-muted">{{ $transaction->hari_masuk_pembayaran }}</small>
                                    </div>
                                </td>
                                <td class="text-end fw-bold {{ $transaction->outstanding > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($transaction->outstanding, 0, ',', '.') }}
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info-soft text-info">
                                        <i class="fas fa-chart-line me-1"></i> Analytics
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                        <tr>
                            <td colspan="38" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted mb-2">Tidak ada transaksi</h5>
                                    <p class="text-muted">Belum ada data transaksi pembayaran dari TikTok</p>
                                    <a href="{{ route('finance.tiktok.index') }}" class="btn btn-primary mt-2">
                                        <i class="fas fa-file-upload me-1"></i> Import Data Sekarang
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($transactions->total() > 0)
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center p-3 border-top">
                <div class="text-muted small mb-2 mb-md-0">
                    Menampilkan {{ $transactions->count() ? $transactions->firstItem() : 0 }} sampai {{ $transactions->count() ? $transactions->lastItem() : 0 }} dari {{ $transactions->total() }} data
                </div>
                <div>
                    {{ $transactions->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-filter me-2"></i>Filter Transaksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('analytics.finance.tiktok') }}" method="GET">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dari Tanggal Pembayaran</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                                </div>
                                <div class="form-text">Filter berdasarkan tanggal pembayaran</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sampai Tanggal Pembayaran</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                                </div>
                                <div class="form-text">Filter berdasarkan tanggal pembayaran</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dari Tanggal Order</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-shopping-cart"></i></span>
                                    <input type="date" name="from_order_date" class="form-control" value="{{ request('from_order_date') }}">
                                </div>
                                <div class="form-text">Filter berdasarkan tanggal order</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sampai Tanggal Order</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-shopping-cart"></i></span>
                                    <input type="date" name="to_order_date" class="form-control" value="{{ request('to_order_date') }}">
                                </div>
                                <div class="form-text">Filter berdasarkan tanggal order</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. Order</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                        <input type="text" name="order_number" class="form-control" value="{{ request('order_number') }}" placeholder="Masukkan nomor order">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">No. Invoice</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-file-invoice"></i></span>
                                        <input type="text" name="invoice_number" class="form-control" value="{{ request('invoice_number') }}" placeholder="Masukkan nomor invoice">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rentang Nominal Fix</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="min_nominal" class="form-control" value="{{ request('min_nominal') }}" placeholder="Min">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="max_nominal" class="form-control" value="{{ request('max_nominal') }}" placeholder="Max">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Outstanding</label>
                                <select name="outstanding_status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="0" {{ request('outstanding_status') === '0' ? 'selected' : '' }}>Tidak Outstanding (= 0)</option>
                                    <option value="1" {{ request('outstanding_status') === '1' ? 'selected' : '' }}>Outstanding (≠ 0)</option>
                                </select>
                                <div class="form-text">Filter berdasarkan status outstanding</div>
                            </div>
                        </div>
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-1"></i> Terapkan Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Dashboard Cards */
    .dashboard-card {
        overflow: hidden;
        border: none;
        border-radius: 0.75rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .shadow-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    
    .card-icon-container {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .bg-primary-soft { background-color: rgba(78, 115, 223, 0.1); }
    .bg-success-soft { background-color: rgba(28, 200, 138, 0.1); }
    .bg-info-soft { background-color: rgba(54, 185, 204, 0.1); }
    .bg-danger-soft { background-color: rgba(231, 74, 59, 0.5); }
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); }
    
    /* Table Styling */
    #tiktokTransactionsTable {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    #tiktokTransactionsTable tr.order-header {
        background-color: rgba(236, 240, 255, 0.5);
    }
    
    #tiktokTransactionsTable tbody tr:hover {
        background-color: rgba(236, 240, 255, 0.7);
    }
    
    #tiktokTransactionsTable tbody tr td:first-child {
        border-left: 3px solid transparent;
    }
    
    #tiktokTransactionsTable tbody tr.order-header td:first-child {
        border-left: 3px solid #4e73df;
    }
    
    #tiktokTransactionsTable tr td {
        white-space: nowrap;
        vertical-align: middle;
    }
    
    /* Pagination styling */
    .pagination {
        margin-bottom: 0;
    }
    
    .page-item.active .page-link {
        background-color: #4e73df;
        border-color: #4e73df;
        color: white;
    }
    
    .page-link {
        color: #4e73df;
        border-radius: 0.25rem;
        margin: 0 2px;
    }
    
    .page-link:hover {
        color: #224abe;
        background-color: #f8f9fa;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive {
            overflow-y: hidden;
        }
    }
</style>
@endpush

@push('scripts')
<script>
function resetFilters() {
    // Redirect to the same page without any query parameters
    window.location.href = "{{ route('analytics.finance.tiktok') }}";
}
</script>
@endpush

@endsection 