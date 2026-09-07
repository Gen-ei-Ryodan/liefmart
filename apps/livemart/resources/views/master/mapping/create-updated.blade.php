@extends('layouts.app')

@push('styles')
@include('master.mapping.styles')
@endpush

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="m-0 fw-bold text-primary"><i class="fas fa-link me-2"></i>Tambah Mapping Produk Baru</h5>
                </div>
                <div class="card-body p-4">
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
                                <select name="platform" id="platform" class="form-select" required onchange="loadPlatformProducts()">
                                    <option value="">-- Pilih Platform --</option>
                                    @foreach($platforms as $platform)
                                    <option value="{{ $platform->name }}" {{ old('platform') == $platform->name ? 'selected' : '' }}>
                                        {{ $platform->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Barang Platform <span class="required">*</span></label>
                                <select name="platform_product_id" id="platform_product_id" class="form-select" required>
                                    <option value="">-- Pilih atau Buat Barang Platform --</option>
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Jika barang platform belum ada, pilih "Buat Barang Platform Baru" di bawah
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Input Section (hidden by default) -->
                    <div id="manual-input-section" style="display: none;">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Buat Barang Platform Baru</h6>
                            <p>Isi form di bawah untuk membuat barang platform baru</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Nama Produk Platform <span class="required">*</span></label>
                                    <input type="text" name="new_platform_product_name" id="new_platform_product_name" 
                                           class="form-control" placeholder="Masukkan nama produk di platform">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Variant (Opsional)</label>
                                    <input type="text" name="new_variant" id="new_variant" 
                                           class="form-control" placeholder="Contoh: Warna Merah, Size L">
                                </div>
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
</div>
</div>
@endsection

@push('scripts')
<script>
let productCount = 1;

// Function to load platform products when platform is selected
function loadPlatformProducts() {
    const platformSelect = document.getElementById('platform');
    const platformProductSelect = document.getElementById('platform_product_id');
    const manualInputSection = document.getElementById('manual-input-section');
    
    if (!platformSelect.value) {
        platformProductSelect.innerHTML = '<option value="">-- Pilih Platform Terlebih Dahulu --</option>';
        manualInputSection.style.display = 'none';
        return;
    }
    
    // Show loading
    platformProductSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch platform products
    fetch(`/api/master/barang-platform/by-platform/${platformSelect.value}`)
        .then(response => response.json())
        .then(data => {
            platformProductSelect.innerHTML = '<option value="">-- Pilih Barang Platform --</option>';
            
            // Add existing platform products
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.text;
                option.dataset.name = item.platform_product_name;
                option.dataset.variant = item.variant;
                platformProductSelect.appendChild(option);
            });
            
            // Add option to create new
            const createNewOption = document.createElement('option');
            createNewOption.value = 'create_new';
            createNewOption.textContent = '➕ Buat Barang Platform Baru';
            createNewOption.style.fontWeight = 'bold';
            createNewOption.style.color = '#007bff';
            platformProductSelect.appendChild(createNewOption);
            
            // Add event listener for create new option
            platformProductSelect.addEventListener('change', function() {
                if (this.value === 'create_new') {
                    manualInputSection.style.display = 'block';
                    // Clear manual input fields
                    document.getElementById('new_platform_product_name').value = '';
                    document.getElementById('new_variant').value = '';
                } else {
                    manualInputSection.style.display = 'none';
                    // Auto-fill manual input fields if existing product selected
                    if (this.value && this.selectedOptions[0].dataset.name) {
                        document.getElementById('new_platform_product_name').value = this.selectedOptions[0].dataset.name;
                        document.getElementById('new_variant').value = this.selectedOptions[0].dataset.variant || '';
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading platform products:', error);
            platformProductSelect.innerHTML = '<option value="">Error loading data</option>';
        });
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
    }, 50);
}

// Function to remove a product field
function removeProductField(rowId) {
    const row = document.getElementById(`product-row-${rowId}`);
    if (row) {
        // Add removal animation
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            row.remove();
        }, 300);
    }
}

// Initialize product selects
function initializeProductSelect(selectId) {
    const select = document.getElementById(selectId);
    if (select && typeof TomSelect !== 'undefined') {
        new TomSelect(select, {
            placeholder: 'Pilih produk master...',
            allowEmptyOption: false,
            create: false,
            sortField: {
                field: 'text',
                direction: 'asc'
            }
        });
    }
}

// Initialize all product selects on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize first product select
    initializeProductSelect('product_id_1');
    
    // Initialize platform product select
    const platformProductSelect = document.getElementById('platform_product_id');
    if (platformProductSelect) {
        new TomSelect(platformProductSelect, {
            placeholder: 'Pilih barang platform...',
            allowEmptyOption: true,
            create: false,
            sortField: {
                field: 'text',
                direction: 'asc'
            }
        });
    }
});

// Form validation
document.getElementById('mappingForm').addEventListener('submit', function(e) {
    const platformProductSelect = document.getElementById('platform_product_id');
    const isCreatingNew = platformProductSelect.value === 'create_new';
    
    if (isCreatingNew) {
        const newName = document.getElementById('new_platform_product_name').value;
        if (!newName.trim()) {
            e.preventDefault();
            alert('Nama produk platform harus diisi jika membuat barang platform baru.');
            return;
        }
    }
    
    // Validate that at least one product is selected
    const productSelects = document.querySelectorAll('select[name="product_id[]"]');
    let hasValidProduct = false;
    
    productSelects.forEach(select => {
        if (select.value) {
            hasValidProduct = true;
        }
    });
    
    if (!hasValidProduct) {
        e.preventDefault();
        alert('Pilih minimal satu produk master.');
        return;
    }
});
</script>
@endpush
