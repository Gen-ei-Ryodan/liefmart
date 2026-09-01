<?php

namespace App\Http\Controllers;

use App\Traits\QueueExport;
use App\Models\OfflineSale;
use App\Models\OfflineSaleItem;
use App\Models\Product;
use App\Models\ReturOfflineSale;
use App\Models\ReturOfflineSaleDetail;
use App\Models\WarehouseStock;
use App\Models\Lokasi;
use App\Models\PenerimaanDetail;
use App\Services\ReturFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReturOfflineSaleController extends Controller
{
    use QueueExport;

    /**
     * Display a listing of the retur offline sales.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ReturOfflineSale::with(['offlineSale', 'user']);

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('kode_retur', 'like', '%' . $search . '%')
                  ->orWhereHas('offlineSale', function($saleQuery) use ($search) {
                      $saleQuery->where('surat_jalan_number', 'like', '%' . $search . '%')
                               ->orWhere('customer_name', 'like', '%' . $search . '%')
                               ->orWhere('No_PO', 'like', '%' . $search . '%')
                               ->orWhereHas('customerInfo', function($customerQuery) use ($search) {
                                   $customerQuery->where('name', 'like', '%' . $search . '%');
                               });
                  })
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_retur', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_retur', '<=', $request->date_to);
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $returOfflineSales = $query->orderBy('created_at', 'desc')->paginate(10);

        // Get filter options
        $users = \App\Models\User::orderBy('name')->get();

        return view('retur.offline.index', compact('returOfflineSales', 'users'));
    }

    /**
     * Show the form for creating a new retur offline sale.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Display a list of offline sales to choose from with eager loaded relations
        $offlineSaleList = OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['customerInfo'])
            ->whereHas('items')
            ->orderBy('sale_date', 'desc')
            ->limit(1000)
            ->get();

        return view('retur.offline.create', compact('offlineSaleList'));
    }

    /**
     * Get a specific offline sale with its details for the retur form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOfflineSale($id)
    {
        $offlineSale = OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['items.product', 'customerInfo'])
            ->findOrFail($id);

        return response()->json($offlineSale);
    }

    /**
     * Store a newly created retur offline sale in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \Log::info('ReturOfflineSale store method called with data: ' . json_encode($request->all()));
        
        try {
            \Log::info('Starting validation');
            $validated = $request->validate([
                'offline_sale_id' => 'required|exists:offline_sales,id',
                'tanggal_retur' => 'required|date',
                'catatan' => 'nullable|string',
                'details' => 'required|array',
                'details.*.offline_sale_item_id' => 'required|exists:offline_sale_items,id',
                'details.*.product_id' => 'required|exists:products,id',
                'details.*.qty' => 'required|numeric|min:0',
                'details.*.kondisi' => 'required|string|in:BAGUS,RUSAK,HILANG',
                'details.*.alasan' => 'nullable|string',
            ]);
            \Log::info('Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed: ' . json_encode($e->errors()));
            throw $e;
        }

        try {
            DB::beginTransaction();
            \Log::info('Starting transaction for retur offline sale');

            // Create the retur offline sale header
            $returOfflineSale = ReturOfflineSale::create([
                'kode_retur' => ReturOfflineSale::generateKodeRetur(),
                'offline_sale_id' => $request->offline_sale_id,
                'user_id' => Auth::id(),
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'status' => 'draft',
            ]);
            
            \Log::info('Created retur offline sale header with ID: ' . $returOfflineSale->id);

            // Process each detail item
            foreach ($request->details as $index => $detail) {
                \Log::info('Processing detail item with index: ' . $index . ': ' . json_encode($detail));
                
                // Skip if qty is zero or null
                if (empty($detail['qty']) || floatval($detail['qty']) <= 0) {
                    \Log::info('Skipping item with zero or negative qty');
                    continue;
                }

                // Get the offline sale item
                $offlineSaleItem = OfflineSaleItem::findOrFail($detail['offline_sale_item_id']);
                \Log::info('Found offline sale item: ' . $offlineSaleItem->id . ' with quantity: ' . $offlineSaleItem->quantity);
                
                // Validate return quantity doesn't exceed original quantity
                if (floatval($detail['qty']) > $offlineSaleItem->quantity) {
                    \Log::warning('Return quantity exceeds original quantity for item ID ' . $offlineSaleItem->id);
                    throw new \Exception("Jumlah retur untuk item ID {$offlineSaleItem->id} melebihi jumlah asli");
                }

                // Create detail record
                ReturOfflineSaleDetail::create([
                    'retur_offline_sale_id' => $returOfflineSale->id,
                    'offline_sale_item_id' => $detail['offline_sale_item_id'],
                    'product_id' => $detail['product_id'],
                    'qty' => floatval($detail['qty']),
                    'kondisi' => $detail['kondisi'],
                    'alasan' => $detail['alasan'] ?? null,
                ]);
                
                \Log::info('Created detail record for product ID: ' . $detail['product_id']);
            }

            DB::commit();
            \Log::info('Transaction committed successfully');

            return redirect()->route('retur-offline.show', $returOfflineSale->id)
                ->with('success', 'Retur penjualan offline berhasil dibuat dan menunggu proses.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in ReturOfflineSale store: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified retur offline sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $returOfflineSale = ReturOfflineSale::with([
            'offlineSale', 
            'user', 
            'details.product', 
            'details.offlineSaleItem.barangKeluar.warehouseStock'
        ])->findOrFail($id);

        return view('retur.offline.show', compact('returOfflineSale'));
    }

    /**
     * Show the form for editing the specified retur offline sale.
     * Only allowed if status is 'draft'
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $returOfflineSale = ReturOfflineSale::with(['offlineSale', 'details.product'])
            ->findOrFail($id);

        // Only draft status can be edited
        if ($returOfflineSale->status !== 'draft') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diedit.');
        }

        $offlineSaleList = OfflineSale::withoutGlobalScope('mainCategory')
            ->with(['customerInfo'])
            ->whereHas('items')
            ->get();

        return view('retur.offline.edit', compact('returOfflineSale', 'offlineSaleList'));
    }

    /**
     * Update the specified retur offline sale in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $returOfflineSale = ReturOfflineSale::findOrFail($id);

        // Only draft status can be updated
        if ($returOfflineSale->status !== 'draft') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diupdate.');
        }

        $request->validate([
            'offline_sale_id' => 'required|exists:offline_sales,id',
            'tanggal_retur' => 'required|date',
            'catatan' => 'nullable|string',
            'details' => 'required|array',
            'details.*.offline_sale_item_id' => 'required|exists:offline_sale_items,id',
            'details.*.product_id' => 'required|exists:products,id',
            'details.*.qty' => 'required|numeric|min:0',
            'details.*.kondisi' => 'required|string|in:BAGUS,RUSAK,HILANG',
            'details.*.alasan' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Update the retur offline sale
            $returOfflineSale->update([
                'offline_sale_id' => $request->offline_sale_id,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
            ]);

            // Delete existing details
            $returOfflineSale->details()->delete();

            // Create new details
            foreach ($request->details as $detail) {
                // Skip if qty is zero or null
                if (empty($detail['qty']) || floatval($detail['qty']) <= 0) {
                    continue;
                }

                ReturOfflineSaleDetail::create([
                    'retur_offline_sale_id' => $returOfflineSale->id,
                    'offline_sale_item_id' => $detail['offline_sale_item_id'],
                    'product_id' => $detail['product_id'],
                    'qty' => floatval($detail['qty']),
                    'kondisi' => $detail['kondisi'],
                    'alasan' => $detail['alasan'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('retur-offline.show', $returOfflineSale->id)
                ->with('success', 'Retur penjualan offline berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Process a draft retur offline sale, apply stock changes.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function process($id)
    {
        $returOfflineSale = ReturOfflineSale::with('details')->findOrFail($id);

        // Only draft status can be processed
        if ($returOfflineSale->status !== 'draft') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat diproses.');
        }

        try {
            DB::beginTransaction();
            \Log::info('Processing retur offline sale ID: ' . $id);

            // Process each detail item
            foreach ($returOfflineSale->details as $detail) {
                // Get the offline sale item
                $offlineSaleItem = OfflineSaleItem::findOrFail($detail->offline_sale_item_id);
                \Log::info('Processing offline sale item ID: ' . $offlineSaleItem->id);
                
                // Validate return quantity doesn't exceed original quantity
                if (floatval($detail->qty) > $offlineSaleItem->quantity) {
                    throw new \Exception("Jumlah retur untuk item ID {$offlineSaleItem->id} melebihi jumlah asli");
                }
                
                // Calculate new quantity
                $newQuantity = $offlineSaleItem->quantity - floatval($detail->qty);
                
                // Recalculate subtotal based on new quantity
                $newSubtotal = $this->recalculateSubtotal($offlineSaleItem, $newQuantity);
                
                // Update the offline sale item quantity and subtotal
                $offlineSaleItem->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $newSubtotal
                ]);
                
                // Refresh to ensure update is saved
                $offlineSaleItem->refresh();
                
                \Log::info('Updated offline sale item', [
                    'offline_sale_item_id' => $offlineSaleItem->id,
                    'old_quantity' => $offlineSaleItem->quantity + floatval($detail->qty),
                    'new_quantity' => $newQuantity,
                    'old_subtotal' => $offlineSaleItem->subtotal + ($offlineSaleItem->unit_price * floatval($detail->qty)),
                    'new_subtotal' => $newSubtotal,
                    'updated_subtotal' => $offlineSaleItem->subtotal
                ]);
                
                // Handle stock based on condition
                // PERBAIKAN: Untuk process(), kita perlu mendapatkan tax_id dari barang keluar asli
                $offlineSaleItem = OfflineSaleItem::with(['barangKeluar.warehouseStock'])->findOrFail($detail->offline_sale_item_id);
                $barangKeluar = $offlineSaleItem->barangKeluar()->with('warehouseStock')->first();
                $originalTaxId = $barangKeluar && $barangKeluar->warehouseStock ? $barangKeluar->warehouseStock->tax_id : null;
                \Log::info('Process retur offline - Original tax_id from barang keluar: ' . ($originalTaxId ?? 'null') . ' for product ID: ' . $detail->product_id);
                
                if ($detail->kondisi === 'BAGUS') {
                    $this->addBackToStock($detail->product_id, floatval($detail->qty), false, $returOfflineSale->tanggal_retur->format('Y-m-d'), $returOfflineSale->id, $originalTaxId);
                } else if ($detail->kondisi === 'RUSAK') {
                    $this->addBackToStock($detail->product_id, floatval($detail->qty), true, $returOfflineSale->tanggal_retur->format('Y-m-d'), $returOfflineSale->id, $originalTaxId);
                } else {
                    \Log::info('Product marked as HILANG, not adding to stock: product ID ' . $detail->product_id);
                }
            }

            // Update status to 'selesai'
            $returOfflineSale->update([
                'status' => 'selesai',
                'user_id' => Auth::id(),
            ]);
            
            // Refresh offline sale to get latest data
            $returOfflineSale->refresh();
            $offlineSale = $returOfflineSale->offlineSale;
            
            \Log::info('Before calling finance service', [
                'retur_offline_sale_id' => $returOfflineSale->id,
                'offline_sale_id' => $offlineSale->id,
                'invoices_count' => $offlineSale->getInvoices()->count()
            ]);

            // Handle finance logic for offline return
            $financeService = new ReturFinanceService();
            $financeService->handleOfflineReturFinance($returOfflineSale);

            DB::commit();

            return redirect()->route('retur-offline.show', $returOfflineSale->id)
                ->with('success', 'Retur penjualan offline berhasil diproses.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a draft retur offline sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $returOfflineSale = ReturOfflineSale::findOrFail($id);

        // Only draft status can be cancelled
        if ($returOfflineSale->status !== 'draft') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status draft yang dapat dibatalkan.');
        }

        try {
            // Update status to 'dibatalkan'
            $returOfflineSale->update([
                'status' => 'dibatalkan',
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('retur-offline.show', $returOfflineSale->id)
                ->with('success', 'Retur penjualan offline berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Reverse/Cancel a completed retur offline sale (batal retur)
     * This will fully restore the state as if the return never happened
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reverseReturn($id)
    {
        $returOfflineSale = ReturOfflineSale::with('details')->findOrFail($id);

        // Only completed returns can be reversed
        if ($returOfflineSale->status !== 'selesai') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status selesai yang dapat dibatalkan.');
        }

        try {
            DB::beginTransaction();
            \Log::info('Reversing completed retur offline sale ID: ' . $id);

            $offlineSale = $returOfflineSale->offlineSale;

            // Process each detail item to reverse changes
            foreach ($returOfflineSale->details as $detail) {
                // Get the offline sale item and restore its quantity
                $offlineSaleItem = OfflineSaleItem::findOrFail($detail->offline_sale_item_id);
                $oldQuantity = $offlineSaleItem->quantity;
                $newQuantity = $offlineSaleItem->quantity + floatval($detail->qty);
                
                \Log::info('Restoring offline sale item ID: ' . $offlineSaleItem->id . ' qty from ' . $oldQuantity . ' to ' . $newQuantity);
                
                // Recalculate subtotal with restored quantity
                $newSubtotal = $this->recalculateSubtotal($offlineSaleItem, $newQuantity);
                
                // Restore the offline sale item quantity and subtotal
                $offlineSaleItem->update([
                    'quantity' => $newQuantity,
                    'subtotal' => $newSubtotal
                ]);
                
                \Log::info('Updated offline sale item', [
                    'offline_sale_item_id' => $offlineSaleItem->id,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'old_subtotal' => $offlineSaleItem->subtotal,
                    'new_subtotal' => $newSubtotal
                ]);
                
                // Remove stock that was added during return (only for BAGUS and RUSAK conditions)
                if ($detail->kondisi === 'BAGUS') {
                    $this->removeReturnedStock($detail->product_id, floatval($detail->qty), false, $returOfflineSale->id);
                } else if ($detail->kondisi === 'RUSAK') {
                    $this->removeReturnedStock($detail->product_id, floatval($detail->qty), true, $returOfflineSale->id);
                }
                // For HILANG condition, no stock was added, so nothing to remove
            }

            // Recalculate and restore invoice nominal
            if ($offlineSale) {
                $invoices = $offlineSale->getInvoices();
                
                foreach ($invoices as $invoice) {
                    \Log::info('Restoring invoice for reversed retur', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'old_nominal' => $invoice->nominal,
                        'old_status' => $invoice->status
                    ]);
                    
                    // Recalculate nominal based on restored subtotals (DPP)
                    $newNominalDPP = 0;
                    foreach ($invoice->barangKeluarItems as $bk) {
                        if ($bk->offlineSaleItem) {
                            $bk->offlineSaleItem->refresh();
                            $newNominalDPP += $bk->offlineSaleItem->subtotal ?? 0;
                        }
                    }
                    
                    // Get tax_id from first item
                    $firstItem = $invoice->barangKeluarItems()->with('warehouseStock')->first();
                    $taxId = $firstItem && $firstItem->warehouseStock && $firstItem->warehouseStock->tax_id 
                        ? $firstItem->warehouseStock->tax_id 
                        : null;
                    
                    // Calculate grand total (nominal + PPN)
                    $dpp = \App\Helpers\NumberFormatter::calculateDPP($newNominalDPP);
                    $grandTotal = $dpp;
                    
                    if ($taxId == 3) {
                        // PKP: Calculate PPN
                        $dpp11_12 = \App\Helpers\NumberFormatter::calculateDPP1112($dpp);
                        $ppn = \App\Helpers\NumberFormatter::calculatePPN($dpp11_12);
                        $grandTotal = \App\Helpers\NumberFormatter::calculateGrandTotal($dpp, $ppn);
                    } else {
                        // Non-PKP: Just round DPP
                        $grandTotal = \App\Helpers\NumberFormatter::roundToWholeNumber($dpp);
                    }
                    
                    // Update invoice with recalculated nominal
                    // Remove partial_refund status and restore to normal
                    // Check if invoice has payments to determine status
                    $totalPaid = $invoice->payments->sum('amount');
                    $newStatus = null;
                    
                    if ($totalPaid >= $grandTotal) {
                        $newStatus = 'paid';
                    } elseif ($totalPaid > 0) {
                        $newStatus = 'partial';
                    } else {
                        $newStatus = null; // unpaid/default
                    }
                    
                    $updateData = [
                        'nominal' => $grandTotal,
                        'status' => $newStatus, // Restore to appropriate status
                        'notes' => null, // Clear notes
                        'outstanding' => 0 // Reset outstanding
                    ];
                    
                    $invoice->update($updateData);
                    $invoice->refresh();
                    
                    \Log::info('Invoice restored for reversed retur', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'new_nominal_dpp' => $newNominalDPP,
                        'new_nominal_grand_total' => $grandTotal,
                        'tax_id' => $taxId,
                        'new_status' => $invoice->status
                    ]);
                }
            }

            // Update status to 'dibatalkan'
            $returOfflineSale->update([
                'status' => 'dibatalkan',
                'user_id' => Auth::id(),
            ]);

            DB::commit();
            \Log::info('Successfully reversed retur offline sale ID: ' . $id);

            return redirect()->route('retur-offline.show', $id)
                ->with('success', 'Retur penjualan offline berhasil dibatalkan. Semua perubahan telah dikembalikan ke kondisi semula (sales, invoice, dan warehouse stock).');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error reversing retur offline sale: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Helper function to add items back to stock
     * 
     * @param int $productId
     * @param float $quantity
     * @param bool $isDamaged
     * @param string|null $returDate
     * @param int|null $returOfflineSaleId
     */
    private function addBackToStock($productId, $quantity, $isDamaged, $returDate = null, $returOfflineSaleId = null, $originalTaxId = null)
    {
        $product = Product::findOrFail($productId);
        \Log::info("Adding back to stock: product ID {$productId}, qty {$quantity}, damaged: " . ($isDamaged ? 'Yes' : 'No'));
        
        // Get the retur warehouse location
        $returLocation = Lokasi::where('kode', 'GUDANG_RETUR')->first();
        if (!$returLocation) {
            $returLocation = Lokasi::create([
                'kode' => 'GUDANG_RETUR',
                'nama' => 'Gudang Retur',
                'deskripsi' => 'Tempat penyimpanan barang hasil retur'
            ]);
            \Log::info("Created new retur location with ID: {$returLocation->id}");
        }
        
        // PERBAIKAN: Prioritaskan menggunakan tax_id dari barang keluar asli
        // Jika originalTaxId tersedia, gunakan itu untuk mempertahankan tax_id yang benar
        $taxId = $originalTaxId;
        
        // Find a suitable warehouse stock to reference for ED dan atribut lainnya
        // Prioritas: cari stock dengan tax_id yang sama dengan originalTaxId (jika ada)
        $referenceStock = null;
        
        if ($originalTaxId) {
            // Cari stock dengan tax_id yang sama dengan originalTaxId
            $referenceStock = WarehouseStock::where('product_id', $productId)
                ->where('is_damaged', $isDamaged)
                ->where('tax_id', $originalTaxId)
                ->where('qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->first();
        }
        
        // Jika tidak ditemukan dengan tax_id yang sama, cari stock apapun dengan product dan damage status yang sama
        if (!$referenceStock) {
            $referenceStock = WarehouseStock::where('product_id', $productId)
                ->where('is_damaged', $isDamaged)
                ->where('qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->orderBy('tax_id', 'asc')
                ->first();
        }
        
        // Jika masih tidak ditemukan, cari stock apapun untuk product ini
        if (!$referenceStock) {
            $referenceStock = WarehouseStock::where('product_id', $productId)
                ->where('qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->orderBy('tax_id', 'asc')
                ->first();
        }
        
        // Set default values
        $penerimaanDetailId = null;
        $expiredDate = null;
        $statusEd = 'aman';
        
        if ($referenceStock) {
            // Use reference stock's attributes untuk ED dan penerimaan_detail_id
            // TAPI tetap gunakan originalTaxId jika tersedia
            $penerimaanDetailId = $referenceStock->penerimaan_detail_id;
            if (!$taxId) {
                $taxId = $referenceStock->tax_id; // Fallback jika originalTaxId tidak tersedia
            }
            $expiredDate = $referenceStock->expired_date;
            $statusEd = $referenceStock->status_ed;
            
            \Log::info("Using reference stock ID: {$referenceStock->id} for ED and other attributes", [
                'penerimaan_detail_id' => $penerimaanDetailId,
                'tax_id' => $taxId,
                'original_tax_id' => $originalTaxId,
                'expired_date' => $expiredDate,
                'status_ed' => $statusEd
            ]);
        } else {
            // Fallback: find any penerimaan_detail for this product
            $penerimaanDetail = PenerimaanDetail::where('penerimaan_detail.product_id', $productId)
                ->with('penerimaan')
                ->orderBy('penerimaan_detail.id', 'desc')
                ->first();
                
            if ($penerimaanDetail) {
                $penerimaanDetailId = $penerimaanDetail->id;
                \Log::info("Found penerimaan_detail_id: {$penerimaanDetailId} for product");
            } else {
                \Log::warning("No penerimaan_detail found for product ID: {$productId}");
            }
        }
        
        // PERBAIKAN: Jika tax_id masih null, ambil dari penerimaan_detail->penerimaan->tax_category_id
        // Ini memastikan warehouse stock selalu punya tax_id karena selalu ada penerimaan_detail_id
        if (!$taxId && $penerimaanDetailId) {
            $penerimaanDetailForTax = PenerimaanDetail::with('penerimaan')->find($penerimaanDetailId);
            if ($penerimaanDetailForTax && $penerimaanDetailForTax->penerimaan) {
                $taxId = $penerimaanDetailForTax->penerimaan->tax_category_id;
                \Log::info("Using tax_id from penerimaan: {$taxId} for penerimaan_detail_id: {$penerimaanDetailId}");
            }
        }
        
        // Get existing warehouse stock or create new one
        $warehouseStock = WarehouseStock::where('product_id', $productId)
            ->where('is_damaged', $isDamaged)
            ->where('lokasi_id', $returLocation->id)
            ->first();
        
        if (!$warehouseStock) {
            // Create a new warehouse stock record for the condition (damaged/good)
            $warehouseStock = WarehouseStock::create([
                'product_id' => $productId,
                'lokasi_id' => $returLocation->id, // Use retur location
                'penerimaan_detail_id' => $penerimaanDetailId,
                'tax_id' => $taxId, // Menggunakan originalTaxId jika tersedia, atau dari reference stock, atau dari penerimaan
                'qty' => $isDamaged ? 0 : $quantity,
                'qty_damaged' => $isDamaged ? $quantity : 0,
                'expired_date' => $expiredDate,
                'status_ed' => $statusEd,
                'is_damaged' => $isDamaged,
                'source_type' => 'retur_offline',
                'source_id' => $returOfflineSaleId,
                'source_date' => $returDate ? \Carbon\Carbon::parse($returDate) : now(),
                'catatan' => 'Retur penjualan offline pada ' . ($returDate ?? now()->format('Y-m-d')),
            ]);
            
            \Log::info("Created new warehouse stock record ID: {$warehouseStock->id}", [
                'penerimaan_detail_id' => $penerimaanDetailId,
                'tax_id' => $taxId,
                'original_tax_id_provided' => $originalTaxId !== null,
                'expired_date' => $expiredDate,
                'status_ed' => $statusEd,
                'is_damaged' => $isDamaged
            ]);
        } else {
            // Update existing stock based on condition
            // Use DB::table() to avoid global scope JOIN conflicts during update
            if ($isDamaged) {
                // For damaged items, update qty_damaged
                DB::table('warehouse_stock')
                    ->where('id', $warehouseStock->id)
                    ->increment('qty_damaged', $quantity);
                $warehouseStock->refresh();
                \Log::info("Updated damaged stock, new qty_damaged: {$warehouseStock->qty_damaged}");
            } else {
                // For good items, update regular qty
                DB::table('warehouse_stock')
                    ->where('id', $warehouseStock->id)
                    ->increment('qty', $quantity);
                $warehouseStock->refresh();
                \Log::info("Updated good stock, new qty: {$warehouseStock->qty}");
            }
        }
    }
    
    /**
     * Print the retur offline sale document.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $returOfflineSale = ReturOfflineSale::with([
            'offlineSale',
            'offlineSale.customerInfo',
            'user',
            'details.product',
            'details.offlineSaleItem.barangKeluar.warehouseStock'
        ])->findOrFail($id);

        // Only allow printing of completed returns
        if ($returOfflineSale->status !== 'selesai') {
            return redirect()->route('retur-offline.show', $id)
                ->with('error', 'Hanya retur dengan status selesai yang dapat dicetak.');
        }

        return view('retur.offline.print', compact('returOfflineSale'));
    }

    /**
     * Export retur offline sales to Excel
     */
    public function export()
    {
        $filename = 'retur_offline_detail_' . date('Y-m-d') . '.xlsx';
        
        return $this->queueExcelExport(new \App\Exports\ReturOfflineDetailExport(), $filename, 'Export Retur Offline Detail');
    }

    /**
     * Remove stock that was added during return processing
     * PERBAIKAN: Hapus warehouse stock entries yang dibuat saat retur (berdasarkan source_type dan source_id)
     * 
     * @param  int  $productId
     * @param  float  $qty
     * @param  bool  $isDamaged
     * @param  int  $returOfflineSaleId
     * @return void
     */
    private function removeReturnedStock($productId, $qty, $isDamaged, $returOfflineSaleId)
    {
        \Log::info("removeReturnedStock called for product ID: {$productId}, qty: {$qty}, isDamaged: " . ($isDamaged ? 'yes' : 'no') . ", returOfflineSaleId: {$returOfflineSaleId}");
        
        try {
            // Find warehouse stock entries that were created by this return
            // PERBAIKAN: Hapus semua warehouse stock yang dibuat dari retur ini
            $returnedStocks = WarehouseStock::where('product_id', $productId)
                ->where('is_damaged', $isDamaged)
                ->where('source_type', 'retur_offline')
                ->where('source_id', $returOfflineSaleId)
                ->orderBy('created_at', 'desc') // Most recent first
                ->get();

            if ($returnedStocks->isEmpty()) {
                \Log::warning("No specific return stock found for product ID: {$productId}, returOfflineSaleId: {$returOfflineSaleId}");
                // Don't throw error, just log warning - mungkin stock sudah dihapus atau tidak ada
                return;
            }

            $remainingQty = floatval($qty);
            $deletedStockIds = [];
            
            foreach ($returnedStocks as $stock) {
                if ($remainingQty <= 0) break;

                // For offline returns, check if we're dealing with damaged/good stock columns
                $stockQty = 0;
                if ($isDamaged && isset($stock->qty_damaged)) {
                    $stockQty = floatval($stock->qty_damaged);
                } else {
                    $stockQty = floatval($stock->qty);
                }
                
                \Log::info("Checking stock ID: {$stock->id}, qty: {$stockQty}, remaining to delete: {$remainingQty}");
                
                if ($stockQty <= $remainingQty) {
                    // Hapus seluruh stock entry jika qty-nya <= remaining qty
                    \Log::info("Deleting entire stock ID: {$stock->id} with qty: {$stockQty}");
                    $deletedStockIds[] = $stock->id;
                    $stock->delete();
                    $remainingQty -= $stockQty;
                } else {
                    // Jika stock qty > remaining qty, kurangi qty-nya saja
                    \Log::info("Reducing stock ID: {$stock->id} from {$stockQty} to " . ($stockQty - $remainingQty));
                    if ($isDamaged && isset($stock->qty_damaged)) {
                        // Use DB::table() to avoid global scope JOIN conflicts during update
                        DB::table('warehouse_stock')
                            ->where('id', $stock->id)
                            ->decrement('qty_damaged', $remainingQty);
                    } else {
                        DB::table('warehouse_stock')
                            ->where('id', $stock->id)
                            ->decrement('qty', $remainingQty);
                    }
                    $remainingQty = 0;
                }
            }

            if ($remainingQty > 0) {
                \Log::warning("Could not remove all requested quantity. Remaining: {$remainingQty}");
                // Don't throw error, just log warning - mungkin ada perbedaan karena rounding atau data tidak konsisten
            }

            \Log::info("Successfully removed returned stock for product ID: {$productId}. Deleted stock IDs: " . implode(', ', $deletedStockIds));
            
        } catch (\Exception $e) {
            \Log::error("Error in removeReturnedStock: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e; // Re-throw to be caught by the calling method
        }
    }

    /**
     * Recalculate subtotal for an offline sale item based on new quantity
     * 
     * @param OfflineSaleItem $offlineSaleItem
     * @param float $newQuantity
     * @return float
     */
    private function recalculateSubtotal($offlineSaleItem, $newQuantity)
    {
        // Get base price
        $basePrice = $offlineSaleItem->unit_price;
        
        // Calculate total before discounts
        $totalBeforeDiscount = $basePrice * $newQuantity;
        $currentTotal = $totalBeforeDiscount;
        
        // Apply all percentage discounts (1-5) in sequence
        for($i = 1; $i <= 5; $i++) {
            $percentField = "discount_percent_" . $i;
            $discountPercent = $offlineSaleItem->$percentField ?? 0;
            if($discountPercent > 0) {
                $discountAmount = $currentTotal * ($discountPercent / 100);
                $currentTotal -= $discountAmount;
                // Apply cascading rounding after each discount
                $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
            }
        }
        
        // Apply all nominal discounts (1-5) in sequence
        for($i = 1; $i <= 5; $i++) {
            $amountField = "discount_amount_" . $i;
            $discountAmount = $offlineSaleItem->$amountField ?? 0;
            if($discountAmount > 0) {
                $currentTotal -= ($discountAmount * $newQuantity);
                // Apply cascading rounding after each discount
                $currentTotal = \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
            }
        }
        
        return \App\Helpers\NumberFormatter::roundToTwoDecimals($currentTotal);
    }
} 