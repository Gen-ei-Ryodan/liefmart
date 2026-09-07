@extends('layouts.app')

@section('title', 'Daftar Retur Pembelian')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-undo-alt me-2"></i>Daftar Retur Pembelian
                    </h5>
                    <div>
                        <a href="#" data-export="{{ route('retur-pembelian.export') }}" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </a>
                        <a href="{{ route('retur-pembelian.create') }}" class="btn btn-sm btn-primary">
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
                            <form method="GET" action="{{ route('retur-pembelian.index') }}" id="filterForm">
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="search">Cari:</label>
                                            <input type="text" name="search" id="search" class="form-control" 
                                                   value="{{ request('search') }}" 
                                                   placeholder="Kode retur, nomor PO, kode penerimaan, user...">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label for="tipe_retur">Tipe Retur:</label>
                                            <select name="tipe_retur" id="tipe_retur" class="form-control">
                                                <option value="">Semua Tipe</option>
                                                <option value="sebagian" {{ request('tipe_retur') == 'sebagian' ? 'selected' : '' }}>Sebagian</option>
                                                <option value="full" {{ request('tipe_retur') == 'full' ? 'selected' : '' }}>Full</option>
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
                                            <a href="{{ route('retur-pembelian.index') }}" class="btn btn-secondary">
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
                                    <th>Nomor PO</th>
                                    <th>Tanggal Penerimaan</th>
                                    <th>Tanggal Retur</th>
                                    <th>Tipe Retur</th>
                                    <th>Total Qty</th>
                                    <th>Total Nominal</th>
                                    <th>User</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($returPembelians as $retur)
                                @php
                                    $totalQty = $retur->details->sum('qty');
                                    $totalNominal = $retur->details->sum(function($detail) {
                                        if ($detail->penerimaanDetail) {
                                            $penerimaanDetail = $detail->penerimaanDetail;
                                            // Calculate harga per unit after tiered discounts
                                            $hargaHpp = 0;
                                            if ($penerimaanDetail->qty > 0 && $penerimaanDetail->subtotal > 0) {
                                                $hargaHpp = $penerimaanDetail->subtotal / $penerimaanDetail->qty;
                                            } else {
                                                // Fallback: calculate from harga_hpp with discounts
                                                $hargaHpp = $penerimaanDetail->harga_hpp;
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $diskonPersen = $penerimaanDetail->{"diskon_persen_$i"} ?? 0;
                                                    if ($diskonPersen > 0) {
                                                        $hargaHpp = $hargaHpp * (1 - $diskonPersen / 100);
                                                    }
                                                }
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $diskonNominal = $penerimaanDetail->{"diskon_nominal_$i"} ?? 0;
                                                    if ($diskonNominal > 0 && $penerimaanDetail->qty > 0) {
                                                        $hargaHpp = $hargaHpp - ($diskonNominal / $penerimaanDetail->qty);
                                                    }
                                                }
                                            }
                                            return $hargaHpp * $detail->qty;
                                        }
                                        return 0;
                                    });
                                @endphp
                                <tr>
                                    <td>{{ $retur->kode_retur }}</td>
                                    <td>{{ $retur->penerimaan->nomor_po }}</td>
                                    <td>{{ $retur->penerimaan->tanggal_penerimaan->format('d/m/Y') }}</td>
                                    <td>{{ $retur->tanggal_retur->format('d/m/Y') }}</td>
                                    <td>
                                        @if($retur->tipe_retur == 'sebagian')
                                        <span class="badge badge-warning text-dark">Sebagian</span>
                                        @elseif($retur->tipe_retur == 'full')
                                        <span class="badge badge-danger">Full</span>
                                        @else
                                        <span class="badge badge-secondary">-</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($totalQty, 0) }}</td>
                                    <td>Rp {{ number_format($totalNominal, 0, ',', '.') }}</td>
                                    <td>{{ $retur->user ? $retur->user->name : 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('retur-pembelian.show', $retur->id) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                        <a href="{{ route('retur-pembelian.edit', $retur->id) }}" class="btn btn-sm btn-warning ml-1">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="{{ route('retur-pembelian.destroy', $retur->id) }}" method="POST" class="d-inline ml-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus retur ini? Stok akan dikembalikan ke gudang.')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">Tidak ada data retur pembelian</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <nav aria-label="Pagination Navigation">
                            <ul class="pagination justify-content-center">
                                {{-- Previous Page Link --}}
                                @if ($returPembelians->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPembelians->previousPageUrl() }}" rel="prev">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                @endif

                                {{-- Pagination Elements --}}
                                @foreach ($returPembelians->getUrlRange(1, $returPembelians->lastPage()) as $page => $url)
                                    @if ($page == $returPembelians->currentPage())
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
                                @if ($returPembelians->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $returPembelians->nextPageUrl() }}" rel="next">
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
                                Showing {{ $returPembelians->firstItem() }} to {{ $returPembelians->lastItem() }} of {{ $returPembelians->total() }} results
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
    .btn-group {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .btn-group form {
        margin-bottom: 5px;
    }

    .badge {
        font-size: 90%;
        font-weight: 600;
        padding: 6px 10px;
        border-radius: 4px;
    }

    .badge-warning {
        background-color: #ffc107;
    }

    .badge-danger {
        background-color: #dc3545;
    }

    @media (max-width: 768px) {
        .btn-group {
            flex-direction: column;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Auto-submit form when select filters change
        $('#tipe_retur, #user_id').on('change', function() {
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