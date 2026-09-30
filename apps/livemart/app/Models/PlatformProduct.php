<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PlatformProduct extends Model
{
    use HasFactory;

    // Kolom yang boleh diisi secara massal
    protected $fillable = [
        'platform_id',
        'platform_product_name',
        'variant', // Menambahkan kolom variant ke fillable
    ];

    // Relasi ke model Platform
    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    // Relasi ke model MappingBarang
    public function mappingBarang()
    {
        return $this->hasMany(MappingBarang::class);
    }

    // Relasi ke model Product melalui MappingBarang
    public function products()
    {
        return $this->belongsToMany(Product::class, 'mapping_barangs')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Get mappings that were active on a specific date
     */
    public function getMappingsForDate($date)
    {
        return MappingBarang::getMappingsForDate($this->id, $date);
    }

    /**
     * Get mapping that was active on a specific date (single mapping)
     */
    public function getMappingForDate($date)
    {
        return MappingBarang::getMappingForDate($this->id, $date);
    }

    /**
     * Cari platform product berdasarkan nama + variant dengan logika yang SAMA
     * seperti saat import diproses (processImport).
     *
     * Penting: preview import dan proses import harus memakai resolver ini,
     * supaya hitungan kebutuhan stok di preview identik dengan pengurangan stok
     * saat import berjalan. Jika berbeda, preview bisa lolos padahal saat proses
     * stok kurang (dan transaksi di-rollback).
     *
     * Urutan pencarian:
     * 1. Exact match: nama produk + variant (jika ada variant spesifik)
     * 2. Exact match: nama produk + variant NULL/kosong (jika tanpa variant)
     * 3. Pencarian fleksibel (LIKE) — HANYA jika tanpa variant spesifik
     *
     * @param  int         $platformId
     * @param  string      $productName   Nama produk dari kolom nama barang Excel
     * @param  string|null $variation     Variant dari kolom variasi Excel
     * @return static|null
     */
    public static function resolveForPlatform($platformId, $productName, $variation = null)
    {
        $fullProductName = !empty($variation) ? "{$productName} - {$variation}" : $productName;

        // 1 & 2: pencarian exact
        $platformProduct = static::where('platform_id', $platformId)
            ->where(function ($query) use ($productName, $variation) {
                if (!empty($variation)) {
                    $query->where('platform_product_name', $productName)
                        ->where('variant', $variation);
                } else {
                    $query->where('platform_product_name', $productName)
                        ->where(function ($q) {
                            $q->whereNull('variant')
                              ->orWhere('variant', '');
                        });
                }
            })
            ->orderBy('id')
            ->first();

        if ($platformProduct) {
            return $platformProduct;
        }

        // 3: pencarian fleksibel hanya untuk baris tanpa variant spesifik
        if (!empty($variation)) {
            return null;
        }

        return static::where('platform_id', $platformId)
            ->where(function ($query) use ($productName, $fullProductName) {
                $query->where('platform_product_name', $fullProductName)
                    ->orWhere('platform_product_name', 'LIKE', '%' . $fullProductName . '%')
                    ->orWhere('platform_product_name', $productName)
                    ->orWhere('platform_product_name', 'LIKE', '%' . $productName . '%')
                    ->orWhere(DB::raw('LOWER(platform_product_name)'), 'LIKE', '%' . strtolower($productName) . '%');
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * Customize the array representation
     * 
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();
        
        // Add both camelCase and snake_case forms of the relationships for JS compatibility
        if ($this->relationLoaded('mappingBarang')) {
            $array['mappingBarang'] = $this->mappingBarang->toArray();
            $array['mapping_barang'] = $array['mappingBarang']; // For snake_case JS access
        }
        
        return $array;
    }
}