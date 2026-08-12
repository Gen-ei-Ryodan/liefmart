<?php

namespace App\Http\Controllers;

use App\Imports\Shopee2Import;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class Shopee2Controller extends Controller
{
    protected $platform;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            $routeParam = 'shopee liefmarket';
            $this->platform = $this->getPlatformByRouteParam($routeParam);
        } catch (\Exception $e) {
            \Log::error('Exception in Shopee2Controller constructor: ' . $e->getMessage());
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
        
        // 4. Jika masih tidak ditemukan, cari shopee liefmarket secara spesifik
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee%liefmarket%'])->first();
        }
        
        // 5. Jika masih tidak ditemukan, cari shopee tapi hindari lamourad
        if (!$platform) {
            $platform = Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee%'])
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
     * Tampilkan halaman import Excel Shopee Liefmarket
     */
    public function importExcel()
    {
        return view('sales.shopee2.import-excel');
    }

    /**
     * Preview data Excel sebelum import
     */
    public function previewImport(Request $request)
    {
        \Log::info('---------- MULAI PROSES IMPORT SHOPEE LIEF MART ----------');
        
        // Validasi file yang diupload
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,csv',
            ], [
                'excel_file.required' => 'File Excel wajib diupload.',
                'excel_file.file' => 'Upload harus berupa file.',
                'excel_file.mimes' => 'File harus berformat Excel (.xlsx, .xls) atau CSV.',
            ]);
            
            if (!$request->hasFile('excel_file')) {
                \Log::error('Excel file not present in request');
                return redirect()->route('sales.shopee2.import-excel')
                    ->with('error', 'File Excel tidak ditemukan. Pastikan Anda memilih file sebelum mengklik Preview Data.');
            }
            
            // Log file info for debugging
            $file = $request->file('excel_file');
            \Log::info('File info: ', [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize() . ' bytes (' . round($file->getSize() / 1024 / 1024, 2) . ' MB)',
                'mime' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'error' => $file->getError(),
                'valid' => $file->isValid()
            ]);
            
            if (!$file->isValid()) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File melebihi batas upload_max_filesize di php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File melebihi batas MAX_FILE_SIZE yang ditentukan dalam form HTML',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                    UPLOAD_ERR_EXTENSION => 'Sebuah ekstensi PHP menghentikan upload file'
                ];
                
                $errorCode = $file->getError();
                $errorMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Error upload dengan kode: ' . $errorCode;
                
                \Log::error('Invalid file upload: ' . $errorMessage);
                return redirect()->route('sales.shopee2.import-excel')
                    ->with('error', 'Error file upload: ' . $errorMessage);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation exception: ' . json_encode($e->errors()));
            return redirect()->route('sales.shopee2.import-excel')
                ->with('error', $e->errors()['excel_file'][0] ?? 'Error validasi file Excel.');
        } catch (\Exception $e) {
            \Log::error('Validation error in Shopee2 import: ' . $e->getMessage());
            return redirect()->route('sales.shopee2.import-excel')
                ->with('error', 'Error validasi file: ' . $e->getMessage());
        }

        try {
            // Proses file Excel
            $import = new Shopee2Import($this->platform->id);
            \Log::info('Before Excel import for Shopee2');
            
            try {
                Excel::import($import, $request->file('excel_file'));
                \Log::info('After Excel import for Shopee2 - import successful');
            } catch (\Exception $e) {
                \Log::error('Excel import exception: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                
                // Provide more specific error messages based on common issues
                $errorMsg = 'Gagal mengimport file Excel: ' . $e->getMessage();
                
                if (strpos($e->getMessage(), 'identification as Excel') !== false) {
                    $errorMsg = 'File tidak dapat diidentifikasi sebagai file Excel yang valid. Pastikan format file benar.';
                } else if (strpos($e->getMessage(), 'Format header') !== false) {
                    $errorMsg = 'Format header tidak sesuai. Pastikan header sesuai dengan format Shopee2.';
                }
                
                return redirect()->route('sales.shopee2.import-excel')
                    ->with('error', $errorMsg);
            }

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

            // Get orders that contain unmapped products
            $ordersWithUnmappedProducts = $import->getOrdersWithUnmappedProducts();
            \Log::info('Orders with unmapped products: ' . count($ordersWithUnmappedProducts));

            // Informasi tentang baris yang dilewati
            $skippedRows = [
                'Tanggal kosong' => $import->getSkippedForMissingDate(),
                'Hari kosong' => $import->getSkippedForMissingDay(),
                'No Resi kosong' => $import->getSkippedForMissingResi(),
            ];

            // Filter skippedRows yang nilainya 0
            $skippedRows = array_filter($skippedRows);
            \Log::info('Skipped rows: ' . json_encode($skippedRows));

            // Ambil data duplikat dari import
            $duplicateOrders = $import->getDuplicateOrders();
            $duplicateOrdersInFile = $import->getDuplicateOrdersInFile();
            $duplicateOrdersInDatabase = $import->getDuplicateOrdersInDatabase();
            
            if (!empty($duplicateOrders)) {
                \Log::info('Duplicate orders found: ' . $duplicateOrders);
            }
            if (!empty($duplicateOrdersInFile)) {
                \Log::info('Duplicate orders in file: ' . count($duplicateOrdersInFile));
            }
            if (!empty($duplicateOrdersInDatabase)) {
                \Log::info('Duplicate orders in database: ' . count($duplicateOrdersInDatabase));
            }
            
            // Set main category ke Kosmetik sebelum validasi stok
            session(['main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId()]);
            session(['main_category_name' => 'Kosmetik']);
            
            // Debug logging
            $mainCategoryId = \App\Helpers\MainCategoryHelper::getSelectedMainCategoryId();
            \Log::info('PreviewImport - Main Category set to: ' . ($mainCategoryId ?: 'NULL'));
            
            // Validasi lengkap untuk semua kemungkinan error
            $insufficientStockProducts = [];
            $stockErrors = [];
            $mappingErrors = [];
            $dataErrors = [];
            $ordersWithStockIssues = [];
            $stockIssuesSummary = [];
            
            if (!empty($data)) {
                // Hitung total kebutuhan dari seluruh file Excel (bukan per order) - sama seperti TikTok
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
                        // Cari platform product berdasarkan nama dan variasi
                        $platformProduct = \App\Models\PlatformProduct::where('platform_id', $this->platform->id)
                            ->where('platform_product_name', $row['nama_barang'] ?? '')
                            ->where('variant', $row['variasi'] ?? '')
                            ->first();
                        
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
                        $platformProduct = \App\Models\PlatformProduct::find($platformProductId);
                        $mappingErrors[] = [
                            'platform_product_id' => $platformProductId,
                            'platform_product_name' => $platformProduct ? $platformProduct->name : "Unknown",
                            'error' => 'Tidak ada mapping barang untuk platform product ini'
                        ];
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
                
                // Cek stok berdasarkan TOTAL kebutuhan dari seluruh file Excel
                foreach ($totalProductQuantities as $productId => $totalRequiredQty) {
                    $availableStock = \App\Models\WarehouseStock::where('product_id', $productId)
                        ->where('qty', '>', 0)
                        ->sum('qty');
                    
                    // Debug logging untuk stok
                    \Log::info("Preview Stock Check - Product ID: {$productId}, Total Required: {$totalRequiredQty}, Available: {$availableStock}, Main Category: {$mainCategoryId}");
                    
                    if ($availableStock < $totalRequiredQty) {
                        $product = \App\Models\Product::find($productId);
                        $productName = $product ? $product->name : "Product ID: {$productId}";
                        
                        // Cari order mana yang terpengaruh
                        $affectedOrders = [];
                        foreach ($orderProductQuantities as $orderNumber => $products) {
                            if (isset($products[$productId]) && $products[$productId] > 0) {
                                $affectedOrders[] = $orderNumber;
                            }
                        }
                        
                        $stockIssuesSummary[$productId] = [
                            'product_name' => $productName,
                            'total_required' => $totalRequiredQty,
                            'available_qty' => $availableStock,
                            'shortage' => $totalRequiredQty - $availableStock,
                            'affected_orders' => $affectedOrders
                        ];
                        
                        \Log::warning("Preview Stock Check - Insufficient stock detected: {$productName}, Total Required: {$totalRequiredQty}, Available: {$availableStock}, Affected Orders: " . implode(', ', $affectedOrders));
                    }
                }
                
                // Buat detail per order untuk display
                foreach ($orderProductQuantities as $orderNumber => $products) {
                    $orderStockIssues = [];
                    foreach ($products as $productId => $requiredQty) {
                        if (isset($stockIssuesSummary[$productId])) {
                            $product = \App\Models\Product::find($productId);
                            $productName = $product ? $product->name : "Product ID: {$productId}";
                            
                            $orderStockIssues[] = [
                                'product_name' => $productName,
                                'required_qty' => $requiredQty,
                                'available_qty' => $stockIssuesSummary[$productId]['available_qty'],
                                'shortage' => $requiredQty - $stockIssuesSummary[$productId]['available_qty']
                            ];
                        }
                    }
                    
                    if (!empty($orderStockIssues)) {
                        $ordersWithStockIssues[$orderNumber] = $orderStockIssues;
                    }
                }
                
                // Konversi summary ke array untuk display
                $insufficientStockProducts = array_values($stockIssuesSummary);
                
                \Log::info('Insufficient stock products: ' . count($insufficientStockProducts));
                \Log::info('Orders with stock issues: ' . count($ordersWithStockIssues));
                
                // Validasi data tambahan
                foreach ($data as $index => $row) {
                    $rowErrors = [];
                    
                    // Cek tanggal
                    if (empty($row['tanggal'])) {
                        $rowErrors[] = 'Tanggal kosong';
                    }
                    
                    // Cek nomor order
                    if (empty($row['no_order'])) {
                        $rowErrors[] = 'Nomor order kosong';
                    }
                    
                    // Cek quantity
                    if (empty($row['qty']) || $row['qty'] <= 0) {
                        $rowErrors[] = 'Quantity tidak valid';
                    }
                    
                    // Cek harga
                    if (empty($row['harga_setelah_diskon']) || $row['harga_setelah_diskon'] <= 0) {
                        $rowErrors[] = 'Harga tidak valid';
                    }
                    
                    if (!empty($rowErrors)) {
                        $dataErrors[] = [
                            'row' => $index + 1,
                            'order_number' => $row['no_order'] ?? 'N/A',
                            'errors' => $rowErrors
                        ];
                    }
                }
                
                \Log::info('Validation results:', [
                    'insufficient_stock' => count($insufficientStockProducts),
                    'mapping_errors' => count($mappingErrors),
                    'data_errors' => count($dataErrors),
                    'stock_errors' => count($stockErrors)
                ]);
            }

            // Jika ada masalah dengan header, tampilkan pesan error
            if (!empty($headerIssues)) {
                \Log::warning('Header issues detected: ' . implode(', ', $headerIssues));
                return redirect()->route('sales.shopee2.import-excel')
                    ->with('error', 'Format file Excel tidak sesuai: ' . implode(', ', $headerIssues));
            }

            // Jika tidak ada data yang ditemukan, tampilkan error
            if (empty($data)) {
                $errorMessage = 'Tidak ada data yang dapat diproses dari file Excel.';
                if (!empty($skippedRows)) {
                    $errorMessage .= ' Beberapa data dilewati: ' . implode(', ', array_map(function ($value, $key) { 
                        return "$key: $value"; 
                    }, $skippedRows, array_keys($skippedRows)));
                }
                if (!empty($totalRows) && $totalRows > 0) {
                    $errorMessage .= " Total {$totalRows} baris ditemukan dalam file, tetapi tidak ada yang dapat diproses.";
                }
                $errorMessage .= ' Pastikan data memiliki format yang benar dan semua kolom wajib telah diisi.';
                \Log::warning('No data found in import: ' . $errorMessage);
                return redirect()->route('sales.shopee2.import-excel')
                    ->with('error', $errorMessage);
            }

            // Simpan data ke session untuk digunakan di proses import
            session(['preview_data' => $data]);
            session(['unmapped_products' => $unmappedProducts]);
            session(['insufficient_stock_products' => $insufficientStockProducts]);
            session(['orders_with_stock_issues' => $ordersWithStockIssues]);
            session(['stock_issues_summary' => $stockIssuesSummary]);
            session(['stock_errors' => $stockErrors]);
            session(['mapping_errors' => $mappingErrors]);
            session(['data_errors' => $dataErrors]);
            session(['total_rows' => $totalRows]);
            session(['skipped_rows' => $skippedRows]);
            session(['duplicate_orders' => $duplicateOrders]);
            session(['duplicate_orders_in_file' => $duplicateOrdersInFile]);
            session(['duplicate_orders_in_database' => $duplicateOrdersInDatabase]);
            session(['invalid_data' => $invalidData]);
            session(['orders_with_unmapped_products' => $ordersWithUnmappedProducts]);
            
            // Debug logging untuk memastikan data tersimpan
            \Log::info('PreviewImport - Data saved to session:', [
                'preview_data_count' => count($data),
                'unmapped_products_count' => count($unmappedProducts),
                'insufficient_stock_count' => count($insufficientStockProducts),
                'orders_with_stock_issues_count' => count($ordersWithStockIssues),
                'session_id' => session()->getId(),
                'session_saved' => session()->has('preview_data')
            ]);
            
            \Log::info('Data saved to session, redirecting to preview');

            // Tentukan apakah bisa proceed berdasarkan peraturan baru:
            // - Jika ada error selain duplikat maka tidak bisa diproses
            // - Jika hanya duplikat (baik dalam file maupun database) masih bisa diproses dan skip duplikat
            $hasErrors = !empty($invalidData) || 
                        !empty($insufficientStockProducts) || 
                        !empty($unmappedProducts) || 
                        !empty($stockErrors) || 
                        !empty($mappingErrors) || 
                        !empty($dataErrors);
            
            // Duplikat (baik dalam file maupun database) tidak menghalangi proses (akan di-skip)
            // Tetapi stok tidak mencukupi HARUS menghalangi proses
            
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
            
            // Tampilkan preview dengan semua data yang diperlukan
            return view('sales.shopee2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'invalidData' => $invalidData,
                'canProceed' => !$hasErrors,
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
                'duplicateOrdersInFile' => $duplicateOrdersInFile,
                'duplicateOrdersInDatabase' => $duplicateOrdersInDatabase,
                'insufficientStockProducts' => $insufficientStockProducts,
                'ordersWithStockIssues' => $ordersWithStockIssues,
                'stockIssuesSummary' => $stockIssuesSummary,
                'stockErrors' => $stockErrors,
                'mappingErrors' => $mappingErrors,
                'dataErrors' => $dataErrors,
                'ordersWithUnmappedProducts' => $ordersWithUnmappedProducts,
                'hasErrors' => $hasErrors,
                'platformId' => $this->platform->id ?? null
            ])->with('warning', $warningMessage);
        } catch (\Exception $e) {
            \Log::error('Exception in previewImport: ' . $e->getMessage());
            \Log::error('Exception type: ' . get_class($e));
            \Log::error('Line: ' . $e->getLine() . ' in ' . $e->getFile());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->route('sales.shopee2.import-excel')
                ->with('error', 'Terjadi kesalahan saat memproses file: ' . $e->getMessage());
        }
    }

    /**
     * Proses final import data
     */
    public function processImport(Request $request)
    {

        // Ambil data preview dari session
        $data = session('preview_data');
        $unmappedProducts = session('unmapped_products');
        $insufficientStockProducts = session('insufficient_stock_products', []);

        // Debug logging
        \Log::info('ProcessImport - Session data check:', [
            'has_preview_data' => !empty($data),
            'has_unmapped_products' => !empty($unmappedProducts),
            'has_insufficient_stock' => !empty($insufficientStockProducts),
            'session_id' => session()->getId(),
            'all_session_keys' => array_keys(session()->all()),
            'preview_data_type' => gettype($data),
            'preview_data_size' => is_array($data) ? count($data) : 'not_array'
        ]);

        // Jika tidak ada data, kembalikan ke halaman import
        if (! $data) {
            \Log::warning('ProcessImport - No preview data found in session');
            return redirect()->route('sales.shopee2.import-excel')
                ->with('error', 'Data preview tidak ditemukan. Silakan upload file Excel terlebih dahulu.');
        }

        // Jika masih ada produk dengan stok tidak mencukupi, tampilkan error dan tidak bisa import
        if (!empty($insufficientStockProducts)) {
            // Dapatkan info baris yang di-skip dan duplikat dari import
            $import = new Shopee2Import($this->platform->id);
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

            // Buat pesan error yang detail
            $errorDetails = [];
            foreach ($insufficientStockProducts as $product) {
                $errorDetails[] = "• {$product['product_name']}: Butuh {$product['required_qty']} unit, tersedia {$product['available_qty']} unit (kurang " . ($product['required_qty'] - $product['available_qty']) . " unit)";
            }
            
            $errorMessage = '❌ ERROR: Beberapa produk memiliki stok tidak mencukupi. Import tidak dapat dilanjutkan.' . "\n\n" . implode("\n", $errorDetails);

            // Simpan pesan error dalam flash session
            session()->flash('error', $errorMessage);

            return view('sales.shopee2.preview-import', [
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

        try {
            // Set main category ke Kosmetik sebelum import
            session(['main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId()]);
            session(['main_category_name' => 'Kosmetik']);
            
            // Debug logging
            $mainCategoryId = \App\Helpers\MainCategoryHelper::getSelectedMainCategoryId();
            \Log::info('ProcessImport - Main Category set to: ' . ($mainCategoryId ?: 'NULL'));
            
            // Register our modified sales import handler
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = true;
            \Log::info('Enabling order item consolidation for tax_id differences');
            
            // Proses import data
            $import = new Shopee2Import($this->platform->id);

            // Set data yang akan diproses
            $import->setData($data);
            
            // Set unmapped products dari session
            $unmappedProducts = session('unmapped_products', []);
            $import->setUnmappedProducts($unmappedProducts);

            // Jalankan proses import
            $result = $import->processImport();

            // Reset the flag after import
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;

            // Jika sukses, hapus session dan tampilkan pesan sukses
            if ($result['success'] > 0) {
                session()->forget(['preview_data', 'unmapped_products']);

                // Tambahkan informasi tambahan tentang nomor pesanan duplikat
                $successMessage = "Berhasil mengimport {$result['success']} data penjualan Shopee2.";

                if (isset($result['duplicates']) && $result['duplicates'] > 0) {
                    $successMessage .= " {$result['duplicates']} nomor pesanan dilewati karena sudah ada di database.";
                }

                if (isset($result['skipped']) && $result['skipped'] > 0) {
                    $successMessage .= " {$result['skipped']} pesanan dilewati karena tidak memiliki data lengkap.";
                }
                
                if (isset($result['unmapped_skipped']) && $result['unmapped_skipped'] > 0) {
                    $successMessage .= " {$result['unmapped_skipped']} pesanan dilewati karena memiliki produk yang belum dimapping.";
                }

                return redirect()->route('sales.list')
                    ->with('success', $successMessage);
            } else {
                // Jika semua order gagal atau ada error, simpan pesan error dalam flash session
                $errorMessage = 'Gagal mengimport data: '.implode(', ', $result['errors']);
                
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
                \Log::info('Error message set in session: '.$errorMessage);

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
                return view('sales.shopee2.preview-import', [
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
            // Reset the flag in case of error
            \App\Models\WarehouseStock::$consolidateOrderItemsByProduct = false;
            
            \Log::error('Exception in processImport: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            // Simpan pesan error dalam flash session
            $errorMessage = 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage();
            session()->flash('error', $errorMessage);

            // Debug: tambahkan log untuk melihat pesan error
            \Log::info('Exception message set in session: '.$errorMessage);

            // Dapatkan info baris yang di-skip dan duplikat
            $import = new Shopee2Import($this->platform->id);
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

            return view('sales.shopee2.preview-import', [
                'data' => $data,
                'unmappedProducts' => $unmappedProducts,
                'canProceed' => empty($unmappedProducts),
                'invalidData' => [],
                'totalRows' => $totalRows,
                'skippedRows' => $skippedRows,
                'duplicateOrders' => $duplicateOrders,
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
        $duplicateOrdersInFile = session('duplicate_orders_in_file', []);
        $duplicateOrdersInDatabase = session('duplicate_orders_in_database', []);
        $insufficientStockProducts = session('insufficient_stock_products', []);
        $ordersWithStockIssues = session('orders_with_stock_issues', []);
        $stockIssuesSummary = session('stock_issues_summary', []);
        $ordersWithUnmappedProducts = session('orders_with_unmapped_products', []);
        
        // Ensure all duplicate order arrays are actually arrays
        if (!is_array($duplicateOrders)) {
            $duplicateOrders = [];
        }
        if (!is_array($duplicateOrdersInFile)) {
            $duplicateOrdersInFile = [];
        }
        if (!is_array($duplicateOrdersInDatabase)) {
            $duplicateOrdersInDatabase = [];
        }

        // Debug logging
        \Log::info('ShowPreview - Session data check:', [
            'has_preview_data' => !empty($data),
            'has_unmapped_products' => !empty($unmappedProducts),
            'has_insufficient_stock' => !empty($insufficientStockProducts),
            'has_orders_with_stock_issues' => !empty($ordersWithStockIssues),
            'session_id' => session()->getId()
        ]);

        // If no data in session, redirect to import page
        if (!$data) {
            \Log::warning('ShowPreview - No preview data found in session');
            return redirect()->route('sales.shopee2.import-excel')
                ->with('error', 'Tidak ada data preview. Silakan upload file Excel terlebih dahulu melalui menu Import Excel Shopee2.');
        }

        return view('sales.shopee2.preview-import', [
            'data' => $data,
            'unmappedProducts' => $unmappedProducts,
            'invalidData' => $invalidData,
            'canProceed' => empty($invalidData) && empty($insufficientStockProducts) && empty($unmappedProducts),
            'totalRows' => $totalRows,
            'skippedRows' => $skippedRows,
            'duplicateOrders' => $duplicateOrders,
            'duplicateOrdersInFile' => $duplicateOrdersInFile,
            'duplicateOrdersInDatabase' => $duplicateOrdersInDatabase,
            'insufficientStockProducts' => $insufficientStockProducts,
            'ordersWithStockIssues' => $ordersWithStockIssues,
            'stockIssuesSummary' => $stockIssuesSummary,
            'ordersWithUnmappedProducts' => $ordersWithUnmappedProducts,
            'platformId' => $this->platform->id ?? null
        ]);
    }
    
    /**
     * Helper method untuk mendapatkan order yang terpengaruh oleh produk
     */
    private function getOrdersAffectedByProduct($data, $platformProductId)
    {
        $affectedOrders = [];
        foreach ($data as $row) {
            if (isset($row['platform_product_id']) && $row['platform_product_id'] == $platformProductId) {
                $affectedOrders[] = $row['no_order'] ?? 'N/A';
            }
        }
        return array_unique($affectedOrders);
    }
    
    /**
     * Helper method untuk generate warning message
     */
    private function generateWarningMessage($unmappedProducts, $insufficientStockProducts, $stockErrors, $mappingErrors, $dataErrors, $duplicateOrdersInDatabase = [])
    {
        $messages = [];
        
        if (!empty($unmappedProducts)) {
            $messages[] = 'Beberapa produk perlu dimapping terlebih dahulu.';
        }
        
        if (!empty($insufficientStockProducts)) {
            $messages[] = 'Beberapa produk memiliki stok tidak mencukupi.';
        }
        
        if (!empty($mappingErrors)) {
            $messages[] = 'Beberapa produk tidak memiliki mapping barang.';
        }
        
        if (!empty($dataErrors)) {
            $messages[] = 'Beberapa data memiliki format yang tidak valid.';
        }
        
        if (!empty($stockErrors)) {
            $messages[] = 'Ada masalah dengan ketersediaan stok.';
        }
        
        if (!empty($duplicateOrdersInDatabase)) {
            $messages[] = 'Beberapa order sudah ada di database dan akan di-skip.';
        }
        
        return !empty($messages) ? implode(' ', $messages) : null;
    }
}
