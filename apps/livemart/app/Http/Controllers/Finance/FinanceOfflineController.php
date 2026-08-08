<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FinanceOffline;
use App\Models\BarangKeluar;
use App\Models\OfflineSale;
use App\Models\ReturOfflineSale;
use App\Exports\FinanceOfflineInvoiceExport;
use App\Services\ReturFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class FinanceOfflineController extends Controller
{
    /**
     * Display a listing of the barang from penjualan offline.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Query for barang_keluar items with their relationships
        $query = BarangKeluar::whereHas('offlineSaleItem')
            ->with([
                'warehouseStock',
                'warehouseStock.product',
                'warehouseStock.tax',
                'financeOffline',
                'offlineSaleItem',
                'offlineSaleItem.product',
                'offlineSaleItem.product.mainCategory',
                'offlineSaleItem.offlineSale',
                'offlineSaleItem.offlineSale.customerInfo',
                'offlineSaleItem.offlineSale.mainCategory'
            ]);

        // Apply date filters
        if ($request->filled('date_start')) {
            $query->whereHas('offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->whereDate('sale_date', '>=', $request->date_start);
            });
        }

        if ($request->filled('date_end')) {
            $query->whereHas('offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->whereDate('sale_date', '<=', $request->date_end);
            });
        }

        // Apply PO number filter
        if ($request->filled('no_po')) {
            $query->whereHas('offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->where('No_PO', 'like', '%' . $request->no_po . '%');
            });
        }

        // Apply SJ number filter
        if ($request->filled('sj_number')) {
            $query->whereHas('offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->where('surat_jalan_number', 'like', '%' . $request->sj_number . '%');
            });
        }

        // Apply customer filter
        if ($request->filled('customer')) {
            $query->whereHas('offlineSaleItem.offlineSale.customerInfo', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer . '%');
            });
        }

        // Apply product name filter - simplified to avoid complex nested queries
        if ($request->filled('product_name')) {
            $productSearch = '%' . $request->product_name . '%';
            $query->whereHas('offlineSaleItem.product', function($q) use ($productSearch) {
                $q->where('name', 'like', $productSearch);
            });
        }

        // Apply tax_id filter
        if ($request->filled('tax_id')) {
            $query->whereHas('warehouseStock', function($q) use ($request) {
                $q->where('tax_id', $request->tax_id);
            });
        }

        // Apply invoice status filter
        if ($request->filled('invoice_status')) {
            if ($request->invoice_status == 'with_invoice') {
                $query->whereNotNull('finance_offline_id');
            } elseif ($request->invoice_status == 'no_invoice') {
                $query->whereNull('finance_offline_id');
            }
        }
        
        // Apply main_category filter from session if exists
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->where(function($q) use ($mainCategoryId) {
                $q->whereHas('offlineSaleItem.offlineSale', function($subQ) use ($mainCategoryId) {
                    $subQ->where('main_category_id', $mainCategoryId);
                })
                ->orWhereHas('warehouseStock.product', function($subQ) use ($mainCategoryId) {
                    $subQ->where('main_category_id', $mainCategoryId);
                });
            });
        }

        // Execute the query with ordering
        $barangKeluarItems = $query->orderBy('created_at', 'desc')->get();

        // Group items by purchase order (offline_sale_id)
        $groupedItems = $barangKeluarItems->groupBy(function($item) {
            return $item->offlineSaleItem ? $item->offlineSaleItem->offline_sale_id : 'no_sale';
        });

        // Paginate the grouped items
        $perPage = 20;
        $currentPage = $request->input('page', 1);
        $groupedItemsCollection = collect($groupedItems);
        $currentPageItems = $groupedItemsCollection->forPage($currentPage, $perPage);
        
        $groupedItemsPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $groupedItemsCollection->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('finance.offline.index', [
            'groupedItems' => $groupedItemsPaginator
        ]);
    }

    /**
     * Display a list of invoices.
     *
     * @return \Illuminate\Http\Response
     */
    public function listInvoices(Request $request)
    {
        $query = FinanceOffline::with([
            'barangKeluarItems', 
            'payments', 
            'barangKeluarItems.warehouseStock',
            'barangKeluarItems.warehouseStock.tax',
            'barangKeluarItems.warehouseStock.product.mainCategory',
            'barangKeluarItems.offlineSaleItem',
            'barangKeluarItems.offlineSaleItem.offlineSale',
            'barangKeluarItems.offlineSaleItem.offlineSale.customerInfo',
            'barangKeluarItems.offlineSaleItem.offlineSale.mainCategory',
            'barangKeluarItems.offlineSaleItem.offlineSale.returOfflineSales' => function($q) {
                $q->where('status', 'selesai')->with('details.offlineSaleItem');
            }
        ]);

        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Filter by SJ number
        if ($request->filled('sj_number')) {
            $query->whereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->where('surat_jalan_number', 'like', '%' . $request->sj_number . '%');
            });
        }

        // Filter by customer
        if ($request->filled('customer')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($subQ) use ($request) {
                    $subQ->where('customer_name', 'like', '%' . $request->customer . '%');
                })
                ->orWhereHas('barangKeluarItems.offlineSaleItem.offlineSale.customerInfo', function($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->customer . '%');
                });
            });
        }

        // Filter by payment status
        // Note: We'll filter after calculating status in the map function
        // because payment status depends on net total after return

        // Filter by date range - tanggal_invoice
        if ($request->filled('date_start')) {
            $query->whereDate('tanggal_invoice', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('tanggal_invoice', '<=', $request->date_end);
        }

        // Filter by tax ID (requires checking the related warehouse stock)
        if ($request->filled('tax_id')) {
            $query->whereHas('barangKeluarItems.warehouseStock', function($q) use ($request) {
                $q->where('tax_id', $request->tax_id);
            });
        }
        
        // Apply main_category filter from session if exists
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->where(function($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId)
                  ->orWhereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($subQ) use ($mainCategoryId) {
                      $subQ->where('main_category_id', $mainCategoryId);
                  })
                  ->orWhereHas('barangKeluarItems.warehouseStock.product', function($subQ) use ($mainCategoryId) {
                      $subQ->where('main_category_id', $mainCategoryId);
                  });
            });
        }

        // Get all invoices first to calculate payment status and sort
        // Use fresh query without any cache
        $allInvoices = $query->get();
        
        // Force refresh each invoice to get latest data from database
        foreach ($allInvoices as $invoice) {
            $invoice->refresh();
        }
        
        // Calculate payment status and sort (with net total after return)
        $invoicesWithStatus = $allInvoices->map(function($invoice) {
            $calcs = \App\Services\FinanceOfflineCalculator::calculate($invoice);

            // Parse tanggal_invoice menjadi Carbon biar tidak disort sebagai string
            $tanggalInvoice = $invoice->tanggal_invoice instanceof \Carbon\Carbon
                ? $invoice->tanggal_invoice
                : \Carbon\Carbon::parse($invoice->tanggal_invoice);

            return [
                'invoice' => $invoice,
                'is_fully_paid' => $calcs['is_fully_paid'],
                'payment_status' => $calcs['payment_status'],
                'tanggal_invoice' => $tanggalInvoice, // Carbon instance untuk sorting yang benar
            ];
        });
        
        // Filter by payment status if requested
        if ($request->filled('payment_status')) {
            $paymentStatusFilter = $request->payment_status;
            $invoicesWithStatus = $invoicesWithStatus->filter(function($item) use ($paymentStatusFilter) {
                return $item['payment_status'] === $paymentStatusFilter;
            });
        }
        
        // Urutkan invoice offline dengan:
        // 1. Pisahkan menjadi grup berdasarkan payment_status: belum lunas, lunas, tidak balance, retur full
        // 2. Parse tanggal_invoice menjadi Carbon biar tidak disort sebagai string (sudah dilakukan di map)
        // 3. Urutkan masing-masing grup dari tanggal paling lama ke paling baru (ASC)
        // 4. Gabungkan kembali dengan urutan: belum lunas -> lunas -> tidak balance -> retur full
        // 5. Pakai values() supaya urutan final tetap konsisten
        
        // Step 1: Pisahkan menjadi grup berdasarkan payment_status
        $belumLunas = $invoicesWithStatus->filter(function($item) {
            return $item['payment_status'] === 'belum_lunas';
        });
        
        $sudahLunas = $invoicesWithStatus->filter(function($item) {
            return $item['payment_status'] === 'lunas';
        });
        
        $tidakBalance = $invoicesWithStatus->filter(function($item) {
            return $item['payment_status'] === 'tidak_balance';
        });
        
        $lainLain = $invoicesWithStatus->filter(function($item) {
            return $item['payment_status'] === 'retur_full';
        });
        
        // Step 2 & 3: tanggal_invoice sudah di-parse menjadi Carbon di map function
        // Urutkan masing-masing grup dari tanggal paling lama ke paling baru (ASC)
        $belumLunasSorted = $belumLunas->sortBy('tanggal_invoice')->values();
        $sudahLunasSorted = $sudahLunas->sortBy('tanggal_invoice')->values();
        $tidakBalanceSorted = $tidakBalance->sortBy('tanggal_invoice')->values();
        $lainLainSorted = $lainLain->sortBy('tanggal_invoice')->values();
        
        // Step 4 & 5: Gabungkan kembali dengan urutan: belum lunas -> lunas -> tidak balance -> retur full
        // Pakai values() supaya urutan final tetap konsisten
        $sortedInvoices = $belumLunasSorted
            ->concat($sudahLunasSorted)
            ->concat($tidakBalanceSorted)
            ->concat($lainLainSorted)
            ->values();
        
        // Extract invoices from sorted collection (maintain order)
        $sortedInvoiceCollection = $sortedInvoices->pluck('invoice')->values();
        
        // Manual pagination
        $perPage = 20;
        $currentPage = $request->input('page', 1);
        $currentPageItems = $sortedInvoiceCollection->forPage($currentPage, $perPage);
        
        $invoices = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $sortedInvoiceCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query()
            ]
        );
        
        // Set query string for pagination links
        $invoices->appends($request->query());
        
        // Calculate summary based on filtered invoices (using NET total after return)
        $totalNetValue = 0;
        $totalPaidAmount = 0;
        $totalUnpaidAmount = 0;
        $totalReturAmount = 0;
        $paidCount = 0;
        $unpaidCount = 0;
        $partialCount = 0;
        $returFullCount = 0;
        $tidakBalanceCount = 0;
        
        // Use filtered invoices for summary
        $filteredInvoices = $invoicesWithStatus->pluck('invoice');
        
        foreach ($filteredInvoices as $invoice) {
            // Hitung semua nilai finansial via satu sumber kebenaran (FinanceOfflineCalculator)
            $calcs = \App\Services\FinanceOfflineCalculator::calculate($invoice);

            $returAmount = $calcs['retur_amount'];
            $netTotal = $calcs['net_total'];
            $totalPaid = $calcs['total_paid'];

            $totalReturAmount += $returAmount;

            // Tidak Balance: hanya muncul saat ada pembayaran > NET Total (NET DPP + PPN setelah retur)
            // NET Total = (DPP - Retur) + PPN
            if ($calcs['payment_status'] == 'retur_full') {
                // For refunded invoices, don't add to grand total (retur full means all items returned)
                $returFullCount++;
            } elseif ($calcs['payment_status'] == 'tidak_balance') {
                // Tidak Balance: pembayaran > NET Total (setelah retur)
                // Contoh: Bayar 100.000, DPP 100.000, Retur 20.000, NET DPP = 80.000
                // Jika PKP, NET Total = 80.000 + PPN. Jika pembayaran > NET Total, maka tidak balance
                $tidakBalanceCount++;
                $totalPaidAmount += $totalPaid;
                $totalUnpaidAmount += 0; // Already overpaid
                $totalNetValue += $netTotal; // Add to grand total (use net total, not overpaid amount)
            } elseif ($totalPaid >= $netTotal) {
                // Fully paid (compare with NET Total including PPN)
                // Use actual payment amount to match gross profit offline calculation
                $totalPaidAmount += $totalPaid;
                $totalNetValue += $netTotal; // Add to grand total only if not refunded
                $paidCount++;
            } elseif ($totalPaid > 0) {
                // Partially paid
                $totalPaidAmount += $totalPaid;
                $totalUnpaidAmount += ($netTotal - $totalPaid);
                $totalNetValue += $netTotal; // Add to grand total only if not refunded
                $partialCount++;
            } else {
                // Unpaid
                $totalUnpaidAmount += $netTotal;
                $totalNetValue += $netTotal; // Add to grand total only if not refunded
                $unpaidCount++;
            }
        }
        
        $summary = [
            'total_invoices' => $filteredInvoices->count(),
            'total_value' => $totalNetValue, // GRAND TOTAL (NET + PPN for PKP, NET only for Non-PKP)
            'total_paid' => $totalPaidAmount,
            'total_unpaid' => $totalUnpaidAmount,
            'total_retur' => $totalReturAmount,
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'partial_count' => $partialCount,
            'retur_full_count' => $returFullCount,
            'tidak_balance_count' => $tidakBalanceCount,
            'avg_invoice_value' => $filteredInvoices->count() > 0 ? $totalNetValue / $filteredInvoices->count() : 0,
        ];

        return view('finance.offline.list_invoices', compact('invoices', 'summary'));
    }

    /**
     * Export invoices to Excel
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function exportInvoices(Request $request)
    {
        // Use the same query logic as listInvoices but get all data
        $query = FinanceOffline::with(['barangKeluarItems', 'payments', 
                                        'barangKeluarItems.warehouseStock',
                                        'barangKeluarItems.warehouseStock.tax',
                                        'barangKeluarItems.warehouseStock.product.mainCategory',
                                        'barangKeluarItems.offlineSaleItem',
                                        'barangKeluarItems.offlineSaleItem.offlineSale',
                                        'barangKeluarItems.offlineSaleItem.offlineSale.customerInfo',
                                        'barangKeluarItems.offlineSaleItem.offlineSale.mainCategory',
                                        'barangKeluarItems.offlineSaleItem.offlineSale.returOfflineSales' => function($q) {
                                            $q->where('status', 'selesai')->with('details.offlineSaleItem');
                                        }]);

        // Apply the same filters as listInvoices
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Filter by SJ number
        if ($request->filled('sj_number')) {
            $query->whereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($q) use ($request) {
                $q->where('surat_jalan_number', 'like', '%' . $request->sj_number . '%');
            });
        }

        // Filter by customer
        if ($request->filled('customer')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($subQ) use ($request) {
                    $subQ->where('customer_name', 'like', '%' . $request->customer . '%');
                })
                ->orWhereHas('barangKeluarItems.offlineSaleItem.offlineSale.customerInfo', function($subQ) use ($request) {
                    $subQ->where('name', 'like', '%' . $request->customer . '%');
                });
            });
        }

        // Filter by date range - tanggal_invoice
        if ($request->filled('date_start')) {
            $query->whereDate('tanggal_invoice', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('tanggal_invoice', '<=', $request->date_end);
        }

        // Filter by tax ID (requires checking the related warehouse stock)
        if ($request->filled('tax_id')) {
            $query->whereHas('barangKeluarItems.warehouseStock', function($q) use ($request) {
                $q->where('tax_id', $request->tax_id);
            });
        }

        // Apply main_category filter from session if exists
        if (session()->has('main_category_id')) {
            $mainCategoryId = session('main_category_id');
            $query->where(function($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId)
                  ->orWhereHas('barangKeluarItems.offlineSaleItem.offlineSale', function($subQ) use ($mainCategoryId) {
                      $subQ->where('main_category_id', $mainCategoryId);
                  })
                  ->orWhereHas('barangKeluarItems.warehouseStock.product', function($subQ) use ($mainCategoryId) {
                      $subQ->where('main_category_id', $mainCategoryId);
                  });
            });
        }

        $invoices = $query->with('payments')->orderBy('created_at', 'desc')->get();

        // Filter by payment status (dihitung setelah status pembayaran dikomputasi,
        // karena status di DB (paid/unpaid/refunded/...) beda dengan status tampilan)
        if ($request->filled('payment_status')) {
            $paymentStatusFilter = $request->payment_status;
            $invoices = $invoices->filter(function ($invoice) use ($paymentStatusFilter) {
                return \App\Services\FinanceOfflineCalculator::calculate($invoice)['payment_status'] === $paymentStatusFilter;
            })->values();
        }

        $fileName = 'Finance_Offline_Invoices_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new FinanceOfflineInvoiceExport($invoices), $fileName);
    }

    /**
     * Update the status of offline sales related to a paid invoice
     */
    private function updateRelatedOfflineSalesStatus($invoice)
    {
        // Get all offline sales related to this invoice
        $offlineSaleIds = $invoice->barangKeluarItems()
            ->whereHas('offlineSaleItem')
            ->with('offlineSaleItem')
            ->get()
            ->pluck('offlineSaleItem.offline_sale_id')
            ->unique();
        
        // Update status for each offline sale
        foreach ($offlineSaleIds as $offlineSaleId) {
            $offlineSale = OfflineSale::withoutGlobalScope('mainCategory')->find($offlineSaleId);
            if ($offlineSale) {
                $offlineSale->updateStatusBasedOnPayment();
            }
        }
    }

    private function calculateNetTotalForInvoice(FinanceOffline $invoice): int
    {
        return \App\Services\FinanceOfflineCalculator::calculate($invoice)['net_total'];
    }
    
    /**
     * Mark an invoice as paid.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function markAsPaid(Request $request, $id)
    {
        $invoice = FinanceOffline::findOrFail($id);

        $dbStatus = $invoice->status ?? 'unpaid';
        if ($dbStatus == 'refunded' || $dbStatus == 'retur_full') {
            return redirect()->back()->with('error', 'Invoice retur full tidak bisa dibayar.');
        }

        $netTotal = $this->calculateNetTotalForInvoice($invoice);
        $totalPaidBefore = \App\Helpers\NumberFormatter::roundToWholeNumber($invoice->payments()->sum('amount'));
        $remainingAmount = max(0, $netTotal - $totalPaidBefore);

        if ($remainingAmount <= 0) {
            return redirect()->back()->with('error', 'Invoice ini sudah lunas.');
        }
        
        // Get payment details from the request
        $paymentAmount = \App\Helpers\NumberFormatter::roundToWholeNumber($request->input('payment_amount', $remainingAmount));
        $paymentDate = $request->input('payment_date', now()->format('Y-m-d'));
        $paymentMethod = $request->input('payment_method', 'transfer');
        $paymentNotes = $request->input('payment_notes');

        if ($paymentAmount <= 0) {
            return redirect()->back()->with('error', 'Nominal pembayaran tidak valid.');
        }

        if ($paymentAmount > $remainingAmount) {
            return redirect()->back()->with('error', 'Nominal pembayaran melebihi sisa tagihan.');
        }
        
        // Create a new payment record
        $payment = new \App\Models\InvoicePayment([
            'finance_offline_id' => $invoice->id,
            'amount' => $paymentAmount,
            'payment_date' => $paymentDate,
            'payment_method' => $paymentMethod,
            'notes' => $paymentNotes,
        ]);
        
        $payment->save();
        
        $totalPaid = \App\Helpers\NumberFormatter::roundToWholeNumber($invoice->payments()->sum('amount'));
        
        if ($totalPaid >= $netTotal) {
            $invoice->status = 'paid';
            $invoice->tanggal_bayar = $paymentDate;
            $invoice->save();
            
            // Update status of related offline sales
            $this->updateRelatedOfflineSalesStatus($invoice);
        }
        
        return redirect()->route('finance.offline.invoices')
            ->with('success', 'Pembayaran sebesar Rp ' . number_format($paymentAmount, 0, ',', '.') . ' berhasil dicatat.');
    }

    /**
     * Adjust payment to balance invoice (reduce overpayment to match net total)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function adjustPayment(Request $request, $id)
    {
        // Check if user is superadmin
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk menyesuaikan pembayaran.');
        }

        try {
            DB::beginTransaction();

            $invoice = FinanceOffline::findOrFail($id);
            
            $netTotal = $this->calculateNetTotalForInvoice($invoice);
            
            $totalPaid = \App\Helpers\NumberFormatter::roundToWholeNumber($invoice->payments->sum('amount'));
            $excessAmount = $totalPaid - $netTotal;
            
            if ($excessAmount <= 0) {
                return redirect()->back()->with('error', 'Invoice ini tidak memiliki kelebihan pembayaran.');
            }
            
            // Create adjustment payment (negative amount)
            $adjustmentPayment = new \App\Models\InvoicePayment([
                'finance_offline_id' => $invoice->id,
                'amount' => -$excessAmount, // Negative amount to reduce total
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'adjustment',
                'notes' => 'Penyesuaian pembayaran karena retur sebagian - dari Rp ' . number_format($totalPaid, 0, ',', '.') . ' menjadi Rp ' . number_format($netTotal, 0, ',', '.'),
            ]);
            
            $adjustmentPayment->save();
            
            // Update invoice status
            $newTotalPaid = \App\Helpers\NumberFormatter::roundToWholeNumber($invoice->payments->sum('amount'));
            
            if ($invoice->status != 'partial_refund') {
                $invoice->status = $newTotalPaid >= $netTotal ? 'paid' : 'unpaid';
            }
            
            $invoice->save();
            
            // Update status of related offline sales
            $this->updateRelatedOfflineSalesStatus($invoice);
            
            DB::commit();
            
            return redirect()->back()
                ->with('success', 'Pembayaran berhasil disesuaikan. Kelebihan pembayaran sebesar Rp ' . number_format($excessAmount, 0, ',', '.') . ' telah dikurangi.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan saat menyesuaikan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * Delete a payment record (superadmin only).
     *
     * @param  int  $paymentId
     * @return \Illuminate\Http\Response
     */
    public function deletePayment($paymentId)
    {
        // Check if user is superadmin
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk menghapus pembayaran.');
        }

        try {
            DB::beginTransaction();

            $payment = \App\Models\InvoicePayment::findOrFail($paymentId);
            $invoice = $payment->invoice;
            $deletedAmount = $payment->amount;

            // Delete the payment record
            $payment->delete();

            // Recalculate invoice status
            $dbStatus = $invoice->status ?? 'unpaid';
            if ($dbStatus != 'refunded' && $dbStatus != 'retur_full') {
                $netTotal = $this->calculateNetTotalForInvoice($invoice);
                $totalPaid = \App\Helpers\NumberFormatter::roundToWholeNumber($invoice->payments()->sum('amount'));
                
                if ($totalPaid >= $netTotal) {
                    $invoice->status = 'paid';
                    $latestPayment = $invoice->payments()->latest('payment_date')->first();
                    $invoice->tanggal_bayar = $latestPayment ? $latestPayment->payment_date : null;
                } else {
                    $invoice->status = 'unpaid';
                    $invoice->tanggal_bayar = null;
                }
            }
            
            $invoice->save();

            // Update status of related offline sales
            $this->updateRelatedOfflineSalesStatus($invoice);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Pembayaran sebesar Rp ' . number_format($deletedAmount, 0, ',', '.') . ' berhasil dihapus.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Terjadi kesalahan saat menghapus pembayaran: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate invoice for a barang_keluar item.
     *
     * @param  int  $saleId
     * @return \Illuminate\Http\Response
     */
    public function generateInvoice($saleId)
    {
        // Find the offline sale and its items
        $offlineSale = OfflineSale::with(['items.barangKeluar.warehouseStock.tax', 'customerInfo'])->findOrFail($saleId);
        
        // Group barangKeluar items by tax_id
        $barangKeluarGrouped = collect();
        
        foreach ($offlineSale->items as $item) {
            if ($item->barangKeluar->count() > 0) {
                foreach ($item->barangKeluar as $barangKeluar) {
                    if ($barangKeluar->financeOffline) {
                        continue; // Skip items that already have an invoice
                    }
                    
                    $taxId = $barangKeluar->warehouseStock && $barangKeluar->warehouseStock->tax_id ? 
                        $barangKeluar->warehouseStock->tax_id : 4; // Default to Non-PKP if not found
                    
                    if (!$barangKeluarGrouped->has($taxId)) {
                        $barangKeluarGrouped[$taxId] = collect();
                    }
                    
                    $barangKeluarGrouped[$taxId]->push($barangKeluar);
                }
            }
        }
        
        if ($barangKeluarGrouped->isEmpty()) {
            return redirect()->route('finance.offline.index')
                ->with('error', 'Tidak ada barang yang belum memiliki invoice.');
        }
        
        DB::beginTransaction();
        
        try {
            $invoiceMessages = [];
            
            // Process each tax group separately
            foreach ($barangKeluarGrouped as $taxId => $barangKeluarItems) {
                // Get customer info for tax purposes
                $customer = $offlineSale->customerInfo;
                
                // Generate invoice number from Surat Jalan number (menyesuaikan dengan SJ)
                $invoiceNumber = FinanceOffline::generateInvoiceNumberFromSuratJalan(
                    $offlineSale->surat_jalan_number, 
                    $taxId
                );
                
                // Calculate nominal from offline_sales total_amount
                // Get all unique offline sales from this invoice's barang_keluar items
                $offlineSales = collect();
                foreach ($barangKeluarItems as $bk) {
                    if ($bk->offlineSaleItem && $bk->offlineSaleItem->offlineSale) {
                        $offlineSales->push($bk->offlineSaleItem->offlineSale);
                    }
                }
                $offlineSales = $offlineSales->unique('id');
                
                // Calculate nominal from unique offline_sale items (not from barang_keluar items)
                // This ensures each sale item is only counted once, even if split into multiple barang_keluar
                $nominal = 0;
                $processedSaleItemIds = [];
                
                foreach ($barangKeluarItems as $bk) {
                    if ($bk->offlineSaleItem) {
                        $saleItemId = $bk->offlineSaleItem->id;
                        
                        // Only count each sale item once
                        if (!in_array($saleItemId, $processedSaleItemIds)) {
                            $subtotal = $bk->offlineSaleItem->subtotal ?? 0;
                            $nominal += $subtotal;
                            $processedSaleItemIds[] = $saleItemId;
                        }
                    }
                }
                
                // Format nominal (this is DPP, not grand total)
                $nominal = \App\Helpers\NumberFormatter::formatForDatabase($nominal);
                
                // Set main_category_id from offlineSale or session
                $mainCategoryId = $offlineSale->main_category_id ?? session('main_category_id', null);
                
                // Determine invoice status based on offline sale status
                $invoiceStatus = 'unpaid';
                $tanggalBayar = null;
                if ($offlineSale->status === 'paid') {
                    $invoiceStatus = 'paid';
                    $tanggalBayar = $offlineSale->sale_date; // Set payment date to sale date
                }
                
                // Create a single finance offline record for this tax group
                $financeOffline = new FinanceOffline([
                    'invoice_number' => $invoiceNumber,
                    'nominal' => $nominal,
                    'tanggal_invoice' => $offlineSale->sale_date, // Use sale_date instead of current date
                    'status' => $invoiceStatus,
                    'tanggal_bayar' => $tanggalBayar,
                    'main_category_id' => $mainCategoryId
                ]);
                
                $financeOffline->save();
                
                // If the offline sale is marked as paid, create a payment record
                if ($offlineSale->status === 'paid') {
                    // Use payment_date if available, otherwise use sale_date
                    $paymentDate = $offlineSale->payment_date ?? $offlineSale->sale_date;
                    $paymentMethod = $offlineSale->payment_method ?? 'cash';
                    
                    // Calculate total payment amount including PPN for PKP items
                    $totalPaymentAmount = $nominal; // Start with DPP
                    if ($taxId == 3) { // PKP items
                        $dpp11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($nominal);
                        $ppn = \App\Helpers\NumberFormatter::calculatePPN($dpp11_12);
                        $totalPaymentAmount = \App\Helpers\NumberFormatter::calculateGrandTotal($nominal, $ppn);
                    }
                    
                    $payment = new \App\Models\InvoicePayment([
                        'finance_offline_id' => $financeOffline->id,
                        'amount' => $totalPaymentAmount, // Full amount including PPN for PKP items
                        'payment_date' => $paymentDate,
                        'payment_method' => $paymentMethod,
                        'notes' => 'Pembayaran lunas saat penjualan offline',
                    ]);
                    $payment->save();
                }
                
                // Associate all barangKeluar items with this invoice
                foreach ($barangKeluarItems as $barangKeluar) {
                    $barangKeluar->finance_offline_id = $financeOffline->id;
                    $barangKeluar->save();
                }
                
                $invoiceMessages[] = "Invoice {$invoiceNumber} berhasil dibuat untuk " . 
                    ($taxId == 3 ? "barang PKP" : "barang Non-PKP");
            }
            
            // Check if this offline sale has completed returns and handle finance accordingly
            if (!$barangKeluarGrouped->isEmpty() && $offlineSale) {
                $completedReturs = ReturOfflineSale::where('offline_sale_id', $offlineSale->id)
                    ->where('status', 'selesai')
                    ->with('details.offlineSaleItem')
                    ->get();
                
                if ($completedReturs->isNotEmpty()) {
                    Log::info("Found {$completedReturs->count()} completed return(s) for sale {$offlineSale->id}, applying finance adjustments");
                    
                    $financeService = new ReturFinanceService();
                    foreach ($completedReturs as $retur) {
                        $financeService->handleOfflineReturFinance($retur);
                        Log::info("Finance adjustment applied for retur {$retur->kode_retur} after invoice generation");
                    }
                    
                    $invoiceMessages[] = "Penyesuaian retur diterapkan untuk " . $completedReturs->count() . " retur.";
                }
            }
            
            DB::commit();
            
            return redirect()->route('finance.offline.index')
                ->with('success', implode('<br>', $invoiceMessages));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('finance.offline.index')
                ->with('error', 'Gagal membuat invoice: ' . $e->getMessage());
        }
    }
    
    /**
     * Print the invoice.
     *
     * @param  int  $id The invoice ID
     * @return \Illuminate\Http\Response
     */
    public function printInvoice($id)
    {
        // Use ID instead of invoice_number to avoid conflicts when multiple invoices have the same number
        $invoice = FinanceOffline::where('id', $id)
            ->with([
                'barangKeluarItems', 
                'barangKeluarItems.warehouseStock',
                'barangKeluarItems.warehouseStock.product',
                'barangKeluarItems.warehouseStock.tax',
                'barangKeluarItems.offlineSaleItem', 
                'barangKeluarItems.offlineSaleItem.offlineSale',
                'barangKeluarItems.offlineSaleItem.offlineSale.customerInfo'
            ])
            ->firstOrFail();
        
        $user = auth()->user();
        
        // Check if the user can print this invoice
        if (!$invoice->canBePrinted($user)) {
            if ($invoice->print_count > 0 && !$invoice->reprint_requested) {
                // Request reprint if not already requested
                $invoice->requestReprint();
                return redirect()->route('finance.offline.invoices')
                    ->with('error', 'Anda telah mencapai batas cetak. Permohonan cetak ulang telah dikirim ke Super Admin untuk persetujuan.');
            } elseif ($invoice->reprint_requested && !$invoice->reprint_approved) {
                return redirect()->route('finance.offline.invoices')
                    ->with('error', 'Permohonan cetak ulang sedang menunggu persetujuan dari Super Admin.');
            }
            
            return redirect()->route('finance.offline.invoices')
                ->with('error', 'Anda tidak memiliki izin untuk mencetak invoice ini.');
        }
        
        // Track this print - This counts as a print before even viewing the invoice
        $invoice->trackPrint();
        
        return view('finance.offline.print_invoice', compact('invoice', 'user'));
    }

    /**
     * Print the invoice after return (new format showing items after return deduction).
     *
     * @param  string  $invoiceNumber
     * @return \Illuminate\Http\Response
     */
    public function printInvoiceAfterReturn($invoiceNumber)
    {
        $invoice = FinanceOffline::where('invoice_number', $invoiceNumber)
            ->with([
                'barangKeluarItems', 
                'barangKeluarItems.warehouseStock',
                'barangKeluarItems.warehouseStock.product',
                'barangKeluarItems.warehouseStock.tax',
                'barangKeluarItems.offlineSaleItem', 
                'barangKeluarItems.offlineSaleItem.offlineSale',
                'barangKeluarItems.offlineSaleItem.offlineSale.customerInfo'
            ])
            ->firstOrFail();
        
        $user = auth()->user();
        
        return view('finance.offline.print_invoice_after_return', compact('invoice', 'user'));
    }

    /**
     * Print the return invoice (showing only returned items).
     *
     * @param  string  $invoiceNumber
     * @return \Illuminate\Http\Response
     */
    public function printReturnInvoice($invoiceNumber)
    {
        $invoice = FinanceOffline::where('invoice_number', $invoiceNumber)
            ->with([
                'barangKeluarItems', 
                'barangKeluarItems.warehouseStock',
                'barangKeluarItems.warehouseStock.product',
                'barangKeluarItems.warehouseStock.tax',
                'barangKeluarItems.offlineSaleItem', 
                'barangKeluarItems.offlineSaleItem.offlineSale',
                'barangKeluarItems.offlineSaleItem.offlineSale.customerInfo'
            ])
            ->firstOrFail();
        
        $user = auth()->user();
        
        return view('finance.offline.print_return_invoice', compact('invoice', 'user'));
    }

    /**
     * Approve a reprint request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approveReprint($id)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        if (!$user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'Hanya Super Admin yang dapat menyetujui permintaan cetak ulang.');
        }
        
        try {
            $invoice = FinanceOffline::findOrFail($id);
            
            if (!$invoice->reprint_requested) {
                return redirect()->route('finance.offline.invoices')
                    ->with('error', 'Tidak ada permintaan cetak ulang untuk invoice ini.');
            }
            
            if ($invoice->approveReprint($user)) {
                return redirect()->route('finance.offline.invoices')
                    ->with('success', 'Permintaan cetak ulang untuk invoice ' . $invoice->invoice_number . ' telah disetujui.');
            } else {
                return redirect()->route('finance.offline.invoices')
                    ->with('error', 'Gagal menyetujui permintaan cetak ulang, silakan coba lagi.');
            }
        } catch (\Exception $e) {
            Log::error('Error approving reprint: ' . $e->getMessage());
            
            return redirect()->route('finance.offline.invoices')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate nominal for all invoices or a specific invoice
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recalculateNominal(Request $request)
    {
        try {
            if ($request->has('invoice_id')) {
                // Recalculate specific invoice
                $invoice = FinanceOffline::findOrFail($request->invoice_id);
                $oldNominal = $invoice->nominal;
                $invoice->updateNominal();
                $newNominal = $invoice->nominal;
                
                return redirect()->route('finance.offline.invoices')
                    ->with('success', "Nominal invoice {$invoice->invoice_number} telah diperbarui dari " . number_format($oldNominal, 2) . " menjadi " . number_format($newNominal, 2));
            } else {
                // Recalculate all invoices
                $invoices = FinanceOffline::with('barangKeluarItems.offlineSaleItem.product')->get();
                $updatedCount = 0;
                $totalDifference = 0;
                
                foreach ($invoices as $invoice) {
                    $oldNominal = $invoice->nominal;
                    $newNominal = $invoice->recalculateNominal();
                    
                    if (abs($oldNominal - $newNominal) > 0.01) {
                        $invoice->nominal = $newNominal;
                        $invoice->save();
                        $updatedCount++;
                        $totalDifference += abs($oldNominal - $newNominal);
                    }
                }
                
                return redirect()->route('finance.offline.invoices')
                    ->with('success', "{$updatedCount} invoice telah diperbarui. Total selisih: " . number_format($totalDifference, 2));
            }
        } catch (\Exception $e) {
            return redirect()->route('finance.offline.invoices')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
