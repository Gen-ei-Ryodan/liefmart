@extends('layouts.app')

@section('title', 'Edit Mapping Produk')

@push('styles')
@include('master.mapping.styles')

<style>
    .form-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .form-header {
        background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        color: white;
        padding: 2rem;
        text-align: center;
    }
    
    .form-header h2 {
        margin: 0;
        font-weight: 700;
        font-size: 1.5rem;
    }
    
    .form-header p {
        margin: 0.5rem 0 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    
    .form-body {
        padding: 2rem;
    }
    
    .form-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-section-title i {
        color: #f59e0b;
    }
    
    .info-table {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .info-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        width: 30%;
    }
    
    .info-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        color: #6b7280;
    }
    
    .info-table tr:last-child th,
    .info-table tr:last-child td {
        border-bottom: none;
    }
    
    .mapping-item-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .mapping-item-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border-color: #f59e0b;
    }
    
    .mapping-item-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .mapping-item-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        flex: 1;
        min-width: 200px;
    }
    
    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .quantity-input-group {
        display: flex;
        align-items: center;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: white;
    }
    
    .quantity-btn {
        background: #f3f4f6;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #6b7280;
    }
    
    .quantity-btn:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .quantity-input {
        border: none;
        padding: 0.75rem;
        text-align: center;
        font-weight: 500;
        min-width: 80px;
        max-width: 120px;
    }
    
    .quantity-input:focus {
        outline: none;
    }
    
    .btn-update {
        background: #10b981;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
        border: 2px solid #10b981;
    }
    
    .btn-update:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
    }
    
    .btn-update:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    }
    
    .btn-delete {
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    .add-product-section {
        background: #f0f9ff;
        border: 2px dashed #0ea5e9;
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        margin-top: 2rem;
    }
    
    .add-product-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #0ea5e9, #3b82f6, #8b5cf6);
    }
    
    .add-product-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .add-product-form .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 200px;
    }
    
    .btn-add {
        background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.875rem 2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        border: 2px solid #0ea5e9;
        font-size: 0.95rem;
    }
    
    .btn-add:hover {
        background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(14, 165, 233, 0.4);
    }
    
    .btn-add:active {
        transform: translateY(0);
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }
    
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .action-info {
        flex: 1;
        text-align: center;
        background: #f0f9ff;
        border: 1px solid #0ea5e9;
        border-radius: 12px;
        padding: 1rem;
        margin-left: 1rem;
    }
    
    .action-info p {
        font-size: 0.9rem;
        color: #0369a1;
        margin: 0; 
    }
    
    .btn-back {
        background: #6b7280;
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .btn-back:hover {
        background: #4b5563;
        color: white;
        transform: translateY(-1px);
    }
    
    .history-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .history-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    
    .history-table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 600;
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.9rem;
    }
    
    .history-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.9rem;
        color: #6b7280;
    }
    
    .history-table tr:last-child td {
        border-bottom: none;
    }
    
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-secondary { background: #f3f4f6; color: #6b7280; }
    .badge-primary { background: #dbeafe; color: #1e40af; }
    
    @media (max-width: 768px) {
        .form-body {
            padding: 1rem;
        }
        
        .mapping-item-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .quantity-controls {
            justify-content: center;
        }
        
        .add-product-form {
            flex-direction: column;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 1rem;
        }
        
        .action-info {
            margin-left: 0;
            order: -1;
        }
        
        .btn-back {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Mapping Produk</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('master.mapping.index') }}" class="text-decoration-none">Mapping Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('master.mapping.version-history', $mapping->platform_product_id) }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-history me-2"></i> Riwayat Versi
            </a>
            <a href="{{ route('master.mapping.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
        </div>
    </div>

    <div class="form-card">
        <div class="form-body">
            <!-- Alert Messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert" style="border-radius: 12px; border: none; background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert" style="border-radius: 12px; border: none; background: #fee2e2; color: #991b1b;">
                    <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            
            <!-- Platform Information Section -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Produk Platform
                </h6>
                
                <div class="info-table">
                    <table class="table mb-0">
                            <tr>
                                <th>Platform</th>
                                <td>
                                <span class="badge platform-{{ strtolower($mapping->platformProduct->platform ? $mapping->platformProduct->platform->name : 'unknown') }}">
                                        {{ $mapping->platformProduct->platform ? $mapping->platformProduct->platform->name : 'Unknown Platform' }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Nama Produk</th>
                                <td>{{ $mapping->platformProduct->platform_product_name }}</td>
                            </tr>
                            @if($mapping->platformProduct->variant)
                            <tr>
                                <th>Variant</th>
                                <td>{{ $mapping->platformProduct->variant }}</td>
                            </tr>
                            @endif
                            <tr>
                                <th>Versi Mapping</th>
                                <td>
                                <span class="badge badge-primary">v{{ $mapping->version }}</span>
                                    @if($hasBeenUsed)
                                    <span class="badge badge-warning ms-2">Sudah Digunakan dalam Penjualan</span>
                                    <small class="d-block text-muted mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Perubahan akan membuat versi baru (V{{ $mapping->version + 1 }})
                                    </small>
                                    @else
                                    <span class="badge badge-success ms-2">Belum Digunakan</span>
                                    <small class="d-block text-muted mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Dapat diedit langsung tanpa membuat versi baru
                                    </small>
                                    @endif
                                </td>
                            </tr>
                            @if($mapping->change_reason)
                            <tr>
                                <th>Alasan Perubahan</th>
                                <td>{{ $mapping->change_reason }}</td>
                            </tr>
                            @endif
                        </table>
                </div>
            </div>
            
            <!-- Current Mappings Section -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-cubes"></i>
                    Daftar Mapping Produk Master
                </h6>
                <p class="help-text mb-3">Kelola produk master yang terhubung dengan produk platform ini. <strong>Klik tombol "Update" untuk menyimpan perubahan quantity.</strong> Anda dapat menambah produk master tambahan di bagian bawah.</p>
                
                            @php 
                                // Ambil mapping aktif (versi terbaru) - hanya dari versi terbaru
                                $latestVersion = App\Models\MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                                    ->where('is_active', true)
                                    ->max('version');
                                
                                $allMappings = App\Models\MappingBarang::where('platform_product_id', $mapping->platform_product_id)
                                    ->where('is_active', true)
                                    ->where('version', $latestVersion)
                                    ->with('product') // Eager load relasi product untuk menghindari null
                                    ->get();
                            @endphp
                            
                            @foreach($allMappings as $item)
                <div class="mapping-item-card">
                    <div class="mapping-item-header">
                        <div class="mapping-item-name">{{ $item->product ? $item->product->name : 'Product tidak ditemukan (ID: ' . $item->product_id . ')' }}</div>
                        <div class="quantity-controls">
                            <form action="{{ route('master.mapping.update', $item->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                
                                <div class="quantity-input-group">
                                    <button type="button" class="quantity-btn decrease-qty" data-target="quantity-{{ $item->id }}">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                    <input type="number" name="quantity" id="quantity-{{ $item->id }}" 
                                           step="0.01" min="0.01" class="quantity-input" 
                                                    value="{{ $item->quantity }}" required
                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                                    <button type="button" class="quantity-btn increase-qty" data-target="quantity-{{ $item->id }}">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                
                                @if($hasBeenUsed)
                                <div class="mt-2">
                                    <input type="text" name="change_reason" class="form-control form-control-sm" 
                                           placeholder="Alasan perubahan (opsional)" maxlength="500">
                                </div>
                                @endif
                                
                                <button type="submit" class="btn-update" title="Update Quantity">
                                    <i class="fas fa-check me-1"></i> Update
                                </button>
                            </form>
                            
                            @if(auth()->user()->canEdit())
                            <form action="{{ route('master.mapping.destroy', $item->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete" title="Hapus Mapping">
                                    <i class="fas fa-trash me-1"></i> Hapus
                                </button>
                            </form>
                            @endif
                        </div>
                                        </div>
                </div>
                @endforeach
                
            <!-- Add New Product Section -->
            <div class="add-product-section">
                <h6 class="form-section-title mb-3">
                    <i class="fas fa-plus-circle"></i>
                    Tambah Produk Master Baru
                </h6>
                <p class="help-text mb-3">Tambahkan produk master lain untuk mapping ini. <strong>Satu produk platform dapat memiliki multiple produk master.</strong></p>
                
                @if($hasBeenUsed)
                    <div class="alert alert-info mb-3" style="border-radius: 12px; border: none; background: #dbeafe; color: #1e40af;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Perhatian:</strong> Karena mapping ini sudah digunakan dalam penjualan, menambah produk master baru akan membuat versi baru (V{{ $mapping->version + 1 }}) dan mapping lama akan menjadi history.
                    </div>
                                            @else
                    <div class="alert alert-success mb-3" style="border-radius: 12px; border: none; background: #d1fae5; color: #065f46;">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Info:</strong> Mapping ini belum digunakan dalam penjualan, sehingga produk master baru akan ditambahkan ke versi yang sama.
                    </div>
                @endif
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="border-radius: 12px; border: none; background: #d1fae5; color: #065f46;">
                        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="border-radius: 12px; border: none; background: #fee2e2; color: #991b1b;">
                        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                    <form action="{{ route('master.mapping.add-product') }}" method="POST" id="add-product-form">
                        @csrf
                        <input type="hidden" name="platform_product_id" value="{{ $mapping->platform_product_id }}">
                        
                    <div class="add-product-form">
                        <div class="form-group">
                            <label class="form-label">Produk Master <span style="color: #ef4444;">*</span></label>
                            <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                                        <option value="">-- Pilih Produk Master --</option>
                                        @foreach($products as $product)
                                            @php
                                                $isUsed = $allMappings->where('product_id', $product->id)->count() > 0;
                                            @endphp
                                    <option value="{{ $product->id }}" {{ $isUsed ? 'disabled' : '' }} {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                                {{ $product->name }} {{ $isUsed ? '(Sudah dimapping)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                            @error('product_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                                </div>
                        <div class="form-group">
                            <label class="form-label">Quantity Ratio <span style="color: #ef4444;">*</span></label>
                                        <input type="number" name="quantity" step="0.01" min="0.01" 
                                   class="form-control @error('quantity') is-invalid @enderror" required value="{{ old('quantity', 1) }}"
                                               oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                            @error('quantity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn-add" id="add-product-btn">
                                <i class="fas fa-plus me-2"></i> Tambah Produk
                                        </button>
                        </div>
                    </div>
                    <div class="help-text mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Contoh: Jika 1 produk platform membutuhkan 2 produk master, isi 2
                        </div>
                    </form>
            </div>
            
            <!-- History section removed - now using separate version history page -->
        </div>
        
        <div class="form-actions">
            <a href="{{ route('master.mapping.index') }}" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
            <div class="action-info">
                <p class="mb-0 text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Perubahan akan tersimpan otomatis saat Anda mengklik tombol "Update" pada setiap produk
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize TomSelect for product dropdown
    let productSelect;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize product select
        if (document.getElementById('product_id')) {
            productSelect = new TomSelect('#product_id', {
                placeholder: 'Pilih Produk Master...',
                allowEmptyOption: true,
                searchField: ['text'],
                render: {
                    option: function(data, escape) {
                        const isDisabled = data.disabled ? 'ts-disabled' : '';
                        return '<div class="ts-option ' + isDisabled + '">' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(value) {
                    // Sync with original select element
                    const productElement = document.getElementById('product_id');
                    if (productElement) {
                        productElement.value = value;
                        productElement.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }
        // Initialize quantity control buttons
        function initQuantityControls() {
            document.querySelectorAll('.decrease-qty').forEach(button => {
            button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const input = document.getElementById(targetId);
                    if (input) {
                        let value = parseFloat(input.value) || 0;
                if (value > 0.01) {
                    const decrementAmount = value >= 1 ? 1 : 0.01;
                    value = Math.max(0.01, value - decrementAmount);
                    input.value = value.toFixed(2);
                }
                    }
                });
        });
        
            document.querySelectorAll('.increase-qty').forEach(button => {
            button.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const input = document.getElementById(targetId);
                    if (input) {
                        let value = parseFloat(input.value) || 0;
                const incrementAmount = value >= 1 ? 1 : 0.01;
                value += incrementAmount;
                input.value = value.toFixed(2);
                    }
                });
            });
        }
        
        // Initialize numeric input validation
        function initNumericInputs() {
            document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('keypress', function(e) {
                const charCode = (e.which) ? e.which : e.keyCode;
                // Allow only numbers and decimal point
                if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode !== 46) {
                    e.preventDefault();
                }
                // Allow only one decimal point
                if (charCode === 46 && this.value.includes('.')) {
                    e.preventDefault();
                }
            });
        });
        }
        
        // Add loading state to forms
        function initFormLoading() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Sync TomSelect values before form submission
                    if (productSelect) {
                        const productElement = document.getElementById('product_id');
                        if (productElement) {
                            productElement.value = productSelect.getValue();
                        }
                    }
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        
                        // Different messages for different forms
                        if (this.id === 'add-product-form') {
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menambahkan...';
                        } else {
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
                        }
                        
                        submitBtn.disabled = true;
                        
                        // Show success message after form submission
                        setTimeout(() => {
                            if (this.id === 'add-product-form') {
                                showSuccessMessage('Produk master berhasil ditambahkan!');
                            } else {
                                showSuccessMessage('Perubahan berhasil disimpan!');
                            }
                        }, 1000);
                        
                        // Re-enable after 5 seconds as fallback
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 5000);
                    }
                });
            });
        }
        
        // Show success message
        function showSuccessMessage(message) {
            // Remove existing success message if any
            const existingMessage = document.querySelector('.success-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create success message
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
                z-index: 1050;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                animation: slideIn 0.3s ease;
            `;
            successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            document.body.appendChild(successDiv);
            
            // Remove after 3 seconds
            setTimeout(() => {
                successDiv.style.animation = 'slideIn 0.3s ease reverse';
                setTimeout(() => {
                    successDiv.remove();
                }, 300);
            }, 3000);
        }
        
        // Add confirmation for delete actions
        function initDeleteConfirmations() {
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Apakah Anda yakin ingin menghapus mapping ini?')) {
                        e.preventDefault();
                    }
                });
            });
        }
        
        // Initialize add product form validation
        function initAddProductValidation() {
            const addProductForm = document.getElementById('add-product-form');
            if (addProductForm) {
                addProductForm.addEventListener('submit', function(e) {
                    const productSelect = this.querySelector('select[name="product_id"]');
                    const quantityInput = this.querySelector('input[name="quantity"]');
                    
                    // Validate product selection
                    if (!productSelect.value) {
                        e.preventDefault();
                        alert('Mohon pilih produk master terlebih dahulu!');
                        productSelect.focus();
                        return false;
                    }
                    
                    // Validate quantity
                    if (!quantityInput.value || parseFloat(quantityInput.value) <= 0) {
                        e.preventDefault();
                        alert('Mohon masukkan quantity yang valid (lebih dari 0)!');
                        quantityInput.focus();
                        return false;
                    }
                    
                    // Check if product is already mapped
                    const selectedProduct = productSelect.options[productSelect.selectedIndex];
                    if (selectedProduct.disabled) {
                        e.preventDefault();
                        alert('Produk master ini sudah dimapping sebelumnya!');
                        productSelect.focus();
                        return false;
                    }
                });
            }
        }
        
        // Initialize all functions
        initQuantityControls();
        initNumericInputs();
        initFormLoading();
        initDeleteConfirmations();
        initAddProductValidation();
        
        // Add smooth animations
        document.querySelectorAll('.mapping-item-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    });
</script>
@endpush