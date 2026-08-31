@extends('layouts.app')

@section('title', 'Buat Retur Pembelian')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Buat Retur Pembelian Baru</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('retur-pembelian.index') }}" class="text-decoration-none">Purchase Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Buat Baru</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('retur-pembelian.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-times me-2"></i> Batal
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

                    <form action="{{ route('retur-pembelian.store') }}" method="POST" id="returForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="penerimaan_id">Pilih Penerimaan (PO):</label>
                                    <select name="penerimaan_id" id="penerimaan_id" class="form-control select2 @error('penerimaan_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih Penerimaan --</option>
                                        @foreach($penerimaanList as $penerimaan)
                                        <option value="{{ $penerimaan->id }}">
                                            {{ $penerimaan->kode_penerimaan }} - {{ $penerimaan->nomor_po }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('penerimaan_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal_retur">Tanggal Retur:</label>
                                    <input type="date" name="tanggal_retur" id="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" value="{{ old('tanggal_retur') }}" required>
                                    @error('tanggal_retur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="catatan">Catatan:</label>
                            <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="3">{{ old('catatan') }}</textarea>
                            @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div id="penerimaan-info" class="d-none">
                            <div class="card card-outline card-info">
                        <div class="ds-card-header">
                            <h5 class="card-title">Informasi Penerimaan</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-borderless table-sm">
                                                <tr>
                                                    <td width="150">Nomor PO</td>
                                                    <td width="10">:</td>
                                                    <td id="info-nomor-po">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Tanggal Penerimaan</td>
                                                    <td>:</td>
                                                    <td id="info-tanggal">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Kategori</td>
                                                    <td>:</td>
                                                    <td id="info-kategori">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless table-sm">
                                                <tr>
                                                    <td width="150">Status</td>
                                                    <td width="10">:</td>
                                                    <td id="info-status">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Lokasi</td>
                                                    <td>:</td>
                                                    <td id="info-lokasi">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Total Harga</td>
                                                    <td>:</td>
                                                    <td id="info-total">-</td>
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
                                            <th>ID Penerimaan</th>
                                            <th>Qty Diterima</th>
                                            <th>Stok Tersedia</th>
                                            <th>Tanggal ED</th>
                                            <th>Satuan</th>
                                            <th>Qty Retur</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-container">
                                        <tr>
                                            <td colspan="8" class="text-center">Pilih Penerimaan (PO) terlebih dahulu</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle mr-2"></i> Anda dapat memilih stok spesifik yang akan diretur berdasarkan penerimaan detail dan tanggal ED.
                            </div>
                        </div>

                        <div class="form-group mt-4 text-right">
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submit-btn" disabled>
                                <i class="fas fa-save me-2"></i> Simpan Retur
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
    .stock-warning {
        color: #dc3545;
        font-weight: bold;
    }
    .stock-available {
        color: #28a745;
        font-weight: bold;
    }
</style>
@endpush

@push('scripts')
<!-- Tambahkan jQuery terlebih dahulu -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<!-- Setelah jQuery baru load select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Pastikan jQuery tersedia
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery === 'undefined') {
            console.error('jQuery tidak ditemukan! Pastikan jQuery dimuat sebelum script ini.');
            return;
        }
        
        jQuery(function($) {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap4',
                placeholder: 'Pilih Penerimaan (PO)',
                allowClear: true,
                width: '100%'
            });

            $('#penerimaan_id').change(function() {
                const penerimaanId = $(this).val();
                
                if (penerimaanId) {
                    // Enable the submit button
                    $('#submit-btn').prop('disabled', false);
                    
                    // Show loading indicator
                    $('#items-container').html('<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</td></tr>');
                    
                    // Fetch penerimaan details
                    $.ajax({
                        url: "{{ url('retur-pembelian/get-penerimaan') }}/" + penerimaanId,
                        type: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            let html = '';
                            
                            // Update PO information
                            $('#info-nomor-po').text(response.nomor_po);
                            $('#info-tanggal').text(response.tanggal_penerimaan ? new Date(response.tanggal_penerimaan).toLocaleDateString('id-ID') : '-');
                            $('#info-kategori').text(response.main_category ? (response.main_category.name || '-') : '-');
                            $('#info-status').html(getStatusBadge(response.status));
                            $('#info-lokasi').text(response.lokasi ? response.lokasi.name : '-');
                            $('#info-total').text(formatRupiah(response.total_harga));
                            
                            // Show PO information
                            $('#penerimaan-info').removeClass('d-none');
                            
                            if (response.details && response.details.length > 0) {
                                let index = 0;
                                
                                response.details.forEach(function(detail) {
                                    // Check if this detail has any warehouse stocks
                                    if (detail.warehouse_stocks && detail.warehouse_stocks.length > 0) {
                                        // Create a row for each warehouse stock of this detail
                                        detail.warehouse_stocks.forEach(function(stock) {
                                            const stockClass = stock.qty > 0 ? 'stock-available' : 'stock-warning';
                                            const stockDisplay = `<span class="${stockClass}">${stock.qty}</span>`;
                                            const edDate = stock.expired_date ? new Date(stock.expired_date).toLocaleDateString('id-ID') : 'Tidak Ada';
                                            
                                            html += `
                                            <tr>
                                                <td>${detail.product.name}</td>
                                                <td>PD#${detail.id}</td>
                                                <td>${detail.qty}</td>
                                                <td>${stockDisplay}</td>
                                                <td>${edDate}</td>
                                                <td>${detail.satuan ? detail.satuan.name : '-'}</td>
                                                <td>
                                                    <input type="hidden" name="details[${index}][penerimaan_detail_id]" value="${detail.id}">
                                                    <input type="hidden" name="details[${index}][product_id]" value="${detail.product_id}">
                                                    <input type="hidden" name="details[${index}][satuan_id]" value="${detail.satuan_id}">
                                                    <input type="hidden" name="details[${index}][warehouse_stock_id]" value="${stock.id}">
                                                    <input type="number" name="details[${index}][qty]" class="form-control form-control-sm qty-input" min="0" max="${stock.qty}" step="0.01" value="0" data-index="${index}" ${stock.qty <= 0 ? 'disabled' : ''}>
                                                </td>
                                                <td>
                                                    <input type="text" name="details[${index}][alasan]" class="form-control form-control-sm alasan-input" placeholder="Alasan retur" data-index="${index}" ${stock.qty <= 0 ? 'disabled' : ''}>
                                                </td>
                                            </tr>
                                            `;
                                            index++;
                                        });
                                    } else {
                                        // No warehouse stocks for this detail
                                        html += `
                                        <tr>
                                            <td>${detail.product.name}</td>
                                            <td>PD#${detail.id}</td>
                                            <td>${detail.qty}</td>
                                            <td><span class="stock-warning">0</span></td>
                                            <td>-</td>
                                            <td>${detail.satuan ? detail.satuan.name : '-'}</td>
                                            <td colspan="2" class="text-center">
                                                <span class="text-muted">Tidak ada stok tersedia</span>
                                            </td>
                                        </tr>
                                        `;
                                    }
                                });
                            } else {
                                html = '<tr><td colspan="8" class="text-center">Tidak ada detail penerimaan</td></tr>';
                            }
                            
                            $('#items-container').html(html);
                        },
                        error: function(xhr) {
                            alert('Terjadi kesalahan saat mengambil data penerimaan');
                            console.error(xhr.responseText);
                            $('#items-container').html('<tr><td colspan="8" class="text-center">Error: Gagal memuat data</td></tr>');
                        }
                    });
                } else {
                    // Disable the submit button when no penerimaan is selected
                    $('#submit-btn').prop('disabled', true);
                    $('#penerimaan-info').addClass('d-none');
                    $('#items-container').html('<tr><td colspan="8" class="text-center">Pilih Penerimaan (PO) terlebih dahulu</td></tr>');
                }
            });
            
            // Form submission validation
            $('#returForm').submit(function(e) {
                // Check if penerimaan is selected
                if (!$('#penerimaan_id').val()) {
                    e.preventDefault();
                    alert('Anda harus memilih Penerimaan (PO) terlebih dahulu!');
                    return false;
                }
                
                // Check if tanggal_retur is filled
                if (!$('#tanggal_retur').val()) {
                    e.preventDefault();
                    alert('Tanggal retur harus diisi!');
                    return false;
                }
                
                // Check if any items have a quantity > 0
                let anyItemsSelected = false;
                let totalItems = $('.qty-input').length;
                let emptyItems = 0;
                
                $('.qty-input').each(function() {
                    if (parseFloat($(this).val()) <= 0 || !$(this).val()) {
                        emptyItems++;
                    } else {
                        anyItemsSelected = true;
                        
                        // Check if alasan is provided for items with qty > 0
                        let index = $(this).data('index');
                        let alasan = $('input[name="details[' + index + '][alasan]"]').val();
                        
                        if (!alasan) {
                            e.preventDefault();
                            alert('Alasan retur harus diisi untuk semua item yang diretur!');
                            return false;
                        }
                    }
                });
                
                if (totalItems === 0) {
                    e.preventDefault();
                    alert('Tidak ada item yang dapat diretur. Silakan pilih Penerimaan (PO) terlebih dahulu!');
                    return false;
                }
                
                if (!anyItemsSelected) {
                    e.preventDefault();
                    alert('Anda harus memasukkan jumlah retur minimal 1 barang!');
                    return false;
                }
                
                // Confirmation message
                if (!confirm('Apakah Anda yakin ingin membuat retur pembelian ini?')) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Helper functions
            function getStatusBadge(status) {
                if (status === 'Located') {
                    return '<span class="badge badge-success">Located</span>';
                } else if (status === 'Unlocated') {
                    return '<span class="badge badge-warning">Unlocated</span>';
                } else if (status === 'dibatalkan') {
                    return '<span class="badge badge-danger">Dibatalkan</span>';
                } else {
                    return status;
                }
            }
            
            function formatRupiah(angka) {
                if (!angka) return 'Rp 0';
                
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0
                }).format(angka);
            }
        });
    });
</script>
@endpush 