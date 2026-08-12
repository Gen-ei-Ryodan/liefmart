@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Gross Profit Offline</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('analytics.sales-by-platform') }}">Analytics</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Gross Profit Offline</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" onclick="exportData()">
                <i class="fas fa-file-excel me-2"></i> Export Excel
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light py-3">
            <h6 class="mb-0"><i class="fas fa-filter me-2 text-primary"></i> Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('analytics.offline.gross-profit') }}" method="GET">
                <div class="row g-3">
                    <!-- Date Range Filter -->
                    <div class="col-md-3">
                        <label for="start_date" class="form-label small fw-medium">Tanggal Mulai</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}" placeholder="DD/MM/YYYY">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="end_date" class="form-label small fw-medium">Tanggal Akhir</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" placeholder="DD/MM/YYYY">
                        </div>
                    </div>
                    
                    <!-- Invoice Number Filter -->
                    <div class="col-md-3">
                        <label for="invoice_number" class="form-label small fw-medium">No. Invoice</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-file-invoice"></i></span>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="{{ $selectedInvoice }}" placeholder="No. Invoice">
                        </div>
                    </div>
                    
                    <!-- PO Number Filter -->
                    <div class="col-md-3">
                        <label for="po_number" class="form-label small fw-medium">No. PO</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-file-alt"></i></span>
                            <input type="text" class="form-control" id="po_number" name="po_number" value="{{ $selectedPO }}" placeholder="No. PO">
                        </div>
                    </div>
                    
                    <!-- SKU Filter -->
                    <div class="col-md-3">
                        <label for="sku" class="form-label small fw-medium">SKU</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" id="sku" name="sku" value="{{ $selectedSKU }}" placeholder="SKU">
                        </div>
                    </div>
                    
                    <!-- Customer Filter -->
                    <div class="col-md-3">
                        <label for="customer_id" class="form-label small fw-medium">Customer</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">Semua Customer</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ $selectedCustomer == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Search & Reset Buttons -->
                    <div class="col-md-6 d-flex align-items-end justify-content-center">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-2"></i> Cari
                        </button>
                        <a href="{{ route('analytics.offline.gross-profit') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="stat-icon rounded d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px; background-color: rgba(74, 108, 247, 0.1);">
                                <i class="fas fa-shopping-cart text-primary" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase fs-xs fw-semibold mb-1">Total Penjualan</h6>
                            <h3 class="card-title fw-bold mb-0">{{ number_format($totalSales) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="stat-icon rounded d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px; background-color: rgba(34, 197, 94, 0.1);">
                                <i class="fas fa-dollar-sign text-success" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase fs-xs fw-semibold mb-1">Total Revenue</h6>
                            <h3 class="card-title fw-bold mb-0">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="stat-icon rounded d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px; background-color: rgba(59, 130, 246, 0.1);">
                                <i class="fas fa-receipt text-info" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase fs-xs fw-semibold mb-1">Total Revenue -PPN</h6>
                            <h3 class="card-title fw-bold mb-0">Rp {{ number_format($totalRevenueWithoutPPN, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="stat-icon rounded d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px; background-color: rgba(255, 193, 7, 0.1);">
                                <i class="fas fa-chart-line text-warning" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase fs-xs fw-semibold mb-1">Total Profit</h6>
                            <h3 class="card-title fw-bold mb-0">Rp {{ number_format($totalProfit, 0, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="stat-icon rounded d-flex align-items-center justify-content-center" 
                                 style="width: 48px; height: 48px; background-color: rgba(220, 53, 69, 0.1);">
                                <i class="fas fa-percentage text-danger" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase fs-xs fw-semibold mb-1">Average Margin</h6>
                            <h3 class="card-title fw-bold mb-0">{{ number_format($averageMargin, 2) }}%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Detail Gross Profit Offline</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive disable-fixed-scrollbar" style="max-height: 65vh; overflow-y: auto; overflow-x: auto;">
                <table class="table table-hover">
                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr class="bg-white">
                            <th scope="col" class="text-center">#</th>
                            <th scope="col">Tanggal Penjualan</th>
                            <th scope="col">Tanggal Pembayaran</th>
                            <th scope="col">Customer</th>
                            <th scope="col">No. PO</th>
                            <th scope="col">No. Invoice</th>
                            <th scope="col">Nama Produk</th>
                            <th scope="col" class="text-center">Qty</th>
                            <th scope="col">SKU</th>
                            <th scope="col" class="text-end">Pembayaran per INV</th>
                            <th scope="col" class="text-end">Pembayaran per INV -PPN</th>
                            <th scope="col" class="text-end">Pembayaran per Produk -PPN</th>
                            <th scope="col" class="text-end">Pembayaran per PCS -PPN</th>
                            <th scope="col" class="text-end">Harga Modal per produk(COGS)</th>
                            <th scope="col" class="text-end">Harga Modal Total</th>
                            <th scope="col" class="text-end">Profit per PCS</th>
                            <th scope="col" class="text-end">Profit per Produk</th>
                            <th scope="col" class="text-end">Profit per INV</th>
                            <th scope="col" class="text-center">Margin per PCS %</th>
                            <th scope="col" class="text-center">Margin per Produk %</th>
                            <th scope="col" class="text-center">Margin per INV %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profitData as $index => $item)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>{{ $item['sale_date'] ? \Carbon\Carbon::parse($item['sale_date'])->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item['payment_date'] ? \Carbon\Carbon::parse($item['payment_date'])->format('d/m/Y') : '-' }}</td>
                                <td>{{ $item['customer_name'] }}</td>
                                <td>{{ $item['po_number'] }}</td>
                                <td>{{ $item['invoice_number'] }}</td>
                                <td>{{ $item['product_name'] }}</td>
                                <td class="text-center">{{ number_format($item['quantity'], 0) }}</td>
                                <td>{{ $item['sku'] }}</td>
                                <td class="text-end">Rp {{ number_format($item['payment_per_invoice'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item['payment_per_invoice_without_ppn'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item['payment_per_product_without_ppn'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item['payment_per_pcs_without_ppn'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item['cost_price'], 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item['total_cost_price'], 0, ',', '.') }}</td>
                                <td class="text-end {{ $item['profit_per_unit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    Rp {{ number_format($item['profit_per_unit'], 0, ',', '.') }}
                                </td>
                                <td class="text-end {{ $item['profit_per_product'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    Rp {{ number_format($item['profit_per_product'], 0, ',', '.') }}
                                </td>
                                <td class="text-end {{ $item['profit_per_invoice'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    Rp {{ number_format($item['profit_per_invoice'], 0, ',', '.') }}
                                </td>
                                <td class="text-center {{ $item['margin_per_unit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($item['margin_per_unit'], 2) }}%
                                </td>
                                <td class="text-center {{ $item['margin_per_product'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($item['margin_per_product'], 2) }}%
                                </td>
                                <td class="text-center {{ $item['margin_per_invoice'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($item['margin_per_invoice'], 2) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="21" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <h5 class="fw-normal">Belum ada data profit</h5>
                                        <p class="text-muted">Tidak ada data penjualan offline dalam periode yang dipilih</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Export function that preserves current filters
    function exportData() {
        // Get current filter values
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const invoiceNumber = document.getElementById('invoice_number').value;
        const poNumber = document.getElementById('po_number').value;
        const sku = document.getElementById('sku').value;
        const customerId = document.getElementById('customer_id').value;
        
        // Build URL with current filters
        const exportUrl = new URL('{{ route("analytics.offline.gross-profit.export") }}');
        const params = new URLSearchParams();
        
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (invoiceNumber) params.append('invoice_number', invoiceNumber);
        if (poNumber) params.append('po_number', poNumber);
        if (sku) params.append('sku', sku);
        if (customerId) params.append('customer_id', customerId);
        
        exportUrl.search = params.toString();
        
        // Open export URL in new tab/window
        window.open(exportUrl.toString(), '_blank');
    }
    
    // Initialize date formatting for DD/MM/YYYY display
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for date-format.js to load
        function waitForDateFormat() {
            if (typeof window.formatDateDDMMYYYY === 'function' && window.dateFormatLoaded) {
                initializeDateInputs();
            } else {
                console.log('Waiting for date-format.js to load...');
                setTimeout(waitForDateFormat, 100);
            }
        }
        
        function initializeDateInputs() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                // Convert existing values to DD/MM/YYYY format for display
                if (startDateInput.value) {
                    startDateInput.value = window.convertToDDMMYYYY(startDateInput.value);
                }
                if (endDateInput.value) {
                    endDateInput.value = window.convertToDDMMYYYY(endDateInput.value);
                }
                
                // Add focus event to show DD/MM/YYYY format
                startDateInput.addEventListener('focus', function() {
                    if (this.value) {
                        this.value = window.convertToDDMMYYYY(this.value);
                    }
                });
                
                endDateInput.addEventListener('focus', function() {
                    if (this.value) {
                        this.value = window.convertToDDMMYYYY(this.value);
                    }
                });
                
                // Add blur event to convert back to YYYY-MM-DD for form submission
                startDateInput.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = window.convertToYYYYMMDD(this.value);
                    }
                });
                
                endDateInput.addEventListener('blur', function() {
                    if (this.value) {
                        this.value = window.convertToYYYYMMDD(this.value);
                    }
                });
                
                console.log('Date inputs initialized for DD/MM/YYYY format');
            }
        }
        
        // Start waiting for date-format.js
        waitForDateFormat();
    });
</script>
@endpush
