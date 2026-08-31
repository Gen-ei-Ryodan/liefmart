@extends('layouts.app')

@section('title', 'Edit Retur Penjualan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Retur Penjualan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('retur-penjualan.index') }}" class="text-decoration-none">Online Sales Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('retur-penjualan.show', $returPenjualan->id) }}" class="btn btn-outline-primary rounded-pill px-4">
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

                    <form action="{{ route('retur-penjualan.update', $returPenjualan->id) }}" method="POST" id="returForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_id">Order:</label>
                                    <select name="order_id" id="order_id" class="form-control @error('order_id') is-invalid @enderror" required>
                                        @foreach($orderList as $order)
                                        <option value="{{ $order->id }}" {{ $returPenjualan->order_id == $order->id ? 'selected' : '' }}>
                                            {{ $order->order_number }} - {{ $order->platform->name ?? 'Tidak ada platform' }} - {{ $order->tanggal->format('d/m/Y') }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('order_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tanggal_retur">Tanggal Retur:</label>
                                    <input type="date" name="tanggal_retur" id="tanggal_retur" class="form-control @error('tanggal_retur') is-invalid @enderror" value="{{ old('tanggal_retur', $returPenjualan->tanggal_retur->format('Y-m-d')) }}" required>
                                    @error('tanggal_retur')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="catatan">Catatan:</label>
                            <textarea name="catatan" id="catatan" class="form-control @error('catatan') is-invalid @enderror" rows="3">{{ old('catatan', $returPenjualan->catatan) }}</textarea>
                            @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>
                        <h5>Detail Barang</h5>
                        
                        <div id="detail-container" class="mt-3">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="detail-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Barang</th>
                                            <th>Qty Retur</th>
                                            <th>Status Barang</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($returPenjualan->order->orderItems as $orderItem)
                                            @foreach($orderItem->platformProduct->mappings ?? [] as $mapping)
                                                @php
                                                    $detail = $returPenjualan->details
                                                        ->where('order_item_id', $orderItem->id)
                                                        ->where('product_id', $mapping->product_id)
                                                        ->first();
                                                    
                                                    $index = $loop->parent->index . '_' . $loop->index;
                                                @endphp
                                                <tr>
                                                    <td>{{ $mapping->product ? $mapping->product->name : 'Product tidak ditemukan (ID: ' . $mapping->product_id . ')' }}</td>
                                                    <td>
                                                        <input type="hidden" name="details[{{ $index }}][order_item_id]" value="{{ $orderItem->id }}">
                                                        <input type="hidden" name="details[{{ $index }}][product_id]" value="{{ $mapping->product_id }}">
                                                        <input type="number" name="details[{{ $index }}][qty]" class="form-control form-control-sm qty-input" min="0" max="{{ $orderItem->quantity }}" step="1" value="{{ $detail ? $detail->qty : 0 }}">
                                                    </td>
                                                    <td>
                                                        <select name="details[{{ $index }}][kondisi]" class="form-control form-control-sm">
                                                            <option value="BAGUS" {{ $detail && $detail->kondisi == 'BAGUS' ? 'selected' : '' }}>BAGUS (Masuk Warehouse)</option>
                                                            <option value="RUSAK" {{ $detail && $detail->kondisi == 'RUSAK' ? 'selected' : '' }}>RUSAK (Masuk Warehouse Rusak)</option>
                                                            <option value="HILANG" {{ $detail && $detail->kondisi == 'HILANG' ? 'selected' : '' }}>HILANG (Tidak Masuk Warehouse)</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="details[{{ $index }}][alasan]" class="form-control form-control-sm" placeholder="Alasan retur" value="{{ $detail ? $detail->alasan : '' }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Disable order selection change to avoid confusion
        $('#order_id').on('change', function(e) {
            // If user tries to change, revert to original value
            $(this).val('{{ $returPenjualan->order_id }}');
            alert('Tidak dapat mengganti Order pada retur yang sudah dibuat. Buat retur baru jika ingin meretur Order yang berbeda.');
        });
        
        // Form submission validation
        $('#returForm').submit(function(e) {
            // Check if any items have a quantity > 0
            let anyItemsSelected = false;
            $('.qty-input').each(function() {
                if (parseFloat($(this).val()) > 0) {
                    anyItemsSelected = true;
                    return false; // Break the loop
                }
            });
            
            if (!anyItemsSelected) {
                e.preventDefault();
                alert('Anda harus memasukkan jumlah retur minimal 1 barang');
                return false;
            }
            
            return true;
        });
    });
</script>
@endpush 