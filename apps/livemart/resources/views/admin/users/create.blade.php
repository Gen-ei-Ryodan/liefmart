@extends('layouts.app')

@section('title', 'Tambah User Baru')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Tambah User Baru</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">User Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah User</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-user-plus text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Tambah User Baru</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.store') }}" method="POST" id="userForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-user me-2"></i>
                                            Informasi User
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name') }}" 
                                                   placeholder="Masukkan nama lengkap" required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="login_type" class="form-label">Tipe Login <span class="text-danger">*</span></label>
                                            <select class="form-select @error('login_type') is-invalid @enderror" 
                                                    id="login_type" name="login_type" required onchange="toggleLoginFields()">
                                                <option value="">-- Pilih Tipe Login --</option>
                                                <option value="email" {{ old('login_type') === 'email' ? 'selected' : '' }}>Email</option>
                                                <option value="username" {{ old('login_type') === 'username' ? 'selected' : '' }}>Username</option>
                                            </select>
                                            @error('login_type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Email Field -->
                                        <div class="mb-3" id="emailField" style="display: none;">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                                   id="email" name="email" value="{{ old('email') }}" 
                                                   placeholder="email@example.com">
                                            <small class="text-muted">Email untuk login</small>
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Username Field -->
                                        <div class="mb-3" id="usernameField" style="display: none;">
                                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('username') is-invalid @enderror" 
                                                   id="username" name="username" value="{{ old('username') }}" 
                                                   placeholder="username">
                                            <small class="text-muted">Username untuk login</small>
                                            @error('username')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                                   id="password" name="password" placeholder="Minimal 8 karakter" required>
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="mb-3">
                                            <label for="password_confirmation" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" 
                                                   id="password_confirmation" name="password_confirmation" 
                                                   placeholder="Ulangi password" required>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_active" 
                                                       name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">
                                                    User Aktif
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-shield-alt me-2"></i>
                                            Role & Permissions
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Role Selection -->
                                        <div class="mb-3">
                                            <label for="role_id" class="form-label">Pilih Role <span class="text-danger">*</span></label>
                                            <select class="form-select @error('role_id') is-invalid @enderror" 
                                                    id="role_id" name="role_id" required onchange="showRoleInfo()">
                                                <option value="">-- Pilih Role --</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}" 
                                                            data-name="{{ $role->name }}"
                                                            data-description="{{ $role->description }}"
                                                            {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                        {{ $role->display_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('role_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <!-- Role Information -->
                                        <div id="roleInfo" style="display: none;">
                                            <div class="alert alert-info">
                                                <h6><i class="fas fa-info-circle me-2"></i>Informasi Role</h6>
                                                <div id="roleDescription"></div>
                                                <div id="rolePermissions" class="mt-2"></div>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="fas fa-times me-2"></i>Batal
                                    </a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                                        <i class="fas fa-save me-2"></i>Simpan User
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

<script>
// Role data for frontend
const roleData = @json($roles->toArray());

function toggleLoginFields() {
    const loginTypeSelect = document.getElementById('login_type');
    const emailField = document.getElementById('emailField');
    const usernameField = document.getElementById('usernameField');
    
    const emailInput = document.getElementById('email');
    const usernameInput = document.getElementById('username');

    if (loginTypeSelect.value === 'email') {
        // Show email field
        emailField.style.display = 'block';
        usernameField.style.display = 'none';
        
        // Set required attributes
        emailInput.required = true;
        usernameInput.required = false;
        
    } else if (loginTypeSelect.value === 'username') {
        // Show username field
        emailField.style.display = 'none';
        usernameField.style.display = 'block';
        
        // Set required attributes
        emailInput.required = false;
        usernameInput.required = true;
        
    } else {
        // Hide all fields
        emailField.style.display = 'none';
        usernameField.style.display = 'none';
        
        // Remove required attributes
        emailInput.required = false;
        usernameInput.required = false;
    }
}

function showRoleInfo() {
    const roleSelect = document.getElementById('role_id');
    const roleInfo = document.getElementById('roleInfo');
    const roleDescription = document.getElementById('roleDescription');
    
    if (roleSelect.value) {
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
        const roleName = selectedOption.getAttribute('data-name');
        const description = selectedOption.getAttribute('data-description');
        
        roleDescription.innerHTML = `
            <strong>${selectedOption.text}</strong><br>
            <small>${description || 'Tidak ada deskripsi'}</small><br>
            <span class="badge bg-secondary">${roleName}</span>
        `;
        
        roleInfo.style.display = 'block';
    } else {
        roleInfo.style.display = 'none';
    }
}

// Role selection change
document.getElementById('role_id').addEventListener('change', function() {
    const roleId = this.value;
    const roleInfo = document.getElementById('roleInfo');
    const roleDescription = document.getElementById('roleDescription');
    const rolePermissions = document.getElementById('rolePermissions');
    
    if (roleId) {
        const selectedRole = roleData.find(role => role.id == roleId);
        if (selectedRole) {
            roleDescription.innerHTML = `
                <strong>${selectedRole.display_name}</strong><br>
                <small>${selectedRole.description || 'Tidak ada deskripsi'}</small>
            `;
            
            // Note: In real implementation, you'd need to load permissions via AJAX
            rolePermissions.innerHTML = `
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Role ini akan memberikan akses sesuai permissions yang telah dikonfigurasi.
                </small>
            `;
            
            roleInfo.style.display = 'block';
        }
    } else {
        roleInfo.style.display = 'none';
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleLoginFields();
});
</script>
@endsection
