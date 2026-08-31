@extends('layouts.app')

@section('title', 'Edit Produk')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Produk</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none">Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('products.initial-price.show', $product->id) }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-tag me-2"></i> Harga Awal
            </a>
            <a href="{{ route('products.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
                <div class="card-body p-4">
                    <form action="{{ route('products.update', $product->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                    <div class="card-header section-header d-flex justify-content-between align-items-center py-3 px-4">
                                        <h6 class="mb-0 fw-semibold text-primary">
                                            <i class="fas fa-info-circle me-2"></i>Informasi Produk
                                        </h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-7">
                                                <label for="name" class="form-control-label">Nama Produk <span class="text-danger">*</span></label>
                                                <input class="form-control @error('name') is-invalid @enderror" type="text"
                                                    id="name" name="name" value="{{ old('name', $product->name) }}" required>
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-5">
                                                <label for="sku" class="form-control-label">SKU</label>
                                                <input class="form-control @error('sku') is-invalid @enderror" type="text"
                                                    id="sku" name="sku" value="{{ old('sku', $product->sku) }}" readonly>
                                                <small class="form-text text-muted">SKU akan digenerate otomatis</small>
                                                @error('sku')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-7">
                                                <label for="barcode" class="form-control-label">Barcode</label>
                                                <input class="form-control @error('barcode') is-invalid @enderror" type="text"
                                                    id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="Masukkan barcode produk">
                                                <small class="form-text text-muted">Barcode produk (opsional)</small>
                                                @error('barcode')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-5">
                                                <label for="main_category_id" class="form-control-label">Main Category <span class="text-danger">*</span></label>
                                                <select class="form-select @error('main_category_id') is-invalid @enderror"
                                                    id="main_category_id" name="main_category_id" required>
                                                    <option value="">Pilih Main Category</option>
                                                    @foreach($mainCategories as $category)
                                                        <option value="{{ $category->id }}" {{ old('main_category_id', $product->main_category_id) == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('main_category_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-12">
                                                <label for="description" class="form-control-label">Deskripsi</label>
                                                <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                                    name="description" rows="3">{{ old('description', $product->description) }}</textarea>
                                                @error('description')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                                    <div class="card-header section-header d-flex justify-content-between align-items-center py-3 px-4">
                                        <h6 class="mb-0 fw-semibold text-primary">
                                            <i class="fas fa-sitemap me-2"></i>Klasifikasi Produk
                                        </h6>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="brand_id" class="form-control-label">Brand <span class="text-danger">*</span></label>
                                                <select class="form-select @error('brand_id') is-invalid @enderror"
                                                    id="brand_id" name="brand_id" required>
                                                    <option value="">Pilih Brand</option>
                                                    @foreach($brands as $brand)
                                                        <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                                            {{ $brand->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('brand_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="sub_brand_id" class="form-control-label">Sub Brand <span class="text-danger">*</span></label>
                                                <select class="form-select @error('sub_brand_id') is-invalid @enderror"
                                                    id="sub_brand_id" name="sub_brand_id" required>
                                                    <option value="">Pilih Sub Brand</option>
                                                    @foreach($subBrands as $subBrand)
                                                        <option value="{{ $subBrand->id }}" data-brand="{{ $subBrand->brand_id }}"
                                                            {{ old('sub_brand_id', $product->sub_brand_id) == $subBrand->id ? 'selected' : '' }}>
                                                            {{ $subBrand->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('sub_brand_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="product_category_id" class="form-control-label">Kategori Produk <span class="text-danger">*</span></label>
                                                <select class="form-select @error('product_category_id') is-invalid @enderror"
                                                    id="product_category_id" name="product_category_id" required>
                                                    <option value="">Pilih Kategori Produk</option>
                                                    @foreach($productCategories as $category)
                                                        <option value="{{ $category->id }}" data-subbrand="{{ $category->sub_brand_id }}"
                                                            {{ old('product_category_id', $product->product_category_id) == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_category_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="product_type_id" class="form-control-label">Tipe Produk <span class="text-danger">*</span></label>
                                                <select class="form-select @error('product_type_id') is-invalid @enderror"
                                                    id="product_type_id" name="product_type_id" required>
                                                    <option value="">Pilih Tipe Produk</option>
                                                    @foreach($productTypes as $type)
                                                        <option value="{{ $type->id }}" data-category="{{ $type->product_category_id }}"
                                                            {{ old('product_type_id', $product->product_type_id) == $type->id ? 'selected' : '' }}>
                                                            {{ $type->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_type_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="product_size_id" class="form-control-label">Ukuran Produk <span class="text-danger">*</span></label>
                                                <select class="form-select @error('product_size_id') is-invalid @enderror"
                                                    id="product_size_id" name="product_size_id" required>
                                                    <option value="">Pilih Ukuran Produk</option>
                                                    @foreach($productSizes as $size)
                                                        <option value="{{ $size->id }}" {{ old('product_size_id', $product->product_size_id) == $size->id ? 'selected' : '' }}>
                                                            {{ $size->name }} @if($size->code)({{ $size->code }})@endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_size_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-md-6">
                                                <label for="product_variant_id" class="form-control-label">Varian Produk</label>
                                                <select class="form-select @error('product_variant_id') is-invalid @enderror"
                                                    id="product_variant_id" name="product_variant_id">
                                                    <option value="">Pilih Varian Produk (Opsional)</option>
                                                    @foreach($productVariants as $variant)
                                                        <option value="{{ $variant->id }}" {{ old('product_variant_id', $product->product_variant_id) == $variant->id ? 'selected' : '' }}>
                                                            {{ $variant->sku }} - {{ $variant->name ?? 'Tanpa Nama' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('product_variant_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                    <div class="card-header section-header d-flex justify-content-between align-items-center py-3 px-4">
                                        <h6 class="mb-0 fw-semibold text-primary">
                                            <i class="fas fa-tag me-2"></i>Harga & Status
                                        </h6>
                                        <a href="{{ route('products.initial-price.show', $product->id) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            <i class="fas fa-history me-1"></i> Versi
                                        </a>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="mb-3">
                                            <label for="initial_price" class="form-control-label">Harga Awal</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input class="form-control @error('initial_price') is-invalid @enderror"
                                                    type="number" id="initial_price" name="initial_price"
                                                    value="{{ old('initial_price', $product->initial_price ?? 0) }}"
                                                    min="0" step="0.01" placeholder="0">
                                            </div>
                                            @error('initial_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="discount_percentage" class="form-control-label">Diskon (%)</label>
                                            <div class="input-group">
                                                <input class="form-control @error('discount_percentage') is-invalid @enderror"
                                                    type="number" id="discount_percentage" name="discount_percentage"
                                                    value="{{ old('discount_percentage', $product->discount_percentage ?? 0) }}"
                                                    min="0" max="100" step="0.01" placeholder="0">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            @error('discount_percentage')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-control-label">Harga Akhir</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" class="form-control" id="final_price" readonly
                                                    value="{{ number_format(($product->initial_price ?? 0) * (1 - ($product->discount_percentage ?? 0) / 100), 0, ',', '.') }}">
                                            </div>
                                            <small class="form-text text-muted">Dihitung otomatis berdasarkan harga awal dan diskon</small>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                                    {{ old('is_active', $product->is_active) == 1 ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">Aktif</label>
                                                <input type="hidden" name="is_active_hidden" value="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                                        <i class="fas fa-arrow-left me-1"></i> Kembali
                                    </a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                                        <i class="fas fa-save me-1"></i> Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-light {
        background: linear-gradient(to right, #f8f9fa, #fff);
    }

    .section-header {
        background: linear-gradient(to right, #f8f9fa, #fff);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .form-control,
    .form-select {
        border-radius: 0.75rem;
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #5e72e4;
        box-shadow: 0 0 0 0.2rem rgba(94, 114, 228, 0.15);
    }

    .input-group > .input-group-text {
        border-radius: 0.75rem 0 0 0.75rem;
        border: 1px solid #e9ecef;
        background: #fff;
    }

    .input-group > .form-control {
        border-radius: 0 0.75rem 0.75rem 0;
        border: 1px solid #e9ecef;
    }

    .btn.btn-secondary {
        border-radius: 999px;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .btn.btn-primary {
        border-radius: 999px;
        padding-left: 1rem;
        padding-right: 1rem;
    }
</style>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter sub brands based on brand (only for new selections)
        const brandSelect = document.getElementById('brand_id');
        const subBrandSelect = document.getElementById('sub_brand_id');
        const subBrandOptions = Array.from(document.querySelectorAll('#sub_brand_id option'));
        
        brandSelect.addEventListener('change', function() {
            if (this.value === '') {
                subBrandSelect.innerHTML = '<option value="">Pilih Sub Brand</option>';
                return;
            }
            
            // Store current selection
            const currentSubBrandId = subBrandSelect.value;
            
            // Reset sub brand dropdown
            subBrandSelect.innerHTML = '<option value="">Pilih Sub Brand</option>';
            
            // Filter sub brands based on selected brand
            subBrandOptions.forEach(option => {
                if (option.value === '' || option.dataset.brand === this.value) {
                    subBrandSelect.appendChild(option.cloneNode(true));
                }
            });
            
            // Try to restore previous selection if possible
            if (currentSubBrandId) {
                const exists = Array.from(subBrandSelect.options).some(
                    option => option.value === currentSubBrandId
                );
                
                if (exists) {
                    subBrandSelect.value = currentSubBrandId;
                }
            }
        });
        
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
    });
</script>
@endpush
@endsection 
