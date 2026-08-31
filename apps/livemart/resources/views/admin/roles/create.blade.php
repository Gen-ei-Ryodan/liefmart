@extends('layouts.app')

@section('title', 'Tambah Role Baru')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah Role Baru</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}" class="text-decoration-none">Role Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Role</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-plus-circle text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Tambah Role Baru</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.roles.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <!-- Role Information -->
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Informasi Role
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nama Role <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name') }}" 
                                                   placeholder="Contoh: sales_staff" required>
                                            <small class="text-muted">Gunakan format lowercase dengan underscore</small>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="display_name" class="form-label">Display Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('display_name') is-invalid @enderror" 
                                                   id="display_name" name="display_name" value="{{ old('display_name') }}" 
                                                   placeholder="Contoh: Staff Penjualan" required>
                                            @error('display_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Deskripsi</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                                      id="description" name="description" rows="3" 
                                                      placeholder="Deskripsi singkat tentang role ini">{{ old('description') }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_active" 
                                                       name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">
                                                    Role Aktif
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permissions -->
                            <div class="col-md-8">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fas fa-key me-2"></i>
                                            Permissions - Pilih Fitur yang Bisa Diakses
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-success" id="select-all">
                                                <i class="fas fa-check-double me-1"></i>Pilih Semua
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning" id="deselect-all">
                                                <i class="fas fa-times me-1"></i>Hapus Semua
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                        @if($permissions->count() > 0)
                                            @foreach($permissions as $category => $categoryPermissions)
                                                <div class="mb-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="text-primary mb-0">
                                                            <i class="fas fa-folder me-2"></i>
                                                            {{ ucfirst(str_replace('-', ' ', $category)) }}
                                                        </h6>
                                                        <div>
                                                            <button type="button" class="btn btn-sm btn-outline-primary category-select-all" 
                                                                    data-category="{{ $category }}">
                                                                <i class="fas fa-check me-1"></i>Pilih Semua
                                                            </button>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        @foreach($categoryPermissions as $permission)
                                                            <div class="col-md-6 col-lg-4 mb-2">
                                                                <div class="form-check p-3 border rounded {{ in_array($permission->id, old('permissions', [])) ? 'bg-primary bg-opacity-10 border-primary' : 'bg-light' }}">
                                                                    <input class="form-check-input permission-checkbox category-{{ $category }}" 
                                                                           type="checkbox" 
                                                                           id="permission_{{ $permission->id }}" 
                                                                           name="permissions[]" 
                                                                           value="{{ $permission->id }}"
                                                                           {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                                                    <label class="form-check-label w-100" for="permission_{{ $permission->id }}">
                                                                        <div>
                                                                            <strong>{{ $permission->display_name }}</strong>
                                                                        </div>
                                                                        <small class="text-muted">{{ $permission->description }}</small>
                                                                        <div class="mt-1">
                                                                            <span class="badge bg-secondary">{{ $permission->name }}</span>
                                                                        </div>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <hr>
                                            @endforeach
                                        @else
                                            <div class="text-center py-4">
                                                <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Tidak ada permissions yang tersedia</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="fas fa-times me-2"></i>Batal
                                    </a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                                        <i class="fas fa-save me-2"></i>Simpan Role
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-check:hover {
    background-color: var(--bs-primary-bg-subtle) !important;
    border-color: var(--bs-primary) !important;
}

.form-check-input:checked + .form-check-label {
    color: var(--bs-primary);
}

.permission-checkbox:checked + .form-check-label {
    font-weight: 600;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All Permissions
    document.getElementById('select-all').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = true;
            updateCheckboxStyle(cb);
        });
    });

    // Deselect All Permissions
    document.getElementById('deselect-all').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false;
            updateCheckboxStyle(cb);
        });
    });

    // Category Select All
    document.querySelectorAll('.category-select-all').forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            const checkboxes = document.querySelectorAll(`.category-${category}`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
                updateCheckboxStyle(cb);
            });
            
            this.innerHTML = allChecked 
                ? '<i class="fas fa-check me-1"></i>Pilih Semua'
                : '<i class="fas fa-times me-1"></i>Hapus Semua';
        });
    });

    // Individual checkbox change
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateCheckboxStyle(this);
        });
    });

    function updateCheckboxStyle(checkbox) {
        const formCheck = checkbox.closest('.form-check');
        if (checkbox.checked) {
            formCheck.classList.add('bg-primary', 'bg-opacity-10', 'border-primary');
            formCheck.classList.remove('bg-light');
        } else {
            formCheck.classList.remove('bg-primary', 'bg-opacity-10', 'border-primary');
            formCheck.classList.add('bg-light');
        }
    }

    // Auto generate name from display name
    document.getElementById('display_name').addEventListener('input', function() {
        const nameField = document.getElementById('name');
        if (!nameField.value) {
            const name = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s]/g, '')
                .replace(/\s+/g, '_');
            nameField.value = name;
        }
    });
});
</script>
@endsection
