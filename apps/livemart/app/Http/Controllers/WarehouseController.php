<?php

namespace App\Http\Controllers;

use App\Traits\QueueExport;
use App\Models\Lokasi;
use App\Models\Penerimaan;
use App\Models\PenerimaanDetail;
use App\Models\WarehouseStock;
use App\Models\TaxCategory;
use App\Models\MainCategory;
use App\Models\Brand;
use App\Models\SubBrand;
use App\Models\ProductCategory;
use App\Models\ProductType;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Exports\WarehouseExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseController extends Controller
{
    use QueueExport;

    public function index(Request $request)
    {
        // Query dasar untuk mendapatkan penerimaan detail dengan status Unlocated
        $query = PenerimaanDetail::whereHas('penerimaan', function ($query) {
            $query->where('status', 'Unlocated');
        });

        // Tambahkan filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('product', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('penerimaan', function($q) use ($search) {
                    $q->where('kode_penerimaan', 'like', "%{$search}%")
                      ->orWhere('nomor_po', 'like', "%{$search}%");
                });
            });
        }

        // Filter berdasarkan kode penerimaan
        if ($request->filled('kode_penerimaan')) {
            $query->whereHas('penerimaan', function($q) use ($request) {
                $q->where('kode_penerimaan', 'like', "%{$request->kode_penerimaan}%")
                  ->orWhere('nomor_po', 'like', "%{$request->kode_penerimaan}%");
            });
        }

        // Filter berdasarkan nama produk
        if ($request->filled('nama_produk')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->nama_produk}%");
            });
        }

        // Filter berdasarkan tanggal penerimaan
        if ($request->filled('tanggal_mulai')) {
            $query->whereHas('penerimaan', function($q) use ($request) {
                $q->whereDate('tanggal_penerimaan', '>=', $request->tanggal_mulai);
            });
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereHas('penerimaan', function($q) use ($request) {
                $q->whereDate('tanggal_penerimaan', '<=', $request->tanggal_akhir);
            });
        }

        // Load relasi yang dibutuhkan dan tambahkan subquery untuk menghitung remaining quantity
        $query->with(['penerimaan', 'product', 'satuan'])
              ->select('penerimaan_detail.*')
              ->selectRaw('(penerimaan_detail.qty - IFNULL((SELECT SUM(ws.qty) FROM warehouse_stock ws WHERE ws.penerimaan_detail_id = penerimaan_detail.id), 0)) as remaining_qty')
              ->havingRaw('remaining_qty > 0')
              ->orderBy('penerimaan_detail.id', 'desc');
        
        // Ambil data dengan pagination
        $unlocatedItems = $query->paginate(10)->withQueryString();

        return view('warehouse.index', compact('unlocatedItems'));
    }

    public function create(Request $request)
    {
        $gudangALokasi = Lokasi::where('kode', 'GUDANG_A')->first();

        // Query dasar untuk mendapatkan penerimaan detail
        $query = PenerimaanDetail::whereHas('penerimaan', function ($query) {
            $query->where('status', 'Unlocated');
        })->with('product', 'satuan', 'penerimaan');

        // Jika ada item spesifik yang dipilih
        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        $penerimaanDetails = $query->get();

        // Siapkan item yang akan ditampilkan dengan menghitung sisa
        $items = [];
        foreach ($penerimaanDetails as $detail) {
            // Hitung total yang sudah masuk ke warehouse
            $warehouseTotal = WarehouseStock::where('penerimaan_detail_id', $detail->id)->sum('qty');

            // Hitung sisa yang belum masuk ke warehouse
            $remainingQty = $detail->qty - $warehouseTotal;

            // Jika masih ada sisa, tambahkan ke daftar
            if ($remainingQty > 0) {
                $detail->remaining_qty = $remainingQty;
                $items[] = $detail;
            }
        }

        return view('warehouse.create', compact('items', 'gudangALokasi'));
    }

    public function store(Request $request)
    {
        \Log::info('WarehouseController@store - Incoming request', $request->all());

        // Filter hanya item yang dicentang
        $filteredItems = array_filter($request->input('items', []), function($item) {
            return isset($item['selected']);
        });
        $request->merge(['items' => $filteredItems]);

        $gudangALokasi = Lokasi::where('kode', 'GUDANG_A')->first();
        if (! $gudangALokasi) {
            return back()->with('error', 'Lokasi Gudang A tidak ditemukan');
        }

        $validatedData = $request->validate([
            'items' => 'required|array',
            'items.*.penerimaan_detail_id' => 'required|exists:penerimaan_detail,id',
            'items.*.expired_date' => 'nullable|date',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.selected' => 'nullable',
        ]);

        DB::beginTransaction();
        try {
            $processedPenerimaanIds = [];

            foreach ($validatedData['items'] as $itemId => $itemData) {
                // Pastikan item dipilih
                if (! isset($itemData['selected'])) {
                    continue;
                }

                $penerimaanDetailId = $itemData['penerimaan_detail_id'];
                $penerimaanDetail = PenerimaanDetail::findOrFail($penerimaanDetailId);
                $penerimaan = $penerimaanDetail->penerimaan;

                // Tambahkan ID penerimaan ke array untuk diproses nanti
                if (! in_array($penerimaan->id, $processedPenerimaanIds)) {
                    $processedPenerimaanIds[] = $penerimaan->id;
                }

                // Hitung total yang sudah masuk ke warehouse
                $warehouseTotal = WarehouseStock::where('penerimaan_detail_id', $penerimaanDetailId)->sum('qty');

                // Hitung sisa yang belum masuk ke warehouse
                $remainingQty = $penerimaanDetail->qty - $warehouseTotal;

                // Periksa apakah jumlah yang dipindahkan tidak melebihi sisa
                if ($itemData['qty'] > $remainingQty) {
                    throw new \Exception('Jumlah yang dipindahkan untuk '.$penerimaanDetail->product->name.' melebihi stok yang tersedia');
                }

                // Ambil tax_id dari penerimaan
                $taxId = $penerimaan->tax_category_id;

                // Buat record baru di warehouse_stock
                WarehouseStock::create([
                    'product_id' => $penerimaanDetail->product_id,
                    'lokasi_id' => $gudangALokasi->id, // ID Gudang A (2)
                    'penerimaan_detail_id' => $penerimaanDetailId,
                    'tax_id' => $taxId,
                    'qty' => $itemData['qty'],
                    'expired_date' => $itemData['expired_date'] ?? null,
                    'status_ed' => 'aman', // Default 'aman' karena enum
                    'catatan' => '',
                    'source_type' => 'penerimaan',
                    'source_id' => $penerimaanDetail->penerimaan_id,
                    'source_date' => $penerimaanDetail->penerimaan->tanggal_penerimaan ?? now(),
                ]);
            }

            // Periksa apakah perlu update status penerimaan
            foreach ($processedPenerimaanIds as $penerimaanId) {
                $penerimaan = Penerimaan::find($penerimaanId);

                // Cek semua item dalam penerimaan
                $allItemsAllocated = true;
                foreach ($penerimaan->details as $detail) {
                    $warehouseTotal = WarehouseStock::where('penerimaan_detail_id', $detail->id)->sum('qty');
                    $remainingQty = $detail->qty - $warehouseTotal;

                    if ($remainingQty > 0) {
                        $allItemsAllocated = false;
                        break;
                    }
                }

                // Jika semua item sudah dipindahkan sepenuhnya
                if ($allItemsAllocated) {
                    $penerimaan->update([
                        'status' => 'Located',
                        'lokasi_id' => $gudangALokasi->id,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('warehouse.index')->with('success', 'Barang berhasil dipindahkan ke Gudang A');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal memindahkan barang: '.$e->getMessage());
        }
    }

    public function stockList(Request $request)
    {
        // Query dasar
        $query = WarehouseStock::with([
                'product', 
                'product.mainCategory',
                'product.brand',
                'product.subBrand',
                'product.productCategory',
                'product.productType',
                'product.productSize',
                'product.productVariant',
                'lokasi', 
                'tax', 
                'penerimaanDetail', 
                'penerimaanDetail.satuan'
            ]);
            
        // Filter berdasarkan pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        
        // Filter berdasarkan SKU
        if ($request->filled('sku')) {
            $sku = $request->sku;
            $query->whereHas('product', function($q) use ($sku) {
                $q->where('sku', 'like', "%{$sku}%");
            });
        }
        
        // Filter berdasarkan status ED
        if ($request->filled('status_ed')) {
            if ($request->status_ed === 'tidak_ada_ed') {
                $query->whereNull('expired_date');
            } elseif ($request->status_ed === 'kadaluarsa') {
                $query->whereDate('expired_date', '<', now());
            } elseif ($request->status_ed === 'kurang_dari_3_bulan') {
                $query->whereDate('expired_date', '>=', now())
                      ->whereDate('expired_date', '<=', now()->addDays(90));
            } elseif ($request->status_ed === 'kurang_dari_6_bulan') {
                $query->whereDate('expired_date', '>', now()->addDays(90))
                      ->whereDate('expired_date', '<=', now()->addDays(180));
            } elseif ($request->status_ed === 'kurang_dari_1_tahun') {
                $query->whereDate('expired_date', '>', now()->addDays(180))
                      ->whereDate('expired_date', '<=', now()->addDays(365));
            } elseif ($request->status_ed === 'lebih_dari_1_tahun') {
                $query->whereDate('expired_date', '>', now()->addDays(365));
            }
        }
        
        // Filter berdasarkan tax category
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        
        // Filter berdasarkan main category
        if ($request->filled('main_category_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('main_category_id', $request->main_category_id);
            });
        }

        // Filter berdasarkan brand
        if ($request->filled('brand_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('brand_id', $request->brand_id);
            });
        }

        // Filter berdasarkan sub brand
        if ($request->filled('sub_brand_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('sub_brand_id', $request->sub_brand_id);
            });
        }

        // Filter berdasarkan product category
        if ($request->filled('product_category_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_category_id', $request->product_category_id);
            });
        }

        // Filter berdasarkan product type
        if ($request->filled('product_type_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        // Filter berdasarkan product size
        if ($request->filled('product_size_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_size_id', $request->product_size_id);
            });
        }

        // Filter berdasarkan product variant
        if ($request->filled('product_variant_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_variant_id', $request->product_variant_id);
            });
        }
        
        // Filter barang free
        if ($request->filled('is_free')) {
            if ($request->is_free == 1) {
                $query->whereHas('penerimaanDetail', function($q) {
                    $q->where('is_free', true);
                });
            } elseif ($request->is_free == 0) {
                $query->whereHas('penerimaanDetail', function($q) {
                    $q->where('is_free', false);
                });
            }
        }
        
        // Sorting
        $query->orderBy('product_id')
              ->orderBy('created_at');
        
        // Clone query untuk export jika diperlukan
        $exportQuery = clone $query;
            
        // Ambil semua hasil filter untuk summary card (tanpa pagination)
        $filteredStocks = $query->get();

        // Proses status ED untuk masing-masing item pada $filteredStocks (summary card)
        $today = now();
        foreach ($filteredStocks as $stock) {
            if ($stock->expired_date) {
                $diffInDays = $today->diffInDays($stock->expired_date, false);

                if ($diffInDays < 0) {
                    $stock->ed_status = 'kadaluarsa';
                } elseif ($diffInDays <= 90) {
                    $stock->ed_status = 'kurang_dari_3_bulan';
                } elseif ($diffInDays <= 180) {
                    $stock->ed_status = 'kurang_dari_6_bulan';
                } elseif ($diffInDays <= 365) {
                    $stock->ed_status = 'kurang_dari_1_tahun';
                } else {
                    $stock->ed_status = 'lebih_dari_1_tahun';
                }
            } else {
                $stock->ed_status = 'tidak_ada_ed';
            }
            $stock->is_free = $stock->penerimaanDetail && $stock->penerimaanDetail->is_free;
        }
        
        // Clone query lagi untuk pagination
        $query = clone $exportQuery;
        // Paginate hasil untuk tabel utama
        $stocks = $query->paginate(10)->withQueryString();
        
        // Proses status ED untuk masing-masing item pada $stocks (tabel utama)
        foreach ($stocks as $stock) {
            if ($stock->expired_date) {
                $diffInDays = $today->diffInDays($stock->expired_date, false);

                if ($diffInDays < 0) {
                    $stock->ed_status = 'kadaluarsa';
                } elseif ($diffInDays <= 90) {
                    $stock->ed_status = 'kurang_dari_3_bulan';
                } elseif ($diffInDays <= 180) {
                    $stock->ed_status = 'kurang_dari_6_bulan';
                } elseif ($diffInDays <= 365) {
                    $stock->ed_status = 'kurang_dari_1_tahun';
                } else {
                    $stock->ed_status = 'lebih_dari_1_tahun';
                }
            } else {
                $stock->ed_status = 'tidak_ada_ed';
            }
            $stock->is_free = $stock->penerimaanDetail && $stock->penerimaanDetail->is_free;
        }

        // Dapatkan nilai pajak unik untuk filter
        $taxCategories = TaxCategory::all();
        
        // Dapatkan kategori utama untuk filter
        $mainCategories = MainCategory::where('is_active', true)->get();

        // Dapatkan data untuk filter tambahan
        $brands = Brand::where('is_active', true)->get();
        $subBrands = SubBrand::where('is_active', true)->get();
        $productCategories = ProductCategory::where('is_active', true)->get();
        $productTypes = ProductType::where('is_active', true)->get();
        $productSizes = ProductSize::where('is_active', true)->get();
        $productVariants = ProductVariant::where('is_active', true)->get();

        return view('warehouse.stock-list', compact(
            'stocks', 
            'filteredStocks',
            'taxCategories', 
            'mainCategories', 
            'brands',
            'subBrands',
            'productCategories',
            'productTypes',
            'productSizes',
            'productVariants',
            'request',
            'exportQuery'
        ));
    }

    public function exportExcel(Request $request)
    {
        // Query dasar sama dengan stockList
        $query = WarehouseStock::with([
                'product', 
                'product.mainCategory',
                'product.brand',
                'product.subBrand',
                'product.productCategory',
                'product.productType',
                'product.productSize',
                'product.productVariant',
                'lokasi', 
                'tax', 
                'penerimaanDetail', 
                'penerimaanDetail.satuan'
            ]);
            
        // Filter berdasarkan pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }
        
        // Filter berdasarkan SKU
        if ($request->filled('sku')) {
            $sku = $request->sku;
            $query->whereHas('product', function($q) use ($sku) {
                $q->where('sku', 'like', "%{$sku}%");
            });
        }
        
        // Filter berdasarkan status ED
        if ($request->filled('status_ed')) {
            if ($request->status_ed === 'tidak_ada_ed') {
                $query->whereNull('expired_date');
            } elseif ($request->status_ed === 'kadaluarsa') {
                $query->whereDate('expired_date', '<', now());
            } elseif ($request->status_ed === 'kurang_dari_3_bulan') {
                $query->whereDate('expired_date', '>=', now())
                      ->whereDate('expired_date', '<=', now()->addDays(90));
            } elseif ($request->status_ed === 'kurang_dari_6_bulan') {
                $query->whereDate('expired_date', '>', now()->addDays(90))
                      ->whereDate('expired_date', '<=', now()->addDays(180));
            } elseif ($request->status_ed === 'kurang_dari_1_tahun') {
                $query->whereDate('expired_date', '>', now()->addDays(180))
                      ->whereDate('expired_date', '<=', now()->addDays(365));
            } elseif ($request->status_ed === 'lebih_dari_1_tahun') {
                $query->whereDate('expired_date', '>', now()->addDays(365));
            }
        }
        
        // Filter berdasarkan tax category
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        
        // Filter berdasarkan main category
        if ($request->filled('main_category_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('main_category_id', $request->main_category_id);
            });
        }

        // Filter berdasarkan brand
        if ($request->filled('brand_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('brand_id', $request->brand_id);
            });
        }

        // Filter berdasarkan sub brand
        if ($request->filled('sub_brand_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('sub_brand_id', $request->sub_brand_id);
            });
        }

        // Filter berdasarkan product category
        if ($request->filled('product_category_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_category_id', $request->product_category_id);
            });
        }

        // Filter berdasarkan product type
        if ($request->filled('product_type_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        // Filter berdasarkan product size
        if ($request->filled('product_size_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_size_id', $request->product_size_id);
            });
        }

        // Filter berdasarkan product variant
        if ($request->filled('product_variant_id')) {
            $query->whereHas('product', function($q) use ($request) {
                $q->where('product_variant_id', $request->product_variant_id);
            });
        }
        
        // Filter barang free
        if ($request->filled('is_free')) {
            if ($request->is_free == 1) {
                $query->whereHas('penerimaanDetail', function($q) {
                    $q->where('is_free', true);
                });
            } elseif ($request->is_free == 0) {
                $query->whereHas('penerimaanDetail', function($q) {
                    $q->where('is_free', false);
                });
            }
        }
        
        // Sorting
        $query->orderBy('product_id')
              ->orderBy('created_at');
        
        // Ambil semua data (tanpa pagination) untuk export
        $stocks = $query->get();
        
        // Tambahkan status ED ke setiap item dan flag is_free
        $today = now();
        foreach ($stocks as $stock) {
            if ($stock->expired_date) {
                $diffInDays = $today->diffInDays($stock->expired_date, false);

                if ($diffInDays < 0) {
                    $stock->ed_status = 'Kadaluarsa';
                } elseif ($diffInDays <= 90) {
                    $stock->ed_status = '< 3 Bulan';
                } elseif ($diffInDays <= 180) {
                    $stock->ed_status = '< 6 Bulan';
                } elseif ($diffInDays <= 365) {
                    $stock->ed_status = '< 1 Tahun';
                } else {
                    $stock->ed_status = '> 1 Tahun';
                }
            } else {
                $stock->ed_status = 'Tanpa ED';
            }
            
            // Set the is_free flag
            $stock->is_free = $stock->penerimaanDetail && $stock->penerimaanDetail->is_free;
        }
        
        // Generate nama file
        $filename = 'stok_barang_'.date('Y-m-d').'.xlsx';
        
        // Generate Excel file using the existing StockExport class
        return $this->queueExcelExport(new \App\Exports\StockExport($stocks), $filename, 'Export Stok Barang');
    }

    public function export(Request $request)
    {
        return $this->queueExcelExport(new WarehouseExport($request), 'unlocated_items_'.date('Y-m-d').'.xlsx', 'Export Warehouse Unlocated Items');
    }
}
