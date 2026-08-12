<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturOfflineSaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'retur_offline_sale_id',
        'offline_sale_item_id',
        'product_id',
        'qty',
        'kondisi',
        'alasan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * Get the retur offline sale that owns the detail
     */
    public function returOfflineSale(): BelongsTo
    {
        return $this->belongsTo(ReturOfflineSale::class, 'retur_offline_sale_id');
    }

    /**
     * Get the offline sale item associated with the retur
     */
    public function offlineSaleItem(): BelongsTo
    {
        return $this->belongsTo(OfflineSaleItem::class, 'offline_sale_item_id');
    }

    /**
     * Get the product being returned
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScope('mainCategory');
    }
} 