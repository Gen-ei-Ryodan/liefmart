<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Shared\Models\BankAccount;
use App\Models\InvoiceSequence;

class Tiktok2FinancialTransaction extends Model
{
    use HasFactory;

    protected $table = 'tiktok2_financial_transactions';

    protected $fillable = [
        'tanggal_order',
        'hari_order',
        'no_order',
        'no_invoice',
        'nominal_harga',
        'nominal_diskon1',
        'nominal_diskon2',
        'nominal_diskon3',
        'nominal_diskon4',
        'nominal_diskon5',
        'nominal_diskon6',
        'nominal_diskon7',
        'nominal_diskon8',
        'nominal_diskon9',
        'nominal_diskon10',
        'nominal_diskon11',
        'nominal_diskon12',
        'adjustment',
        'adjustment_description',
        'nominal_fix',
        'qty',
        'saldo_masuk',
        'tanggal_masuk_pembayaran',
        'hari_masuk_pembayaran',
        'outstanding',
        'persentase_diskon1',
        'persentase_diskon2',
        'persentase_diskon3',
        'persentase_diskon4',
        'persentase_diskon5',
        'persentase_diskon6',
        'persentase_diskon7',
        'persentase_diskon8',
        'persentase_diskon9',
        'persentase_diskon10',
        'persentase_diskon11',
        'persentase_diskon12',
        'total_persentase',
        'order_id',
    ];

    protected $casts = [
        'tanggal_order' => 'date',
        'tanggal_masuk_pembayaran' => 'date',
        'nominal_harga' => 'decimal:2',
        'nominal_diskon1' => 'decimal:2',
        'nominal_diskon2' => 'decimal:2',
        'nominal_diskon3' => 'decimal:2',
        'nominal_diskon4' => 'decimal:2',
        'nominal_diskon5' => 'decimal:2',
        'nominal_diskon6' => 'decimal:2',
        'nominal_diskon7' => 'decimal:2',
        'nominal_diskon8' => 'decimal:2',
        'nominal_diskon9' => 'decimal:2',
        'nominal_diskon10' => 'decimal:2',
        'nominal_diskon11' => 'decimal:2',
        'nominal_diskon12' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'nominal_fix' => 'decimal:2',
        'qty' => 'decimal:2',
        'saldo_masuk' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'persentase_diskon1' => 'decimal:4',
        'persentase_diskon2' => 'decimal:4',
        'persentase_diskon3' => 'decimal:4',
        'persentase_diskon4' => 'decimal:4',
        'persentase_diskon5' => 'decimal:4',
        'persentase_diskon6' => 'decimal:4',
        'persentase_diskon7' => 'decimal:4',
        'persentase_diskon8' => 'decimal:4',
        'persentase_diskon9' => 'decimal:4',
        'persentase_diskon10' => 'decimal:4',
        'persentase_diskon11' => 'decimal:4',
        'persentase_diskon12' => 'decimal:4',
        'total_persentase' => 'decimal:4',
    ];

    /**
     * Relasi ke model Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Menghasilkan nomor invoice berdasarkan tax_id dari warehouse_stock
     * 
     * @param Order $order
     * @param int $taxId
     * @return string
     */
    public static function generateInvoiceNumber($order, $taxId)
    {
        // Mendapatkan kategori berdasarkan tax_id
        $category = ($taxId == 1 || $taxId == 2 || $taxId == 5 || $taxId == 6) 
            ? InvoiceSequence::CATEGORY_KOPI 
            : InvoiceSequence::CATEGORY_SKINCARE;
            
        // Mendapatkan jenis penjualan (untuk Tiktok2 selalu ONLINE)
        $salesType = InvoiceSequence::SALES_ONLINE;
        
        // Mendapatkan status pajak
        $taxStatus = in_array($taxId, [1, 3, 5, 7]) 
            ? InvoiceSequence::TAX_PKP 
            : InvoiceSequence::TAX_NON_PKP;
        
        // Ambil tanggal order PASTI dari tabel orders - TIDAK BOLEH NULL
        $orderDate = $order->tanggal 
            ?? Order::where('order_number', $order->order_number)->value('tanggal')
            ?? throw new \Exception("Tanggal order tidak ditemukan untuk Order {$order->order_number}");
        
        // Mendapatkan nomor invoice dari InvoiceSequence dengan tanggal ORDER
        $invoiceData = InvoiceSequence::getNextInvoiceNumber($category, $salesType, $taxStatus, $orderDate);
        
        return $invoiceData['invoice_number'];
    }

    /**
     * Menghasilkan nomor invoice sederhana
     * @deprecated Use generateInvoiceNumber instead
     */
    public static function generateSimpleInvoiceNumber($order)
    {
        // Format sederhana: INV-ORDER_NUMBER
        $prefix = 'INV';
        return "{$prefix}-{$order->order_number}";
    }

    /**
     * Mendapatkan data dari order terkait
     */
    public function setDataFromOrder($order)
    {
        $this->tanggal_order = $order->tanggal;
        $this->hari_order = $order->hari;
        $this->no_order = $order->order_number;
        $this->order_id = $order->id;
        
        // Calculate total quantity across all order items
        $totalQty = $order->orderItems->sum('quantity');
        $this->qty = $totalQty;
        
        // Calculate total invoice value (price_after_discount × quantity)
        $totalInvoiceValue = 0;
        foreach ($order->orderItems as $item) {
            $totalInvoiceValue += $item->price_after_discount * $item->quantity;
        }
        $this->nominal_harga = $totalInvoiceValue;
        
        return $this;
    }

    /**
     * Calculate the nominal_fix value
     * 
     * @return $this
     */
    public function calculateNominalFix()
    {
        // Since discount values are already stored as negative numbers,
        // we simply add them to the nominal_harga (which effectively subtracts them)
        $this->nominal_fix = $this->nominal_harga + 
            ($this->nominal_diskon1 ?? 0) + 
            ($this->nominal_diskon2 ?? 0) + 
            ($this->nominal_diskon3 ?? 0) + 
            ($this->nominal_diskon4 ?? 0) + 
            ($this->nominal_diskon5 ?? 0) + 
            ($this->nominal_diskon6 ?? 0) + 
            ($this->nominal_diskon7 ?? 0) + 
            ($this->nominal_diskon8 ?? 0) + 
            ($this->nominal_diskon9 ?? 0) + 
            ($this->nominal_diskon10 ?? 0) + 
            ($this->nominal_diskon11 ?? 0) + 
            ($this->nominal_diskon12 ?? 0) + 
            ($this->adjustment ?? 0);
            
        return $this;
    }

    /**
     * Calculate the outstanding value
     * 
     * @return $this
     */
    public function calculateOutstanding()
    {
        $this->outstanding = round($this->nominal_fix - ($this->saldo_masuk ?? 0), 2);
        return $this;
    }

    /**
     * Calculate percentages for the transaction
     * 
     * @return $this
     */
    public function calculatePercentages()
    {
        if ($this->nominal_harga > 0) {
            $this->persentase_diskon1 = ($this->nominal_diskon1 / $this->nominal_harga) * 100;
            $this->persentase_diskon2 = ($this->nominal_diskon2 / $this->nominal_harga) * 100;
            $this->persentase_diskon3 = ($this->nominal_diskon3 / $this->nominal_harga) * 100;
            $this->persentase_diskon4 = ($this->nominal_diskon4 / $this->nominal_harga) * 100;
            $this->persentase_diskon5 = ($this->nominal_diskon5 / $this->nominal_harga) * 100;
            $this->persentase_diskon6 = ($this->nominal_diskon6 / $this->nominal_harga) * 100;
            $this->persentase_diskon7 = ($this->nominal_diskon7 / $this->nominal_harga) * 100;
            $this->persentase_diskon8 = ($this->nominal_diskon8 / $this->nominal_harga) * 100;
            $this->persentase_diskon9 = ($this->nominal_diskon9 / $this->nominal_harga) * 100;
            $this->persentase_diskon10 = ($this->nominal_diskon10 / $this->nominal_harga) * 100;
            $this->persentase_diskon11 = ($this->nominal_diskon11 / $this->nominal_harga) * 100;
            $this->persentase_diskon12 = ($this->nominal_diskon12 / $this->nominal_harga) * 100;
            $this->total_persentase = $this->persentase_diskon1 + $this->persentase_diskon2 + 
                                     $this->persentase_diskon3 + $this->persentase_diskon4 + 
                                     $this->persentase_diskon5 + $this->persentase_diskon6 +
                                     $this->persentase_diskon7 + $this->persentase_diskon8 +
                                     $this->persentase_diskon9 + $this->persentase_diskon10 +
                                     $this->persentase_diskon11 + $this->persentase_diskon12;
        }
        
        return $this;
    }
    
    /**
     * Format display tanggal
     */
    public function getFormattedDateAttribute()
    {
        return $this->tanggal_order ? Carbon::parse($this->tanggal_order)->format('d-m-Y') : '';
    }
    
    /**
     * Format display tanggal pembayaran
     */
    public function getFormattedPaymentDateAttribute()
    {
        return $this->tanggal_masuk_pembayaran ? Carbon::parse($this->tanggal_masuk_pembayaran)->format('d-m-Y') : '';
    }

    /**
     * Synchronize order date with the related order
     * This method ensures that tanggal_order matches the order's tanggal
     */
    public function syncOrderDate()
    {
        if ($this->order && $this->order->tanggal) {
            $this->tanggal_order = $this->order->tanggal;
            $this->hari_order = $this->order->hari;
            return $this->save();
        }
        return false;
    }

    /**
     * Static method to synchronize all transactions with their order dates
     */
    public static function syncAllOrderDates()
    {
        $updated = 0;
        
        // Use chunking to avoid memory issues
        self::whereNotNull('order_id')
            ->with('order')
            ->chunk(100, function ($transactions) use (&$updated) {
                foreach ($transactions as $transaction) {
                    if ($transaction->order && $transaction->order->tanggal) {
                        $needsUpdate = false;
                        
                        // Check if tanggal_order needs updating
                        if (!$transaction->tanggal_order || $transaction->tanggal_order != $transaction->order->tanggal) {
                            $transaction->tanggal_order = $transaction->order->tanggal;
                            $needsUpdate = true;
                        }
                        
                        // Check if hari_order needs updating
                        if (!$transaction->hari_order || $transaction->hari_order != $transaction->order->hari) {
                            $transaction->hari_order = $transaction->order->hari;
                            $needsUpdate = true;
                        }
                        
                        if ($needsUpdate) {
                            $transaction->save();
                            $updated++;
                        }
                    }
                }
            });
        
        return $updated;
    }

    /**
     * Get active bank account for this transaction
     * 
     * @return \App\Models\BankAccount|null
     */
    public static function getActive()
    {
        return BankAccount::where('is_active', true)->first();
    }
    
    /**
     * Get bank account information for display on invoice
     * 
     * @return array
     */
    public static function getBankAccountInfo()
    {
        $activeAccount = self::getActive();
        
        if ($activeAccount) {
            return [
                'bank_name' => $activeAccount->bank_name,
                'account_number' => $activeAccount->account_number,
                'account_name' => $activeAccount->account_name,
                'has_active' => true
            ];
        }
        
        // Default fallback information if no active account is set
        return [
            'bank_name' => 'DANAMON',
            'account_number' => '********** (Hubungi Admin)',
            'account_name' => 'PT. HARVEST GLOBAL NIAGA',
            'has_active' => false
        ];
    }
}
