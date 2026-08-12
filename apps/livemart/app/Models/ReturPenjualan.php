<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturPenjualan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_retur',
        'order_id',
        'user_id',
        'tanggal_retur',
        'catatan',
        'status',
    ];

    protected $casts = [
        'tanggal_retur' => 'date:Y-m-d',
    ];

    /**
     * Get the order that is being returned
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScope('mainCategory');
    }

    /**
     * Get the user that created the retur penjualan
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the details for the retur penjualan
     */
    public function details(): HasMany
    {
        return $this->hasMany(ReturPenjualanDetail::class);
    }

    /**
     * Generate kode retur based on current date and id
     */
    public static function generateKodeRetur()
    {
        $today = now()->format('Ymd');
        $lastRetur = self::whereDate('created_at', now())->latest('id')->first();
        
        $sequence = $lastRetur ? (intval(substr($lastRetur->kode_retur, -4)) + 1) : 1;
        
        return 'RJ' . $today . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
} 