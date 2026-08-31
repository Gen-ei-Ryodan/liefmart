@extends('layouts.app')

@section('title', 'Pilih Platform Keuangan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Pilih Platform Keuangan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Keuangan</li>
                </ol>
            </nav>
            <p class="text-muted mb-0">Pilih platform untuk melihat dan mengelola data keuangan</p>
        </div>
    </div>
    <div class="row justify-content-center g-4">
        <div class="col-md-3 col-12">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center p-4">
                    <div class="platform-icon mb-3">
                        <img src="{{ asset('images/logo/shopee.png') }}" alt="Shopee Lamourad" class="img-fluid" style="max-height: 80px;" onerror="this.src='https://via.placeholder.com/150x80?text=Shopee+Lamourad'">
                    </div>
                    <h5 class="card-title fw-bold">Shopee Lamourad</h5>
                    <p class="card-text text-muted">Kelola data keuangan platform Shopee Lamourad</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('finance.shopee.index') }}" class="btn btn-primary">
                            <i class="fas fa-money-bill me-1"></i> Kelola Keuangan
                        </a>
                        <a href="{{ route('finance.shopee.import') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-import me-1"></i> Import Data
                        </a>
                        <a href="{{ route('finance.aruskasshopee.index') }}" class="btn btn-outline-info">
                            <i class="fas fa-chart-line me-1"></i> Arus Kas
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-12">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center p-4">
                    <div class="platform-icon mb-3">
                        <img src="{{ asset('images/logo/tiktok.png') }}" alt="Tiktok Lamourad" class="img-fluid" style="max-height: 80px;" onerror="this.src='https://via.placeholder.com/150x80?text=Tiktok+Lamourad'">
                    </div>
                    <h5 class="card-title fw-bold">Tiktok Lamourad</h5>
                    <p class="card-text text-muted">Kelola data keuangan platform Tiktok Lamourad</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('finance.tiktok.index') }}" class="btn btn-dark">
                            <i class="fas fa-money-bill me-1"></i> Kelola Keuangan
                        </a>
                        <a href="{{ route('finance.tiktok.import') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-import me-1"></i> Import Data
                        </a>
                        <a href="{{ route('finance.aruskastiktok.index') }}" class="btn btn-outline-info">
                            <i class="fas fa-chart-line me-1"></i> Arus Kas
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-12">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center p-4">
                    <div class="platform-icon mb-3">
                        <img src="{{ asset('images/logo/shopee.png') }}" alt="Shopee Liefmarket" class="img-fluid" style="max-height: 80px;" onerror="this.src='https://via.placeholder.com/150x80?text=Shopee+Liefmarket'">
                    </div>
                    <h5 class="card-title fw-bold">Shopee Liefmarket</h5>
                    <p class="card-text text-muted">Kelola data keuangan platform Shopee Liefmarket</p>
                    <div class="d-grid gap-2">
                    <a href="{{ route('finance.shopee2.index') }}" class="btn btn-primary">
                        <i class="fas fa-money-bill me-1"></i> Kelola Keuangan
                    </a>
                    <a href="{{ route('finance.shopee2.import') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-file-import me-1"></i> Import Data
                    </a>
                    <a href="{{ route('finance.aruskasshopee2.index') }}" class="btn btn-outline-info">
                        <i class="fas fa-chart-line me-1"></i> Arus Kas
                    </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-12">
            <div class="card h-100 shadow-sm border-0 hover-card">
                <div class="card-body text-center p-4">
                    <div class="platform-icon mb-3">
                        <img src="{{ asset('images/logo/tiktok.png') }}" alt="Tiktok Liefmarket" class="img-fluid" style="max-height: 80px;" onerror="this.src='https://via.placeholder.com/150x80?text=Tiktok+Liefmarket'">
                    </div>
                    <h5 class="card-title fw-bold">Tiktok Liefmarket</h5>
                    <p class="card-text text-muted">Kelola data keuangan platform Tiktok Liefmarket</p>
                    <div class="d-grid gap-2">
                    <a href="{{ route('finance.tiktok2.index') }}" class="btn btn-dark">
                        <i class="fas fa-money-bill me-1"></i> Kelola Keuangan
                    </a>
                    <a href="{{ route('finance.tiktok2.import') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-file-import me-1"></i> Import Data
                    </a>
                    <a href="{{ route('finance.aruskastiktok2.index') }}" class="btn btn-outline-info">
                        <i class="fas fa-chart-line me-1"></i> Arus Kas
                    </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-12">
            <div class="card h-100 shadow-sm border-0 hover-card bg-warning bg-opacity-10">
                <div class="card-body text-center p-4">
                    <div class="platform-icon mb-3">
                        <i class="fas fa-exclamation-circle fa-3x text-warning"></i>
                    </div>
                    <h5 class="card-title fw-bold text-warning">Order Belum Ada Pembayaran</h5>
                    <p class="card-text text-muted">Lihat dan export semua order yang belum ada pembayaran di semua platform</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('finance.unpaid-orders.index') }}" class="btn btn-warning text-white">
                            <i class="fas fa-search-dollar me-1"></i> Lihat Order Belum Bayar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-card {
        transition: transform 0.3s ease;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .platform-icon img, .platform-icon i {
        margin-bottom: 8px;
    }
    .card-title {
        font-size: 1.2rem;
    }
    .card-text {
        min-height: 40px;
    }
</style>
@endsection
