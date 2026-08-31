@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit User: {{ $user->name }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">User Management</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.show', $user->id) }}" class="text-decoration-none">Detail User</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit User</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <div class="card-header d-flex align-items-center py-3">
                    <i class="fas fa-user-edit text-primary me-2"></i>
                    <h5 class="mb-0 fw-semibold">Form Edit User</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select @error('role_id') is-invalid @enderror" 
                                            id="role_id" name="role_id" required>
                                        <option value="">Pilih Role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" 
                                                {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                                {{ $role->display_name }} ({{ $role->name }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="login_type" class="form-label">Tipe Login <span class="text-danger">*</span></label>
                                    <select class="form-select @error('login_type') is-invalid @enderror" 
                                            id="login_type" name="login_type" required>
                                        <option value="email" {{ old('login_type', $user->email ? 'email' : 'username') == 'email' ? 'selected' : '' }}>
                                            Email
                                        </option>
                                        <option value="username" {{ old('login_type', $user->username ? 'username' : 'email') == 'username' ? 'selected' : '' }}>
                                            Username
                                        </option>
                                    </select>
                                    @error('login_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="is_active" class="form-label">Status</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            User Aktif
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3" id="email_field" style="display: {{ old('login_type', $user->email ? 'email' : 'username') == 'email' ? 'block' : 'none' }};">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           id="email" name="email" value="{{ old('email', $user->email) }}">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3" id="username_field" style="display: {{ old('login_type', $user->username ? 'username' : 'email') == 'username' ? 'block' : 'none' }};">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('username') is-invalid @enderror" 
                                           id="username" name="username" value="{{ old('username', $user->username) }}">
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                           id="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password">
                                    <small class="form-text text-muted">Minimal 8 karakter. Kosongkan jika tidak ingin mengubah password.</small>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" 
                                           id="password_confirmation" name="password_confirmation" 
                                           placeholder="Ulangi password baru">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">Informasi User Saat Ini:</label>
                                    <div class="alert alert-info">
                                        <strong>ID:</strong> {{ $user->id }} | 
                                        <strong>Dibuat:</strong> {{ $user->created_at->format('d/m/Y H:i:s') }} | 
                                        <strong>Update Terakhir:</strong> {{ $user->updated_at->format('d/m/Y H:i:s') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-outline-primary rounded-pill px-4">
                                        <i class="fas fa-arrow-left me-2"></i> Kembali
                                    </a>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                                            <i class="fas fa-save me-2"></i> Update User
                                        </button>
                                        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                            <i class="fas fa-times me-2"></i> Batal
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle email/username fields based on login type
    $('#login_type').change(function() {
        var loginType = $(this).val();
        
        if (loginType === 'email') {
            $('#email_field').show();
            $('#username_field').hide();
            $('#email').prop('required', true);
            $('#username').prop('required', false);
        } else {
            $('#email_field').hide();
            $('#username_field').show();
            $('#email').prop('required', false);
            $('#username').prop('required', true);
        }
    });

    // Password confirmation validation
    $('#password_confirmation').on('input', function() {
        var password = $('#password').val();
        var confirmPassword = $(this).val();
        
        if (password && confirmPassword && password !== confirmPassword) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">Password tidak cocok!</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });

    // Form validation before submit
    $('form').on('submit', function(e) {
        var password = $('#password').val();
        var confirmPassword = $('#password_confirmation').val();
        
        if (password && confirmPassword && password !== confirmPassword) {
            e.preventDefault();
            alert('Password dan konfirmasi password tidak cocok!');
            return false;
        }
    });
});
</script>
@endpush
@endsection
