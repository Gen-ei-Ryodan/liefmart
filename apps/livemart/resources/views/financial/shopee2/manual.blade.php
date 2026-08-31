@extends('layouts.app')

@section('title', 'Input Manual Data Keuangan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Input Manual Data Keuangan {{ $platformLabel }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('finance.shopee2.index') }}" class="text-decoration-none">Keuangan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Input Manual</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('finance.shopee2.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-money-bill text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Input Manual Data Keuangan {{ $platformLabel }}</h5>
                </div>

                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><strong>Error:</strong>
                            {!! session('error') !!}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> <strong>Error!</strong> Ada beberapa masalah dengan inputan Anda:
                            <ul class="mb-0 ps-3 pt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('finance.shopee2.manual-store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="order_id" class="form-label">Nomor Pesanan <span class="text-danger">*</span></label>
                                <select class="form-select" id="order_id" name="order_id" required>
                                    <option value="">-- Pilih Nomor Pesanan --</option>
                                    @php
                                        // Get Shopee2 platform ID dynamically
                                        $platformModel = App\Models\Platform::whereRaw('LOWER(name) = ?', ['shopee liefmarket'])->first();
                                        $shopee2PlatformId = $platformModel ? $platformModel->id : 2;
                                    @endphp
                                    @foreach(App\Models\Order::where('platform_id', $shopee2PlatformId)
                                        ->whereDoesntHave('shopee2FinancialTransactions')
                                        ->whereHas('orderItems')
                                        ->orderBy('tanggal', 'desc')
                                        ->get()
                                        ->filter(fn($o) => !$o->isFullyReturned()) as $order)
                                        <option value="{{ $order->id }}" {{ (request('order_id') == $order->id || old('order_id') == $order->id) ? 'selected' : '' }}>{{ $order->order_number }} - {{ $order->customer_name }} - {{ $order->tanggal ? $order->tanggal->format('d/m/Y') : 'N/A' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="tanggal_masuk_pembayaran" class="form-label">Tanggal Masuk Pembayaran <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="tanggal_masuk_pembayaran" name="tanggal_masuk_pembayaran" value="{{ old('tanggal_masuk_pembayaran', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="hari_masuk_pembayaran" class="form-label">Hari Masuk Pembayaran <span class="text-danger">*</span></label>
                                <select class="form-select" id="hari_masuk_pembayaran" name="hari_masuk_pembayaran" required>
                                    <option value="Senin" {{ old('hari_masuk_pembayaran') == 'Senin' ? 'selected' : '' }}>Senin</option>
                                    <option value="Selasa" {{ old('hari_masuk_pembayaran') == 'Selasa' ? 'selected' : '' }}>Selasa</option>
                                    <option value="Rabu" {{ old('hari_masuk_pembayaran') == 'Rabu' ? 'selected' : '' }}>Rabu</option>
                                    <option value="Kamis" {{ old('hari_masuk_pembayaran') == 'Kamis' ? 'selected' : '' }}>Kamis</option>
                                    <option value="Jumat" {{ old('hari_masuk_pembayaran') == 'Jumat' ? 'selected' : '' }}>Jumat</option>
                                    <option value="Sabtu" {{ old('hari_masuk_pembayaran') == 'Sabtu' ? 'selected' : '' }}>Sabtu</option>
                                    <option value="Minggu" {{ old('hari_masuk_pembayaran') == 'Minggu' ? 'selected' : '' }}>Minggu</option>
                                </select>
                            </div>
                        </div>

                        {{-- Hitung total nominal_harga dan qty dari order yang dipilih --}}
                        @php
                            $totalHarga = 0;
                            $totalQty = 0;
                            $selectedOrderId = request('order_id') ?: old('order_id');
                            if ($selectedOrderId) {
                                $selectedOrder = \App\Models\Order::with('orderItems')->find($selectedOrderId);
                                if ($selectedOrder) {
                                    foreach ($selectedOrder->orderItems as $item) {
                                        $totalHarga += $item->price_after_discount * $item->quantity;
                                        $totalQty += $item->quantity;
                                    }
                                }
                            }
                        @endphp
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Total Harga (Dari Penjualan)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="nominal_harga_display" value="{{ number_format($totalHarga, 0, ',', '.') }}" readonly style="background-color: #e9ecef; font-weight: 600;">
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>Total harga otomatis dari data penjualan
                                </div>
                                <input type="hidden" name="nominal_harga" id="nominal_harga" value="{{ $totalHarga }}">
                            </div>
                            <div class="col-md-1 mb-3">
                                <label class="form-label">Qty</label>
                                <input type="text" class="form-control" id="qty_display" value="{{ $totalQty }}" readonly style="background-color: #e9ecef; font-weight: 600;">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="saldo_masuk" class="form-label">Jumlah Masuk Pembayaran <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="saldo_masuk" name="saldo_masuk" value="{{ old('saldo_masuk', 0) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon1" class="form-label">Voucher Ditanggung Penjual</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon1" name="nominal_diskon1" value="{{ old('nominal_diskon1', 0) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon2" class="form-label">Komisi AMS/Affiliate</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon2" name="nominal_diskon2" value="{{ old('nominal_diskon2', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon3" class="form-label">Biaya Admin</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon3" name="nominal_diskon3" value="{{ old('nominal_diskon3', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon4" class="form-label">Biaya Layanan (Gratis Ongkir + Cashback)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon4" name="nominal_diskon4" value="{{ old('nominal_diskon4', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon5" class="form-label">Biaya 5</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon5" name="nominal_diskon5" value="{{ old('nominal_diskon5', 0) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon6" class="form-label">Biaya 6</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon6" name="nominal_diskon6" value="{{ old('nominal_diskon6', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon7" class="form-label">Biaya 7</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon7" name="nominal_diskon7" value="{{ old('nominal_diskon7', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon8" class="form-label">Biaya 8</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon8" name="nominal_diskon8" value="{{ old('nominal_diskon8', 0) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon9" class="form-label">Biaya 9</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon9" name="nominal_diskon9" value="{{ old('nominal_diskon9', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon10" class="form-label">Biaya 10</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon10" name="nominal_diskon10" value="{{ old('nominal_diskon10', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon11" class="form-label">Biaya 11</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon11" name="nominal_diskon11" value="{{ old('nominal_diskon11', 0) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nominal_diskon12" class="form-label">Biaya 12</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="nominal_diskon12" name="nominal_diskon12" value="{{ old('nominal_diskon12', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="adjustment" class="form-label">Adjustment</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="adjustment" name="adjustment" value="{{ old('adjustment', 0) }}">
                                </div>
                                <div class="form-text">Nilai positif akan menambah nominal fix, nilai negatif akan mengurangi nominal fix</div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-save me-2"></i> Simpan Data
                            </button>
                            <button type="reset" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-undo me-2"></i> Reset
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
<!-- Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .ts-wrapper .ts-control {
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .ts-wrapper.multi .ts-control {
        padding: calc(0.375rem - 1px) 0.75rem;
    }
    .ts-wrapper.focus .ts-control {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>
@endpush

@push('scripts')
<!-- Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Tom Select for order selection
        try {
            const selectElement = document.getElementById('order_id');
            if (selectElement) {
                if (typeof TomSelect !== 'undefined') {
                    new TomSelect('#order_id', {
                        create: false,
                        sortField: {
                            field: "text",
                            direction: "asc"
                        },
                        placeholder: "Pilih Nomor Pesanan",
                        onChange: function(value) {
                            if (value) {
                                fetchOrderSummary(value);
                            } else {
                                // Clear on deselect
                                document.getElementById('nominal_harga_display').value = '0';
                                document.getElementById('nominal_harga').value = '0';
                                document.getElementById('qty_display').value = '0';
                            }
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error initializing TomSelect:', error);
        }
        
        // AJAX: Fetch order total when user selects an order
        function fetchOrderSummary(orderId) {
            const priceDisplay = document.getElementById('nominal_harga_display');
            const priceHidden = document.getElementById('nominal_harga');
            const qtyDisplay = document.getElementById('qty_display');
            
            // Show loading
            if (priceDisplay) priceDisplay.value = 'Loading...';
            
            fetch('{{ route("finance.shopee2.order-total", "__ID__") }}'.replace('__ID__', orderId))
                .then(response => {
                    if (!response.ok) throw new Error('Order not found');
                    return response.json();
                })
                .then(data => {
                    if (priceDisplay) priceDisplay.value = data.formatted;
                    if (priceHidden) priceHidden.value = data.total_harga;
                    if (qtyDisplay) qtyDisplay.value = data.total_qty;
                })
                .catch(error => {
                    console.error('[Shopee2 Manual] Fetch order total failed:', error);
                    if (priceDisplay) priceDisplay.value = 'Error';
                });
        }
        
        // Set day of week automatically when date changes
        const dateInput = document.getElementById('tanggal_masuk_pembayaran');
        const daySelect = document.getElementById('hari_masuk_pembayaran');
        
        if (dateInput && daySelect) {
            dateInput.addEventListener('change', function() {
                const date = new Date(this.value);
                if (!isNaN(date.getTime())) {
                    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    const dayName = days[date.getDay()];
                    for (let i = 0; i < daySelect.options.length; i++) {
                        if (daySelect.options[i].value === dayName) {
                            daySelect.selectedIndex = i;
                            break;
                        }
                    }
                }
            });
            dateInput.dispatchEvent(new Event('change'));
        }
        
        // On page load, if order already selected (from old input or URL param), fetch totals
        var initialOrderId = document.getElementById('order_id').value;
        if (initialOrderId) {
            fetchOrderSummary(initialOrderId);
        }
    });
</script>
@endpush 