@extends('layouts.app')

@section('title', 'Edit Retur Penjualan Offline')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Retur Penjualan Offline</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('retur-offline.index') }}" class="text-decoration-none">Offline Sales Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('retur-offline.show', $returOfflineSale->id) }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-body p-4">
                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    <div class="alert alert-info shadow-sm rounded-3">
                        <i class="fas fa-info-circle me-2"></i> Edit retur penjualan offline. Perubahan akan tersimpan dengan status draft sampai diproses.
                    </div>

                    <form action="{{ route('retur-offline.update', $returOfflineSale->id) }}" method="POST" id="returForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="offline_sale_id">Penjualan Offline:</label>
                                    <select name="offline_sale_id" id="offline_sale_id" class="form-control select2 @error('offline_sale_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih Penjualan Offline --</option>
                                        @foreach($offlineSaleList as $sale)
                                        <option value="{{ $sale->id }}" {{ $returOfflineSale->offline_sale_id == $sale->id ? 'selected' : '' }}>
                                            {{ $sale->surat_jalan_number }} - {{ $sale->customerInfo->name ?? $sale->customer_name ?? 'Tanpa Customer' }} - {{ $sale->sale_date->format('d/m/Y') }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('offline_sale_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal_retur">Tanggal Retur:</label>
                                    <input type="date" name="tanggal_retur" id="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" value="{{ old('tanggal_retur', $returOfflineSale->tanggal_retur->format('Y-m-d')) }}" required>
                                    @error('tanggal_retur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="catatan">Catatan:</label>
                            <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="3">{{ old('catatan', $returOfflineSale->catatan) }}</textarea>
                            @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>
                        <div id="sale-info" class="mb-3">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="mb-2">Informasi Penjualan Offline</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr>
                                                    <td width="140">Nomor Surat Jalan</td>
                                                    <td width="10">:</td>
                                                    <td id="info-sj-number">{{ $returOfflineSale->offlineSale->surat_jalan_number }}</td>
                                                </tr>
                                                <tr>
                                                    <td>Tanggal</td>
                                                    <td>:</td>
                                                    <td id="info-tanggal">{{ $returOfflineSale->offlineSale->sale_date->format('d/m/Y') }}</td>
                                                </tr>
                                                <tr>
                                                    <td>Customer</td>
                                                    <td>:</td>
                                                    <td id="info-customer">{{ $returOfflineSale->offlineSale->customerInfo->name ?? $returOfflineSale->offlineSale->customer_name ?? '-' }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr>
                                                    <td width="140">No. PO</td>
                                                    <td width="10">:</td>
                                                    <td id="info-po-number">{{ $returOfflineSale->offlineSale->No_PO ?? '-' }}</td>
                                                </tr>
                                                <tr>
                                                    <td>Jumlah Item</td>
                                                    <td>:</td>
                                                    <td id="info-item-count">{{ $returOfflineSale->offlineSale->items->count() }}</td>
                                                </tr>
                                                <tr>
                                                    <td>Total</td>
                                                    <td>:</td>
                                                    <td id="info-total">Rp {{ number_format($returOfflineSale->offlineSale->total_amount, 0, ',', '.') }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5>Detail Barang</h5>
                        
                        <div id="detail-container" class="mt-3">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="detail-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Barang</th>
                                            <th style="width: 100px; text-align: center;">Qty Order</th>
                                            <th style="width: 100px; text-align: center;">Qty Retur</th>
                                            <th style="width: 200px;">Status Barang</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-container">
                                        @foreach($returOfflineSale->offlineSale->items as $index => $item)
                                            @php
                                            $detailItem = $returOfflineSale->details->where('offline_sale_item_id', $item->id)->first();
                                            $qtyRetur = $detailItem ? $detailItem->qty : 0;
                                            $kondisi = $detailItem ? $detailItem->kondisi : 'BAGUS';
                                            $alasan = $detailItem ? $detailItem->alasan : '';
                                            @endphp
                                            <tr>
                                                <td>
                                                    {{ $item->product->name ?? 'Product tidak ditemukan' }}
                                                    <input type="hidden" name="details[{{ $index }}][offline_sale_item_id]" value="{{ $item->id }}">
                                                    <input type="hidden" name="details[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                                </td>
                                                <td class="text-center">
                                                    <span class="qty-badge">{{ $item->quantity }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" name="details[{{ $index }}][qty]" class="form-control form-control-sm qty-input" 
                                                        min="0" max="{{ $item->quantity }}" step="0.01" value="{{ $qtyRetur }}">
                                                </td>
                                                <td>
                                                    <select name="details[{{ $index }}][kondisi]" class="form-control form-control-sm">
                                                        <option value="BAGUS" {{ $kondisi == 'BAGUS' ? 'selected' : '' }}>Baik (Kembali ke Stok)</option>
                                                        <option value="RUSAK" {{ $kondisi == 'RUSAK' ? 'selected' : '' }}>Rusak (Kembali ke Stok Rusak)</option>
                                                        <option value="HILANG" {{ $kondisi == 'HILANG' ? 'selected' : '' }}>Hilang (Tidak Kembali ke Stok)</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="details[{{ $index }}][alasan]" class="form-control form-control-sm" value="{{ $alasan }}" placeholder="Alasan retur">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-group mt-4 text-right">
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submit-btn">
                                <i class="fas fa-save me-2"></i> Update Retur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css" rel="stylesheet" />

<style>
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px) !important;
    }
    .qty-badge {
        font-size: 13px;
        padding: 4px 8px;
        border-radius: 4px;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    jQuery(document).ready(function($) {
        // Initialize Select2
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih Penjualan Offline',
            allowClear: true,
            width: '100%'
        });

        // Disable the select if we already have a value
        if ($('#offline_sale_id').val()) {
            $('#offline_sale_id').prop('disabled', true);
        }
        
        // Validate form before submit
        $('#returForm').submit(function(e) {
            // Enable the select before submitting to include it in the form data
            $('#offline_sale_id').prop('disabled', false);
            
            let hasQty = false;
            $('.qty-input').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    hasQty = true;
                    return false; // break the loop
                }
            });
            
            if (!hasQty) {
                e.preventDefault();
                alert('Masukkan minimal satu item dengan quantity lebih dari 0');
                return false;
            }
            
            return true;
        });
    });
</script>
@endpush 