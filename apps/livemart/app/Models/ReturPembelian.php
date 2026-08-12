<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturPembelian extends Model
{
    use HasFactory;

    protected $table = 'retur_pembelians';

    protected $fillable = [
        'kode_retur',
        'penerimaan_id',
        'user_id',
        'tanggal_retur',
        'catatan',
        'status',
        'tipe_retur',
    ];

    protected $casts = [
        'tanggal_retur' => 'date:Y-m-d',
    ];

    // Tipe retur constants
    const TIPE_SEBAGIAN = 'sebagian';
    const TIPE_FULL = 'full';

    /**
     * Get the penerimaan that owns the retur pembelian
     */
    public function penerimaan(): BelongsTo
    {
        return $this->belongsTo(Penerimaan::class)->withoutGlobalScope('mainCategory');
    }

    /**
     * Get the user that created the retur pembelian
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the details for the retur pembelian
     */
    public function details(): HasMany
    {
        return $this->hasMany(ReturPembelianDetail::class);
    }

    /**
     * Generate kode retur based on current date and id
     */
    public static function generateKodeRetur()
    {
        $today = now()->format('Ymd');
        $lastRetur = self::whereDate('created_at', now())->latest('id')->first();
        
        $sequence = $lastRetur ? (intval(substr($lastRetur->kode_retur, -4)) + 1) : 1;
        
        return 'RP' . $today . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Determine if the retur is full or partial.
     * Konsisten dengan logika ReturPembelianController (store/update):
     * full hanya jika semua produk di PO diretur penuh sesuai qty.
     */
    public function determineTipeRetur()
    {
        if (!$this->penerimaan_id) {
            return self::TIPE_SEBAGIAN;
        }

        $penerimaanDetails = PenerimaanDetail::where('penerimaan_id', $this->penerimaan_id)->get();
        $totalPoProductsCount = $penerimaanDetails->count();

        // Total qty per produk di PO
        $poTotalQtyPerProduct = [];
        foreach ($penerimaanDetails as $poDetail) {
            $productId = $poDetail->product_id;
            $poTotalQtyPerProduct[$productId] = ($poTotalQtyPerProduct[$productId] ?? 0) + (float) $poDetail->qty;
        }

        // Qty retur per produk untuk retur ini
        $returQtyPerProduct = [];
        $returProductCount = 0;
        foreach ($this->details as $detail) {
            $productId = $detail->product_id;
            if (!isset($returQtyPerProduct[$productId])) {
                $returQtyPerProduct[$productId] = 0;
                $returProductCount++;
            }
            $returQtyPerProduct[$productId] += (float) $detail->qty;
        }

        // Full hanya jika semua produk di PO diretur penuh sesuai qty
        $allProductsFullyReturned = true;
        foreach ($poTotalQtyPerProduct as $productId => $totalQty) {
            $returQty = $returQtyPerProduct[$productId] ?? 0;
            if ($returQty < $totalQty) {
                $allProductsFullyReturned = false;
                break;
            }
        }

        return ($returProductCount == $totalPoProductsCount && $allProductsFullyReturned)
            ? self::TIPE_FULL
            : self::TIPE_SEBAGIAN;
    }
} 
