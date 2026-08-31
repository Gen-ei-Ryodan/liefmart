@extends('layouts.app')

@section('title', 'Edit Sub Brand')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Sub Brand</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('subbrands.index') }}" class="text-decoration-none">Sub Brand</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('subbrands.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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

    @if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <form action="{{ route('subbrands.update', $subbrand->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-tags text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Sub Brand</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="name" class="form-label fw-medium">Nama Sub Brand <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $subbrand->name) }}" placeholder="Masukkan nama sub brand" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="brand_id" class="form-label fw-medium">Brand <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3 @error('brand_id') is-invalid @enderror" id="brand_id" name="brand_id" required>
                                <option value="" selected disabled>-- Pilih Brand --</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $subbrand->brand_id) == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('brand_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label fw-medium">Deskripsi</label>
                    <textarea class="form-control rounded-3 @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Deskripsi sub brand (opsional)">{{ old('description', $subbrand->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $subbrand->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktif</label>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="{{ route('subbrands.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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
