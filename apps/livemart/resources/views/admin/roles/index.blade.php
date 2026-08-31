@extends('layouts.app')

@section('title', 'Management Role')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-user-shield me-2"></i>Management Role
                    </h5>
                    <a href="{{ route('admin.roles.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Tambah Role
                    </a>
                </div>

                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Role</th>
                                    <th>Display Name</th>
                                    <th>Deskripsi</th>
                                    <th>Permissions</th>
                                    <th>Users</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $index => $role)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $role->name }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $role->display_name }}</strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $role->description ?: '-' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $role->permissions->count() }} permissions</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">{{ $role->users->count() }} users</span>
                                        </td>
                                        <td>
                                            @if($role->is_active)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-danger">Tidak Aktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.roles.show', $role) }}" class="btn btn-sm btn-info" title="Lihat">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form action="{{ route('admin.roles.toggle-status', $role) }}" method="POST" style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm {{ $role->is_active ? 'btn-secondary' : 'btn-success' }}" 
                                                            title="{{ $role->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i class="fas fa-{{ $role->is_active ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                </form>

                                                @if($role->users->count() == 0)
                                                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Yakin ingin menghapus role {{ $role->display_name }}?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">Belum ada role yang dibuat</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
