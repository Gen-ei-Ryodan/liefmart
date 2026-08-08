<?php

namespace App\Models;

use Carbon\Carbon;
use Shared\Helpers\MainCategoryHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStock extends Model
{
    use HasFactory;

    /**
     * Static flag to control whether order items with the same product but different
     * tax_ids should be consolidated into a single order item.
     * When true, order items will be consolidated by product regardless of tax_id.
     * Barang keluar records will still reflect the different tax_ids.
     * 
     * @var bool
     */
    public static $consolidateOrderItemsByProduct = false;

    protected $table = 'warehouse_stock';

    protected $fillable = [
        'product_id',
        'lokasi_id',
        'penerimaan_detail_id',
        'tax_id',
        'qty',
        'qty_damaged',
        'expired_date',
        'status_ed',
        'catatan',
        'is_damaged',
        'source_type',
        'source_id',
        'source_date',
    ];

    protected $casts = [
        'expired_date' => 'datetime',
        'source_date' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('mainCategory', function (Builder $builder) {
            $mainCategoryId = MainCategoryHelper::getSelectedMainCategoryId();
            if ($mainCategoryId) {
                // Only apply the scope if we're not loading relationships that might conflict
                // Check if we're in a context where we need to avoid the join
                $query = $builder->getQuery();
                $isLoadingRelationships = false;
                
                // Check if we're loading relationships that might conflict with the join
                if (method_exists($query, 'getEagerLoads') && $query->getEagerLoads()) {
                    $eagerLoads = $query->getEagerLoads();
                    foreach ($eagerLoads as $relation => $constraints) {
                        if (str_contains($relation, 'returPenjualan') || str_contains($relation, 'order')) {
                            $isLoadingRelationships = true;
                            break;
                        }
                    }
                }
                
                if (!$isLoadingRelationships) {
                    // Join with the products table to filter by main_category_id
                    $builder->join('products', 'warehouse_stock.product_id', '=', 'products.id')
                           ->where('products.main_category_id', $mainCategoryId)
                           ->select('warehouse_stock.*'); // Select only from warehouse_stock table
                } else {
                    // Use whereHas instead of join to avoid conflicts
                    $builder->whereHas('product', function ($q) use ($mainCategoryId) {
                        $q->where('main_category_id', $mainCategoryId);
                    });
                }
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class);
    }

    public function penerimaanDetail()
    {
        return $this->belongsTo(PenerimaanDetail::class);
    }

    public function tax()
    {
        return $this->belongsTo(TaxCategory::class, 'tax_id');
    }

    /**
     * Get the related retur penjualan if source_type is 'retur_penjualan'
     */
    public function returPenjualan()
    {
        return $this->belongsTo(ReturPenjualan::class, 'source_id');
    }

    /**
     * Get the related retur offline sale if source_type is 'retur_offline'
     */
    public function returOfflineSale()
    {
        return $this->belongsTo(ReturOfflineSale::class, 'source_id');
    }

    // Metode untuk mengakses satuan melalui penerimaanDetail
    public function getSatuanAttribute()
    {
        return $this->penerimaanDetail ? $this->penerimaanDetail->satuan : null;
    }

    // Mutator untuk status ED
    public function setStatusEdAttribute()
    {
        if (! $this->expired_date) {
            $this->attributes['status_ed'] = 'aman';

            return;
        }

        $daysUntilExpired = Carbon::now()->diffInDays($this->expired_date, false);

        if ($daysUntilExpired < 0) {
            $this->attributes['status_ed'] = 'kadaluarsa';
        } elseif ($daysUntilExpired <= 30) {
            $this->attributes['status_ed'] = 'hampir_kadaluarsa';
        } else {
            $this->attributes['status_ed'] = 'aman';
        }
    }
}
