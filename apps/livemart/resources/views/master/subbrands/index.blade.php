@extends('layouts.app')

@section('title', 'Data Sub Brand')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-tags me-2"></i>Data Sub Brand
                    </h5>
                    <a href="{{ route('subbrands.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Tambah Sub Brand
                    </a>
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

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">No</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nama Sub Brand</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Brand</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subBrands as $index => $subbrand)
                                <tr>
                                    <td class="ps-4">
                                        <p class="text-xs font-weight-bold mb-0">{{ $subBrands->firstItem() + $index }}</p>
                                    </td>
                                    <td>
                                        <div class="d-flex px-2 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm">{{ $subbrand->name }}</h6>
                                                <p class="text-xs text-secondary mb-0">{{ \Illuminate\Support\Str::limit($subbrand->description, 50) }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-secondary text-xs font-weight-bold">
                                            @if($subbrand->brand)
                                                {{ $subbrand->brand->name }}
                                            @elseif($subbrand->brand_id)
                                                Brand ID: {{ $subbrand->brand_id }} (Not Found)
                                            @else
                                                Tidak ada
                                            @endif
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge badge-sm {{ $subbrand->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $subbrand->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        <a href="{{ route('subbrands.edit', $subbrand->id) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('subbrands.show', $subbrand->id) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form action="{{ route('subbrands.destroy', $subbrand->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada data sub brand</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center px-4 pt-4">
                        <p class="text-sm text-muted mb-0">
                            Menampilkan {{ $subBrands->firstItem() ?? 0 }} - {{ $subBrands->lastItem() ?? 0 }} dari {{ $subBrands->total() }} data
                        </p>
                        <div class="pagination-container">
                            {{ $subBrands->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 768px) {
        .d-flex.justify-content-between.align-items-center {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .d-flex.justify-content-between.align-items-center > p {
            margin-bottom: 1rem;
        }

        .pagination-container {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 15px;
        }
    }
</style>
@endsection 