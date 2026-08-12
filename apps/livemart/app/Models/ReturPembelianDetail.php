<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturPembelianDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'retur_pembelian_id',
        'penerimaan_detail_id',
        'product_id',
        'qty',
        'satuan_id',
        'alasan',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * Get the retur pembelian that owns the detail
     */
    public function returPembelian(): BelongsTo
    {
        return $this->belongsTo(ReturPembelian::class);
    }

    /**
     * Get the penerimaan detail associated with the retur
     */
    public function penerimaanDetail(): BelongsTo
    {
        return $this->belongsTo(PenerimaanDetail::class);
    }

    /**
     * Get the product being returned
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScope('mainCategory');
    }

    /**
     * Get the satuan (unit) for the returned item
     */
    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }
} 