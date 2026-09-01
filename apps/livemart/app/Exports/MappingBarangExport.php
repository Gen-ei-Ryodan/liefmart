<?php

namespace App\Exports;

use App\Models\MappingBarang;
use App\Models\PlatformProduct;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MappingBarangExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected array $filters;
    protected int $counter = 1;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $platform = $this->filters['platform'] ?? null;
        $search = $this->filters['search'] ?? null;
        $variant = $this->filters['variant'] ?? null;

        $platformProductIds = PlatformProduct::withoutGlobalScopes()
            ->whereHas('mappingBarang', function ($q) {
                $q->where('is_active', true);
            })
            ->when($platform, function ($q) use ($platform) {
                $q->whereHas('platform', function ($subQ) use ($platform) {
                    $subQ->where('name', $platform);
                });
            })
            ->when($search, function ($q) use ($search) {
                $normalizedSearch = preg_replace('/[\s\+\-]+/', ' ', trim($search));
                $normalizedSearch = preg_replace('/\s+/', ' ', $normalizedSearch);

                $q->where(function ($subQ) use ($search, $normalizedSearch) {
                    $subQ->where('platform_product_name', 'like', '%' . $search . '%')
                        ->orWhere('platform_product_name', 'like', '%' . $normalizedSearch . '%')
                        ->orWhereRaw('REPLACE(REPLACE(REPLACE(platform_product_name, "+", " "), "-", " "), "  ", " ") LIKE ?', ['%' . $normalizedSearch . '%'])
                        ->orWhereRaw('LOWER(platform_product_name) LIKE ?', ['%' . strtolower($normalizedSearch) . '%']);
                });
            })
            ->when($variant, function ($q) use ($variant) {
                $q->where('variant', 'like', '%' . $variant . '%');
            })
            ->select('id');

        return MappingBarang::query()
            ->with([
                'platformProduct' => function ($q) {
                    $q->withoutGlobalScopes()->with('platform');
                },
                'product.productVariant',
            ])
            ->where('is_active', true)
            ->whereIn('platform_product_id', $platformProductIds)
            ->orderBy('platform_product_id')
            ->orderBy('product_id');
    }

    public function headings(): array
    {
        return [
            'No',
            'Platform',
            'Produk Platform',
            'Variant Platform',
            'Versi',
            'Produk Master',
            'SKU Master',
            'Variant Master',
            'Qty',
        ];
    }

    public function map($mapping): array
    {
        $platformProduct = $mapping->platformProduct;
        $platform = $platformProduct?->platform;
        $product = $mapping->product;

        return [
            $this->counter++,
            $platform?->name ?? '-',
            $platformProduct?->platform_product_name ?? '-',
            $platformProduct?->variant ?: '-',
            $mapping->version ?? 1,
            $product?->name ?? '-',
            $product?->sku ?? '-',
            $product?->productVariant?->name ?? '-',
            $mapping->quantity,
        ];
    }
}
