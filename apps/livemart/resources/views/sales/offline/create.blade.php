@extends('layouts.app')

@section('title', 'Tambah Penjualan Offline')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css">
<style>
    .item-row .form-control {
        margin-bottom: 8px;
    }
    .product-details {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .stock-info {
        font-size: 0.8rem;
    }
    .low-stock {
        color: #dc3545;
    }
    .discount-container {
        margin-top: 8px;
    }
    .discount-row {
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .discount-amount-group, .discount-percent-group {
        flex: 1;
    }
    .remove-discount-btn {
        flex-shrink: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Penjualan Offline</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('sales.index') }}" class="text-decoration-none">Menu Penjualan</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sales.offline.list') }}" class="text-decoration-none">Penjualan Offline</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('sales.offline.list') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-times me-2"></i> Batal
        </a>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6>Tambah Penjualan Offline</h6>
                    <a href="{{ route('sales.offline.list') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('info'))
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            {{ session('info') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('sales.offline.store') }}" id="offlineSaleForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sale_date" class="form-control-label">Tanggal Penjualan</label>
                                    <input class="form-control @error('sale_date') is-invalid @enderror" 
                                           type="date" id="sale_date" name="sale_date" 
                                           value="{{ old('sale_date', date('Y-m-d')) }}" required>
                                    @error('sale_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="No_PO" class="form-control-label">Nomor PO</label>
                                    <input class="form-control @error('No_PO') is-invalid @enderror" 
                                           type="text" id="No_PO" name="No_PO" 
                                           value="{{ old('No_PO') }}">
                                    @error('No_PO')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_id" class="form-control-label">Pelanggan</label>
                                    <select class="form-control tom-select @error('customer_id') is-invalid @enderror" 
                                            id="customer_id" name="customer_id" required>
                                        <option value="">-- Pilih Pelanggan --</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }} - {{ $customer->phone }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status" class="form-control-label">Status Pembayaran</label>
                                    <select class="form-control @error('status') is-invalid @enderror" 
                                            id="status" name="status" required>
                                        <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Belum Dibayar</option>
                                        <option value="paid" {{ old('status') == 'paid' ? 'selected' : '' }}>Sudah Dibayar</option>
                                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Payment details (shown when status is paid) -->
                        <div class="row mt-3 d-none" id="payment-details">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_date" class="form-control-label">Tanggal Pembayaran</label>
                                    <input type="date" class="form-control @error('payment_date') is-invalid @enderror" 
                                           id="payment_date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}">
                                    @error('payment_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_method" class="form-control-label">Metode Pembayaran</label>
                                    <select class="form-control @error('payment_method') is-invalid @enderror" 
                                            id="payment_method" name="payment_method">
                                        <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                        <option value="transfer" {{ old('payment_method') == 'transfer' ? 'selected' : '' }}>Transfer</option>
                                        <option value="check" {{ old('payment_method') == 'check' ? 'selected' : '' }}>Check</option>
                                        <option value="credit_card" {{ old('payment_method') == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                    </select>
                                    @error('payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="notes" class="form-control-label">Catatan</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" 
                                              id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="main_category_id" class="form-control-label">Kategori Utama</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-0">
                                            <i class="fas fa-layer-group text-primary"></i>
                                        </span>
                                        <input type="text" class="form-control bg-light border-0 ps-1" value="{{ session('main_category_name') }}" readonly>
                                        <input type="hidden" name="main_category_id" id="main_category_id" value="{{ session('main_category_id') }}">
                                    </div>
                                    <div class="form-text text-muted">Kategori yang dipilih saat login</div>
                                    @error('main_category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4 mb-4">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Item Penjualan</h6>
                                <button type="button" class="btn btn-sm btn-success" id="addItemBtn">
                                    <i class="fas fa-plus"></i> Tambah Item
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0" id="items-table">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Produk</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Kuantitas</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Harga</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Diskon</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Subtotal</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="items-container">
                                            <!-- Items will be added here dynamically -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end pe-3">
                                                    <strong>Subtotal:</strong>
                                                </td>
                                                <td colspan="2">
                                                    <span id="total-subtotal">0</span>
                                                    <input type="hidden" name="subtotal" id="subtotal-input" value="0">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="4" class="text-end pe-3">
                                                    <strong>Total:</strong>
                                                </td>
                                                <td colspan="2">
                                                    <strong id="grand-total">0</strong>
                                                    <input type="hidden" name="total_amount" id="total-amount-input" value="0">
                                                    <input type="hidden" name="tax_amount" id="tax-amount-input" value="0">
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-secondary me-2" onclick="window.history.back();">
                                Batal
                            </button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template for new item row -->
<template id="item-row-template">
    <tr class="item-row">
        <td>
            <div class="d-flex px-2 py-1">
                <div class="d-flex flex-column justify-content-center">
                    <select class="form-control product-select" name="product_id[]" required>
                        <option value="">-- Pilih Produk --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" data-name="{{ $product->name }}">
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="form-control quantity-input" name="quantity[]" 
                   min="0.001" step="0.001" value="1" required>
        </td>
        <td>
            <input type="number" class="form-control price-input" name="unit_price[]" 
                   min="0" step="0.001" value="0" required>
        </td>
        <td>
            <div class="discount-container">
                <div class="discount-row" data-discount-index="1">
                    <div class="input-group discount-amount-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control discount-amount" 
                               name="discount_amount_1[]" data-discount-number="1" placeholder="Nominal" min="0" step="0.001" value="0">
                    </div>
                    <div class="input-group discount-percent-group">
                        <input type="number" class="form-control discount-percent" 
                               name="discount_percent_1[]" data-discount-number="1" placeholder="Persen" min="0" max="100" step="0.001" value="0">
                        <span class="input-group-text">%</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-discount-btn d-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-1 add-discount-btn">
                    <i class="fas fa-plus"></i> Diskon
                </button>
            </div>
        </td>
        <td>
            <div class="font-weight-bold">
                <span class="subtotal">0</span>
                <input type="hidden" class="subtotal-input" name="item_subtotal[]" value="0">
            </div>
        </td>
        <td>
            <div class="d-flex">
                <button type="button" class="btn btn-link text-danger remove-item-btn p-1">
                    <i class="fas fa-trash"></i>
                </button>
                <button type="button" class="btn btn-link text-info item-notes-btn p-1" data-bs-toggle="modal" data-bs-target="#notesModal">
                    <i class="fas fa-sticky-note"></i>
                </button>
                <input type="hidden" class="item-notes" name="item_notes[]" value="">
            </div>
        </td>
    </tr>
</template>

<!-- Modal for item notes -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notesModalLabel">Catatan Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="modal-notes-input" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="save-notes-btn">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tom-select for customer
        new TomSelect('#customer_id', {
            placeholder: 'Pilih Pelanggan',
            allowEmptyOption: true,
            plugins: ['clear_button'],
            dropdownParent: 'body',
        });

        let itemIndex = 0;
        const itemsContainer = document.getElementById('items-container');
        const itemRowTemplate = document.getElementById('item-row-template');
        const addItemBtn = document.getElementById('addItemBtn');
        const notesModal = document.getElementById('notesModal');
        const modalNotesInput = document.getElementById('modal-notes-input');
        const saveNotesBtn = document.getElementById('save-notes-btn');
        let currentNotesInput = null;

        // Function for cascading rounding (ensuring correct subtotal calculation)
        function cascadingRound(value) {
            // Round to 3 decimal places for calculation purposes
            return Math.round((value + Number.EPSILON) * 1000) / 1000;
        }
        
        // Format currency for display
        function formatCurrency(value) {
            return new Intl.NumberFormat('id-ID').format(Math.round(value));
        }
        
        // Fix subtotal display reference in calculateItemSubtotal
        function calculateItemSubtotal(row) {
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            
            // Start with base subtotal
            let subtotal = quantity * price;
            
            // Get all discount rows and convert to array for sorting
            const discountRowsNodeList = row.querySelectorAll('.discount-row');
            const discountRows = Array.from(discountRowsNodeList);
            
            // Sort discount rows by their data-discount-index attribute
            // This ensures discounts are applied in the correct sequence (1, 2, 3, 4, 5)
            discountRows.sort((a, b) => {
                const indexA = parseInt(a.getAttribute('data-discount-index') || a.dataset.discountIndex || 0);
                const indexB = parseInt(b.getAttribute('data-discount-index') || b.dataset.discountIndex || 0);
                return indexA - indexB; // Sort in ascending order for calculation
            });
            
            // Apply discounts in order
            for (const discountRow of discountRows) {
                const percentInput = discountRow.querySelector('.discount-percent');
                const amountInput = discountRow.querySelector('.discount-amount');
                
                if (percentInput && amountInput) {
                    const percent = parseFloat(percentInput.value) || 0;
                    const amount = parseFloat(amountInput.value) || 0;
                    
                    // Apply percentage discount first
                    if (percent > 0) {
                        const percentDiscount = subtotal * (percent / 100);
                        subtotal -= percentDiscount;
                        // Apply 2 decimal place rounding after percentage discount
                        subtotal = Math.round(subtotal * 100) / 100;
                    }
                    
                    // Apply amount discount next
                    if (amount > 0) {
                        const amountDiscount = amount * quantity;
                        subtotal -= amountDiscount;
                        // Apply 2 decimal place rounding after amount discount
                        subtotal = Math.round(subtotal * 100) / 100;
                    }
                }
            }
            
            // Final subtotal rounding to 2 decimal places
            subtotal = Math.round(subtotal * 100) / 100;
            
            // Display the result - ensure compatibility with both .subtotal and .subtotal-display
            const subtotalElement = row.querySelector('.subtotal') || row.querySelector('.subtotal-display');
            if (subtotalElement) {
                subtotalElement.textContent = formatCurrency(subtotal);
            }
            
            const subtotalInput = row.querySelector('.subtotal-input');
            if (subtotalInput) {
                subtotalInput.value = subtotal.toFixed(2);
            }
            
            // Return subtotal for further calculations
            return subtotal;
        }
        
        // Function to calculate overall totals
        function calculateTotals() {
            const rows = document.querySelectorAll('.item-row');
            let totalSubtotal = 0;
            
            rows.forEach(row => {
                totalSubtotal += parseFloat(row.querySelector('.subtotal-input').value) || 0;
            });
            
            // Update subtotal - round for display and storage to 2 decimal places
            totalSubtotal = Math.round(totalSubtotal * 100) / 100;
            document.getElementById('total-subtotal').textContent = formatCurrency(totalSubtotal);
            document.getElementById('subtotal-input').value = totalSubtotal.toFixed(2);
            
            // Calculate grand total (now equal to subtotal, no tax)
            const grandTotal = totalSubtotal;
            document.getElementById('grand-total').textContent = formatCurrency(grandTotal);
            document.getElementById('total-amount-input').value = grandTotal.toFixed(2);
            document.getElementById('tax-amount-input').value = '0.00'; // Set tax amount to 0
        }
        
        // Function to add a new item row
        function addItemRow() {
            // Clone the template content
            const clone = document.importNode(itemRowTemplate.content, true);
            const row = clone.querySelector('tr');
            
            // Set unique names with current index
            const selectElements = row.querySelectorAll('select, input');
            selectElements.forEach(el => {
                const currentName = el.getAttribute('name');
                if (currentName && currentName.includes('[]')) {
                    el.setAttribute('name', currentName);
                    el.setAttribute('data-index', itemIndex);
                }
            });
            
            // Add the new row to the container
            itemsContainer.appendChild(row);
            
            // Initialize Tom Select for the product dropdown
            const productSelect = row.querySelector('.product-select');
            new TomSelect(productSelect, {
                placeholder: 'Pilih Produk',
                allowEmptyOption: true,
                plugins: ['clear_button'],
                dropdownParent: 'body',
                onChange: function(value) {
                    if (value) {
                        // You could add AJAX call here to get available stock and price information
                        // For now, this is just a placeholder
                        fetch(`/api/products/${value}/stock-info`)
                            .then(response => response.json())
                            .then(data => {
                                // Set price if available
                                if (data.price) {
                                    row.querySelector('.price-input').value = data.price;
                                }
                                
                                // Create stock information display
                                let stockInfo = document.createElement('div');
                                stockInfo.className = 'stock-info mt-2';
                                
                                // Add total stock information at the top
                                let totalStockEl = document.createElement('div');
                                totalStockEl.className = data.total_stock > 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
                                totalStockEl.innerHTML = `Total Stok: ${data.total_stock} <small>(Maksimum yang bisa diambil)</small>`;
                                stockInfo.appendChild(totalStockEl);
                                
                                // Add individual stock details if needed
                                if (data.warehouse_stocks && data.warehouse_stocks.length > 0) {
                                    let stockDetailsHeader = document.createElement('div');
                                    stockDetailsHeader.className = 'mt-1 mb-1 fw-bold';
                                    stockDetailsHeader.textContent = 'Detail stok (diurutkan berdasarkan ED & FIFO):';
                                    stockInfo.appendChild(stockDetailsHeader);
                                    
                                    // Only show up to 5 stocks to save space
                                    const maxStocksToShow = 5;
                                    const stocksToShow = data.warehouse_stocks.slice(0, maxStocksToShow);
                                    
                                    stocksToShow.forEach(stock => {
                                        let stockDetail = document.createElement('div');
                                        stockDetail.className = 'small';
                                        
                                        // Format the expired date nicely or show "Tidak ada ED"
                                        const expiredText = stock.expired_date 
                                            ? `ED: ${stock.expired_date}` 
                                            : 'Tidak ada ED';
                                            
                                        stockDetail.textContent = `${stock.location}: ${stock.qty} (${expiredText})`;
                                        stockInfo.appendChild(stockDetail);
                                    });
                                    
                                    // Show message if there are more stocks
                                    if (data.warehouse_stocks.length > maxStocksToShow) {
                                        let moreStocks = document.createElement('div');
                                        moreStocks.className = 'small fst-italic';
                                        moreStocks.textContent = `... dan ${data.warehouse_stocks.length - maxStocksToShow} stok lainnya`;
                                        stockInfo.appendChild(moreStocks);
                                    }
                                }
                                
                                // Set the max value for quantity input to the total stock
                                const quantityInput = row.querySelector('.quantity-input');
                                if (data.max_allowed_qty) {
                                    // Only set max if we're using an input that supports it
                                    if (quantityInput.type === 'number') {
                                        quantityInput.max = data.max_allowed_qty;
                                    }
                                    
                                    // Add a custom data attribute for validation
                                    quantityInput.dataset.maxQty = data.max_allowed_qty;
                                }
                                
                                // Remove any existing stock info
                                const existingStockInfo = row.querySelector('.stock-info');
                                if (existingStockInfo) {
                                    existingStockInfo.remove();
                                }
                                
                                // Add the new stock info
                                const productCell = row.querySelector('.d-flex.flex-column');
                                productCell.appendChild(stockInfo);
                                
                                // Update calculations
                                calculateItemSubtotal(row);
                                calculateTotals();
                            })
                            .catch(error => {
                                console.error('Error fetching product info:', error);
                            });
                    }
                }
            });
            
            // Set up event listeners for quantity and price inputs
            row.querySelector('.quantity-input').addEventListener('input', () => {
                calculateItemSubtotal(row);
                calculateTotals();
            });
            
            row.querySelector('.price-input').addEventListener('input', () => {
                calculateItemSubtotal(row);
                calculateTotals();
            });
            
            // Set up event listeners for discount inputs
            row.querySelectorAll('.discount-amount, .discount-percent').forEach(input => {
                input.addEventListener('input', () => {
                    calculateItemSubtotal(row);
                    calculateTotals();
                });
            });
            
            // Set up remove button
            row.querySelector('.remove-item-btn').addEventListener('click', () => {
                row.remove();
                calculateTotals();
            });
            
            // Set up add discount button
            row.querySelector('.add-discount-btn').addEventListener('click', (e) => {
                addDiscountRow(e.target.closest('.discount-container'), itemIndex);
            });
            
            // Set up notes button
            row.querySelector('.item-notes-btn').addEventListener('click', (e) => {
                currentNotesInput = e.target.closest('td').querySelector('.item-notes');
                modalNotesInput.value = currentNotesInput.value;
            });
            
            // Increment the index for the next row
            itemIndex++;
            
            // Calculate initial values
            calculateItemSubtotal(row);
            calculateTotals();
        }
        
        // Function to add a new discount row
        function addDiscountRow(container, rowIndex) {
            // Check if we already have 5 discount rows (maximum allowed)
            const existingRows = container.querySelectorAll('.discount-row');
            if (existingRows.length >= 5) {
                alert('Maksimal 5 diskon per item');
                return;
            }
            
            // Use existing rows length + 1 for the discount index to ensure proper order
            const discountNumber = existingRows.length + 1;
            
            // Create new discount row
            const discountRow = document.createElement('div');
            discountRow.className = 'discount-row';
            discountRow.dataset.discountIndex = discountNumber; // Store the index for reference
            discountRow.innerHTML = `
                <div class="input-group discount-amount-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" class="form-control discount-amount" 
                           name="discount_amount_${discountNumber}[]" data-discount-number="${discountNumber}" placeholder="Nominal" min="0" step="0.001" value="0">
                </div>
                <div class="input-group discount-percent-group">
                    <input type="number" class="form-control discount-percent" 
                           name="discount_percent_${discountNumber}[]" data-discount-number="${discountNumber}" placeholder="Persen" min="0" max="100" step="0.001" value="0">
                    <span class="input-group-text">%</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-discount-btn">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Show remove button for first discount row if adding a second row
            if (existingRows.length === 1) {
                const firstRowRemoveBtn = existingRows[0].querySelector('.remove-discount-btn');
                if (firstRowRemoveBtn) {
                    firstRowRemoveBtn.classList.remove('d-none');
                }
            }
            
            // Add row before the "Add Discount" button
            container.insertBefore(discountRow, container.querySelector('.add-discount-btn'));
            
            // Set up event listeners for new discount inputs
            discountRow.querySelectorAll('.discount-amount, .discount-percent').forEach(input => {
                input.addEventListener('input', () => {
                    calculateItemSubtotal(container.closest('.item-row'));
                    calculateTotals();
                });
            });
            
            // Set up remove button for the discount row
            discountRow.querySelector('.remove-discount-btn').addEventListener('click', () => {
                discountRow.remove();
                
                // Hide the remove button on the first row if there's only one row left
                const remainingRows = container.querySelectorAll('.discount-row');
                if (remainingRows.length === 1) {
                    const firstRowRemoveBtn = remainingRows[0].querySelector('.remove-discount-btn');
                    if (firstRowRemoveBtn) {
                        firstRowRemoveBtn.classList.add('d-none');
                    }
                }
                
                // Renumber the remaining discount rows to maintain proper order
                renumberDiscountRows(container);
                
                calculateItemSubtotal(container.closest('.item-row'));
                calculateTotals();
            });
        }
        
        // New function to renumber discount rows after removal
        function renumberDiscountRows(container) {
            const discountRows = Array.from(container.querySelectorAll('.discount-row'));
            
            // Sort existing rows by their current index to preserve order
            discountRows.sort((a, b) => {
                const indexA = parseInt(a.getAttribute('data-discount-index') || a.dataset.discountIndex || 0);
                const indexB = parseInt(b.getAttribute('data-discount-index') || b.dataset.discountIndex || 0);
                return indexA - indexB;
            });
            
            // Now renumber in sequence starting from 1
            discountRows.forEach((row, index) => {
                const newIndex = index + 1;
                
                // Set both HTML attribute and dataset property to ensure consistency
                row.setAttribute('data-discount-index', newIndex);
                row.dataset.discountIndex = newIndex;
                
                // Update the discount amount input
                const amountInput = row.querySelector('.discount-amount');
                if (amountInput) {
                    amountInput.name = `discount_amount_${newIndex}[]`;
                    amountInput.setAttribute('data-discount-number', newIndex);
                    amountInput.dataset.discountNumber = newIndex;
                }
                
                // Update the discount percent input
                const percentInput = row.querySelector('.discount-percent');
                if (percentInput) {
                    percentInput.name = `discount_percent_${newIndex}[]`;
                    percentInput.setAttribute('data-discount-number', newIndex);
                    percentInput.dataset.discountNumber = newIndex;
                }
            });
        }
        
        // Add item button event listener
        addItemBtn.addEventListener('click', addItemRow);
        
        // Save notes button event listener
        saveNotesBtn.addEventListener('click', () => {
            if (currentNotesInput) {
                currentNotesInput.value = modalNotesInput.value;
                const modal = bootstrap.Modal.getInstance(notesModal);
                modal.hide();
            }
        });
        
        // Form validation before submission
        document.getElementById('offlineSaleForm').addEventListener('submit', function(event) {
            // First, ensure all discount rows are properly numbered in sequence
            document.querySelectorAll('.discount-container').forEach(container => {
                renumberDiscountRows(container);

                // Tambahan: pastikan setiap item punya 5 field diskon (percent & amount)
                for (let j = 1; j <= 5; j++) {
                    // Cek apakah sudah ada input untuk diskon ke-j
                    let amountInput = container.querySelector(`input[name='discount_amount_${j}[]']`);
                    let percentInput = container.querySelector(`input[name='discount_percent_${j}[]']`);
                    if (!amountInput) {
                        // Tambahkan input hidden jika tidak ada
                        let hiddenAmount = document.createElement('input');
                        hiddenAmount.type = 'hidden';
                        hiddenAmount.name = `discount_amount_${j}[]`;
                        hiddenAmount.value = 0;
                        container.appendChild(hiddenAmount);
                    }
                    if (!percentInput) {
                        let hiddenPercent = document.createElement('input');
                        hiddenPercent.type = 'hidden';
                        hiddenPercent.name = `discount_percent_${j}[]`;
                        hiddenPercent.value = 0;
                        container.appendChild(hiddenPercent);
                    }
                }
            });
            
            const rows = document.querySelectorAll('.item-row');
            let isValid = true;
            
            rows.forEach(row => {
                const quantityInput = row.querySelector('.quantity-input');
                const quantity = parseFloat(quantityInput.value) || 0;
                const maxQty = parseFloat(quantityInput.dataset.maxQty) || 0;
                const productSelect = row.querySelector('.product-select');
                const productId = productSelect.value;
                const productOption = productSelect.options[productSelect.selectedIndex];
                const productName = productOption ? productOption.text : 'Produk';
                
                if (quantity <= 0) {
                    alert(`Kuantitas untuk ${productName} harus lebih dari 0`);
                    isValid = false;
                }
                
                if (maxQty > 0 && quantity > maxQty) {
                    alert(`Kuantitas untuk ${productName} (${quantity}) melebihi stok tersedia (${maxQty})`);
                    isValid = false;
                }
            });
            
            if (!isValid) {
                event.preventDefault();
            }
        });
        
        // Initialize with one item row
        addItemRow();
        
        // Toggle payment details based on status
        const statusSelect = document.getElementById('status');
        const paymentDetails = document.getElementById('payment-details');
        
        function togglePaymentDetails() {
            if (statusSelect.value === 'paid') {
                paymentDetails.style.display = 'block';
            } else {
                paymentDetails.style.display = 'none';
            }
        }
        
        statusSelect.addEventListener('change', togglePaymentDetails);
        
        // Initialize payment details visibility
        togglePaymentDetails();
        
        // SJ Number will be generated automatically by backend when creating the sale
        // No need to generate it on the frontend since field was removed from form
    });
</script>
@endpush
