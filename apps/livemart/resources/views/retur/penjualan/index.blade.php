@extends('layouts.app')

@section('title', 'Daftar Retur Penjualan')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-undo me-2"></i>Daftar Retur Penjualan
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('retur-penjualan.export') }}" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </a>
                        <a href="{{ route('retur-penjualan.create') }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Buat Retur Baru
                        </a>
                    </div>
                </div>

                <div class="card-body p-4">
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Sukses!</strong>
                        </div>
                        <p class="mb-0 mt-1">{{ session('success') }}</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Error!</strong>
                        </div>
                        <p class="mb-0 mt-1">{{ session('error') }}</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    <!-- Filter Section -->
                    <div class="card bg-light mb-4 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i> Filter Pencarian</h6>
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="filterCollapse">
                            <div class="card-body py-3">
                            <form method="GET" action="{{ route('retur-penjualan.index') }}" id="filterForm">
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">Cari:</label>
                                            <input type="text" name="search" id="search" class="form-control" 
                                                   value="{{ request('search') }}" 
                                                   placeholder="Kode retur, order number, resi, user...">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="status">Status:</label>
                                            <select name="status" id="status" class="form-control">
                                                <option value="">Semua Status</option>
                                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                                <option value="selesai" {{ request('status') == 'selesai' ? 'selected' : '' }}>Selesai</option>
                                                <option value="dibatalkan" {{ request('status') == 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="platform_id">Platform:</label>
                                            <select name="platform_id" id="platform_id" class="form-control">
                                                <option value="">Semua Platform</option>
                                                @foreach($platforms as $platform)
                                                <option value="{{ $platform->id }}" {{ request('platform_id') == $platform->id ? 'selected' : '' }}>
                                                    {{ $platform->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="user_id">User:</label>
                                            <select name="user_id" id="user_id" class="form-control">
                                                <option value="">Semua User</option>
                                                @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="date_from">Tanggal Dari:</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" 
                                                   value="{{ request('date_from') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="date_to">Tanggal Sampai:</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" 
                                                   value="{{ request('date_to') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-10">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search"></i> Filter
                                                </button>
                                                <a href="{{ route('retur-penjualan.index') }}" class="btn btn-secondary">
                                                    <i class="fas fa-times"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Kode Retur</th>
                                    <th>Nomor Order</th>
                                    <th>No. Resi</th>
                                    <th>Platform</th>
                                    <th>Tanggal Retur</th>
                                    <th>Status</th>
                                    <th>User</th>
                                    <th>Total Produk</th>
                                    <th>Total Harga</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($returPenjualans as $retur)
                                @php
                                    // Get tracking number from order items
                                    $trackingNumbers = $retur->order->orderItems->pluck('tracking_number')->filter()->unique();
                                    $resi = $trackingNumbers->count() > 0 ? $trackingNumbers->implode(', ') : '-';
                                    
                                    $totalProduk = $retur->details->sum('qty');
                                    // Calculate total price using correct retur logic
                                    $totalHarga = $retur->details->sum(function($d) {
                                        if (!$d->orderItem) return 0;
                                        
                                        // Get the platform product and its mapping
                                        $orderItem = $d->orderItem;
                                        $platformProduct = $orderItem->platformProduct;
                                        
                                        if (!$platformProduct || !$platformProduct->mappingBarang) {
                                            // If no mapping, use original price
                                            $pricePerProduct = $orderItem->price_after_discount;
                                        } else {
                                            // Calculate total quantity in the package from mapping
                                            $totalPackageQty = $platformProduct->mappingBarang
                                                ->where('is_active', true)
                                                ->sum('quantity');
                                            
                                            if ($totalPackageQty > 1) {
                                                // If package contains more than 1 item, divide the price
                                                $pricePerProduct = $orderItem->price_after_discount / $totalPackageQty;
                                            } else {
                                                // If package contains only 1 item, use original price
                                                $pricePerProduct = $orderItem->price_after_discount;
                                            }
                                        }
                                        
                                        return $pricePerProduct * $d->qty;
                                    });
                                @endphp
                                <tr>
                                    <td>{{ $retur->kode_retur }}</td>
                                    <td>{{ $retur->order->order_number }}</td>
                                    <td>{{ $resi }}</td>
                                    <td>{{ $retur->order->platform->name ?? '-' }}</td>
                                    <td>{{ $retur->tanggal_retur->format('d/m/Y') }}</td>
                                    <td>
                                        @if($retur->status == 'draft')
                                        <span class="status-badge status-draft">Draft</span>
                                        @elseif($retur->status == 'selesai')
                                        <span class="status-badge status-selesai">Selesai</span>
                                        @elseif($retur->status == 'dibatalkan')
                                        <span class="status-badge status-dibatalkan">Dibatalkan</span>
                                        @endif
                                    </td>
                                    <td>{{ $retur->user->name }}</td>
                                    <td>{{ $totalProduk }}</td>
                                    <td>Rp {{ number_format($totalHarga, 0, ',', '.') }} <!-- Updated: {{ time() }} --></td>
                                    <td>
                                        <a href="{{ route('retur-penjualan.show', $retur->id) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        @if($retur->status == 'draft')
                                        <div class="btn-group mt-1">
                                            <a href="{{ route('retur-penjualan.edit', $retur->id) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form action="{{ route('retur-penjualan.process', $retur->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Yakin ingin memproses retur ini?')">
                                                    <i class="fas fa-check"></i> Proses
                                                </button>
                                            </form>
                                            <form action="{{ route('retur-penjualan.cancel', $retur->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin membatalkan retur ini?')">
                                                    <i class="fas fa-times"></i> Batalkan
                                                </button>
                                            </form>
                                        </div>
                                        @elseif($retur->status == 'selesai')
                                        <form action="{{ route('retur-penjualan.reverse', $retur->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('PERINGATAN: Batal retur akan mengembalikan SEMUA perubahan ke kondisi semula (qty item dan stok warehouse). Yakin ingin membatalkan retur ini?')">
                                                <i class="fas fa-undo"></i> Batal Retur
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="10" class="p-0">
                                        <div class="table-responsive m-0">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Nama Produk</th>
                                                        <th>Harga Produk</th>
                                                        <th>Qty</th>
                                                        <th>Total Harga</th>
                                                        <th>Kondisi</th>
                                                        <th>Alasan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($retur->details as $detail)
                                                    @php
                                                        // Use correct retur logic based on mapping quantity
                                                        if (!$detail->orderItem) {
                                                            $pricePerProduct = 0;
                                                        } else {
                                                            $orderItem = $detail->orderItem;
                                                            $platformProduct = $orderItem->platformProduct;
                                                            
                                                            if (!$platformProduct || !$platformProduct->mappingBarang) {
                                                                // If no mapping, use original price
                                                                $pricePerProduct = $orderItem->price_after_discount;
                                                            } else {
                                                                // Calculate total quantity in the package from mapping
                                                                $totalPackageQty = $platformProduct->mappingBarang
                                                                    ->where('is_active', true)
                                                                    ->sum('quantity');
                                                                
                                                                if ($totalPackageQty > 1) {
                                                                    // If package contains more than 1 item, divide the price
                                                                    $pricePerProduct = $orderItem->price_after_discount / $totalPackageQty;
                                                                } else {
                                                                    // If package contains only 1 item, use original price
                                                                    $pricePerProduct = $orderItem->price_after_discount;
                                                                }
                                                            }
                                                        }
                                                        
                                                        $totalPriceDetail = $pricePerProduct * $detail->qty;
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $detail->product->name ?? 'Produk tidak ditemukan' }}</strong>
                                                            @if($detail->product && $detail->product->sku)
                                                            <br><small class="text-muted">SKU: {{ $detail->product->sku }}</small>
                                                            @endif
                                                        </td>
                                                        <td>Rp {{ number_format($pricePerProduct, 0, ',', '.') }}</td>
                                                        <td>{{ number_format($detail->qty, 2) }}</td>
                                                        <td>Rp {{ number_format($totalPriceDetail, 0, ',', '.') }}</td>
                                                        <td>
                                                            @if($detail->kondisi === 'RUSAK')
                                                            <span class="item-status status-rusak">RUSAK</span>
                                                            @elseif($detail->kondisi === 'HILANG')
                                                            <span class="item-status status-hilang">HILANG</span>
                                                            @else
                                                            <span class="item-status status-baik">BAGUS</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $detail->alasan ?? '-' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">Tidak ada data retur penjualan</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        @if($returPenjualans->hasPages())
                        <nav aria-label="Pagination Navigation">
                            <ul class="pagination justify-content-center">
                                {{-- Previous Page Link --}}
                                @if ($returPenjualans->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPenjualans->appends(request()->query())->previousPageUrl() }}" rel="prev">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                @endif

                                @php
                                    $currentPage = $returPenjualans->currentPage();
                                    $lastPage = $returPenjualans->lastPage();
                                    $onEachSide = 2;
                                    
                                    // Calculate start and end page numbers
                                    $start = max(1, $currentPage - $onEachSide);
                                    $end = min($lastPage, $currentPage + $onEachSide);
                                    
                                    // Adjust if we're near the beginning
                                    if ($start <= 3) {
                                        $end = min($lastPage, $start + ($onEachSide * 2) + 1);
                                    }
                                    
                                    // Adjust if we're near the end
                                    if ($end >= $lastPage - 2) {
                                        $start = max(1, $end - ($onEachSide * 2) - 1);
                                    }
                                @endphp

                                {{-- First Page --}}
                                @if ($start > 1)
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPenjualans->appends(request()->query())->url(1) }}">1</a>
                                    </li>
                                    @if ($start > 2)
                                        <li class="page-item disabled">
                                            <span class="page-link pagination-ellipsis">...</span>
                                        </li>
                                    @endif
                                @endif

                                {{-- Page Numbers --}}
                                @for ($page = $start; $page <= $end; $page++)
                                    @if ($page == $currentPage)
                                        <li class="page-item active">
                                            <span class="page-link">{{ $page }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $returPenjualans->appends(request()->query())->url($page) }}">{{ $page }}</a>
                                        </li>
                                    @endif
                                @endfor

                                {{-- Last Page --}}
                                @if ($end < $lastPage)
                                    @if ($end < $lastPage - 1)
                                        <li class="page-item disabled">
                                            <span class="page-link pagination-ellipsis">...</span>
                                        </li>
                                    @endif
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPenjualans->appends(request()->query())->url($lastPage) }}">{{ $lastPage }}</a>
                                    </li>
                                @endif

                                {{-- Next Page Link --}}
                                @if ($returPenjualans->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPenjualans->appends(request()->query())->nextPageUrl() }}" rel="next">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </span>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                        @endif
                        
                        {{-- Pagination Info --}}
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Showing {{ $returPenjualans->firstItem() ?? 0 }} to {{ $returPenjualans->lastItem() ?? 0 }} of {{ $returPenjualans->total() }} results
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        text-align: center;
        min-width: 100px;
        font-size: 0.9rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .status-draft {
        background-color: #ffc107;
        color: #212529;
        border: 1px solid #e0a800;
    }

    .status-selesai {
        background-color: #28a745;
        color: white;
        border: 1px solid #218838;
    }

    .status-dibatalkan {
        background-color: #dc3545;
        color: white;
        border: 1px solid #c82333;
    }

    .item-status {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-weight: 600;
        text-align: center;
        min-width: 90px;
        font-size: 0.85rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .status-rusak {
        background-color: #dc3545;
        color: white;
        border: 1px solid #c82333;
    }

    .status-baik {
        background-color: #28a745;
        color: white;
        border: 1px solid #218838;
    }

    .status-hilang {
        background-color: #6c757d;
        color: white;
        border: 1px solid #5a6268;
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Auto-submit form when select filters change
        $('#status, #platform_id, #user_id').on('change', function() {
            $('#filterForm').submit();
        });
        
        // Auto-submit form when date inputs change
        $('#date_from, #date_to').on('change', function() {
            $('#filterForm').submit();
        });
        
        // Debounced search input
        let searchTimeout;
        $('#search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                $('#filterForm').submit();
            }, 500);
        });
        
        // Clear search on escape
        $('#search').on('keydown', function(e) {
            if (e.keyCode === 27) { // Escape key
                $(this).val('');
                $('#filterForm').submit();
            }
        });
    });
</script>
@endpush 