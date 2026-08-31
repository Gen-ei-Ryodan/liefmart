@extends('layouts.app')

@section('title', 'Input Manual Penjualan')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Input Manual Penjualan - {{ $platformDisplayName ?? $platform }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}" class="text-decoration-none">Menu Penjualan</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sales.online') }}" class="text-decoration-none">Penjualan Online</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Input Manual ({{ $platformDisplayName ?? $platform }})</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('sales.online') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-file-invoice text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Input Manual Penjualan - {{ $platformDisplayName ?? $platform }}</h5>
                </div>

                <div class="card-body p-4">
                    <div class="row mb-4 d-none">
                        <div class="col-12">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Menu Penjualan</a></li>
                                    <li class="breadcrumb-item"><a href="{{ route('sales.online') }}">Penjualan Online</a></li>
                                    <li class="breadcrumb-item active">Input Manual ({{ $platformDisplayName ?? $platform }})</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    
                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif
                    
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif
                    
                    <!-- Alert for duplicate order -->
                    <div class="alert alert-danger alert-dismissible fade show d-none" role="alert" id="duplicateOrderAlert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Perhatian!</strong> Nomor order sudah ada di database.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    
                    <form action="{{ route('sales.save-online-transaction') }}" method="POST" id="salesForm">
                        @csrf
                        <input type="hidden" name="platform" value="{{ strtolower($platform) }}">
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="order_date" class="form-label">Tanggal Order <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="order_date" name="order_date" required>
                            </div>
                            <div class="col-md-4">
                                <label for="day" class="form-label">Hari <span class="text-danger">*</span></label>
                                <select class="form-select" id="day" name="day" required>
                                    <option value="">Pilih Hari</option>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                    <option value="Minggu">Minggu</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="day_status" class="form-label">Status Hari <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="day_status" name="day_status" 
                                       placeholder="Contoh: Weekday,Weekend atau Doubledate,Weekend" required>
                                <small class="text-muted">Bisa multiple values dipisahkan dengan comma (contoh: Weekday,Weekend)</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="no_order" class="form-label">Nomor Order <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="no_order" name="no_order" 
                                           placeholder="Masukkan nomor order..." required>
                                    <button type="button" class="btn btn-outline-secondary" id="checkOrderBtn">
                                        <i class="fas fa-search"></i> Cek
                                    </button>
                                </div>
                                <div class="invalid-feedback d-none" id="orderNumberFeedback">
                                    Nomor order sudah ada dalam sistem.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="tracking_number" class="form-label">Nomor Resi</label>
                                <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                                       placeholder="Masukkan nomor resi...">
                                <small class="text-muted">Nomor resi untuk seluruh order ini</small>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Item Penjualan</h6>
                                    <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                                        <i class="fas fa-plus"></i> Tambah Item
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Produk</th>
                                                <th>Variasi</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Harga Satuan</th>
                                                <th class="text-end">Subtotal</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsContainer">
                                            <!-- Item rows will be added here dynamically -->
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold">Total</td>
                                                <td colspan="2">
                                                    <span id="grandTotal" class="fw-bold">0</span>
                                                    <input type="hidden" name="total_amount" id="totalAmountInput" value="0">
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('sales.online') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Kembali
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template for new item row -->
<template id="itemRowTemplate">
    <tr class="item-row">
        <td>
            <select class="form-select product-select" name="items[__index__][platform_product_id]" required>
                <option value="">Pilih Produk</option>
                @foreach($mappedProducts as $product)
                    <option 
                        value="{{ $product->id }}" 
                        data-variant="{{ $product->variant }}" 
                        data-stock="{{ $product->stok_tersedia }}"
                        data-name="{{ $product->platform_product_name }}"
                    >
                        {{ $product->platform_product_name }}
                        @if($product->variant)
                            - {{ $product->variant }}
                        @endif
                    </option>
                @endforeach
            </select>
            <div class="stock-info mt-1 d-none">
                <small class="stock-text"></small>
            </div>
        </td>
        <td>
            <input type="text" class="form-control variant-input" name="items[__index__][variant]" readonly>
        </td>
        <td>
            <input type="number" class="form-control qty-input" name="items[__index__][qty]" min="1" value="1" required>
        </td>
        <td>
            <input type="number" class="form-control price-input" name="items[__index__][price]" min="0" step="0.001" required>
        </td>
        <td>
            <span class="subtotal">0</span>
            <input type="hidden" class="subtotal-input" name="items[__index__][subtotal]" value="0">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css">
<style>
    .stock-text.low-stock {
        color: #dc3545;
        font-weight: bold;
    }
    
    /* Style for duplicate order input */
    input.is-invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        const itemsContainer = document.getElementById('itemsContainer');
        const itemRowTemplate = document.getElementById('itemRowTemplate');
        const addItemBtn = document.getElementById('addItemBtn');
        const salesForm = document.getElementById('salesForm');
        const noOrderInput = document.getElementById('no_order');
        const checkOrderBtn = document.getElementById('checkOrderBtn');
        const orderNumberFeedback = document.getElementById('orderNumberFeedback');
        const duplicateOrderAlert = document.getElementById('duplicateOrderAlert');
        const orderDateInput = document.getElementById('order_date');
        const daySelect = document.getElementById('day');
        const dayStatusSelect = document.getElementById('day_status');
        let itemIndex = 0;
        let isDuplicateOrder = false;
        
        // Auto-fill day based on date
        orderDateInput.addEventListener('change', function() {
            const date = new Date(this.value);
            if (isNaN(date.getTime())) return;
            
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const dayName = days[date.getDay()];
            daySelect.value = dayName;
            
            // Note: Status hari is now manual input, no longer auto-filled
        });
        
        // Check for duplicate order number
        const checkOrderNumber = () => {
            const orderNumber = noOrderInput.value.trim();
            if (!orderNumber) return;
            
            const platform = "{{ strtolower($platform) }}";
            
            // Show loading state
            checkOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            checkOrderBtn.disabled = true;
            
            // Make AJAX request to check if order exists
            fetch(`/api/check-order?platform=${platform}&order_number=${encodeURIComponent(orderNumber)}`)
                .then(response => response.json())
                .then(data => {
                    checkOrderBtn.innerHTML = '<i class="fas fa-search"></i> Cek';
                    checkOrderBtn.disabled = false;
                    
                    if (data.exists) {
                        // Order number already exists
                        noOrderInput.classList.add('is-invalid');
                        orderNumberFeedback.classList.remove('d-none');
                        duplicateOrderAlert.classList.remove('d-none');
                        isDuplicateOrder = true;
                    } else {
                        // Order number is unique
                        noOrderInput.classList.remove('is-invalid');
                        noOrderInput.classList.add('is-valid');
                        orderNumberFeedback.classList.add('d-none');
                        duplicateOrderAlert.classList.add('d-none');
                        isDuplicateOrder = false;
                    }
                })
                .catch(error => {
                    console.error('Error checking order number:', error);
                    checkOrderBtn.innerHTML = '<i class="fas fa-search"></i> Cek';
                    checkOrderBtn.disabled = false;
                });
        };
        
        // Attach check event to button
        checkOrderBtn.addEventListener('click', checkOrderNumber);
        
        // Also check when input loses focus
        noOrderInput.addEventListener('blur', checkOrderNumber);
        
        // Check when input changes (after a delay)
        let typingTimer;
        noOrderInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(checkOrderNumber, 1000);
        });
        
        // Store product data for reference
        const productData = {
            @foreach($mappedProducts as $product)
            "{{ $product->id }}": {
                name: "{{ $product->platform_product_name }}",
                variant: "{{ $product->variant }}",
                stock: {{ $product->stok_tersedia }},
                stock_details: [
                    @foreach($product->stok_detail ?? [] as $detail)
                    {
                        product_id: {{ $detail['product_id'] }},
                        product_name: "{{ $detail['product_name'] }}",
                        stok_tersedia: {{ $detail['stok_tersedia'] }},
                        stok_efektif: {{ $detail['stok_efektif'] }}
                    },
                    @endforeach
                ]
            },
            @endforeach
        };
        
        // Format currency
        function formatCurrency(amount) {
            // Tampilkan angka dengan maksimal 3 angka di belakang koma, tanpa pembulatan ke atas
            if (isNaN(amount)) return amount;
            return parseFloat(amount).toFixed(3).replace(/\.0{1,3}$/, '');
        }
        
        // Calculate subtotal for a row
        function calculateSubtotal(row) {
            const qty = parseInt(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const subtotal = qty * price;
            
            row.querySelector('.subtotal').textContent = formatCurrency(subtotal);
            row.querySelector('.subtotal-input').value = subtotal;
            
            return subtotal;
        }
        
        // Calculate grand total
        function calculateGrandTotal() {
            const rows = document.querySelectorAll('.item-row');
            let total = 0;
            
            rows.forEach(row => {
                total += parseFloat(row.querySelector('.subtotal-input').value) || 0;
            });
            
            document.getElementById('grandTotal').textContent = formatCurrency(total);
            document.getElementById('totalAmountInput').value = total;
        }
        
        // Add new item row
        function addItemRow() {
            // Clone template
            const template = itemRowTemplate.content.cloneNode(true);
            const row = template.querySelector('tr');
            
            // Replace index placeholder
            const elements = row.querySelectorAll('[name*="__index__"]');
            elements.forEach(el => {
                el.name = el.name.replace('__index__', itemIndex);
            });
            
            // Add event listeners
            const selectElement = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.qty-input');
            const priceInput = row.querySelector('.price-input');
            const removeBtn = row.querySelector('.remove-item-btn');
            
            // Product select change
            selectElement.addEventListener('change', function() {
                const productId = this.value;
                const stockInfo = row.querySelector('.stock-info');
                const stockText = row.querySelector('.stock-text');
                const variantInput = row.querySelector('.variant-input');
                const qtyInput = row.querySelector('.qty-input');
                
                if (productId) {
                    const product = productData[productId];
                    if (product) {
                        variantInput.value = product.variant;
                        qtyInput.max = product.stock;
                        
                        stockInfo.classList.remove('d-none');
                        stockText.textContent = `Stok: ${product.stock}`;
                        
                        if (product.stock < 5) {
                            stockText.classList.add('low-stock');
                        } else {
                            stockText.classList.remove('low-stock');
                        }
                    }
                } else {
                    variantInput.value = '';
                    stockInfo.classList.add('d-none');
                }
                
                calculateSubtotal(row);
                calculateGrandTotal();
            });
            
            // Qty and price change
            [qtyInput, priceInput].forEach(input => {
                input.addEventListener('input', function() {
                    calculateSubtotal(row);
                    calculateGrandTotal();
                });
            });
            
            // Remove button click
            removeBtn.addEventListener('click', function() {
                row.remove();
                calculateGrandTotal();
            });
            
            // Add row to container
            itemsContainer.appendChild(row);
            
            // Initialize Tom Select
            new TomSelect(selectElement, {
                create: false,
                sortField: {
                    field: 'text',
                    direction: 'asc'
                },
                dropdownParent: 'body', // <- ini penting agar dropdown tidak dibatasi div parent
                render: {
                    option: function(data, escape) {
                        const productId = data.value;
                        const product = productData[productId] || {};
                        const stock = product.stock || 0;
                        
                        return `<div class="d-flex justify-content-between">
                            <span>${escape(data.text)}</span>
                            <span class="text-muted">Stok: ${stock}</span>
                        </div>`;
                    }
                }
            });
            
            // Increment index for next row
            itemIndex++;
        }
        
        // Add item button click
        addItemBtn.addEventListener('click', addItemRow);
        
        // Form submit validation
        salesForm.addEventListener('submit', function(event) {
            // Prevent form submission if order number is duplicate
            if (isDuplicateOrder) {
                event.preventDefault();
                alert('Nomor order sudah ada di database. Silakan gunakan nomor order yang berbeda.');
                noOrderInput.focus();
                return;
            }
            
            const rows = document.querySelectorAll('.item-row');
            
            // Check if there are items
            if (rows.length === 0) {
                event.preventDefault();
                alert('Harap tambahkan minimal 1 item!');
                return;
            }
            
            // Validate each row
            rows.forEach(row => {
                const selectElement = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.qty-input');
                
                if (selectElement.value) {
                    const productId = selectElement.value;
                    const product = productData[productId] || {};
                    const stock = product.stock || 0;
                    const qty = parseInt(qtyInput.value) || 0;
                    
                    if (qty > stock) {
                        event.preventDefault();
                        alert(`Quantity untuk produk "${product.name}" melebihi stok tersedia!`);
                        qtyInput.focus();
                        return;
                    }
                }
            });
        });
        
        // Auto-fill day on page load if date is already set
        if (orderDateInput.value) {
            const date = new Date(orderDateInput.value);
            if (!isNaN(date.getTime())) {
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const dayName = days[date.getDay()];
                daySelect.value = dayName;
                
                // Set day status automatically
                if (date.getDay() === 0 || date.getDay() === 6) { // Sunday or Saturday
                    dayStatusSelect.value = 'Weekend';
                } else {
                    dayStatusSelect.value = 'Weekday';
                }
            }
        }
        
        // Add one item row on page load
        addItemRow();
    });
</script>
@endpush
