<?php

namespace App\Http\Controllers;

use App\Imports\Tiktok2Import;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class Tiktok2Controller extends Controller
{
    protected $platform;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            $routeParam = 'tiktok liefmarket';
            $this->platform = $this->getPlatformByRouteParam($routeParam);
        } catch (\Exception $e) {
            \Log::error('Exception in Tiktok2Controller constructor: ' . $e->getMessage());
            $this->platform = null;
        }
    }
    
    /**
     * Helper method untuk mendapatkan platform berdasarkan route parameter
     */
    protected function getPlatformByRouteParam($routeParam)
    {
        // 1. Cari berdasarkan ID jika ada di request/session
        $platformId = request()->route('platform_id') ?? session('platform_id');
        if ($platformId) {
            $platform = Platform::find($platformId);
            if ($platform) {
                return $platform;
            }
        }
        
        // 2. Cari berdasarkan nama dengan case-insensitive
        $platform = Platform::whereRaw('LOWER(name) = ?', [strtolower($routeParam)])->first();
        
        // 3. Jika tidak ditemukan, cari dengan LIKE (untuk menangani variasi nama)
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($routeParam) . '%'])->first();
        }
        
        // 4. Jika masih tidak ditemukan, cari tiktok liefmarket secara spesifik
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%tiktok%liefmarket%'])->first();
        }
        
        // 5. Jika masih tidak ditemukan, cari tiktok tapi hindari lamourad
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%tiktok%'])
                ->whereRaw('LOWER(name) NOT LIKE ?', ['%lamourad%'])
                ->first();
        }
        
        // 6. Jika benar-benar tidak ada platform di database
        if (!$platform) {
            throw new \Exception('Platform tidak ditemukan di database. Pastikan ada platform yang terdaftar.');
        }
        
        return $platform;
    }
    /**
     * Tampilkan halaman import Excel Tiktok Liefmarket
     */
public function importExcel()
{
    // Tambahkan informasi untuk ditampilkan di halaman import
    $importGuidelines = [
        'Pastikan kolom berikut ada di file Excel Anda:' => [
            'NOMOR PESANAN (wajib)',
            'NAMA PRODUK (wajib)',
            'JUMLAH/QTY (wajib)',
            'HARGA SETELAH DISKON (wajib)',
            'TANGGAL PESANAN',
            'NOMOR RESI',
            'HARI',
        ],
        'Catatan Penting:' => [
            'Format header bisa fleksibel (tidak harus sama persis)',
            'Header bisa berada di baris mana saja dalam file Excel',
            'Kolom HARI boleh kosong dan tetap bisa diimpor',
            'Semua produk harus sudah terdaftar dan dimapping di sistem'
        ]
    ];

    return view('sales.tiktok2.import-excel', [
        'importGuidelines' => $importGuidelines
    ]);
}

    /**
     * Preview data Excel sebelum import
     */
    public function previewImport(Request $request)
    {
        \Log::info('---------- MULAI PROSES IMPORT TIKTOK LIEF MARKET ----------');
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            // Log awal proses import
            \Log::info('Starting Excel import preview process');
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $file = $request->file('excel_file');
            \Log::info('Excel file uploaded', [
                'name' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]);

            // Pastikan platform sudah di-set
            if (!$this->platform) {
                \Log::error('Platform is null in previewImport');
                return redirect()->route('sales.tiktok2.import-excel')->with('error', 'Platform tidak ditemukan. Silakan hubungi administrator.');
            }

            // Proses file Excel
            $import = new Tiktok2Import($this->platform->id);
            \Log::info('Before Excel import');
            Excel::import($import, $file);
            \Log::info('After Excel import');

            // Dapatkan data untuk preview
            $data = $import->getData();
            \Log::info('Retrieved data from import, count: ' . count($data));

            $unmappedProducts = $import->getUnmappedProducts();
            \Log::info('Unmapped products count: ' . count($unmappedProducts));

            $invalidData = $import->getInvalidData();
            \Log::info('Invalid data count: ' . count($invalidData));

            $headerIssues = $import->getHeaderIssues();
            \Log::info('Header issues: ' . json_encode($headerIssues));

            $totalRows = $import->getTotalRows();
            \Log::info('Total rows from Excel: ' . $totalRows);

            // Informasi tentang baris yang dilewati
            $skippedRows = [
                'Tanggal kosong' => $import->getSkippedForMissingDate(),
                'Hari kosong' => $import->getSkippedForMissingDay(),
                'No Resi kosong' => $import->getSkippedForMissingResi(),
            ];

            // Filter skippedRows yang nilainya 0
            $skippedRows = array_filter($skippedRows);
            \Log::info('Skipped rows: ' . json_encode($skippedRows));

            // Cek order yang sudah ada di database
            $duplicateOrders = [];
            if (!empty($data)) {
                $orderNumbers = array_unique(array_column($data, 'no_order'));
                \Log::info('Unique order numbers: ' . count($orderNumbers));

                foreach ($orderNumbers as $orderNumber) {
                    $existingOrder = Order::where('order_number', $orderNumber)->exists();

                    if ($existingOrder) {
                        $duplicateOrders[] = $orderNumber;
                    }
                }
                \Log::info('Duplicate orders found: ' . count($duplicateOrders));
            }
            
            // Set main category ke Kosmetik sebelum validasi stok
            session(['main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId()]);
            session(['main_category_name' => 'Kosmetik']);
            
            // Debug logging
            $mainCategoryId = \App\Helpers\MainCategoryHelper::getSelectedMainCategoryId();
            \Log::info('TikTok2 PreviewImport - Main Category set to: ' . ($mainCategoryId ?: 'NULL'));
            
            // Cek ketersediaan stok dengan detail yang lebih lengkap
            $insufficientStockProducts = [];
            $ordersWithStockIssues = [];
            $stockIssuesSummary = [];
            
            if (!empty($data)) {
                // Hitung total kebutuhan dari seluruh file Excel (bukan per order) - sama seperti Shopee2
                $totalProductQuantities = [];
                $orderProductQuantities = [];
                
                foreach ($data as $row) {
                    $orderNumber = $row['no_order'] ?? 'N/A';
                    if (!isset($orderProductQuantities[$orderNumber])) {
                        $orderProductQuantities[$orderNumber] = [];
                    }
                    
                    // Cari platform product jika belum ada di data
                    $platformProductId = $row['platform_product_id'] ?? null;
                    
                    if (!$platformProductId) {
                        $productName = $row['nama_barang'] ?? '';
                        $variation = $row['variasi'] ?? null;
                        $fullProductName = !empty($variation) ? "$productName - $variation" : $productName;

                        $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                            ->where(function ($query) use ($productName, $variation) {
                                if (!empty($variation)) {
                                    $query->where('platform_product_name', $productName)
                                        ->where('variant', $variation);
                                } else {
                                    $query->where('platform_product_name', $productName)
                                        ->where(function($q) {
                                            $q->whereNull('variant')
                                              ->orWhere('variant', '');
                                        });
                                }
                            })
                            ->first();

                        if (!$platformProduct && !empty($variation)) {
                            $baseProductName = preg_replace('/\s*-\s*100ml$/', '', $productName);
                            $newVariant = '100ml - ' . $variation;
                            if ($baseProductName !== $productName) {
                                $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                                    ->where('platform_product_name', $baseProductName)
                                    ->where('variant', $newVariant)
                                    ->first();
                            }
                        }

                        if (!$platformProduct && empty($variation)) {
                            $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                                ->where(function ($query) use ($productName, $fullProductName) {
                                    $query->where('platform_product_name', $fullProductName)
                                        ->orWhere('platform_product_name', 'LIKE', '%' . $fullProductName . '%')
                                        ->orWhere('platform_product_name', $productName)
                                        ->orWhere('platform_product_name', 'LIKE', '%' . $productName . '%')
                                        ->orWhere(\Illuminate\Support\Facades\DB::raw('LOWER(platform_product_name)'), 'LIKE', '%' . strtolower($productName) . '%');
                                })
                                ->first();
                        }
                        
                        if ($platformProduct) {
                            $platformProductId = $platformProduct->id;
                        }
                    }
                    
                    if (!$platformProductId) {
                        // Skip jika platform product tidak ditemukan (akan ditangani oleh unmapped products)
                        continue;
                    }
                    
                    // Ambil semua mapping barang AKTIF untuk platform product ini
                    $mappings = \App\Models\MappingBarang::where('platform_product_id', $platformProductId)
                        ->where('is_active', true)
                        ->get();
                    
                    if ($mappings->isEmpty()) {
                        // Skip jika tidak ada mapping (akan ditangani oleh unmapped products)
                        continue;
                    }
                    
                    $itemQty = $row['qty'] ?? 0;
                    
                    foreach ($mappings as $mapping) {
                        $requiredQty = $itemQty * $mapping->quantity;
                        
                        // Hitung per order (untuk detail)
                        if (!isset($orderProductQuantities[$orderNumber][$mapping->product_id])) {
                            $orderProductQuantities[$orderNumber][$mapping->product_id] = 0;
                        }
                        $orderProductQuantities[$orderNumber][$mapping->product_id] += $requiredQty;
                        
                        // Hitung total dari seluruh file Excel
                        if (!isset($totalProductQuantities[$mapping->product_id])) {
                            $totalProductQuantities[$mapping->product_id] = 0;
                        }
                        $totalProductQuantities[$mapping->product_id] += $requiredQty;
                    }
                }
                
                // CEK STOK PER ORDER (simulasi FIFO seperti reduceStock)
                $virtualStock = [];
                foreach (array_keys($totalProductQuantities) as $productId) {
                    $stockQtys = \App\Models\WarehouseStock::where('product_id', $productId)
                        ->where('qty', '>', 0)
                        ->orderBy('warehouse_stock.created_at')
                        ->orderBy('warehouse_stock.tax_id', 'asc')
                        ->pluck('warehouse_stock.qty')
                        ->toArray();
                    $virtualStock[$productId] = $stockQtys;
                }

                foreach ($orderProductQuantities as $orderNumber => $products) {
                    $orderIssues = [];
                    foreach ($products as $productId => $requiredQty) {
                        if (!isset($virtualStock[$productId])) {
                            continue;
                        }

                        $remainingQty = $requiredQty;
                        foreach ($virtualStock[$productId] as $i => $stockQty) {
                            if ($remainingQty <= 0) {
                                break;
                            }
                            $qtyToTake = min($remainingQty, $stockQty);
                            $remainingQty -= $qtyToTake;
                            $virtualStock[$productId][$i] -= $qtyToTake;
                        }

                        if ($remainingQty > 0) {
                            $product = \App\Models\Product::find($productId);
                            $productName = $product ? $product->name : "Product ID: {$productId}";
                            $usedQty = $requiredQty - $remainingQty;

                            $orderIssues[] = [
                                'product_name' => $productName,
                                'product_id' => $productId,
                                'required_qty' => $requiredQty,
                                'available_qty' => $usedQty,
                                'shortage' => $remainingQty,
                            ];

                            if (!isset($stockIssuesSummary[$productId])) {
                                $stockIssuesSummary[$productId] = [
                                    'product_name' => $productName,
                                    'total_required' => $requiredQty,
                                    'available_qty' => $usedQty,
                                    'shortage' => $remainingQty,
                                    'affected_orders' => []
                                ];
                            }
                            $stockIssuesSummary[$productId]['affected_orders'][] = $orderNumber;
                        }
                    }

                    if (!empty($orderIssues)) {
                        $ordersWithStockIssues[$orderNumber] = $orderIssues;
                    }
                }
                
                // Konversi summary ke array untuk display
                $insufficientStockProducts = array_values($stockIssuesSummary);
                
                \Log::info('Insufficient stock products: ' . count($insufficientStockProducts));
                \Log::info('Orders with stock issues: ' . count($ordersWithStockIssues));
            }

            // Jika ada masalah dengan header, tampilkan pesan error tanpa redirect
            if (!empty($headerIssues)) {
                \Log::warning('Header issues detected: ' . implode(', ', $headerIssues));
                return redirect()->route('sales.tiktok2.import-excel')->with('error', 'Format file Excel tidak sesuai: ' . implode(', ', $headerIssues));
            }

            // Jika tidak ada data yang ditemukan, tampilkan error tanpa redirect
            if (empty($data)) {
                $errorMessage = 'Tidak ada data yang dapat diproses dari file Excel.';
                if (!empty($skippedRows)) {
                    $errorMessage .= ' Beberapa data dilewati: ' . implode(', ', array_map(function ($value, $key) { return "$key: $value"; }, $skippedRows, array_keys($skippedRows)));
                }
                if (!empty($totalRows) && $totalRows > 0) {
                    $errorMessage .= " Total {$totalRows} baris ditemukan dalam file, tetapi tidak ada yang dapat diproses.";
                }
                $errorMessage .= ' Pastikan data memiliki format yang benar dan semua kolom wajib telah diisi.';
                \Log::warning('No data found in import: ' . $errorMessage);
                return redirect()->route('sales.tiktok2.import-excel')->with('error', $errorMessage);
            }

            // Simpan data ke session untuk digunakan di proses import
            session(['preview_data' => $data]);
            session(['unmapped_products' => $unmappedProducts]);
            session(['invalid_data' => $invalidData]);
            session(['total_rows' => $totalRows]);
            session(['skipped_rows' => $skippedRows]);
            session(['duplicate_orders' => $duplicateOrders]);
            session(['insufficient_stock_products' => $insufficientStockProducts]);
            session(['orders_with_stock_issues' => $ordersWithStockIssues]);
            session(['stock_issues_summary' => $stockIssuesSummary]);
            \Log::info('Data saved to session, redirecting to preview');

            // Tentukan apakah bisa melanjutkan import
            // Tidak bisa import jika ada masalah stok, mapping, atau data tidak valid
            $canProceed = empty($invalidData) && empty($unmappedProducts) && empty($insufficientStockProducts);
            
            // Buat pesan warning yang lebih detail
            $warningMessages = [];
            if (!empty($unmappedProducts)) {
                $warningMessages[] = 'Beberapa produk perlu dimapping terlebih dahulu.';
            }
            if (!empty($insufficientStockProducts)) {
                $warningMessages[] = 'Beberapa produk memiliki stok tidak mencukupi.';
            }
            if (!empty($skippedRows)) {
                $skippedCount = array_sum($skippedRows);
                $warningMessages[] = "{$skippedCount} pesanan akan dilewati karena data tidak lengkap.";
            }
            
            $warningMessage = !empty($warningMessages) ? implode(' ', $warningMessages) : null;

            // Jika ada produk yang belum dimapping, tetap tampilkan preview
            return view('sales.tiktok2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'invalidData' => $invalidData,
                'canProceed' => $canProceed,
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
                'insufficientStockProducts' => $insufficientStockProducts,
                'ordersWithStockIssues' => $ordersWithStockIssues,
                'stockIssuesSummary' => $stockIssuesSummary,
                'platformId' => $this->platform->id ?? null
            ])->with('warning', $warningMessage);
        } catch (\Exception $e) {
            \Log::error('Exception in previewImport: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return redirect()->route('sales.tiktok2.import-excel')->with('error', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }

    /**
     * Proses final import data
     */
    public function processImport(Request $request)
    {
        // Increase execution time and memory limit for large imports
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Pastikan platform sudah di-set
        if (!$this->platform) {
            \Log::error('Platform is null in processImport');
            return redirect()->route('sales.tiktok2.import-excel')->with('error', 'Platform tidak ditemukan. Silakan hubungi administrator.');
        }

        // Ambil data preview dari session
        $data = session('preview_data');
        $unmappedProducts = session('unmapped_products');
        $insufficientStockProducts = session('insufficient_stock_products');

        // Jika tidak ada data, kembalikan ke halaman import
        if (!$data) {
            return redirect()->route('sales.tiktok2.import-excel')->with('error', 'Data preview tidak ditemukan. Silakan upload ulang file Excel.');
        }

        // Jika masih ada produk yang belum di-mapping, tampilkan error
        if (!empty($unmappedProducts)) {
            // Dapatkan info baris yang di-skip dan duplikat dari import
            $import = new Tiktok2Import($this->platform->id);
            $totalRows = $import->getTotalRows();
            $skippedRows = [
                'Tanggal kosong' => $import->getSkippedForMissingDate(),
                'Hari kosong' => $import->getSkippedForMissingDay(),
                'No Resi kosong' => $import->getSkippedForMissingResi(),
            ];
            $skippedRows = array_filter($skippedRows);

            // Cek order yang sudah ada di database
            $duplicateOrders = [];
            $orderNumbers = array_unique(array_column($data, 'no_order'));
            foreach ($orderNumbers as $orderNumber) {
                $existingOrder = Order::where('order_number', $orderNumber)->exists();
                if ($existingOrder) {
                    $duplicateOrders[] = $orderNumber;
                }
            }

            // Simpan pesan warning dalam flash session
            session()->flash('warning', 'Masih ada produk yang belum di-mapping. Harap selesaikan mapping produk terlebih dahulu.');

            return view('sales.tiktok2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'canProceed' => false,
                'invalidData' => [],
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
                'insufficientStockProducts' => $insufficientStockProducts
            ]);
        }
        
        // Jika masih ada produk dengan stok tidak mencukupi, tampilkan error dan tidak bisa import
        if (!empty($insufficientStockProducts)) {
            // Dapatkan info baris yang di-skip dan duplikat dari import
            $import = new Tiktok2Import($this->platform->id);
            $totalRows = $import->getTotalRows();
            $skippedRows = [
                'Tanggal kosong' => $import->getSkippedForMissingDate(),
                'Hari kosong' => $import->getSkippedForMissingDay(),
                'No Resi kosong' => $import->getSkippedForMissingResi(),
            ];
            $skippedRows = array_filter($skippedRows);

            // Cek order yang sudah ada di database
            $duplicateOrders = [];
            $orderNumbers = array_unique(array_column($data, 'no_order'));
            foreach ($orderNumbers as $orderNumber) {
                $existingOrder = Order::where('order_number', $orderNumber)->exists();
                if ($existingOrder) {
                    $duplicateOrders[] = $orderNumber;
                }
            }

            // Ambil data stock issues dari session
            $ordersWithStockIssues = session('orders_with_stock_issues', []);
            $stockIssuesSummary = session('stock_issues_summary', []);

            // Buat pesan error yang detail
            $errorDetails = [];
            foreach ($insufficientStockProducts as $product) {
                $errorDetails[] = "• {$product['product_name']}: Butuh {$product['total_required']} unit, tersedia {$product['available_qty']} unit (kurang " . ($product['total_required'] - $product['available_qty']) . " unit)";
            }
            
            $errorMessage = '❌ ERROR: Beberapa produk memiliki stok tidak mencukupi. Import tidak dapat dilanjutkan.' . "\n\n" . implode("\n", $errorDetails);

            // Simpan pesan error dalam flash session
            session()->flash('error', $errorMessage);

            return view('sales.tiktok2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'canProceed' => false, // Tidak bisa import jika ada masalah stok
                'invalidData' => [],
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
                'insufficientStockProducts' => $insufficientStockProducts,
                'ordersWithStockIssues' => $ordersWithStockIssues,
                'stockIssuesSummary' => $stockIssuesSummary
            ]);
        }

        try {
            // Proses import data
            $import = new Tiktok2Import($this->platform->id);

            // Set data yang akan diproses
            $import->setData($data);
            
            // Set unmapped products dari session
            $unmappedProducts = session('unmapped_products', []);
            $import->setUnmappedProducts($unmappedProducts);

            // Jalankan proses import
            $result = $import->processImport();

            // Jika sukses, hapus session dan tampilkan pesan sukses
            if ($result['success'] > 0) {
                session()->forget(['preview_data', 'unmapped_products', 'insufficient_stock_products']);

                // Tambahkan informasi tambahan tentang nomor pesanan duplikat
                $successMessage = "Berhasil mengimport {$result['success']} data penjualan Tiktok2.";

                if (isset($result['duplicates']) && $result['duplicates'] > 0) {
                    $successMessage .= " {$result['duplicates']} nomor pesanan dilewati karena sudah ada di database.";
                }

                if (isset($result['skipped']) && $result['skipped'] > 0) {
                    $successMessage .= " {$result['skipped']} pesanan dilewati karena tidak memiliki data lengkap.";
                }

                // Tambahkan informasi tentang order yang gagal jika ada
                if (isset($result['failed_orders']) && !empty($result['failed_orders'])) {
                    $failedCount = count($result['failed_orders']);
                    $successMessage .= " {$failedCount} pesanan gagal diproses.";
                    
                    // Simpan detail order yang gagal untuk ditampilkan sebagai warning
                    $failedOrderDetails = [];
                    foreach ($result['failed_orders'] as $failedOrder) {
                        $failedOrderDetails[] = "Order {$failedOrder['order_number']}: {$failedOrder['error']}";
                    }
                    session()->flash('warning', 'Beberapa pesanan gagal diproses: ' . implode('; ', $failedOrderDetails));
                }

                return redirect()->route('sales.list')->with('success', $successMessage);
            } else {
                // Jika semua order gagal atau ada error, simpan pesan error dalam flash session
                $errorMessage = 'Gagal mengimport data: ' . implode(', ', $result['errors']);
                
                // Jika ada failed orders, tambahkan detail
                if (isset($result['failed_orders']) && !empty($result['failed_orders'])) {
                    $failedDetails = [];
                    foreach ($result['failed_orders'] as $failed) {
                        $failedDetails[] = "Order {$failed['order_number']}: {$failed['error']}";
                    }
                    $errorMessage .= "\n\nDetail error:\n" . implode("\n", $failedDetails);
                }
                
                session()->flash('error', $errorMessage);

                // Debug: tambahkan log untuk melihat pesan error
                \Log::info('Error message set in session: ' . $errorMessage);

                // Dapatkan info baris yang di-skip dan duplikat
                $totalRows = $import->getTotalRows();
                $skippedRows = [
                    'Tanggal kosong' => $import->getSkippedForMissingDate(),
                    'Hari kosong' => $import->getSkippedForMissingDay(),
                    'No Resi kosong' => $import->getSkippedForMissingResi(),
                ];
                $skippedRows = array_filter($skippedRows);

                // Cek order yang sudah ada di database
                $duplicateOrders = [];
                $orderNumbers = array_unique(array_column($data, 'no_order'));
                foreach ($orderNumbers as $orderNumber) {
                    $existingOrder = Order::where('order_number', $orderNumber)->exists();
                    if ($existingOrder) {
                        $duplicateOrders[] = $orderNumber;
                    }
                }

                // Data tetap di preview jika ada error
                return view('sales.tiktok2.preview-import', [
                    'data' => $data,
                    'unmappedProducts' => $unmappedProducts,
                    'canProceed' => false,
                    'invalidData' => [],
                    'totalRows' => $totalRows,
                    'skippedRows' => $skippedRows,
                    'duplicateOrders' => $duplicateOrders,
                    'insufficientStockProducts' => $insufficientStockProducts
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Exception in processImport: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            // Simpan pesan error dalam flash session dengan detail yang lebih jelas
            $errorMessage = 'Import gagal: ' . $e->getMessage();
            
            // Jika error terkait stok, berikan pesan yang lebih spesifik
            if (strpos($e->getMessage(), 'Stok tidak cukup') !== false) {
                $errorMessage = 'Import gagal karena stok tidak mencukupi untuk beberapa produk. Silakan periksa stok warehouse dan coba lagi.';
            }
            
            session()->flash('error', $errorMessage);

            // Debug: tambahkan log untuk melihat pesan error
            \Log::info('Exception message set in session: ' . $errorMessage);

            // Dapatkan info baris yang di-skip dan duplikat
            $import = new Tiktok2Import($this->platform->id);
            $totalRows = $import->getTotalRows();
            $skippedRows = [
                'Tanggal kosong' => $import->getSkippedForMissingDate(),
                'Hari kosong' => $import->getSkippedForMissingDay(),
                'No Resi kosong' => $import->getSkippedForMissingResi(),
            ];
            $skippedRows = array_filter($skippedRows);

            // Cek order yang sudah ada di database
            $duplicateOrders = [];
            $orderNumbers = array_unique(array_column($data, 'no_order'));
            foreach ($orderNumbers as $orderNumber) {
                $existingOrder = Order::where('order_number', $orderNumber)->exists();
                if ($existingOrder) {
                    $duplicateOrders[] = $orderNumber;
                }
            }

            return view('sales.tiktok2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'canProceed' => false,
                'invalidData' => [],
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
                'insufficientStockProducts' => $insufficientStockProducts
            ]);
        }
    }

    /**
     * Show the preview page for GET requests
     */
    public function showPreview()
    {
        // Get data from session
        $data = session('preview_data');
        $unmappedProducts = session('unmapped_products');
        $invalidData = session('invalid_data', []);
        $totalRows = session('total_rows', 0);
        $skippedRows = session('skipped_rows', []);
        $duplicateOrders = session('duplicate_orders', []);
        $insufficientStockProducts = session('insufficient_stock_products', []);
        $ordersWithStockIssues = session('orders_with_stock_issues', []);
        $stockIssuesSummary = session('stock_issues_summary', []);
        
        // Ensure duplicateOrders is always an array
        if (!is_array($duplicateOrders)) {
            $duplicateOrders = [];
        }

        // If no data in session, redirect to import page
        if (!$data) {
            return redirect()->route('sales.tiktok2.import-excel')
                ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
        }

        // Recalculate unmapped products based on current database state
        $unmappedProducts = $this->recalculateUnmappedProducts($data);

        return view('sales.tiktok2.preview-import', [
            'data' => $data,
            'unmappedProducts' => $unmappedProducts,
            'invalidData' => $invalidData,
            'canProceed' => empty($invalidData) && empty($insufficientStockProducts) && empty($unmappedProducts),
            'totalRows' => $totalRows,
            'skippedRows' => $skippedRows,
            'duplicateOrders' => $duplicateOrders,
            'insufficientStockProducts' => $insufficientStockProducts,
            'platformId' => $this->platform->id ?? null
        ]);
    }

    /**
     * Recalculate unmapped products based on current database state
     * Menggunakan logika yang sama dengan Tiktok2Import->checkProductMapping()
     */
    private function recalculateUnmappedProducts($data)
    {
        $unmappedProducts = [];
        
        // Get unique products from data
        $uniqueProducts = [];
        foreach ($data as $row) {
            $productName = $row['nama_barang'] ?? '';
            $variant = $row['variasi'] ?? '';
            $fullProductName = $variant ? $productName . ' - ' . $variant : $productName;
            
            if (!isset($uniqueProducts[$fullProductName])) {
                $uniqueProducts[$fullProductName] = [
                    'name' => $productName,
                    'variant' => $variant ?: null,
                    'full_name' => $fullProductName
                ];
            }
        }
        
        // Check each product for mapping using the same logic as Tiktok2Import
        foreach ($uniqueProducts as $product) {
            $productName = $product['name'];
            $variation = $product['variant'];
            
            if (empty($productName)) {
                continue;
            }
            
            // Cari produk platform dengan exact matching untuk variant (sama seperti Tiktok2Import)
            $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                ->where(function ($query) use ($productName, $variation) {
                    if (!empty($variation)) {
                        // Jika ada variant, cari dengan nama produk dan variant yang tepat
                        $query->where('platform_product_name', $productName)
                            ->where('variant', $variation);
                    } else {
                        // Jika tidak ada variant, cari dengan nama produk saja dan variant null/kosong
                        $query->where('platform_product_name', $productName)
                            ->where(function($q) {
                                $q->whereNull('variant')
                                  ->orWhere('variant', '');
                            });
                    }
                })
                ->first();
            
            // Jika tidak ditemukan dengan exact matching, coba dengan format yang berbeda
            // untuk menangani kasus seperti "Product Name - 100ml" vs "Product Name" dengan variant "100ml - PAKET"
            if (!$platformProduct && !empty($variation)) {
                // Coba cari dengan format: nama produk tanpa "- 100ml" dan variant dengan "100ml - " + variant asli
                $baseProductName = preg_replace('/\s*-\s*100ml$/', '', $productName);
                $newVariant = '100ml - ' . $variation;
                
                if ($baseProductName !== $productName) {
                    $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                        ->where('platform_product_name', $baseProductName)
                        ->where('variant', $newVariant)
                        ->first();
                }
            }
            
            // Jika tidak ditemukan dengan exact matching, coba dengan pencarian yang lebih fleksibel
            // TETAPI hanya jika tidak ada variant yang spesifik (sama seperti Tiktok2Import)
            if (!$platformProduct) {
                if (!empty($variation)) {
                    // Jika ada variant spesifik, jangan gunakan flexible search
                    // Biarkan platformProduct tetap null agar bisa ditangani sebagai unmapped product
                } else {
                    // Hanya gunakan flexible search jika tidak ada variant
                    $fullProductName = $productName;
                    $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                        ->where(function ($query) use ($productName, $fullProductName) {
                            // Coba cari dengan nama lengkap
                            $query->where('platform_product_name', $fullProductName)
                                ->orWhere('platform_product_name', 'LIKE', '%'.$fullProductName.'%')
                                // Juga coba cari dengan nama produk saja
                                ->orWhere('platform_product_name', $productName)
                                ->orWhere('platform_product_name', 'LIKE', '%'.$productName.'%')
                                ->orWhere(\DB::raw('LOWER(platform_product_name)'), 'LIKE', '%'.strtolower($productName).'%');
                        })
                        ->first();
                }
            }
            
            // Periksa mapping (sama seperti Tiktok2Import)
            $mappingExists = false;
            if ($platformProduct) {
                $mappingExists = \App\Models\MappingBarang::where('platform_product_id', $platformProduct->id)->exists();
            }
            
            // Jika tidak ada mapping, tambahkan ke unmapped
            if (!$mappingExists) {
                $unmappedProducts[] = $product;
            }
        }
        
        \Log::info('Recalculated unmapped products', [
            'total_products' => count($uniqueProducts),
            'unmapped_count' => count($unmappedProducts),
            'platform_id' => $this->platform->id
        ]);
        
        return $unmappedProducts;
    }
}
