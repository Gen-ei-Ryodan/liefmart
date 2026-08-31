@extends('layouts.app')

@section('title', 'Edit Master Barang Platform')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Master Barang Platform</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('barang-platform.index') }}" class="text-decoration-none">Barang Platform</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
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

    <form action="{{ route('barang-platform.update', $platformProduct->id) }}" method="POST" class="needs-validation" novalidate>
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-store text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Platform</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="platform_id" class="form-label fw-medium">Platform</label>
                            <input type="text" class="form-control rounded-3" value="{{ $platformProduct->platform->name }}" readonly>
                            <div class="form-text text-muted">
                                <i class="fas fa-lock me-1"></i>Platform tidak dapat diubah
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="platform_product_name" class="form-label fw-medium">Nama Barang Platform <span class="text-danger">*</span></label>
                            <input type="text" name="platform_product_name" id="platform_product_name" class="form-control rounded-3 @error('platform_product_name') is-invalid @enderror" value="{{ old('platform_product_name', $platformProduct->platform_product_name) }}" required>
                            @error('platform_product_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Mengubah nama akan mempengaruhi semua analytics dan laporan
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="variant" class="form-label fw-medium">Variant</label>
                    <input type="text" name="variant" id="variant" class="form-control rounded-3 @error('variant') is-invalid @enderror" value="{{ old('variant', $platformProduct->variant) }}" placeholder="Contoh: Warna Merah, Size L, dll">
                    @error('variant')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-muted">
                        <i class="fas fa-tag me-1"></i>Variant produk (opsional)
                    </div>
                </div>

                @if($platformProduct->mappingBarang->count() > 0)
                    <div class="alert alert-info">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle fa-2x me-3 mt-1"></i>
                            <div>
                                <h5 class="alert-heading mb-2">Informasi Mapping</h5>
                                <p class="mb-2">Barang ini sudah di-mapping dengan <strong>{{ $platformProduct->mappingBarang->count() }}</strong> produk internal:</p>
                                <ul class="mb-0">
                                    @foreach($platformProduct->mappingBarang as $mapping)
                                        <li><strong>{{ $mapping->product ? $mapping->product->name : 'Product tidak ditemukan (ID: ' . $mapping->product_id . ')' }}</strong> (Qty: {{ $mapping->quantity }})</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $usedInSales = \DB::table('order_items')
                        ->where('platform_product_id', $platformProduct->id)
                        ->exists();
                @endphp
                @if($usedInSales)
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle fa-2x me-3 mt-1"></i>
                            <div>
                                <h5 class="alert-heading mb-2">Peringatan Penting</h5>
                                <p class="mb-0">Barang ini sudah digunakan dalam transaksi penjualan. Mengubah nama akan mempengaruhi semua laporan dan analytics.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('barang-platform.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="fas fa-times me-2"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Update Barang Platform
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-save draft functionality
let draftKey = 'barang-platform-edit-{{ $platformProduct->id }}';

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input[type="text"]');

    const savedDraft = localStorage.getItem(draftKey);
    if (savedDraft) {
        const draft = JSON.parse(savedDraft);
        Object.keys(draft).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && input.value === '') {
                input.value = draft[key];
            }
        });
    }

    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const formData = new FormData(form);
            const draft = {};
            for (let [key, value] of formData.entries()) {
                if (key !== '_token' && key !== '_method') {
                    draft[key] = value;
                }
            }
            localStorage.setItem(draftKey, JSON.stringify(draft));
        });
    });

    form.addEventListener('submit', function() {
        localStorage.removeItem(draftKey);
    });
});

document.querySelector('form').addEventListener('submit', function(e) {
    const platformProductName = document.getElementById('platform_product_name').value;
    const originalName = '{{ $platformProduct->platform_product_name }}';

    if (platformProductName !== originalName) {
        if (!confirm('Anda yakin ingin mengubah nama barang platform? Perubahan ini akan mempengaruhi semua analytics dan laporan.')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>
@endpush
@endsection
