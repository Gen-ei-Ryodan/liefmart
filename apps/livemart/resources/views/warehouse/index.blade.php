@extends('layouts.app')

@section('title', 'Daftar Item Unlocated')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-warehouse me-2"></i>Daftar Item Unlocated
                    </h5>
                    <div>
                        <a href="{{ route('warehouse.create') }}" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-exchange-alt me-1"></i> Transfer Semua Barang
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
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            <p class="mb-0 mt-2">{{ session('success') }}</p>
                        </div>
                    @endif

                    <!-- Filter Card -->
                    <div class="card bg-light mb-4 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i> Filter & Pencarian</h6>
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse show" id="filterCollapse">
                            <div class="card-body py-3">
                                <form action="{{ route('warehouse.index') }}" method="GET">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Cari</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Cari...">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Kode Penerimaan</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                                                <input type="text" class="form-control" name="kode_penerimaan" value="{{ request('kode_penerimaan') }}" placeholder="PNR-000001">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Nama Produk</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="fas fa-box"></i></span>
                                                <input type="text" class="form-control" name="nama_produk" value="{{ request('nama_produk') }}" placeholder="Nama produk">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Tanggal Mulai</label>
                                            <input type="date" class="form-control" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-medium">Tanggal Akhir</label>
                                            <input type="date" class="form-control" name="tanggal_akhir" value="{{ request('tanggal_akhir') }}">
                                        </div>
                                        <div class="col-md-12 d-flex align-items-end justify-content-center mt-3">
                                            <button type="submit" class="btn btn-primary rounded-pill px-4 me-2">
                                                <i class="fas fa-search me-2"></i> Cari
                                            </button>
                                            <a href="{{ route('warehouse.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                                <i class="fas fa-redo me-2"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive disable-fixed-scrollbar ds-table-container">
                        <table class="table table-hover">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">NOMOR PO</th>
                                    <th scope="col">Nama Produk</th>
                                    <th scope="col" class="text-center">Jumlah</th>
                                    <th scope="col">Satuan</th>
                                    <th scope="col">Tanggal Penerimaan</th>
                                    <th scope="col" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($unlocatedItems as $index => $item)
                                <tr>
                                    <td class="text-center">{{ ($unlocatedItems->currentPage() - 1) * $unlocatedItems->perPage() + $loop->iteration }}</td>
                                    <td>{{ $item->penerimaan->nomor_po }}</td>
                                    <td>{{ $item->product->name }}</td>
                                    <td class="text-center">{{ number_format($item->remaining_qty, 0) }}</td>
                                    <td>{{ $item->satuan ? $item->satuan->name : 'N/A' }}</td>
                                    <td>{{ $item->penerimaan->tanggal_penerimaan->format('d/m/Y') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('warehouse.create', ['id' => $item->id]) }}" class="btn btn-sm btn-outline-warning rounded-pill px-2" data-bs-toggle="tooltip" title="Pindahkan">
                                            <i class="fas fa-exchange-alt"></i> Pindahkan
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5 class="fw-normal">Tidak ada barang yang tersedia</h5>
                                            <p class="text-muted">Belum ada item unlocated yang perlu dipindahkan</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-white border-top-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Menampilkan <span class="fw-semibold">{{ $unlocatedItems->firstItem() ?? 0 }}</span> -
                            <span class="fw-semibold">{{ $unlocatedItems->lastItem() ?? 0 }}</span> dari
                            <span class="fw-semibold">{{ $unlocatedItems->total() }}</span> data
                        </div>
                        <div>
                            @if ($unlocatedItems->hasPages())
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-end">
                                    <li class="page-item {{ $unlocatedItems->onFirstPage() ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ $unlocatedItems->previousPageUrl() }}">&laquo;</a>
                                    </li>

                                    @if($unlocatedItems->currentPage() > 3)
                                        <li class="page-item"><a class="page-link" href="{{ $unlocatedItems->url(1) }}">1</a></li>
                                        @if($unlocatedItems->currentPage() > 4)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                    @endif

                                    @foreach(range(max(1, $unlocatedItems->currentPage() - 2), min($unlocatedItems->lastPage(), $unlocatedItems->currentPage() + 2)) as $page)
                                        <li class="page-item {{ $unlocatedItems->currentPage() == $page ? 'active' : '' }}">
                                            <a class="page-link" href="{{ $unlocatedItems->url($page) }}">{{ $page }}</a>
                                        </li>
                                    @endforeach

                                    @if($unlocatedItems->currentPage() < $unlocatedItems->lastPage() - 2)
                                        @if($unlocatedItems->currentPage() < $unlocatedItems->lastPage() - 3)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                        <li class="page-item"><a class="page-link" href="{{ $unlocatedItems->url($unlocatedItems->lastPage()) }}">{{ $unlocatedItems->lastPage() }}</a></li>
                                    @endif

                                    <li class="page-item {{ $unlocatedItems->hasMorePages() ? '' : 'disabled' }}">
                                        <a class="page-link" href="{{ $unlocatedItems->nextPageUrl() }}">&raquo;</a>
                                    </li>
                                </ul>
                            </nav>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
