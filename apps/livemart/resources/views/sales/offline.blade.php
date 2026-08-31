@extends('layouts.app')

@section('title', 'Penjualan Offline')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Penjualan Offline</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}" class="text-decoration-none">Menu Penjualan</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sales.choose-type') }}" class="text-decoration-none">Pilih Tipe Penjualan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penjualan Offline</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('sales.choose-type') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-store text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Penjualan Offline</h5>
                </div>

                <div class="card-body p-4">
                    <div class="row mb-4">
                        <div class="col-md-12 text-center">
                            <h4>Silahkan pilih menu penjualan offline</h4>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-body text-center">
                                    <i class="fas fa-cart-plus fa-4x mb-3"></i>
                                    <h5 class="card-title">Buat Penjualan Baru</h5>
                                    <p class="card-text">Input transaksi penjualan offline baru</p>
                                    <a href="{{ route('sales.offline.create') }}" class="btn btn-primary">Buat Penjualan</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-body text-center">
                                    <i class="fas fa-list fa-4x mb-3"></i>
                                    <h5 class="card-title">Daftar Penjualan</h5>
                                    <p class="card-text">Lihat semua transaksi penjualan offline</p>
                                    <a href="{{ route('sales.offline.list') }}" class="btn btn-primary">Lihat Daftar</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12 text-center">
                            <a href="{{ route('sales.choose-type') }}" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-arrow-left me-2"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 