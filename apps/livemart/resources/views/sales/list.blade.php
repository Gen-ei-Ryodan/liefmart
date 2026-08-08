@extends('layouts.app')

@section('content')
    <script>
        // Immediate execution script to enforce table height
        (function() {
            console.log("Sales list immediate table fix running");
            document.addEventListener('DOMContentLoaded', function() {
                // Force table height constraint
                const tableContainer = document.querySelector('.table-responsive');
                if (tableContainer) {
                    tableContainer.style.maxHeight = '500px';
                    tableContainer.style.overflowY = 'auto';
                    console.log("Applied immediate sales table height fix");
                }
            });
        })();
    </script>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="ds-page-header">
                    <div>
                        <h1 class="text-gradient">Daftar Penjualan</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Menu Penjualan</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Daftar Penjualan</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="{{ route('sales.choose-type') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Tambah Penjualan Baru
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" id="toggleFilterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="m-0">Daftar Penjualan</h5>
                    </div>

                    <div class="card-body">
                        <div id="filterSection" class="ds-filter-card" style="display: none;">
                            <form action="{{ route('sales.list') }}" method="GET" id="filterForm">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="dateStart" class="form-label small">Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-sm" id="dateStart" name="date_start" 
                                               value="{{ request('date_start') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="dateEnd" class="form-label small">Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-sm" id="dateEnd" name="date_end" 
                                               value="{{ request('date_end') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="platformFilter" class="form-label small">Platform</label>
                                        <select class="form-select form-select-sm" id="platformFilter" name="platform">
                                            <option value="" {{ empty(request('platform')) || request('platform') == '' ? 'selected' : '' }}>Semua Platform</option>
                                            @foreach($platforms ?? [] as $platform)
                                                <option value="{{ $platform->id }}" {{ request('platform') == $platform->id ? 'selected' : '' }}>
                                                    {{ ucfirst($platform->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="orderNumber" class="form-label small">Nomor Order</label>
                                        <input type="text" class="form-control form-control-sm" id="orderNumber" name="order_number" 
                                               placeholder="Cari nomor order..." value="{{ request('order_number') }}">
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-end mt-2">
                                            <button type="submit" class="btn btn-sm btn-primary me-2">
                                                <i class="fas fa-search"></i> Cari
                                            </button>
                                            <a href="{{ route('sales.list') }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-sync-alt"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @if (session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="table-responsive disable-fixed-scrollbar">
                            <table class="table table-hover table-bordered-bottom wide-table">
                                <thead class="thead-light sticky-top">
                                    <tr>
                                        <th class="text-center">No</th>
                                        <th class="text-center">Tanggal</th>
                                        <th class="text-center">Hari</th>
                                        <th class="text-center">Status Hari</th>
                                        <th class="text-center">Platform</th>
                                        <th class="text-center">No Order</th>
                                        <th>Nama Barang</th>
                                        <th class="text-center">Varian</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Harga</th>
                                        <th class="text-end">Total Item</th>
                                        <th class="text-end">Total Invoice</th>
                                        <th class="text-center">No Resi</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php 
                                        $no = ($orders->currentPage() - 1) * $orders->perPage() + 1;
                                        $currentOrderId = null;
                                        $currentOrderNumber = null;
                                        $orderTotal = 0;
                                        $rowspan = 0;
                                    @endphp
                                    @forelse($orders as $order)
                                        @php
                                            // Menghitung total per invoice (nomor pesanan)
                                            $orderItems = $order->orderItems;
                                            $orderTotal = $orderItems->sum(function($item) {
                                                return $item->price_after_discount * $item->quantity;
                                            });
                                            
                                            // Hitung rowspan untuk order ini
                                            $rowspan = $orderItems->count();
                                            
                                            // Tentukan nomor order saat ini
                                            $currentOrderNumber = $order->order_number;
                                        @endphp
                                        
                                        @forelse($orderItems as $index => $item)
                                            <tr>
                                                <!-- Nomor urut tetap ditampilkan per baris -->
                                                <td class="text-center">{{ $no++ }}</td>
                                                
                                                @if($index === 0)
                                                    <!-- Tanggal hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        @if($order->tanggal) 
                                                            {{ \Carbon\Carbon::parse($order->tanggal)->format('d-m-Y') }}
                                                        @else 
                                                            -
                                                        @endif
                                                    </td>
                                                    
                                                    <!-- Hari hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        {{ $order->hari ?? '-' }}
                                                    </td>
                                                    
                                                    <!-- Status Hari hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        {{ $order->status_hari ?? '-' }}
                                                    </td>
                                                    
                                                    <!-- Platform hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        @if($order->platform)
                                                            <span class="badge bg-{{ 
                                                                str_contains(strtolower($order->platform->name), 'shopee') ? 'warning' : 
                                                                (str_contains(strtolower($order->platform->name), 'tiktok') ? 'dark' : 'primary') 
                                                            }}">
                                                                {{ $order->platform->name }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    
                                                    <!-- Nomor Order hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        {{ $currentOrderNumber }}
                                                        @if($order->hasReturns())
                                                            <div class="mt-1">
                                                                @if($order->isFullyReturned())
                                                                    <span class="badge bg-danger">Retur Full</span>
                                                                @else
                                                                    <span class="badge bg-warning text-dark">Retur Sebagian</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                @endif
                                                
                                                <!-- Detail produk, qty dan harga tetap per baris -->
                                                <td title="{{ $item->platformProduct ? $item->platformProduct->platform_product_name : 'Data produk tidak tersedia' }}">
                                                    @if ($item->platformProduct)
                                                        <div class="product-info">
                                                            <div class="product-name fw-medium">
                                                        {{ $item->platformProduct->platform_product_name }}
                                                            </div>
                                                            @if($item->platformProduct->variant)
                                                                <div class="product-variant">
                                                                    <small class="text-muted">{{ $item->platformProduct->variant }}</small>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-muted fst-italic">Data produk tidak tersedia</span>
                                                    @endif
                                                </td>
                                                
                                                <!-- Kolom Varian -->
                                                <td class="text-center">
                                                    @if ($item->platformProduct && $item->platformProduct->variant)
                                                        <span class="badge bg-info text-white small">{{ $item->platformProduct->variant }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                
                                                <td class="text-center">{{ $item->quantity }}</td>
                                                <td class="text-end">
                                                    {{ number_format($item->price_after_discount, 0, ',', '.') }}
                                                </td>
                                                <td class="text-end">
                                                    {{ number_format($item->price_after_discount * $item->quantity, 0, ',', '.') }}
                                                </td>
                                                
                                                @if($index === 0)
                                                    <!-- Total invoice hanya muncul sekali per order -->
                                                    <td class="text-end invoice-total" rowspan="{{ $rowspan }}">
                                                        {{ number_format($orderTotal, 0, ',', '.') }}
                                                    </td>
                                                    
                                                    <!-- No Resi hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        @if ($item->tracking_number)
                                                            {{ $item->tracking_number }}
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    
                                                    <!-- Aksi hanya muncul sekali per order -->
                                                    <td class="text-center" rowspan="{{ $rowspan }}">
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-cog"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a class="dropdown-item" href="#" onclick="showOrderDetail('{{ $order->id }}')">
                                                                    <i class="fas fa-eye fa-sm text-primary"></i> Detail
                                                                </a></li>
                                                                <li><a class="dropdown-item" href="#" onclick="printInvoice('{{ $order->id }}', '{{ strtolower($order->platform->name ?? '') }}')">
                                                                    <i class="fas fa-print fa-sm text-success"></i> Cetak
                                                                </a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                @if(Auth::check() && (Auth::user()->role === 'superadmin' || Auth::user()->isSuperAdmin() || Auth::user()->canDelete()))
                                                                <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete('{{ $order->id }}')">
                                                                    <i class="fas fa-trash fa-sm text-danger"></i> Hapus
                                                                </a></li>
                                                                @endif
                                                            </ul>
                                                        </div>
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

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted">Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} data</small>
                            </div>
                            <div>
                                {{ $orders->withQueryString()->links('pagination::bootstrap-5') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailModalLabel">Detail Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat data...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="printOrderBtn">Cetak</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pesanan ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Menghapus pesanan akan secara otomatis mengembalikan stok ke gudang.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form id="deleteOrderForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Hapus Pesanan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: auto !important;
        max-height: 500px !important;
        border: 1px solid #dee2e6;
        width: 100% !important;
    }

    .wide-table {
        min-width: 1200px;
        margin-bottom: 0 !important;
    }

    table.table {
        font-size: 12px;
        width: 100%;
        border-collapse: collapse;
    }

    .invoice-total {
        font-weight: bold;
        background-color: #f0f8ff;
    }

    td[rowspan] {
        vertical-align: middle;
        background-color: #f8f9fa;
        border-right: 1px solid #e3e6f0;
    }

    td[rowspan]:nth-child(5) {
        font-weight: bold;
        font-family: monospace;
        background-color: #f0f8ff;
    }

    td:nth-child(6) {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: 1.2;
    }

    td:last-child {
        font-family: monospace;
        font-size: 11px;
        letter-spacing: 0px;
    }

    .badge.bg-warning {
        background-color: #FF6720 !important;
        color: white;
    }

    #filterSection {
        transition: all 0.3s ease-in-out;
        overflow: hidden;
        border-left: 4px solid #4e73df !important;
    }

    .product-info {
        max-width: 300px;
    }

    .product-name {
        line-height: 1.3;
        margin-bottom: 4px;
        word-break: break-word;
        max-width: 300px;
    }

    .product-variant {
        margin-top: 2px;
    }

    td[title]:hover {
        position: relative;
        cursor: pointer;
    }

    td[title]:hover::after {
        content: attr(title);
        position: absolute;
        left: 0;
        top: 100%;
        background-color: #333;
        color: white;
        padding: 5px 8px;
        border-radius: 4px;
        z-index: 10;
        max-width: 300px;
        white-space: normal;
        font-size: 11px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
        .table {
            font-size: 10px;
        }

        .table th, .table td {
            padding: 4px 3px;
        }

        td:nth-child(6) {
            max-width: 150px;
        }

        .badge {
            font-size: 9px;
            padding: 2px 4px;
        }
    }
    </style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Sales list DOM loaded");
        
        // Debug any active filters
        const urlParams = new URLSearchParams(window.location.search);
        console.log("Active URL parameters:", Object.fromEntries(urlParams.entries()));
        
        // Log platform filter value
        const platformFilter = document.getElementById('platformFilter');
        if (platformFilter) {
            console.log("Platform filter value:", platformFilter.value);
        }
        
        // Monitor and fix table dimensions
        function checkTableDimensions() {
            const tableContainer = document.querySelector('.table-responsive');
            if (tableContainer && tableContainer.scrollHeight > 500) {
                console.log("Fixing table height - current scrollHeight:", tableContainer.scrollHeight);
                tableContainer.style.maxHeight = '500px';
                tableContainer.style.overflowY = 'auto';
            }
        }
        
        // Run initially and every 2 seconds
        checkTableDimensions();
        setInterval(checkTableDimensions, 2000);
        
        // Toggle filter section
        const toggleFilterBtn = document.getElementById('toggleFilterBtn');
        const filterSection = document.getElementById('filterSection');
        
        toggleFilterBtn.addEventListener('click', function() {
            if (filterSection.style.display === 'none') {
                filterSection.style.display = 'block';
                setTimeout(() => {
                    filterSection.style.maxHeight = filterSection.scrollHeight + 'px';
                }, 10);
            } else {
                filterSection.style.maxHeight = '0px';
                setTimeout(() => {
                    filterSection.style.display = 'none';
                }, 300);
            }
        });
        
        // Show filter section if there are active filters
        if (window.location.search && window.location.search.length > 1) {
            filterSection.style.display = 'block';
        }
        
        // Date filter sync and validation
        const dateStart = document.getElementById('dateStart');
        const dateEnd = document.getElementById('dateEnd');
        
        if (dateStart && dateEnd) {
            dateStart.addEventListener('change', function() {
                if (dateEnd.value && dateStart.value > dateEnd.value) {
                    dateEnd.value = dateStart.value;
                }
            });
            
            dateEnd.addEventListener('change', function() {
                if (dateStart.value && dateEnd.value < dateStart.value) {
                    dateStart.value = dateEnd.value;
                }
            });
        }
    });
    
    // Function to show order detail in modal
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
                
                // Set print button action
                document.getElementById('printOrderBtn').onclick = function() {
                    // Get platform info from the button data attribute or from the current page context
                    const platformName = document.querySelector(`[onclick*="showOrderDetail('${orderId}')"]`)
                        ?.closest('tr')?.querySelector('[onclick*="printInvoice"]')
                        ?.getAttribute('onclick')?.match(/'([^']*)'$/)?.[1] || '';
                    printInvoice(orderId, platformName);
                };
            })
            .catch(error => {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Gagal memuat detail pesanan: ${error.message}
                    </div>
                `;
            });
    }
    
    // Function to print invoice
    function printInvoice(orderId, platformName) {
        let printUrl = `/sales/orders/${orderId}/print`;
        window.open(printUrl, '_blank');
    }
    
    // Function to confirm deletion
    function confirmDelete(orderId) {
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        document.getElementById('deleteOrderForm').action = `{{ url('sales/orders') }}/${orderId}`;
        modal.show();
    }
</script>
@endpush