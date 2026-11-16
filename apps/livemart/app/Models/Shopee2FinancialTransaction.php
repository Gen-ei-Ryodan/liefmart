<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Shared\Models\BankAccount;
use App\Models\InvoiceSequence;

class Shopee2FinancialTransaction extends Model
{
    use HasFactory;

    protected $table = 'shopee2_financial_transactions';

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
        'adjustment',
        'nominal_fix',
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
        'adjustment' => 'decimal:2',
        'nominal_fix' => 'decimal:2',
        'saldo_masuk' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'persentase_diskon1' => 'decimal:2',
        'persentase_diskon2' => 'decimal:2',
        'persentase_diskon3' => 'decimal:2',
        'persentase_diskon4' => 'decimal:2',
        'persentase_diskon5' => 'decimal:2',
        'persentase_diskon6' => 'decimal:2',
        'total_persentase' => 'decimal:2',
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
            
        // Mendapatkan jenis penjualan (untuk Shopee2 selalu ONLINE)
        $salesType = InvoiceSequence::SALES_ONLINE;
        
        // Mendapatkan status pajak
        $taxStatus = in_array($taxId, [1, 3, 5, 7]) 
            ? InvoiceSequence::TAX_PKP 
            : InvoiceSequence::TAX_NON_PKP;
        
        // Mendapatkan tanggal ORDER dari order object
        $orderDate = $order->tanggal ?? null;
        
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
        // Nominal fix is simply the nominal_harga minus all the discounts (which are stored as negative values)
        // and plus any adjustment
        $this->nominal_fix = $this->nominal_harga + 
            ($this->nominal_diskon1 ?? 0) + 
            ($this->nominal_diskon2 ?? 0) + 
            ($this->nominal_diskon3 ?? 0) + 
            ($this->nominal_diskon4 ?? 0) + 
            ($this->nominal_diskon5 ?? 0) + 
            ($this->nominal_diskon6 ?? 0) + 
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
        // Outstanding is simply nominal_fix minus saldo_masuk
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
            $this->total_persentase = $this->persentase_diskon1 + $this->persentase_diskon2 + 
                                     $this->persentase_diskon3 + $this->persentase_diskon4 + 
                                     $this->persentase_diskon5 + $this->persentase_diskon6;
        } else {
            $this->persentase_diskon1 = 0;
            $this->persentase_diskon2 = 0;
            $this->persentase_diskon3 = 0;
            $this->persentase_diskon4 = 0;
            $this->persentase_diskon5 = 0;
            $this->persentase_diskon6 = 0;
            $this->total_persentase = 0;
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
