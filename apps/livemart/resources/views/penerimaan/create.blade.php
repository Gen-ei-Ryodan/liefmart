@extends('layouts.app')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Form Penerimaan Barang</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('penerimaan.index') }}" class="text-decoration-none">Penerimaan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('penerimaan.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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

    <form onsubmit="return false;" id="formPenerimaan">
        <div class="row">
            <!-- Left Column - Main Information -->
            <div class="col-lg-12 mb-4">
                <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
                    <div class="card-header bg-gradient-light d-flex align-items-center py-3">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <h5 class="mb-0 fw-semibold">Informasi Utama</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <!-- Left side - Essential info -->
                            <div class="col-md-6">
                                <!-- Kode Penerimaan -->
                                <div class="mb-4">
                                    <label for="kode_penerimaan" class="form-label fw-medium">Kode Penerimaan</label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="fas fa-barcode text-primary"></i>
                                        </span>
                                        <input type="text" class="form-control bg-light border-0 ps-1" id="kode_penerimaan" name="kode_penerimaan" value="{{ $kodePenerimaan ?? 'PNR-000001' }}" readonly>
                                    </div>
                                </div>
                                
                                <!-- Jenis Kategori -->
                                <div class="mb-4">
                                    <label for="main_category_id" class="form-label fw-medium">Kategori Barang</label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="fas fa-layer-group text-primary"></i>
                                        </span>
                                        <input type="text" class="form-control bg-light border-0 ps-1" value="{{ session('main_category_name') }}" readonly>
                                        <input type="hidden" name="main_category_id" id="main_category_id" value="{{ session('main_category_id') }}">
                                    </div>
                                    <div class="form-text text-muted">Kategori yang dipilih saat login</div>
                                </div>
                                
                                <!-- Replace with visible tax category dropdown -->
                                <div class="mb-4">
                                    <label for="tax_category_id" class="form-label fw-medium">Kategori Pajak <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="fas fa-percentage text-primary"></i>
                                        </span>
                                        <select class="form-select" id="tax_category_id" name="tax_category_id" required>
                                            <option value="" disabled selected>-- Pilih Kategori Pajak --</option>
                                            <!-- Options will be loaded dynamically based on main_category_id -->
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right side - Additional info -->
                            <div class="col-md-6">
                                <!-- Nomor PO -->
                                <div class="mb-4">
                                    <label for="nomor_po" class="form-label fw-medium">Nomor PO <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control rounded-3" id="nomor_po" name="nomor_po" placeholder="Masukkan nomor PO" required>
                                </div>
                                
                                <!-- Tanggal Penerimaan -->
                                <div class="mb-4">
                                    <label for="tanggal_penerimaan" class="form-label fw-medium">Tanggal Penerimaan <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text border-end-0">
                                            <i class="fas fa-calendar-alt text-primary"></i>
                                        </span>
                                        <input type="date" class="form-control border-start-0 ps-0 rounded-3" id="tanggal_penerimaan" name="tanggal_penerimaan" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                
                                <!-- Metode Pembayaran -->
                                <div class="mb-4">
                                    <label for="metode_pembayaran" class="form-label fw-medium">Metode Pembayaran <span class="text-danger">*</span></label>
                                    <select class="form-select rounded-3" id="metode_pembayaran" name="metode_pembayaran" required>
                                        <option value="Cash">Cash</option>
                                        <option value="Jatuh Tempo">Jatuh Tempo</option>
                                    </select>
                                </div>
                                
                                <!-- Tanggal Jatuh Tempo (Tersembunyi secara default) -->
                                <div class="mb-4" id="jatuhTempoContainer" style="display: none; transition: all 0.3s ease;">
                                    <label for="tanggal_jatuh_tempo" class="form-label fw-medium">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text border-end-0">
                                            <i class="fas fa-calendar-alt text-primary"></i>
                                        </span>
                                        <input type="date" class="form-control border-start-0 ps-0 rounded-3" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo">
                                    </div>
                                    <div class="form-text text-muted">Tanggal jatuh tempo pembayaran</div>
                                </div>
                            </div>
                            
                            <!-- Full width - Catatan -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="catatan" class="form-label fw-medium">Catatan</label>
                                    <textarea class="form-control rounded-3" id="catatan" name="catatan" rows="3" placeholder="Catatan tambahan (opsional)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detail Barang Section -->
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-box text-primary me-2"></i>
                        <h5 class="mb-0">Detail Barang</h5>
                    </div>
                    <div class="card-body p-0">
                        <!-- Table for displaying items -->
                        <div class="table-responsive">
                            <table class="table align-middle" id="tabelDetailBarang">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 30%" class="ps-4">Nama Barang</th>
                                        <th style="width: 8%" class="text-center">Qty</th>
                                        <th style="width: 12%" class="text-center">Satuan</th>
                                        <th style="width: 12%" class="text-end">Harga</th>
                                        <th style="width: 15%" class="text-end">Diskon</th>
                                        <th style="width: 13%" class="text-end">Sub Total</th>
                                        <th style="width: 10%" class="text-center pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelDetailBarangBody">
                                    <tr id="emptyRow">
                                        <td colspan="7" class="text-center py-5">
                                            <div class="py-4">
                                                <div class="mb-3">
                                                    <i class="fas fa-box-open fa-3x text-muted opacity-50"></i>
                                                </div>
                                                <h6 class="fw-normal mb-1">Belum ada barang</h6>
                                                <p class="text-muted mb-0 small">Tambahkan barang menggunakan form di bawah</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Form untuk tambah barang dengan styling yang lebih baik -->
                        <div class="p-4 border-top" style="background-color: rgba(181, 198, 224, 0.05);">
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label small fw-medium mb-2">Nama Barang <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" id="barang_id">
                                        <option value="" selected disabled>-- Pilih Barang --</option>
                                    </select>
                                </div>
                                <div class="col-lg-1 col-md-2 col-sm-3">
                                    <label class="form-label small fw-medium mb-2">Qty <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control shadow-none text-center" id="qty" min="1" step="1" placeholder="0">
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-4">
                                    <label class="form-label small fw-medium mb-2">Satuan <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" id="satuan_id">
                                        <option value="" selected disabled>-- Satuan --</option>
                                        @foreach($satuan as $item)
                                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 col-md-4 col-sm-5">
                                    <label class="form-label small fw-medium mb-2">Harga <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-seamless">
                                        <span class="input-group-text border-end-0 bg-light">Rp</span>
                                        <input type="number" class="form-control border-start-0 ps-0 shadow-none" id="harga_hpp" min="0" placeholder="0">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mt-2 align-items-center" id="penerimaanHelperRow">
                                <div class="col-md-5">
                                    <div id="priceHistoryContainer" class="d-none">
                                        <button type="button" class="btn btn-sm btn-outline-info" id="btnPriceHistory" data-bs-toggle="modal" data-bs-target="#priceHistoryModal">
                                            <i class="fas fa-history me-1"></i> Lihat History Harga
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div id="priceSuggestionContainer"></div>
                                </div>
                            </div>
                            
                            <!-- Discount System - 5 Levels -->
                            <div class="p-3 mb-3 rounded-3 border border-1" style="background-color: rgba(65, 95, 255, 0.03);">
                                <div class="mb-2">
                                    <h6 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i> Sistem Diskon (5 Level)</h6>
                                    <p class="text-muted small mb-0">Isi hanya satu jenis diskon (% atau Rp) per level</p>
                                </div>
                                
                                <div class="row g-2">
                                    <!-- Discount Level 1 -->
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Diskon 1 (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-center discount-input" id="diskon_persen_1" min="0" max="100" step="0.01" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Nominal 1</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control discount-input" id="diskon_nominal_1" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Discount Level 2 -->
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Diskon 2 (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-center discount-input" id="diskon_persen_2" min="0" max="100" step="0.01" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Nominal 2</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control discount-input" id="diskon_nominal_2" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Discount Level 3 -->
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Diskon 3 (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-center discount-input" id="diskon_persen_3" min="0" max="100" step="0.01" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Nominal 3</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control discount-input" id="diskon_nominal_3" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Discount Level 4 -->
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Diskon 4 (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-center discount-input" id="diskon_persen_4" min="0" max="100" step="0.01" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Nominal 4</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control discount-input" id="diskon_nominal_4" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Discount Level 5 -->
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Diskon 5 (%)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control text-center discount-input" id="diskon_persen_5" min="0" max="100" step="0.01" placeholder="0">
                                            <span class="input-group-text bg-light">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 col-6">
                                        <label class="form-label small fw-medium">Nominal 5</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light">Rp</span>
                                            <input type="number" class="form-control discount-input" id="diskon_nominal_5" min="0" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 col-sm-8">
                                    <div class="form-check">
                                        <input class="form-check-input shadow-none" type="checkbox" id="is_free">
                                        <label class="form-check-label" for="is_free">
                                            <span class="small">Barang Free</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-4 text-end">
                                    <button type="button" class="btn btn-primary rounded-pill px-4" id="btnTambahBarang">
                                        <i class="fas fa-plus me-1"></i> Tambah Barang
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Total Penerimaan Card -->
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="fw-medium mb-1">Total Penerimaan</h5>
                                <p class="text-muted mb-0 small">Jumlah total biaya penerimaan barang</p>
                            </div>
                            <h3 class="text-primary fw-bold mb-0" id="totalHargaDisplay">Rp 0</h3>
                        </div>
                        <hr>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-lg btn-primary py-3 rounded-pill shadow-sm">
                                <i class="fas fa-save me-2"></i> Simpan & Masukkan ke Unlocated
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Price History Modal -->
<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-labelledby="priceHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="priceHistoryModalLabel">
                    <i class="fas fa-history me-2"></i>History Harga & Diskon
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="priceHistoryContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat history harga...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('styles')
<!-- Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<style>
/* Tom Select Custom Styling */
.ts-wrapper.form-select {
    height: auto;
    padding: 0;
}

.ts-control {
    border-radius: 8px !important;
    border: 1.5px solid #e9ecef !important;
    padding: 0.6rem 1rem !important;
    box-shadow: none !important;
    transition: all 0.3s ease;
}

.ts-control:focus {
    border-color: #4a6cf7 !important;
    box-shadow: 0 0 0 0.25rem rgba(74, 108, 247, 0.15) !important;
}

.ts-dropdown {
    border-radius: 8px !important;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
    border: 1.5px solid #e9ecef !important;
}

.ts-dropdown .option {
    padding: 0.65rem 1rem !important;
}

.ts-dropdown .active {
    background-color: rgba(74, 108, 247, 0.08) !important;
    color: #4a6cf7 !important;
}

.ts-dropdown .create {
    padding: 0.65rem 1rem !important;
}

.bg-gradient-light {
    background: linear-gradient(to right, #f8f9fa, #f0f3f6);
}

.input-group-seamless .input-group-text {
    background-color: transparent;
    border-right: 0;
    color: #6c757d;
}

.input-group-seamless .form-control {
    border-left: 0;
}

#tabelDetailBarang {
    border-collapse: separate;
    border-spacing: 0;
}

#tabelDetailBarang thead th {
    font-weight: 600;
    color: #495057;
    padding: 1rem;
    border-bottom: 2px solid #eaecf0;
}

#tabelDetailBarang tbody td {
    padding: 1rem;
    border-bottom: 1px solid #eaecf0;
}

tr.item-row {
    transition: all 0.3s ease;
}

tr.item-row:hover {
    background-color: rgba(74, 108, 247, 0.02);
}

#emptyRow td {
    background-color: #f8f9fa;
    border-bottom: none;
}

.btn-delete {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background-color: #fff1f1;
    color: #dc3545;
    border: none;
    transition: all 0.2s;
}

.btn-delete:hover {
    background-color: #dc3545;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 3px 10px rgba(220, 53, 69, 0.2);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(10px);
    }
}

.item-row {
    animation: fadeIn 0.3s ease-out forwards;
}

.item-row.deleting {
    animation: fadeOut 0.3s ease-in forwards;
}

.was-validated .form-control:invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.was-validated .form-control:valid {
    border-color: #198754;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }

    .btn {
        padding: 0.5rem 1rem;
    }

    #tabelDetailBarang th,
    #tabelDetailBarang td {
        padding: 0.75rem 0.5rem;
    }

    #priceHistoryModal .modal-dialog {
        margin: 0.5rem;
    }

    #priceHistoryModal .modal-body {
        padding: 1rem;
    }

    #priceHistoryModal .table {
        font-size: 0.85rem;
    }

    #priceHistoryModal .table th,
    #priceHistoryModal .table td {
        padding: 0.5rem;
    }

    #btnPriceHistory {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
        width: 100%;
    }
}

@media (max-width: 576px) {
    #priceHistoryModal .table-responsive {
        font-size: 0.8rem;
    }

    #priceHistoryModal .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }

    #priceHistoryModal .modal-title {
        font-size: 1.1rem;
    }
}

#priceHistoryModal .modal-header {
    background: linear-gradient(to right, #f8f9fa, #f0f3f6);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

#priceHistoryModal .table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #eaecf0;
    padding: 0.75rem;
}

#priceHistoryModal .table td {
    padding: 0.75rem;
    vertical-align: middle;
}

#priceHistoryModal .table td small {
    font-size: 0.8rem;
    line-height: 1.3;
}

#priceHistoryModal .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
}

#priceHistoryContainer {
    animation: fadeIn 0.3s ease-out;
}

#btnPriceHistory {
    transition: all 0.3s ease;
    border: 1px solid #17a2b8;
    color: #17a2b8;
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
}

#btnPriceHistory:hover {
    background-color: #17a2b8;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(23, 162, 184, 0.2);
}

#btnPriceHistory:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.85rem;
}

.alert-sm .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

#priceSuggestion {
    animation: slideDown 0.3s ease-out;
}

#penerimaanHelperRow {
    min-height: 46px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endpush

@push('scripts')
<!-- Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const mainCategoryId = document.getElementById('main_category_id').value;
    const taxCategorySelect = document.getElementById('tax_category_id');
    const barangSelect = document.getElementById('barang_id');
    const satuanSelect = document.getElementById('satuan_id');
    const hargaInput = document.getElementById('harga_hpp');
    const qtyInput = document.getElementById('qty');
    
    // Discount inputs - All 5 levels
    const diskonPersenInputs = [
        document.getElementById('diskon_persen_1'),
        document.getElementById('diskon_persen_2'),
        document.getElementById('diskon_persen_3'),
        document.getElementById('diskon_persen_4'),
        document.getElementById('diskon_persen_5')
    ];
    
    const diskonNominalInputs = [
        document.getElementById('diskon_nominal_1'),
        document.getElementById('diskon_nominal_2'),
        document.getElementById('diskon_nominal_3'),
        document.getElementById('diskon_nominal_4'),
        document.getElementById('diskon_nominal_5')
    ];
    
    const isFreeCheckbox = document.getElementById('is_free');
    const btnTambahBarang = document.getElementById('btnTambahBarang');
    const tabelDetailBarang = document.getElementById('tabelDetailBarang');
    const emptyRow = document.getElementById('emptyRow');
    const formPenerimaan = document.getElementById('formPenerimaan');
    
    // Add Form Validation Styling
    formPenerimaan.classList.add('needs-validation');
    
    // Visual feedback function
    function flashElement(el, className = 'flash-highlight') {
        el.classList.add(className);
        setTimeout(() => {
            el.classList.remove(className);
        }, 750);
    }

    async function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            const data = await response.json();
            if (!response.ok) {
                let message = data.message || `HTTP error! Status: ${response.status}`;
                if (data.errors && typeof data.errors === 'object') {
                    const firstKey = Object.keys(data.errors)[0];
                    const firstError = firstKey ? data.errors[firstKey] : null;
                    if (Array.isArray(firstError) && firstError[0]) {
                        message = firstError[0];
                    } else if (typeof firstError === 'string' && firstError) {
                        message = firstError;
                    }
                }
                throw new Error(message);
            }
            return data;
        }

        const status = response.status;
        if (status === 419 || status === 401 || status === 403) {
            throw new Error('Sesi login habis atau tidak valid. Silakan refresh halaman lalu login ulang.');
        }
        throw new Error(`Respon server bukan JSON (HTTP ${status}). Silakan refresh halaman lalu coba lagi.`);
    }
    
    // Format currency with 2 decimal places for individual items
    function formatRupiah(amount) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }
    
    // Format currency for grand total (no decimals)
    function formatRupiahTotal(amount) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(Math.round(amount));
    }
    
    let taxCategoryTomSelect = new TomSelect(taxCategorySelect, {
        placeholder: "-- Pilih Kategori Pajak --",
        allowEmptyOption: true,
        sortField: {
            field: "text",
            direction: "asc"
        },
        dropdownParent: 'body',
        closeAfterSelect: true
    });
    
    // Load tax categories based on main category
    function loadTaxCategories(mainCategoryId) {
        if (!mainCategoryId) return;

        // Reset and disable tax category select with loading indicator
        taxCategoryTomSelect.clear();
        taxCategoryTomSelect.clearOptions();
        taxCategoryTomSelect.addOption({
            value: '',
            text: 'Loading...'
        });
        taxCategoryTomSelect.disable();
        
        console.log("Loading tax categories for main category ID:", mainCategoryId);

        // Fetch tax categories for selected main category
        fetch(`/api/tax-categories?main_category_id=${mainCategoryId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(parseJsonResponse)
            .then(data => {
                if (data.success) {
                    // Clear existing options
                    taxCategoryTomSelect.clear();
                    taxCategoryTomSelect.clearOptions();

                    // Add default option
                    taxCategoryTomSelect.addOption({
                        value: '',
                        text: '-- Pilih Kategori Pajak --'
                    });

                    // Filter tax categories based on main category
                    let filteredCategories = data.tax_categories;
                    
                    // For KOSMETIK (main_category_id = 2), only show tax_id 3 or 4 (PKP and NON PKP)
                    if (mainCategoryId == 2) { // KOSMETIK
                        console.log("Filtering tax categories for KOSMETIK (showing tax_id 3 or 4 - PKP and NON PKP)");
                        filteredCategories = data.tax_categories.filter(cat => cat.id == 3 || cat.id == 4);
                    }

                    // Add filtered options
                    filteredCategories.forEach(category => {
                        taxCategoryTomSelect.addOption({
                            value: category.id,
                            text: `${category.name} (${category.tax_percentage}%)`
                        });
                    });

                    // Enable select with animation
                    taxCategoryTomSelect.enable();

                    // Auto-select first tax category if available
                    if (filteredCategories.length > 0) {
                        taxCategoryTomSelect.setValue(filteredCategories[0].id);
                    }
                } else {
                    console.error('Error response from server:', data);
                    taxCategoryTomSelect.clear();
                    taxCategoryTomSelect.clearOptions();
                    taxCategoryTomSelect.addOption({
                        value: '',
                        text: 'Error: ' + (data.error || 'Unknown error')
                    });
                    taxCategoryTomSelect.disable();
                }
            })
            .catch(error => {
                console.error('Error fetching tax categories:', error);
                taxCategoryTomSelect.clear();
                taxCategoryTomSelect.clearOptions();
                taxCategoryTomSelect.addOption({
                    value: '',
                    text: 'Error: ' + error.message
                });
                taxCategoryTomSelect.disable();
            });
    }

    // Initialize tax categories based on main category
    loadTaxCategories(mainCategoryId);

    let barangTomSelect = new TomSelect(barangSelect, {
        placeholder: "-- Pilih Barang --",
        allowEmptyOption: true,
        sortField: {
            field: "text",
            direction: "asc"
        },
        dropdownParent: 'body',
        closeAfterSelect: true,
        render: {
            option: function(item, escape) {
                return `<div class="py-2 px-3">
                    <div class="mb-1">
                        <span class="fw-medium">${escape(item.text)}</span>
                    </div>
                </div>`;
            }
        },
        onChange: function(value) {
            const priceHistoryContainer = document.getElementById('priceHistoryContainer');
            const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
            if (!value) {
                if (priceHistoryContainer) {
                    priceHistoryContainer.classList.add('d-none');
                }
                if (priceSuggestionContainer) {
                    priceSuggestionContainer.innerHTML = '';
                }
                return;
            }
            
            // Get selected product data
            const selectedOption = this.options[value];
            if (selectedOption) {
                console.log('Selected product:', selectedOption);
                
                // Set unit based on selected product - handle null safely
                const defaultSatuanId = selectedOption.default_satuan_id;
                if (defaultSatuanId && defaultSatuanId !== 'null' && defaultSatuanId !== '') {
                    satuanTomSelect.setValue(defaultSatuanId);
                }
                
                // Set price based on selected product - handle null safely
                const hargaValue = selectedOption.harga_hpp || (selectedOption.$option && selectedOption.$option.dataset.hargaHpp);
                if (hargaValue && hargaValue !== '0' && hargaValue !== 'null') {
                    const hargaNum = parseFloat(hargaValue);
                    if (!isNaN(hargaNum) && hargaNum > 0 && !isFreeCheckbox.checked) {
                        hargaInput.value = hargaNum;
                    }
                }
                
                if (priceHistoryContainer) {
                    priceHistoryContainer.classList.remove('d-none');
                }

                loadPriceHistory(value);
                
                suggestLastPrice(value);
                
                // Focus on quantity field for better UX flow
                qtyInput.focus();
            }
        }
    });
    
    let satuanTomSelect = new TomSelect(satuanSelect, {
        placeholder: "-- Pilih Satuan --",
        allowEmptyOption: true,
        sortField: {
            field: "text",
            direction: "asc"
        },
        dropdownParent: 'body',
        closeAfterSelect: true
    });

    // Load products for the main category ID from session
    if (mainCategoryId) {
        // Clear product select and show loading indicator
        barangTomSelect.clear();
        barangTomSelect.clearOptions();
        barangTomSelect.addOption({value: '', text: 'Loading...'});
        
        // Fetch products for the selected main category
        fetch(`{{ route('penerimaan.get-products') }}?main_category_id=${mainCategoryId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(parseJsonResponse)
            .then(data => {
                console.log('Products response:', data);
            
                // Clear existing options
                barangTomSelect.clear();
                barangTomSelect.clearOptions();
                
                // Add default option
                barangTomSelect.addOption({value: '', text: '-- Pilih Barang --'});
                
                // Add product options - handle both array format and object with array property
                if (Array.isArray(data)) {
                    data.forEach(product => {
                        barangTomSelect.addOption({
                            value: product.id,
                            text: product.text,
                            harga_hpp: product.harga_hpp || 0,
                            default_satuan_id: product.default_satuan_id || null
                        });
                    });
                } else if (data && Array.isArray(data.data)) {
                    // Handle case where response has data property containing the array
                    data.data.forEach(product => {
                        barangTomSelect.addOption({
                            value: product.id,
                            text: product.text || product.name,
                            harga_hpp: product.harga_hpp || product.price,
                            default_satuan_id: product.default_satuan_id
                        });
                    });
                } else {
                    console.error('Unexpected product data format:', data);
                    barangTomSelect.addOption({value: '', text: 'Error: Unexpected data format'});
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                barangTomSelect.clear();
                barangTomSelect.clearOptions();
                barangTomSelect.addOption({value: '', text: 'Error loading products'});
            });
    }
    
    // Smooth toggle for payment method change
    const metodePembayaranSelect = document.getElementById('metode_pembayaran');
    const jatuhTempoContainer = document.getElementById('jatuhTempoContainer');
    const tanggalJatuhTempoInput = document.getElementById('tanggal_jatuh_tempo');
    
    if (metodePembayaranSelect) {
        metodePembayaranSelect.addEventListener('change', function() {
            if (this.value === 'Jatuh Tempo') {
                jatuhTempoContainer.style.display = 'block';
                jatuhTempoContainer.style.opacity = '0';
                setTimeout(() => {
                    jatuhTempoContainer.style.opacity = '1';
                }, 10);
                tanggalJatuhTempoInput.setAttribute('required', 'required');
                tanggalJatuhTempoInput.focus();
            } else {
                jatuhTempoContainer.style.opacity = '0';
                setTimeout(() => {
                    jatuhTempoContainer.style.display = 'none';
                }, 300);
                tanggalJatuhTempoInput.removeAttribute('required');
            }
        });
    }
    
    // Add animation when the free checkbox is toggled
    if (isFreeCheckbox) {
        isFreeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                hargaInput.value = '0';
                hargaInput.setAttribute('readonly', 'readonly');
                diskonPersenInputs.forEach(input => input.value = '0');
                diskonNominalInputs.forEach(input => input.value = '0');
                diskonPersenInputs.forEach(input => input.setAttribute('readonly', 'readonly'));
                diskonNominalInputs.forEach(input => input.setAttribute('readonly', 'readonly'));
                
                const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
                if (priceSuggestionContainer) {
                    priceSuggestionContainer.innerHTML = '';
                }
                
                // Apply visual feedback
                hargaInput.parentElement.classList.add('text-muted');
                diskonPersenInputs.forEach(input => input.parentElement.classList.add('text-muted'));
                diskonNominalInputs.forEach(input => input.parentElement.classList.add('text-muted'));
            } else {
                hargaInput.removeAttribute('readonly');
                diskonPersenInputs.forEach(input => input.removeAttribute('readonly'));
                diskonNominalInputs.forEach(input => input.removeAttribute('readonly'));
                
                // Remove visual feedback
                hargaInput.parentElement.classList.remove('text-muted');
                diskonPersenInputs.forEach(input => input.parentElement.classList.remove('text-muted'));
                diskonNominalInputs.forEach(input => input.parentElement.classList.remove('text-muted'));
                
                // Restore previous value if available
                if (barangTomSelect.selectedIndex > 0) {
                    const selectedOption = barangTomSelect.options[barangTomSelect.selectedIndex];
                    hargaInput.value = selectedOption.harga || '';
                }
                
                hargaInput.focus();
            }
        });
    }
    
    // Array to store detail items
    let detailItems = [];
    let counter = 0;
    
    // Product selection with auto-fill price is handled in the TomSelect initialization
    
    // Clear price suggestion when user manually types in price field
    if (hargaInput) {
        hargaInput.addEventListener('input', function() {
            const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
            if (priceSuggestionContainer) {
                priceSuggestionContainer.innerHTML = '';
            }
        });
        
        hargaInput.addEventListener('focus', function() {
            const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
            if (priceSuggestionContainer && priceSuggestionContainer.innerHTML.trim() !== '') {
                clearTimeout(window.priceSuggestionTimeout);
            }
        });
    }
    
    // Price History Functionality
    function loadPriceHistory(productId) {
        const priceHistoryContent = document.getElementById('priceHistoryContent');
        
        if (!productId) {
            return;
        }
        
        // Show loading state
        priceHistoryContent.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat history harga...</p>
            </div>
        `;
        
        // Fetch price history
        fetch(`/penerimaan/price-history/${productId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(parseJsonResponse)
            .then(data => {
                if (data.success) {
                    displayPriceHistory(data);
                } else {
                    priceHistoryContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'Tidak dapat memuat history harga'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading price history:', error);
                priceHistoryContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Terjadi kesalahan saat memuat history harga
                    </div>
                `;
            });
    }
    
    function displayPriceHistory(data) {
        const priceHistoryContent = document.getElementById('priceHistoryContent');
        const modalTitle = document.getElementById('priceHistoryModalLabel');
        
        // Update modal title with product name
        modalTitle.innerHTML = `<i class="fas fa-history me-2"></i>History Harga & Diskon - ${data.product.name}`;
        
        if (data.history.length === 0) {
            priceHistoryContent.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum ada history harga untuk barang ini
                </div>
            `;
            return;
        }
        
        let historyHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Kode Penerimaan</th>
                            <th>No. PO</th>
                            <th class="text-end">Harga</th>
                            <th>Diskon</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.history.forEach(item => {
            const diskonText = item.diskon_detail.length > 0 
                ? item.diskon_detail.join('<br>') 
                : (item.diskon_persen_total > 0 ? `${item.diskon_persen_total}%` : '-');
            
            const statusBadge = item.is_free 
                ? '<span class="badge bg-secondary">Free</span>' 
                : '<span class="badge bg-success">Berbayar</span>';
            
            historyHTML += `
                <tr>
                    <td>${item.tanggal_formatted}</td>
                    <td><small>${item.kode_penerimaan}</small></td>
                    <td><small>${item.nomor_po || '-'}</small></td>
                    <td class="text-end fw-medium">${item.harga_formatted}</td>
                    <td><small>${diskonText}</small></td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
        
        historyHTML += `
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan ${data.history.length} transaksi terakhir
                </small>
            </div>
        `;
        
        priceHistoryContent.innerHTML = historyHTML;
    }
    
    function suggestLastPrice(productId) {
        if (!productId) return;
        
        const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
        if (!priceSuggestionContainer) return;

        clearTimeout(window.priceSuggestionTimeout);
        priceSuggestionContainer.innerHTML = '';

        fetch(`/penerimaan/price-history/${productId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(parseJsonResponse)
            .then(data => {
                if (data.success && data.history.length > 0) {
                    const lastTransaction = data.history[0];
                    
                    if (!hargaInput.value && !isFreeCheckbox.checked && !lastTransaction.is_free) {
                        const suggestionDiv = document.createElement('div');
                        suggestionDiv.id = 'priceSuggestion';
                        suggestionDiv.className = 'alert alert-info alert-sm mt-0 mb-0';
                        suggestionDiv.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <small>Harga terakhir: <strong>${lastTransaction.harga_formatted}</strong> (${lastTransaction.tanggal_formatted})</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="window.applySuggestedPrice(${lastTransaction.harga})">
                                    Gunakan
                                </button>
                            </div>
                        `;
                        
                        priceSuggestionContainer.appendChild(suggestionDiv);
                        
                        window.priceSuggestionTimeout = setTimeout(() => {
                            priceSuggestionContainer.innerHTML = '';
                        }, 5000);
                    }
                }
            })
            .catch(error => {
                console.error('Error suggesting price:', error);
            });
    }
    
    window.applySuggestedPrice = function(price) {
        hargaInput.value = price;
        
        const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
        if (priceSuggestionContainer) {
            priceSuggestionContainer.innerHTML = '';
        }
        
        hargaInput.classList.add('flash-success');
        setTimeout(() => {
            hargaInput.classList.remove('flash-success');
        }, 750);
        
        qtyInput.focus();
    };
    
    // Function to reset all inputs after adding an item
    function resetAllInputs() {
        barangTomSelect.clear();
        satuanTomSelect.clear();
        qtyInput.value = '';
        hargaInput.value = '';
        diskonPersenInputs.forEach(input => input.value = '');
        diskonNominalInputs.forEach(input => input.value = '');
        isFreeCheckbox.checked = false;
        
        // Reset states
        hargaInput.removeAttribute('readonly');
        diskonPersenInputs.forEach(input => input.removeAttribute('readonly'));
        diskonNominalInputs.forEach(input => input.removeAttribute('readonly'));

        hargaInput.parentElement.classList.remove('text-muted');
        diskonPersenInputs.forEach(input => input.parentElement.classList.remove('text-muted'));
        diskonNominalInputs.forEach(input => input.parentElement.classList.remove('text-muted'));

        const priceHistoryContainer = document.getElementById('priceHistoryContainer');
        if (priceHistoryContainer) {
            priceHistoryContainer.classList.add('d-none');
        }

        const priceSuggestionContainer = document.getElementById('priceSuggestionContainer');
        if (priceSuggestionContainer) {
            priceSuggestionContainer.innerHTML = '';
        }
        
        // Focus back on product selection
        setTimeout(() => {
            barangTomSelect.focus();
        }, 10);
    }
    
    // Enhanced event when adding new item
    if (btnTambahBarang) {
        const originalClickHandler = btnTambahBarang.onclick;
        btnTambahBarang.onclick = null;
        
        btnTambahBarang.addEventListener('click', function() {
            // Validate inputs
            let isValid = true;
            
            if (!barangTomSelect.getValue()) {
                isValid = false;
                barangTomSelect.focus();
                return;
            }
            
            // Validation with safe parsing
            const qtyVal = qtyInput.value ? parseFloat(qtyInput.value) : 0;
            if (!qtyInput.value || isNaN(qtyVal) || qtyVal <= 0) {
                qtyInput.classList.add('is-invalid');
                isValid = false;
                qtyInput.focus();
                return;
            } else {
                qtyInput.classList.remove('is-invalid');
            }
            
            if (!satuanTomSelect.getValue()) {
                isValid = false;
                satuanTomSelect.focus();
                return;
            }
            
            if (!isFreeCheckbox.checked) {
                const hargaVal = hargaInput.value ? parseFloat(hargaInput.value) : 0;
                if (!hargaInput.value || isNaN(hargaVal) || hargaVal <= 0) {
                    hargaInput.classList.add('is-invalid');
                    isValid = false;
                    hargaInput.focus();
                    return;
                } else {
                    hargaInput.classList.remove('is-invalid');
                }
            }
            
            if (!isValid) {
                return;
            }
            
            // Add success feedback
            btnTambahBarang.classList.add('btn-success');
            btnTambahBarang.classList.remove('btn-primary');
            btnTambahBarang.innerHTML = '<i class="fas fa-check me-1"></i> Ditambahkan';
            setTimeout(() => {
                btnTambahBarang.classList.remove('btn-success');
                btnTambahBarang.classList.add('btn-primary');
                btnTambahBarang.innerHTML = '<i class="fas fa-plus me-1"></i> Tambah Barang';
            }, 1000);
            
            // Hide empty row with animation if visible
            if (emptyRow.style.display !== 'none') {
                emptyRow.style.opacity = '0';
                setTimeout(() => {
                    emptyRow.style.display = 'none';
                }, 300);
            }
            
            // Get selected values - handle null/empty safely
            const barangId = barangTomSelect.getValue();
            const barangItem = barangTomSelect.getItem(barangId);
            const barangText = barangItem ? barangItem.textContent : '';
            
            const qtyValue = qtyInput.value ? parseFloat(qtyInput.value) : 0;
            const qty = isNaN(qtyValue) || qtyValue <= 0 ? 0 : qtyValue;
            
            const satuanId = satuanTomSelect.getValue();
            const satuanItem = satuanTomSelect.getItem(satuanId);
            const satuanText = satuanItem ? satuanItem.textContent : '';
            
            const hargaValue = hargaInput.value ? parseFloat(hargaInput.value) : 0;
            const harga = isNaN(hargaValue) ? 0 : hargaValue;
            
            const isFree = isFreeCheckbox.checked;
            
            // Get all discount values - handle null/empty/NaN safely
            const diskonPersenValues = diskonPersenInputs.map(input => {
                const val = input && input.value ? parseFloat(input.value) : 0;
                return isNaN(val) ? 0 : val;
            });
            const diskonNominalValues = diskonNominalInputs.map(input => {
                const val = input && input.value ? parseFloat(input.value) : 0;
                return isNaN(val) ? 0 : val;
            });
            
            // Calculate subtotal considering all discount levels with 2 decimal precision
            let subtotal = Math.round((qty * harga) * 100) / 100;
            if (!isFree) {
                // Apply all discount levels sequentially
                for (let i = 0; i < 5; i++) {
                    if (diskonPersenValues[i] > 0) {
                        subtotal = Math.round((subtotal - (subtotal * diskonPersenValues[i] / 100)) * 100) / 100;
                    } else if (diskonNominalValues[i] > 0) {
                        subtotal = Math.round((subtotal - diskonNominalValues[i]) * 100) / 100;
                    }
                }
            } else {
                subtotal = 0;
            }
            
            // Create new row
            const newRow = document.createElement('tr');
            newRow.id = `item-${counter}`;
            newRow.className = 'item-row';
            
            // Create discount badges display
            let discountBadgesHTML = '';
            for (let i = 0; i < 5; i++) {
                if (diskonPersenValues[i] > 0) {
                    discountBadgesHTML += `<span class="badge bg-info text-dark rounded-pill me-1">D${i+1}: ${diskonPersenValues[i]}%</span>`;
                } else if (diskonNominalValues[i] > 0) {
                    discountBadgesHTML += `<span class="badge bg-info text-dark rounded-pill me-1">D${i+1}: Rp ${formatRupiah(diskonNominalValues[i])}</span>`;
                }
            }
            
            if (discountBadgesHTML === '' && !isFree) {
                discountBadgesHTML = '-';
            }
            
            // Add row content with improved styling - NO HIDDEN INPUTS
            newRow.innerHTML = `
                <td class="ps-4">
                    <p class="fw-medium mb-0">${barangText}</p>
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">${qty}</span>
                </td>
                <td class="text-center">
                    ${satuanText}
                </td>
                <td class="text-end">
                    ${isFree ? '<span class="badge bg-secondary rounded-pill">Free</span>' : `Rp ${formatRupiah(harga)}`}
                </td>
                <td class="text-end">
                    ${isFree ? '<span class="badge bg-secondary rounded-pill">Free</span>' : discountBadgesHTML}
                </td>
                <td class="text-end">
                    ${isFree ? '<span class="badge bg-secondary rounded-pill">Free</span>' : `Rp ${formatRupiah(subtotal)}`}
                </td>
                <td class="text-center pe-4">
                    <button type="button" class="btn-delete" data-id="${counter}" title="Hapus item">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            // Add to table with animation
            newRow.style.opacity = '0';
            tabelDetailBarang.querySelector('tbody').appendChild(newRow);
            setTimeout(() => {
                newRow.style.opacity = '1';
            }, 10);
            
            // Store item in array with ALL data (no hidden inputs needed)
            detailItems.push({
                id: counter,
                barang_id: barangId ? (isNaN(parseInt(barangId)) ? 0 : parseInt(barangId)) : 0,
                barangText: barangText || '',
                qty: qty || 0,
                satuan_id: satuanId ? (isNaN(parseInt(satuanId)) ? 0 : parseInt(satuanId)) : 0,
                satuanText: satuanText || '',
                harga_hpp: harga || 0,
                diskon_persen_1: diskonPersenValues[0] || 0,
                diskon_persen_2: diskonPersenValues[1] || 0,
                diskon_persen_3: diskonPersenValues[2] || 0,
                diskon_persen_4: diskonPersenValues[3] || 0,
                diskon_persen_5: diskonPersenValues[4] || 0,
                diskon_nominal_1: diskonNominalValues[0] || 0,
                diskon_nominal_2: diskonNominalValues[1] || 0,
                diskon_nominal_3: diskonNominalValues[2] || 0,
                diskon_nominal_4: diskonNominalValues[3] || 0,
                diskon_nominal_5: diskonNominalValues[4] || 0,
                is_free: isFree ? 1 : 0,
                subtotal: subtotal || 0,
                catatan: null
            });
            
            // Increment counter
            counter++;
            
            // Calculate and update total harga with animation
            updateTotalHarga(true);
            
            // Reset form inputs
            resetAllInputs();
        });
    }
    
    // Enhanced function to update total amount with animation
    function updateTotalHarga(animate = false) {
        let total = 0;
        detailItems.forEach(item => {
            total += parseFloat(item.subtotal);
        });
        
        const totalHargaDisplay = document.getElementById('totalHargaDisplay');
        const oldValue = totalHargaDisplay.getAttribute('data-value') || 0;
        
            if (totalHargaDisplay) {
                if (animate && oldValue != total) {
                    totalHargaDisplay.classList.add('animate-value');
                    setTimeout(() => {
                        totalHargaDisplay.textContent = `Rp ${formatRupiahTotal(total)}`;
                        totalHargaDisplay.setAttribute('data-value', total);
                        setTimeout(() => {
                            totalHargaDisplay.classList.remove('animate-value');
                        }, 300);
                    }, 200);
                } else {
                    totalHargaDisplay.textContent = `Rp ${formatRupiahTotal(total)}`;
                    totalHargaDisplay.setAttribute('data-value', total);
                }
            }
        
        // Add hidden input for total
        let totalHargaInput = document.querySelector('input[name="total_harga"]');
        if (!totalHargaInput) {
            totalHargaInput = document.createElement('input');
            totalHargaInput.type = 'hidden';
            totalHargaInput.name = 'total_harga';
            formPenerimaan.appendChild(totalHargaInput);
        }
        totalHargaInput.value = total;
    }
    
    // Enhanced event delegation for delete buttons with animation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete')) {
            const btn = e.target.closest('.btn-delete');
            const id = btn.getAttribute('data-id');
            const row = document.getElementById(`item-${id}`);
            
            // Add deleting class for animation
            row.classList.add('deleting');
            
            // Add visual feedback to delete button
            btn.classList.add('active');
            
            // Delayed removal
            setTimeout(() => {
                // Remove from table
                row.remove();
                
                // Remove from array
                detailItems = detailItems.filter(item => item.id != id);
                
                // Show empty row if no items with animation
                if (detailItems.length === 0) {
                    emptyRow.style.display = '';
                    emptyRow.style.opacity = '0';
                    setTimeout(() => {
                        emptyRow.style.opacity = '1';
                    }, 10);
                }
                
                // Update total with animation
                updateTotalHarga(true);
            }, 300);
        }
    });
    
    // Enhanced diskon fields interaction
    if (diskonPersenInputs.length > 0 && diskonNominalInputs.length > 0 && hargaInput) {
        diskonPersenInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value) {
                    // Visual feedback for active discount
                    this.parentElement.classList.add('active-discount');
                    
                    // Clear and disable the other discount field
                    diskonNominalInputs.forEach(otherInput => {
                        if (otherInput !== this) {
                            otherInput.value = '';
                            otherInput.setAttribute('disabled', 'disabled');
                            otherInput.parentElement.classList.remove('active-discount');
                        }
                    });
                } else {
                    // Remove active styling and enable the other field
                    this.parentElement.classList.remove('active-discount');
                    diskonNominalInputs.forEach(otherInput => otherInput.removeAttribute('disabled'));
                }
            });
        });
        
        diskonNominalInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value) {
                    // Visual feedback for active discount
                    this.parentElement.classList.add('active-discount');
                    
                    // Clear and disable the other discount field
                    diskonPersenInputs.forEach(otherInput => {
                        if (otherInput !== this) {
                            otherInput.value = '';
                            otherInput.setAttribute('disabled', 'disabled');
                            otherInput.parentElement.classList.remove('active-discount');
                        }
                    });
                } else {
                    // Remove active styling and enable the other field
                    this.parentElement.classList.remove('active-discount');
                    diskonPersenInputs.forEach(otherInput => otherInput.removeAttribute('disabled'));
                }
            });
        });
    }
    
    // Find submit button - try multiple selectors
    let submitButton = document.querySelector('#formPenerimaan button[type="submit"]');
    if (!submitButton) {
        submitButton = formPenerimaan.querySelector('button[type="submit"]');
    }
    if (!submitButton) {
        submitButton = document.querySelector('form button[type="submit"]');
    }
    
    console.log('Submit button found:', !!submitButton);
    console.log('Form found:', !!formPenerimaan);
    
    // AJAX Form submission - Full JSON approach
    async function handleSubmit(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (detailItems.length === 0) {
            alert('Mohon tambahkan minimal satu barang sebelum menyimpan.');
            if (btnTambahBarang) {
                btnTambahBarang.classList.add('btn-danger');
                setTimeout(() => {
                    btnTambahBarang.classList.remove('btn-danger');
                    btnTambahBarang.classList.add('btn-primary');
                }, 1000);
            }
            return false;
        }
        
        if (!submitButton) {
            console.error('Submit button not found!');
            alert('Terjadi kesalahan: Tombol simpan tidak ditemukan.');
            return false;
        }
        
        const originalButtonText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
        submitButton.disabled = true;
        
        try {
            // Step 1: POST /penerimaan/create-header
            const tanggalJatuhTempoInput = document.getElementById('tanggal_jatuh_tempo');
            const metodePembayaran = document.getElementById('metode_pembayaran').value;
            
            // Handle tanggal_jatuh_tempo - hanya kirim jika metode pembayaran adalah "Jatuh Tempo" dan value ada
            let tanggalJatuhTempo = null;
            if (metodePembayaran === 'Jatuh Tempo' && tanggalJatuhTempoInput && tanggalJatuhTempoInput.value) {
                tanggalJatuhTempo = tanggalJatuhTempoInput.value;
            }
            
            const headerData = {
                main_category_id: document.getElementById('main_category_id').value,
                tax_category_id: document.getElementById('tax_category_id').value,
                kode_penerimaan: document.getElementById('kode_penerimaan').value,
                nomor_po: document.getElementById('nomor_po').value,
                tanggal_penerimaan: document.getElementById('tanggal_penerimaan').value,
                metode_pembayaran: metodePembayaran,
                tanggal_jatuh_tempo: tanggalJatuhTempo,
                catatan: (document.getElementById('catatan').value || '').trim() || null
            };
            
            const headerResponse = await fetch('{{ route("penerimaan.create-header") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(headerData)
            });
            
            const headerResult = await parseJsonResponse(headerResponse);
            
            if (!headerResult.success) {
                throw new Error(headerResult.message || 'Gagal membuat header penerimaan');
            }

            if (headerResult.kode_penerimaan) {
                const kodeInput = document.getElementById('kode_penerimaan');
                if (kodeInput) {
                    kodeInput.value = headerResult.kode_penerimaan;
                }
            }
            
            const penerimaanId = headerResult.penerimaan_id;
            
            // Step 2: POST /penerimaan/{id}/store-batch-details dengan body JSON { items: detailItems }
            const detailsData = {
                items: detailItems.map(item => ({
                    barang_id: item.barang_id,
                    qty: item.qty,
                    satuan_id: item.satuan_id,
                    harga_hpp: item.harga_hpp,
                    diskon_persen_1: item.diskon_persen_1,
                    diskon_persen_2: item.diskon_persen_2,
                    diskon_persen_3: item.diskon_persen_3,
                    diskon_persen_4: item.diskon_persen_4,
                    diskon_persen_5: item.diskon_persen_5,
                    diskon_nominal_1: item.diskon_nominal_1,
                    diskon_nominal_2: item.diskon_nominal_2,
                    diskon_nominal_3: item.diskon_nominal_3,
                    diskon_nominal_4: item.diskon_nominal_4,
                    diskon_nominal_5: item.diskon_nominal_5,
                    is_free: item.is_free,
                    catatan: item.catatan
                }))
            };
            
            const detailsResponse = await fetch(`/penerimaan/${penerimaanId}/store-batch-details`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(detailsData)
            });
            
            const detailsResult = await parseJsonResponse(detailsResponse);
            
            if (!detailsResult.success) {
                throw new Error(detailsResult.message || 'Gagal menyimpan detail penerimaan');
            }
            
            // Step 3: POST /penerimaan/{id}/finalize
            const finalizeResponse = await fetch(`/penerimaan/${penerimaanId}/finalize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            });
            
            const finalizeResult = await parseJsonResponse(finalizeResponse);
            
            if (!finalizeResult.success) {
                throw new Error(finalizeResult.message || 'Gagal finalisasi penerimaan');
            }
            
            // Success - redirect
            window.location.href = '{{ route("penerimaan.index", ["success" => 1]) }}';
            
        } catch (error) {
            console.error('Error:', error);
            alert('Terjadi kesalahan: ' + error.message);
            if (submitButton) {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        }
    }
    
    // Attach event listeners - both submit and click
    if (formPenerimaan) {
        formPenerimaan.addEventListener('submit', handleSubmit);
        console.log('Form submit listener attached');
    } else {
        console.error('Form not found!');
    }
    
    if (submitButton) {
        submitButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Submit button clicked');
            handleSubmit(e);
        });
        console.log('Submit button click listener attached');
    } else {
        console.error('Submit button not found! Check selector or button exists in DOM');
    }
});
</script>

<style>
/* Animation classes for visual feedback */
.flash-highlight {
    animation: flashHighlight 0.75s ease;
}

.flash-success {
    animation: flashSuccess 0.75s ease;
}

.flash-error {
    animation: flashError 0.75s ease;
}

.animate-value {
    animation: pulseValue 0.5s ease;
}

.active-discount {
    border-color: #4a6cf7 !important;
    background-color: rgba(74, 108, 247, 0.05);
}

.deleting {
    animation: fadeOut 0.3s ease-in forwards;
}

@keyframes flashHighlight {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(74, 108, 247, 0.1); }
}

@keyframes flashSuccess {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(25, 135, 84, 0.1); }
}

@keyframes flashError {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(220, 53, 69, 0.1); }
}

@keyframes pulseValue {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
@endpush
