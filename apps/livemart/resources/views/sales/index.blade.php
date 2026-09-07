@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 fw-bold text-primary">
                        <i class="fas fa-shopping-cart me-2"></i>Daftar Pesanan
                    </h5>
                    <a href="{{ route('sales.choose-type') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Tambah Pesanan
                    </a>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="table-responsive disable-fixed-scrollbar">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light sticky-top">
                                <tr>
                                    <th>No. Pesanan</th>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Platform</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->order_date->format('d/m/Y') }}</td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>
                                        <span class="badge badge-{{ $order->platform == 'Shopee' ? 'warning' : 'primary' }}">
                                            {{ $order->platform }}
                                        </span>
                                    </td>
                                    <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $order->status == 'paid' ? 'success' : ($order->status == 'pending' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('sales.order.detail', $order->id) }}" class="btn btn-info btn-sm">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a href="{{ route('sales.list', ['order_number' => $order->order_number]) }}" class="btn btn-warning btn-sm">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="{{ route('sales.order.print', $order->id) }}" class="btn btn-secondary btn-sm" target="_blank">
                                                <i class="fa fa-print"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="setupDeleteModal('{{ $order->id }}', '{{ $order->order_number }}')" 
                                                data-toggle="modal" data-target="#deleteOrderModal">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data pesanan.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center">
                        {{ $orders->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('sales.partials.delete-confirmation')
@endsection 
