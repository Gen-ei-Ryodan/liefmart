<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Penjualan Detail</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

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

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container-fluid {
            padding: 20px;
            max-width: 1440px;
            margin: 0 auto;
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            padding: 15px 20px;
        }

        .card-body {
            padding: 20px;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .table-dark th {
            background-color: var(--dark-color) !important;
            color: white !important;
            font-weight: 500;
        }

        .table-row-even {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .table-row-odd {
            background-color: #fff;
        }

        .table-row-even:hover, .table-row-odd:hover {
            background-color: rgba(99, 102, 241, 0.04);
        }

        /* Highlight cells with rowspan */
        .cell-highlight {
            background-color: #f8f9fa;
            vertical-align: middle;
        }

        /* Card and Icon styles */
        .icon-circle {
            height: 3rem;
            width: 3rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-circle i {
            font-size: 1.5rem;
        }

        /* Platform box styling */
        .platform-box {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-width: 80px;
            text-align: center;
            font-size: 0.8rem;
        }

        /* Platform specific colors */
        .platform-shopee {
            background-color: #EE4D2D;
            color: white;
        }

        .platform-tiktok {
            background-color: #000000;
            color: white;
        }

        .platform-offline {
            background-color: #6B7280;
            color: white;
        }

        .platform-unknown {
            background-color: #6c757d;
            color: white;
        }

        /* Breadcrumb */
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

/* Table responsive */
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        /* Form styling */
        #filter-form {
            border-left: 4px solid var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table {
                font-size: 0.75rem;
            }

            .platform-box {
                min-width: 60px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item">Analytics</li>
            <li class="breadcrumb-item active">Penjualan Detail</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="m-0 font-weight-bold">Analytics Penjualan Detail</h5>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('analytics.sales-detail-report') }}" id="filter-form" class="mb-5 p-3 bg-light rounded">
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

                    <!-- Price Range Filter -->
                    <div class="col-md-3">
                        <label for="min_price" class="form-label">Harga Minimum</label>
                        <input type="number" class="form-control" id="min_price" name="min_price"
                               placeholder="Min" value="{{ request('min_price') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="max_price" class="form-label">Harga Maksimum</label>
                        <input type="number" class="form-control" id="max_price" name="max_price"
                               placeholder="Max" value="{{ request('max_price') }}">
                    </div>

                    <!-- Quantity Range Filter -->
                    <div class="col-md-3">
                        <label for="min_qty" class="form-label">Qty Total Minimum</label>
                        <input type="number" min="1" class="form-control" id="min_qty" name="min_qty"
                               placeholder="Min Qty Total" value="{{ request('min_qty') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="max_qty" class="form-label">Qty Total Maksimum</label>
                        <input type="number" min="1" class="form-control" id="max_qty" name="max_qty"
                               placeholder="Max Qty Total" value="{{ request('max_qty') }}">
                    </div>

                    <!-- Submit and Reset Button -->
                    <div class="col-md-3">
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="{{ route('analytics.sales-detail-report') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                            <div class="mt-2">
                                <a href="#" data-export="{{ route('analytics.sales-detail-report.export', request()->query()) }}" class="btn btn-success">
                                    <i class="bi bi-download"></i> Export Excel
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
                                    <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-cart me-1"></i>Total Order (Setelah Retur)
                                    </h6>
                                    <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">
                                        {{ number_format($summary['total_orders_after_returns']) }}
                                    </h2>
                                </div>
                                <div class="icon-circle bg-white bg-opacity-20 text-white" style="flex-shrink: 0;">
                                    <i class="bi bi-cart" style="font-size: 1.5rem;"></i>
                                </div>
                            </div>
                            <div class="mt-2 small opacity-75" style="font-size: 0.75rem; line-height: 1.4;">
                                @php
                                    $fullyReturned = $summary['fully_returned_orders'] ?? 0;
                                    $totalAllOrders = $summary['total_orders_all'] ?? $summary['total_orders_after_returns'] ?? 0;
                                @endphp
                                {{ number_format($fullyReturned) }} order full retur (tidak ditampilkan) dari {{ number_format($totalAllOrders) }} total orderan
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-cash-coin me-1"></i>Total Value
                                    </h6>
                                    <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">
                                        Rp {{ number_format($summary['total_value'], 0, ',', '.') }}
                                    </h2>
                                </div>
                                <div class="icon-circle bg-white bg-opacity-20 text-white" style="flex-shrink: 0;">
                                    <i class="bi bi-cash-coin" style="font-size: 1.5rem;"></i>
                                </div>
                            </div>
                            <div class="mt-2 small opacity-75" style="font-size: 0.75rem; line-height: 1.4;">
                                Rata-rata: Rp {{ number_format($summary['avg_order_value'], 0, ',', '.') }}/order
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-box me-1"></i>Total Volume
                                    </h6>
                                    <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">
                                        {{ number_format($summary['total_volume']) }} <small style="font-size: 0.6em;">pcs</small>
                                    </h2>
                                </div>
                                <div class="icon-circle bg-white bg-opacity-20 text-white" style="flex-shrink: 0;">
                                    <i class="bi bi-box" style="font-size: 1.5rem;"></i>
                                </div>
                            </div>
                            <div class="mt-2 small opacity-75" style="font-size: 0.75rem; line-height: 1.4;">
                                Rata-rata: {{ number_format($summary['avg_order_volume'] ?? ($summary['total_volume'] / max($summary['total_orders_after_returns'], 1)), 1) }} pcs/order
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card {{ $summary['percentage_shown'] == 100 ? 'bg-secondary' : 'bg-warning' }} text-white h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <h6 class="text-uppercase mb-2 opacity-75" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-percent me-1"></i>Persentase Tampil
                                    </h6>
                                    <h2 class="font-weight-bold mb-0" style="font-size: 2rem;">
                                        {{ number_format($summary['percentage_shown'], 1) }}%
                                    </h2>
                                </div>
                                <div class="icon-circle bg-white bg-opacity-20 text-white" style="flex-shrink: 0;">
                                    <i class="bi bi-percent" style="font-size: 1.5rem;"></i>
                                </div>
                            </div>
                            <div class="mt-2 small opacity-75" style="font-size: 0.75rem; line-height: 1.4;">
                                % dari filter tanggal/platform yang dipilih
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
                            <th>Nama Barang</th>
                            <th width="80">Varian</th>
                            <th width="60">Qty</th>
                            <th width="70">QTY Retur</th>
                            <th width="100">Harga</th>
                            <th width="120">Total Item</th>
                            <th width="80">Qty Total</th>
                            <th width="120">Total Invoice</th>
                            <th width="120">No Resi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $no = ($orders->currentPage() - 1) * $orders->perPage() + 1;
                            $currentOrderId = null;
                        @endphp

                        @forelse($orders as $order)
                            @php
                                $orderItems = $order->orderItems;
                                $rowspan = $orderItems->count();

                                // Calculate total returned quantities and values for the entire order
                                $totalOrderQtyRetur = 0.0;
                                $totalOrderValueAfterRetur = 0.0;
                                $totalOrderVolumeAfterRetur = 0.0;

                                foreach($orderItems as $orderItem) {
                                    $itemQtyReturIndividual = \App\Models\ReturPenjualanDetail::where('order_item_id', $orderItem->id)
                                        ->whereHas('returPenjualan', function($q) {
                                            $q->whereIn('status', ['draft', 'selesai']);
                                        })
                                        ->sum('qty');
                                    $itemQtyReturIndividual = (float) $itemQtyReturIndividual;

                                    // Check if this is a package product and get total package quantity
                                    $itemPackageQuantity = 1;
                                    if ($orderItem->platformProduct && $orderItem->platformProduct->mappingBarang && $orderItem->platformProduct->mappingBarang->count() > 0) {
                                        $itemPackageQuantity = $orderItem->platformProduct->mappingBarang->sum('quantity');
                                    }

                                    // Convert individual retur quantity back to package quantity
                                    $itemQtyRetur = $itemPackageQuantity > 0 ? $itemQtyReturIndividual / $itemPackageQuantity : $itemQtyReturIndividual;
                                    $totalOrderQtyRetur += $itemQtyRetur;

                                    // Calculate original quantity (current + returned)
                                    $currentItemQty = (float) ($orderItem->quantity ?? 0);
                                    $originalQty = $currentItemQty + $itemQtyRetur;

                                    // Calculate remaining quantity after return
                                    $remainingQty = max(0.0, $originalQty - $itemQtyRetur);
                                    $totalOrderVolumeAfterRetur += $remainingQty;

                                    // Calculate remaining value after return
                                    $itemPrice = (float) ($orderItem->price_after_discount ?? 0);
                                    $remainingValue = round($itemPrice * $remainingQty, 2);
                                    $totalOrderValueAfterRetur += $remainingValue;
                                }

                                // Round final totals to ensure consistency
                                $totalOrderVolumeAfterRetur = round($totalOrderVolumeAfterRetur, 0);
                                $totalOrderValueAfterRetur = round($totalOrderValueAfterRetur, 2);

                                // Ensure variables are never null
                                $totalOrderVolumeAfterRetur = $totalOrderVolumeAfterRetur ?? 0;
                                $totalOrderValueAfterRetur = $totalOrderValueAfterRetur ?? 0;
                                $orderTotal = $order->total_value;
                            @endphp

                            @forelse($orderItems as $index => $item)
                                <tr class="{{ $index % 2 == 0 ? 'table-row-even' : 'table-row-odd' }}">
                                    <!-- Nomor urut tetap ditampilkan per baris -->
                                    <td class="text-center">{{ $no++ }}</td>

                                    @if($index === 0)
                                        <!-- Tanggal hanya muncul sekali per order -->
                                        <td class="text-center cell-highlight" rowspan="{{ $rowspan }}">
                                            @if($order->tanggal)
                                                {{ \Carbon\Carbon::parse($order->tanggal)->format('d-m-Y') }}
                                            @else
                                                -
                                            @endif
                                        </td>

                                        <!-- Hari hanya muncul sekali per order -->
                                        <td class="text-center cell-highlight" rowspan="{{ $rowspan }}">
                                            {{ $order->hari ?? '-' }}
                                        </td>

                                        <!-- Nomor Order hanya muncul sekali per order -->
                                        <td class="text-center cell-highlight" rowspan="{{ $rowspan }}">
                                            <span class="fw-bold font-monospace">{{ $order->order_number }}</span>
                                        </td>

                                        <!-- Platform hanya muncul sekali per order -->
                                        <td class="text-center cell-highlight" rowspan="{{ $rowspan }}">
                                            @if($order->platform)
                                                <div class="platform-box platform-{{ strtolower(str_replace(' ', '-', $order->platform->name)) }}">
                                                    {{ $order->platform->name }}
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    @endif

                                    <!-- Detail produk, qty dan harga tetap per baris -->
                                    <td title="{{ $item->platformProduct ? $item->platformProduct->platform_product_name : 'Data produk tidak tersedia' }}">
                                        @if ($item->platformProduct)
                                            {{ $item->platformProduct->platform_product_name }}
                                        @else
                                            <span class="text-muted">Data produk tidak tersedia</span>
                                        @endif
                                    </td>

                                    <!-- Kolom Varian -->
                                    <td class="text-center">
                                        @if ($item->platformProduct && $item->platformProduct->variant)
                                            <span class="badge bg-info text-white">{{ $item->platformProduct->variant }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>

                                    @php
                                        // Calculate qty retur for this order item (online sales return)
                                        $qtyReturIndividual = \App\Models\ReturPenjualanDetail::where('order_item_id', $item->id)
                                            ->whereHas('returPenjualan', function($q) {
                                                $q->whereIn('status', ['draft', 'selesai']);
                                            })
                                            ->sum('qty');
                                        $qtyReturIndividual = (float) $qtyReturIndividual;

                                        // Check if this is a package product and get total package quantity
                                        $packageQuantity = 1; // Default for non-package products
                                        if ($item->platformProduct && $item->platformProduct->mappingBarang && $item->platformProduct->mappingBarang->count() > 0) {
                                            $packageQuantity = $item->platformProduct->mappingBarang->sum('quantity');
                                        }

                                        // Convert individual retur quantity back to package quantity
                                        $qtyRetur = $packageQuantity > 0 ? $qtyReturIndividual / $packageQuantity : $qtyReturIndividual;

                                        // Calculate original quantity (current quantity + returned quantity)
                                        $currentQty = (float) ($item->quantity ?? 0);
                                        $originalItemQty = $currentQty + $qtyRetur;

                                        // Calculate original total value for this item (before any returns)
                                        $itemPrice = (float) ($item->price_after_discount ?? 0);
                                        $originalItemValue = round($itemPrice * $originalItemQty, 2);

                                        // Ensure variables are never null
                                        $originalItemValue = $originalItemValue ?? 0;
                                    @endphp
                                    <td class="text-center fw-medium">{{ number_format($originalItemQty) }}</td>
                                    <td class="text-center">{{ number_format($qtyRetur) }} pcs</td>
                                    <td class="text-end">
                                        Rp {{ number_format($item->price_after_discount ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end fw-medium">
                                        @php
                                            $displayValue = isset($originalItemValue) ? $originalItemValue : 0;
                                            $displayValue = is_numeric($displayValue) ? $displayValue : 0;
                                        @endphp
                                        Rp {{ number_format($displayValue, 0, ',', '.') }}
                                    </td>

                                    @if($index === 0)
                                        <!-- Qty Total after returns -->
                                        <td class="text-center fw-bold cell-highlight" rowspan="{{ $rowspan }}">
                                            @php
                                                $displayQtyTotal = isset($totalOrderVolumeAfterRetur) ? $totalOrderVolumeAfterRetur : 0;
                                                $displayQtyTotal = is_numeric($displayQtyTotal) ? $displayQtyTotal : 0;
                                            @endphp
                                            {{ number_format($displayQtyTotal) }}
                                        </td>

                                        <!-- Total Invoice after returns -->
                                        <td class="text-end fw-bold cell-highlight" rowspan="{{ $rowspan }}">
                                            @php
                                                $displayTotalInvoice = isset($totalOrderValueAfterRetur) ? $totalOrderValueAfterRetur : 0;
                                                $displayTotalInvoice = is_numeric($displayTotalInvoice) ? $displayTotalInvoice : 0;
                                            @endphp
                                            Rp {{ number_format($displayTotalInvoice, 0, ',', '.') }}
                                        </td>

                                        <!-- No Resi hanya muncul sekali per order -->
                                        <td class="text-center font-monospace cell-highlight" rowspan="{{ $rowspan }}">
                                            @php
                                                $trackingNumber = collect($orderItems)->pluck('tracking_number')->filter()->first();
                                            @endphp

                                            @if ($trackingNumber)
                                                {{ $trackingNumber }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center py-4">Tidak ada item pada pesanan ini</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="14" class="text-center py-4">Tidak ada data penjualan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if(method_exists($orders, 'links'))
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} data
                </div>
                <div>
                    {{ $orders->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Set default date to today if not already set
    document.addEventListener('DOMContentLoaded', function() {
        // Get date inputs
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        // Get today's date in YYYY-MM-DD format
        const today = new Date();
        const todayFormatted = getTodayYYYYMMDD();

        // Set default values if empty
        if (!startDateInput.value) {
            startDateInput.value = todayFormatted;
        }

        if (!endDateInput.value) {
            endDateInput.value = todayFormatted;
        }

        // If URL doesn't have date parameters, submit the form with today's date
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('start_date') && !urlParams.has('end_date') && !document.referrer.includes('sales-detail-report')) {
            document.getElementById('filter-form').submit();
        }
    });
</script>
</body>
</html>