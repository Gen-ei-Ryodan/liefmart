@extends('layouts.app')

@section('title', 'Edit Varian Produk')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Varian Produk</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('product-variants.index') }}" class="text-decoration-none">Varian Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('product-variants.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Error!</strong> Ada beberapa masalah dengan inputan Anda:
        <ul class="mb-0 ps-3 pt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <form action="{{ route('product-variants.update', $productVariant->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-shapes text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Varian Produk</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="name" class="form-label fw-medium">Nama Varian <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $productVariant->name) }}" placeholder="Masukkan nama varian" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="product_size_id" class="form-label fw-medium">Ukuran Produk <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3 @error('product_size_id') is-invalid @enderror" id="product_size_id" name="product_size_id" required>
                                <option value="" selected disabled>-- Pilih Ukuran Produk --</option>
                                @foreach($productSizes as $size)
                                    <option value="{{ $size->id }}" {{ old('product_size_id', $productVariant->product_size_id) == $size->id ? 'selected' : '' }}>
                                        {{ $size->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_size_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label fw-medium">Deskripsi</label>
                    <textarea class="form-control rounded-3 @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Deskripsi varian produk (opsional)">{{ old('description', $productVariant->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $productVariant->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="{{ route('product-variants.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="fas fa-times me-2"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
