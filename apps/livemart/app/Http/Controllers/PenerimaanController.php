<?php

namespace App\Http\Controllers;

use App\Models\BarangKeluar;
use App\Models\MainCategory;
use App\Models\OfflineSaleItem;
use App\Models\OrderItem;
use App\Models\Penerimaan;
use App\Models\PenerimaanActivity;
use App\Models\PenerimaanDetail;
use App\Models\Product;
use App\Models\Satuan;
use App\Models\TaxCategory;
use App\Models\WarehouseStock;
use Shared\Helpers\NumberFormatter;
use App\Exports\PenerimaanExport;
use App\Exports\PenerimaanDetailExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PenerimaanController extends Controller
{
    /**
     * Menampilkan daftar penerimaan barang
     */
    public function index(Request $request)
    {
        // Get all available categories for the filter dropdown
        $mainCategories = MainCategory::where('is_active', true)->get();
        
        // Build the query with filters
        $query = Penerimaan::with(['mainCategory', 'taxCategory'])
            ->when($request->filled('kode'), function ($q) use ($request) {
                return $q->where('kode_penerimaan', 'like', '%' . $request->kode . '%');
            })
            ->when($request->filled('kategori'), function ($q) use ($request) {
                return $q->where('main_category_id', $request->kategori);
            })
            ->when($request->filled('nomor_po'), function ($q) use ($request) {
                return $q->where('nomor_po', 'like', '%' . $request->nomor_po . '%');
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                return $q->where('status', $request->status);
            })
            ->when($request->filled('tax_category'), function ($q) use ($request) {
                return $q->whereHas('taxCategory', function ($subQ) use ($request) {
                    $subQ->where('name', $request->tax_category);
                });
            })
            ->when($request->filled('start_date'), function ($q) use ($request) {
                return $q->whereDate('tanggal_penerimaan', '>=', $request->start_date);
            })
            ->when($request->filled('end_date'), function ($q) use ($request) {
                return $q->whereDate('tanggal_penerimaan', '<=', $request->end_date);
            })
            ->orderBy('tanggal_penerimaan', 'asc');
        
        // Execute the query with pagination
        $penerimaan = $query->paginate(10)->withQueryString();

        return view('penerimaan.index', compact('penerimaan', 'mainCategories'));
    }

    /**
     * Export data penerimaan to Excel
     */
    public function export(Request $request)
    {
        $filename = 'penerimaan_' . date('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new PenerimaanExport($request), $filename);
    }

    /**
     * Export detail penerimaan per barang to Excel with separate sheets
     */
    public function exportDetail(Request $request)
    {
        $filename = 'penerimaan_detail_' . date('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new PenerimaanDetailExport($request), $filename);
    }

    /**
     * Menampilkan form tambah penerimaan
     */
    public function create()
    {
        // Get the selected main category ID from session
        $mainCategoryId = session('main_category_id');
        
        // Get tax categories for this main category
        $taxCategories = TaxCategory::where('main_category_id', $mainCategoryId)
                                   ->where('is_active', true)
                                   ->get();
        
        // Ambil data satuan
        $satuan = Satuan::where('is_active', true)->get();

        $kodePenerimaan = $this->generateUniqueKodePenerimaan();

        return view('penerimaan.create', compact('satuan', 'kodePenerimaan', 'taxCategories'));
    }

    /**
     * Mendapatkan kategori pajak berdasarkan kategori utama
     */
    public function getTaxCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main_category_id' => 'required|exists:main_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $taxCategories = TaxCategory::where('main_category_id', $request->main_category_id)
                ->where('is_active', true)
                ->select('id', 'name', 'description', 'tax_percentage')
                ->get();

            return response()->json([
                'success' => true,
                'tax_categories' => $taxCategories,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getTaxCategories: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch tax categories',
            ], 500);
        }
    }

    public function getProducts(Request $request)
    {
        try {
            $query = Product::where('is_active', true);
            
            if ($request->has('main_category_id')) {
                $query->where('main_category_id', $request->main_category_id);
            }
            
            if ($request->has('search')) {
                $query->where('name', 'like', '%'.$request->search.'%');
            }
            
            $products = $query->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'text' => $product->name,
                        'harga_hpp' => $product->price,
                        'default_satuan_id' => $product->default_satuan_id,
                        'status_pajak' => $product->tax_status ?? null,
                    ];
                });

            return response()->json($products);
        } catch (\Exception $e) {
            \Log::error('Error in getProducts: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch products'], 500);
        }
    }

    /**
     * Menyimpan data penerimaan baru
     */
    public function store(Request $request)
    {
        // Log jumlah items yang diterima untuk debugging max_input_vars issue
        \Log::info('Penerimaan Store - Items received:', [
            'barang_id_count' => is_array($request->barang_id) ? count($request->barang_id) : 0,
            'qty_count' => is_array($request->qty) ? count($request->qty) : 0,
            'satuan_id_count' => is_array($request->satuan_id) ? count($request->satuan_id) : 0,
            'harga_hpp_count' => is_array($request->harga_hpp) ? count($request->harga_hpp) : 0,
            'max_input_vars' => ini_get('max_input_vars'),
            'kode_penerimaan' => $request->kode_penerimaan,
        ]);
        
        // Validasi request
        $request->validate([
            'main_category_id' => 'required',
            'tax_category_id' => 'required',
            'kode_penerimaan' => 'required|unique:penerimaan,kode_penerimaan',
            'nomor_po' => 'required',
            'tanggal_penerimaan' => 'required|date',
            'metode_pembayaran' => 'required|in:Cash,Jatuh Tempo',
            'tanggal_jatuh_tempo' => 'required_if:metode_pembayaran,Jatuh Tempo|nullable|date',
            'barang_id' => 'required|array',
            'qty' => 'required|array',
            'satuan_id' => 'required|array',
            'harga_hpp' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // Total harga akan dihitung ulang setelah detail disimpan

            // Buat record penerimaan baru
            $penerimaan = Penerimaan::create([
                'kode_penerimaan' => $request->kode_penerimaan,
                'main_category_id' => $request->main_category_id,
                'tax_category_id' => $request->tax_category_id,
                'nomor_po' => $request->nomor_po,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tanggal_jatuh_tempo' => $request->metode_pembayaran == 'Jatuh Tempo' ? $request->tanggal_jatuh_tempo : null,
                'total_harga' => 0, // Will be recalculated after details are saved
                'status' => 'Unlocated',
                'catatan' => $request->catatan,
                'lokasi_id' => 1, // Default lokasi ID (Unlocated)
            ]);
            
            // Log the creation activity
            $this->logActivity($penerimaan->id, 'create', 'Membuat penerimaan baru', [
                'kode' => $penerimaan->kode_penerimaan,
                'total_items' => count($request->barang_id),
                'total_harga' => 0 // Will be updated after recalculation
            ]);

            // Simpan detail penerimaan
            foreach ($request->barang_id as $index => $barangId) {
                $isFree = isset($request->is_free[$index]) && $request->is_free[$index] == 1;
                $harga = $isFree ? 0 : NumberFormatter::formatDecimal($request->harga_hpp[$index]);

                $subtotal = 0;
                if (! $isFree) {
                    $qty = NumberFormatter::formatDecimal($request->qty[$index]);
                    $subtotal = NumberFormatter::multiplyDecimal($qty, $harga);

                    // Hitung diskon bertingkat (cascading discounts)
                    for ($i = 1; $i <= 5; $i++) {
                        $diskonPersen = isset($request->{"diskon_persen_$i"}[$index]) ? NumberFormatter::formatDecimal($request->{"diskon_persen_$i"}[$index]) : 0;
                        $diskonNominal = isset($request->{"diskon_nominal_$i"}[$index]) ? NumberFormatter::formatDecimal($request->{"diskon_nominal_$i"}[$index]) : 0;

                        if ($diskonPersen > 0) {
                            $potongan = NumberFormatter::percentageOf($subtotal, $diskonPersen);
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $potongan);
                        } elseif ($diskonNominal > 0) {
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $diskonNominal);
                        }
                    }
                }

                PenerimaanDetail::create([
                    'penerimaan_id' => $penerimaan->id,
                    'product_id' => $barangId,
                    'qty' => NumberFormatter::formatDecimal($request->qty[$index]),
                    'satuan_id' => $request->satuan_id[$index],
                    'harga_hpp' => $harga,
                    'diskon_persen_1' => NumberFormatter::formatDecimal($request->diskon_persen_1[$index] ?? 0),
                    'diskon_nominal_1' => NumberFormatter::formatDecimal($request->diskon_nominal_1[$index] ?? 0),
                    'diskon_persen_2' => NumberFormatter::formatDecimal($request->diskon_persen_2[$index] ?? 0),
                    'diskon_nominal_2' => NumberFormatter::formatDecimal($request->diskon_nominal_2[$index] ?? 0),
                    'diskon_persen_3' => NumberFormatter::formatDecimal($request->diskon_persen_3[$index] ?? 0),
                    'diskon_nominal_3' => NumberFormatter::formatDecimal($request->diskon_nominal_3[$index] ?? 0),
                    'diskon_persen_4' => NumberFormatter::formatDecimal($request->diskon_persen_4[$index] ?? 0),
                    'diskon_nominal_4' => NumberFormatter::formatDecimal($request->diskon_nominal_4[$index] ?? 0),
                    'diskon_persen_5' => NumberFormatter::formatDecimal($request->diskon_persen_5[$index] ?? 0),
                    'diskon_nominal_5' => NumberFormatter::formatDecimal($request->diskon_nominal_5[$index] ?? 0),
                    'is_free' => $isFree,
                    'subtotal' => $subtotal,
                    'catatan' => $request->detail_catatan[$index] ?? null,
                ]);
            }

            // Recalculate total_harga from detail items to ensure consistency
            $penerimaan->recalculateTotalHarga();

            DB::commit();

            return redirect()->route('penerimaan.index')
                ->with('success', 'Penerimaan barang berhasil disimpan dan masuk ke unlocated inventory.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Menampilkan detail penerimaan
     */
    public function show($id)
    {
        $penerimaan = Penerimaan::with([
            'mainCategory', 
            'details.product', 
            'details.satuan',
            'activities.user'
        ])->findOrFail($id);

        return view('penerimaan.show', compact('penerimaan'));
    }

    /**
     * Menampilkan form edit penerimaan
     */
    public function edit($id)
    {
        // Check user permission (hanya superadmin)
        if (!Auth::user()->isSuperAdmin()) {
            return redirect()->route('penerimaan.index')
                ->with('error', 'Anda tidak memiliki izin untuk mengedit data penerimaan.');
        }

        try {
            // Load data penerimaan beserta detailnya
            $penerimaan = Penerimaan::with(['details.product', 'details.satuan', 'mainCategory', 'taxCategory'])
                ->findOrFail($id);

            // Cek apakah ada WarehouseStock dari penerimaan ini yang sudah digunakan
            // di barang_keluar, order_items, atau offline_sale_items
            $detailIds = $penerimaan->details()->pluck('id')->toArray();
            $warehouseStockIds = WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->pluck('id')->toArray();
            
            $hasBarangKeluar = !empty($warehouseStockIds) && BarangKeluar::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOrderItem = !empty($warehouseStockIds) && OrderItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOfflineSaleItem = !empty($warehouseStockIds) && OfflineSaleItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            
            if ($hasBarangKeluar || $hasOrderItem || $hasOfflineSaleItem) {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Penerimaan tidak dapat diedit karena stok sudah digunakan dalam transaksi penjualan.');
            }
        } catch (\Exception $e) {
            return redirect()->route('penerimaan.index')
                ->with('error', 'Data penerimaan tidak ditemukan.');
        }

        // Ambil data kategori utama dan satuan
        $mainCategories = MainCategory::where('is_active', true)->get();
        $satuan = Satuan::where('is_active', true)->get();

        // Ambil produk sesuai dengan kategori utama yang dipilih
        $products = Product::where('main_category_id', $penerimaan->main_category_id)
            ->where('is_active', true)
            ->get();

        // Make sure tax_category is loaded
        if (!$penerimaan->relationLoaded('taxCategory')) {
            $penerimaan->load('taxCategory');
        }

        return view('penerimaan.edit', compact('penerimaan', 'mainCategories', 'satuan', 'products'));
    }

    /**
     * Update data penerimaan
     */
    public function update(Request $request, $id)
    {
        // Check user permission (hanya superadmin)
        if (!Auth::user()->isSuperAdmin()) {
            return redirect()->route('penerimaan.index')
                ->with('error', 'Anda tidak memiliki izin untuk mengedit data penerimaan.');
        }

        // Log jumlah items yang diterima untuk debugging max_input_vars issue
        \Log::info('Penerimaan Update - Items received:', [
            'penerimaan_id' => $id,
            'barang_id_count' => is_array($request->barang_id) ? count($request->barang_id) : 0,
            'qty_count' => is_array($request->qty) ? count($request->qty) : 0,
            'satuan_id_count' => is_array($request->satuan_id) ? count($request->satuan_id) : 0,
            'harga_hpp_count' => is_array($request->harga_hpp) ? count($request->harga_hpp) : 0,
            'max_input_vars' => ini_get('max_input_vars'),
        ]);
        
        // Validasi request
        $request->validate([
            'main_category_id' => 'required',
            'tax_category_id' => 'required',
            'nomor_po' => 'required',
            'tanggal_penerimaan' => 'required|date',
            'metode_pembayaran' => 'required|in:Cash,Jatuh Tempo',
            'tanggal_jatuh_tempo' => 'required_if:metode_pembayaran,Jatuh Tempo|nullable|date',
            'barang_id' => 'required|array',
            'qty' => 'required|array',
            'satuan_id' => 'required|array',
            'harga_hpp' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $penerimaan = Penerimaan::findOrFail($id);

            // Cek apakah ada WarehouseStock dari penerimaan ini yang sudah digunakan
            // di barang_keluar, order_items, atau offline_sale_items
            $detailIds = $penerimaan->details()->pluck('id')->toArray();
            $warehouseStockIds = WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->pluck('id')->toArray();
            
            $hasBarangKeluar = !empty($warehouseStockIds) && BarangKeluar::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOrderItem = !empty($warehouseStockIds) && OrderItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOfflineSaleItem = !empty($warehouseStockIds) && OfflineSaleItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            
            if ($hasBarangKeluar || $hasOrderItem || $hasOfflineSaleItem) {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Penerimaan tidak dapat diedit karena stok sudah digunakan dalam transaksi penjualan.');
            }

            // Store old data for logging
            $oldData = [
                'nomor_po' => $penerimaan->nomor_po,
                'tanggal_penerimaan' => $penerimaan->tanggal_penerimaan,
                'metode_pembayaran' => $penerimaan->metode_pembayaran,
                'tax_category_id' => $penerimaan->tax_category_id,
                'total_harga' => $penerimaan->total_harga,
                'item_count' => $penerimaan->details()->count()
            ];

            // Total harga akan dihitung ulang setelah detail disimpan untuk memastikan konsistensi

            // Update data penerimaan
            $penerimaan->update([
                'main_category_id' => $request->main_category_id,
                'tax_category_id' => $request->tax_category_id,
                'nomor_po' => $request->nomor_po,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tanggal_jatuh_tempo' => $request->metode_pembayaran == 'Jatuh Tempo' ? $request->tanggal_jatuh_tempo : null,
                'catatan' => $request->catatan,
            ]);
            
            // Log activity akan dilakukan setelah recalculation total

            // Hapus detail penerimaan lama
            PenerimaanDetail::where('penerimaan_id', $penerimaan->id)->delete();

            // Simpan detail penerimaan baru
            foreach ($request->barang_id as $index => $barangId) {
                $isFree = isset($request->is_free[$index]) && $request->is_free[$index] == 1;
                $harga = $isFree ? 0 : NumberFormatter::formatDecimal($request->harga_hpp[$index]);

                $subtotal = 0;
                if (! $isFree) {
                    $qty = NumberFormatter::formatDecimal($request->qty[$index]);
                    $subtotal = NumberFormatter::multiplyDecimal($qty, $harga);

                    // Hitung diskon bertingkat (cascading discounts)
                    for ($i = 1; $i <= 5; $i++) {
                        $diskonPersen = isset($request->{"diskon_persen_$i"}[$index]) ? NumberFormatter::formatDecimal($request->{"diskon_persen_$i"}[$index]) : 0;
                        $diskonNominal = isset($request->{"diskon_nominal_$i"}[$index]) ? NumberFormatter::formatDecimal($request->{"diskon_nominal_$i"}[$index]) : 0;

                        if ($diskonPersen > 0) {
                            $potongan = NumberFormatter::percentageOf($subtotal, $diskonPersen);
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $potongan);
                        } elseif ($diskonNominal > 0) {
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $diskonNominal);
                        }
                    }
                }

                PenerimaanDetail::create([
                    'penerimaan_id' => $penerimaan->id,
                    'product_id' => $barangId,
                    'qty' => NumberFormatter::formatDecimal($request->qty[$index]),
                    'satuan_id' => $request->satuan_id[$index],
                    'harga_hpp' => $harga,
                    'diskon_persen_1' => NumberFormatter::formatDecimal($request->diskon_persen_1[$index] ?? 0),
                    'diskon_nominal_1' => NumberFormatter::formatDecimal($request->diskon_nominal_1[$index] ?? 0),
                    'diskon_persen_2' => NumberFormatter::formatDecimal($request->diskon_persen_2[$index] ?? 0),
                    'diskon_nominal_2' => NumberFormatter::formatDecimal($request->diskon_nominal_2[$index] ?? 0),
                    'diskon_persen_3' => NumberFormatter::formatDecimal($request->diskon_persen_3[$index] ?? 0),
                    'diskon_nominal_3' => NumberFormatter::formatDecimal($request->diskon_nominal_3[$index] ?? 0),
                    'diskon_persen_4' => NumberFormatter::formatDecimal($request->diskon_persen_4[$index] ?? 0),
                    'diskon_nominal_4' => NumberFormatter::formatDecimal($request->diskon_nominal_4[$index] ?? 0),
                    'diskon_persen_5' => NumberFormatter::formatDecimal($request->diskon_persen_5[$index] ?? 0),
                    'diskon_nominal_5' => NumberFormatter::formatDecimal($request->diskon_nominal_5[$index] ?? 0),
                    'is_free' => $isFree,
                    'subtotal' => $subtotal,
                    'catatan' => $request->detail_catatan[$index] ?? null,
                ]);
            }

            // Recalculate total_harga from detail items to ensure consistency
            $newTotalHarga = $penerimaan->recalculateTotalHarga();

            // Log the update activity with recalculated total
            $newData = [
                'nomor_po' => $penerimaan->nomor_po,
                'tanggal_penerimaan' => $penerimaan->tanggal_penerimaan,
                'metode_pembayaran' => $penerimaan->metode_pembayaran,
                'total_harga' => $newTotalHarga,
                'item_count' => count($request->barang_id)
            ];
            
            $this->logActivity($penerimaan->id, 'update', 'Mengubah data penerimaan', [
                'old_data' => $oldData,
                'new_data' => $newData
            ]);

            DB::commit();

            return redirect()->route('penerimaan.index')
                ->with('success', 'Data penerimaan barang berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Menghapus data penerimaan
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $penerimaan = Penerimaan::findOrFail($id);

            // Hanya boleh hapus jika statusnya masih Unlocated
            if ($penerimaan->status !== 'Unlocated') {
                return redirect()->route('penerimaan.index')
                    ->with('error', 'Penerimaan yang sudah diproses (Located) tidak dapat dihapus.');
            }

            // Hapus WarehouseStock terkait agar tidak jadi stok orphan
            $detailIds = PenerimaanDetail::where('penerimaan_id', $id)->pluck('id')->toArray();
            if (!empty($detailIds)) {
                WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->delete();
            }

            // Hapus detail penerimaan
            PenerimaanDetail::where('penerimaan_id', $id)->delete();

            // Hapus penerimaan
            $penerimaan->delete();

            DB::commit();

            return redirect()->route('penerimaan.index')
                ->with('success', 'Data penerimaan barang berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('penerimaan.index')
                ->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Print penerimaan document
     */
    public function print($id)
    {
        $penerimaan = Penerimaan::with([
            'mainCategory', 
            'taxCategory',
            'details.product', 
            'details.satuan'
        ])->findOrFail($id);

        return view('penerimaan.print', compact('penerimaan'));
    }

    /**
     * Create header penerimaan (AJAX)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createHeader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'main_category_id' => 'required',
            'tax_category_id' => 'required',
            'kode_penerimaan' => 'nullable|string',
            'nomor_po' => 'required',
            'tanggal_penerimaan' => 'required|date',
            'metode_pembayaran' => 'required|in:Cash,Jatuh Tempo',
            'tanggal_jatuh_tempo' => 'required_if:metode_pembayaran,Jatuh Tempo|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $requestedKode = (string) ($request->kode_penerimaan ?? '');
            $requestedKode = trim($requestedKode);

            $existing = null;
            if ($requestedKode !== '') {
                $existing = Penerimaan::withoutGlobalScopes()
                    ->where('kode_penerimaan', $requestedKode)
                    ->first();
            }

            if ($existing && $existing->status === 'Unlocated' && $existing->details()->count() === 0) {
                $existing->update([
                    'main_category_id' => $request->main_category_id,
                    'tax_category_id' => $request->tax_category_id,
                    'nomor_po' => $request->nomor_po,
                    'tanggal_penerimaan' => $request->tanggal_penerimaan,
                    'metode_pembayaran' => $request->metode_pembayaran,
                    'tanggal_jatuh_tempo' => $request->metode_pembayaran == 'Jatuh Tempo' ? $request->tanggal_jatuh_tempo : null,
                    'catatan' => $request->catatan,
                ]);

                return response()->json([
                    'success' => true,
                    'penerimaan_id' => $existing->id,
                    'kode_penerimaan' => $existing->kode_penerimaan,
                    'message' => 'Header penerimaan berhasil dibuat'
                ]);
            }

            $kodeToUse = $requestedKode;
            if ($kodeToUse === '' || $existing) {
                $kodeToUse = $this->generateUniqueKodePenerimaan();
            }

            $penerimaan = Penerimaan::create([
                'kode_penerimaan' => $kodeToUse,
                'main_category_id' => $request->main_category_id,
                'tax_category_id' => $request->tax_category_id,
                'nomor_po' => $request->nomor_po,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tanggal_jatuh_tempo' => $request->metode_pembayaran == 'Jatuh Tempo' ? $request->tanggal_jatuh_tempo : null,
                'total_harga' => 0,
                'status' => 'Unlocated',
                'catatan' => $request->catatan,
                'lokasi_id' => 1,
            ]);

            return response()->json([
                'success' => true,
                'penerimaan_id' => $penerimaan->id,
                'kode_penerimaan' => $penerimaan->kode_penerimaan,
                'message' => 'Header penerimaan berhasil dibuat'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store batch details (AJAX)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeBatchDetails(Request $request, $id)
    {
        // Cek permission via route middleware (permission:warehouse.view), tidak perlu superadmin
        // karena method ini dipanggil saat create maupun edit

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.satuan_id' => 'required|exists:satuans,id',
            'items.*.harga_hpp' => 'required|numeric|min:0',
            'items.*.diskon_persen_1' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_persen_2' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_persen_3' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_persen_4' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_persen_5' => 'nullable|numeric|min:0|max:100',
            'items.*.diskon_nominal_1' => 'nullable|numeric|min:0',
            'items.*.diskon_nominal_2' => 'nullable|numeric|min:0',
            'items.*.diskon_nominal_3' => 'nullable|numeric|min:0',
            'items.*.diskon_nominal_4' => 'nullable|numeric|min:0',
            'items.*.diskon_nominal_5' => 'nullable|numeric|min:0',
            'items.*.is_free' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $penerimaan = Penerimaan::findOrFail($id);

            // Cek apakah ada WarehouseStock dari penerimaan ini yang sudah digunakan
            // di barang_keluar, order_items, atau offline_sale_items sebelum dihapus
            $existingDetailIds = PenerimaanDetail::where('penerimaan_id', $penerimaan->id)
                ->pluck('id')
                ->toArray();

            if (!empty($existingDetailIds)) {
                $existingStockIds = WarehouseStock::whereIn('penerimaan_detail_id', $existingDetailIds)
                    ->pluck('id')
                    ->toArray();

                $hasBarangKeluar = !empty($existingStockIds) && BarangKeluar::whereIn('warehouse_stock_id', $existingStockIds)->exists();
                $hasOrderItem = !empty($existingStockIds) && OrderItem::whereIn('warehouse_stock_id', $existingStockIds)->exists();
                $hasOfflineSaleItem = !empty($existingStockIds) && OfflineSaleItem::whereIn('warehouse_stock_id', $existingStockIds)->exists();

                if ($hasBarangKeluar || $hasOrderItem || $hasOfflineSaleItem) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Penerimaan tidak dapat disimpan karena stok sudah digunakan dalam transaksi penjualan.'
                    ], 409);
                }
            }

            // Get all detail IDs that will be deleted
            $detailIds = PenerimaanDetail::where('penerimaan_id', $penerimaan->id)
                ->pluck('id')
                ->toArray();

            // Hapus WarehouseStock terkait (bukan hanya set null)
            // karena detail akan dihapus dan dibuat ulang
            if (!empty($detailIds)) {
                WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->delete();
            }

            // Hapus detail lama jika ada (untuk create dan edit - clear-details sudah dipanggil sebelumnya untuk edit tapi untuk safety tetap hapus)
            PenerimaanDetail::where('penerimaan_id', $penerimaan->id)->delete();

            $savedCount = 0;
            foreach ($request->items as $detail) {
                $isFree = isset($detail['is_free']) && $detail['is_free'] == 1;
                $harga = $isFree ? 0 : NumberFormatter::formatDecimal($detail['harga_hpp']);

                $subtotal = 0;
                if (!$isFree) {
                    $qty = NumberFormatter::formatDecimal($detail['qty']);
                    $subtotal = NumberFormatter::multiplyDecimal($qty, $harga);

                    // Hitung diskon bertingkat (cascading discounts)
                    for ($i = 1; $i <= 5; $i++) {
                        $diskonPersen = isset($detail["diskon_persen_$i"]) ? NumberFormatter::formatDecimal($detail["diskon_persen_$i"]) : 0;
                        $diskonNominal = isset($detail["diskon_nominal_$i"]) ? NumberFormatter::formatDecimal($detail["diskon_nominal_$i"]) : 0;

                        if ($diskonPersen > 0) {
                            $potongan = NumberFormatter::percentageOf($subtotal, $diskonPersen);
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $potongan);
                        } elseif ($diskonNominal > 0) {
                            $subtotal = NumberFormatter::subtractDecimal($subtotal, $diskonNominal);
                        }
                    }
                }

                $penerimaanDetail = PenerimaanDetail::create([
                    'penerimaan_id' => $penerimaan->id,
                    'product_id' => $detail['barang_id'],
                    'qty' => NumberFormatter::formatDecimal($detail['qty']),
                    'satuan_id' => $detail['satuan_id'],
                    'harga_hpp' => $harga,
                    'diskon_persen_1' => NumberFormatter::formatDecimal($detail['diskon_persen_1'] ?? 0),
                    'diskon_nominal_1' => NumberFormatter::formatDecimal($detail['diskon_nominal_1'] ?? 0),
                    'diskon_persen_2' => NumberFormatter::formatDecimal($detail['diskon_persen_2'] ?? 0),
                    'diskon_nominal_2' => NumberFormatter::formatDecimal($detail['diskon_nominal_2'] ?? 0),
                    'diskon_persen_3' => NumberFormatter::formatDecimal($detail['diskon_persen_3'] ?? 0),
                    'diskon_nominal_3' => NumberFormatter::formatDecimal($detail['diskon_nominal_3'] ?? 0),
                    'diskon_persen_4' => NumberFormatter::formatDecimal($detail['diskon_persen_4'] ?? 0),
                    'diskon_nominal_4' => NumberFormatter::formatDecimal($detail['diskon_nominal_4'] ?? 0),
                    'diskon_persen_5' => NumberFormatter::formatDecimal($detail['diskon_persen_5'] ?? 0),
                    'diskon_nominal_5' => NumberFormatter::formatDecimal($detail['diskon_nominal_5'] ?? 0),
                    'is_free' => $isFree,
                    'subtotal' => $subtotal,
                    'catatan' => $detail['catatan'] ?? null,
                ]);
                $savedCount++;
            }

            // Recalculate total
            $penerimaan->recalculateTotalHarga();

            DB::commit();

            return response()->json([
                'success' => true,
                'saved_count' => $savedCount,
                'total_harga' => $penerimaan->fresh()->total_harga,
                'message' => "Berhasil menyimpan {$savedCount} item detail"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize penerimaan (AJAX)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function finalizePenerimaan(Request $request, $id)
    {
        try {
            $penerimaan = Penerimaan::findOrFail($id);

            // Log activity
            $this->logActivity($penerimaan->id, 'create', 'Membuat penerimaan baru', [
                'kode' => $penerimaan->kode_penerimaan,
                'total_items' => $penerimaan->details()->count(),
                'total_harga' => $penerimaan->total_harga,
            ]);

            return response()->json([
                'success' => true,
                'penerimaan_id' => $penerimaan->id,
                'message' => 'Penerimaan berhasil disimpan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear details penerimaan (AJAX)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearDetails(Request $request, $id)
    {
        // Hanya superadmin
        if (!Auth::user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya superadmin yang dapat mengedit penerimaan.'], 403);
        }

        try {
            DB::beginTransaction();
            
            $penerimaan = Penerimaan::findOrFail($id);

            // Get all detail IDs that will be deleted
            $detailIds = PenerimaanDetail::where('penerimaan_id', $penerimaan->id)
                ->pluck('id')
                ->toArray();

            // Safety check: cek apakah warehouse_stock sudah digunakan di transaksi penjualan
            $warehouseStockIds = WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->pluck('id')->toArray();
            
            $hasBarangKeluar = !empty($warehouseStockIds) && BarangKeluar::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOrderItem = !empty($warehouseStockIds) && OrderItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOfflineSaleItem = !empty($warehouseStockIds) && OfflineSaleItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            
            if ($hasBarangKeluar || $hasOrderItem || $hasOfflineSaleItem) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stok dari penerimaan ini sudah digunakan dalam transaksi penjualan. Tidak dapat diedit.'
                ], 400);
            }

            // Hapus WarehouseStock terkait (bukan hanya set null)
            // karena detail akan dihapus dan dibuat ulang
            if (!empty($detailIds)) {
                WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->delete();
            }

            // Hapus semua detail
            PenerimaanDetail::where('penerimaan_id', $penerimaan->id)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Detail penerimaan berhasil dibersihkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error clearing penerimaan details', [
                'penerimaan_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update header penerimaan (AJAX)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateHeader(Request $request, $id)
    {
        // Hanya superadmin
        if (!Auth::user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya superadmin yang dapat mengedit penerimaan.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'main_category_id' => 'required',
            'tax_category_id' => 'required',
            'nomor_po' => 'required',
            'tanggal_penerimaan' => 'required|date',
            'metode_pembayaran' => 'required|in:Cash,Jatuh Tempo',
            'tanggal_jatuh_tempo' => 'required_if:metode_pembayaran,Jatuh Tempo|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $penerimaan = Penerimaan::findOrFail($id);

            // Cek apakah ada WarehouseStock dari penerimaan ini yang sudah digunakan
            // di barang_keluar, order_items, atau offline_sale_items
            $detailIds = $penerimaan->details()->pluck('id')->toArray();
            $warehouseStockIds = WarehouseStock::whereIn('penerimaan_detail_id', $detailIds)->pluck('id')->toArray();
            
            $hasBarangKeluar = !empty($warehouseStockIds) && BarangKeluar::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOrderItem = !empty($warehouseStockIds) && OrderItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            $hasOfflineSaleItem = !empty($warehouseStockIds) && OfflineSaleItem::whereIn('warehouse_stock_id', $warehouseStockIds)->exists();
            
            if ($hasBarangKeluar || $hasOrderItem || $hasOfflineSaleItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penerimaan tidak dapat diedit karena stok sudah digunakan dalam transaksi penjualan.'
                ], 400);
            }

            // Store old data for logging
            $oldData = [
                'nomor_po' => $penerimaan->nomor_po,
                'tanggal_penerimaan' => $penerimaan->tanggal_penerimaan,
                'metode_pembayaran' => $penerimaan->metode_pembayaran,
                'tax_category_id' => $penerimaan->tax_category_id,
                'total_harga' => $penerimaan->total_harga,
                'item_count' => $penerimaan->details()->count()
            ];

            // Update data penerimaan
            $penerimaan->update([
                'main_category_id' => $request->main_category_id,
                'tax_category_id' => $request->tax_category_id,
                'nomor_po' => $request->nomor_po,
                'tanggal_penerimaan' => $request->tanggal_penerimaan,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tanggal_jatuh_tempo' => $request->metode_pembayaran == 'Jatuh Tempo' ? $request->tanggal_jatuh_tempo : null,
                'catatan' => $request->catatan,
            ]);

            return response()->json([
                'success' => true,
                'penerimaan_id' => $penerimaan->id,
                'old_data' => $oldData,
                'message' => 'Header penerimaan berhasil diupdate'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize penerimaan (untuk update - AJAX)
     * Method ini dipanggil untuk finalize update
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function finalizePenerimaanUpdate(Request $request, $id)
    {
        // Hanya superadmin
        if (!Auth::user()->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Hanya superadmin yang dapat mengedit penerimaan.'], 403);
        }

        try {
            $penerimaan = Penerimaan::findOrFail($id);

            // Recalculate total
            $newTotalHarga = $penerimaan->recalculateTotalHarga();

            // Kembalikan status ke Unlocated agar items muncul di daftar Unlocated
            // dan bisa di-transfer ulang ke gudang
            $penerimaan->update(['status' => 'Unlocated']);

            // Log activity
            $oldData = $request->old_data ?? [];
            $newData = [
                'nomor_po' => $penerimaan->nomor_po,
                'tanggal_penerimaan' => $penerimaan->tanggal_penerimaan,
                'metode_pembayaran' => $penerimaan->metode_pembayaran,
                'total_harga' => $newTotalHarga,
                'item_count' => $penerimaan->details()->count()
            ];

            $this->logActivity($penerimaan->id, 'update', 'Mengubah data penerimaan', [
                'old_data' => $oldData,
                'new_data' => $newData
            ]);

            return response()->json([
                'success' => true,
                'penerimaan_id' => $penerimaan->id,
                'message' => 'Update penerimaan berhasil disimpan. Status dikembalikan ke Unlocated.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log activity for a penerimaan
     *
     * @param int $penerimaanId
     * @param string $activityType
     * @param string $description
     * @param array|null $details
     * @return void
     */
    private function logActivity($penerimaanId, $activityType, $description, $details = null)
    {
        PenerimaanActivity::create([
            'penerimaan_id' => $penerimaanId,
            'user_id' => Auth::id(),
            'activity_type' => $activityType,
            'description' => $description,
            'details' => $details
        ]);
    }

    private function generateUniqueKodePenerimaan(): string
    {
        $prefix = 'PNR-';
        $lastPenerimaan = Penerimaan::withoutGlobalScopes()
            ->where('kode_penerimaan', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = 0;
        if ($lastPenerimaan && preg_match('/^PNR-(\d+)$/', (string) $lastPenerimaan->kode_penerimaan, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        $candidate = $lastNumber + 1;
        while (true) {
            $kode = $prefix . str_pad((string) $candidate, 6, '0', STR_PAD_LEFT);
            if (!Penerimaan::withoutGlobalScopes()->where('kode_penerimaan', $kode)->exists()) {
                return $kode;
            }
            $candidate++;
        }
    }

    /**
     * Get price and discount history for a product
     *
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPriceHistory($productId)
    {
        try {
            // Get product info
            $product = Product::findOrFail($productId);
            
            // Get price history from penerimaan_detail
            $priceHistory = PenerimaanDetail::with(['penerimaan' => function($query) {
                    $query->select('id', 'kode_penerimaan', 'tanggal_penerimaan', 'nomor_po')
                          ->orderBy('tanggal_penerimaan', 'desc');
                }])
                ->where('product_id', $productId)
                ->whereNotNull('harga_hpp')
                ->where('harga_hpp', '>', 0)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get([
                    'penerimaan_id',
                    'harga_hpp',
                    'diskon_persen_1',
                    'diskon_persen_2',
                    'diskon_persen_3',
                    'diskon_persen_4',
                    'diskon_persen_5',
                    'diskon_nominal_1',
                    'diskon_nominal_2',
                    'diskon_nominal_3',
                    'diskon_nominal_4',
                    'diskon_nominal_5',
                    'is_free',
                    'created_at'
                ]);

            // Format the history data
            $formattedHistory = $priceHistory->map(function($item) {
                // Calculate total discount percentage
                $totalDiscountPercentage = 0;
                $discountDetails = [];
                
                for ($i = 1; $i <= 5; $i++) {
                    $persen = $item->{"diskon_persen_$i"};
                    $nominal = $item->{"diskon_nominal_$i"};
                    
                    if ($persen > 0) {
                        $totalDiscountPercentage += $persen;
                        $discountDetails[] = "Diskon $i: {$persen}%";
                    } elseif ($nominal > 0) {
                        $discountDetails[] = "Diskon $i: Rp " . number_format($nominal, 0, ',', '.');
                    }
                }

                return [
                    'tanggal' => $item->penerimaan->tanggal_penerimaan,
                    'kode_penerimaan' => $item->penerimaan->kode_penerimaan,
                    'nomor_po' => $item->penerimaan->nomor_po,
                    'harga' => $item->harga_hpp,
                    'harga_formatted' => 'Rp ' . number_format($item->harga_hpp, 0, ',', '.'),
                    'diskon_persen_total' => $totalDiscountPercentage,
                    'diskon_detail' => $discountDetails,
                    'is_free' => $item->is_free,
                    'tanggal_formatted' => \Carbon\Carbon::parse($item->penerimaan->tanggal_penerimaan)->format('d/m/Y')
                ];
            });

            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku
                ],
                'history' => $formattedHistory
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
