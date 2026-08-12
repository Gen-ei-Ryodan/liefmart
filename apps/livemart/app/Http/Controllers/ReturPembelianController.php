<?php

namespace App\Http\Controllers;

use App\Models\Penerimaan;
use App\Models\PenerimaanDetail;
use App\Models\Product;
use App\Models\ReturPembelian;
use App\Models\ReturPembelianDetail;
use App\Models\Satuan;
use App\Models\WarehouseStock;
use App\Models\BarangKeluar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReturPembelianController extends Controller
{
    /**
     * Display a listing of the retur pembelian.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ReturPembelian::with(['penerimaan', 'user', 'details.penerimaanDetail']);

        // Search filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('kode_retur', 'like', '%' . $search . '%')
                  ->orWhereHas('penerimaan', function($penerimaanQuery) use ($search) {
                      $penerimaanQuery->where('nomor_po', 'like', '%' . $search . '%')
                                     ->orWhere('kode_penerimaan', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Tipe retur filter
        if ($request->filled('tipe_retur')) {
            $query->where('tipe_retur', $request->tipe_retur);
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

        $returPembelians = $query->orderBy('retur_pembelians.created_at', 'desc')->paginate(10);

        // Get filter options
        $users = \App\Models\User::orderBy('name')->get();

        return view('retur.pembelian.index', compact('returPembelians', 'users'));
    }

    /**
     * Show the form for creating a new retur pembelian.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Display a list of penerimaan (PO) to choose from with eager loaded relations
        // Limit to penerimaan from the last 6 months to avoid timeout and memory issues
        $penerimaanList = Penerimaan::with(['mainCategory', 'lokasi'])
            ->where('status', 'Located')
            ->where('tanggal_penerimaan', '>=', now()->subMonths(6))
            ->orderBy('tanggal_penerimaan', 'desc')
            ->limit(1000)
            ->get();

        return view('retur.pembelian.create', compact('penerimaanList'));
    }

    /**
     * Get a specific penerimaan with its details for the retur form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getPenerimaan($id)
    {
        $penerimaan = Penerimaan::with([
            'details.product', 
            'details.satuan',
            'mainCategory',
            'taxCategory',
            'lokasi'
        ])->where('id', $id)->firstOrFail();
        
        // Get available stock quantities for each product from warehouse_stock
        foreach ($penerimaan->details as $detail) {
            // Get all warehouse stock records for this penerimaan detail with qty > 0
            $stocks = WarehouseStock::withoutGlobalScopes()
                ->where('warehouse_stock.penerimaan_detail_id', $detail->id)
                ->where('warehouse_stock.qty', '>', 0)
                ->orderBy('warehouse_stock.created_at', 'asc')
                ->get();
                
            // Attach the stocks to the detail
            $detail->warehouse_stocks = $stocks;
            // Also calculate total available stock for backward compatibility
            $detail->available_stock = $stocks->sum('qty');
        }

        return response()->json($penerimaan);
    }

    /**
     * Store a newly created retur pembelian in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \Log::info('ReturPembelianController@store - Start', ['request' => $request->all()]);
        
        // Validasi field-field utama terlebih dahulu
        $baseValidator = \Validator::make($request->all(), [
            'penerimaan_id' => 'required|exists:penerimaan,id',
            'tanggal_retur' => 'required|date',
            'catatan' => 'nullable|string',
            'details' => 'required|array',
        ]);
        
        if ($baseValidator->fails()) {
            \Log::error('ReturPembelianController@store - Base validation failed', ['errors' => $baseValidator->errors()->toArray()]);
            return back()->withErrors($baseValidator)->withInput();
        }
        
        // Filter hanya detail dengan qty > 0
        $validDetails = [];
        $anyValidItem = false;
        
        if (isset($request->details) && is_array($request->details)) {
            foreach ($request->details as $index => $detail) {
                // Hanya proses item dengan qty > 0
                if (isset($detail['qty']) && floatval($detail['qty']) > 0) {
                    $validDetails[$index] = $detail;
                    $anyValidItem = true;
                }
            }
        }
        
        // Jika tidak ada item valid sama sekali
        if (!$anyValidItem) {
            \Log::error('ReturPembelianController@store - No valid items with qty > 0');
            return back()->with('error', 'Anda harus memasukkan jumlah retur minimal 1 barang.')->withInput();
        }
        
        // Validasi detail-detail yang valid saja
        $detailRules = [];
        
        foreach ($validDetails as $index => $detail) {
            $detailRules["details.{$index}.penerimaan_detail_id"] = 'required|exists:penerimaan_detail,id';
            $detailRules["details.{$index}.product_id"] = 'required|exists:products,id';
            $detailRules["details.{$index}.qty"] = 'required|numeric|min:0.01';
            $detailRules["details.{$index}.satuan_id"] = 'required|exists:satuans,id';
            $detailRules["details.{$index}.alasan"] = 'required|string';
            $detailRules["details.{$index}.warehouse_stock_id"] = 'required|exists:warehouse_stock,id';
        }
        
        $detailValidator = \Validator::make($request->all(), $detailRules);
        
        if ($detailValidator->fails()) {
            \Log::error('ReturPembelianController@store - Detail validation failed', ['errors' => $detailValidator->errors()->toArray()]);
            return back()->withErrors($detailValidator)->withInput();
        }

        try {
            DB::beginTransaction();
            
            // Log data untuk debugging
            \Log::info('Creating Retur Pembelian with data:', [
                'penerimaan_id' => $request->penerimaan_id,
                'user_id' => Auth::id() ?? null,
                'tanggal_retur' => $request->tanggal_retur,
                'valid_items_count' => count($validDetails)
            ]);
            
            // Verify penerimaan exists
            $penerimaan = Penerimaan::where('id', $request->penerimaan_id)->first();
            if (!$penerimaan) {
                \Log::error('ReturPembelianController@store - Penerimaan not found', ['penerimaan_id' => $request->penerimaan_id]);
                throw new \Exception('Penerimaan dengan ID tersebut tidak ditemukan.');
            }
            
            // Generate kode retur
            $kodeRetur = ReturPembelian::generateKodeRetur();
            \Log::info('Generated kode retur: ' . $kodeRetur);
            
            // Get all PO products
            $poProductDetails = PenerimaanDetail::where('penerimaan_id', $request->penerimaan_id)->get();
            $totalPoProductsCount = $poProductDetails->count();
            
            // Count total PO quantity for each product
            $poTotalQtyPerProduct = [];
            foreach ($poProductDetails as $poDetail) {
                if (!isset($poTotalQtyPerProduct[$poDetail->product_id])) {
                    $poTotalQtyPerProduct[$poDetail->product_id] = 0;
                }
                $poTotalQtyPerProduct[$poDetail->product_id] += $poDetail->qty;
            }
            
            // Count return quantities for each product
            $returQtyPerProduct = [];
            $returProductCount = 0;
            $allProductsFullyReturned = true;
            
            foreach ($validDetails as $detail) {
                $productId = $detail['product_id'];
                if (!isset($returQtyPerProduct[$productId])) {
                    $returQtyPerProduct[$productId] = 0;
                    $returProductCount++;
                }
                $returQtyPerProduct[$productId] += floatval($detail['qty']);
            }
            
            // Check if any product is not fully returned
            foreach ($poTotalQtyPerProduct as $productId => $totalQty) {
                $returQty = $returQtyPerProduct[$productId] ?? 0;
                if ($returQty < $totalQty) {
                    $allProductsFullyReturned = false;
                    break;
                }
            }
            
            // Determine retur type based on the new criteria
            $tipeRetur = ($returProductCount == $totalPoProductsCount && $allProductsFullyReturned) 
                ? ReturPembelian::TIPE_FULL 
                : ReturPembelian::TIPE_SEBAGIAN;
                
            \Log::info("Determining retur type", [
                'po_products_count' => $totalPoProductsCount,
                'returned_products_count' => $returProductCount,
                'all_fully_returned' => $allProductsFullyReturned,
                'tipe_retur' => $tipeRetur
            ]);

            // Create the retur pembelian header (directly as completed status)
            $returPembelian = ReturPembelian::create([
                'kode_retur' => $kodeRetur,
                'penerimaan_id' => $request->penerimaan_id,
                'user_id' => Auth::id() ?? null,
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
                'status' => 'selesai',
                'tipe_retur' => $tipeRetur,
            ]);
            
            \Log::info('Created Retur Pembelian header with ID: ' . $returPembelian->id);
            
            $detailsCreated = 0;
            $stockReduced = 0;

            // Process each detail item (only valid ones)
            foreach ($validDetails as $index => $detail) {
                // Verify penerimaan detail exists
                $penerimaanDetail = PenerimaanDetail::where('id', $detail['penerimaan_detail_id'])->first();
                if (!$penerimaanDetail) {
                    \Log::error('ReturPembelianController@store - PenerimaanDetail not found', [
                        'penerimaan_detail_id' => $detail['penerimaan_detail_id']
                    ]);
                    throw new \Exception('Detail penerimaan dengan ID ' . $detail['penerimaan_detail_id'] . ' tidak ditemukan.');
                }

                // Create detail record
                $returDetail = ReturPembelianDetail::create([
                    'retur_pembelian_id' => $returPembelian->id,
                    'penerimaan_detail_id' => $detail['penerimaan_detail_id'],
                    'product_id' => $detail['product_id'],
                    'qty' => $detail['qty'],
                    'satuan_id' => $detail['satuan_id'],
                    'alasan' => $detail['alasan'] ?? null,
                ]);
                
                $detailsCreated++;
                \Log::info('Created Retur Detail for product ID: ' . $detail['product_id'] . ' with qty: ' . $detail['qty']);

                try {
                    // Reduce stock from warehouse based on specific warehouse stock ID or using FIFO principle as fallback
                    $this->reduceStock($detail['product_id'], $detail['qty'], $detail['penerimaan_detail_id'], $detail['warehouse_stock_id'] ?? null, $returPembelian->id, $returPembelian->tanggal_retur);
                    $stockReduced++;
                    \Log::info('Reduced stock for product ID: ' . $detail['product_id']);
                } catch (\Exception $e) {
                    \Log::error('ReturPembelianController@store - Error reducing stock', [
                        'product_id' => $detail['product_id'],
                        'qty' => $detail['qty'],
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }

            \Log::info('Retur Pembelian creation summary', [
                'retur_id' => $returPembelian->id,
                'details_created' => $detailsCreated,
                'stock_reduced' => $stockReduced
            ]);
            
            DB::commit();
            \Log::info('Retur Pembelian creation successfully committed');

            return redirect()->route('retur-pembelian.show', $returPembelian->id)
                ->with('success', 'Retur pembelian berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating Retur Pembelian: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified retur pembelian.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $returPembelian = ReturPembelian::with(['penerimaan.taxCategory', 'user', 'details.product', 'details.satuan', 'details.penerimaanDetail'])
            ->where('id', $id)->firstOrFail();

        return view('retur.pembelian.show', compact('returPembelian'));
    }

    /**
     * Print the retur pembelian invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $returPembelian = ReturPembelian::with([
            'penerimaan.taxCategory',
            'penerimaan.mainCategory',
            'penerimaan.lokasi',
            'user',
            'details.product',
            'details.satuan',
            'details.penerimaanDetail'
        ])->where('id', $id)->firstOrFail();

        return view('retur.pembelian.print', compact('returPembelian'));
    }

    /**
     * Show the form for editing the specified retur pembelian.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $returPembelian = ReturPembelian::with([
            'penerimaan.mainCategory',
            'penerimaan.lokasi',
            'details.penerimaanDetail.product',
            'details.penerimaanDetail.satuan',
            'details.product',
            'details.satuan'
        ])->where('id', $id)->firstOrFail();

        // Calculate available stock for each retur detail
        foreach ($returPembelian->details as $returDetail) {
            if ($returDetail->penerimaanDetail) {
                $penerimaanDetail = $returDetail->penerimaanDetail;
                $currentReturQty = $returDetail->qty;
                
                // Get available warehouse stock for this specific penerimaan_detail_id and product_id
                $availableStock = WarehouseStock::withoutGlobalScopes()
                    ->where('warehouse_stock.penerimaan_detail_id', $penerimaanDetail->id)
                    ->where('warehouse_stock.product_id', $penerimaanDetail->product_id)
                    ->where('warehouse_stock.qty', '>', 0)
                    ->sum('warehouse_stock.qty');
                
                // Add back the current return qty since we're editing (this qty will be restored)
                $returDetail->available_stock = $availableStock + $currentReturQty;
            }
        }

        return view('retur.pembelian.edit', compact('returPembelian'));
    }

    /**
     * Update the specified retur pembelian in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        \Log::info('ReturPembelianController@update - Start', ['request' => $request->all(), 'id' => $id]);
        
        $returPembelian = ReturPembelian::with('details')->where('id', $id)->firstOrFail();

        // Validasi field-field utama
        $baseValidator = \Validator::make($request->all(), [
            'tanggal_retur' => 'required|date',
            'catatan' => 'nullable|string',
            'details' => 'required|array',
        ]);
        
        if ($baseValidator->fails()) {
            \Log::error('ReturPembelianController@update - Base validation failed', ['errors' => $baseValidator->errors()->toArray()]);
            return back()->withErrors($baseValidator)->withInput();
        }
        
        // Filter hanya detail dengan qty > 0
        $validDetails = [];
        $anyValidItem = false;
        
        if (isset($request->details) && is_array($request->details)) {
            foreach ($request->details as $index => $detail) {
                if (isset($detail['qty']) && floatval($detail['qty']) > 0) {
                    $validDetails[$index] = $detail;
                    $anyValidItem = true;
                }
            }
        }
        
        if (!$anyValidItem) {
            \Log::error('ReturPembelianController@update - No valid items with qty > 0');
            return back()->with('error', 'Anda harus memasukkan jumlah retur minimal 1 barang.')->withInput();
        }
        
        // Validasi detail-detail yang valid saja
        $detailRules = [];
        
        foreach ($validDetails as $index => $detail) {
            $detailRules["details.{$index}.penerimaan_detail_id"] = 'required|exists:penerimaan_detail,id';
            $detailRules["details.{$index}.product_id"] = 'required|exists:products,id';
            $detailRules["details.{$index}.qty"] = 'required|numeric|min:0.01';
            $detailRules["details.{$index}.satuan_id"] = 'required|exists:satuans,id';
            $detailRules["details.{$index}.alasan"] = 'required|string';
        }
        
        $detailValidator = \Validator::make($request->all(), $detailRules);
        
        if ($detailValidator->fails()) {
            \Log::error('ReturPembelianController@update - Detail validation failed', ['errors' => $detailValidator->errors()->toArray()]);
            return back()->withErrors($detailValidator)->withInput();
        }

        try {
            DB::beginTransaction();
            
            // Restore stock from old details
            foreach ($returPembelian->details as $oldDetail) {
                $this->restoreStock($oldDetail->product_id, $oldDetail->qty, $oldDetail->penerimaan_detail_id);
            }
            
            // Delete old details
            $returPembelian->details()->delete();
            
            // Update header
            $returPembelian->update([
                'tanggal_retur' => $request->tanggal_retur,
                'catatan' => $request->catatan,
            ]);
            
            // Get all PO products to determine retur type
            $poProductDetails = PenerimaanDetail::where('penerimaan_id', $returPembelian->penerimaan_id)->get();
            $totalPoProductsCount = $poProductDetails->count();
            
            // Count total PO quantity for each product
            $poTotalQtyPerProduct = [];
            foreach ($poProductDetails as $poDetail) {
                if (!isset($poTotalQtyPerProduct[$poDetail->product_id])) {
                    $poTotalQtyPerProduct[$poDetail->product_id] = 0;
                }
                $poTotalQtyPerProduct[$poDetail->product_id] += $poDetail->qty;
            }
            
            // Count return quantities for each product
            $returQtyPerProduct = [];
            $returProductCount = 0;
            $allProductsFullyReturned = true;
            
            foreach ($validDetails as $detail) {
                $productId = $detail['product_id'];
                if (!isset($returQtyPerProduct[$productId])) {
                    $returQtyPerProduct[$productId] = 0;
                    $returProductCount++;
                }
                $returQtyPerProduct[$productId] += floatval($detail['qty']);
            }
            
            // Check if any product is not fully returned
            foreach ($poTotalQtyPerProduct as $productId => $totalQty) {
                $returQty = $returQtyPerProduct[$productId] ?? 0;
                if ($returQty < $totalQty) {
                    $allProductsFullyReturned = false;
                    break;
                }
            }
            
            // Determine retur type
            $tipeRetur = ($returProductCount == $totalPoProductsCount && $allProductsFullyReturned) 
                ? ReturPembelian::TIPE_FULL 
                : ReturPembelian::TIPE_SEBAGIAN;
            
            $returPembelian->update(['tipe_retur' => $tipeRetur]);
            
            // Create new details and reduce stock
            foreach ($validDetails as $detail) {
                $penerimaanDetail = PenerimaanDetail::where('id', $detail['penerimaan_detail_id'])->first();
                if (!$penerimaanDetail) {
                    throw new \Exception('Detail penerimaan dengan ID ' . $detail['penerimaan_detail_id'] . ' tidak ditemukan.');
                }

                // Get available warehouse stock for this detail
                $warehouseStock = WarehouseStock::withoutGlobalScopes()
                    ->where('warehouse_stock.penerimaan_detail_id', $detail['penerimaan_detail_id'])
                    ->where('warehouse_stock.product_id', $detail['product_id'])
                    ->where('warehouse_stock.qty', '>', 0)
                    ->orderBy('warehouse_stock.created_at', 'asc')
                    ->first();
                
                if (!$warehouseStock || $warehouseStock->qty < $detail['qty']) {
                    throw new \Exception('Stok tidak cukup untuk produk ' . ($penerimaanDetail->product->name ?? 'N/A'));
                }

                // Create detail record
                ReturPembelianDetail::create([
                    'retur_pembelian_id' => $returPembelian->id,
                    'penerimaan_detail_id' => $detail['penerimaan_detail_id'],
                    'product_id' => $detail['product_id'],
                    'qty' => $detail['qty'],
                    'satuan_id' => $detail['satuan_id'],
                    'alasan' => $detail['alasan'] ?? null,
                ]);
                
                // Reduce stock
                $this->reduceStock(
                    $detail['product_id'], 
                    $detail['qty'], 
                    $detail['penerimaan_detail_id'], 
                    $warehouseStock->id, 
                    $returPembelian->id, 
                    $returPembelian->tanggal_retur
                );
            }
            
            DB::commit();
            \Log::info('Retur Pembelian update successfully committed');

            return redirect()->route('retur-pembelian.show', $returPembelian->id)
                ->with('success', 'Retur pembelian berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating Retur Pembelian: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified retur pembelian from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $returPembelian = ReturPembelian::with('details')->where('id', $id)->firstOrFail();

        try {
            DB::beginTransaction();

            // Add stock back to warehouse for all items
            foreach ($returPembelian->details as $detail) {
                $this->restoreStock($detail->product_id, $detail->qty, $detail->penerimaan_detail_id);
                \Log::info('Restored stock', [
                    'product_id' => $detail->product_id, 
                    'qty' => $detail->qty
                ]);
            }

            // Delete details and header
            $returPembelian->details()->delete();
            $returPembelian->delete();

            DB::commit();

            return redirect()->route('retur-pembelian.index')
                ->with('success', 'Retur pembelian berhasil dihapus dan stok telah dikembalikan.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting retur pembelian', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Reduce stock from warehouse based on specific warehouse stock ID or using FIFO principle as fallback
     *
     * @param  int  $productId
     * @param  float  $qty
     * @param  int  $penerimaanDetailId
     * @param  int|null  $warehouseStockId
     * @param  int|null  $returPembelianId
     * @param  string|null  $tanggalRetur
     * @return void
     */
    private function reduceStock($productId, $qty, $penerimaanDetailId, $warehouseStockId = null, $returPembelianId = null, $tanggalRetur = null)
    {
        \Log::info('Reducing stock for product', [
            'product_id' => $productId,
            'qty_to_reduce' => $qty,
            'penerimaan_detail_id' => $penerimaanDetailId,
            'warehouse_stock_id' => $warehouseStockId
        ]);
        
        // Get the product to verify it exists
        $product = Product::where('id', $productId)->first();
        if (!$product) {
            \Log::error('reduceStock - Product not found', ['product_id' => $productId]);
            throw new \Exception("Produk dengan ID {$productId} tidak ditemukan.");
        }
        
        // If a specific warehouse stock ID is provided, use that one
        if ($warehouseStockId) {
            $stock = WarehouseStock::withoutGlobalScopes()
                ->where('warehouse_stock.id', $warehouseStockId)
                ->where('warehouse_stock.product_id', $productId)
                ->where('warehouse_stock.penerimaan_detail_id', $penerimaanDetailId)
                ->where('warehouse_stock.qty', '>', 0)
                ->first();
                
            if (!$stock) {
                \Log::error('reduceStock - Specific warehouse stock not found or empty', [
                    'warehouse_stock_id' => $warehouseStockId,
                    'product_id' => $productId,
                    'penerimaan_detail_id' => $penerimaanDetailId
                ]);
                throw new \Exception("Stok spesifik dengan ID {$warehouseStockId} untuk produk {$product->name} tidak ditemukan atau kosong.");
            }
            
            if ($stock->qty < $qty) {
                \Log::error('reduceStock - Insufficient stock for specific warehouse stock', [
                    'warehouse_stock_id' => $warehouseStockId,
                    'product_id' => $productId,
                    'current_qty' => $stock->qty,
                    'requested_qty' => $qty
                ]);
                throw new \Exception("Stok produk {$product->name} tidak cukup (Tersedia: {$stock->qty}, Dibutuhkan: {$qty}).");
            }
            
            // Update the stock
            $stock->qty -= $qty;
            $stock->save();
            
            // Record stock out movement for retur pembelian
            $this->recordStockOut($stock, $qty, $returPembelianId ?? null, $tanggalRetur);
            
            \Log::info('Stock reduction completed for specific warehouse stock', [
                'warehouse_stock_id' => $stock->id,
                'product_id' => $productId,
                'previous_qty' => $stock->qty + $qty,
                'reduced_qty' => $qty,
                'new_qty' => $stock->qty
            ]);
            
            return;
        }
        
        // If no specific ID provided, fall back to the original FIFO method
        // Get warehouse stock connected to the penerimaan_detail_id
        $warehouseStocks = WarehouseStock::withoutGlobalScopes()
            ->where('warehouse_stock.product_id', $productId)
            ->where('warehouse_stock.penerimaan_detail_id', $penerimaanDetailId)
            ->where('warehouse_stock.qty', '>', 0)
            ->orderBy('warehouse_stock.created_at', 'asc')    // FIFO berdasarkan tanggal penerimaan
            ->lockForUpdate()
            ->get();
            
        if ($warehouseStocks->isEmpty()) {
            \Log::error('reduceStock - No warehouse stock found for this PO and product', [
                'product_id' => $productId,
                'penerimaan_detail_id' => $penerimaanDetailId
            ]);
            throw new \Exception("Stok produk {$product->name} dari PO ini tidak tersedia di gudang.");
        }
        
        // Check total stock availability for this PO detail
        $totalAvailableStock = $warehouseStocks->sum('qty');
        
        \Log::info('Total stock available for this PO and product', [
            'product_id' => $productId,
            'product_name' => $product->name,
            'total_stock' => $totalAvailableStock
        ]);
        
        if ($totalAvailableStock < $qty) {
            \Log::error('reduceStock - Insufficient total stock', [
                'product_id' => $productId, 
                'total_stock' => $totalAvailableStock, 
                'qty_requested' => $qty
            ]);
            throw new \Exception("Stok produk {$product->name} dari PO ini tidak cukup untuk retur (Tersedia: {$totalAvailableStock}, Dibutuhkan: {$qty}).");
        }

        // Reduce stock using FIFO principle
        $remainingQty = $qty;
        
        foreach ($warehouseStocks as $stock) {
            if ($remainingQty <= 0) {
                break;
            }
            
            $qtyToTake = min($remainingQty, $stock->qty);
            
            \Log::info("Reducing from stock ID: {$stock->id}", [
                'warehouse_stock_id' => $stock->id,
                'expired_date' => $stock->expired_date,
                'current_qty' => $stock->qty,
                'qty_to_take' => $qtyToTake
            ]);
            
            // Update the stock
            $stock->qty -= $qtyToTake;
            $stock->save();
            
            // Record stock out movement for retur pembelian
            $this->recordStockOut($stock, $qtyToTake, $returPembelianId ?? null, $tanggalRetur);
            
            // Update remaining qty to reduce
            $remainingQty -= $qtyToTake;
        }
        
        \Log::info('Stock reduction completed using FIFO', [
            'product_id' => $productId,
            'total_reduced' => $qty,
            'remaining_qty' => $remainingQty
        ]);
        
        if ($remainingQty > 0) {
            // This should not happen due to our previous total check
            \Log::error('reduceStock - Could not reduce all requested qty', [
                'product_id' => $productId, 
                'remaining_qty' => $remainingQty
            ]);
            throw new \Exception("Tidak dapat mengurangi seluruh kuantitas yang diminta untuk produk {$product->name}.");
        }
    }

    /**
     * Restore stock that was reduced during retur
     *
     * @param  int  $productId
     * @param  float  $qty
     * @param  int  $penerimaanDetailId
     * @return void
     */
    private function restoreStock($productId, $qty, $penerimaanDetailId)
    {
        \Log::info('Restoring stock for product', [
            'product_id' => $productId,
            'qty_to_restore' => $qty,
            'penerimaan_detail_id' => $penerimaanDetailId
        ]);
        
        // Find the oldest warehouse stock entry for this product and penerimaan_detail_id
        // Or create a new one if none exists
        $warehouseStock = WarehouseStock::withoutGlobalScopes()
            ->where('warehouse_stock.product_id', $productId)
            ->where('warehouse_stock.penerimaan_detail_id', $penerimaanDetailId)
            ->orderBy('warehouse_stock.created_at', 'asc')
            ->first();
            
        if (!$warehouseStock) {
            // Create new warehouse stock entry
            $warehouseStock = WarehouseStock::create([
                'product_id' => $productId,
                'penerimaan_detail_id' => $penerimaanDetailId,
                'lokasi_id' => 1, // Default location, you might need to adjust this
                'qty' => $qty,
                'expired_date' => null, // You might need to handle expiry date appropriately
            ]);
            
            \Log::info('Created new warehouse stock entry for restored stock', [
                'warehouse_stock_id' => $warehouseStock->id,
                'qty' => $qty
            ]);
        } else {
            // Update existing warehouse stock
            $warehouseStock->qty += $qty;
            $warehouseStock->save();
            
            \Log::info('Updated existing warehouse stock with restored quantity', [
                'warehouse_stock_id' => $warehouseStock->id,
                'previous_qty' => $warehouseStock->qty - $qty,
                'added_qty' => $qty,
                'new_qty' => $warehouseStock->qty
            ]);
        }
        
        // Remove related barang keluar records for this retur pembelian
        $this->removeStockOutRecords($productId, $penerimaanDetailId);
    }

    /**
     * Remove stock out records related to retur pembelian
     *
     * @param  int  $productId
     * @param  int  $penerimaanDetailId
     * @return void
     */
    private function removeStockOutRecords($productId, $penerimaanDetailId)
    {
        try {
            // Find and delete barang keluar records related to retur pembelian for this product
            $barangKeluarRecords = BarangKeluar::whereHas('warehouseStock', function($query) use ($productId, $penerimaanDetailId) {
                $query->where('product_id', $productId)
                      ->where('penerimaan_detail_id', $penerimaanDetailId);
            })->where('catatan', 'like', 'Retur Pembelian%')
              ->get();
            
            foreach ($barangKeluarRecords as $record) {
                $record->delete();
                \Log::info('Removed stock out record for retur pembelian', [
                    'barang_keluar_id' => $record->id,
                    'kode_barang_keluar' => $record->kode_barang_keluar
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error removing stock out records for retur pembelian', [
                'product_id' => $productId,
                'penerimaan_detail_id' => $penerimaanDetailId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Export retur pembelian data to Excel
     *
     * @return \Illuminate\Http\Response
     */
    public function export()
    {
        $returPembelians = ReturPembelian::with([
                'penerimaan', 
                'user', 
                'details.product', 
                'details.satuan',
                'details.penerimaanDetail'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $exportData = [];
        foreach ($returPembelians as $retur) {
            foreach ($retur->details as $detail) {
                // Calculate harga per unit after tiered discounts
                $hargaHpp = 0;
                if ($detail->penerimaanDetail) {
                    $penerimaanDetail = $detail->penerimaanDetail;
                    if ($penerimaanDetail->qty > 0 && $penerimaanDetail->subtotal > 0) {
                        $hargaHpp = $penerimaanDetail->subtotal / $penerimaanDetail->qty;
                    } else {
                        // Fallback: calculate from harga_hpp with discounts
                        $hargaHpp = $penerimaanDetail->harga_hpp;
                        for ($i = 1; $i <= 5; $i++) {
                            $diskonPersen = $penerimaanDetail->{"diskon_persen_$i"} ?? 0;
                            if ($diskonPersen > 0) {
                                $hargaHpp = $hargaHpp * (1 - $diskonPersen / 100);
                            }
                        }
                        for ($i = 1; $i <= 5; $i++) {
                            $diskonNominal = $penerimaanDetail->{"diskon_nominal_$i"} ?? 0;
                            if ($diskonNominal > 0 && $penerimaanDetail->qty > 0) {
                                $hargaHpp = $hargaHpp - ($diskonNominal / $penerimaanDetail->qty);
                            }
                        }
                    }
                }
                $totalNominal = $hargaHpp * $detail->qty;
                
                $exportData[] = [
                    'Kode Retur' => $retur->kode_retur,
                    'Nomor PO' => $retur->penerimaan->nomor_po,
                    'Tanggal Penerimaan' => $retur->penerimaan->tanggal_penerimaan ? $retur->penerimaan->tanggal_penerimaan->format('d/m/Y') : '-',
                    'Tanggal Retur' => $retur->tanggal_retur ? $retur->tanggal_retur->format('d/m/Y') : '-',
                    'Tipe Retur' => ucfirst($retur->tipe_retur),
                    'Nama Produk' => $detail->product->name ?? '-',
                    'Harga' => round($hargaHpp, 2),
                    'Qty Retur' => round($detail->qty, 2),
                    'Satuan' => $detail->satuan->name ?? '-',
                    'Total Nominal' => round($totalNominal, 2),
                    'Alasan' => $detail->alasan ?? '-',
                    'User' => $retur->user->name ?? 'N/A',
                    'Dibuat Pada' => $retur->created_at ? $retur->created_at->format('d/m/Y H:i') : '-',
                ];
            }
        }

        return Excel::download(new class($exportData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithColumnFormatting, \Maatwebsite\Excel\Concerns\WithCustomValueBinder {
            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Kode Retur',
                    'Nomor PO',
                    'Tanggal Penerimaan',
                    'Tanggal Retur',
                    'Tipe Retur',
                    'Nama Produk',
                    'Harga',
                    'Qty Retur',
                    'Satuan',
                    'Total Nominal',
                    'Alasan',
                    'User',
                    'Dibuat Pada'
                ];
            }

            public function columnFormats(): array
            {
                return [
                    'G' => '#,##0.00', // Harga
                    'H' => '#,##0.00', // Qty Retur
                    'J' => '#,##0.00', // Total Nominal
                ];
            }

            public function bindValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell, $value)
            {
                if (is_numeric($value)) {
                    $cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    return true;
                }
                $cell->setValue($value);
                return true;
            }
        }, 'retur_pembelian_' . date('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * Record stock out movement for retur pembelian
     *
     * @param  WarehouseStock  $stock
     * @param  float  $qty
     * @param  int|null  $returPembelianId
     * @param  string|null  $tanggalRetur
     * @return void
     */
    private function recordStockOut($stock, $qty, $returPembelianId = null, $tanggalRetur = null)
    {
        try {
            // Generate kode barang keluar
            $kodeBarangKeluar = BarangKeluar::generateKode();
            
            // Get retur pembelian info for notes
            $returPembelian = null;
            if ($returPembelianId) {
                $returPembelian = ReturPembelian::with('penerimaan')->find($returPembelianId);
            }
            
            $catatan = 'Retur Pembelian';
            if ($returPembelian && $returPembelian->penerimaan) {
                $catatan .= " - PO: {$returPembelian->penerimaan->nomor_po}";
            }
            
            // Create barang keluar record
            BarangKeluar::create([
                'kode_barang_keluar' => $kodeBarangKeluar,
                'warehouse_stock_id' => $stock->id,
                'qty' => $qty,
                'tanggal_keluar' => $tanggalRetur ? \Carbon\Carbon::parse($tanggalRetur)->toDateString() : now()->toDateString(),
                'catatan' => $catatan,
            ]);
            
            \Log::info('Recorded stock out for retur pembelian', [
                'kode_barang_keluar' => $kodeBarangKeluar,
                'warehouse_stock_id' => $stock->id,
                'qty' => $qty,
                'retur_pembelian_id' => $returPembelianId
            ]);
        } catch (\Exception $e) {
            \Log::error('Error recording stock out for retur pembelian', [
                'warehouse_stock_id' => $stock->id,
                'qty' => $qty,
                'retur_pembelian_id' => $returPembelianId,
                'error' => $e->getMessage()
            ]);
            // Don't throw exception here to avoid breaking the main flow
        }
    }
} 