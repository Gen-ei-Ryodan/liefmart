@extends('layouts.app')

@section('title', 'Tambah Mapping Produk')

@push('styles')
@include('master.mapping.styles')

<style>
    .form-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .form-header {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        color: white;
        padding: 2rem;
        text-align: center;
    }
    
    .form-header h2 {
        margin: 0;
        font-weight: 700;
        font-size: 1.5rem;
    }
    
    .form-header p {
        margin: 0.5rem 0 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    
    .form-body {
        padding: 2rem;
    }
    
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
        color: #7c3aed;
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
    
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        outline: none;
    }
    
    .product-row {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: end;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .product-row .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 200px;
    }
    
    .product-row .form-group:last-child {
        flex: 0 0 auto;
    }
    
    .btn-add-product {
        background: #10b981;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-add-product:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-remove-product {
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-remove-product:hover {
        background: #dc2626;
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
    
    .btn-save {
        background: #7c3aed;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-save:hover {
        background: #6d28d9;
        transform: translateY(-1px);
    }
    
    .help-text {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }
    
    .required {
        color: #ef4444;
    }
    
    @media (max-width: 768px) {
        .form-body {
            padding: 1rem;
        }
        
        .product-row {
            flex-direction: column;
            align-items: stretch;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn-back, .btn-save {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Mapping Produk</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('master.mapping.index') }}" class="text-decoration-none">Mapping Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('master.mapping.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="form-card">
        <div class="form-body">
            <form action="{{ route('master.mapping.store') }}" method="POST" id="mappingForm">
                @csrf
                <input type="hidden" name="debug" value="1">
                
                <!-- Platform Information Section -->
                <div class="form-section">
                    <h6 class="form-section-title">
                        <i class="fas fa-store"></i>
                        Informasi Produk Platform
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Platform <span class="required">*</span></label>
                                <select name="platform_id" id="platform-select" class="form-select @error('platform_id') is-invalid @enderror" required {{ isset($platformPreselected) ? 'disabled' : '' }}>
                                    <option value="">Pilih Platform</option>
                                    @foreach($platforms as $platform)
                                    <option value="{{ $platform->id }}" {{ 
                                        (old('platform_id') == $platform->id || 
                                        (isset($platformPreselected) && $platformPreselected == $platform->id)) ? 'selected' : '' 
                                    }}>
                                        {{ $platform->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @if(isset($platformPreselected))
                                <input type="hidden" name="platform_id" value="{{ $platformPreselected }}">
                                @endif
                                @error('platform_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="help-text">Pilih platform tempat produk ini dijual</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nama Produk Platform <span class="required">*</span></label>
                                <input type="text" name="platform_product_name" class="form-control @error('platform_product_name') is-invalid @enderror" 
                                       value="{{ old('platform_product_name') ?? (isset($productNamePreselected) ? $productNamePreselected : '') }}" 
                                       placeholder="Masukkan nama produk sesuai di platform" required {{ isset($productNamePreselected) ? 'readonly' : '' }}>
                                @error('platform_product_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="help-text">Nama produk sesuai yang tertera di platform</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Variant (Opsional)</label>
                                <input type="text" name="variant" class="form-control @error('variant') is-invalid @enderror" 
                                       value="{{ old('variant') ?? (isset($variantPreselected) ? $variantPreselected : '') }}" 
                                       placeholder="Masukkan variant produk (jika ada)" {{ isset($variantPreselected) ? 'readonly' : '' }}>
                                @error('variant')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="help-text">Variant produk seperti ukuran, warna, dll (opsional)</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Master Section -->
                <div class="form-section">
                    <h6 class="form-section-title">
                        <i class="fas fa-cubes"></i>
                        Produk Master yang Terkait
                    </h6>
                    <p class="help-text mb-3">Satu produk platform dapat terdiri dari beberapa produk master. Tambahkan produk master yang diperlukan untuk membuat produk platform ini.</p>
                    
                    <div id="products-container">
                        <div class="product-row" id="product-row-1">
                            <div class="form-group">
                                <label class="form-label">Produk Master <span class="required">*</span></label>
                                <select name="product_id[]" id="product_id_1" class="form-select product-select" required>
                                    <option value="">-- Pilih Produk Master --</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jumlah <span class="required">*</span></label>
                                <input type="number" name="quantity[]" step="0.01" min="0.01" 
                                       class="form-control" placeholder="1" required value="1">
                            </div>
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div style="height: 40px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-add-product" onclick="addProductField()">
                        <i class="fas fa-plus"></i> Tambah Produk Master Lain
                    </button>
                </div>
                
                @if(isset($fromAutoCreate) && $fromAutoCreate)
                <input type="hidden" name="from_auto_create" value="1">
                @endif
                
                <div class="form-actions">
                    <a href="{{ isset($fromAutoCreate) && $fromAutoCreate ? url()->previous() : route('master.mapping.index') }}" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Simpan Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Keep track of the current product fields count
    let productCount = 1;
    
    // Initialize TomSelect instances
    let platformSelect, productSelects = [];
    
    document.addEventListener('DOMContentLoaded', function() {
        // Debug form action URL
        const form = document.getElementById('mappingForm');
        if (form) {
            console.log('Form action URL:', form.action);
            console.log('Form method:', form.method);
        }
        
        // Check if this is auto-filled form
        const isAutoFilled = {{ isset($fromAutoCreate) ? 'true' : 'false' }};
        if (isAutoFilled) {
            console.log('AUTO-FILLED FORM DETECTED');
            console.log('Platform preselected:', {{ isset($platformPreselected) ? $platformPreselected : 'null' }});
            console.log('Product name preselected:', '{{ isset($productNamePreselected) ? $productNamePreselected : '' }}');
            console.log('Variant preselected:', '{{ isset($variantPreselected) ? $variantPreselected : '' }}');
        }
        
        // Initialize platform select
        if (document.getElementById('platform-select')) {
            console.log('Initializing platform TomSelect...');
            const platformElement = document.getElementById('platform-select');
            const currentValue = platformElement.value;
            console.log('Platform current value:', currentValue);
            
            platformSelect = new TomSelect('#platform-select', {
                placeholder: 'Pilih Platform...',
                allowEmptyOption: true,
                searchField: ['text'],
                render: {
                    option: function(data, escape) {
                        return '<div class="ts-option">' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(value) {
                    console.log('Platform TomSelect onChange:', value);
                    // Sync with original select element
                    const platformElement = document.getElementById('platform-select');
                    if (platformElement) {
                        platformElement.value = value;
                        platformElement.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },
                onInitialize: function() {
                    console.log('Platform TomSelect initialized');
                    // Set the current value if it exists
                    if (currentValue) {
                        this.setValue(currentValue);
                        console.log('Platform value set to:', currentValue);
                    }
                }
            });
            console.log('Platform TomSelect created:', platformSelect);
        }
        
        // Initialize first product select with delay for auto-filled forms
        if (isAutoFilled) {
            // Add delay for auto-filled forms to ensure proper initialization
            setTimeout(() => {
                initializeProductSelect('product_id_1');
            }, 100);
        } else {
            initializeProductSelect('product_id_1');
        }
    });
    
    function initializeProductSelect(selectId) {
        const selectElement = document.getElementById(selectId);
        if (selectElement && !productSelects[selectId]) {
            console.log('Initializing TomSelect for:', selectId);
            const currentValue = selectElement.value;
            console.log('Product current value:', currentValue);
            
            productSelects[selectId] = new TomSelect(selectElement, {
                placeholder: 'Pilih Produk Master...',
                allowEmptyOption: true,
                searchField: ['text'],
                render: {
                    option: function(data, escape) {
                        return '<div class="ts-option">' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(value) {
                    console.log('TomSelect onChange:', selectId, 'value:', value);
                    // Sync with original select element
                    selectElement.value = value;
                    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                },
                onInitialize: function() {
                    console.log('TomSelect initialized for:', selectId);
                    // Set the current value if it exists
                    if (currentValue) {
                        this.setValue(currentValue);
                        console.log('Product value set to:', currentValue);
                    }
                }
            });
            
            console.log('TomSelect created for:', selectId, productSelects[selectId]);
        }
    }
    
    // Function to sync TomSelect values with original select elements
    function syncTomSelectValues() {
        console.log('Syncing TomSelect values...');
        
        // Sync platform select
        if (platformSelect) {
            const platformElement = document.getElementById('platform-select');
            if (platformElement) {
                const platformValue = platformSelect.getValue();
                platformElement.value = platformValue;
                console.log('Platform synced:', platformValue);
            }
        }
        
        // Sync product selects
        Object.keys(productSelects).forEach(selectId => {
            const tomSelect = productSelects[selectId];
            const originalSelect = document.getElementById(selectId);
            if (tomSelect && originalSelect) {
                const productValue = tomSelect.getValue();
                originalSelect.value = productValue;
                console.log('Product synced:', selectId, 'value:', productValue);
            }
        });
        
        console.log('TomSelect sync completed');
    }

    // Function to add a new product field
    function addProductField() {
        productCount++;
        
        // Create a new row with similar structure to the original
        const newRow = document.createElement('div');
        newRow.className = 'product-row';
        newRow.id = `product-row-${productCount}`;
        
        newRow.innerHTML = `
            <div class="form-group">
                <label class="form-label">Produk Master <span class="required">*</span></label>
                <select name="product_id[]" id="product_id_${productCount}" class="form-select product-select" required>
                    <option value="">-- Pilih Produk Master --</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah <span class="required">*</span></label>
                <input type="number" name="quantity[]" step="0.01" min="0.01" 
                       class="form-control" placeholder="1" required value="1">
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn-remove-product" onclick="removeProductField(${productCount})" title="Hapus produk">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        // Add the new row to the products container
        document.getElementById('products-container').appendChild(newRow);
        
        // Initialize TomSelect for the new product select
        setTimeout(() => {
            initializeProductSelect(`product_id_${productCount}`);
        }, 100);
        
        // Add animation
        newRow.style.opacity = '0';
        newRow.style.transform = 'translateY(20px)';
        setTimeout(() => {
            newRow.style.transition = 'all 0.3s ease';
            newRow.style.opacity = '1';
            newRow.style.transform = 'translateY(0)';
        }, 10);
    }
    
    // Function to remove a product field
    function removeProductField(id) {
        const row = document.getElementById(`product-row-${id}`);
        if (row) {
            // Destroy TomSelect instance if it exists
            const selectId = `product_id_${id}`;
            if (productSelects[selectId]) {
                productSelects[selectId].destroy();
                delete productSelects[selectId];
            }
            
            // Add animation before removing
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                row.remove();
            }, 300);
        }
    }

    // Initialize form validation
    document.addEventListener('DOMContentLoaded', function() {
        // Validate form before submit to prevent duplicate products
        document.getElementById('mappingForm').addEventListener('submit', function(e) {
            // Sync TomSelect values with original select elements before validation
            syncTomSelectValues();
            
            // Debug log to check values
            console.log('Form submission - synced values:', {
                platform_id: document.getElementById('platform-select')?.value,
                platform_product_name: document.querySelector('input[name="platform_product_name"]')?.value,
                variant: document.querySelector('input[name="variant"]')?.value,
                product_ids: Array.from(document.querySelectorAll('select[name="product_id[]"]')).map(s => s.value),
                quantities: Array.from(document.querySelectorAll('input[name="quantity[]"]')).map(i => i.value)
            });
            
            // Validate required fields
            const platformId = document.getElementById('platform-select')?.value;
            const platformProductName = document.querySelector('input[name="platform_product_name"]')?.value;
            
            if (!platformId) {
                e.preventDefault();
                alert('Mohon pilih platform terlebih dahulu.');
                return;
            }
            
            if (!platformProductName) {
                e.preventDefault();
                alert('Mohon isi nama produk platform terlebih dahulu.');
                return;
            }
            
            const productSelects = document.querySelectorAll('select[name="product_id[]"]');
            const selectedProducts = new Set();
            let hasDuplicate = false;
            let emptyFields = false;
            
            productSelects.forEach(select => {
                if (!select.value) {
                    emptyFields = true;
                    console.log('Empty product select found:', select);
                } else if (selectedProducts.has(select.value)) {
                    hasDuplicate = true;
                } else {
                    selectedProducts.add(select.value);
                }
            });
            
            if (emptyFields) {
                e.preventDefault();
                alert('Mohon lengkapi semua field produk master yang wajib diisi.');
                return;
            }
            
            if (hasDuplicate) {
                e.preventDefault();
                alert('Terdapat produk master yang duplikat. Pastikan setiap produk master hanya dipilih sekali.');
                return;
            }
            
            console.log('Form validation passed, submitting...');
            
            // Final check before submission
            const formData = new FormData(this);
            console.log('Form data being submitted:');
            for (let [key, value] of formData.entries()) {
                console.log(key, ':', value);
            }
            
            // Special validation for auto-filled forms
            const isAutoFilled = {{ isset($fromAutoCreate) ? 'true' : 'false' }};
            console.log('isAutoFilled:', isAutoFilled);
            
            if (isAutoFilled) {
                console.log('AUTO-FILLED FORM VALIDATION');
                const platformValue = document.getElementById('platform-select')?.value;
                const productValue = document.getElementById('product_id_1')?.value;
                
                console.log('Auto-filled validation - Platform:', platformValue, 'Product:', productValue);
                
                if (!platformValue) {
                    console.log('Platform validation failed');
                    e.preventDefault();
                    alert('Platform tidak terpilih. Silakan refresh halaman dan coba lagi.');
                    return;
                }
                
                if (!productValue) {
                    console.log('Product validation failed');
                    e.preventDefault();
                    alert('Produk master tidak terpilih. Silakan pilih produk master terlebih dahulu.');
                    return;
                }
                
                console.log('Auto-filled validation passed');
            }
            
            console.log('All validations passed, proceeding with form submission...');
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Menyimpan...';
            submitBtn.disabled = true;
            
            // Re-enable button after 5 seconds as fallback
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
            
            console.log('Form submission initiated...');
        });
        
        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });
</script>
@endpush