<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenerimaanDetail extends Model
{
    use HasFactory;
    
    protected $table = 'penerimaan_detail';
    
    protected $fillable = [
        'penerimaan_id',
        'product_id',
        'qty',
        'satuan_id',
        'harga_hpp',
        'diskon_persen_1',
        'diskon_nominal_1',
        'diskon_persen_2',
        'diskon_nominal_2',
        'diskon_persen_3',
        'diskon_nominal_3',
        'diskon_persen_4',
        'diskon_nominal_4',
        'diskon_persen_5',
        'diskon_nominal_5',
        'is_free',
        'subtotal',
        'catatan'
    ];
    
    protected $casts = [
        'qty' => 'integer',
        'harga_hpp' => 'decimal:2',
        'diskon_persen_1' => 'decimal:2',
        'diskon_nominal_1' => 'decimal:2',
        'diskon_persen_2' => 'decimal:2',
        'diskon_nominal_2' => 'decimal:2',
        'diskon_persen_3' => 'decimal:2',
        'diskon_nominal_3' => 'decimal:2',
        'diskon_persen_4' => 'decimal:2',
        'diskon_nominal_4' => 'decimal:2',
        'diskon_persen_5' => 'decimal:2',
        'diskon_nominal_5' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'is_free' => 'boolean',
    ];
    
    public function penerimaan(): BelongsTo
    {
        return $this->belongsTo(Penerimaan::class);
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }
}