@extends('layouts.app')

@section('title', 'Pilih Tipe Penjualan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Pilih Tipe Penjualan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}" class="text-decoration-none">Menu Penjualan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pilih Tipe</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-cash-register text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Pilih Tipe Penjualan</h5>
                </div>

                <div class="card-body p-4">
                    <div class="row mb-4">
                        <div class="col-md-12 text-center">
                            <h4>Silahkan pilih tipe penjualan yang ingin diinput</h4>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-store fa-4x mb-3 text-primary"></i>
                                    <h5 class="card-title fw-semibold">Penjualan Offline</h5>
                                    <p class="card-text text-muted">Input transaksi penjualan dari toko fisik</p>
                                    <a href="{{ route('sales.offline') }}" class="btn btn-primary rounded-pill px-4">Pilih</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-globe fa-4x mb-3 text-primary"></i>
                                    <h5 class="card-title fw-semibold">Penjualan Online</h5>
                                    <p class="card-text text-muted">Input transaksi penjualan dari platform online</p>
                                    <a href="{{ route('sales.online') }}" class="btn btn-primary rounded-pill px-4">Pilih</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection