@extends('layouts.app')

@section('title', 'Edit Penerimaan Barang')

@section('content')
    <div class="container-fluid animate__animated animate__fadeIn animate__faster">
        <!-- Basic form setup - no TomSelect -->
        <script>
            // Global parseJsonResponse function
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

            // Global object to provide compatibility with any existing code
            window.barangTomSelect = {
                on: function() { return this; },
                enable: function() { return this; },
                disable: function() { return this; },
                getValue: function() { 
                    const select = document.getElementById('barang_id');
                    return select ? select.value : '';
                },
                setValue: function() { return this; },
                clear: function() { return this; },
                getItem: function() { return { textContent: '' }; }
            };
            
            // Same for other TomSelect objects
            window.satuanTomSelect = {
                setValue: function(value) {
                    const select = document.getElementById('satuan_id');
                    if (select) select.value = value;
                    return this;
                }
            };
            
            window.taxCategoryTomSelect = {
                setValue: function(value) {
                    const select = document.getElementById('tax_category_id');
                    if (select) select.value = value;
                    return this;
                }
            };
            
            // Wait for DOM to be fully loaded
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM fully loaded, initializing dropdowns with standard select elements');
                
                // Load products when main category changes
                const mainCategorySelect = document.getElementById('main_category_id');
                const barangSelect = document.getElementById('barang_id');
                const satuanSelect = document.getElementById('satuan_id');
                const taxCategorySelect = document.getElementById('tax_category_id');
                
                if (mainCategorySelect && mainCategorySelect.value) {
                    // Load products for the selected main category
                    loadProducts(mainCategorySelect.value);
                    
                    // Load tax categories for the selected main category
                    loadTaxCategories(mainCategorySelect.value);
                }
                
                // Handle product selection change
                if (barangSelect) {
                    barangSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        if (!selectedOption || !selectedOption.value) return;
                        
                        console.log('Product selected:', selectedOption.textContent);
                        
                        // Get data attributes from the selected option - handle null safely
                        const hargaHpp = selectedOption.dataset.hargaHpp;
                        const defaultSatuanId = selectedOption.dataset.defaultSatuanId;
                        
                        // Set price if available
                        const hargaInput = document.getElementById('harga_hpp');
                        const isFreeCheckbox = document.getElementById('is_free');
                        
                        if (hargaInput && hargaHpp && hargaHpp !== '0' && hargaHpp !== 'null' && !isFreeCheckbox.checked) {
                            const hargaNum = parseFloat(hargaHpp);
                            if (!isNaN(hargaNum) && hargaNum > 0) {
                                hargaInput.value = hargaNum;
                            }
                        }
                        
                        // Set satuan if available
                        if (satuanSelect && defaultSatuanId && defaultSatuanId !== '' && defaultSatuanId !== 'null') {
                            satuanSelect.value = defaultSatuanId;
                        }
                    });
                }
                
                // Handle refresh button click
                const refreshProductsBtn = document.getElementById('refreshProductsBtn');
                if (refreshProductsBtn) {
                    refreshProductsBtn.addEventListener('click', function() {
                        if (mainCategorySelect && mainCategorySelect.value) {
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            this.disabled = true;
                            
                            loadProducts(mainCategorySelect.value);
                            
                            setTimeout(() => {
                                this.innerHTML = '<i class="fas fa-sync-alt"></i>';
                                this.disabled = false;
                            }, 1000);
                        }
                    });
                }
                
                // Handle reset tax category button click
                const resetTaxCategoryBtn = document.getElementById('resetTaxCategoryBtn');
                if (resetTaxCategoryBtn) {
                    resetTaxCategoryBtn.classList.remove('d-none');
                    resetTaxCategoryBtn.addEventListener('click', function() {
                        if (mainCategorySelect && mainCategorySelect.value) {
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            this.disabled = true;
                            
                            loadTaxCategories(mainCategorySelect.value);
                            
                            setTimeout(() => {
                                this.innerHTML = '<i class="fas fa-sync-alt"></i>';
                                this.disabled = false;
                            }, 1000);
                        }
                    });
                }
                
                // Function to load products with standard select
                function loadProducts(mainCategoryId) {
                    if (!mainCategoryId || !barangSelect) return;
                    
                    console.log('Loading products for category:', mainCategoryId);
                    
                    // Show loading state
                    barangSelect.disabled = true;
                    barangSelect.innerHTML = '<option value="" selected disabled>Loading...</option>';
                    
                    // Fetch products from API
                    fetch(`/api/products?main_category_id=${mainCategoryId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(parseJsonResponse)
                        .then(data => {
                            // Clear the select
                            barangSelect.innerHTML = '<option value="" selected disabled>-- Pilih Barang --</option>';
                            
                            // Add product options
                            if (Array.isArray(data) && data.length > 0) {
                                console.log(`Adding ${data.length} products to dropdown`);
                                
                                data.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = product.text || product.name;
                                    
                                    // Store additional data as data attributes - handle null safely
                                    option.dataset.hargaHpp = product.harga_hpp || '0';
                                    option.dataset.defaultSatuanId = product.default_satuan_id || '';
                                    
                                    barangSelect.appendChild(option);
                                });
                                
                                console.log('Products loaded successfully');
                            } else {
                                console.warn('No products found for category:', mainCategoryId);
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'Tidak ada barang untuk kategori ini';
                                option.disabled = true;
                                barangSelect.appendChild(option);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading products:', error);
                            barangSelect.innerHTML = '<option value="" selected disabled>Error loading products</option>';
                        })
                        .finally(() => {
                            barangSelect.disabled = false;
                        });
                }
                
                // Function to load tax categories with standard select
                function loadTaxCategories(mainCategoryId) {
                    if (!mainCategoryId || !taxCategorySelect) return;
                    
                    console.log('Loading tax categories for category:', mainCategoryId);
                    
                    // Show loading state
                    taxCategorySelect.disabled = true;
                    taxCategorySelect.innerHTML = '<option value="" selected disabled>Loading...</option>';
                    
                    // Fetch tax categories from API
                    fetch(`/api/tax-categories?main_category_id=${mainCategoryId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(parseJsonResponse)
                        .then(data => {
                            // Clear the select
                            taxCategorySelect.innerHTML = '<option value="" selected disabled>-- Pilih Kategori Pajak --</option>';
                            
                            if (data.success && data.tax_categories) {
                                // Filter categories based on main category
                                let categories = data.tax_categories;
                                if (mainCategoryId == 2) { // KOSMETIK - only show PKP and NON PKP
                                    categories = categories.filter(cat => cat.id == 3 || cat.id == 4);
                                }
                                
                                // Add category options
                                categories.forEach(category => {
                                    const option = document.createElement('option');
                                    option.value = category.id;
                                    option.textContent = `${category.name} (${category.tax_percentage}%)`;
                                    taxCategorySelect.appendChild(option);
                                });
                                
                                // Set default value if available
                                const savedTaxCategoryId = {{ $penerimaan->tax_category_id ?? 'null' }};
                                if (savedTaxCategoryId && categories.some(c => c.id == savedTaxCategoryId)) {
                                    taxCategorySelect.value = savedTaxCategoryId;
                                } else if (categories.length > 0) {
                                    taxCategorySelect.value = categories[0].id;
                                }
                                
                                console.log('Tax categories loaded successfully');
                            } else {
                                console.warn('No tax categories found or API error');
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'Error loading categories';
                                option.disabled = true;
                                taxCategorySelect.appendChild(option);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading tax categories:', error);
                            taxCategorySelect.innerHTML = '<option value="" selected disabled>Error loading categories</option>';
                        })
                        .finally(() => {
                            taxCategorySelect.disabled = false;
                        });
                }
            });
        </script>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold mb-1 text-gradient">Edit Penerimaan Barang</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}"
                                class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('penerimaan.index') }}"
                                class="text-decoration-none">Penerimaan</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                    </ol>
                </nav>
            </div>
            <a href="{{ route('penerimaan.show', $penerimaan->id) }}" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
        </div>

        <!-- Alert -->
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error!</strong> Ada beberapa masalah dengan inputan Anda:
                <ul class="mb-0 ps-3 pt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Warning Banner -->
        <div class="alert alert-warning shadow-sm rounded-3" role="alert">
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle fa-lg me-3 mt-1"></i>
                <div>
                    <strong>Perhatian!</strong> Mengubah data penerimaan akan menghapus stok lama dan mengembalikan status ke <strong>Unlocated</strong>. 
                    Pastikan tidak ada transaksi penjualan yang sudah menggunakan stok dari penerimaan ini, karena jika ada maka <strong>tidak bisa diedit</strong>.
                    <br>
                    <small class="text-muted">Setelah disimpan, items akan muncul kembali di daftar <strong>Unlocated</strong> dan perlu dipindahkan ke gudang kembali via fitur transfer.</small>
                </div>
            </div>
        </div>

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
                                            <input type="text" class="form-control bg-light border-0 ps-1"
                                                id="kode_penerimaan" name="kode_penerimaan"
                                                value="{{ $penerimaan->kode_penerimaan }}" readonly>
                                        </div>
                                    </div>

                                    <!-- Kategori Barang -->
                                    <div class="mb-4">
                                        <label for="main_category_id" class="form-label fw-medium">Kategori Barang</label>
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text bg-light border-0">
                                                <i class="fas fa-layer-group text-primary"></i>
                                            </span>
                                            <input type="text" class="form-control bg-light border-0 ps-1"
                                                value="{{ $penerimaan->mainCategory->name ?? session('main_category_name') }}" readonly>
                                            <input type="hidden" name="main_category_id" id="main_category_id"
                                                value="{{ $penerimaan->main_category_id }}">
                                        </div>
                                        <div class="form-text text-muted">Kategori yang dipilih saat login</div>
                                    </div>

                                    <!-- Replace the hidden tax_category_id field with a visible dropdown -->
                                    <div class="mb-4">
                                        <label for="tax_category_id" class="form-label fw-medium">Kategori Pajak <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text bg-light border-0">
                                                <i class="fas fa-percentage text-primary"></i>
                                            </span>
                                            <select class="form-select" id="tax_category_id" name="tax_category_id" required>
                                                <option value="" disabled selected>-- Pilih Kategori Pajak --</option>
                                            </select>
                                            <button type="button" class="btn btn-outline-secondary d-none" id="resetTaxCategoryBtn" onclick="window.resetTaxCategorySelect()" title="Reset dropdown jika bermasalah">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                        <div class="form-text text-muted">Kategori pajak sesuai dengan kategori barang</div>
                                    </div>
                                </div>

                                <!-- Right side - Additional info -->
                                <div class="col-md-6">
                                    <!-- Nomor PO -->
                                    <div class="mb-4">
                                        <label for="nomor_po" class="form-label fw-medium">Nomor PO <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control rounded-3" id="nomor_po" name="nomor_po"
                                            value="{{ $penerimaan->nomor_po }}" placeholder="Masukkan nomor PO" required>
                                    </div>

                                    <!-- Tanggal Penerimaan -->
                                    <div class="mb-4">
                                        <label for="tanggal_penerimaan" class="form-label fw-medium">Tanggal Penerimaan
                                            <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text border-end-0">
                                                <i class="fas fa-calendar-alt text-primary"></i>
                                            </span>
                                            <input type="date" class="form-control border-start-0 ps-0 rounded-3"
                                                id="tanggal_penerimaan" name="tanggal_penerimaan"
                                                value="{{ $penerimaan->tanggal_penerimaan->format('Y-m-d') }}" required>
                                        </div>
                                    </div>

                                    <!-- Metode Pembayaran -->
                                    <div class="mb-4">
                                        <label for="metode_pembayaran" class="form-label fw-medium">Metode Pembayaran <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select rounded-3" id="metode_pembayaran"
                                            name="metode_pembayaran" required>
                                            <option value="Cash"
                                                {{ $penerimaan->metode_pembayaran == 'Cash' ? 'selected' : '' }}>Cash
                                            </option>
                                            <option value="Jatuh Tempo"
                                                {{ $penerimaan->metode_pembayaran == 'Jatuh Tempo' ? 'selected' : '' }}>
                                                Jatuh Tempo</option>
                                        </select>
                                    </div>

                                    <!-- Tanggal Jatuh Tempo (Tersembunyi secara default) -->
                                    <div class="mb-4" id="jatuhTempoContainer"
                                        style="{{ $penerimaan->metode_pembayaran != 'Jatuh Tempo' ? 'display: none;' : '' }} transition: all 0.3s ease;">
                                        <label for="tanggal_jatuh_tempo" class="form-label fw-medium">Tanggal Jatuh Tempo
                                            <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text border-end-0">
                                                <i class="fas fa-calendar-alt text-primary"></i>
                                            </span>
                                            <input type="date" class="form-control border-start-0 ps-0 rounded-3"
                                                id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo"
                                                value="{{ $penerimaan->tanggal_jatuh_tempo ? $penerimaan->tanggal_jatuh_tempo->format('Y-m-d') : '' }}">
                                        </div>
                                        <div class="form-text text-muted">Tanggal jatuh tempo pembayaran</div>
                                    </div>
                                </div>

                                <!-- Full width - Catatan -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="catatan" class="form-label fw-medium">Catatan</label>
                                        <textarea class="form-control rounded-3" id="catatan" name="catatan" rows="3"
                                            placeholder="Catatan tambahan (opsional)">{{ $penerimaan->catatan }}</textarea>
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
                                    <tbody>
                                        @if ($penerimaan->details->count() > 0)
                                            @foreach ($penerimaan->details as $index => $detail)
                                                <tr id="item-{{ $index }}" class="item-row">
                                                    <td class="ps-4">
                                                        <p class="fw-medium mb-0">{{ $detail->product->name }}</p>
                                                    </td>
                                                    <td class="text-center">
                                                        <span
                                                            class="badge bg-light text-dark rounded-pill px-3 py-2">{{ $detail->qty }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        {{ $detail->satuan->name }}
                                                    </td>
                                                    <td class="text-end">
                                                        @if ($detail->is_free)
                                                            <span class="badge bg-secondary rounded-pill">Free</span>
                                                        @else
                                                            Rp {{ number_format($detail->harga_hpp, 0, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @php
                                                            $hasDiscount = false;
                                                        @endphp

                                                        @for ($i = 1; $i <= 5; $i++)
                                                            @php
                                                                $persenField = "diskon_persen_$i";
                                                                $nominalField = "diskon_nominal_$i";
                                                            @endphp

                                                            @if ($detail->$persenField > 0)
                                                                <span
                                                                    class="badge bg-info text-dark rounded-pill me-1">D{{ $i }}:
                                                                    {{ $detail->$persenField }}%</span>
                                                                @php $hasDiscount = true; @endphp
                                                            @elseif($detail->$nominalField > 0)
                                                                <span
                                                                    class="badge bg-info text-dark rounded-pill me-1">D{{ $i }}:
                                                                    Rp
                                                                    {{ number_format($detail->$nominalField, 0, ',', '.') }}</span>
                                                                @php $hasDiscount = true; @endphp
                                                            @endif
                                                        @endfor

                                                        @if (!$hasDiscount && !$detail->is_free)
                                                            -
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        @if ($detail->is_free)
                                                            <span class="badge bg-secondary rounded-pill">Free</span>
                                                        @else
                                                            Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td class="text-center pe-4">
                                                        <button type="button" class="btn-delete"
                                                            data-id="{{ $index }}" title="Hapus item">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr id="emptyRow">
                                                <td colspan="6" class="text-center py-5">
                                                    <div class="py-4">
                                                        <div class="mb-3">
                                                            <i class="fas fa-box-open fa-3x text-muted opacity-50"></i>
                                                        </div>
                                                        <h6 class="fw-normal mb-1">Belum ada barang</h6>
                                                        <p class="text-muted mb-0 small">Tambahkan barang menggunakan form
                                                            di bawah</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <!-- Form untuk tambah barang -->
                            <div class="p-4 border-top" style="background-color: rgba(181, 198, 224, 0.05);">
                                <div class="row g-3 mb-3 align-items-end">
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label small fw-medium mb-2">Nama Barang <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <select class="form-select shadow-none" id="barang_id">
                                                <option value="" selected disabled>-- Pilih Barang --</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshProductsBtn" title="Refresh products list">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-1 col-md-2 col-sm-3">
                                        <label class="form-label small fw-medium mb-2">Qty <span
                                                class="text-danger">*</span></label>
                                        <input type="number" class="form-control shadow-none text-center" id="qty"
                                            min="0.01" step="0.01" placeholder="0">
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-4">
                                        <label class="form-label small fw-medium mb-2">Satuan <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select shadow-none" id="satuan_id">
                                            <option value="" selected disabled>-- Satuan --</option>
                                            @foreach ($satuan as $item)
                                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-2 col-md-4 col-sm-5">
                                        <label class="form-label small fw-medium mb-2">Harga <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text border-end-0 bg-light">Rp</span>
                                            <input type="number" class="form-control border-start-0 ps-0 shadow-none"
                                                id="harga_hpp" min="0" placeholder="0">
                                        </div>
                                    </div>
                                </div>

                                <!-- Discount System - 5 Levels -->
                                <div class="p-3 mb-3 rounded-3 border border-1"
                                    style="background-color: rgba(65, 95, 255, 0.03);">
                                    <div class="mb-2">
                                        <h6 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i> Sistem Diskon (5
                                            Level)</h6>
                                        <p class="text-muted small mb-0">Isi hanya satu jenis diskon (% atau Rp) per level
                                        </p>
                                    </div>

                                    <div class="row g-2">
                                        <!-- Discount Level 1 -->
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Diskon 1 (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center discount-input"
                                                    id="diskon_persen_1" min="0" max="100" step="0.01"
                                                    placeholder="0">
                                                <span class="input-group-text bg-light">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Nominal 1</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Rp</span>
                                                <input type="number" class="form-control discount-input"
                                                    id="diskon_nominal_1" min="0" placeholder="0">
                                            </div>
                                        </div>

                                        <!-- Discount Level 2 -->
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Diskon 2 (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center discount-input"
                                                    id="diskon_persen_2" min="0" max="100" step="0.01"
                                                    placeholder="0">
                                                <span class="input-group-text bg-light">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Nominal 2</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Rp</span>
                                                <input type="number" class="form-control discount-input"
                                                    id="diskon_nominal_2" min="0" placeholder="0">
                                            </div>
                                        </div>

                                        <!-- Discount Level 3 -->
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Diskon 3 (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center discount-input"
                                                    id="diskon_persen_3" min="0" max="100" step="0.01"
                                                    placeholder="0">
                                                <span class="input-group-text bg-light">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Nominal 3</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Rp</span>
                                                <input type="number" class="form-control discount-input"
                                                    id="diskon_nominal_3" min="0" placeholder="0">
                                            </div>
                                        </div>

                                        <!-- Discount Level 4 -->
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Diskon 4 (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center discount-input"
                                                    id="diskon_persen_4" min="0" max="100" step="0.01"
                                                    placeholder="0">
                                                <span class="input-group-text bg-light">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Nominal 4</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Rp</span>
                                                <input type="number" class="form-control discount-input"
                                                    id="diskon_nominal_4" min="0" placeholder="0">
                                            </div>
                                        </div>

                                        <!-- Discount Level 5 -->
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Diskon 5 (%)</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center discount-input"
                                                    id="diskon_persen_5" min="0" max="100" step="0.01"
                                                    placeholder="0">
                                                <span class="input-group-text bg-light">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-4 col-6">
                                            <label class="form-label small fw-medium">Nominal 5</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light">Rp</span>
                                                <input type="number" class="form-control discount-input"
                                                    id="diskon_nominal_5" min="0" placeholder="0">
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
                                        <button type="button" class="btn btn-primary rounded-pill px-4"
                                            id="btnTambahBarang">
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
                                <h3 class="text-primary fw-bold mb-0" id="totalHargaDisplay">Rp
                                    {{ number_format(round($penerimaan->total_harga), 0, ',', '.') }}</h3>
                            </div>
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-lg btn-primary py-3 rounded-pill shadow-sm">
                                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <!-- TomSelect CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    
    <!-- TomSelect JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Reset function for tax category dropdown
            window.resetTaxCategorySelect = function() {
                const mainCategoryId = document.getElementById('main_category_id')?.value;
                const taxCategorySelect = document.getElementById('tax_category_id');
                
                if (!mainCategoryId || !taxCategorySelect) return false;
                
                // Show loading state
                taxCategorySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
                taxCategorySelect.disabled = true;
                
                // Load tax categories
                fetch(`/api/tax-categories?main_category_id=${mainCategoryId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(parseJsonResponse)
                    .then(data => {
                        taxCategorySelect.innerHTML = '<option value="" disabled selected>-- Pilih Kategori Pajak --</option>';
                        
                        if (data.success && data.tax_categories) {
                            // Filter categories based on main category
                            let categories = data.tax_categories;
                            if (mainCategoryId == 2) { // KOSMETIK - only show PKP and NON PKP
                                categories = categories.filter(cat => cat.id == 3 || cat.id == 4);
                            }
                            
                            // Add category options
                            categories.forEach(category => {
                                const option = document.createElement('option');
                                option.value = category.id;
                                option.textContent = `${category.name} (${category.tax_percentage}%)`;
                                taxCategorySelect.appendChild(option);
                            });
                            
                            // Set default value if available
                            const savedTaxCategoryId = {{ $penerimaan->tax_category_id ?? 'null' }};
                            if (savedTaxCategoryId && categories.some(c => c.id == savedTaxCategoryId)) {
                                taxCategorySelect.value = savedTaxCategoryId;
                            } else if (categories.length > 0) {
                                taxCategorySelect.value = categories[0].id;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading tax categories:', error);
                        taxCategorySelect.innerHTML = '<option value="" disabled selected>Error loading categories</option>';
                    })
                    .finally(() => {
                        taxCategorySelect.disabled = false;
                    });
                    
                return true;
            };
            
            // Initialize simple TomSelect for product dropdown only
            try {
                const barangSelect = document.getElementById('barang_id');
                if (barangSelect) {
                    console.log("Initializing TomSelect for product dropdown");
                    
                    // Add immediate flag to prevent double initialization
                    if (window.barangTomSelectInitialized) {
                        console.log("Product TomSelect already initialized, skipping");
                        return;
                    }
                    
                    // Initialize main category ID
                    const mainCategoryId = document.getElementById('main_category_id')?.value;
                    if (!mainCategoryId) {
                        console.error("Main category ID not found");
                        return;
                    }
                    
                    // First load the products with standard approach to ensure we have options
                    barangSelect.disabled = true;
                    barangSelect.innerHTML = '<option value="" selected disabled>Loading...</option>';
                    
                    // Fetch products before initializing TomSelect
                    fetch(`/api/products?main_category_id=${mainCategoryId}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(parseJsonResponse)
                        .then(data => {
                            // Clear select element
                            barangSelect.innerHTML = '';
                            
                            // Add default option
                            const defaultOption = document.createElement('option');
                            defaultOption.value = '';
                            defaultOption.textContent = '-- Pilih Barang --';
                            barangSelect.appendChild(defaultOption);
                            
                            // Add product options
                            if (Array.isArray(data) && data.length > 0) {
                                console.log(`Adding ${data.length} products to select`);
                                
                                data.forEach(product => {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = product.text || product.name;
                                    
                                    // Store additional data as data attributes - handle null safely
                                    option.dataset.hargaHpp = product.harga_hpp || '0';
                                    option.dataset.defaultSatuanId = product.default_satuan_id || '';
                                    
                                    barangSelect.appendChild(option);
                                });
                            }
                            
                            // Enable select element
                            barangSelect.disabled = false;
                            
                            // Create TomSelect instance
                            setTimeout(() => {
                                // Now initialize TomSelect on the populated select
                                const tomSelectConfig = {
                                    plugins: ['clear_button'],
                                    create: false,
                                    maxItems: 1,
                                    allowEmptyOption: true,
                                    closeAfterSelect: true,
                                    render: {
                                        option: function(data, escape) {
                                            return `<div class="py-2 px-3">
                                                <div class="mb-0 fw-medium">${escape(data.text)}</div>
                                            </div>`;
                                        },
                                        item: function(data, escape) {
                                            return `<div>${escape(data.text)}</div>`;
                                        }
                                    },
                                    onChange: function(value) {
                                        if (!value) return;
                                        
                                        // Get selected option data from the original select element
                                        const originalSelect = document.getElementById('barang_id');
                                        const selectedOption = originalSelect.options[originalSelect.selectedIndex];
                                        
                                        if (!selectedOption) return;
                                        
                                        // Get data attributes - handle null safely
                                        const hargaHpp = selectedOption.dataset.hargaHpp;
                                        const defaultSatuanId = selectedOption.dataset.defaultSatuanId;
                                        
                                        // Set price if available
                                        const hargaInput = document.getElementById('harga_hpp');
                                        const isFreeCheckbox = document.getElementById('is_free');
                                        
                                        if (hargaInput && hargaHpp && hargaHpp !== '0' && hargaHpp !== 'null' && !isFreeCheckbox.checked) {
                                            const hargaNum = parseFloat(hargaHpp);
                                            if (!isNaN(hargaNum) && hargaNum > 0) {
                                                hargaInput.value = hargaNum;
                                            }
                                        }
                                        
                                        // Set satuan if available
                                        if (defaultSatuanId && defaultSatuanId !== '' && defaultSatuanId !== 'null') {
                                            const satuanInput = document.getElementById('satuan_id');
                                            if (satuanInput) {
                                                satuanInput.value = defaultSatuanId;
                                            }
                                        }
                                    }
                                };
                                
                                try {
                                    const tomInstance = new TomSelect(barangSelect, tomSelectConfig);
                                    
                                    // Store instance in window object
                                    window.barangTomSelect = tomInstance;
                                    window.barangTomSelectInitialized = true;
                                    
                                    console.log("Product TomSelect initialized successfully");
                                    
                                    // Force re-render of TomSelect
                                    setTimeout(() => {
                                        if (window.barangTomSelect) {
                                            window.barangTomSelect.clear();
                                            window.barangTomSelect.clearOptions();
                                            window.barangTomSelect.sync();
                                            console.log("Re-rendered TomSelect");
                                        }
                                    }, 200);
                                } catch (e) {
                                    console.error("Error creating TomSelect instance:", e);
                                }
                            }, 100); // Small delay to ensure DOM is ready
                        })
                        .catch(error => {
                            console.error("Error loading products:", error);
                            barangSelect.innerHTML = '<option value="" disabled selected>Error loading products</option>';
                            barangSelect.disabled = false;
                        });
                    
                    // Add refresh button functionality
                    const refreshProductsBtn = document.getElementById('refreshProductsBtn');
                    if (refreshProductsBtn) {
                        refreshProductsBtn.addEventListener('click', function() {
                            const mainCategoryId = document.getElementById('main_category_id')?.value;
                            if (!mainCategoryId) return;
                            
                            // Show loading state
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                            this.disabled = true;
                            
                            // Reset and reload
                            if (window.barangTomSelect) {
                                try {
                                    // Destroy existing instance
                                    window.barangTomSelect.destroy();
                                    window.barangTomSelectInitialized = false;
                                    
                                    // Re-initialize
                                    setTimeout(() => {
                                        const barangSelect = document.getElementById('barang_id');
                                        if (barangSelect) {
                                            // Load products
                                            barangSelect.disabled = true;
                                            barangSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
                                            
                                            fetch(`/api/products?main_category_id=${mainCategoryId}`, {
                                                headers: {
                                                    'Accept': 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest'
                                                }
                                            })
                                                .then(parseJsonResponse)
                                                .then(data => {
                                                    // Clear select element
                                                    barangSelect.innerHTML = '';
                                                    
                                                    // Add default option
                                                    const defaultOption = document.createElement('option');
                                                    defaultOption.value = '';
                                                    defaultOption.textContent = '-- Pilih Barang --';
                                                    barangSelect.appendChild(defaultOption);
                                                    
                                                    // Add product options
                                                    if (Array.isArray(data) && data.length > 0) {
                                                        data.forEach(product => {
                                                            const option = document.createElement('option');
                                                            option.value = product.id;
                                                            option.textContent = product.text || product.name;
                                                            option.dataset.hargaHpp = product.harga_hpp;
                                                            option.dataset.defaultSatuanId = product.default_satuan_id;
                                                            barangSelect.appendChild(option);
                                                        });
                                                    }
                                                    
                                                    // Enable select element
                                                    barangSelect.disabled = false;
                                                    
                                                    // Re-initialize TomSelect
                                                    try {
                                                        window.barangTomSelect = new TomSelect(barangSelect, {
                                                            plugins: ['clear_button'],
                                                            create: false,
                                                            maxItems: 1,
                                                            allowEmptyOption: true,
                                                            closeAfterSelect: true
                                                        });
                                                        window.barangTomSelectInitialized = true;
                                                        console.log("TomSelect re-initialized successfully");
                                                    } catch (e) {
                                                        console.error("Error re-initializing TomSelect:", e);
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error("Error reloading products:", error);
                                                    barangSelect.innerHTML = '<option value="" disabled selected>Error loading products</option>';
                                                    barangSelect.disabled = false;
                                                })
                                                .finally(() => {
                                                    // Reset button after delay
                                                    setTimeout(() => {
                                                        refreshProductsBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                                                        refreshProductsBtn.disabled = false;
                                                    }, 500);
                                                });
                                        }
                                    }, 100);
                                } catch (e) {
                                    console.error("Error during refresh:", e);
                                    refreshProductsBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                                    refreshProductsBtn.disabled = false;
                                }
                            } else {
                                console.error("barangTomSelect not available");
                                refreshProductsBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                                refreshProductsBtn.disabled = false;
                            }
                        });
                    }
                }
            } catch (error) {
                console.error("Error initializing TomSelect:", error);
                // Fall back to standard select approach if TomSelect fails
            }
        });
    </script>

    <!-- Add missing JavaScript functions for item management -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let itemCounter = {{ $penerimaan->details->count() }};
            const tabelDetailBarang = document.getElementById('tabelDetailBarang');
            const emptyRow = document.getElementById('emptyRow');
            const btnTambahBarang = document.getElementById('btnTambahBarang');
            const formPenerimaan = document.querySelector('form');
            
            // Array to store detail items
            let detailItems = [];
            
            // Initialize existing items - Store ALL data in detailItems array (NO HIDDEN INPUTS)
            @if($penerimaan->details->count() > 0)
                @foreach($penerimaan->details as $index => $detail)
                    detailItems.push({
                        id: {{ $index }},
                        barang_id: {{ $detail->product_id }},
                        barangText: '{{ addslashes($detail->product->name) }}',
                        qty: {{ $detail->qty }},
                        satuan_id: {{ $detail->satuan_id }},
                        satuanText: '{{ addslashes($detail->satuan->name) }}',
                        harga_hpp: {{ $detail->harga_hpp }},
                        diskon_persen_1: {{ $detail->diskon_persen_1 }},
                        diskon_persen_2: {{ $detail->diskon_persen_2 }},
                        diskon_persen_3: {{ $detail->diskon_persen_3 }},
                        diskon_persen_4: {{ $detail->diskon_persen_4 }},
                        diskon_persen_5: {{ $detail->diskon_persen_5 }},
                        diskon_nominal_1: {{ $detail->diskon_nominal_1 }},
                        diskon_nominal_2: {{ $detail->diskon_nominal_2 }},
                        diskon_nominal_3: {{ $detail->diskon_nominal_3 }},
                        diskon_nominal_4: {{ $detail->diskon_nominal_4 }},
                        diskon_nominal_5: {{ $detail->diskon_nominal_5 }},
                        is_free: {{ $detail->is_free ? 1 : 0 }},
                        subtotal: {{ $detail->subtotal }},
                        catatan: null
                    });
                @endforeach
            @endif
            
            // Format currency function with 2 decimal places for individual items
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
            
            // Calculate subtotal function with 2 decimal precision
            function calculateSubtotal(qty, harga, diskonPersen, diskonNominal, isFree) {
                if (isFree) return 0;
                
                let subtotal = Math.round((qty * harga) * 100) / 100;
                
                // Apply discounts
                for (let i = 0; i < 5; i++) {
                    if (diskonPersen[i] > 0) {
                        subtotal = Math.round((subtotal - (subtotal * diskonPersen[i] / 100)) * 100) / 100;
                    } else if (diskonNominal[i] > 0) {
                        subtotal = Math.round((subtotal - diskonNominal[i]) * 100) / 100;
                    }
                }
                
                return Math.max(0, subtotal);
            }
            
            // Update total harga display
            function updateTotalHarga() {
                let total = 0;
                detailItems.forEach(item => {
                    total += item.subtotal;
                });
                
                const totalHargaDisplay = document.getElementById('totalHargaDisplay');
                if (totalHargaDisplay) {
                    totalHargaDisplay.textContent = `Rp ${formatRupiahTotal(total)}`;
                }
            }
            
            // Add item function
            function addItem() {
                const qtyInput = document.getElementById('qty');
                const satuanSelect = document.getElementById('satuan_id');
                const hargaInput = document.getElementById('harga_hpp');
                const isFreeCheckbox = document.getElementById('is_free');
                
                // Validation - use TomSelect for barang
                if (!window.barangTomSelect || !window.barangTomSelect.getValue()) {
                    alert('Pilih barang terlebih dahulu');
                    return;
                }
                
                // Validation with safe parsing
                const qtyVal = qtyInput.value ? parseFloat(qtyInput.value) : 0;
                if (!qtyInput.value || isNaN(qtyVal) || qtyVal <= 0) {
                    alert('Masukkan quantity yang valid');
                    qtyInput.focus();
                    return;
                }
                
                if (!satuanSelect.value) {
                    alert('Pilih satuan terlebih dahulu');
                    satuanSelect.focus();
                    return;
                }
                
                if (!isFreeCheckbox.checked) {
                    const hargaVal = hargaInput.value ? parseFloat(hargaInput.value) : 0;
                    if (!hargaInput.value || isNaN(hargaVal) || hargaVal < 0) {
                        alert('Masukkan harga yang valid');
                        hargaInput.focus();
                        return;
                    }
                }
                
                // Get selected product info using TomSelect
                const productId = window.barangTomSelect.getValue();
                const barangItem = window.barangTomSelect.getItem(productId);
                const productName = barangItem ? barangItem.textContent : '';
                const satuanName = satuanSelect.options[satuanSelect.selectedIndex].textContent;
                
                // Parse values safely - handle null/empty/NaN
                const qtyValue = qtyInput.value ? parseFloat(qtyInput.value) : 0;
                const qty = isNaN(qtyValue) || qtyValue <= 0 ? 0 : qtyValue;
                
                const satuanId = satuanSelect.value;
                
                const hargaValue = hargaInput.value ? parseFloat(hargaInput.value) : 0;
                const harga = isFreeCheckbox.checked ? 0 : (isNaN(hargaValue) ? 0 : hargaValue);
                
                const isFree = isFreeCheckbox.checked;
                
                // Get discount values - handle null/empty safely
                const diskonPersen = [];
                const diskonNominal = [];
                for (let i = 1; i <= 5; i++) {
                    const persenInput = document.getElementById(`diskon_persen_${i}`);
                    const nominalInput = document.getElementById(`diskon_nominal_${i}`);
                    const persenValue = persenInput && persenInput.value ? parseFloat(persenInput.value) : 0;
                    const nominalValue = nominalInput && nominalInput.value ? parseFloat(nominalInput.value) : 0;
                    diskonPersen.push(isNaN(persenValue) ? 0 : persenValue);
                    diskonNominal.push(isNaN(nominalValue) ? 0 : nominalValue);
                }
                
                // Calculate subtotal
                const subtotal = calculateSubtotal(qty, harga, diskonPersen, diskonNominal, isFree);
                
                // Create item object - Store ALL data (NO HIDDEN INPUTS)
                const newItem = {
                    id: itemCounter,
                    barang_id: productId ? (isNaN(parseInt(productId)) ? 0 : parseInt(productId)) : 0,
                    barangText: productName || '',
                    qty: qty || 0,
                    satuan_id: satuanId ? (isNaN(parseInt(satuanId)) ? 0 : parseInt(satuanId)) : 0,
                    satuanText: satuanName || '',
                    harga_hpp: harga || 0,
                    diskon_persen_1: diskonPersen[0] || 0,
                    diskon_persen_2: diskonPersen[1] || 0,
                    diskon_persen_3: diskonPersen[2] || 0,
                    diskon_persen_4: diskonPersen[3] || 0,
                    diskon_persen_5: diskonPersen[4] || 0,
                    diskon_nominal_1: diskonNominal[0] || 0,
                    diskon_nominal_2: diskonNominal[1] || 0,
                    diskon_nominal_3: diskonNominal[2] || 0,
                    diskon_nominal_4: diskonNominal[3] || 0,
                    diskon_nominal_5: diskonNominal[4] || 0,
                    is_free: isFree ? 1 : 0,
                    subtotal: subtotal || 0,
                    catatan: null
                };
                
                // Add to array
                detailItems.push(newItem);
                
                // Create table row
                const newRow = document.createElement('tr');
                newRow.id = `item-${itemCounter}`;
                newRow.className = 'item-row';
                
                // Create discount badges HTML
                let discountBadgesHTML = '';
                for (let i = 0; i < 5; i++) {
                    if (diskonPersen[i] > 0) {
                        discountBadgesHTML += `<span class="badge bg-info text-dark me-1">${diskonPersen[i]}%</span>`;
                    }
                    if (diskonNominal[i] > 0) {
                        discountBadgesHTML += `<span class="badge bg-info text-dark me-1">Rp ${formatRupiah(diskonNominal[i])}</span>`;
                    }
                }
                
                newRow.innerHTML = `
                    <td class="ps-4">
                        <p class="fw-medium mb-0">${productName}</p>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark rounded-pill px-3 py-2">${qty}</span>
                    </td>
                    <td class="text-center">
                        ${satuanName}
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
                        <button type="button" class="btn-delete" data-id="${itemCounter}" title="Hapus item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                // Add to table
                if (emptyRow) {
                    emptyRow.style.display = 'none';
                }
                tabelDetailBarang.querySelector('tbody').appendChild(newRow);
                
                // Clear form
                if (window.barangTomSelect) {
                    window.barangTomSelect.clear();
                }
                qtyInput.value = '';
                satuanSelect.value = '';
                hargaInput.value = '';
                isFreeCheckbox.checked = false;
                
                // Clear discount inputs
                for (let i = 1; i <= 5; i++) {
                    document.getElementById(`diskon_persen_${i}`).value = '';
                    document.getElementById(`diskon_nominal_${i}`).value = '';
                }
                
                // Update total
                updateTotalHarga();
                
                // Increment counter
                itemCounter++;
            }
            
            // Remove item function
            function removeItem(id) {
                // Remove from array
                detailItems = detailItems.filter(item => item.id != id);
                
                // Remove from table
                const row = document.getElementById(`item-${id}`);
                if (row) {
                    row.remove();
                }
                
                // Show empty row if no items
                if (detailItems.length === 0 && emptyRow) {
                    emptyRow.style.display = '';
                }
                
                // Update total
                updateTotalHarga();
            }
            
            // Event listeners
            if (btnTambahBarang) {
                btnTambahBarang.addEventListener('click', addItem);
            }
            
            // Delete button event delegation
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-delete')) {
                    const btn = e.target.closest('.btn-delete');
                    const id = btn.getAttribute('data-id');
                    removeItem(id);
                }
            });
            
            // Initialize total
            updateTotalHarga();
            
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
            
            // AJAX Form submission - Full JSON approach for EDIT
            async function handleSubmit(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                if (detailItems.length === 0) {
                    alert('Mohon tambahkan minimal satu barang sebelum menyimpan.');
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
                
                const penerimaanId = {{ $penerimaan->id }};
                
                try {
                    // Step 1: POST /penerimaan/{id}/update-header
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
                        nomor_po: document.getElementById('nomor_po').value,
                        tanggal_penerimaan: document.getElementById('tanggal_penerimaan').value,
                        metode_pembayaran: metodePembayaran,
                        tanggal_jatuh_tempo: tanggalJatuhTempo,
                        catatan: (document.getElementById('catatan').value || '').trim() || null
                    };
                    
                    const headerResponse = await fetch(`/penerimaan/${penerimaanId}/update-header`, {
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
                        throw new Error(headerResult.message || 'Gagal mengupdate header penerimaan');
                    }
                    
                    // Step 2: POST /penerimaan/{id}/clear-details
                    const clearResponse = await fetch(`/penerimaan/${penerimaanId}/clear-details`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({})
                    });
                    
                    const clearResult = await parseJsonResponse(clearResponse);
                    
                    if (!clearResult.success) {
                        throw new Error(clearResult.message || 'Gagal membersihkan detail penerimaan');
                    }
                    
                    // Step 3: POST /penerimaan/{id}/store-batch-details (JSON dengan items)
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
                    
                    // Step 4: POST /penerimaan/{id}/finalize-update
                    const finalizeResponse = await fetch(`/penerimaan/${penerimaanId}/finalize-update`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ old_data: headerResult.old_data })
                    });
                    
                    const finalizeResult = await parseJsonResponse(finalizeResponse);
                    
                    if (!finalizeResult.success) {
                        throw new Error(finalizeResult.message || 'Gagal finalisasi update penerimaan');
                    }
                    
                    // Success - redirect with success flag
                    window.location.href = '{{ route("penerimaan.index") }}?success=1';
                    
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
        /* TomSelect Custom Styling */
        .ts-wrapper {
            border-radius: 0.375rem;
        }
        
        .ts-wrapper.single .ts-control {
            background-color: #fff;
            border-color: #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
            font-size: 1rem;
            min-height: 38px;
            height: auto;
        }
        
        .ts-wrapper.focus .ts-control {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .ts-dropdown {
            border-radius: 0.375rem;
            border-color: rgba(0, 0, 0, 0.15);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 1060 !important; /* Higher z-index to ensure dropdown shows above other elements */
            width: 400px !important; /* Make dropdown wider for product names */
            max-width: 90vw; /* But not too wide on mobile */
        }
        
        .ts-dropdown .option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .ts-dropdown .active {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .ts-dropdown .option:hover {
            background-color: #e9ecef;
        }
        
        /* Flash highlight animation for inputs */
        .flash-highlight {
            background-color: rgba(0, 123, 255, 0.1);
            transition: background-color 0.75s ease;
        }

        /* Animation for value changes */
        .animate-value {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        /* Styling for delete buttons */
        .btn-delete {
            background: none;
            border: none;
            color: #dc3545;
            opacity: 0.7;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Item row transitions */
        .item-row {
            transition: opacity 0.3s ease;
        }

        /* Discount layout improvements */
        .discount-section {
            background-color: rgba(65, 95, 255, 0.03);
            border-radius: 0.5rem;
            border: 1px solid rgba(65, 95, 255, 0.2);
        }
    </style>
@endpush
