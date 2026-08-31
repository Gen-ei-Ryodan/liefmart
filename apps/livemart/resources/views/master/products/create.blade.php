@extends('layouts.app')

@section('title', 'Tambah Produk')

@section('content')
<style>
    .form-control-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
    }

    .input-group .btn {
        border-radius: 0 0.375rem 0.375rem 0;
    }

    .form-text {
        font-size: 0.875rem;
        color: #6c757d;
        font-style: italic;
    }

    .invalid-feedback {
        font-size: 0.875rem;
        font-weight: 500;
    }

    .container-fluid.py-4:not(.navbar) {
        min-height: calc(100vh - 60px);
    }

    @media (max-width: 768px) {
        .container-fluid.py-4:not(.navbar) {
            min-height: auto;
        }
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .navbar {
        height: auto !important;
        min-height: auto !important;
    }

    .navbar-brand {
        height: auto !important;
        line-height: normal !important;
    }

    .navbar-nav .nav-link {
        padding: 0.5rem 1rem !important;
        height: auto !important;
    }
</style>
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Produk Baru</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none">Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
                <div class="card-body p-4">
                    <form action="{{ route('products.store') }}" method="POST" id="productForm">
                        @csrf
                        <input type="hidden" name="main_category_id" id="main_category_id" value="{{ $mainCategoryId }}">
                        
                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif
                        
                        <!-- Informasi Dasar Produk -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Informasi Dasar Produk
                                </h6>
                            </div>
                            <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-control-label">Nama Produk <span class="text-danger">*</span></label>
                                    <input class="form-control @error('name') is-invalid @enderror" type="text" 
                                        id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sku" class="form-control-label">SKU</label>
                                    <input class="form-control @error('sku') is-invalid @enderror" type="text" 
                                        id="sku" name="sku" value="{{ old('sku') }}" readonly>
                                    <small class="form-text text-muted">SKU akan digenerate otomatis</small>
                                    @error('sku')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="barcode" class="form-control-label">Barcode</label>
                                    <input class="form-control @error('barcode') is-invalid @enderror" type="text" 
                                        id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="Masukkan barcode produk">
                                    <small class="form-text text-muted">Barcode produk (opsional)</small>
                                    @error('barcode')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                            </div>
                        </div>
                        
                            <div class="col-md-6">
                                <div class="form-group">
                                            <label for="description" class="form-control-label">Deskripsi</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" 
                                                name="description" rows="3" placeholder="Masukkan deskripsi produk">{{ old('description') }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            
                        <!-- Kategori dan Klasifikasi Produk -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-tags me-2"></i>Kategori dan Klasifikasi Produk
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="brand_id" class="form-control-label">Brand <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('brand_id') is-invalid @enderror" 
                                        id="brand_id" name="brand_id" required>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                                {{ $brand->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBrandModal">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('brand_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                            </div>
                        </div>
                        
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sub_brand_id" class="form-control-label">Sub Brand <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('sub_brand_id') is-invalid @enderror" 
                                        id="sub_brand_id" name="sub_brand_id" required>
                                        @foreach($subBrands as $subBrand)
                                            <option value="{{ $subBrand->id }}" 
                                                data-brand="{{ $subBrand->brand_id }}" 
                                                {{ old('sub_brand_id') == $subBrand->id ? 'selected' : '' }}>
                                                {{ $subBrand->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSubBrandModal" title="Pilih Brand terlebih dahulu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('sub_brand_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                        </div>
                                </div>
                            </div>
                            
                                <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_category_id" class="form-control-label">Kategori Produk <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('product_category_id') is-invalid @enderror" 
                                        id="product_category_id" name="product_category_id" required>
                                        @foreach($productCategories as $category)
                                            <option value="{{ $category->id }}" 
                                                data-subbrand="{{ $category->sub_brand_id }}" 
                                                {{ old('product_category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal" title="Pilih Sub Brand terlebih dahulu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('product_category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                            </div>
                        </div>
                        
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_type_id" class="form-control-label">Tipe Produk <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('product_type_id') is-invalid @enderror" 
                                        id="product_type_id" name="product_type_id" required>
                                        @foreach($productTypes as $type)
                                            <option value="{{ $type->id }}" 
                                                data-category="{{ $type->product_category_id }}" 
                                                {{ old('product_type_id') == $type->id ? 'selected' : '' }}>
                                                {{ $type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTypeModal" title="Pilih Kategori Produk terlebih dahulu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('product_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                        </div>
                                </div>
                            </div>
                            
                                <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_size_id" class="form-control-label">Ukuran Produk <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('product_size_id') is-invalid @enderror" 
                                        id="product_size_id" name="product_size_id" required disabled>
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSizeModal" title="Pilih Tipe Produk terlebih dahulu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('product_size_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                            </div>
                        </div>
                        
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="product_variant_id" class="form-control-label">Varian Produk</label>
                                            <div class="input-group">
                                    <select class="form-select tom-select @error('product_variant_id') is-invalid @enderror" 
                                        id="product_variant_id" name="product_variant_id" disabled>
                                    </select>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createVariantModal" title="Pilih Ukuran Produk terlebih dahulu">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                    @error('product_variant_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                            </div>
                        </div>
                        
                        <!-- Harga dan Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-dollar-sign me-2"></i>Harga dan Status
                                </h6>
                            </div>
                            <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="initial_price" class="form-control-label">Harga Awal</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input class="form-control @error('initial_price') is-invalid @enderror" 
                                            type="number" id="initial_price" name="initial_price" 
                                            value="{{ old('initial_price') }}" 
                                            min="0" step="0.01" placeholder="0">
                                    </div>
                                    @error('initial_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="discount_percentage" class="form-control-label">Diskon (%)</label>
                                    <div class="input-group">
                                        <input class="form-control @error('discount_percentage') is-invalid @enderror" 
                                            type="number" id="discount_percentage" name="discount_percentage" 
                                            value="{{ old('discount_percentage') }}" 
                                            min="0" max="100" step="0.01" placeholder="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('discount_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                                <div class="row">
                                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-control-label">Harga Akhir</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" id="final_price" readonly value="0">
                            </div>
                            <small class="form-text text-muted">Harga akhir akan dihitung otomatis berdasarkan harga awal dan diskon</small>
                                        </div>
                        </div>
                        
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Status Produk</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Aktif</label>
                            <!-- Hidden input untuk memastikan is_active selalu terkirim -->
                            <input type="hidden" name="is_active_hidden" value="0">
                                            </div>
                                            <small class="form-text text-muted">Produk aktif akan tampil di sistem</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('products.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-times me-2"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-save me-2"></i> Simpan Produk
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk membuat Brand baru -->
<div class="modal fade" id="createBrandModal" tabindex="-1" aria-labelledby="createBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBrandModalLabel">Tambah Brand Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createBrandForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_brand_name" class="form-control-label">Nama Brand <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_brand_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_brand_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_brand_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_brand_main_category_id" name="main_category_id" value="{{ $mainCategoryId }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<!-- Modal untuk membuat Sub Brand baru -->
<div class="modal fade" id="createSubBrandModal" tabindex="-1" aria-labelledby="createSubBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSubBrandModalLabel">Tambah Sub Brand Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createSubBrandForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_sub_brand_name" class="form-control-label">Nama Sub Brand <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_sub_brand_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_sub_brand_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_sub_brand_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_sub_brand_brand_id" name="brand_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal untuk membuat Kategori Produk baru -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCategoryModalLabel">Tambah Kategori Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCategoryForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_category_name" class="form-control-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_category_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_category_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_category_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_category_sub_brand_id" name="sub_brand_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal untuk membuat Tipe Produk baru -->
<div class="modal fade" id="createTypeModal" tabindex="-1" aria-labelledby="createTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createTypeModalLabel">Tambah Tipe Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createTypeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_type_name" class="form-control-label">Nama Tipe <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_type_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_type_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_type_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_type_category_id" name="product_category_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal untuk membuat Ukuran Produk baru -->
<div class="modal fade" id="createSizeModal" tabindex="-1" aria-labelledby="createSizeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSizeModalLabel">Tambah Ukuran Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createSizeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_size_name" class="form-control-label">Nama Ukuran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_size_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_size_code" class="form-control-label">Kode Ukuran</label>
                        <input type="text" class="form-control" id="new_size_code" name="code">
                    </div>
                    <div class="form-group">
                        <label for="new_size_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_size_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_size_type_id" name="product_type_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal untuk membuat Varian Produk baru -->
<div class="modal fade" id="createVariantModal" tabindex="-1" aria-labelledby="createVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createVariantModalLabel">Tambah Varian Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createVariantForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_variant_name" class="form-control-label">Nama Varian <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new_variant_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_variant_description" class="form-control-label">Deskripsi</label>
                        <textarea class="form-control" id="new_variant_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" id="new_variant_size_id" name="product_size_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup is_active switch
        const isActiveCheckbox = document.getElementById('is_active');
        isActiveCheckbox.addEventListener('change', function() {
            // Handle is_active checkbox change
        });
        
        // Inisialisasi Tom Select untuk seluruh dropdown
        let tomSelectInstances = {};
        
        // Konfigurasi Tom Select
        const tomSelectConfig = {
            plugins: ['clear_button'],
            allowEmptyOption: true,
            create: false,
            sortField: {
                field: 'text',
                direction: 'asc'
            },
            dropdownParent: 'body', // Ensure dropdown renders in body
            onInitialize: function(){
                // Pastikan class select asli tidak terlihat
                this.input.style.display = 'block';
                this.input.style.opacity = 0;
                this.input.style.position = 'absolute';
                this.input.style.left = '0px';
                this.input.style.height = '1px';
                this.input.style.width = '100%';
            },
            onDropdownOpen: function() {
                // Ensure dropdown is visible above all elements
                const dropdown = this.dropdown;
                if (dropdown) {
                    dropdown.style.zIndex = '9999';
                    dropdown.style.position = 'absolute';
                }
            }
        };
        
        // Main category tidak perlu diinisialisasi karena sudah hidden
        
        // Inisialisasi Tom Select untuk Brand dengan custom render
        tomSelectInstances.brand = new TomSelect('#brand_id', {
            ...tomSelectConfig,
            onChange: function(value) {
                if (value) {
                    updateSubBrands(value);
                }
            }
        });
        
        // Inisialisasi Tom Select untuk Sub Brand
        tomSelectInstances.subBrand = new TomSelect('#sub_brand_id', {
            ...tomSelectConfig,
            disabled: true, // Mulai dengan disabled
            onChange: function(value) {
                if (value) {
                    updateProductCategories(value);
                }
            }
        });
        
        // Inisialisasi Tom Select untuk Product Category
        tomSelectInstances.productCategory = new TomSelect('#product_category_id', {
            ...tomSelectConfig,
            disabled: true, // Mulai dengan disabled
            onChange: function(value) {
                if (value) {
                    updateProductTypes(value);
                }
            }
        });
        
        // Inisialisasi Tom Select untuk Product Type
        tomSelectInstances.productType = new TomSelect('#product_type_id', {
            ...tomSelectConfig,
            disabled: true, // Mulai dengan disabled
            onChange: function(value) {
                if (value) {
                    updateProductSizes(value);
                } else {
                    // Reset Product Size dan Product Variant jika Product Type di-clear
                    resetProductSizes();
                    resetProductVariants();
                }
            }
        });
        
        // Inisialisasi Tom Select untuk Product Size
        tomSelectInstances.productSize = new TomSelect('#product_size_id', {
            ...tomSelectConfig,
            disabled: true, // Mulai dengan disabled
            onChange: function(value) {
                if (value) {
                    updateProductVariants(value);
                } else {
                    // Reset Product Variant jika Product Size di-clear
                    resetProductVariants();
                }
            }
        });
        
        // Inisialisasi Tom Select untuk Product Variant
        tomSelectInstances.productVariant = new TomSelect('#product_variant_id', {
            ...tomSelectConfig,
            disabled: true, // Mulai dengan disabled
            onChange: function(value) {
                // Product Variant adalah optional, tidak perlu action khusus
            }
        });

        // Fungsi untuk memperbarui dropdown Sub Brand berdasarkan Brand yang dipilih
        function updateSubBrands(brandId) {
            // Enable Tom Select instance
            tomSelectInstances.subBrand.enable();
            
            // Enable tombol hanya jika brand dipilih
            const subBrandButton = document.querySelector('#sub_brand_id').nextElementSibling;
            if (brandId && brandId !== '') {
                subBrandButton.disabled = false;
                subBrandButton.style.opacity = '1';
                subBrandButton.style.cursor = 'pointer';
                subBrandButton.title = 'Tambah Sub Brand baru';
            } else {
                subBrandButton.disabled = true;
                subBrandButton.style.opacity = '0.5';
                subBrandButton.style.cursor = 'not-allowed';
                subBrandButton.title = 'Pilih Brand terlebih dahulu';
            }
            
            // Clear dan update dropdown
            tomSelectInstances.subBrand.clear();
            tomSelectInstances.subBrand.clearOptions();
            
            // Tambahkan opsi default
            tomSelectInstances.subBrand.addOption({
                value: '',
                text: ''
            });
            
            // Filter sub brands berdasarkan brand_id
            @foreach($subBrands as $subBrand)
            if ("{{ $subBrand->brand_id }}" === brandId) {
                tomSelectInstances.subBrand.addOption({
                    value: "{{ $subBrand->id }}",
                    text: "{{ $subBrand->name }}",
                    data: {
                        brand: "{{ $subBrand->brand_id }}"
                    }
                });
            }
            @endforeach
            
            tomSelectInstances.subBrand.refreshOptions(false);
            
            // Reset Product Category, Product Type, Product Size dan Product Variant
            resetProductCategories();
            resetProductTypes();
            resetProductSizes();
            resetProductVariants();
            
            // Validasi modal buttons
            validateModalButtons();
        }
        
        // Fungsi untuk memperbarui dropdown Product Category berdasarkan Sub Brand yang dipilih
        function updateProductCategories(subBrandId) {
            // Enable Tom Select instance
            tomSelectInstances.productCategory.enable();
            
            // Enable tombol hanya jika sub brand dipilih
            const categoryButton = document.querySelector('#product_category_id').nextElementSibling;
            if (subBrandId && subBrandId !== '') {
                categoryButton.disabled = false;
                categoryButton.style.opacity = '1';
                categoryButton.style.cursor = 'pointer';
                categoryButton.title = 'Tambah Kategori Produk baru';
            } else {
                categoryButton.disabled = true;
                categoryButton.style.opacity = '0.5';
                categoryButton.style.cursor = 'not-allowed';
                categoryButton.title = 'Pilih Sub Brand terlebih dahulu';
            }
            
            // Clear dan update dropdown
            tomSelectInstances.productCategory.clear();
            tomSelectInstances.productCategory.clearOptions();
            
            // Tambahkan opsi default
            tomSelectInstances.productCategory.addOption({
                value: '',
                text: ''
            });
            
            // Filter product categories berdasarkan sub_brand_id
            @foreach($productCategories as $category)
            if ("{{ $category->sub_brand_id }}" === subBrandId) {
                tomSelectInstances.productCategory.addOption({
                    value: "{{ $category->id }}",
                    text: "{{ $category->name }}",
                    data: {
                        subbrand: "{{ $category->sub_brand_id }}"
                    }
                });
            }
            @endforeach
            
            tomSelectInstances.productCategory.refreshOptions(false);
            
            // Reset Product Type, Product Size dan Product Variant
            resetProductTypes();
            resetProductSizes();
            resetProductVariants();
            
            // Validasi modal buttons
            validateModalButtons();
        }
        
        // Fungsi untuk memperbarui dropdown Product Type berdasarkan Product Category yang dipilih
        function updateProductTypes(categoryId) {
            // Enable Tom Select instance
            tomSelectInstances.productType.enable();
            
            // Enable tombol hanya jika category dipilih
            const typeButton = document.querySelector('#product_type_id').nextElementSibling;
            if (categoryId && categoryId !== '') {
                typeButton.disabled = false;
                typeButton.style.opacity = '1';
                typeButton.style.cursor = 'pointer';
                typeButton.title = 'Tambah Tipe Produk baru';
            } else {
                typeButton.disabled = true;
                typeButton.style.opacity = '0.5';
                typeButton.style.cursor = 'not-allowed';
                typeButton.title = 'Pilih Kategori Produk terlebih dahulu';
            }
            
            // Clear dan update dropdown
            tomSelectInstances.productType.clear();
            tomSelectInstances.productType.clearOptions();
            
            // Tambahkan opsi default
            tomSelectInstances.productType.addOption({
                value: '',
                text: ''
            });
            
            // Filter product types berdasarkan product_category_id
            @foreach($productTypes as $type)
            if ("{{ $type->product_category_id }}" === categoryId) {
                tomSelectInstances.productType.addOption({
                    value: "{{ $type->id }}",
                    text: "{{ $type->name }}",
                    data: {
                        category: "{{ $type->product_category_id }}"
                    }
                });
            }
            @endforeach
            
            tomSelectInstances.productType.refreshOptions(false);
            
            // Validasi modal buttons
            validateModalButtons();
        }
        
        // Fungsi untuk memperbarui dropdown Product Size berdasarkan Product Type yang dipilih
        function updateProductSizes(typeId) {
            
            // Enable Tom Select instance
            tomSelectInstances.productSize.enable();
            
            // Enable tombol hanya jika type dipilih
            const sizeButton = document.querySelector('#product_size_id').nextElementSibling;
            if (typeId && typeId !== '') {
                sizeButton.disabled = false;
                sizeButton.style.opacity = '1';
                sizeButton.style.cursor = 'pointer';
                sizeButton.title = 'Tambah Ukuran Produk baru';
            } else {
                sizeButton.disabled = true;
                sizeButton.style.opacity = '0.5';
                sizeButton.style.cursor = 'not-allowed';
                sizeButton.title = 'Pilih Tipe Produk terlebih dahulu';
            }
            
            // Clear dan update dropdown
            tomSelectInstances.productSize.clear();
            tomSelectInstances.productSize.clearOptions();
            
            // Tambahkan opsi default
            tomSelectInstances.productSize.addOption({
                value: '',
                text: ''
            });
            
            // Filter product sizes berdasarkan product_type_id
            @foreach($productSizes as $size)
            if ("{{ $size->product_type_id }}" === typeId) {
                tomSelectInstances.productSize.addOption({
                    value: "{{ $size->id }}",
                    text: "{{ $size->name }} @if($size->code)({{ $size->code }})@endif",
                    data: {
                        type: "{{ $size->product_type_id }}"
                    }
                });
            }
            @endforeach
            
            tomSelectInstances.productSize.refreshOptions(false);
            
            // Reset Product Variant
            resetProductVariants();
            
            // Validasi modal buttons
            validateModalButtons();
        }
        
        // Fungsi untuk memperbarui dropdown Product Variant berdasarkan Product Size yang dipilih
        function updateProductVariants(sizeId) {
            // Enable Tom Select instance
            tomSelectInstances.productVariant.enable();
            
            // Enable tombol hanya jika size dipilih
            const variantButton = document.querySelector('#product_variant_id').nextElementSibling;
            if (sizeId && sizeId !== '') {
                variantButton.disabled = false;
                variantButton.style.opacity = '1';
                variantButton.style.cursor = 'pointer';
                variantButton.title = 'Tambah Varian Produk baru';
            } else {
                variantButton.disabled = true;
                variantButton.style.opacity = '0.5';
                variantButton.style.cursor = 'not-allowed';
                variantButton.title = 'Pilih Ukuran Produk terlebih dahulu';
            }
            
            // Clear dan update dropdown
            tomSelectInstances.productVariant.clear();
            tomSelectInstances.productVariant.clearOptions();
            
            // Tambahkan opsi default
            tomSelectInstances.productVariant.addOption({
                value: '',
                text: ''
            });
            
            // Filter product variants berdasarkan product_size_id
            @foreach($productVariants as $variant)
            if ("{{ $variant->product_size_id }}" === sizeId) {
                tomSelectInstances.productVariant.addOption({
                    value: "{{ $variant->id }}",
                    text: "{{ $variant->name ?? 'Tanpa Nama' }}",
                    data: {
                        size: "{{ $variant->product_size_id }}"
                    }
                });
            }
            @endforeach
            
            tomSelectInstances.productVariant.refreshOptions(false);
            
            // Validasi modal buttons
            validateModalButtons();
        }
        
        // Fungsi untuk reset Product Size
        function resetProductSizes() {
            // Disable Tom Select instance
            tomSelectInstances.productSize.disable();
            
            // Disable tombol
            const sizeButton = document.querySelector('#product_size_id').nextElementSibling;
            sizeButton.disabled = true;
            sizeButton.style.opacity = '0.5';
            sizeButton.style.cursor = 'not-allowed';
            
            // Clear dropdown
            tomSelectInstances.productSize.clear();
            tomSelectInstances.productSize.clearOptions();
            tomSelectInstances.productSize.refreshOptions(false);
        }
        
        // Fungsi untuk reset Product Variant
        function resetProductVariants() {
            // Disable Tom Select instance
            tomSelectInstances.productVariant.disable();
            
            // Disable tombol
            const variantButton = document.querySelector('#product_variant_id').nextElementSibling;
            variantButton.disabled = true;
            variantButton.style.opacity = '0.5';
            variantButton.style.cursor = 'not-allowed';
            
            // Clear dropdown
            tomSelectInstances.productVariant.clear();
            tomSelectInstances.productVariant.clearOptions();
            tomSelectInstances.productVariant.refreshOptions(false);
        }
        
        // Fungsi untuk reset Product Type
        function resetProductTypes() {
            // Disable Tom Select instance
            tomSelectInstances.productType.disable();
            
            // Disable tombol
            const typeButton = document.querySelector('#product_type_id').nextElementSibling;
            typeButton.disabled = true;
            typeButton.style.opacity = '0.5';
            typeButton.style.cursor = 'not-allowed';
            
            // Clear dropdown
            tomSelectInstances.productType.clear();
            tomSelectInstances.productType.clearOptions();
            tomSelectInstances.productType.refreshOptions(false);
        }
        
        // Fungsi untuk reset Sub Brand
        function resetSubBrands() {
            // Disable Tom Select instance
            tomSelectInstances.subBrand.disable();
            
            // Disable tombol
            const subBrandButton = document.querySelector('#sub_brand_id').nextElementSibling;
            subBrandButton.disabled = true;
            subBrandButton.style.opacity = '0.5';
            subBrandButton.style.cursor = 'not-allowed';
            
            // Clear dropdown
            tomSelectInstances.subBrand.clear();
            tomSelectInstances.subBrand.clearOptions();
            tomSelectInstances.subBrand.refreshOptions(false);
        }
        
        // Fungsi untuk reset Product Category
        function resetProductCategories() {
            // Disable Tom Select instance
            tomSelectInstances.productCategory.disable();
            
            // Disable tombol
            const categoryButton = document.querySelector('#product_category_id').nextElementSibling;
            categoryButton.disabled = true;
            categoryButton.style.opacity = '0.5';
            categoryButton.style.cursor = 'not-allowed';
            
            // Clear dropdown
            tomSelectInstances.productCategory.clear();
            tomSelectInstances.productCategory.clearOptions();
            tomSelectInstances.productCategory.refreshOptions(false);
        }
        
        // Inisialisasi awal - disable semua dropdown kecuali Brand
        resetSubBrands();
        resetProductCategories();
        resetProductTypes();
        resetProductSizes();
        resetProductVariants();
        
        // Validasi modal buttons saat inisialisasi
        validateModalButtons();
        
        // Tambahkan event listener untuk mencegah klik pada tombol disabled
        document.querySelectorAll('.input-group .btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
        
        // Tambahkan event listener untuk mencegah modal dibuka jika tombol disabled
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
        
        // Validasi hierarki untuk modal buttons
        function validateModalButtons() {
            // Sub Brand Modal - hanya bisa dibuka jika Brand dipilih
            const subBrandModal = document.querySelector('[data-bs-target="#createSubBrandModal"]');
            const brandValue = tomSelectInstances.brand.getValue();
            if (brandValue && brandValue !== '') {
                subBrandModal.disabled = false;
                subBrandModal.style.opacity = '1';
                subBrandModal.style.cursor = 'pointer';
                subBrandModal.title = 'Tambah Sub Brand baru';
            } else {
                subBrandModal.disabled = true;
                subBrandModal.style.opacity = '0.5';
                subBrandModal.style.cursor = 'not-allowed';
                subBrandModal.title = 'Pilih Brand terlebih dahulu';
            }
            
            // Category Modal - hanya bisa dibuka jika Sub Brand dipilih
            const categoryModal = document.querySelector('[data-bs-target="#createCategoryModal"]');
            const subBrandValue = tomSelectInstances.subBrand.getValue();
            if (subBrandValue && subBrandValue !== '') {
                categoryModal.disabled = false;
                categoryModal.style.opacity = '1';
                categoryModal.style.cursor = 'pointer';
                categoryModal.title = 'Tambah Kategori Produk baru';
            } else {
                categoryModal.disabled = true;
                categoryModal.style.opacity = '0.5';
                categoryModal.style.cursor = 'not-allowed';
                categoryModal.title = 'Pilih Sub Brand terlebih dahulu';
            }
            
            // Type Modal - hanya bisa dibuka jika Category dipilih
            const typeModal = document.querySelector('[data-bs-target="#createTypeModal"]');
            const categoryValue = tomSelectInstances.productCategory.getValue();
            if (categoryValue && categoryValue !== '') {
                typeModal.disabled = false;
                typeModal.style.opacity = '1';
                typeModal.style.cursor = 'pointer';
                typeModal.title = 'Tambah Tipe Produk baru';
            } else {
                typeModal.disabled = true;
                typeModal.style.opacity = '0.5';
                typeModal.style.cursor = 'not-allowed';
                typeModal.title = 'Pilih Kategori Produk terlebih dahulu';
            }
            
            // Size Modal - hanya bisa dibuka jika Type dipilih
            const sizeModal = document.querySelector('[data-bs-target="#createSizeModal"]');
            const typeValue = tomSelectInstances.productType.getValue();
            if (typeValue && typeValue !== '') {
                sizeModal.disabled = false;
                sizeModal.style.opacity = '1';
                sizeModal.style.cursor = 'pointer';
                sizeModal.title = 'Tambah Ukuran Produk baru';
            } else {
                sizeModal.disabled = true;
                sizeModal.style.opacity = '0.5';
                sizeModal.style.cursor = 'not-allowed';
                sizeModal.title = 'Pilih Tipe Produk terlebih dahulu';
            }
            
            // Variant Modal - hanya bisa dibuka jika Size dipilih
            const variantModal = document.querySelector('[data-bs-target="#createVariantModal"]');
            const sizeValue = tomSelectInstances.productSize.getValue();
            if (sizeValue && sizeValue !== '') {
                variantModal.disabled = false;
                variantModal.style.opacity = '1';
                variantModal.style.cursor = 'pointer';
                variantModal.title = 'Tambah Varian Produk baru';
            } else {
                variantModal.disabled = true;
                variantModal.style.opacity = '0.5';
                variantModal.style.cursor = 'not-allowed';
                variantModal.title = 'Pilih Ukuran Produk terlebih dahulu';
            }
        }
        
        // Inisialisasi data awal jika ada data yang sudah tersimpan (setelah validasi gagal)
        const oldBrandId = "{{ old('brand_id') }}";
        const oldSubBrandId = "{{ old('sub_brand_id') }}";
        const oldCategoryId = "{{ old('product_category_id') }}";
        
        // Jika ada data brand yang tersimpan, trigger perubahan
        if (oldBrandId) {
            // Set nilai
            tomSelectInstances.brand.setValue(oldBrandId);
            
            // Update sub brands
            setTimeout(() => {
                updateSubBrands(oldBrandId);
                
                // Jika ada data sub brand yang tersimpan, trigger perubahan
                if (oldSubBrandId) {
                    setTimeout(() => {
                        tomSelectInstances.subBrand.setValue(oldSubBrandId);
                        updateProductCategories(oldSubBrandId);
                        
                        // Jika ada data kategori yang tersimpan, trigger perubahan
                        if (oldCategoryId) {
                            setTimeout(() => {
                                tomSelectInstances.productCategory.setValue(oldCategoryId);
                                updateProductTypes(oldCategoryId);
                                
                                // Set product type jika ada
                                const oldTypeId = "{{ old('product_type_id') }}";
                                if (oldTypeId) {
                                    setTimeout(() => {
                                        tomSelectInstances.productType.setValue(oldTypeId);
                                    }, 100);
                                }
                            }, 100);
                        }
                    }, 100);
                }
            }, 100);
        }
        
        // Calculate final price when initial price or discount changes
        const initialPriceInput = document.getElementById('initial_price');
        const discountInput = document.getElementById('discount_percentage');
        const finalPriceInput = document.getElementById('final_price');
        
        function calculateFinalPrice() {
            const initialPrice = parseFloat(initialPriceInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            
            const finalPrice = initialPrice * (1 - discount / 100);
            finalPriceInput.value = new Intl.NumberFormat('id-ID').format(Math.round(finalPrice));
        }
        
        initialPriceInput.addEventListener('input', calculateFinalPrice);
        discountInput.addEventListener('input', calculateFinalPrice);
        
        // Form validation
        const productForm = document.getElementById('productForm');
        if (productForm) {
            productForm.addEventListener('submit', function(e) {
                // Enable semua Tom Select instance sebelum sync
                if (tomSelectInstances.brand && tomSelectInstances.brand.isDisabled) {
                    tomSelectInstances.brand.enable();
                }
                if (tomSelectInstances.subBrand && tomSelectInstances.subBrand.isDisabled) {
                    tomSelectInstances.subBrand.enable();
                }
                if (tomSelectInstances.productCategory && tomSelectInstances.productCategory.isDisabled) {
                    tomSelectInstances.productCategory.enable();
                }
                if (tomSelectInstances.productType && tomSelectInstances.productType.isDisabled) {
                    tomSelectInstances.productType.enable();
                }
                if (tomSelectInstances.productSize && tomSelectInstances.productSize.isDisabled) {
                    tomSelectInstances.productSize.enable();
                }
                if (tomSelectInstances.productVariant && tomSelectInstances.productVariant.isDisabled) {
                    tomSelectInstances.productVariant.enable();
                }
                
                // Sync semua nilai Tom Select ke form input sebelum validasi
                if (tomSelectInstances.brand) {
                    document.getElementById('brand_id').value = tomSelectInstances.brand.getValue() || '';
                }
                if (tomSelectInstances.subBrand) {
                    document.getElementById('sub_brand_id').value = tomSelectInstances.subBrand.getValue() || '';
                }
                if (tomSelectInstances.productCategory) {
                    document.getElementById('product_category_id').value = tomSelectInstances.productCategory.getValue() || '';
                }
                if (tomSelectInstances.productType) {
                    document.getElementById('product_type_id').value = tomSelectInstances.productType.getValue() || '';
                }
                if (tomSelectInstances.productSize) {
                    document.getElementById('product_size_id').value = tomSelectInstances.productSize.getValue() || '';
                }
                if (tomSelectInstances.productVariant) {
                    document.getElementById('product_variant_id').value = tomSelectInstances.productVariant.getValue() || '';
                }
                // Periksa apakah semua dropdown required sudah terisi
                const requiredSelects = [
                    { id: 'brand_id', name: 'Brand' },
                    { id: 'sub_brand_id', name: 'Sub Brand' },
                    { id: 'product_category_id', name: 'Kategori Produk' },
                    { id: 'product_type_id', name: 'Tipe Produk' },
                    { id: 'product_size_id', name: 'Ukuran Produk' }
                ];
                
                let hasErrors = false;
                const errorMessages = [];
                
                // Handle is_active value
                
                requiredSelects.forEach(field => {
                // Ambil nilai dari form input (sudah di-sync sebelumnya)
                const formInput = document.getElementById(field.id);
                const value = formInput ? formInput.value : null;
                
                if (!value || value === '') {
                    e.preventDefault();
                    hasErrors = true;
                    errorMessages.push(`${field.name} harus diisi`);
                    
                    // Tampilkan pesan error
                    const fieldElement = document.getElementById(field.id);
                    const parentElement = fieldElement.parentNode;
                    
                    // Tambahkan class bootstrap untuk invalid
                    const wrapper = parentElement.querySelector('.ts-wrapper');
                    if (wrapper) {
                        wrapper.classList.add('is-invalid');
                    }
                    
                    // Buat atau tampilkan pesan error jika belum ada
                    let errorElement = parentElement.querySelector('.invalid-feedback');
                    if (!errorElement) {
                        errorElement = document.createElement('div');
                        errorElement.className = 'invalid-feedback';
                        errorElement.textContent = `${field.name} harus diisi.`;
                        parentElement.appendChild(errorElement);
                    } else {
                        errorElement.textContent = `${field.name} harus diisi.`;
                    }
                    
                    errorElement.style.display = 'block';
                }
            });
            
            if (hasErrors) {
                // Buat alert error dengan bootstrap
                const alertElement = document.createElement('div');
                alertElement.className = 'alert alert-danger mt-3';
                alertElement.innerHTML = '<strong>Mohon periksa kembali form Anda:</strong><ul>' + 
                    errorMessages.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
                
                // Hapus alert sebelumnya jika ada
                const oldAlert = document.querySelector('form .alert-danger');
                if (oldAlert) {
                    oldAlert.remove();
                }
                
                // Tambahkan alert di awal form
                const formElement = document.getElementById('productForm');
                formElement.insertBefore(alertElement, formElement.firstChild);
                
                // Scroll ke atas
                window.scrollTo(0, 0);
            }
        });
        }
        
        // Tambahkan event listener untuk menghapus class is-invalid saat nilai berubah
        for (const key in tomSelectInstances) {
            if (tomSelectInstances[key] && typeof tomSelectInstances[key].on === 'function') {
                tomSelectInstances[key].on('change', function() {
                    const fieldId = this.input.id;
                    const parentElement = document.getElementById(fieldId).parentNode;
                    
                    // Hapus class is-invalid
                    const wrapper = parentElement.querySelector('.ts-wrapper');
                    if (wrapper) {
                        wrapper.classList.remove('is-invalid');
                    }
                    
                    // Sembunyikan pesan error
                    const errorElement = parentElement.querySelector('.invalid-feedback');
                    if (errorElement) {
                        errorElement.style.display = 'none';
                    }
                });
            }
        }
        
        // Handle modal events untuk set parent IDs
        document.getElementById('createSubBrandModal').addEventListener('show.bs.modal', function() {
            const brandId = tomSelectInstances.brand.getValue();
            document.getElementById('new_sub_brand_brand_id').value = brandId || '';
        });
        
        document.getElementById('createCategoryModal').addEventListener('show.bs.modal', function() {
            const subBrandId = tomSelectInstances.subBrand.getValue();
            document.getElementById('new_category_sub_brand_id').value = subBrandId || '';
        });
        
        document.getElementById('createTypeModal').addEventListener('show.bs.modal', function() {
            const categoryId = tomSelectInstances.productCategory.getValue();
            document.getElementById('new_type_category_id').value = categoryId || '';
        });
        
        document.getElementById('createSizeModal').addEventListener('show.bs.modal', function() {
            const typeId = tomSelectInstances.productType.getValue();
            document.getElementById('new_size_type_id').value = typeId || '';
        });
        
        document.getElementById('createVariantModal').addEventListener('show.bs.modal', function() {
            const sizeId = tomSelectInstances.productSize.getValue();
            document.getElementById('new_variant_size_id').value = sizeId || '';
        });
        
        // Handle form submissions untuk membuat data baru
        document.getElementById('createBrandForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewBrand();
        });
        
        document.getElementById('createSubBrandForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewSubBrand();
        });
        
        document.getElementById('createCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewCategory();
        });
        
        document.getElementById('createTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewType();
        });
        
        document.getElementById('createSizeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewSize();
        });
        
        document.getElementById('createVariantForm').addEventListener('submit', function(e) {
            e.preventDefault();
            createNewVariant();
        });
        
        // Functions untuk create new data
        function createNewBrand() {
            const formData = new FormData(document.getElementById('createBrandForm'));
            
            fetch('/api/brands', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to brand select
                    tomSelectInstances.brand.addOption({
                        value: data.id,
                        text: data.name
                    });
                    tomSelectInstances.brand.setValue(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createBrandModal')).hide();
                    
                    // Clear form
                    document.getElementById('createBrandForm').reset();
                    
                    alert('Brand berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Brand'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Brand');
            });
        }
        
        function createNewSubBrand() {
            const formData = new FormData(document.getElementById('createSubBrandForm'));
            
            fetch('/api/sub-brands', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to sub brand select
                    tomSelectInstances.subBrand.addOption({
                        value: data.id,
                        text: data.name
                    });
                    tomSelectInstances.subBrand.setValue(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createSubBrandModal')).hide();
                    
                    // Clear form
                    document.getElementById('createSubBrandForm').reset();
                    
                    alert('Sub Brand berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Sub Brand'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Sub Brand');
            });
        }
        
        function createNewCategory() {
            const formData = new FormData(document.getElementById('createCategoryForm'));
            
            fetch('/api/product-categories', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to category select
                    tomSelectInstances.productCategory.addOption({
                        value: data.id,
                        text: data.name
                    });
                    tomSelectInstances.productCategory.setValue(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createCategoryModal')).hide();
                    
                    // Clear form
                    document.getElementById('createCategoryForm').reset();
                    
                    alert('Kategori Produk berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Kategori Produk'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Kategori Produk');
            });
        }
        
        function createNewType() {
            const formData = new FormData(document.getElementById('createTypeForm'));
            
            fetch('/api/product-types', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to type select
                    tomSelectInstances.productType.addOption({
                        value: data.id,
                        text: data.name
                    });
                    tomSelectInstances.productType.setValue(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createTypeModal')).hide();
                    
                    // Clear form
                    document.getElementById('createTypeForm').reset();
                    
                    alert('Tipe Produk berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Tipe Produk'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Tipe Produk');
            });
        }
        
        function createNewSize() {
            const formData = new FormData(document.getElementById('createSizeForm'));
            
            fetch('/api/product-sizes', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to size select
                    tomSelectInstances.productSize.addOption({
                        value: data.id,
                        text: data.name + (data.code ? ' (' + data.code + ')' : '')
                    });
                    tomSelectInstances.productSize.setValue(data.id);
                    
                    // Trigger update untuk Product Variant
                    updateProductVariants(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createSizeModal')).hide();
                    
                    // Clear form
                    document.getElementById('createSizeForm').reset();
                    
                    alert('Ukuran Produk berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Ukuran Produk'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Ukuran Produk');
            });
        }
        
        function createNewVariant() {
            const formData = new FormData(document.getElementById('createVariantForm'));
            
            fetch('/api/product-variants', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new option to variant select
                    tomSelectInstances.productVariant.addOption({
                        value: data.id,
                        text: data.name
                    });
                    tomSelectInstances.productVariant.setValue(data.id);
                    
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('createVariantModal')).hide();
                    
                    // Clear form
                    document.getElementById('createVariantForm').reset();
                    
                    alert('Varian Produk berhasil ditambahkan');
                } else {
                    alert('Error: ' + (data.message || 'Gagal menambahkan Varian Produk'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan Varian Produk');
            });
        }
    });
</script>
@endpush
@endsection
