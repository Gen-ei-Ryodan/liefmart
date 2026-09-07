@extends('layouts.app')

@push('styles')
@include('master.mapping.styles')

<style>
    .form-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-section-title i {
        color: #0ea5e9;
    }
    
    .info-table {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .info-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        width: 30%;
    }
    
    .info-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        color: #6b7280;
    }
    
    .info-table tr:last-child th,
    .info-table tr:last-child td {
        border-bottom: none;
    }
    
    .add-product-section {
        background: #f0f9ff;
        border: 2px dashed #0ea5e9;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
    }
    
    .add-product-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .add-product-form .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 200px;
    }
    
    .btn-add {
        background: #0ea5e9;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        white-space: nowrap;
    }
    
    .btn-add:hover {
        background: #0284c7;
        transform: translateY(-1px);
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }
    
    .btn-back {
        background: #6b7280;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .btn-back:hover {
        background: #4b5563;
        color: white;
        transform: translateY(-1px);
    }
    
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        outline: none;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .help-text {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }
    
    .existing-mappings {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .existing-mappings th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .existing-mappings td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        color: #6b7280;
    }
    
    .existing-mappings tr:last-child td {
        border-bottom: none;
    }
    
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-secondary { background: #f3f4f6; color: #6b7280; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    
    @media (max-width: 768px) {
        .add-product-form {
            flex-direction: column;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn-back {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="m-0 fw-bold text-primary"><i class="fas fa-link me-2"></i>Edit Produk Mapping</h5>
                </div>
                <div class="card-body p-4">
            <!-- Platform Information Section -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Produk Platform
                </h6>
                
                <div class="info-table">
                    <table class="table mb-0">
                        <tr>
                            <th>Platform</th>
                            <td>
                                <span class="badge platform-{{ $platformProduct->platform ? strtolower($platformProduct->platform->name) : 'unknown' }}">
                                    {{ $platformProduct->platform ? $platformProduct->platform->name : 'Unknown Platform' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Nama Produk</th>
                            <td>{{ $platformProduct->platform_product_name }}</td>
                        </tr>
                        @if($platformProduct->variant)
                        <tr>
                            <th>Variant</th>
                            <td>{{ $platformProduct->variant }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Existing Mappings Section -->
            @if($existingMappings->count() > 0)
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-list"></i>
                    Mapping yang Sudah Ada
                </h6>
                <p class="help-text mb-3">Daftar produk master yang sudah dimapping untuk produk platform ini</p>
                
                <div class="existing-mappings">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Produk Master</th>
                                <th>Quantity Ratio</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($existingMappings as $mapping)
                            <tr>
                                <td>{{ $mapping->product ? $mapping->product->name : 'Product tidak ditemukan (ID: ' . $mapping->product_id . ')' }}</td>
                                <td>{{ $mapping->quantity }}</td>
                                <td>v{{ $mapping->version }}</td>
                                <td>
                                    @if($mapping->is_active)
                                        <span class="badge badge-success">Aktif</span>
                                    @else
                                        <span class="badge badge-secondary">Tidak Aktif</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('master.mapping.edit', $mapping->id) }}" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Add New Mapping Section -->
            <div class="add-product-section">
                <h6 class="form-section-title mb-3">
                    <i class="fas fa-plus-circle"></i>
                    Tambah Mapping Baru
                </h6>
                <p class="help-text mb-3">Tambahkan produk master untuk platform product ini</p>
                
                <form action="{{ route('master.mapping.add-product') }}" method="POST">
                    @csrf
                    <input type="hidden" name="platform_product_id" value="{{ $platformProduct->id }}">
                    
                    <div class="add-product-form">
                        <div class="form-group">
                            <label class="form-label">Pilih Produk Master</label>
                            <select name="product_id" id="product_id" class="form-select" required>
                                <option value="">-- Pilih Produk Master --</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity Ratio</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" 
                                   class="form-control" required value="1"
                                   oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                        </div>
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn-add">
                                <i class="fas fa-plus me-2"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="help-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Pilih produk master dan tentukan berapa jumlah yang dibutuhkan
                    </div>
                </form>
            </div>
        </div>
        
        <div class="form-actions">
            <a href="{{ route('master.mapping.index') }}" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize TomSelect for product dropdown
    let productSelect;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize product select
        if (document.getElementById('product_id')) {
            productSelect = new TomSelect('#product_id', {
                placeholder: 'Pilih Produk Master...',
                allowEmptyOption: true,
                searchField: ['text'],
                render: {
                    option: function(data, escape) {
                        return '<div class="ts-option">' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(value) {
                    // Sync with original select element
                    const productElement = document.getElementById('product_id');
                    if (productElement) {
                        productElement.value = value;
                        productElement.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
        // Add loading state to form
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                // Sync TomSelect values before form submission
                if (productSelect) {
                    const productElement = document.getElementById('product_id');
                    if (productElement) {
                        productElement.value = productSelect.getValue();
                    }
                }
                
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menambahkan...';
                    submitBtn.disabled = true;
                    
                    // Re-enable after 5 seconds as fallback
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        }
        
        // Add smooth animations
        document.querySelectorAll('.form-section').forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            setTimeout(() => {
                section.style.transition = 'all 0.3s ease';
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, 100);
        });
    });
</script>
@endpush
