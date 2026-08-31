@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Pelanggan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}" class="text-decoration-none">Pelanggan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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

    <form action="{{ route('customers.store') }}" method="POST">
        @csrf

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-user text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Pelanggan</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="name" class="form-label fw-medium">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Masukkan nama pelanggan" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="email" class="form-label fw-medium">Email</label>
                            <input type="email" class="form-control rounded-3 @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="phone" class="form-label fw-medium">Nomor Telepon <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="Masukkan nomor telepon" required>
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="pic_name" class="form-label fw-medium">Nama PIC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('pic_name') is-invalid @enderror" id="pic_name" name="pic_name" value="{{ old('pic_name') }}" placeholder="Nama penanggung jawab" required>
                            @error('pic_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="npwp" class="form-label fw-medium">NPWP</label>
                            <input type="text" class="form-control rounded-3 @error('npwp') is-invalid @enderror" id="npwp" name="npwp" value="{{ old('npwp') }}" placeholder="Nomor NPWP (opsional)">
                            @error('npwp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="status" class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3 @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Tidak Aktif</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="address" class="form-label fw-medium">Alamat</label>
                    <textarea class="form-control rounded-3 @error('address') is-invalid @enderror" id="address" name="address" rows="3" placeholder="Alamat lengkap pelanggan">{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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
