@extends('layouts.app')
@section('title', 'Analisis Saldo Masuk per Tanggal')
@section('content')

<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-calendar me-2"></i>Analisis Saldo Masuk per Tanggal
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('analytics.sales-by-date-number.export', request()->all()) }}" class="btn btn-sm btn-success">
                            <i class="bi bi-download me-1"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">

                    <!-- Quick Date Range Filters -->
                    <div class="mb-4">
                        <h6 class="mb-2 fw-bold"><i class="bi bi-calendar3 me-2"></i>Filter Cepat:</h6>
                        <div class="btn-group" role="group" aria-label="Quick date filters">
                            <a href="{{ route('analytics.sales-by-date-number', ['quick_range' => '7days'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                               class="btn {{ request('quick_range') == '7days' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-calendar-week me-1"></i> 7 Hari Terakhir
                            </a>
                            <a href="{{ route('analytics.sales-by-date-number', ['quick_range' => '2weeks'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                               class="btn {{ request('quick_range') == '2weeks' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-calendar2-week me-1"></i> 2 Minggu Terakhir
                            </a>
                            <a href="{{ route('analytics.sales-by-date-number', ['quick_range' => '1month'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                               class="btn {{ request('quick_range') == '1month' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-calendar-month me-1"></i> 1 Bulan Terakhir
                            </a>
                            <a href="{{ route('analytics.sales-by-date-number', ['quick_range' => '3months'] + request()->except(['start_date', 'end_date', 'quick_range'])) }}"
                               class="btn {{ request('quick_range') == '3months' ? 'btn-primary' : 'btn-outline-primary' }}">
                                <i class="bi bi-calendar3-range me-1"></i> 3 Bulan Terakhir
                            </a>
                            @if(request('quick_range'))
                                <a href="{{ route('analytics.sales-by-date-number', request()->except(['quick_range', 'start_date', 'end_date'])) }}"
                                   class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle"></i> Reset Filter Cepat
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('analytics.sales-by-date-number') }}" id="filter-form" class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Filter Custom</h6>
                        </div>
                        <div class="card-body">
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

                                <!-- Submit and Reset Button -->
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100" id="filter-submit-btn">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <a href="{{ route('analytics.sales-by-date-number') }}" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </a>
                                </div>
                            </div>

                            <!-- Loading indicator -->
                            <div class="row mt-3" id="loading-indicator" style="display: none;">
                                <div class="col-12 text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Memproses data, mohon tunggu...</p>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Active Filters Display -->
                    <div class="bg-light p-3 rounded mb-4 border">
                        <h6 class="fw-bold mb-3"><i class="bi bi-funnel-fill me-2"></i>Filter Aktif:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <span class="fw-semibold">Periode:</span>
                                    {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}
                                    @if($startDate != $endDate)
                                     - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <span class="fw-semibold">Platform:</span>
                                    @if($selectedPlatform)
                                        @php
                                            $platformName = $platforms->where('id', $selectedPlatform)->first()->name ?? 'Unknown';
                                        @endphp
                                        <span class="platform-box platform-{{ strtolower(str_replace(' ', '-', $platformName)) }}">
                                            {{ $platformName }}
                                        </span>
                                    @else
                                        Semua Platform
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                @if(request()->hasAny(['start_date', 'end_date', 'platform_id']))
                                    <a href="{{ route('analytics.sales-by-date-number') }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Reset Semua Filter
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Info about data filtering -->
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Informasi Data</h5>
                        <p class="mb-0">
                            Data yang ditampilkan <strong>hanya mencakup transaksi yang memiliki saldo masuk (pembayaran valid)</strong>.
                            Pesanan yang belum memiliki catatan pembayaran tidak dimasukkan dalam analisis.
                        </p>
                        <hr>
                        <p class="mb-0">
                            <strong>{{ number_format($summary['total_filtered_orders']) }}</strong> dari
                            <strong>{{ number_format($summary['total_all_orders']) }}</strong> pesanan memiliki transaksi keuangan
                            ({{ $summary['percent_filtered'] }}% dari total pesanan).
                        </p>
                    </div>

                    @if($summary['total_orders'] == 0)
                    <div class="alert alert-info my-4">
                        <h5 class="alert-heading">Tidak ada data</h5>
                        <p>Tidak ditemukan transaksi dengan saldo masuk {{ $startDate && $endDate ? ' untuk periode '.$startDate.' sampai '.$endDate : '' }}.</p>
                        @if($startDate && $endDate)
                        <p>Kemungkinan penyebab:</p>
                        <ul>
                            <li>Belum ada pembayaran yang tercatat untuk pesanan dalam periode ini</li>
                            <li>Data transaksi keuangan belum diimpor ke sistem</li>
                            <li>Periode yang dipilih tidak memiliki transaksi</li>
                        </ul>
                        <p>Silakan ubah filter tanggal atau platform untuk melihat data yang tersedia.</p>
                        @endif
                    </div>
                    @else
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary summary-card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        Total Pesanan
                                        @if($selectedPlatform)
                                        <span class="badge bg-warning text-dark ms-2">Filter</span>
                                        @endif
                                    </h5>
                                    <h2 class="display-5">{{ number_format($summary['total_orders']) }}</h2>
                                    <p>
                                        <i class="bi bi-calendar-event me-1"></i>
                                        @if($startDate == $endDate)
                                            {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}
                                        @else
                                            {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success summary-card">
                                <div class="card-body">
                                    <h5 class="card-title">Total Saldo Masuk</h5>
                                    <h2 class="display-5">Rp {{ number_format($summary['total_value'], 0, ',', '.') }}</h2>
                                    <p>Rata-rata: Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }} per order</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info summary-card">
                                <div class="card-body">
                                    <h5 class="card-title">Total Volume</h5>
                                    <h2 class="display-5">{{ number_format($summary['total_volume']) }} pcs</h2>
                                    <p>Rata-rata: {{ number_format($summary['avg_order_volume'], 1) }} pcs per order</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Day of Week Summary -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-dark text-white">
                                    <h5 class="mb-0">
                                        Ringkasan Saldo Masuk per Tanggal (1-31)
                                        @if($selectedPlatform || request('quick_range') || ($startDate != now()->format('Y-m-d') || $endDate != now()->format('Y-m-d')))
                                        <span class="badge bg-warning text-dark ms-2">Data Terfilter</span>
                                        @endif
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="dateNumberChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Breakdown Tables -->
                    @if(isset($monthlyData) && count($monthlyData) > 0)
                        @foreach($monthlyData as $monthKey => $monthInfo)
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-calendar-month me-2"></i>
                                    Tabel {{ $monthInfo['month_name'] }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th class="text-end">Jumlah Order</th>
                                                <th class="text-end">Total Nominal Penjualan (Rp)</th>
                                                <th class="text-end">Total Saldo Masuk (Rp)</th>
                                                <th class="text-end">Gross Profit (Rp)</th>
                                                <th class="text-end">Total Volume (pcs)</th>
                                                <th class="text-end">Avg Saldo/Order (Rp)</th>
                                                <th class="text-end">Avg Volume/Order</th>
                                                <th class="text-end">Saldo/Volume (Rp)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @for($day = $monthInfo['first_day']; $day <= $monthInfo['last_day']; $day++)
                                                @php
                                                    $dateKey = sprintf('%02d', $day);
                                                    $data = $monthInfo['date_data'][$dateKey] ?? [
                                                        'date_number' => $dateKey,
                                                        'order_count' => 0,
                                                        'total_value' => 0,
                                                        'total_nominal' => 0,
                                                        'total_hpp' => 0,
                                                        'total_volume' => 0
                                                    ];
                                                    $avgValue = $data['order_count'] > 0 ? $data['total_value'] / $data['order_count'] : 0;
                                                    $avgVolume = $data['order_count'] > 0 ? $data['total_volume'] / $data['order_count'] : 0;
                                                    $valueVolumeRatio = $data['total_volume'] > 0 ? $data['total_value'] / $data['total_volume'] : 0;
                                                    $grossProfit = $data['total_value'] - ($data['total_hpp'] ?? 0);
                                                @endphp
                                                <tr class="{{ $data['order_count'] > 0 ? '' : 'table-secondary' }}">
                                                    <td class="fw-bold">{{ $dateKey }}</td>
                                                    <td class="text-end">{{ number_format($data['order_count']) }}</td>
                                                    <td class="text-end">{{ number_format($data['total_nominal'] ?? 0, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($data['total_value'], 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($grossProfit, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($data['total_volume']) }}</td>
                                                    <td class="text-end">{{ number_format($avgValue, 0, ',', '.') }}</td>
                                                    <td class="text-end">{{ number_format($avgVolume, 1) }}</td>
                                                    <td class="text-end">{{ number_format($valueVolumeRatio, 0, ',', '.') }}</td>
                                                </tr>
                                            @endfor
                                        </tbody>
                                        <tfoot class="table-dark">
                                            <tr>
                                                <th>TOTAL {{ strtoupper($monthInfo['month_name']) }}</th>
                                                <th class="text-end">{{ number_format($monthInfo['summary']['total_orders']) }}</th>
                                                <th class="text-end">{{ number_format($monthInfo['summary']['total_nominal'] ?? 0, 0, ',', '.') }}</th>
                                                <th class="text-end">{{ number_format($monthInfo['summary']['total_value'], 0, ',', '.') }}</th>
                                                <th class="text-end">{{ number_format($monthInfo['summary']['total_gross_profit'] ?? 0, 0, ',', '.') }}</th>
                                                <th class="text-end">{{ number_format($monthInfo['summary']['total_volume']) }}</th>
                                                <th class="text-end">
                                                    {{ $monthInfo['summary']['total_orders'] > 0 ? number_format($monthInfo['summary']['total_value'] / $monthInfo['summary']['total_orders'], 0, ',', '.') : '0' }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $monthInfo['summary']['total_orders'] > 0 ? number_format($monthInfo['summary']['total_volume'] / $monthInfo['summary']['total_orders'], 1) : '0' }}
                                                </th>
                                                <th class="text-end">
                                                    {{ $monthInfo['summary']['total_volume'] > 0 ? number_format($monthInfo['summary']['total_value'] / $monthInfo['summary']['total_volume'], 0, ',', '.') : '0' }}
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @endif

                    <!-- Date Number Detail Table (Summary) -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-bar-chart me-2"></i>
                                Tabel Summary - Detail Saldo Masuk per Tanggal (1-31)
                            </h5>
                        </div>
                        <div class="card-body">
                            @if(isset($debugInfo) && !empty($debugInfo))
                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Peringatan: Orders Tanpa Financial Transactions</h6>
                        <p class="mb-2">Tanggal berikut memiliki orders tapi tidak memiliki financial transactions:</p>
                        <ul class="mb-0">
                            @foreach($debugInfo as $dateKey => $debug)
                                @if($dateKey != '31_details')
                                <li>
                                    <strong>Tanggal {{ $dateKey }}:</strong>
                                    {{ number_format($debug['order_count']) }} orders tanpa financial transactions
                                </li>
                                @endif
                            @endforeach
                        </ul>

                        @if(isset($debugInfo['31_details']) && !empty($debugInfo['31_details']))
                        <hr>
                        <div class="mt-2">
                            <strong>Khusus Tanggal 31:</strong>
                            <ul class="mb-0">
                                @foreach($debugInfo['31_details'] as $date31Detail)
                                <li>
                                    <strong>{{ \Carbon\Carbon::parse($date31Detail['full_date'])->format('d M Y') }}:</strong>
                                    {{ number_format($date31Detail['order_count']) }} orders
                                </li>
                                @endforeach
                            </ul>
                            <p class="mb-0 mt-2"><small class="text-info">
                                <i class="bi bi-info-circle"></i>
                                Tanggal 31 hanya ada di bulan: Januari, Maret, Mei, Juli, Agustus, Oktober, Desember.
                                Bulan lain (Februari, April, Juni, September, November) tidak punya tanggal 31.
                            </small></p>
                        </div>
                        @else
                        <p class="mb-0 mt-2"><small class="text-info">
                            <i class="bi bi-info-circle"></i>
                            Tidak ada orders ditemukan di tanggal 31 dalam periode ini.
                            Tanggal 31 hanya ada di bulan: Januari, Maret, Mei, Juli, Agustus, Oktober, Desember.
                        </small></p>
                        @endif

                        <p class="mb-0 mt-2"><small>Note: Data di tabel hanya menampilkan orders yang memiliki financial transactions dengan saldo_masuk > 0.</small></p>
                    </div>
                    @endif

                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th class="text-end">Jumlah Order<br><small class="fw-normal">(dengan transaksi)</small></th>
                                            @if(isset($allOrdersByDate))
                                            <th class="text-end">Total All Orders<br><small class="fw-normal">(semua orders)</small></th>
                                            @endif
                                            <th class="text-end">Total Nominal Penjualan (Rp)</th>
                                            <th class="text-end">Total Saldo Masuk (Rp)</th>
                                            <th class="text-end">Gross Profit (Rp)</th>
                                            <th class="text-end">Total Volume (pcs)</th>
                                            <th class="text-end">Avg Saldo/Order (Rp)</th>
                                            <th class="text-end">Avg Volume/Order</th>
                                            <th class="text-end">Saldo/Volume (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($i = 1; $i <= 31; $i++)
                                            @php
                                                $dateKey = sprintf('%02d', $i);
                                                $data = $dateNumberSummary[$dateKey] ?? [
                                                    'date_number' => $dateKey,
                                                    'order_count' => 0,
                                                    'total_value' => 0,
                                                    'total_nominal' => 0,
                                                    'total_hpp' => 0,
                                                    'total_volume' => 0
                                                ];
                                                $allOrdersCount = $allOrdersByDate[$dateKey] ?? 0;
                                                $hasOrdersWithoutFT = isset($debugInfo[$dateKey]);
                                                $avgValue = $data['order_count'] > 0 ? $data['total_value'] / $data['order_count'] : 0;
                                                $avgVolume = $data['order_count'] > 0 ? $data['total_volume'] / $data['order_count'] : 0;
                                                $valueVolumeRatio = $data['total_volume'] > 0 ? $data['total_value'] / $data['total_volume'] : 0;
                                                $grossProfit = $data['total_value'] - ($data['total_hpp'] ?? 0);
                                            @endphp
                                            <tr class="{{ $data['order_count'] > 0 ? '' : ($allOrdersCount > 0 ? 'table-warning' : 'table-secondary') }}">
                                                <td class="fw-bold">
                                                    {{ $dateKey }}
                                                    @if($hasOrdersWithoutFT)
                                                        <i class="bi bi-exclamation-triangle text-warning" title="{{ $debugInfo[$dateKey]['order_count'] }} orders tanpa financial transactions"></i>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ number_format($data['order_count']) }}</td>
                                                @if(isset($allOrdersByDate))
                                                <td class="text-end">
                                                    {{ number_format($allOrdersCount) }}
                                                    @if($allOrdersCount > $data['order_count'])
                                                        <br><small class="text-danger">(-{{ number_format($allOrdersCount - $data['order_count']) }})</small>
                                                    @endif
                                                </td>
                                                @endif
                                                <td class="text-end">{{ number_format($data['total_nominal'] ?? 0, 0, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($data['total_value'], 0, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($grossProfit, 0, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($data['total_volume']) }}</td>
                                                <td class="text-end">{{ number_format($avgValue, 0, ',', '.') }}</td>
                                                <td class="text-end">{{ number_format($avgVolume, 1) }}</td>
                                                <td class="text-end">{{ number_format($valueVolumeRatio, 0, ',', '.') }}</td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <th>TOTAL</th>
                                            <th class="text-end">{{ number_format($summary['total_orders']) }}</th>
                                            @if(isset($allOrdersByDate))
                                            <th class="text-end">{{ number_format($summary['total_all_orders']) }}</th>
                                            @endif
                                            <th class="text-end">{{ number_format($summary['total_nominal'] ?? 0, 0, ',', '.') }}</th>
                                            <th class="text-end">{{ number_format($summary['total_value'], 0, ',', '.') }}</th>
                                            <th class="text-end">{{ number_format($summary['total_gross_profit'] ?? 0, 0, ',', '.') }}</th>
                                            <th class="text-end">{{ number_format($summary['total_volume']) }}</th>
                                            <th class="text-end">{{ number_format($summary['avg_order_value'], 0, ',', '.') }}</th>
                                            <th class="text-end">{{ number_format($summary['avg_order_volume'], 1) }}</th>
                                            <th class="text-end">{{ $summary['total_volume'] > 0 ? number_format($summary['total_value'] / $summary['total_volume'], 0, ',', '.') : '0' }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Platform Summary -->
                    @if(count($platformSummary) > 1)
                    <h5 class="mb-3">Ringkasan Saldo Masuk per Platform</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Platform</th>
                                    <th class="text-end">Jumlah Order</th>
                                    <th class="text-end">Total Nominal Penjualan (Rp)</th>
                                    <th class="text-end">Total Saldo Masuk (Rp)</th>
                                    <th class="text-end">Gross Profit (Rp)</th>
                                    <th class="text-end">Total Volume (pcs)</th>
                                    <th class="text-end">% dari Total</th>
                                    <th class="text-end">Saldo/Volume (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($platformSummary as $platformData)
                                <tr>
                                    <td>
                                        <div class="platform-box platform-{{ strtolower(str_replace(' ', '-', $platformData['platform'])) }}">
                                            {{ $platformData['platform'] }}
                                        </div>
                                    </td>
                                    <td class="text-end">{{ number_format($platformData['order_count']) }}</td>
                                    <td class="text-end">{{ number_format($platformData['total_nominal'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($platformData['total_value'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($platformData['total_gross_profit'] ?? 0, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($platformData['total_volume']) }}</td>
                                    <td class="text-end">
                                        @if(isset($platformData['total_volume']) && $summary['total_volume'] > 0)
                                            {{ number_format(($platformData['order_count'] / $summary['total_orders']) * 100, 1) }}%
                                        @else
                                            0.0%
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if(isset($platformData['total_volume']) && $platformData['total_volume'] > 0)
                                            {{ number_format($platformData['total_value'] / $platformData['total_volume'], 0, ',', '.') }}
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center">Tidak ada data platform</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @endif
                </div>
            </div>

            <!-- Linear Trend Chart -->
            @if($summary['total_orders'] > 0)
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">
                        Trend Linear Saldo Masuk per Tanggal (1-31)
                        @if($selectedPlatform || request('quick_range') || ($startDate != now()->format('Y-m-d') || $endDate != now()->format('Y-m-d')))
                        <span class="badge bg-warning text-dark ms-2">Data Terfilter</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 400px;">
                        <canvas id="dayOfWeekLinearChart"></canvas>
                    </div>
                    <div class="mt-3 text-center text-muted">
                        <small>
                            Grafik menunjukkan trend saldo masuk berdasarkan tanggal dalam bulan (1-31).
                            X-axis: Tanggal (1-31), Y-axis: Saldo Masuk (Rp)
                        </small>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    :root {
        --primary-color: #4361ee;
        --success-color: #0bb4aa;
        --info-color: #4cc9f0;
        --dark-color: #212529;
    }

    .summary-card {
        border-radius: 10px;
        color: white;
        height: 100%;
    }

    .bg-primary { background-color: var(--primary-color) !important; }
    .bg-success { background-color: var(--success-color) !important; }
    .bg-info { background-color: var(--info-color) !important; }
    .bg-dark { background-color: var(--dark-color) !important; }

    .platform-box {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: 500;
    }

    .platform-shopee { background-color: #f53d2d; color: white; }
    .platform-tiktok { background-color: #000000; color: white; }
    .platform-offline { background-color: #6c757d; color: white; }

    .chart-container {
        position: relative;
        margin: 20px 0;
        height: 300px;
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

    .display-5 {
        font-size: 2.5rem;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .display-5 { font-size: 2rem; }
        .card-body { padding: 15px; }
        .btn-group { flex-wrap: wrap; }
        .btn-group .btn { margin-bottom: 5px; }
    }
</style>
@endpush

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.getElementById('filter-form');
        const loadingIndicator = document.getElementById('loading-indicator');
        const submitBtn = document.getElementById('filter-submit-btn');

        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                loadingIndicator.style.display = 'block';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Memproses...';
                loadingIndicator.scrollIntoView({ behavior: 'smooth' });
            });
        }

        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');
        const today = new Date();
        const todayFormatted = today.toISOString().split('T')[0];

        if (startDateInput && !startDateInput.value) {
            startDateInput.value = todayFormatted;
        }

        if (endDateInput && !endDateInput.value) {
            endDateInput.value = todayFormatted;
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('start_date') && !urlParams.has('end_date') && !urlParams.has('quick_range') && !document.referrer.includes('sales-by-date-number')) {
            const ff = document.getElementById('filter-form');
            if (ff) {
                ff.submit();
            }
        }

        const dateNumberChartEl = document.getElementById('dateNumberChart');

        @php
            $chartData = [];
            for ($i = 1; $i <= 31; $i++) {
                $dateKey = sprintf('%02d', $i);
                $data = $dateNumberSummary[$dateKey] ?? ['total_value' => 0];
                $chartData[] = $data['total_value'];
            }
        @endphp

        var chartLabels = [];
        var chartDataArr = [];
        for (var i = 1; i <= 31; i++) {
            var dateKey = i.toString().padStart(2, '0');
            chartLabels.push(dateKey);
            chartDataArr.push({{ implode(',', $chartData) }}[i-1]);
        }

        if (dateNumberChartEl) {
            var ctx = dateNumberChartEl.getContext('2d');

            var dateNumberChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Saldo Masuk (Rp)',
                        data: chartDataArr,
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        const dayOfWeekLinearChartEl = document.getElementById('dayOfWeekLinearChart');
        if (dayOfWeekLinearChartEl) {
            var ctxLinear = dayOfWeekLinearChartEl.getContext('2d');

            var linearChart = new Chart(ctxLinear, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Trend Saldo Masuk',
                        data: chartDataArr,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                        pointRadius: 4,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: { display: true, text: 'Tanggal (1-31)' }
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Saldo Masuk (Rp)' },
                            ticks: {
                                callback: function(value) {
                                    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.raw.toLocaleString('id-ID');
                                }
                            }
                        },
                        legend: { position: 'top' }
                    }
                }
            });
        }
    });
</script>
@endsection
