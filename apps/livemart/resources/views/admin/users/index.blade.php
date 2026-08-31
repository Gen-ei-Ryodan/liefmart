@extends('layouts.app')

@section('title', 'Management User')

@section('content')
<div class="container-fluid py-3 animate__animated animate__fadeIn animate__faster">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-users me-2"></i>Management User
                    </h5>
                    <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Tambah User
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
                            <thead class="table-primary">
                                <tr>
                                    <th class="text-dark">No</th>
                                    <th class="text-dark">Nama</th>
                                    <th class="text-dark">Login</th>
                                    <th class="text-dark">Role Type</th>
                                    <th class="text-dark">Role</th>
                                    <th class="text-dark">Status</th>
                                    <th class="text-dark">Last Login</th>
                                    <th class="text-dark">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $index => $user)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-circle me-2 text-muted"></i>
                                                <div>
                                                    <strong>{{ $user->name }}</strong>
                                                    @if($user->id === auth()->id())
                                                        <span class="badge bg-info ms-1">You</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($user->email)
                                                <small class="text-muted">Email:</small><br>
                                                {{ $user->email }}
                                            @else
                                                <small class="text-muted">Username:</small><br>
                                                {{ $user->username }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->roleModel && $user->roleModel->name === 'superadmin')
                                                <span class="badge bg-danger">Superadmin</span>
                                            @elseif($user->roleModel && $user->roleModel->name === 'admin')
                                                <span class="badge bg-primary">Admin</span>
                                            @else
                                                <span class="badge bg-secondary">Staff</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->roleModel)
                                                <span class="badge bg-secondary">{{ $user->roleModel->display_name }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->is_active)
                                                <span class="badge bg-success">Aktif</span>
                                            @else
                                                <span class="badge bg-danger">Tidak Aktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $user->updated_at->diffForHumans() }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-info" title="Lihat">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                @if($user->id !== auth()->id())
                                                    <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm {{ $user->is_active ? 'btn-secondary' : 'btn-success' }}" 
                                                                title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                            <i class="fas fa-{{ $user->is_active ? 'ban' : 'check' }}"></i>
                                                        </button>
                                                    </form>

                                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('Yakin ingin menghapus user {{ $user->name }}?')">
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
                                            <p class="text-muted">Belum ada user yang terdaftar</p>
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
