@extends('layouts.app')

@section('title', 'Daftar Retur Penjualan Offline')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-store-alt me-2"></i>Daftar Retur Penjualan Offline
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('retur-offline.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                            <i class="fas fa-plus me-1"></i> Buat Retur Baru
                        </a>
                        <a href="{{ route('retur-offline.export') }}" class="btn btn-sm btn-success rounded-pill px-3">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
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
                            <form method="GET" action="{{ route('retur-offline.index') }}" id="filterForm">
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">Cari:</label>
                                            <input type="text" name="search" id="search" class="form-control" 
                                                   value="{{ request('search') }}" 
                                                   placeholder="Kode retur, surat jalan, customer, PO, user...">
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
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="date_from">Tanggal Dari:</label>
                                            <input type="date" name="date_from" id="date_from" class="form-control" 
                                                   value="{{ request('date_from') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="date_to">Tanggal Sampai:</label>
                                            <input type="date" name="date_to" id="date_to" class="form-control" 
                                                   value="{{ request('date_to') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <a href="{{ route('retur-offline.index') }}" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Reset Filter
                                            </a>
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
                                    <th>Nomor Surat Jalan</th>
                                    <th>Customer</th>
                                    <th>Tanggal Retur</th>
                                    <th>Status</th>
                                    <th>User</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($returOfflineSales as $retur)
                                <tr>
                                    <td>{{ $retur->kode_retur }}</td>
                                    <td>{{ $retur->offlineSale->surat_jalan_number }}</td>
                                    <td>{{ $retur->offlineSale->customerInfo->name ?? $retur->offlineSale->customer_name ?? '-' }}</td>
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
                                    <td>
                                        <a href="{{ route('retur-offline.show', $retur->id) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        @if($retur->status == 'selesai')
                                        <a href="{{ route('retur-offline.print', $retur->id) }}" class="btn btn-sm btn-success mt-1" target="_blank">
                                            <i class="fas fa-print"></i> Print Retur
                                        </a>
                                        @endif
                                        @if($retur->status == 'draft')
                                        <div class="btn-group mt-1">
                                            <a href="{{ route('retur-offline.edit', $retur->id) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form action="{{ route('retur-offline.process', $retur->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Yakin ingin memproses retur ini?')">
                                                    <i class="fas fa-check"></i> Proses
                                                </button>
                                            </form>
                                            <form action="{{ route('retur-offline.cancel', $retur->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin membatalkan retur ini?')">
                                                    <i class="fas fa-times"></i> Batalkan
                                                </button>
                                            </form>
                                        </div>
                                        @elseif($retur->status == 'selesai')
                                        <form action="{{ route('retur-offline.reverse', $retur->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('PERINGATAN: Batal retur akan mengembalikan SEMUA perubahan ke kondisi semula (qty item dan stok warehouse). Yakin ingin membatalkan retur ini?')">
                                                <i class="fas fa-undo"></i> Batal Retur
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data retur penjualan offline</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <nav aria-label="Pagination Navigation">
                            <ul class="pagination justify-content-center">
                                {{-- Previous Page Link --}}
                                @if ($returOfflineSales->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returOfflineSales->previousPageUrl() }}" rel="prev">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                @endif

                                {{-- Pagination Elements --}}
                                @foreach ($returOfflineSales->getUrlRange(1, $returOfflineSales->lastPage()) as $page => $url)
                                    @if ($page == $returOfflineSales->currentPage())
                                        <li class="page-item active">
                                            <span class="page-link">{{ $page }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                        </li>
                                    @endif
                                @endforeach

                                {{-- Next Page Link --}}
                                @if ($returOfflineSales->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returOfflineSales->nextPageUrl() }}" rel="next">
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
                        
                        {{-- Pagination Info --}}
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Showing {{ $returOfflineSales->firstItem() }} to {{ $returOfflineSales->lastItem() }} of {{ $returOfflineSales->total() }} results
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
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Auto-submit form when select filters change
        $('#status, #user_id').on('change', function() {
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