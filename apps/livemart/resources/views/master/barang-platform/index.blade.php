@extends('layouts.app')

@push('styles')
<style>
    .product-name-cell {
        white-space: normal !important;
        word-wrap: break-word;
        word-break: break-word;
        max-width: 300px;
        min-width: 200px;
        line-height: 1.4;
        vertical-align: top;
        padding: 0.25rem 0.5rem !important;
    }

    .product-name {
        display: block;
        max-width: 100%;
        word-wrap: break-word;
        word-break: break-word;
        white-space: pre-wrap;
        line-height: 1.4;
        padding: 0.1rem 0;
        text-indent: 0;
    }

    .product-name::before {
        content: '';
        display: block;
        height: 0;
        overflow: hidden;
    }

    .variant-cell {
        white-space: normal !important;
        word-wrap: break-word;
        word-break: break-word;
        max-width: 200px;
        min-width: 150px;
        line-height: 1.4;
        vertical-align: top;
        padding: 0.25rem 0.5rem !important;
    }

    .table-responsive thead.bg-dark th {
        background-color: #343a40 !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-store me-2"></i>Master Barang Platform
                    </h5>
                    <a href="{{ route('barang-platform.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Tambah Barang Platform
                    </a>
                </div>

                <div class="card-body p-4">
                    <!-- Filter Form -->
                    <div class="card mb-4 border">
                        <div class="card-body bg-light p-3">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Platform</label>
                                    <select name="platform" class="form-select">
                                        <option value="">Semua Platform</option>
                                        @foreach($platforms as $platform)
                                            <option value="{{ $platform->name }}" {{ request('platform') == $platform->name ? 'selected' : '' }}>
                                                {{ $platform->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Nama Barang</label>
                                    <input type="text" name="search" class="form-control" placeholder="Cari nama barang..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Variant</label>
                                    <input type="text" name="variant" class="form-control" placeholder="Cari variant..." value="{{ request('variant') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Per Halaman</label>
                                    <select name="per_page" class="form-select">
                                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                        <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filter
                                        </button>
                                        <a href="{{ route('barang-platform.index') }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-undo"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th class="text-center" width="5%">No</th>
                                    <th width="12%">Platform</th>
                                    <th width="40%">Nama Barang</th>
                                    <th width="18%">Variant</th>
                                    <th class="text-center" width="8%">Mapping</th>
                                    <th class="text-center" width="10%">Digunakan di Sales</th>
                                    <th class="text-center" width="7%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($platformProducts as $index => $product)
                                    <tr>
                                        <td class="text-center">{{ $platformProducts->firstItem() + $index }}</td>
                                        <td>
                                            @if($product->platform)
                                                <span class="badge bg-info text-white">{{ $product->platform->name }}</span>
                                            @else
                                                <span class="badge bg-danger text-white">Platform tidak ditemukan</span>
                                            @endif
                                        </td>
                                        <td class="product-name-cell">
                                            <div class="product-name" title="{{ $product->platform_product_name }}">
                                                {{ $product->platform_product_name }}
                                            </div>
                                        </td>
                                        <td class="variant-cell">
                                            @if($product->variant)
                                                <span class="badge bg-secondary text-white" style="white-space: normal; word-wrap: break-word; display: inline-block; max-width: 100%;">{{ $product->variant }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($product->mappingBarang->count() > 0)
                                                <span class="badge bg-success text-white">
                                                    <i class="fas fa-link"></i> {{ $product->mappingBarang->count() }}
                                                </span>
                                            @else
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-unlink"></i> 0
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @php
                                                $usedInSales = \DB::table('order_items')
                                                    ->where('platform_product_id', $product->id)
                                                    ->exists();
                                            @endphp
                                            @if($usedInSales)
                                                <span class="badge bg-success text-white">
                                                    <i class="fas fa-check"></i> Ya
                                                </span>
                                            @else
                                                <span class="badge bg-secondary text-white">
                                                    <i class="fas fa-times"></i> Belum
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('barang-platform.edit', $product->id) }}" 
                                                   class="btn btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('barang-platform.destroy', $product->id) }}" 
                                                      method="POST" class="d-inline"
                                                      onsubmit="return confirm('Yakin ingin menghapus?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">Tidak ada data barang platform</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted small">
                            Menampilkan {{ $platformProducts->firstItem() ?? 0 }} sampai {{ $platformProducts->lastItem() ?? 0 }} 
                            dari {{ $platformProducts->total() }} hasil
                        </div>
                        <div>
                            {{ $platformProducts->appends(request()->query())->links('pagination.custom') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to trim leading spaces but preserve internal spaces
    function trimLeadingSpaces() {
        const productNames = document.querySelectorAll('.product-name');
        
        productNames.forEach(function(element) {
            const originalText = element.textContent;
            
            // Trim leading spaces but keep internal spaces
            const trimmedText = originalText.replace(/^\s+/, '');
            
            // Only update if there were leading spaces
            if (trimmedText !== originalText) {
                element.textContent = trimmedText;
            }
        });
    }
    
    // Run the function when page loads
    trimLeadingSpaces();
    
    // Also run when table content changes (for pagination, etc.)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                trimLeadingSpaces();
            }
        });
    });
    
    // Observe the table for changes
    const table = document.querySelector('.table');
    if (table) {
        observer.observe(table, { childList: true, subtree: true });
    }
});
</script>
@endpush
