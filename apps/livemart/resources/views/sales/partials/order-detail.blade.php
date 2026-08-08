<div class="row">
    <div class="col-md-6">
        <h5 class="border-bottom pb-2">Informasi Pesanan</h5>
        <table class="table table-sm">
            <tr>
                <th>Platform</th>
                <td>
                    @if($order->platform)
                        <span class="badge bg-{{ 
                            $order->platform->name == 'shopee' ? 'warning' : 
                            ($order->platform->name == 'tiktok' ? 'dark' : 'primary') 
                        }}">
                            {{ $order->platform->name }}
                        </span>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Nomor Order</th>
                <td><strong>{{ $order->order_number }}</strong></td>
            </tr>
            <tr>
                <th>Tanggal Order</th>
                <td>
                    @if($order->tanggal) 
                        {{ \Carbon\Carbon::parse($order->tanggal)->format('d-m-Y') }}
                    @else 
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <th>Hari</th>
                <td>{{ $order->hari }}</td>
            </tr>
            <tr>
                <th>Status Hari</th>
                <td>
                    @if($order->status_hari)
                        @php
                            $statuses = explode(',', $order->status_hari);
                            $statuses = array_map('trim', $statuses);
                        @endphp
                        @foreach($statuses as $status)
                            <span class="badge bg-info me-1">{{ $status }}</span>
                        @endforeach
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    @if($order->status == 'completed')
                        <span class="badge bg-success">Selesai</span>
                    @elseif($order->status == 'pending')
                        <span class="badge bg-warning text-dark">Tertunda</span>
                    @elseif($order->status == 'canceled')
                        <span class="badge bg-danger">Dibatalkan</span>
                    @else
                        <span class="badge bg-secondary">{{ $order->status }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Retur</th>
                <td>
                    @if($order->isFullyReturned())
                        <span class="badge bg-danger">Retur Full</span>
                    @elseif($order->hasReturns())
                        <span class="badge bg-warning text-dark">Retur Sebagian</span>
                    @else
                        <span class="badge bg-secondary">Tidak Ada</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h5 class="border-bottom pb-2">Informasi Pengiriman</h5>
        <table class="table table-sm">
            <tr>
                <th>No. Resi</th>
                <td>
                    @if($order->orderItems->first() && $order->orderItems->first()->tracking_number)
                        <span class="font-monospace">{{ $order->orderItems->first()->tracking_number }}</span>
                    @else
                        <span class="text-muted">Belum ada nomor resi</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>

<h5 class="border-bottom pb-2 mt-3">Detail Item</h5>
<div class="table-responsive">
    <table class="table table-sm table-striped table-bordered">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Produk</th>
                <th>Variasi</th>
                <th class="text-center">Quantity</th>
                <th class="text-end">Harga</th>
                <th class="text-end">Subtotal</th>
                <th>Stok Diambil Dari</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @forelse($order->orderItems as $index => $item)
                @php 
                    $subtotal = $item->price_after_discount * $item->quantity;
                    $total += $subtotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        @if($item->platformProduct)
                            {{ $item->platformProduct->platform_product_name }}
                        @else
                            <span class="text-muted">Data produk tidak tersedia</span>
                        @endif
                    </td>
                    <td>
                        @if($item->platformProduct && $item->platformProduct->variant)
                            <span class="badge bg-info text-white">{{ $item->platformProduct->variant }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-end">{{ number_format($item->price_after_discount, 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format($subtotal, 0, ',', '.') }}</td>
                    <td>
                        @if($item->warehouseStock && $item->warehouseStock->product)
                            {{ $item->warehouseStock->product->name }}
                            <span class="text-muted">(ID: {{ $item->warehouse_stock_id }})</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada item</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="5" class="text-end">Total</th>
                <th class="text-end">{{ number_format($total, 0, ',', '.') }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="bg-light p-3 rounded">
            <h6><i class="fas fa-info-circle me-2"></i>Informasi Tambahan</h6>
            <p class="mb-0 text-muted small">
                Pesanan dibuat pada {{ $order->created_at->format('d M Y H:i') }}
                @if($order->created_at != $order->updated_at)
                <br>Terakhir diperbarui: {{ $order->updated_at->format('d M Y H:i') }}
                @endif
            </p>
        </div>
    </div>
</div> 