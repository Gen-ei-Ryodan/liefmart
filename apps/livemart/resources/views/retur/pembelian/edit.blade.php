@extends('layouts.app')

@section('title', 'Edit Retur Pembelian')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Retur Pembelian</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('retur-pembelian.index') }}" class="text-decoration-none">Purchase Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('retur-pembelian.show', $returPembelian->id) }}" class="btn btn-outline-primary rounded-pill px-4">
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

                    <form action="{{ route('retur-pembelian.update', $returPembelian->id) }}" method="POST" id="returForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="card card-outline card-info mb-4">
                        <div class="ds-card-header">
                            <h5 class="card-title">Informasi Penerimaan (PO)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <td width="150">Nomor PO</td>
                                                <td width="10">:</td>
                                                <td>{{ $returPembelian->penerimaan->nomor_po }}</td>
                                            </tr>
                                            <tr>
                                                <td>Tanggal Penerimaan</td>
                                                <td>:</td>
                                                <td>{{ $returPembelian->penerimaan->tanggal_penerimaan->format('d/m/Y') }}</td>
                                            </tr>
                                            <tr>
                                                <td>Kategori</td>
                                                <td>:</td>
                                                <td>{{ $returPembelian->penerimaan->mainCategory->name ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless table-sm">
                                            <tr>
                                                <td width="150">Status</td>
                                                <td width="10">:</td>
                                                <td>
                                                    @if($returPembelian->penerimaan->status == 'Located')
                                                    <span class="badge badge-success">Located</span>
                                                    @elseif($returPembelian->penerimaan->status == 'Unlocated')
                                                    <span class="badge badge-warning">Unlocated</span>
                                                    @else
                                                    {{ $returPembelian->penerimaan->status }}
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Lokasi</td>
                                                <td>:</td>
                                                <td>{{ $returPembelian->penerimaan->lokasi->nama ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="tanggal_retur">Tanggal Retur:</label>
                                    <input type="date" name="tanggal_retur" id="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" value="{{ old('tanggal_retur', $returPembelian->tanggal_retur->format('Y-m-d')) }}" required>
                                    @error('tanggal_retur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="catatan">Catatan:</label>
                            <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="3">{{ old('catatan', $returPembelian->catatan) }}</textarea>
                            @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <h5>Detail Barang</h5>
                        
                        <div id="detail-container" class="mt-3">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="detail-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Barang</th>
                                            <th>Qty Diterima</th>
                                            <th>Stok Tersedia</th>
                                            <th>Satuan</th>
                                            <th>Qty Retur</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-container">
                                        @php
                                            $itemIndex = 0;
                                        @endphp
                                        @foreach($returPembelian->details as $returDetail)
                                        @php
                                            $penerimaanDetail = $returDetail->penerimaanDetail;
                                            if (!$penerimaanDetail) continue;
                                            
                                            $item = $penerimaanDetail;
                                            // available_stock already calculated in controller
                                            $availableStock = $returDetail->available_stock ?? 0;
                                            $stockClass = $availableStock > 0 ? 'text-success font-weight-bold' : 'text-danger font-weight-bold';
                                        @endphp
                                        <tr>
                                            <td>{{ $item->product->name }}</td>
                                            <td>{{ $item->qty }}</td>
                                            <td class="{{ $stockClass }}">{{ $availableStock }}</td>
                                            <td>{{ $item->satuan ? $item->satuan->name : '-' }}</td>
                                            <td>
                                                <input type="hidden" name="details[{{ $itemIndex }}][penerimaan_detail_id]" value="{{ $item->id }}">
                                                <input type="hidden" name="details[{{ $itemIndex }}][product_id]" value="{{ $item->product_id }}">
                                                <input type="hidden" name="details[{{ $itemIndex }}][satuan_id]" value="{{ $item->satuan_id }}">
                                                <input type="number" name="details[{{ $itemIndex }}][qty]" class="form-control form-control-sm qty-input" min="0" max="{{ $availableStock }}" step="0.01" value="{{ $returDetail->qty }}" required>
                                            </td>
                                            <td>
                                                <input type="text" name="details[{{ $itemIndex }}][alasan]" class="form-control form-control-sm" placeholder="Alasan retur" value="{{ $returDetail->alasan ?? '' }}" required>
                                            </td>
                                        </tr>
                                        @php
                                            $itemIndex++;
                                        @endphp
                                        @endforeach
                                        @if($returPembelian->details->isEmpty())
                                        <tr>
                                            <td colspan="6" class="text-center">Tidak ada detail barang retur</td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle mr-2"></i> Anda dapat memilih stok spesifik yang akan diretur berdasarkan penerimaan detail dan tanggal ED.
                            </div>
                        </div>

                        <div class="form-group mt-4 text-right">
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="submit-btn">
                                <i class="fas fa-save me-2"></i> Simpan Perubahan
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
<style>
    .text-success {
        color: #28a745;
    }
    .text-danger {
        color: #dc3545;
    }
    .font-weight-bold {
        font-weight: bold;
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Form submission validation
        $('#returForm').submit(function(e) {
            // Check if any items have a quantity > 0
            let anyItemsSelected = false;
            $('.qty-input').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    anyItemsSelected = true;
                    
                    // Check if alasan is provided for items with qty > 0
                    let alasan = $(this).closest('tr').find('input[type="text"]').val();
                    
                    if (!alasan) {
                        e.preventDefault();
                        alert('Alasan retur harus diisi untuk semua item yang diretur!');
                        anyItemsSelected = false;
                        return false; // Break the loop
                    }
                }
            });
            
            if (!anyItemsSelected) {
                e.preventDefault();
                alert('Anda harus memasukkan jumlah retur minimal 1 barang');
                return false;
            }
            
            if (!confirm('Apakah Anda yakin ingin mengubah retur pembelian ini?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    });
</script>
@endpush 