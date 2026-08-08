<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Shared\Helpers\MainCategoryHelper;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SuratJalanSequence;

class OfflineSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'surat_jalan_number',
        'No_PO',
        'sale_date',
        'customer_name',
        'customer_id',
        'status',
        'payment_date',
        'payment_method',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'created_by',
        'main_category_id',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
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
                $builder->where(function($query) use ($mainCategoryId) {
                    $query->where('main_category_id', $mainCategoryId)
                          ->orWhereNull('main_category_id');
                });
            }
        });
    }

    /**
     * Get all items for this offline sale
     */
    public function items()
    {
        return $this->hasMany(OfflineSaleItem::class);
    }

    /**
     * Get the user who created this sale
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the customer for this sale
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_name', 'name');
    }

    // Add a direct relationship using customer_id
    public function customerInfo()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the main category for this sale
     */
    public function mainCategory()
    {
        return $this->belongsTo(MainCategory::class);
    }

    /**
     * Get all retur offline sales for this sale
     */
    public function returOfflineSales()
    {
        return $this->hasMany(ReturOfflineSale::class);
    }

    /**
     * Get all finance offline records related to this sale
     */
    public function getFinanceOfflineAttribute()
    {
        return $this->getInvoices();
    }

    /**
     * Check if this offline sale has any invoices
     */
    public function hasInvoices()
    {
        return $this->items()
            ->whereHas('barangKeluar', function($query) {
                $query->whereNotNull('finance_offline_id');
            })
            ->exists();
    }

    /**
     * Get all invoices related to this offline sale
     */
    public function getInvoices()
    {
        return FinanceOffline::whereHas('barangKeluarItems', function($query) {
            $query->whereHas('offlineSaleItem', function($subQuery) {
                $subQuery->where('offline_sale_id', $this->id);
            });
        })->get();
    }

    /**
     * Check if all invoices related to this offline sale are paid
     */
    public function areAllInvoicesPaid()
    {
        $invoices = $this->getInvoices();
        
        if ($invoices->isEmpty()) {
            return false; // No invoices means not paid
        }
        
        return $invoices->every(function($invoice) {
            return $invoice->status === 'paid';
        });
    }

    /**
     * Check if any invoice related to this offline sale is paid
     */
    public function hasAnyPaidInvoice()
    {
        $invoices = $this->getInvoices();
        
        return $invoices->contains(function($invoice) {
            return $invoice->status === 'paid';
        });
    }

    /**
     * Get the payment status of this offline sale
     * Returns: 'pending', 'partial', 'paid'
     */
    public function getPaymentStatus()
    {
        // Kolom status offline_sales adalah sumber kebenaran utama.
        // Jika penjualan sudah ditandai LUNAS langsung saat input (mis. bayar tunai),
        // anggap lunas meskipun invoice finance belum digenerate — konsisten dengan halaman detail.
        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        $invoices = $this->getInvoices();

        if ($invoices->isEmpty()) {
            return 'pending'; // No invoices created yet
        }

        $allPaid = $invoices->every(function($invoice) {
            return $invoice->status === 'paid';
        });

        $anyPaid = $invoices->contains(function($invoice) {
            return $invoice->status === 'paid';
        });

        if ($allPaid) {
            return 'paid';
        } elseif ($anyPaid) {
            return 'partial';
        } else {
            return 'pending';
        }
    }

    /**
     * Update the status based on payment status
     */
    public function updateStatusBasedOnPayment()
    {
        $paymentStatus = $this->getPaymentStatus();
        
        $newStatus = match($paymentStatus) {
            'paid' => 'paid',
            'partial' => 'pending', // Keep as pending if partially paid
            default => 'pending'
        };
        
        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Generate a unique surat jalan number based on tax ID and main category
     *
     * @param int $taxId
     * @param int $mainCategoryId
     * @param string $orderDate Tanggal ORDER (format: Y-m-d)
     * @return string
     */
    public static function generateSuratJalanNumber($taxId = null, $mainCategoryId = null, $orderDate = null)
    {
        // Jika tidak ada tanggal ORDER, gunakan tanggal saat ini
        if (!$orderDate) {
            $orderDate = Carbon::now()->format('Y-m-d');
        }
        
        // Jika main category tidak ditentukan, gunakan format lama
        if (!$mainCategoryId) {
            $today = Carbon::now()->format('Ymd');
            $latestSJ = self::where('surat_jalan_number', 'like', "SJ-OFF-{$today}%")
                ->orderBy('surat_jalan_number', 'desc')
                ->value('surat_jalan_number');

            if ($latestSJ) {
                $lastNumber = (int) substr($latestSJ, -4);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            return "SJ-OFF-{$today}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }
        
        // Gunakan sistem baru dengan SuratJalanSequence
        $result = SuratJalanSequence::getNextSuratJalanNumber($taxId, $mainCategoryId, $orderDate);
        
        return $result['surat_jalan_number'];
    }

    /**
     * Check if this offline sale has any completed returns.
     * Retur yang masih draft (belum di-Proses) atau dibatalkan tidak dihitung,
     * karena barangnya belum benar-benar diretur.
     */
    public function hasReturns()
    {
        return \App\Models\ReturOfflineSale::where('offline_sale_id', $this->id)
            ->where('status', 'selesai')
            ->exists();
    }

    /**
     * Get all completed returns for this offline sale
     */
    public function getReturns()
    {
        return \App\Models\ReturOfflineSale::where('offline_sale_id', $this->id)
            ->where('status', 'selesai')
            ->with('details')
            ->get();
    }

    /**
     * Check if this offline sale has full return (all items returned)
     * Retur full means all items have quantity = 0 after returns
     */
    public function hasReturFull()
    {
        // Check if there are any completed returns first
        $hasReturns = $this->hasReturns();
        
        if (!$hasReturns) {
            return false;
        }
        
        // Load items if not already loaded
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }
        
        // Check if all items have quantity = 0 (all items returned)
        // Retur full = has returns AND all items quantity = 0 AND has at least one item
        $items = $this->items;
        if ($items->isEmpty()) {
            return false;
        }
        
        $allItemsReturned = $items->every(function($item) {
            return $item->quantity == 0;
        });
        
        return $allItemsReturned;
    }
} 