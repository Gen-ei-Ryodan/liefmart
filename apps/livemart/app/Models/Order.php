<?php

namespace App\Models;

use Shared\Helpers\MainCategoryHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'platform_id',
        'order_number',
        'order_date',
        'customer_name',
        'platform',
        'total_amount',
        'status',
        'tanggal',
        'hari',
        'status_hari',
        'main_category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tanggal' => 'date',
        'order_date' => 'date',
        'total_amount' => 'decimal:2',
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
                $builder->whereHas('orderItems.warehouseStock.product', function($query) use ($mainCategoryId) {
                    $query->where('main_category_id', $mainCategoryId);
                });
            }
        });
    }

    /**
     * Relasi ke platform
     */
    public function platform()
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Relasi ke order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the main category for this order
     */
    public function mainCategory()
    {
        return $this->belongsTo(MainCategory::class);
    }

    /**
     * Relation to Shopee financial transactions
     */
public function shopeeFinancialTransactions()
    {
        return $this->hasMany(ShopeeFinancialTransaction::class);
    }
    
    /**
     * Relation to Shopee2 financial transactions
     */
    public function shopee2FinancialTransactions()
    {
        return $this->hasMany(Shopee2FinancialTransaction::class);
    }
    
    /**
     * Relation to TikTok financial transactions
     */
    public function tiktokFinancialTransactions()
    {
        return $this->hasMany(TiktokFinancialTransaction::class);
    }
    
    /**
     * Relation to TikTok2 financial transactions
     */
    public function tiktok2FinancialTransactions()
    {
        return $this->hasMany(Tiktok2FinancialTransaction::class);
    }
    
    /**
     * Relation to retur penjualan (sales returns)
     */
    public function returPenjualan()
    {
        return $this->hasMany(ReturPenjualan::class);
    }

    /**
     * Check if this order has any returns
     */
    public function hasReturns()
    {
        // Pakai cache primeHasReturns() bila sudah di-prime (mis. di halaman
        // finance yang menampilkan banyak baris), agar tidak query per baris
        if (array_key_exists((int) $this->id, static::$hasReturnsCache)) {
            return static::$hasReturnsCache[(int) $this->id];
        }

        return $this->returPenjualan()
            ->whereIn('status', ['draft', 'selesai'])
            ->exists();
    }

    /**
     * Cache hasil hasReturns() per order untuk satu request.
     *
     * @var array<int, bool>
     */
    protected static array $hasReturnsCache = [];

    /**
     * Isi cache hasReturns() untuk banyak order sekaligus (1 query per chunk),
     * supaya view tidak menjalankan query exists per baris.
     *
     * @param  array<int, int>  $orderIds
     * @return void
     */
    public static function primeHasReturns($orderIds): void
    {
        $ids = array_values(array_unique(array_filter($orderIds)));

        foreach (array_chunk($ids, 1000) as $chunk) {
            // Default false, order yang punya retur draft/selesai menjadi true
            $flags = array_fill_keys($chunk, false);

            $returOrderIds = ReturPenjualan::whereIn('order_id', $chunk)
                ->whereIn('status', ['draft', 'selesai'])
                ->distinct()
                ->pluck('order_id');

            foreach ($returOrderIds as $orderId) {
                $flags[(int) $orderId] = true;
            }

            foreach ($flags as $orderId => $hasReturns) {
                static::$hasReturnsCache[$orderId] = $hasReturns;
            }
        }
    }

    /**
     * Cache total qty retur per order item untuk satu request.
     * Diisi lewat primeReturQty() agar loop besar tidak query per item (N+1).
     *
     * @var array<int, float>
     */
    protected static array $returQtyCache = [];

    /**
     * Ambil total qty retur (retur draft/selesai) untuk banyak order item sekaligus.
     *
     * Hasil identik dengan query per item di isFullyReturned(), hanya digabung
     * menjadi 1 query per 1.000 item. Panggil sebelum loop filter besar
     * (mis. filter isFullyReturned() di halaman finance).
     *
     * @param  array<int, int>  $orderItemIds
     * @return void
     */
    public static function primeReturQty($orderItemIds): void
    {
        $ids = array_values(array_unique(array_filter($orderItemIds)));

        foreach (array_chunk($ids, 1000) as $chunk) {
            // Item yang tidak punya retur dianggap 0, jadi semua id diisi di awal
            $sums = array_fill_keys($chunk, 0.0);

            $rows = ReturPenjualanDetail::whereIn('order_item_id', $chunk)
                ->whereHas('returPenjualan', function ($q) {
                    $q->whereIn('status', ['draft', 'selesai']);
                })
                ->groupBy('order_item_id')
                ->selectRaw('order_item_id, COALESCE(SUM(qty), 0) as total_qty')
                ->get();

            foreach ($rows as $row) {
                $sums[(int) $row->order_item_id] = (float) $row->total_qty;
            }

            foreach ($sums as $itemId => $total) {
                static::$returQtyCache[$itemId] = $total;
            }
        }
    }

    /**
     * Total qty retur satu order item: pakai cache primeReturQty() bila sudah
     * di-prime, kalau belum tetap query seperti biasa (aman untuk pemanggil lain).
     *
     * @param  int|string  $orderItemId
     * @return float
     */
    protected static function returQtyFor($orderItemId): float
    {
        $orderItemId = (int) $orderItemId;

        if (array_key_exists($orderItemId, static::$returQtyCache)) {
            return (float) static::$returQtyCache[$orderItemId];
        }

        return (float) ReturPenjualanDetail::where('order_item_id', $orderItemId)
            ->whereHas('returPenjualan', function ($q) {
                $q->whereIn('status', ['draft', 'selesai']);
            })
            ->sum('qty');
    }

    /**
     * Check if this order is fully returned (all items returned)
     */
    public function isFullyReturned()
    {
        if (!$this->relationLoaded('orderItems')) {
            $this->load('orderItems.platformProduct.mappingBarang');
        }
        
        $totalOriginalQuantity = 0;
        $totalReturnedQuantity = 0;
        
        foreach ($this->orderItems as $item) {
            // Calculate returned quantity for this item first
            // (pakai cache primeReturQty() bila sudah di-prime, agar tidak N+1)
            $returnedQuantityIndividual = static::returQtyFor($item->id);
            
            // Convert individual retur quantity back to package quantity
            $packageQuantity = 1; // Default for non-package products
            if ($item->platformProduct && $item->platformProduct->mappingBarang && $item->platformProduct->mappingBarang->count() > 0) {
                // Use only active mappings when determining package quantity
                $packageQuantity = $item->platformProduct->mappingBarang
                    ->where('is_active', true)
                    ->sum('quantity');
            }
            
            $returnedQuantity = $packageQuantity > 0 ? $returnedQuantityIndividual / $packageQuantity : $returnedQuantityIndividual;
            $totalReturnedQuantity += $returnedQuantity;
            
            // Calculate original quantity: current qty (after returns) + returned qty
            $originalQuantity = $item->quantity + $returnedQuantity;
            $totalOriginalQuantity += $originalQuantity;
        }
        
        // If total returned quantity equals or exceeds original quantity, this is fully returned
        return $totalReturnedQuantity >= $totalOriginalQuantity && $totalReturnedQuantity > 0;
    }
    
    /**
     * Calculate adjusted total value after returns
     * Returns the total value of remaining items (original - returned)
     */
    public function getAdjustedTotalValue(): float
    {
        if (!$this->relationLoaded('orderItems')) {
            $this->load('orderItems.platformProduct.mappingBarang');
        }
        
        $totalValue = 0;
        
        foreach ($this->orderItems as $item) {
            // Current quantity (already reduced by returns)
            $currentQty = (float)($item->quantity ?? 0);
            $price = (float)($item->price_after_discount ?? 0);
            
            $totalValue += $currentQty * $price;
        }
        
        return $totalValue;
    }
    
    /**
     * Calculate adjusted total quantity after returns
     * Returns the total quantity of remaining items
     */
    public function getAdjustedTotalQuantity(): float
    {
        if (!$this->relationLoaded('orderItems')) {
            $this->load('orderItems');
        }
        
        $totalQty = 0;
        
        foreach ($this->orderItems as $item) {
            // Current quantity (already reduced by returns)
            $currentQty = (float)($item->quantity ?? 0);
            $totalQty += $currentQty;
        }
        
        return $totalQty;
    }
    
    /**
     * Calculate returned total value
     * Returns the total value of returned items
     */
    public function getReturnedTotalValue(): float
    {
        if (!$this->relationLoaded('orderItems')) {
            $this->load('orderItems.platformProduct.mappingBarang');
        }
        
        $returnedValue = 0;
        
        foreach ($this->orderItems as $item) {
            // Calculate returned quantity for this item
            $returnedQuantityIndividual = \App\Models\ReturPenjualanDetail::where('order_item_id', $item->id)
                ->whereHas('returPenjualan', function($q) { 
                    $q->whereIn('status', ['draft', 'selesai']); 
                })
                ->sum('qty');
            
            // Convert individual retur quantity back to package quantity
            $packageQuantity = 1;
            if ($item->platformProduct && $item->platformProduct->mappingBarang && $item->platformProduct->mappingBarang->count() > 0) {
                $packageQuantity = $item->platformProduct->mappingBarang
                    ->where('is_active', true)
                    ->sum('quantity');
            }
            
            $returnedQuantity = $packageQuantity > 0 ? $returnedQuantityIndividual / $packageQuantity : $returnedQuantityIndividual;
            $price = (float)($item->price_after_discount ?? 0);
            
            $returnedValue += $returnedQuantity * $price;
        }
        
        return $returnedValue;
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
        if ($this->relationLoaded('orderItems')) {
            $array['orderItems'] = $this->orderItems->toArray();
            $array['order_items'] = $array['orderItems']; // For snake_case JS access
        }
        
        if ($this->relationLoaded('platform')) {
            $array['platform'] = $this->platform ? $this->platform->toArray() : null;
        }
        
        return $array;
    }
}
