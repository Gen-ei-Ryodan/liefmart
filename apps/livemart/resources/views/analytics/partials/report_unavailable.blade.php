<div class="container-fluid">
    <div class="card">
        <div class="card-body text-center py-5">
            <h4 class="mb-3">{{ $title ?? 'Laporan Analytics' }}</h4>
            <p class="text-muted mb-4">Laporan ini belum tersedia pada halaman lama.</p>
            <a href="{{ route('analytics.sales-by-platform') }}" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Analytics
            </a>
        </div>
    </div>
</div>
