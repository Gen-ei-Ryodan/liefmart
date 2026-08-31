@extends('layouts.app')

@push('styles')
@include('master.mapping.styles')

<style>
    /* Simple filter system */
    .filter-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: 1px solid #cbd5e0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .search-input {
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .search-input:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        outline: none;
    }
    
    .platform-select {
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        background: white;
        transition: all 0.3s ease;
            width: 100%;
        }
    
    .platform-select:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        outline: none;
    }
    
    .clear-filters-btn {
        background: #6b7280;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .clear-filters-btn:hover {
        background: #4b5563;
        transform: translateY(-1px);
    }
    
    /* Product cards instead of table */
    .product-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .product-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        transform: translateY(-2px);
        border-color: #7c3aed;
    }
    
    .product-header {
        display: flex;
        justify-content: between;
        align-items: flex-start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .product-info {
        flex: 1;
        min-width: 200px;
    }
    
    .product-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
    }
    
    .product-platform {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }
    
    .platform-shopee { background: #fbbf24; color: #92400e; }
    .platform-tiktok { background: #3b82f6; color: #1e3a8a; }
    
    .product-variant {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }
    
    .mapping-section {
        margin-top: 1rem;
    }
    
    .mapping-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.75rem;
    }
    
    .mapping-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .mapping-item {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .mapping-item-name {
        font-weight: 500;
        color: #374151;
    }
    
    .mapping-item-qty {
        background: #7c3aed;
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .no-mapping {
        color: #f59e0b;
        font-style: italic;
        font-size: 0.9rem;
    }
    
    .product-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .action-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-edit {
        background: #f59e0b;
        color: white;
    }
    
    .btn-edit:hover {
        background: #d97706;
        transform: translateY(-1px);
    }
    
    .btn-delete {
        background: #ef4444;
        color: white;
    }
    
    .btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    
    /* Custom pagination info styling */
    .pagination-info {
        text-align: center;
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6b7280;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #d1d5db;
    }
    
    @media (max-width: 768px) {
        .filter-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .stats-row {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .stat-card {
            min-width: unset;
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
        }
        
        .product-header {
            flex-direction: column;
        }
        
        .product-actions {
            width: 100%;
            justify-content: stretch;
        }
        
        .action-btn {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <!-- Header Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 fw-bold text-primary">
                <i class="fas fa-project-diagram me-2"></i>Mapping Produk
            </h5>
            <div class="d-flex gap-2">
                <a href="{{ route('master.mapping.export.excel', request()->query()) }}" class="btn btn-sm btn-success rounded-pill px-3">
                    <i class="fas fa-file-excel me-1"></i> Export All
                </a>
                <a href="{{ route('master.mapping.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i> Tambah Mapping
                </a>
            </div>
        </div>
    </div>

    <!-- Simple Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Statistik Mapping per Platform</h5>
                    <div class="row">
                        @php
                            // Optimasi: hitung semua statistik sekaligus
                            $platformStats = [];
                            foreach($platforms as $platform) {
                                $platformProductsCount = \App\Models\PlatformProduct::where('platform_id', $platform->id)->count();
                                $mappedProducts = \App\Models\PlatformProduct::where('platform_id', $platform->id)
                                    ->whereHas('mappingBarang')->count();
                                $unmappedProducts = $platformProductsCount - $mappedProducts;
                                $percentage = $platformProductsCount > 0 ? round(($mappedProducts / $platformProductsCount) * 100, 1) : 0;
                                
                                $platformStats[$platform->id] = [
                                    'name' => $platform->name,
                                    'total' => $platformProductsCount,
                                    'mapped' => $mappedProducts,
                                    'unmapped' => $unmappedProducts,
                                    'percentage' => $percentage
                                ];
                            }
                        @endphp
                        @foreach($platformStats as $stat)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-store"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">{{ ucfirst($stat['name']) }}</h6>
                                                <small class="text-muted">
                                                    {{ number_format($stat['mapped']) }}/{{ number_format($stat['total']) }} 
                                                    ({{ $stat['percentage'] }}%)
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
    
    <!-- Filter Section -->
    <div class="filter-card">
        <form method="GET" action="{{ route('master.mapping.index') }}" id="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search" class="form-label fw-semibold mb-2">Cari Produk</label>
                    <input type="text" id="search" name="search" class="search-input" 
                           placeholder="Cari berdasarkan nama produk..." 
                           value="{{ $search }}">
                </div>
                <div class="filter-group">
                    <label for="variant" class="form-label fw-semibold mb-2">Cari Variant</label>
                    <input type="text" id="variant" name="variant" class="search-input" 
                           placeholder="Cari berdasarkan variant..." 
                           value="{{ $variant }}">
                </div>
                <div class="filter-group">
                    <label for="platform" class="form-label fw-semibold mb-2">Platform</label>
                    <select id="platform" name="platform" class="platform-select">
                        <option value="">Semua Platform</option>
                        @foreach($platforms as $platformOption)
                            <option value="{{ $platformOption->name }}" 
                                    {{ $selectedPlatform == $platformOption->name ? 'selected' : '' }}>
                                {{ $platformOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('master.mapping.index') }}" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
            
    <!-- Products List - Simple Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Daftar Produk Platform</h5>
        </div>
        <div class="card-body">
            
            @if($platformProducts && is_countable($platformProducts) && count($platformProducts) > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Produk</th>
                                <th>Platform</th>
                                <th>Variant</th>
                                <th>Status Mapping</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($platformProducts as $index => $platformProduct)
                                <tr>
                                    <td>{{ $platformProducts->firstItem() + $index }}</td>
                                    <td>
                                        <strong>{{ $platformProduct->platform_product_name }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $platformProduct->platform ? $platformProduct->platform->name : 'Unknown Platform' }}</span>
                                    </td>
                                    <td>
                                        @if($platformProduct->variant)
                                            <span class="text-muted">{{ $platformProduct->variant }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $hasMapping = false;
                                            $mappingCount = 0;
                                            if ($platformProduct->mappingBarang && is_object($platformProduct->mappingBarang)) {
                                                $hasMapping = $platformProduct->mappingBarang->where('is_active', true)->count() > 0;
                                                $mappingCount = $platformProduct->mappingBarang->where('is_active', true)->count();
                                            }
                                        @endphp
                                        
                                        @if($hasMapping)
                                            <span class="badge bg-success" style="cursor: pointer;" 
                                                  data-name="{{ $platformProduct->platform_product_name }}"
                                                  data-id="{{ $platformProduct->id }}"
                                                  data-count="{{ $mappingCount }}"
                                                  onclick="handleShowMappingDetail(this)">
                                                <i class="fas fa-check"></i> Sudah Mapping ({{ $mappingCount }})
                                            </span>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle"></i> Belum Mapping
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            @if($hasMapping)
                                                @php
                                                    $activeMapping = $platformProduct->mappingBarang->where('is_active', true)->first();
                                                @endphp
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-name="{{ $platformProduct->platform_product_name }}"
                                                        data-id="{{ $platformProduct->id }}"
                                                        data-count="{{ $mappingCount }}"
                                                        onclick="handleShowMappingDetail(this)"
                                                        title="Lihat Detail Mapping">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="{{ route('master.mapping.edit', $activeMapping->id) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit Mapping">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('master.mapping.edit-product', $platformProduct->id) }}" 
                                                   class="btn btn-sm btn-success" title="Tambah Mapping">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            @endif
                                            
                                            @if(auth()->user()->canEdit())
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-mapping-btn" 
                                                    data-platform-product-id="{{ $platformProduct->id }}"
                                                    data-platform-product-name="{{ $platformProduct->platform_product_name }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            Menampilkan {{ $platformProducts->firstItem() ?? 0 }} - {{ $platformProducts->lastItem() ?? 0 }} 
                            dari {{ $platformProducts->total() }} data
                        </small>
                    </div>
                    <div>
                        {{ $platformProducts->withQueryString()->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4>Tidak ada produk ditemukan</h4>
                    <p class="text-muted">Coba ubah filter pencarian atau platform</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Simple Info -->
    <div class="mt-4">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Info:</strong> Gunakan filter di atas untuk mencari produk platform yang ingin di-mapping.
        </div>
    </div>
</div>

<!-- Modal Delete Confirmation -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus mapping untuk produk <strong id="delete-product-name"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Perhatian:</strong> Ini akan menghapus:
                    <ol class="mb-0 mt-2">
                        <li>Semua mapping produk master yang terkait</li>
                        <li>Data produk platform</li>
                    </ol>
                </div>
                <p class="text-danger"><strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Batal
                </button>
                <form id="delete-form" action="" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Mapping -->
<div class="modal fade" id="mappingDetailModal" tabindex="-1" aria-labelledby="mappingDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="mappingDetailModalLabel">
                    <i class="fas fa-link me-2"></i>Detail Mapping Produk
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="mappingDetailContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat detail mapping...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button click
    document.querySelectorAll('.delete-mapping-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const platformProductId = this.dataset.platformProductId;
            const platformProductName = this.dataset.platformProductName;
            
            document.getElementById('delete-product-name').textContent = platformProductName;
            document.getElementById('delete-form').action = 
                '{{ route("master.mapping.destroy-all", ["platformProductId" => "_id_"]) }}'.replace('_id_', platformProductId);
        });
    });
    
    // Function to handle show mapping detail click
    window.handleShowMappingDetail = function(element) {
        const id = element.getAttribute('data-id');
        const name = element.getAttribute('data-name');
        const count = element.getAttribute('data-count');
        showMappingDetail(id, name, count);
    };

    // Function to show mapping detail
    window.showMappingDetail = function(platformProductId, platformProductName, mappingCount) {
        // Update modal title
        document.getElementById('mappingDetailModalLabel').innerHTML = 
            '<i class="fas fa-link me-2"></i>Detail Mapping: ' + platformProductName;
        
        // Show loading
        document.getElementById('mappingDetailContent').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Memuat detail mapping...</p>
            </div>
        `;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('mappingDetailModal'));
        modal.show();
        
        // Fetch mapping details via AJAX
        fetch(`/master/mapping/details/${platformProductId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = `
                        <div class="mb-3">
                            <h6 class="text-primary">${data.platformProduct.platform_product_name}</h6>
                            <p class="text-muted mb-0">Platform: <span class="badge bg-primary">${data.platformProduct.platform.name}</span></p>
                            ${data.platformProduct.variant ? `<p class="text-muted mb-0">Variant: ${data.platformProduct.variant}</p>` : ''}
                        </div>
                    `;
                    
                    if (data.mappings && data.mappings.length > 0) {
                        console.log('Mappings found:', data.mappings.length);
                        console.log('First mapping:', data.mappings[0]);
                        html += `
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Produk Master</th>
                                            <th>Variant</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.mappings.forEach((mapping, index) => {
                            html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>
                                        <strong>${mapping.product ? mapping.product.name : 'Product tidak ditemukan (ID: ' + mapping.product_id + ')'}</strong>
                                        ${mapping.product && mapping.product.sku ? `<br><small class="text-muted">SKU: ${mapping.product.sku}</small>` : ''}
                                    </td>
                                    <td>
                                        ${mapping.product && mapping.product.product_variant ? mapping.product.product_variant.name : '-'}
                                    </td>
                                    <td>
                                        <span class="badge bg-info">${mapping.quantity}</span>
                                    </td>
                                    <td>
                                        <span class="badge ${mapping.is_active ? 'bg-success' : 'bg-secondary'}">
                                            ${mapping.is_active ? 'Aktif' : 'Tidak Aktif'}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/master/mapping/${mapping.id}/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Belum ada mapping untuk produk ini.
                            </div>
                        `;
                    }
                    
                    document.getElementById('mappingDetailContent').innerHTML = html;
                } else {
                    document.getElementById('mappingDetailContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Gagal memuat detail mapping: ${data.message || 'Unknown error'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('mappingDetailContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Terjadi kesalahan saat memuat detail mapping.
                    </div>
                `;
            });
    };
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);
</script>
@endpush
