@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1 text-gradient">Detail Penerimaan</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('penerimaan.index') }}">Penerimaan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Detail</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('penerimaan.index') }}" class="btn btn-outline-primary me-2">
                <i class="fas fa-arrow-left me-2"></i> Kembali
            </a>
            <a href="{{ route('penerimaan.print', $penerimaan->id) }}" class="btn btn-success me-2" target="_blank">
                <i class="fas fa-print me-2"></i> Print
            </a>
            {{-- Edit button - Available for admin & superadmin --}}
            @if($penerimaan->status == 'Unlocated' && Auth::user()->canEdit())
            <a href="{{ route('penerimaan.edit', $penerimaan->id) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
            @endif
        </div>
    </div>

    <!-- Alert -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <!-- Left Column - Main Information -->
        <div class="col-lg-4 mb-4">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    <h5 class="mb-0">Informasi Penerimaan</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%" class="fw-medium">Kode Penerimaan</td>
                            <td width="60%">: {{ $penerimaan->kode_penerimaan }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">Kategori</td>
                            <td>: {{ $penerimaan->mainCategory->name }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">Nomor PO</td>
                            <td>: {{ $penerimaan->nomor_po }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">Tanggal Penerimaan</td>
                            <td>: {{ \Carbon\Carbon::parse($penerimaan->tanggal_penerimaan)->format('d-m-Y') }}</td>
                        </tr>
                        <tr>
                            <td class="fw-medium">Metode Pembayaran</td>
                            <td>: {{ $penerimaan->metode_pembayaran }}</td>
                        </tr>
                        
                        @if($penerimaan->metode_pembayaran == 'Jatuh Tempo' && $penerimaan->tanggal_jatuh_tempo)
                        <tr>
                            <td class="fw-medium">Tanggal Jatuh Tempo</td>
                            <td>: {{ \Carbon\Carbon::parse($penerimaan->tanggal_jatuh_tempo)->format('d-m-Y') }}</td>
                        </tr>
                        @endif
                        
                        <tr>
                            <td class="fw-medium">Status</td>
                            <td>: 
                                @if($penerimaan->status == 'Unlocated')
                                    <span class="badge bg-warning">Unlocated</span>
                                @else
                                    <span class="badge bg-success">Located</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-medium">Catatan</td>
                            <td>: {{ $penerimaan->catatan ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Total Penerimaan Card -->
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Total Penerimaan</h5>
                        <h3 class="mb-0" id="totalHargaDisplay">Rp {{ number_format(round($penerimaan->total_harga), 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Detail Barang -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-box text-primary me-2"></i>
                    <h5 class="mb-0">Detail Barang</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Barang</th>
                                    <th>Qty</th>
                                    <th>Satuan</th>
                                    <th>Harga</th>
                                    <th>Diskon</th>
                                    <th>Harga Setelah Diskon</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($penerimaan->details as $index => $detail)
                                @php
                                    // Calculate price after tiered discounts
                                    $hppAsli = $detail->harga_hpp;
                                    $hppSetelahDiskon = $hppAsli;
                                    
                                    // Apply percentage discounts in sequence (tiered discounts)
                                    if ($detail->diskon_persen_1 > 0) {
                                        $hppSetelahDiskon -= ($hppSetelahDiskon * $detail->diskon_persen_1 / 100);
                                    }
                                    if ($detail->diskon_persen_2 > 0) {
                                        $hppSetelahDiskon -= ($hppSetelahDiskon * $detail->diskon_persen_2 / 100);
                                    }
                                    if ($detail->diskon_persen_3 > 0) {
                                        $hppSetelahDiskon -= ($hppSetelahDiskon * $detail->diskon_persen_3 / 100);
                                    }
                                    if ($detail->diskon_persen_4 > 0) {
                                        $hppSetelahDiskon -= ($hppSetelahDiskon * $detail->diskon_persen_4 / 100);
                                    }
                                    if ($detail->diskon_persen_5 > 0) {
                                        $hppSetelahDiskon -= ($hppSetelahDiskon * $detail->diskon_persen_5 / 100);
                                    }
                                    
                                    // Apply nominal discounts
                                    if ($detail->diskon_nominal_1 > 0) {
                                        $hppSetelahDiskon -= $detail->diskon_nominal_1;
                                    }
                                    if ($detail->diskon_nominal_2 > 0) {
                                        $hppSetelahDiskon -= $detail->diskon_nominal_2;
                                    }
                                    if ($detail->diskon_nominal_3 > 0) {
                                        $hppSetelahDiskon -= $detail->diskon_nominal_3;
                                    }
                                    if ($detail->diskon_nominal_4 > 0) {
                                        $hppSetelahDiskon -= $detail->diskon_nominal_4;
                                    }
                                    if ($detail->diskon_nominal_5 > 0) {
                                        $hppSetelahDiskon -= $detail->diskon_nominal_5;
                                    }
                                    
                                    // Ensure price doesn't go negative
                                    $hppSetelahDiskon = max(0, $hppSetelahDiskon);
                                @endphp
                                <tr class="item-data-row">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="fw-medium">{{ $detail->product->name }}</div>
                                        @if($detail->product->status_pajak)
                                            <div class="text-muted small mt-1">Status: {{ $detail->product->status_pajak }}</div>
                                        @endif
                                        @if($detail->catatan)
                                            <div class="text-muted small mt-1">{{ $detail->catatan }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($penerimaan->status == 'Unlocated')
                                        <div class="d-inline-flex align-items-center gap-1" id="qty-display-{{ $detail->id }}">
                                            <span id="qty-text-{{ $detail->id }}">{{ intval($detail->qty) }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 border-0" onclick="startEditQty({{ $detail->id }}, {{ intval($detail->qty) }})" title="Edit Qty">
                                                <i class="fas fa-pen fa-xs"></i>
                                            </button>
                                        </div>
                                        <div class="d-none" id="qty-edit-{{ $detail->id }}">
                                            <div class="input-group input-group-sm" style="width: 120px;">
                                                <input type="number" class="form-control form-control-sm" id="qty-input-{{ $detail->id }}" value="{{ intval($detail->qty) }}" min="1" step="1">
                                                <button class="btn btn-success btn-sm" type="button" onclick="saveEditQty({{ $detail->id }}, {{ $penerimaan->id }})">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-secondary btn-sm" type="button" onclick="cancelEditQty({{ $detail->id }}, {{ intval($detail->qty) }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        @else
                                        {{ intval($detail->qty) }}
                                        @endif
                                    </td>
                                    <td>{{ $detail->satuan->name }}</td>
                                    <td>
                                        @if($detail->is_free)
                                            <span class="badge bg-secondary">Free</span>
                                        @else
                                            Rp {{ number_format($detail->harga_hpp, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$detail->is_free)
                                            @php
                                                $diskonList = [];
                                                for ($i = 1; $i <= 5; $i++) {
                                                    $persen = $detail->{'diskon_persen_' . $i} ?? 0;
                                                    $nominal = $detail->{'diskon_nominal_' . $i} ?? 0;
                                                    if ($persen > 0) {
                                                        $diskonList[] = '<span class="badge bg-info text-dark me-1">' . $persen . '%</span>';
                                                    }
                                                    if ($nominal > 0) {
                                                        $diskonList[] = '<span class="badge bg-info text-dark me-1">Rp ' . number_format(round($nominal), 0, ",", ".") . '</span>';
                                                    }
                                                }
                                            @endphp
                                            @if(count($diskonList) > 0)
                                                {!! implode('', $diskonList) !!}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="fw-medium">
                                        @if($detail->is_free)
                                            <span class="badge bg-secondary">Free</span>
                                        @else
                                            Rp {{ number_format($hppSetelahDiskon, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="fw-medium">
                                        Rp {{ number_format($detail->subtotal, 2, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="py-3">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <h6 class="fw-normal mb-1">Belum ada barang</h6>
                                            <p class="text-muted mb-0">Tidak ada detail barang untuk penerimaan ini</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-group-divider">
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="fw-bold text-end">Total:</td>
                                    <td class="fw-bold">Rp {{ number_format(round($penerimaan->total_harga), 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted mb-0">Total Item: {{ $penerimaan->details->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card untuk tracking aktivitas -->
    <div class="card mt-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-history text-primary me-2"></i>
            <h5 class="mb-0">Riwayat Aktivitas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>User</th>
                            <th>Aktivitas</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($penerimaan->activities ?? [] as $activity)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($activity->created_at)->format('d-m-Y H:i') }}</td>
                            <td>{{ optional($activity->user)->name ?? 'User tidak ditemukan' }}</td>
                            <td>{{ $activity->activity_type }}</td>
                            <td>{{ $activity->description }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($penerimaan->created_at)->format('d-m-Y H:i') }}</td>
                            <td>Admin</td>
                            <td>Membuat penerimaan</td>
                            <td>Membuat penerimaan baru dengan kode {{ $penerimaan->kode_penerimaan }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
   
    
    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="modalDeleteConfirm" tabindex="-1" aria-labelledby="modalDeleteConfirmLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDeleteConfirmLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data penerimaan dengan kode <strong>{{ $penerimaan->kode_penerimaan }}</strong>?</p>
                    <p class="text-danger mb-0"><strong>Perhatian:</strong> Tindakan ini tidak dapat dibatalkan dan semua data terkait akan dihapus secara permanen.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form action="#" method="POST" style="display: inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Ya, Hapus Data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .item-data-row:hover {
        background-color: rgba(181, 198, 224, 0.05);
    }

    .table-borderless tr td {
        padding: 0.5rem 0;
        border: none;
    }

    .table-group-divider {
        border-top: 2px solid rgba(181, 198, 224, 0.2);
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });

    function startEditQty(detailId, currentQty) {
        document.getElementById('qty-display-' + detailId).classList.add('d-none');
        document.getElementById('qty-edit-' + detailId).classList.remove('d-none');
        var input = document.getElementById('qty-input-' + detailId);
        input.focus();
        input.select();
    }

    function cancelEditQty(detailId, currentQty) {
        document.getElementById('qty-display-' + detailId).classList.remove('d-none');
        document.getElementById('qty-edit-' + detailId).classList.add('d-none');
        document.getElementById('qty-input-' + detailId).value = currentQty;
    }

    function saveEditQty(detailId, penerimaanId) {
        var input = document.getElementById('qty-input-' + detailId);
        var newQty = parseFloat(input.value);

        if (isNaN(newQty) || newQty <= 0) {
            input.classList.add('is-invalid');
            return;
        }
        input.classList.remove('is-invalid');

        var saveBtn = input.parentElement.querySelector('.btn-success');
        var originalHTML = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        saveBtn.disabled = true;

        fetch('/penerimaan/' + penerimaanId + '/update-detail-qty', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                detail_id: detailId,
                qty: newQty
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success) {
                document.getElementById('qty-text-' + detailId).textContent = result.new_qty;
                document.getElementById('qty-display-' + detailId).classList.remove('d-none');
                document.getElementById('qty-edit-' + detailId).classList.add('d-none');
                input.value = result.new_qty;

                var totalEl = document.getElementById('totalHargaDisplay');
                if (totalEl) {
                    totalEl.textContent = 'Rp ' + formatRupiahTotal(result.new_total);
                }

                var subtotalCell = document.getElementById('qty-display-' + detailId).closest('tr').querySelector('td:last-child');
                if (subtotalCell) {
                    subtotalCell.textContent = 'Rp ' + formatRupiah(result.new_subtotal);
                }
            } else {
                alert('Gagal update qty: ' + (result.message || 'Terjadi kesalahan'));
            }
        })
        .catch(function(error) {
            alert('Terjadi kesalahan: ' + error.message);
        })
        .finally(function() {
            saveBtn.innerHTML = originalHTML;
            saveBtn.disabled = false;
        });
    }

    function formatRupiah(amount) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount);
    }

    function formatRupiahTotal(amount) {
        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount);
    }
</script>
@endpush