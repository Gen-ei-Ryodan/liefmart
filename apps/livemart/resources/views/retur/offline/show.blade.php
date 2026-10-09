@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="ds-card-header">
                    <h4 class="card-title">Detail Retur Penjualan Offline</h4>
                    <div class="card-tools">
                        <a href="{{ route('retur-offline.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        
                        @if($returOfflineSale->status == 'draft')
                        <div class="btn-group ml-2">
                            <a href="{{ route('retur-offline.edit', $returOfflineSale->id) }}" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form action="{{ route('retur-offline.process', $returOfflineSale->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-success" onclick="return confirm('Yakin ingin memproses retur ini?')">
                                    <i class="fas fa-check"></i> Proses
                                </button>
                            </form>
                            <form action="{{ route('retur-offline.cancel', $returOfflineSale->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Yakin ingin membatalkan retur ini?')">
                                    <i class="fas fa-times"></i> Batalkan
                                </button>
                            </form>
                        </div>
                        @elseif($returOfflineSale->status == 'selesai')
                        <div class="btn-group ml-2">
                            <form action="{{ route('retur-offline.reverse', $returOfflineSale->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('PERINGATAN: Batal retur akan mengembalikan SEMUA perubahan ke kondisi semula (qty item dan stok warehouse). Yakin ingin membatalkan retur ini?')">
                                    <i class="fas fa-undo"></i> Batal Retur
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    @endif

                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="ds-card-header">
                                    <h5 class="card-title mb-0">Informasi Retur</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered table-sm">
                                        <tr>
                                            <th width="35%">Kode Retur</th>
                                            <td>{{ $returOfflineSale->kode_retur }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Retur</th>
                                            <td>{{ $returOfflineSale->tanggal_retur->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Status</th>
                                            <td>
                                                @if($returOfflineSale->status == 'draft')
                                                <span class="badge badge-warning">Draft</span>
                                                @elseif($returOfflineSale->status == 'selesai')
                                                <span class="badge badge-success">Selesai</span>
                                                @elseif($returOfflineSale->status == 'dibatalkan')
                                                <span class="badge badge-danger">Dibatalkan</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Dibuat Oleh</th>
                                            <td>{{ $returOfflineSale->user->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Catatan</th>
                                            <td>{{ $returOfflineSale->catatan ?? '-' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="ds-card-header">
                                    <h5 class="card-title mb-0">Informasi Penjualan Offline</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered table-sm">
                                        <tr>
                                            <th width="35%">Nomor Surat Jalan</th>
                                            <td>{{ $returOfflineSale->offlineSale->surat_jalan_number }}</td>
                                        </tr>
                                        <tr>
                                            <th>Tanggal Penjualan</th>
                                            <td>{{ $returOfflineSale->offlineSale->sale_date->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <th>Customer</th>
                                            <td>{{ $returOfflineSale->offlineSale->customerInfo->name ?? $returOfflineSale->offlineSale->customer_name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>No. PO</th>
                                            <td>{{ $returOfflineSale->offlineSale->No_PO ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Total Penjualan</th>
                                            <td>Rp {{ number_format($returOfflineSale->offlineSale->total_amount, 0, ',', '.') }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="ds-card-header">
                            <h5 class="card-title mb-0">Detail Barang</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th>Nama Barang</th>
                                            <th style="width: 100px; text-align: center;">Qty Retur</th>
                                            <th style="width: 120px;">Harga Satuan</th>
                                            <th style="width: 150px;">Diskon</th>
                                            <th style="width: 120px;">Subtotal Retur</th>
                                            <th style="width: 150px;">Status Barang</th>
                                            <th>Alasan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $grandTotalRetur = 0;
                                            $totalDiskonRetur = 0;
                                        @endphp
                                        @php
                                            // Get tax_id from first detail item's barang keluar
                                            $taxId = null;
                                            $firstDetail = $returOfflineSale->details->first();
                                            if ($firstDetail && $firstDetail->offlineSaleItem) {
                                                $barangKeluar = $firstDetail->offlineSaleItem->barangKeluar()->with('warehouseStock')->first();
                                                if ($barangKeluar && $barangKeluar->warehouseStock) {
                                                    $taxId = $barangKeluar->warehouseStock->tax_id;
                                                }
                                            }
                                        @endphp
                                        @forelse($returOfflineSale->details as $index => $detail)
                                        @php
                                            $qtyRetur = $detail->qty;
                                            $hargaSatuan = $detail->offlineSaleItem ? $detail->offlineSaleItem->unit_price : 0;
                                            $currentTotal = $hargaSatuan * $qtyRetur;
                                            $diskonText = [];
                                            $diskonRetur = 0;
                                            // Hitung diskon persen dan nominal (1-5)
                                            for($i = 1; $i <= 5; $i++) {
                                                $percentField = "discount_percent_" . $i;
                                                $amountField = "discount_amount_" . $i;
                                                $percent = $detail->offlineSaleItem ? ($detail->offlineSaleItem->$percentField ?? 0) : 0;
                                                $amount = $detail->offlineSaleItem ? ($detail->offlineSaleItem->$amountField ?? 0) : 0;
                                                if($percent > 0) {
                                                    $diskon = $currentTotal * ($percent / 100);
                                                    $diskonRetur += $diskon;
                                                    $diskonText[] = number_format($percent, 2, ',', '.') . '%';
                                                    $currentTotal -= $diskon;
                                                    $currentTotal = round($currentTotal, 2);
                                                }
                                                if($amount > 0) {
                                                    $diskon = $amount * $qtyRetur;
                                                    $diskonRetur += $diskon;
                                                    $diskonText[] = 'Rp ' . number_format($amount, 0, ',', '.');
                                                    $currentTotal -= $diskon;
                                                    $currentTotal = round($currentTotal, 2);
                                                }
                                            }
                                            $grandTotalRetur += $currentTotal;
                                            $totalDiskonRetur += $diskonRetur;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>{{ $detail->product->name ?? 'Product tidak ditemukan' }}</td>
                                            <td class="text-center">{{ $qtyRetur }}</td>
                                            <td class="text-right">Rp {{ number_format($hargaSatuan, 0, ',', '.') }}</td>
                                            <td class="text-right">{!! implode('<br>', $diskonText) ?: '-' !!}</td>
                                            <td class="text-right">Rp {{ number_format($currentTotal, 0, ',', '.') }}</td>
                                            <td>
                                                @if($detail->kondisi == 'BAGUS')
                                                <span class="badge badge-success text-dark" style="font-weight:600;">Baik</span>
                                                @elseif($detail->kondisi == 'RUSAK')
                                                <span class="badge badge-warning text-dark" style="font-weight:600;">Rusak</span>
                                                @elseif($detail->kondisi == 'HILANG')
                                                <span class="badge badge-danger text-white" style="font-weight:600;">Hilang</span>
                                                @endif
                                            </td>
                                            <td>{{ $detail->alasan ?? '-' }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="8" class="text-center">Tidak ada data detail retur</td>
                                        </tr>
                                        @endforelse
                                        <tr class="table-info">
                                            <td colspan="4" class="text-center"><strong>TOTAL</strong></td>
                                            <td class="text-right"><strong>Rp {{ number_format($totalDiskonRetur, 0, ',', '.') }}</strong></td>
                                            <td class="text-right"><strong>Rp {{ number_format($grandTotalRetur, 0, ',', '.') }}</strong></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @php
                        // Calculate DPP, PPN, and Grand Total
                        // grandTotalRetur adalah total setelah diskon (DPP retur)
                        $dpp = \App\Helpers\NumberFormatter::calculateDPP($grandTotalRetur);
                        $ppn = 0;
                        $grandTotal = \App\Helpers\NumberFormatter::roundToWholeNumber($grandTotalRetur);
                        
                        if ($taxId == 3) {
                            // PKP: Include PPN
                            $dpp = \App\Helpers\NumberFormatter::roundToWholeNumber($grandTotal / 1.11);
                            $dpp11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($dpp);
                            $ppn = $grandTotal - $dpp;
                        } else {
                            // Non-PKP: No PPN
                            $dpp = $grandTotal;
                            $dpp11_12 = 0;
                            $ppn = 0;
                        }
                    @endphp

                    <div class="card mt-3">
                        <div class="ds-card-header">
                            <h5 class="card-title mb-0">Rincian Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 offset-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="50%">DPP (Dasar Pengenaan Pajak)</th>
                                            <td class="text-right"><strong>Rp {{ number_format($dpp, 0, ',', '.') }}</strong></td>
                                        </tr>
                                        <tr>
                                            <th>PPN (11%)</th>
                                            <td class="text-right"><strong>Rp {{ number_format($ppn, 0, ',', '.') }}</strong></td>
                                        </tr>
                                        <tr class="table-info">
                                            <th>TOTAL (DPP + PPN)</th>
                                            <td class="text-right"><strong>Rp {{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 