@extends('layouts.app')

@section('title', 'Edit Rekening Bank')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn animate__faster">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1 text-gradient">Edit Rekening Bank</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('bank-accounts.index') }}" class="text-decoration-none">Rekening Bank</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('bank-accounts.index') }}" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Error!</strong> Ada beberapa masalah dengan inputan Anda:
        <ul class="mb-0 ps-3 pt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <form action="{{ route('bank-accounts.update', $bankAccount) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
            <div class="card-header d-flex align-items-center py-3">
                <i class="fas fa-university text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Informasi Rekening Bank</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="bank_name" class="form-label fw-medium">Nama Bank <span class="text-danger">*</span></label>
                            <select class="form-select rounded-3 @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" required>
                                <option value="" selected disabled>-- Pilih Bank --</option>
                                <option value="BCA" {{ old('bank_name', $bankAccount->bank_name) == 'BCA' ? 'selected' : '' }}>BCA (Bank Central Asia)</option>
                                <option value="BNI" {{ old('bank_name', $bankAccount->bank_name) == 'BNI' ? 'selected' : '' }}>BNI (Bank Negara Indonesia)</option>
                                <option value="BRI" {{ old('bank_name', $bankAccount->bank_name) == 'BRI' ? 'selected' : '' }}>BRI (Bank Rakyat Indonesia)</option>
                                <option value="Mandiri" {{ old('bank_name', $bankAccount->bank_name) == 'Mandiri' ? 'selected' : '' }}>Bank Mandiri</option>
                                <option value="CIMB Niaga" {{ old('bank_name', $bankAccount->bank_name) == 'CIMB Niaga' ? 'selected' : '' }}>CIMB Niaga</option>
                                <option value="Danamon" {{ old('bank_name', $bankAccount->bank_name) == 'Danamon' ? 'selected' : '' }}>Bank Danamon</option>
                                <option value="Permata" {{ old('bank_name', $bankAccount->bank_name) == 'Permata' ? 'selected' : '' }}>Bank Permata</option>
                                <option value="BTN" {{ old('bank_name', $bankAccount->bank_name) == 'BTN' ? 'selected' : '' }}>BTN (Bank Tabungan Negara)</option>
                                <option value="BSI" {{ old('bank_name', $bankAccount->bank_name) == 'BSI' ? 'selected' : '' }}>BSI (Bank Syariah Indonesia)</option>
                                <option value="other" {{ !in_array(old('bank_name', $bankAccount->bank_name), ['BCA', 'BNI', 'BRI', 'Mandiri', 'CIMB Niaga', 'Danamon', 'Permata', 'BTN', 'BSI']) ? 'selected' : '' }}>Bank Lainnya...</option>
                            </select>
                            @error('bank_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4 d-none" id="other_bank_group">
                            <label for="other_bank" class="form-label fw-medium">Nama Bank Lainnya <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3" id="other_bank" placeholder="Masukkan nama bank" value="{{ !in_array($bankAccount->bank_name, ['BCA', 'BNI', 'BRI', 'Mandiri', 'CIMB Niaga', 'Danamon', 'Permata', 'BTN', 'BSI']) ? $bankAccount->bank_name : '' }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-4">
                            <label for="account_number" class="form-label fw-medium">Nomor Rekening <span class="text-danger">*</span></label>
                            <input type="text" class="form-control rounded-3 @error('account_number') is-invalid @enderror" id="account_number" name="account_number" value="{{ old('account_number', $bankAccount->account_number) }}" placeholder="Contoh: 1234567890" required>
                            <div class="form-text text-muted">Masukkan nomor rekening tanpa spasi atau karakter khusus</div>
                            @error('account_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="account_name" class="form-label fw-medium">Atas Nama <span class="text-danger">*</span></label>
                    <input type="text" class="form-control rounded-3 @error('account_name') is-invalid @enderror" id="account_name" name="account_name" value="{{ old('account_name', $bankAccount->account_name) }}" placeholder="Nama pemilik rekening" required>
                    <div class="form-text text-muted">Masukkan nama sesuai yang tertera pada buku tabungan</div>
                    @error('account_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label fw-medium">Deskripsi</label>
                    <textarea class="form-control rounded-3 @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Contoh: Rekening operasional untuk penerimaan pembayaran customer">{{ old('description', $bankAccount->description) }}</textarea>
                    <div class="form-text text-muted">Informasi tambahan tentang rekening bank ini (opsional)</div>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $bankAccount->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Aktifkan sebagai rekening utama</label>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Catatan:</strong> Jika diaktifkan, rekening ini akan muncul di invoice dan rekening aktif lainnya akan dinonaktifkan secara otomatis.
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="{{ route('bank-accounts.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="fas fa-times me-2"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#bank_name').change(function() {
            if ($(this).val() === 'other') {
                $('#other_bank_group').removeClass('d-none');
                $('#other_bank').attr('required', true);
            } else {
                $('#other_bank_group').addClass('d-none');
                $('#other_bank').attr('required', false);
            }
        });

        $('form').submit(function(e) {
            if ($('#bank_name').val() === 'other') {
                $('#bank_name').val($('#other_bank').val());
            }
        });

        $('#bank_name').trigger('change');
    });
</script>
@endpush
@endsection
