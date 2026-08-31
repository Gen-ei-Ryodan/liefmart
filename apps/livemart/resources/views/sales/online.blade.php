@extends('layouts.app')

@section('title', 'Penjualan Online')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Penjualan Online</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}" class="text-decoration-none">Menu Penjualan</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sales.choose-type') }}" class="text-decoration-none">Pilih Tipe Penjualan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penjualan Online</li>
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
                    <i class="fas fa-globe text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Penjualan Online</h5>
                </div>

                <div class="card-body p-4">

                    <div class="row mb-4">
                        <div class="col-md-12 text-center">
                            <h4>Pilih Platform E-commerce</h4>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="card h-100 mb-4">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="mb-3 d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <img src="{{ asset('images/logo/shopee.png') }}" alt="Shopee Lamourad" class="img-fluid" style="max-height: 80px;">
                                    </div>
                                    <h5 class="card-title mb-3">Shopee Lamourad</h5>
                                    <div class="mt-auto">
                                        <a href="{{ route('sales.platform', ['platform' => 'shopee']) }}" class="btn btn-primary w-100">Pilih</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-4">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="mb-3 d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <img src="{{ asset('images/logo/shopee.png') }}" alt="Shopee Liefmarket" class="img-fluid" style="max-height: 80px;">
                                    </div>
                                    <h5 class="card-title mb-3">Shopee Liefmarket</h5>
                                    <div class="mt-auto">
                                        <a href="{{ route('sales.platform', ['platform' => 'shopee2']) }}" class="btn btn-primary w-100">Pilih</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-4">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="mb-3 d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <img src="{{ asset('images/logo/tiktok.png') }}" alt="Tiktok Lamourad" class="img-fluid" style="max-height: 80px;">
                                    </div>
                                    <h5 class="card-title mb-3">Tiktok Lamourad</h5>
                                    <div class="mt-auto">
                                        <a href="{{ route('sales.platform', ['platform' => 'tiktok']) }}" class="btn btn-primary w-100">Pilih</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card h-100 mb-4">
                                <div class="card-body text-center d-flex flex-column">
                                    <div class="mb-3 d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <img src="{{ asset('images/logo/tiktok.png') }}" alt="Tiktok Liefmarket" class="img-fluid" style="max-height: 80px;">
                                    </div>
                                    <h5 class="card-title mb-3">Tiktok Liefmarket</h5>
                                    <div class="mt-auto">
                                        <a href="{{ route('sales.platform', ['platform' => 'tiktok2']) }}" class="btn btn-primary w-100">Pilih</a>
                                    </div>
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