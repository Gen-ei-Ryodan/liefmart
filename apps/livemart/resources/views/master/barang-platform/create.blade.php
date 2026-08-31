@extends('layouts.app')

@section('title', 'Tambah Master Barang Platform')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Master Barang Platform</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('barang-platform.index') }}" class="text-decoration-none">Barang Platform</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('barang-platform.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <form action="{{ route('barang-platform.store') }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-store text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Barang Platform</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="platform_id" class="form-label fw-medium">Platform <span class="text-danger">*</span></label>
                            <select name="platform_id" id="platform_id" class="form-select rounded-3 @error('platform_id') is-invalid @enderror" required>
                                <option value="" selected disabled>-- Pilih Platform --</option>
                                @foreach($platforms as $platform)
                                    <option value="{{ $platform->id }}" {{ old('platform_id') == $platform->id ? 'selected' : '' }}>
                                        {{ $platform->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('platform_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="platform_product_name" class="form-label fw-medium">Nama Barang Platform <span class="text-danger">*</span></label>
                            <input type="text" name="platform_product_name" id="platform_product_name" class="form-control rounded-3 @error('platform_product_name') is-invalid @enderror" value="{{ old('platform_product_name') }}" required>
                            @error('platform_product_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="variant" class="form-label fw-medium">Variant</label>
                    <input type="text" name="variant" id="variant" class="form-control rounded-3 @error('variant') is-invalid @enderror" value="{{ old('variant') }}" placeholder="Contoh: Warna Merah, Size L, dll">
                    @error('variant')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-info">
                        <i class="fas fa-info-circle me-1"></i>
                        Kombinasi Platform + Nama + Variant harus unik
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('barang-platform.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="fas fa-times me-2"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
