<?php

namespace App\Http\Controllers;

use App\Traits\QueueExport;
use App\Exports\StockExport;
use App\Exports\StockMutationExport;
use App\Models\BarangKeluar;
use App\Models\Brand;
use App\Models\Lokasi;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSize;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Models\SubBrand;
use App\Models\TaxCategory;
use App\Models\WarehouseStock;
use App\Queries\Analytics\Stock\StockAnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseStockController extends Controller
{
    use QueueExport;

    public function list(Request $request)
    {
        $query = WarehouseStock::with([
            'product.mainCategory',
            'product.brand',
            'product.subBrand',
            'product.productCategory',
            'product.productType',
            'product.productSize',
            'product.productVariant',
            'penerimaanDetail.satuan',
            'lokasi',
            'tax',
        ]);

        // Show only non-damaged items by default
        $query->where('is_damaged', false);

        // Search filter
        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            });
        }

        // SKU filter
        if ($request->sku) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sku', 'like', '%'.$request->sku.'%');
            });
        }

        // Status ED filter
        if ($request->status_ed) {
            switch ($request->status_ed) {
                case 'kadaluarsa':
                    $query->where('expired_date', '<', now());
                    break;
                case 'kurang_dari_3_bulan':
                    $query->whereBetween('expired_date', [now(), now()->addMonths(3)]);
                    break;
                case 'kurang_dari_6_bulan':
                    $query->whereBetween('expired_date', [now()->addMonths(3), now()->addMonths(6)]);
                    break;
                case 'kurang_dari_1_tahun':
                    $query->whereBetween('expired_date', [now()->addMonths(6), now()->addYear()]);
                    break;
                case 'lebih_dari_1_tahun':
                    $query->where('expired_date', '>', now()->addYear());
                    break;
                case 'tidak_ada_ed':
                    $query->whereNull('expired_date');
                    break;
            }
        }

        // Tax filter - only apply if explicitly provided
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        // If no tax_id filter, include ALL tax_id values (both PKP and non-PKP)

        // Free item filter
        if ($request->is_free !== null && $request->is_free !== '') {
            $query->whereHas('penerimaanDetail', function ($q) use ($request) {
                $q->where('is_free', (bool) $request->is_free);
            });
        }

        // Advanced filters
        if ($request->brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('brand_id', $request->brand_id);
            });
        }

        if ($request->sub_brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sub_brand_id', $request->sub_brand_id);
            });
        }

        if ($request->product_category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_category_id', $request->product_category_id);
            });
        }

        if ($request->product_type_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        if ($request->product_size_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_size_id', $request->product_size_id);
            });
        }

        if ($request->product_variant_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_variant_id', $request->product_variant_id);
            });
        }

        // Clone query for summary cards (without pagination)
        $summaryQuery = clone $query;
        $filteredStocks = $summaryQuery->get();

        // Process ED status for summary cards
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

        // Calculate correct total quantity using mutation logic (same as calculateRunningBalance)
        // Group by product_id to avoid double counting
        $productIds = $filteredStocks->pluck('product_id')->unique();
        $correctTotalQty = 0;
        foreach ($productIds as $productId) {
            $correctTotalQty += $this->calculateRunningBalance($productId);
        }

        // Paginate for table display
        $stocks = $query->paginate(25)->withQueryString();

        // Process ED status for paginated results
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

        return view('warehouse.stock-list', [
            'stocks' => $stocks,
            'filteredStocks' => $filteredStocks,
            'correctTotalQty' => $correctTotalQty,
            'brands' => Brand::where('is_active', true)->get(),
            'subBrands' => SubBrand::where('is_active', true)->get(),
            'productCategories' => ProductCategory::where('is_active', true)->get(),
            'productTypes' => ProductType::where('is_active', true)->get(),
            'productSizes' => ProductSize::where('is_active', true)->get(),
            'productVariants' => ProductVariant::where('is_active', true)->get(),
            'taxCategories' => TaxCategory::where('is_active', true)->get(),
            'isDamaged' => false,
        ]);
    }

    /**
     * Display the damaged items in the warehouse
     */
    public function damagedList(Request $request)
    {
        $query = WarehouseStock::with([
            'product.mainCategory',
            'product.brand',
            'product.subBrand',
            'product.productCategory',
            'product.productType',
            'product.productSize',
            'product.productVariant',
            'penerimaanDetail.satuan',
            'lokasi',
            'tax',
        ]);

        // Show only damaged items
        $query->where('is_damaged', true);

        // Search filter
        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            });
        }

        // SKU filter
        if ($request->sku) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sku', 'like', '%'.$request->sku.'%');
            });
        }

        // Status ED filter
        if ($request->status_ed) {
            switch ($request->status_ed) {
                case 'kadaluarsa':
                    $query->where('expired_date', '<', now());
                    break;
                case 'kurang_dari_3_bulan':
                    $query->whereBetween('expired_date', [now(), now()->addMonths(3)]);
                    break;
                case 'kurang_dari_6_bulan':
                    $query->whereBetween('expired_date', [now()->addMonths(3), now()->addMonths(6)]);
                    break;
                case 'kurang_dari_1_tahun':
                    $query->whereBetween('expired_date', [now()->addMonths(6), now()->addYear()]);
                    break;
                case 'lebih_dari_1_tahun':
                    $query->where('expired_date', '>', now()->addYear());
                    break;
                case 'tidak_ada_ed':
                    $query->whereNull('expired_date');
                    break;
            }
        }

        // Tax filter - only apply if explicitly provided
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        // If no tax_id filter, include ALL tax_id values (both PKP and non-PKP)

        // Free item filter
        if ($request->is_free !== null && $request->is_free !== '') {
            $query->whereHas('penerimaanDetail', function ($q) use ($request) {
                $q->where('is_free', (bool) $request->is_free);
            });
        }

        // Advanced filters
        if ($request->brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('brand_id', $request->brand_id);
            });
        }

        if ($request->sub_brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sub_brand_id', $request->sub_brand_id);
            });
        }

        if ($request->product_category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_category_id', $request->product_category_id);
            });
        }

        if ($request->product_type_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        if ($request->product_size_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_size_id', $request->product_size_id);
            });
        }

        if ($request->product_variant_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_variant_id', $request->product_variant_id);
            });
        }

        // Clone query for summary cards (without pagination)
        $summaryQuery = clone $query;
        $filteredStocks = $summaryQuery->get();

        // Process ED status for summary cards
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

        // Calculate correct total quantity using mutation logic (same as calculateRunningBalance)
        // Group by product_id to avoid double counting
        $productIds = $filteredStocks->pluck('product_id')->unique();
        $correctTotalQty = 0;
        foreach ($productIds as $productId) {
            $correctTotalQty += $this->calculateRunningBalance($productId);
        }

        // Get all damaged stock with pagination
        $stocks = $query->paginate(25)->withQueryString();

        // Process ED status for paginated results
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

        // Get retur information
        $returPenjualanDetails = \App\Models\ReturPenjualanDetail::with(['returPenjualan', 'product'])
            ->where('kondisi', 'RUSAK')
            ->whereHas('returPenjualan', function ($q) {
                $q->where('status', 'selesai');
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get offline retur information
        $returOfflineDetails = \App\Models\ReturOfflineSaleDetail::with(['returOfflineSale', 'product'])
            ->where('kondisi', 'RUSAK')
            ->whereHas('returOfflineSale', function ($q) {
                $q->where('status', 'selesai');
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('warehouse.stock-list', [
            'stocks' => $stocks,
            'filteredStocks' => $filteredStocks,
            'correctTotalQty' => $correctTotalQty,
            'brands' => Brand::where('is_active', true)->get(),
            'subBrands' => SubBrand::where('is_active', true)->get(),
            'productCategories' => ProductCategory::where('is_active', true)->get(),
            'productTypes' => ProductType::where('is_active', true)->get(),
            'productSizes' => ProductSize::where('is_active', true)->get(),
            'productVariants' => ProductVariant::where('is_active', true)->get(),
            'taxCategories' => TaxCategory::where('is_active', true)->get(),
            'isDamaged' => true,
            'returPenjualanDetails' => $returPenjualanDetails,
            'returOfflineDetails' => $returOfflineDetails,
        ]);
    }

    public function export(Request $request)
    {
        // Query data dengan filter yang sama seperti method analytics
        $query = WarehouseStock::with([
            'product.mainCategory',
            'product.brand',
            'product.subBrand',
            'product.productCategory',
            'product.productType',
            'product.productSize',
            'product.productVariant',
            'penerimaanDetail.satuan',
            'penerimaanDetail.penerimaan',
            'lokasi',
            'tax',
        ]);

        // Filter based on damaged status - default to false (non-damaged) like analytics method
        if ($request->has('is_damaged')) {
            $query->where('is_damaged', $request->is_damaged ? true : false);
        } else {
            // Default: show only non-damaged items (same as analytics method)
            $query->where('is_damaged', false);
        }

        // Apply search filter
        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%');
            });
        }

        // Status ED filter
        if ($request->status_ed) {
            switch ($request->status_ed) {
                case 'kadaluarsa':
                    $query->where('expired_date', '<', now());
                    break;
                case 'kurang_dari_3_bulan':
                    $query->whereBetween('expired_date', [now(), now()->addMonths(3)]);
                    break;
                case 'kurang_dari_6_bulan':
                    $query->whereBetween('expired_date', [now()->addMonths(3), now()->addMonths(6)]);
                    break;
                case 'kurang_dari_1_tahun':
                    $query->whereBetween('expired_date', [now()->addMonths(6), now()->addYear()]);
                    break;
                case 'lebih_dari_1_tahun':
                    $query->where('expired_date', '>', now()->addYear());
                    break;
                case 'tidak_ada_ed':
                    $query->whereNull('expired_date');
                    break;
            }
        }

        // Tax filter - only apply if explicitly provided
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        // If no tax_id filter, include ALL tax_id values (both PKP and non-PKP)

        // Advanced filters
        if ($request->main_category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('main_category_id', $request->main_category_id);
            });
        }

        if ($request->brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('brand_id', $request->brand_id);
            });
        }

        if ($request->sub_brand_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sub_brand_id', $request->sub_brand_id);
            });
        }

        if ($request->product_category_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_category_id', $request->product_category_id);
            });
        }

        if ($request->product_type_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_type_id', $request->product_type_id);
            });
        }

        if ($request->product_size_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_size_id', $request->product_size_id);
            });
        }

        if ($request->product_variant_id) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('product_variant_id', $request->product_variant_id);
            });
        }

        // Ambil semua data untuk export
        $allStocks = $query->get();

        // Kelompokkan berdasarkan product_id + tax_id untuk menampilkan breakdown per tax_id
        // Ini memastikan LM dan HGN muncul sebagai baris terpisah seperti di stock-list
        $groupedStocks = $allStocks->groupBy(function ($stock) {
            // Group by product_id + tax_id (null dianggap sebagai string 'null' untuk grouping)
            return $stock->product_id.'_'.($stock->tax_id ?? 'null');
        })->map(function ($stocks) {
            $firstStock = $stocks->first();

            // Akumulasi qty berdasarkan kombinasi product_id + tax_id yang sama
            // Include all quantities including 0
            $totalQty = $stocks->sum(function ($stock) {
                return $stock->qty;
            });

            // Buat salinan dari stock pertama untuk menyimpan data akumulasi
            $aggregatedStock = clone $firstStock;
            $aggregatedStock->qty = $totalQty;

            return $aggregatedStock;
        })->filter(function ($stock) {
            // Filter out null values only (keep stocks with qty = 0)
            return $stock !== null;
        })->values();

        // Tambahkan status ED
        $today = now();
        foreach ($groupedStocks as $stock) {
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

            // Set the is_free flag - dinonaktifkan karena tidak ada kolom is_free di warehouse_stock
            // $stock->is_free = $stock->penerimaanDetail && $stock->penerimaanDetail->is_free;
        }

        // Calculate total inventory value (harga beli x qty per PO)
        // Setiap stock item menggunakan harga beli dari PO-nya masing-masing (penerimaanDetail)
        $totalInventoryValue = 0;
        foreach ($allStocks as $stock) {
            // Hanya hitung stock yang memiliki penerimaanDetail (barang masuk dari PO)
            // Stock dari retur (tanpa penerimaanDetail) tidak dihitung karena tidak ada harga beli
            if ($stock->penerimaanDetail) {
                $penerimaanDetail = $stock->penerimaanDetail;

                // Calculate HPP after discounts (same logic as in analytics)
                // Setiap PO memiliki harga beli yang berbeda, jadi gunakan harga dari penerimaanDetail ini
                $hppAsli = $penerimaanDetail->harga_hpp ?? 0;
                $hppSetelahDiskon = $hppAsli;

                // Apply percentage discounts in sequence (tiered discounts)
                if ($penerimaanDetail->diskon_persen_1 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_1 / 100);
                }
                if ($penerimaanDetail->diskon_persen_2 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_2 / 100);
                }
                if ($penerimaanDetail->diskon_persen_3 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_3 / 100);
                }
                if ($penerimaanDetail->diskon_persen_4 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_4 / 100);
                }
                if ($penerimaanDetail->diskon_persen_5 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_5 / 100);
                }

                // Apply nominal discounts
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_1 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_2 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_3 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_4 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_5 ?? 0);

                // Ensure price doesn't go negative
                $finalHpp = max(0, $hppSetelahDiskon);

                // Add to total: harga beli dari PO ini x qty stock item ini
                // Setiap stock item menggunakan harga beli dari PO-nya masing-masing
                $totalInventoryValue += $finalHpp * $stock->qty;
            }
            // Stock tanpa penerimaanDetail (misalnya dari retur) tidak dihitung karena tidak ada harga beli
        }

        $filename = $request->has('is_damaged') && $request->is_damaged ?
            'stock-rusak-'.date('Y-m-d').'.xlsx' :
            'stock-gudang-'.date('Y-m-d').'.xlsx';

        return $this->queueExcelExport(new StockExport($groupedStocks, $totalInventoryValue), $filename, 'Export Stock');
    }

    /**
     * Display analytics of stock with consolidated view
     */
    public function analytics(Request $request)
    {
        // Build filters array for SQL query (optimized dengan SQL langsung)
        $sqlFilters = [
            'search' => $request->search,
            'sku' => $request->sku,
            'status_ed' => $request->status_ed,
            'tax_id' => $request->tax_id,
            'is_free' => $request->is_free,
            'brand_id' => $request->brand_id,
            'sub_brand_id' => $request->sub_brand_id,
            'product_category_id' => $request->product_category_id,
            'product_type_id' => $request->product_type_id,
            'product_size_id' => $request->product_size_id,
            'product_variant_id' => $request->product_variant_id,
            'main_category_id' => $request->main_category_id,
        ];

        // Execute optimized SQL query untuk mendapatkan grouped stocks
        $sqlQuery = StockAnalyticsQuery::build($sqlFilters);
        $groupedResults = DB::select($sqlQuery);

        // Get product IDs untuk eager loading relationships
        $productIds = collect($groupedResults)->pluck('product_id')->toArray();

        // Eager load products dengan relationships yang diperlukan
        $products = Product::with([
            'mainCategory',
            'brand',
            'subBrand',
            'productCategory',
            'productType',
            'productSize',
            'productVariant',
        ])->whereIn('id', $productIds)->get()->keyBy('id');

        // Get locations per product dengan SQL query
        $locationsQuery = StockAnalyticsQuery::buildLocations($sqlFilters, $productIds);
        $locationsData = DB::select($locationsQuery);
        $locationsByProduct = collect($locationsData)->groupBy('product_id');

        // Get lokasi models untuk relationships
        $lokasiIds = collect($locationsData)->pluck('lokasi_id')->unique()->filter()->toArray();
        $lokasis = Lokasi::whereIn('id', $lokasiIds)->get()->keyBy('id');

        // Transform SQL results ke format yang diharapkan oleh view
        $groupedStocks = collect($groupedResults)->map(function ($row) use ($products, $locationsByProduct, $lokasis) {
            $product = $products->get($row->product_id);
            if (! $product) {
                return null;
            }

            // Set is_free property ke product object (dari penerimaan_detail, bukan products table)
            $product->is_free = (bool) ($row->is_free ?? false);

            // Get locations untuk product ini
            $productLocations = $locationsByProduct->get($row->product_id, collect());
            $locations = $productLocations->map(function ($loc) use ($lokasis) {
                return [
                    'lokasi' => $lokasis->get($loc->lokasi_id),
                    'qty' => (float) $loc->qty,
                ];
            })->values();

            return [
                'product' => $product,
                'total_qty' => (float) $row->total_qty,
                'locations' => $locations,
                'stock_items' => collect(), // Tidak perlu lagi untuk analytics view
                'expired_dates_count' => (int) $row->expired_dates_count,
                'has_expired' => (bool) $row->has_expired,
                'has_damaged' => false, // Akan dihitung terpisah untuk damaged count
                'earliest_expiry' => $row->earliest_expiry ? \Carbon\Carbon::parse($row->earliest_expiry) : null,
                'tax_categories' => collect(), // Tidak diperlukan untuk analytics view utama
            ];
        })->filter()->values();

        // Calculate summary dengan SQL query (optimized)
        $summaryQuery = StockAnalyticsQuery::buildSummary($sqlFilters);
        $summaryResult = DB::selectOne($summaryQuery);

        $totalItems = (int) ($summaryResult->total_items ?? 0);
        $totalQuantity = (float) ($summaryResult->total_quantity ?? 0);
        $multiEdCount = (int) ($summaryResult->multi_ed_products_count ?? 0);

        // Calculate damaged products count dengan SQL (optimized)
        $damagedConditions = $this->buildDamagedWhereConditions($request);
        $damagedCountQuery = "
            SELECT COUNT(DISTINCT ws.product_id) as damaged_count
            FROM warehouse_stock ws
            INNER JOIN products p ON ws.product_id = p.id
            WHERE (ws.is_damaged = 1 OR ws.qty_damaged > 0)
            {$damagedConditions}
        ";
        $damagedResult = DB::selectOne($damagedCountQuery);
        $damagedProductsCount = (int) ($damagedResult->damaged_count ?? 0);

        // Calculate total inventory value dengan SQL query yang dioptimasi
        // Menggunakan query untuk mendapatkan raw data, lalu menghitung di PHP karena kompleksitas diskon bertingkat
        $inventoryValueQuery = StockAnalyticsQuery::buildInventoryValueData();
        $inventoryValueData = DB::select($inventoryValueQuery);

        $totalInventoryValue = 0;
        foreach ($inventoryValueData as $row) {
            $hppAsli = (float) ($row->harga_hpp ?? 0);
            $hppSetelahDiskon = $hppAsli;

            // Apply percentage discounts in sequence (tiered discounts)
            if ($row->diskon_persen_1 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $row->diskon_persen_1 / 100);
            }
            if ($row->diskon_persen_2 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $row->diskon_persen_2 / 100);
            }
            if ($row->diskon_persen_3 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $row->diskon_persen_3 / 100);
            }
            if ($row->diskon_persen_4 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $row->diskon_persen_4 / 100);
            }
            if ($row->diskon_persen_5 > 0) {
                $hppSetelahDiskon -= ($hppSetelahDiskon * $row->diskon_persen_5 / 100);
            }

            // Apply nominal discounts
            $hppSetelahDiskon -= (float) ($row->diskon_nominal_1 ?? 0);
            $hppSetelahDiskon -= (float) ($row->diskon_nominal_2 ?? 0);
            $hppSetelahDiskon -= (float) ($row->diskon_nominal_3 ?? 0);
            $hppSetelahDiskon -= (float) ($row->diskon_nominal_4 ?? 0);
            $hppSetelahDiskon -= (float) ($row->diskon_nominal_5 ?? 0);

            // Ensure price doesn't go negative
            $finalHpp = max(0, $hppSetelahDiskon);

            // Add to total
            $totalInventoryValue += $finalHpp * (float) ($row->qty ?? 0);
        }

        // Build product stock history data (for AJAX fetch later)
        if ($request->ajax() && $request->has('product_id')) {
            $productId = $request->product_id;

            // Stock items for this product - include all stock movements
            // Order by actual transaction date from newest to oldest
            $stockItems = WarehouseStock::where('product_id', $productId)
                ->with([
                    'lokasi',
                    'tax',
                    'penerimaanDetail.penerimaan',
                    'penerimaanDetail.satuan',
                    'returPenjualan.order.platform',
                    'returPenjualan.details',
                    'returOfflineSale.offlineSale',
                    'returOfflineSale.details',
                ])
                ->orderByRaw('
                    CASE 
                        WHEN warehouse_stock.source_date IS NOT NULL THEN warehouse_stock.source_date
                        WHEN warehouse_stock.penerimaan_detail_id IS NOT NULL THEN (
                            SELECT tanggal_penerimaan 
                            FROM penerimaan 
                            WHERE id = (
                                SELECT penerimaan_id 
                                FROM penerimaan_detail 
                                WHERE id = warehouse_stock.penerimaan_detail_id
                            )
                        )
                        ELSE warehouse_stock.created_at
                    END DESC
                ')
                ->get();

            // For "Barang Masuk" display, we should show the ORIGINAL quantity that was received
            // DO NOT modify warehouse_stock.qty as it represents current stock, not original receipt
            // The view will handle displaying the correct quantity for each purpose:
            // - For "Barang Masuk": use penerimaan_detail.qty (original received quantity)
            // - For "Returns": use warehouse_stock.qty (actual returned quantity)
            // - For "Current Stock": use warehouse_stock.qty (current available quantity)

            // Keep warehouse_stock.qty as-is for current stock reference
            // The view will access penerimaan_detail.qty directly when needed

            // Relasi retur data sudah dimuat otomatis melalui eager loading di atas

            // Stock outgoing history - include all barang keluar records from newest to oldest
            $stockOutItems = BarangKeluar::whereHas('warehouseStock', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
                ->with([
                    'warehouseStock.penerimaanDetail.penerimaan',
                    'warehouseStock.penerimaanDetail.satuan',
                    'warehouseStock.lokasi',
                    'warehouseStock.tax',
                    'orderItem.order.platform',
                    'offlineSaleItem.offlineSale',
                    'offlineSaleItem.offlineSale.customerInfo',
                ])
                ->orderBy('barang_keluar.tanggal_keluar', 'desc')
                ->get();

            // Enhanced debug logging (before grouping)
            $product = \App\Models\Product::find($productId);

            \Log::info('Stock Analytics AJAX Request', [
                'product_id' => $productId,
                'product_found' => $product ? true : false,
                'product_name' => $product ? $product->name : 'N/A',
                'product_sku' => $product ? $product->sku : 'N/A',
                'stock_in_count_before_grouping' => $stockItems->count(),
                'stock_out_count' => $stockOutItems->count(),
                'stock_in_sample' => $stockItems->take(3)->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'qty' => $item->qty,
                        'source_type' => $item->source_type,
                        'source_date' => $item->source_date,
                        'created_at' => $item->created_at,
                        'lokasi_nama' => $item->lokasi ? $item->lokasi->nama : null,
                        'penerimaan_detail' => $item->penerimaanDetail ? [
                            'id' => $item->penerimaanDetail->id,
                            'penerimaan_id' => $item->penerimaanDetail->penerimaan_id,
                        ] : null,
                    ];
                })->toArray(),
                'stock_out_sample' => $stockOutItems->take(3)->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'qty' => $item->qty,
                        'tanggal_keluar' => $item->tanggal_keluar,
                        'kode_barang_keluar' => $item->kode_barang_keluar,
                        'warehouse_stock_id' => $item->warehouse_stock_id,
                    ];
                })->toArray(),
                'total_warehouse_stocks' => \App\Models\WarehouseStock::where('product_id', $productId)->count(),
                'total_barang_keluar' => \App\Models\BarangKeluar::whereHas('warehouseStock', function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->count(),
            ]);

            // Visual mutations removed - only show real database data

            // Group stock_in items by penerimaan_detail_id (simple approach)
            // Use penerimaan_detail.qty which is fixed and doesn't change
            // Don't use warehouse_stock.qty because it's dynamic
            $groupedStockIn = $stockItems->groupBy(function ($item) {
                // For normal penerimaan (not returns), group by penerimaan_detail_id only
                // This ensures one entry per penerimaan, even if there are multiple EDs
                if (! $item->source_type || $item->source_type === 'penerimaan') {
                    return $item->penerimaan_detail_id ? 'penerimaan_'.$item->penerimaan_detail_id : 'single_'.$item->id;
                }

                // For returns, group by source_id (retur_penjualan_id) so multiple
                // warehouse_stock entries from the same return are consolidated into one
                return 'return_'.$item->source_id;
            });

            // Create a list - one entry per penerimaan_detail_id
            // Use penerimaan_detail.qty which is the original fixed quantity
            $consolidatedStockIn = $groupedStockIn->map(function ($groupItems, $groupKey) {
                $firstItem = $groupItems->first();

                // For returns, use warehouse_stock.qty (actual returned quantity)
                if (strpos($groupKey, 'return_') === 0) {
                    // For returns, use warehouse_stock.qty as is (sum all items in group if multiple)
                    $firstItem->qty = (float) $groupItems->sum('qty');
                }
                // For normal penerimaan, use penerimaan_detail.qty (fixed, doesn't change)
                // This is the original quantity received, regardless of current warehouse_stock.qty
                elseif ($firstItem->penerimaanDetail && $firstItem->penerimaanDetail->qty) {
                    $firstItem->qty = (float) $firstItem->penerimaanDetail->qty;
                }
                // For penyesuaian, show original adjustment (current qty + barang keluar dari stock ini)
                elseif ($firstItem->source_type === 'penyesuaian') {
                    $wsQty = (float) $groupItems->sum('qty');
                    $bkQty = (float) \App\Models\BarangKeluar::where('warehouse_stock_id', $firstItem->id)->sum('qty');
                    $firstItem->qty = $wsQty + $bkQty;
                }
                // Fallback: use warehouse_stock.qty
                else {
                    $firstItem->qty = (float) $groupItems->sum('qty');
                }

                // Check if there are multiple EDs for this penerimaan_detail_id
                if (strpos($groupKey, 'penerimaan_') === 0) {
                    // Get unique expired dates from all items in this group
                    $uniqueEDs = $groupItems->pluck('expired_date')
                        ->filter()
                        ->map(function ($ed) {
                            return $ed ? \Carbon\Carbon::parse($ed) : null;
                        })
                        ->filter()
                        ->unique(function ($ed) {
                            return $ed->format('Y-m-d');
                        })
                        ->sort()
                        ->values();

                    // If multiple EDs exist, set expired_date to null and add flag with ED list
                    if ($uniqueEDs->count() > 1) {
                        $firstItem->expired_date = null;
                        $firstItem->has_multiple_ed = true;
                        $firstItem->ed_count = $uniqueEDs->count();
                        // Format ED list as "ED 3/2028, ED 8/2028" or similar
                        $firstItem->ed_list = $uniqueEDs->map(function ($ed) {
                            return 'ED '.$ed->format('m/Y');
                        })->toArray();
                    } else {
                        // Single ED, keep the expired_date
                        $firstItem->has_multiple_ed = false;
                        $firstItem->ed_count = 1;
                        $firstItem->ed_list = [];
                    }
                }

                return $firstItem;
            })->values();

            // Update log with consolidated count
            \Log::info('Stock Analytics - Consolidated', [
                'product_id' => $productId,
                'stock_in_count_after_grouping' => $consolidatedStockIn->count(),
                'grouped_keys' => $groupedStockIn->keys()->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'stock_in' => $consolidatedStockIn,
                'stock_out' => $stockOutItems,
            ]);
        }

        // Paginate the grouped results
        $perPage = 25;
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedStocks = $groupedStocks->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedStocks,
            $totalItems, // Menggunakan totalItems dari summary query (sudah dihitung dengan SQL)
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('warehouse.stock-analytics', [
            'groupedStocks' => $paginator,
            'mainCategories' => MainCategory::where('is_active', true)->get(),
            'brands' => Brand::where('is_active', true)->get(),
            'subBrands' => SubBrand::where('is_active', true)->get(),
            'productCategories' => ProductCategory::where('is_active', true)->get(),
            'productTypes' => ProductType::where('is_active', true)->get(),
            'productSizes' => ProductSize::where('is_active', true)->get(),
            'productVariants' => ProductVariant::where('is_active', true)->get(),
            'taxCategories' => TaxCategory::where('is_active', true)->get(),
            'isDamaged' => $request->has('is_damaged') && $request->is_damaged,
            'totalItems' => $totalItems,
            'totalQuantity' => $totalQuantity,
            'damagedProductsCount' => $damagedProductsCount,
            'totalInventoryValue' => $totalInventoryValue,
        ]);
    }

    /**
     * Export selected products with their stock mutations
     */
    public function exportSelected(Request $request)
    {
        // Validate the request
        $request->validate([
            'selected_products' => 'required|string',
        ]);

        // Decode the JSON string to get product IDs
        $productIds = json_decode($request->selected_products, true);

        if (empty($productIds) || ! is_array($productIds)) {
            return redirect()->back()->with('error', 'Tidak ada produk yang dipilih untuk diekspor.');
        }

        // Get the selected products with their data
        $query = WarehouseStock::with([
            'product.mainCategory',
            'product.brand',
            'product.subBrand',
            'product.productCategory',
            'product.productType',
            'product.productSize',
            'product.productVariant',
            'penerimaanDetail.satuan',
            'penerimaanDetail.penerimaan',
            'lokasi',
            'tax',
        ])
            ->whereIn('product_id', $productIds);

        // Apply damaged filter if provided
        if ($request->has('is_damaged')) {
            $query->where('is_damaged', $request->is_damaged ? true : false);
        } else {
            $query->where('is_damaged', false);
        }

        // Apply additional filters if provided

        // Status ED filter
        if ($request->status_ed) {
            switch ($request->status_ed) {
                case 'kadaluarsa':
                    $query->where('expired_date', '<', now());
                    break;
                case 'kurang_dari_3_bulan':
                    $query->whereBetween('expired_date', [now(), now()->addMonths(3)]);
                    break;
                case 'kurang_dari_6_bulan':
                    $query->whereBetween('expired_date', [now()->addMonths(3), now()->addMonths(6)]);
                    break;
                case 'kurang_dari_1_tahun':
                    $query->whereBetween('expired_date', [now()->addMonths(6), now()->addYear()]);
                    break;
                case 'lebih_dari_1_tahun':
                    $query->where('expired_date', '>', now()->addYear());
                    break;
                case 'tidak_ada_ed':
                    $query->whereNull('expired_date');
                    break;
            }
        }

        // Tax filter - only apply if explicitly provided
        if ($request->filled('tax_id')) {
            if ($request->tax_id === 'N/A') {
                $query->whereNull('tax_id');
            } else {
                $query->where('tax_id', $request->tax_id);
            }
        }
        // If no tax_id filter, include ALL tax_id values (both PKP and non-PKP)

        // Location filter
        if ($request->lokasi_id) {
            $query->where('lokasi_id', $request->lokasi_id);
        }

        // Get all stocks matching the criteria
        $allStocks = $query->get();

        // If no stocks found, redirect back with error
        if ($allStocks->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data stok untuk produk yang dipilih.');
        }

        // Group stocks by product ID and aggregate quantities
        $groupedStocks = $allStocks->groupBy('product_id')->map(function ($stocks) {
            $firstStock = $stocks->first();
            $productId = $firstStock->product_id;

            $today = now();
            $hasExpired = false;
            $earliestExpiry = null;

            foreach ($stocks as $stock) {
                if ($stock->expired_date) {
                    $expDate = \Carbon\Carbon::parse($stock->expired_date);

                    if ($expDate < $today) {
                        $hasExpired = true;
                    }

                    if (! $earliestExpiry || $expDate < $earliestExpiry) {
                        $earliestExpiry = $expDate;
                    }
                }
            }

            // Calculate total quantity using mutation logic (same as calculateRunningBalance)
            // This ensures consistency with stock mutation display
            $totalQty = $this->calculateRunningBalance($productId);

            return [
                'product' => $firstStock->product,
                'total_qty' => $totalQty,
                'locations' => $stocks->groupBy('lokasi_id')->map(function ($locStocks, $locId) {
                    return [
                        'lokasi' => $locStocks->first()->lokasi,
                        'qty' => $locStocks->sum('qty'),
                    ];
                })->values(),
                'stock_items' => $stocks,
                'expired_dates_count' => $stocks->whereNotNull('expired_date')->unique('expired_date')->count(),
                'has_expired' => $hasExpired,
                'earliest_expiry' => $earliestExpiry,
            ];
        })->values();

        // Calculate total inventory value (harga beli x qty per PO)
        // Setiap stock item menggunakan harga beli dari PO-nya masing-masing (penerimaanDetail)
        $totalInventoryValue = 0;
        foreach ($allStocks as $stock) {
            // Hanya hitung stock yang memiliki penerimaanDetail (barang masuk dari PO)
            // Stock dari retur (tanpa penerimaanDetail) tidak dihitung karena tidak ada harga beli
            if ($stock->penerimaanDetail) {
                $penerimaanDetail = $stock->penerimaanDetail;

                // Calculate HPP after discounts (same logic as in analytics)
                // Setiap PO memiliki harga beli yang berbeda, jadi gunakan harga dari penerimaanDetail ini
                $hppAsli = $penerimaanDetail->harga_hpp ?? 0;
                $hppSetelahDiskon = $hppAsli;

                // Apply percentage discounts in sequence (tiered discounts)
                if ($penerimaanDetail->diskon_persen_1 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_1 / 100);
                }
                if ($penerimaanDetail->diskon_persen_2 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_2 / 100);
                }
                if ($penerimaanDetail->diskon_persen_3 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_3 / 100);
                }
                if ($penerimaanDetail->diskon_persen_4 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_4 / 100);
                }
                if ($penerimaanDetail->diskon_persen_5 > 0) {
                    $hppSetelahDiskon -= ($hppSetelahDiskon * $penerimaanDetail->diskon_persen_5 / 100);
                }

                // Apply nominal discounts
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_1 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_2 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_3 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_4 ?? 0);
                $hppSetelahDiskon -= ($penerimaanDetail->diskon_nominal_5 ?? 0);

                // Ensure price doesn't go negative
                $finalHpp = max(0, $hppSetelahDiskon);

                // Add to total: harga beli dari PO ini x qty stock item ini
                // Setiap stock item menggunakan harga beli dari PO-nya masing-masing
                $totalInventoryValue += $finalHpp * $stock->qty;
            }
            // Stock tanpa penerimaanDetail (misalnya dari retur) tidak dihitung karena tidak ada harga beli
        }

        // Format date for filename
        $dateFormatted = date('Y-m-d');

        // Add filters to filename if applied
        $filenamePrefix = 'mutasi-stok';
        if ($request->has('is_damaged') && $request->is_damaged) {
            $filenamePrefix = 'mutasi-stok-rusak';
        }

        // Generate the Excel file
        $filename = $filenamePrefix.'-'.$dateFormatted.'.xlsx';

        return $this->queueExcelExport(
            new StockMutationExport(
                $groupedStocks,
                $request->start_date,
                $request->end_date,
                $request->has('include_empty'),
                $totalInventoryValue
            ),
            $filename,
            'Export Mutasi Stok'
        );
    }

    /**
     * Build WHERE conditions untuk damaged products query
     */
    private function buildDamagedWhereConditions(Request $request): string
    {
        $conditions = [];
        $pdo = DB::getPdo();

        // Search filter
        if ($request->search) {
            $search = $pdo->quote('%'.$request->search.'%');
            $conditions[] = "p.name LIKE {$search}";
        }

        // SKU filter
        if ($request->sku) {
            $sku = $pdo->quote('%'.$request->sku.'%');
            $conditions[] = "p.sku LIKE {$sku}";
        }

        // Brand filter
        if ($request->brand_id) {
            $brandId = (int) $request->brand_id;
            $conditions[] = "p.brand_id = {$brandId}";
        }

        // Main category filter
        if ($request->main_category_id) {
            $categoryId = (int) $request->main_category_id;
            $conditions[] = "p.main_category_id = {$categoryId}";
        }

        return ! empty($conditions) ? 'AND '.implode(' AND ', $conditions) : '';
    }

    /**
     * Calculate running balance for a product based on stock mutations
     * Returns real stock from mutations: (Penerimaan + Retur + Penyesuaian) - Barang Keluar
     */
    private function calculateRunningBalance($productId)
    {
        // Get all warehouse_stock records to find penerimaan
        $stockInMovements = WarehouseStock::where('product_id', $productId)
            ->where('is_damaged', false)
            ->with('penerimaanDetail')
            ->get();

        // Group by penerimaan_detail_id to avoid double counting
        $penerimaanGroups = [];
        foreach ($stockInMovements as $movement) {
            if ($movement->penerimaanDetail && ! in_array($movement->source_type, ['retur_penjualan', 'retur_offline'])) {
                $pdId = $movement->penerimaan_detail_id;
                if (! isset($penerimaanGroups[$pdId])) {
                    $penerimaanGroups[$pdId] = $movement->penerimaanDetail->qty;
                }
            }
        }

        // Calculate total penerimaan
        $totalPenerimaanQty = array_sum($penerimaanGroups);

        // Get retur quantities from retur_detail tables (source of truth)
        $totalReturPenjualanQty = \App\Models\ReturPenjualanDetail::where('product_id', $productId)
            ->where('kondisi', 'BAGUS')
            ->sum('qty');
        $totalReturOfflineQty = \App\Models\ReturOfflineSaleDetail::where('product_id', $productId)
            ->where('kondisi', 'BAGUS')
            ->sum('qty');
        $totalReturQty = $totalReturPenjualanQty + $totalReturOfflineQty;

        // Get penyesuaian original amount (current qty + barang keluar dari stock ini)
        $penyesuaianStocks = WarehouseStock::where('product_id', $productId)
            ->where('is_damaged', false)
            ->where('source_type', 'penyesuaian')
            ->pluck('id');

        $totalPenyesuaianQty = 0;
        foreach ($penyesuaianStocks as $wsId) {
            $wsQty = (float) WarehouseStock::where('warehouse_stock.id', $wsId)->value('qty');
            $bkQty = (float) \App\Models\BarangKeluar::where('warehouse_stock_id', $wsId)->sum('qty');
            $totalPenyesuaianQty += $wsQty + $bkQty;
        }

        // Calculate total stock IN
        $totalStockIn = $totalPenerimaanQty + $totalReturQty + $totalPenyesuaianQty;

        // Calculate total barang keluar (from ALL sources)
        $totalBarangKeluarQty = \App\Models\BarangKeluar::whereHas('warehouseStock', function ($q) use ($productId) {
            $q->where('product_id', $productId);
        })
            ->sum('qty');

        // Real stock = (Penerimaan + Retur + Penyesuaian) - Barang Keluar
        $realStock = $totalStockIn - $totalBarangKeluarQty;

        return $realStock;
    }
}
