@extends('layouts.app')

@section('title', 'Data Penerimaan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Data Penerimaan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penerimaan</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('penerimaan.create') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-2"></i> Tambah Penerimaan
            </a>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success rounded-pill px-4 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-file-excel me-2"></i> Export Excel
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportData()">
                        <i class="fas fa-table me-2"></i> Export Ringkasan
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportDetailData()">
                        <i class="fas fa-list-alt me-2"></i> Export Detail per Barang
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Alert -->
    @if(request('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        Berhasil disimpan.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Filter Card -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header d-flex align-items-center py-3 bg-primary text-white rounded-top-3">
            <div class="d-flex justify-content-between align-items-center w-100">
                <h6 class="mb-0 fw-semibold"><i class="fas fa-filter me-2"></i> Filter & Pencarian</h6>
                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="collapse show" id="filterCollapse">
            <div class="card-body p-4">
                <form action="{{ route('penerimaan.index') }}" method="GET">
                    <div class="row g-3">
                        <!-- Kode Penerimaan Filter -->
                        <div class="col-md-4">
                            <label for="kode" class="form-label small fw-medium">Kode Penerimaan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control" id="kode" name="kode" value="{{ request('kode') }}" placeholder="PNR-000001">
                            </div>
                        </div>
                        
                        <!-- Nomor PO Filter -->
                        <div class="col-md-4">
                            <label for="nomor_po" class="form-label small fw-medium">Nomor PO</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-file-invoice"></i></span>
                            <input type="text" class="form-control" id="nomor_po" name="nomor_po" value="{{ request('nomor_po') }}" placeholder="Nomor PO">
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="col-md-4">
                        <label for="status" class="form-label small fw-medium">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Semua Status</option>
                            <option value="Unlocated" {{ request('status') == 'Unlocated' ? 'selected' : '' }}>Unlocated</option>
                            <option value="Located" {{ request('status') == 'Located' ? 'selected' : '' }}>Located</option>
                        </select>
                    </div>
                    
                    <!-- Tax Category Filter -->
                    <div class="col-md-4">
                        <label for="tax_category" class="form-label small fw-medium">Status Tax</label>
                        <select class="form-select" id="tax_category" name="tax_category">
                            <option value="">Semua Status Tax</option>
                            <option value="PKP" {{ request('tax_category') == 'PKP' ? 'selected' : '' }}>PKP</option>
                            <option value="NON PKP" {{ request('tax_category') == 'NON PKP' ? 'selected' : '' }}>NON PKP</option>
                        </select>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div class="col-md-4">
                        <label for="start_date" class="form-label small fw-medium">Tanggal Mulai</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ request('start_date') }}">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="end_date" class="form-label small fw-medium">Tanggal Akhir</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ request('end_date') }}">
                        </div>
                    </div>
                    
                    <!-- Search & Reset Buttons -->
                    <div class="col-md-12 d-flex align-items-end justify-content-center mt-3">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 me-2">
                            <i class="fas fa-search me-2"></i> Cari
                        </button>
                        <a href="{{ route('penerimaan.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Card -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-header d-flex align-items-center py-3">
            <i class="fas fa-truck-loading text-primary me-2"></i>
            <h5 class="mb-0 fw-semibold">Daftar Penerimaan Barang</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive disable-fixed-scrollbar ds-table-container">
                <table class="table table-hover">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th scope="col" class="text-center">#</th>
                            <th scope="col">Kode Penerimaan</th>
                            <th scope="col">Nomor PO</th>
                            <th scope="col">Tanggal Penerimaan</th>
                            <th scope="col" class="text-center">Status Tax</th>
                            <th scope="col" class="text-end">DPP</th>
                            <th scope="col" class="text-end">PPN</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($penerimaan as $index => $item)
                            <tr>
                                <td class="text-center">{{ $index + $penerimaan->firstItem() }}</td>
                                <td>{{ $item->kode_penerimaan }}</td>
                                <td>{{ $item->nomor_po }}</td>
                                <td>{{ $item->tanggal_penerimaan->format('d/m/Y') }}</td>
                                <td class="text-center">
                                    @if($item->taxCategory)
                                        @if($item->taxCategory->name == 'PKP')
                                            <span class="badge bg-info">PKP</span>
                                        @elseif($item->taxCategory->name == 'NON PKP')
                                            <span class="badge bg-warning">NON PKP</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ $item->taxCategory->name }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted">-</span>
                                    @endif
                                </td>
                                @php
                                    $dpp = round($item->calculated_total);
                                    $ppn = 0;
                                    if ($item->taxCategory && $item->taxCategory->name == 'PKP') {
                                        $ppn = round($dpp * 0.11);
                                    }
                                    $total = $dpp + $ppn;
                                @endphp
                                <td class="text-end">Rp {{ number_format($dpp, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($total, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    @if($item->status == 'Unlocated')
                                        <span class="badge bg-warning">Unlocated</span>
                                    @else
                                        <span class="badge bg-success">Located</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('penerimaan.show', $item->id) }}" class="btn btn-sm btn-outline-info rounded-pill px-2" data-bs-toggle="tooltip" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('penerimaan.print', $item->id) }}" class="btn btn-sm btn-outline-success rounded-pill px-2" data-bs-toggle="tooltip" title="Print" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if(Auth::user()->isSuperAdmin())
                                            {{-- Edit button - Hanya superadmin --}}
                                            <a href="{{ route('penerimaan.edit', $item->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2" data-bs-toggle="tooltip" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                        @if($item->status == 'Unlocated')
                                            {{-- Delete button - Only for superadmin --}}
                                            @if(Auth::check() && Auth::user()->isSuperAdmin())
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2" data-bs-toggle="tooltip" title="Hapus" onclick="confirmDelete('{{ $item->id }}', '{{ $item->kode_penerimaan }}')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="fw-normal">Belum ada data penerimaan</h5>
                                        <p class="text-muted">Tambahkan data penerimaan baru dengan klik tombol "Tambah Penerimaan"</p>
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
                    Menampilkan <span class="fw-semibold">{{ $penerimaan->firstItem() ?? 0 }}</span> - 
                    <span class="fw-semibold">{{ $penerimaan->lastItem() ?? 0 }}</span> dari 
                    <span class="fw-semibold">{{ $penerimaan->total() }}</span> data
                </div>
                <div>
                    @if ($penerimaan->hasPages())
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-end">
                            {{-- Previous Page Link --}}
                            @if ($penerimaan->onFirstPage())
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&laquo;</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a class="page-link" href="{{ $penerimaan->previousPageUrl() }}" aria-label="Previous">&laquo;</a>
                                </li>
                            @endif

                            {{-- Pagination Elements --}}
                            @php
                                $window = 3; // How many pages to show on each side of the current page
                                $startPage = max(1, $penerimaan->currentPage() - $window);
                                $endPage = min($penerimaan->lastPage(), $penerimaan->currentPage() + $window);
                            @endphp

                            @if ($startPage > 1)
                                <li class="page-item"><a class="page-link" href="{{ $penerimaan->url(1) }}">1</a></li>
                                @if ($startPage > 2)
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                @endif
                            @endif

                            @for ($i = $startPage; $i <= $endPage; $i++)
                                <li class="page-item {{ $penerimaan->currentPage() == $i ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $penerimaan->url($i) }}">{{ $i }}</a>
                                </li>
                            @endfor

                            @if ($endPage < $penerimaan->lastPage())
                                @if ($endPage < $penerimaan->lastPage() - 1)
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                @endif
                                <li class="page-item">
                                    <a class="page-link" href="{{ $penerimaan->url($penerimaan->lastPage()) }}">{{ $penerimaan->lastPage() }}</a>
                                </li>
                            @endif

                            {{-- Next Page Link --}}
                            @if ($penerimaan->hasMorePages())
                                <li class="page-item">
                                    <a class="page-link" href="{{ $penerimaan->nextPageUrl() }}" aria-label="Next">&raquo;</a>
                                </li>
                            @else
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&raquo;</span>
                                </li>
                            @endif
                        </ul>
                    </nav>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Validate date ranges when form is submitted
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                e.preventDefault();
                alert('Tanggal Mulai tidak boleh lebih besar dari Tanggal Akhir');
                return false;
            }
        });
    });
    
    // Delete confirmation function
    function confirmDelete(id, kode) {
        if (confirm(`Apakah Anda yakin ingin menghapus penerimaan dengan kode ${kode}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('penerimaan') }}/${id}`;
            form.innerHTML = `
                @csrf
                @method('DELETE')
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Export function that preserves current filters
    function exportData() {
        // Get current filter values
        const kode = document.getElementById('kode').value;
        const nomor_po = document.getElementById('nomor_po').value;
        const status = document.getElementById('status').value;
        const tax_category = document.getElementById('tax_category').value;
        const start_date = document.getElementById('start_date').value;
        const end_date = document.getElementById('end_date').value;
        
        // Build URL with current filters
        const exportUrl = new URL('{{ route("penerimaan.export") }}');
        const params = new URLSearchParams();
        
        if (kode) params.append('kode', kode);
        if (nomor_po) params.append('nomor_po', nomor_po);
        if (status) params.append('status', status);
        if (tax_category) params.append('tax_category', tax_category);
        if (start_date) params.append('start_date', start_date);
        if (end_date) params.append('end_date', end_date);
        
        exportUrl.search = params.toString();
        
        // Open export URL in new tab/window
        window.open(exportUrl.toString(), '_blank');
    }

    // Export detail function that preserves current filters
    function exportDetailData() {
        // Get current filter values
        const kode = document.getElementById('kode').value;
        const nomor_po = document.getElementById('nomor_po').value;
        const status = document.getElementById('status').value;
        const tax_category = document.getElementById('tax_category').value;
        const start_date = document.getElementById('start_date').value;
        const end_date = document.getElementById('end_date').value;
        
        // Build URL with current filters
        const exportUrl = new URL('{{ route("penerimaan.export-detail") }}');
        const params = new URLSearchParams();
        
        if (kode) params.append('kode', kode);
        if (nomor_po) params.append('nomor_po', nomor_po);
        if (status) params.append('status', status);
        if (tax_category) params.append('tax_category', tax_category);
        if (start_date) params.append('start_date', start_date);
        if (end_date) params.append('end_date', end_date);
        
        exportUrl.search = params.toString();
        
        // Open export URL in new tab/window
        window.open(exportUrl.toString(), '_blank');
    }
</script>
@endpush
