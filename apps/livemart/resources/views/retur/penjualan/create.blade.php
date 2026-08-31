@extends('layouts.app')

@section('title', 'Buat Retur Penjualan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Buat Retur Penjualan Baru</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('retur-penjualan.index') }}" class="text-decoration-none">Online Sales Returns</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Buat Baru</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('retur-penjualan.index') }}" class="btn btn-outline-primary rounded-pill px-4">
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

                    <div class="alert alert-info shadow-sm rounded-3">
                        <i class="fas fa-info-circle me-2"></i> Retur penjualan akan mengurangi jumlah barang pada order dan mengembalikannya ke stok gudang (jika barang tidak rusak).
                    </div>

                    <form action="{{ route('retur-penjualan.store') }}" method="POST" id="returForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_id">Pilih Order:</label>
                                    <select name="order_id" id="order_id" class="form-control select2 @error('order_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih Order --</option>
                                        @foreach($orderList as $order)
                                        <option value="{{ $order->id }}">
                                            {{ $order->order_number ?: 'Tanpa No Order' }} - {{ $order->platform->name ?? 'Tidak ada platform' }} - {{ optional($order->tanggal)->format('d/m/Y') }}
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

                        <hr>
                        <div id="order-info" class="mb-3 d-none">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="mb-2">Informasi Order</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr>
                                                    <td width="140">Nomor Order</td>
                                                    <td width="10">:</td>
                                                    <td id="info-order-number">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Tanggal</td>
                                                    <td>:</td>
                                                    <td id="info-tanggal">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Platform</td>
                                                    <td>:</td>
                                                    <td id="info-platform">-</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr>
                                                    <td width="140">Status</td>
                                                    <td width="10">:</td>
                                                    <td id="info-status">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Jumlah Item</td>
                                                    <td>:</td>
                                                    <td id="info-item-count">-</td>
                                                </tr>
                                                <tr>
                                                    <td>Hari</td>
                                                    <td>:</td>
                                                    <td id="info-hari">-</td>
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
                                            <th>Variasi</th>
                                            <th style="width: 100px; text-align: center;">Qty Order</th>
                                            <th style="width: 100px; text-align: center;">Qty Retur</th>
                                            <th style="width: 200px;">Status Barang</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-container">
                                        <tr>
                                            <td colspan="6" class="text-center">Pilih Order terlebih dahulu</td>
                                        </tr>
                                    </tbody>
                                </table>
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
        $('#order_id').select2({
            theme: 'bootstrap4',
            placeholder: 'Pilih Order',
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: "{{ route('retur-penjualan.search-orders') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return data;
                },
                cache: true
            }
        });

        $('#order_id').change(function() {
            const orderId = $(this).val();
            
            if (orderId) {
                // Enable the submit button
                $('#submit-btn').prop('disabled', false);
                
                // Show loading indicator
                $('#items-container').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</td></tr>');
                
                // Fetch order details
                $.ajax({
                    url: "{{ url('retur-penjualan/get-order') }}/" + orderId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        console.log('API Response:', response);
                        let html = '';
                        
                        // Update Order information
                        const orderNumberDisplay = response.order_number || response.orderNumber || 'Tanpa No Order';
                        $('#info-order-number').text(orderNumberDisplay);
                        $('#info-tanggal').text(response.tanggal ? new Date(response.tanggal).toLocaleDateString('id-ID') : '-');
                        
                        // Handle both camelCase and snake_case property names for platform
                        const platform = response.platform || response.platform_data || null;
                        $('#info-platform').text(platform ? platform.name : '-');
                        
                        $('#info-status').html(getStatusBadge(response.status));
                        
                        // Get order items - handle both camelCase and snake_case property names
                        const orderItems = response.orderItems || response.order_items || [];
                        $('#info-item-count').text(orderItems ? orderItems.length : '0');
                        $('#info-hari').text(response.hari || '-');
                        
                        // Show Order information
                        $('#order-info').removeClass('d-none');
                        
                        if (orderItems && orderItems.length > 0) {
                            console.log('Order items found:', orderItems.length);
                            let validItemCount = 0;
                            
                            orderItems.forEach(function(item, index) {
                                console.log('Processing item[' + index + ']:', item);
                                validItemCount++;
                                
                                // Handle both camelCase and snake_case property names
                                const platformProduct = item.platformProduct || item.platform_product || null;
                                const mappingBarang = platformProduct ? (platformProduct.mappingBarang || platformProduct.mapping_barang || []) : [];
                                
                                const productName = platformProduct ? platformProduct.platform_product_name : 'Unknown Product';
                                const variation = platformProduct ? (platformProduct.variant || '-') : '-';
                                
                                // Display platform product (simplified, no barang keluar details)
                                if (platformProduct && platformProduct.platform_product_name) {
                                    html += `
                                    <tr>
                                        <td>
                                            <strong>${platformProduct.platform_product_name}</strong>
                                        </td>
                                        <td>${platformProduct.variant || '-'}</td>
                                        <td class="text-center">
                                            <span class="qty-badge">${item.quantity}</span>
                                        </td>
                                        <td>
                                            <input type="hidden" name="details[${index}][order_item_id]" value="${item.id}">
                                            <input type="number" name="details[${index}][qty]" class="form-control form-control-sm qty-input" min="0" max="${item.quantity}" step="1" value="0">
                                        </td>
                                        <td>
                                            <select name="details[${index}][kondisi]" class="form-control form-control-sm">
                                                <option value="BAGUS">BAGUS (Masuk Warehouse)</option>
                                                <option value="RUSAK">RUSAK (Masuk Warehouse Rusak)</option>
                                                <option value="HILANG">HILANG (Tidak Masuk Warehouse)</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="details[${index}][alasan]" class="form-control form-control-sm" placeholder="Alasan retur">
                                        </td>
                                    </tr>
                                    `;
                                } else {
                                    console.warn('Item has no platformProduct:', item.id);
                                    // For items without platform product
                                    html += `
                                    <tr>
                                        <td>${productName}</td>
                                        <td>${variation}</td>
                                        <td class="text-center">
                                            <span class="qty-badge">${item.quantity}</span>
                                        </td>
                                        <td colspan="3" class="text-center text-danger">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Produk tidak memiliki platform product
                                        </td>
                                    </tr>
                                    `;
                                }
                            });
                            
                            if (validItemCount === 0) {
                                html = '<tr><td colspan="6" class="text-center">Tidak ada barang yang bisa diretur</td></tr>';
                            }
                        } else {
                            console.warn('No order items found in response');
                            html = '<tr><td colspan="6" class="text-center">Tidak ada detail order</td></tr>';
                        }
                        
                        $('#items-container').html(html);
                        
                        // Add event listener to qty inputs
                        $('.qty-input').on('input', function() {
                            validateQty($(this));
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                        try {
                            const errorObj = JSON.parse(xhr.responseText);
                            console.error('Error details:', errorObj);
                            alert('Terjadi kesalahan: ' + (errorObj.error || error));
                        } catch (e) {
                            alert('Terjadi kesalahan saat mengambil data order: ' + error);
                        }
                        $('#items-container').html('<tr><td colspan="6" class="text-center">Error: Gagal memuat data</td></tr>');
                    }
                });
            } else {
                // Reset and disable the submit button when no order is selected
                $('#submit-btn').prop('disabled', true);
                $('#order-info').addClass('d-none');
                $('#items-container').html('<tr><td colspan="6" class="text-center">Pilih Order terlebih dahulu</td></tr>');
            }
        });
        
        // Form submission validation
        $('#returForm').on('submit', function(e) {
            let hasValidQty = false;
            
            $('.qty-input').each(function() {
                const qty = parseFloat($(this).val());
                if (qty > 0) {
                    hasValidQty = true;
                    return false; // Break the loop
                }
            });
            
            if (!hasValidQty) {
                e.preventDefault();
                alert('Setidaknya satu item harus memiliki kuantitas retur lebih dari 0');
            }
        });
        
        // Helper function to validate qty input
        function validateQty(input) {
            const qty = parseFloat(input.val());
            const max = parseFloat(input.attr('max'));
            
            if (qty < 0) {
                input.val(0);
            } else if (qty > max) {
                input.val(max);
            }
        }
        
        // Helper function to get status badge HTML
        function getStatusBadge(status) {
            switch(status) {
                case 'pending':
                    return '<span class="badge badge-warning">Pending</span>';
                case 'completed':
                    return '<span class="badge badge-success">Completed</span>';
                case 'cancelled':
                    return '<span class="badge badge-danger">Cancelled</span>';
                default:
                    return '<span class="badge badge-secondary">' + status + '</span>';
            }
        }
    });
</script>
@endpush 
