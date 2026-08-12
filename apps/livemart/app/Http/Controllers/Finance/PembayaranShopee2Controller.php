<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Shopee2FinancialTransaction;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use App\Models\InvoiceSequence;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Shopee2FinanceAnalyticsExport;
use App\Exports\Shopee2CashFlowExport;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AdjustmentHistory;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class PembayaranShopee2Controller extends Controller
{
    public function index(Request $request)
    {
        // Get platform by name (Shopee Liefmarket)
        $platformModel = Platform::whereRaw('LOWER(name) = ?', ['shopee liefmarket'])->first();
        
        // Jika tidak ditemukan berdasarkan nama, cari dengan LIKE
        if (!$platformModel) {
            $platformModel = Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee liefmarket%'])->first();
        }
        
        if (!$platformModel) {
            return redirect()->back()->with('error', 'Platform Shopee Liefmarket tidak ditemukan');
        }
        $platformId = $platformModel->id;
        $platform = 'shopee2'; 
        $platformLabel = 'Shopee Liefmarket';
        
        $query = Shopee2FinancialTransaction::with([
            'order.orderItems.platformProduct.mappingBarang', 
            'order.orderItems.warehouseStock.tax', 
            'order.mainCategory'
        ]);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date
        if ($request->filled('payment_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range
        if ($request->filled('min_nominal')) {
            $query->where('nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                $query->where('outstanding', 0);
            } elseif ($request->outstanding_status === '1') {
                $query->where(function($q) {
                    $q->where('outstanding', '>', 0)
                      ->orWhere('outstanding', '<', 0);
                });
            }
        }
        
        // Get all transactions matching the query (without pagination yet)
        $allTransactions = $query->orderBy('tanggal_order', 'desc')
            ->orderBy('no_order', 'desc')
            ->get();
        
        // Filter out fully returned orders from the results
        $filteredCollection = $allTransactions->filter(function($transaction) {
            // Skip transactions whose orders are fully returned
            if ($transaction->order && $transaction->order->isFullyReturned()) {
                return false;
            }
            return true;
        });
        
        // Calculate totals for cards from FILTERED data (after fully-returned filter)
        $totalCount = $filteredCollection->count();
        $totalNominalFix = $filteredCollection->sum('nominal_fix');
        $totalSaldoMasuk = $filteredCollection->sum('saldo_masuk');
        $totalOutstanding = $filteredCollection->sum('outstanding');
        
        // Paginate the filtered results for display
        $perPage = 50;
        $currentPage = Paginator::resolveCurrentPage('page');
        $currentPageItems = $filteredCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        $transactions = new LengthAwarePaginator(
            $currentPageItems,
            $filteredCollection->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );
        
        // Get all orders that don't have financial transactions
        $missingOrdersQuery = Order::with(['orderItems', 'orderItems.platformProduct.mappingBarang'])
            ->whereDoesntHave('shopee2FinancialTransactions')
            ->where('platform_id', $platformId);

        // Apply the order number filter to unpaid orders as well.
        if ($request->filled('order_number')) {
            $missingOrdersQuery->where('order_number', 'like', '%' . $request->order_number . '%');
        }

        $missingOrders = $missingOrdersQuery
            ->orderBy('tanggal', 'desc') // Use tanggal instead of order_date
            ->get()
            ->filter(function($order) {
                // Filter out fully returned orders
                return !$order->isFullyReturned();
            });
            
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');
        
        // Get all tax categories for filter dropdown
        $taxCategories = DB::table('tax_categories')
            ->orderBy('id')
            ->get();
        
        return view('financial.shopee2.index', compact(
            'transactions', 
            'groupedTransactions', 
            'platform', 
            'platformLabel',
            'missingOrders',
            'totalCount', 
            'totalNominalFix', 
            'totalSaldoMasuk', 
            'totalOutstanding',
            'taxCategories'
        ));
    }

    public function importForm()
    {
        return view('financial.shopee2.import');
    }

    public function preview(Request $request)
    {
        // If this is a GET request, check if we have data in the session
        if ($request->isMethod('get')) {
            if (!session()->has('shopee2_import_data')) {
                return redirect()->route('finance.shopee2.import')
                    ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
            }
            
            // Get the data from the session
            $data = session('shopee2_import_data');
            $previewData = session('shopee2_preview_data');
            $previewHeaders = session('shopee2_preview_headers');
            $headerLabels = session('shopee2_header_labels');
            $issues = session('shopee2_issues');
            $totalRows = session('shopee2_total_rows');
            $validRows = session('shopee2_valid_rows');
            $invalidRows = session('shopee2_invalid_rows');
            
            Log::info("preview GET: previewData is " . (is_array($previewData) ? "array with " . count($previewData) . " items" : "not an array"));
            
            // If any of the required data is missing, redirect to import
            if (!$previewData || !$previewHeaders || !$headerLabels) {
                return redirect()->route('finance.shopee2.import')
                    ->with('error', 'Data preview tidak lengkap. Silakan upload file kembali.');
            }
            
            return view('financial.shopee2.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
        }
        
        // For POST requests, validate and process the file
        $request->validate([
            'file' => 'required|mimes:xlsx,xls', // No file size limit
        ]);
    
        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $data = [];
        $headers = [];
        $issues = [];
        
        try {
            // Increase execution time and memory for large files
            set_time_limit(1200); // 20 minutes for better performance
            ini_set('memory_limit', '2048M'); // Increased memory limit to 2GB
            
            // Log file processing start
            Log::info('Starting Shopee2 financial import preview', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);
            
            // Membaca file Excel tanpa batasan
            $reader = IOFactory::createReader(IOFactory::identify($path));
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($path);
            
            // Mencari sheet Income (case-insensitive)
            $incomeSheet = null;
            $availableSheets = [];
            
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetTitle = $sheet->getTitle();
                $availableSheets[] = $sheetTitle;
                
                // Check for Income sheet (case-insensitive)
                if (strtolower($sheetTitle) === 'income') {
                    $incomeSheet = $sheet;
                    break;
                }
            }
            
            // Log available sheets for debugging
            Log::info('Available sheets in Excel file: ' . implode(', ', $availableSheets));
            
            if (!$incomeSheet) {
                // Free memory
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
                $availableSheetsText = implode(', ', $availableSheets);
                $errorMessage = 'Sheet "Income" tidak ditemukan dalam file Excel.';
                
                if (!empty($availableSheets)) {
                    $errorMessage .= ' Sheet yang tersedia: ' . $availableSheetsText . '.';
                } else {
                    $errorMessage .= ' File tidak memiliki sheet yang dapat dibaca.';
                }
                
                $errorMessage .= ' Pastikan file Excel memiliki sheet dengan nama "Income" (tidak case-sensitive).';
                
                return redirect()->back()->with('error', $errorMessage);
            }
            
            // Get highest row and column to limit processing
            $highestRow = $incomeSheet->getHighestRow();
            $highestColumn = $incomeSheet->getHighestColumn();
            
            Log::info("Excel file dimensions: {$highestRow} rows x {$highestColumn} columns");
            
            // No limit on file size - process all rows
            Log::info("Processing all {$highestRow} rows from Excel file");
            
            $worksheet = $incomeSheet;
            
            // Ambil header (baris pertama)
            $highestColumn = $worksheet->getHighestDataColumn();
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
            
            // Bersihkan header dari spasi berlebih
            $headers = array_map('trim', $headerRow);
            
            // Map for alternate header names (lowercase for case insensitive comparison)
            $headerMapping = [
                'nomor pesanan' => 'NOMOR PESANAN',
                'no pesanan' => 'NOMOR PESANAN',
                'no. pesanan' => 'NOMOR PESANAN',
                'no order' => 'NOMOR PESANAN',
                'no. order' => 'NOMOR PESANAN',
                'order number' => 'NOMOR PESANAN',
                
                'tanggal masuk pembayaran' => 'TANGGAL MASUK PEMBAYARAN',
                'tanggal pembayaran' => 'TANGGAL MASUK PEMBAYARAN',
                'payment date' => 'TANGGAL MASUK PEMBAYARAN',
                'tgl masuk pembayaran' => 'TANGGAL MASUK PEMBAYARAN',
                'tgl pembayaran' => 'TANGGAL MASUK PEMBAYARAN',
                'tanggal' => 'TANGGAL MASUK PEMBAYARAN',
                
                'hari masuk pembayaran' => 'HARI MASUK PEMBAYARAN',
                'hari pembayaran' => 'HARI MASUK PEMBAYARAN',
                'payment day' => 'HARI MASUK PEMBAYARAN',
                'hari' => 'HARI MASUK PEMBAYARAN',
                
                'jumlah masuk pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                'jumlah pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                'amount' => 'JUMLAH MASUK PEMBAYARAN',
                'jumlah' => 'JUMLAH MASUK PEMBAYARAN',
                'nominal pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                
                'voucher ditanggung penjual' => 'Voucher Ditanggung Penjual',
                'voucher penjual' => 'Voucher Ditanggung Penjual',
                'voucher' => 'Voucher Ditanggung Penjual',
                
                'komisi ams/affiliate' => 'KOMISI AMS/AFFILIATE',
                'komisi ams' => 'KOMISI AMS/AFFILIATE',
                'komisi affiliate' => 'KOMISI AMS/AFFILIATE',
                'ams/affiliate' => 'KOMISI AMS/AFFILIATE',
                'ams' => 'KOMISI AMS/AFFILIATE',
                'affiliate' => 'KOMISI AMS/AFFILIATE',
                
                'biaya admin' => 'BIAYA ADMIN',
                'admin fee' => 'BIAYA ADMIN',
                'admin' => 'BIAYA ADMIN',
                
                'biaya layanan (gratis ongkir + cashback)' => 'BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)',
                'biaya layanan' => 'BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)',
                'biaya layanan/ biaya 5' => 'BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)',
                'layanan' => 'BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)',
                
                'diskon 5' => 'DISKON 5',
                'biaya 5' => 'DISKON 5',
                'diskon5' => 'DISKON 5',
                'biaya5' => 'DISKON 5',
                
                'diskon 6' => 'DISKON 6',
                'biaya 6' => 'DISKON 6',
                'diskon6' => 'DISKON 6',
                'biaya6' => 'DISKON 6',
                
                'diskon 7' => 'DISKON 7',
                'biaya 7' => 'DISKON 7',
                'diskon7' => 'DISKON 7',
                'biaya7' => 'DISKON 7',
                
                'diskon 8' => 'DISKON 8',
                'biaya 8' => 'DISKON 8',
                'diskon8' => 'DISKON 8',
                'biaya8' => 'DISKON 8',
                
                'diskon 9' => 'DISKON 9',
                'biaya 9' => 'DISKON 9',
                'diskon9' => 'DISKON 9',
                'biaya9' => 'DISKON 9',
                
                'diskon 10' => 'DISKON 10',
                'biaya 10' => 'DISKON 10',
                'diskon10' => 'DISKON 10',
                'biaya10' => 'DISKON 10',
                
                'diskon 11' => 'DISKON 11',
                'biaya 11' => 'DISKON 11',
                'diskon11' => 'DISKON 11',
                'biaya11' => 'DISKON 11',
                
                'diskon 12' => 'DISKON 12',
                'biaya 12' => 'DISKON 12',
                'diskon12' => 'DISKON 12',
                'biaya12' => 'DISKON 12',
            ];
            
            // Map the headers to standard names
            $mappedHeaders = [];
            foreach ($headers as $index => $header) {
                // Convert to lowercase for case-insensitive matching
                $lowerHeader = strtolower($header);
                if (isset($headerMapping[$lowerHeader])) {
                    $mappedHeaders[$index] = $headerMapping[$lowerHeader];
                    Log::info("Mapped header '{$header}' to '{$headerMapping[$lowerHeader]}'");
                } else {
                    $mappedHeaders[$index] = $header;
                }
            }
            
            // Replace original headers with mapped ones for further processing
            $headers = $mappedHeaders;
            Log::info("Mapped headers: " . json_encode($headers));
            
            // Validasi header (hanya kolom esensial wajib)
            $requiredHeaders = [
                'NOMOR PESANAN',
                'TANGGAL MASUK PEMBAYARAN',
                'HARI MASUK PEMBAYARAN',
                'JUMLAH MASUK PEMBAYARAN'
            ];
            
            $missingHeaders = [];
            foreach ($requiredHeaders as $requiredHeader) {
                if (!in_array($requiredHeader, $headers)) {
                    $missingHeaders[] = $requiredHeader;
                }
            }
            
            if (!empty($missingHeaders)) {
                return redirect()->back()->with('error', 'Format file tidak sesuai. Kolom yang tidak ditemukan: ' . implode(', ', $missingHeaders));
            }
            
            // Dapatkan kolom opsional
            $hasDiskon5 = in_array('DISKON 5', $headers);
            $hasDiskon6 = in_array('DISKON 6', $headers);
            
            // Dapatkan index kolom
            $columnIndices = [];
            foreach ($headers as $index => $header) {
                $columnIndices[$header] = $index;
            }
            
            // Dapatkan data dari Excel
            $rows = $worksheet->toArray();
            
            // Lewati baris header
            array_shift($rows);
            
            // Baca data
            $rowNumber = 1;
            $previewData = [];
            
            foreach ($rows as $row) {
                $rowNumber++;
                
                // Skip baris kosong
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Pastikan jumlah kolom sesuai
                if (count($row) >= count($requiredHeaders)) {
                    // Buat array data berdasarkan header
                    $rowData = [];
                    foreach ($headers as $index => $header) {
                        if (isset($row[$index])) {
                            $rowData[$header] = $row[$index];
                        }
                    }
                    
                    // Validasi data per baris
                    $rowIssues = [];
                    
                    // 1. Validasi nomor pesanan
                    if (empty($rowData['NOMOR PESANAN'])) {
                        $rowIssues[] = 'Nomor pesanan kosong';
                    }
                    
                    // 2. Validasi format tanggal
                    if (!empty($rowData['TANGGAL MASUK PEMBAYARAN'])) {
                        // Jika tanggal dalam format objek Excel
                        if ($rowData['TANGGAL MASUK PEMBAYARAN'] instanceof \DateTime) {
                            $rowData['TANGGAL MASUK PEMBAYARAN'] = $rowData['TANGGAL MASUK PEMBAYARAN']->format('Y-m-d');
                        } else {
                            // Coba parse string tanggal
                            $date = \DateTime::createFromFormat('Y-m-d', $rowData['TANGGAL MASUK PEMBAYARAN']);
                            if (!$date) {
                                $rowIssues[] = 'Format tanggal tidak valid (Format yang benar: YYYY-MM-DD)';
                            }
                        }
                    } else {
                        $rowIssues[] = 'Tanggal pembayaran kosong';
                    }
                    
                    // 3. Validasi hari pembayaran
                    if (empty($rowData['HARI MASUK PEMBAYARAN'])) {
                        $rowIssues[] = 'Hari pembayaran kosong';
                    }
                    
                    // 4. Validasi jumlah pembayaran - allow 0 values
                    if (!isset($rowData['JUMLAH MASUK PEMBAYARAN'])) {
                        $rowIssues[] = 'Jumlah pembayaran tidak ditemukan';
                    } elseif ($rowData['JUMLAH MASUK PEMBAYARAN'] === '') {
                        $rowIssues[] = 'Jumlah pembayaran kosong';
                    }
                    
                    // 5. Cek order di database
                    $order = Order::where('order_number', $rowData['NOMOR PESANAN'])->first();
                    if (!$order) {
                        $rowIssues[] = 'Nomor order tidak ditemukan di database';
                    } else {
                        // 6. Cek jika transaksi sudah ada
                        $transactionExists = Shopee2FinancialTransaction::where('no_order', $rowData['NOMOR PESANAN'])->exists();
                        if ($transactionExists) {
                            $rowIssues[] = 'Transaksi untuk order ini sudah ada';
                        } else {
                            // Get order items with their warehouse stocks
                            $orderItems = $order->orderItems()->with('warehouseStock')->get();
                            
                            if ($orderItems->isEmpty()) {
                                $rowIssues[] = 'Order tidak memiliki item';
                            } else {
                                // Order is valid, create a simple data structure for preview
                                $orderData = [
                                    'tanggal_order' => $order->tanggal ? $order->tanggal->format('Y-m-d') : 'N/A',
                                    'hari_order' => $order->hari ?? 'N/A',
                                    'no_order' => $rowData['NOMOR PESANAN'],
                                    'saldo_masuk' => (float) $rowData['JUMLAH MASUK PEMBAYARAN'],
                                    'tanggal_masuk_pembayaran' => $rowData['TANGGAL MASUK PEMBAYARAN'],
                                    'hari_masuk_pembayaran' => $rowData['HARI MASUK PEMBAYARAN'],
                                    'invoices' => [],
                                ];
                                
                                // Create invoice entries in the preview data
                                $invoice = [
                                    'no_invoice' => 'PREVIEW-' . $rowData['NOMOR PESANAN'],
                                    'tax_id' => 'AUTO',
                                    'is_pkp' => true,
                                ];
                                
                                // Calculate total quantity across all order items
                                $totalQty = $order->orderItems->sum('quantity');
                                $invoice['qty'] = $totalQty;
                                
                                // Calculate total invoice value (price_after_discount × quantity)
                                $totalInvoiceValue = 0;
                                foreach ($order->orderItems as $item) {
                                    $totalInvoiceValue += $item->price_after_discount * $item->quantity;
                                }
                                $invoice['nominal_harga'] = $totalInvoiceValue;
                                
                                // Kolom biaya opsional default 0 jika tidak tersedia
                                $invoice['nominal_diskon1'] = !empty($rowData['Voucher Ditanggung Penjual']) ? -abs((float) $rowData['Voucher Ditanggung Penjual']) : 0;
                                $invoice['nominal_diskon2'] = !empty($rowData['KOMISI AMS/AFFILIATE']) ? -abs((float) $rowData['KOMISI AMS/AFFILIATE']) : 0;
                                $invoice['nominal_diskon3'] = !empty($rowData['BIAYA ADMIN']) ? -abs((float) $rowData['BIAYA ADMIN']) : 0;
                                $invoice['nominal_diskon4'] = !empty($rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) ? -abs((float) $rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) : 0;
                                $invoice['nominal_diskon5'] = !empty($rowData['DISKON 5']) ? -abs((float) $rowData['DISKON 5']) : 0;
                                $invoice['nominal_diskon6'] = !empty($rowData['DISKON 6']) ? -abs((float) $rowData['DISKON 6']) : 0;
                                $invoice['nominal_diskon7'] = !empty($rowData['DISKON 7']) ? -abs((float) $rowData['DISKON 7']) : 0;
                                $invoice['nominal_diskon8'] = !empty($rowData['DISKON 8']) ? -abs((float) $rowData['DISKON 8']) : 0;
                                $invoice['nominal_diskon9'] = !empty($rowData['DISKON 9']) ? -abs((float) $rowData['DISKON 9']) : 0;
                                $invoice['nominal_diskon10'] = !empty($rowData['DISKON 10']) ? -abs((float) $rowData['DISKON 10']) : 0;
                                $invoice['nominal_diskon11'] = !empty($rowData['DISKON 11']) ? -abs((float) $rowData['DISKON 11']) : 0;
                                $invoice['nominal_diskon12'] = !empty($rowData['DISKON 12']) ? -abs((float) $rowData['DISKON 12']) : 0;
                                $invoice['adjustment'] = 0;
                                
                                // Calculate nominal_fix for display
                                $invoice['nominal_fix'] = $invoice['nominal_harga'] + 
                                    $invoice['nominal_diskon1'] + 
                                    $invoice['nominal_diskon2'] + 
                                    $invoice['nominal_diskon3'] + 
                                    $invoice['nominal_diskon4'] + 
                                    $invoice['nominal_diskon5'] + 
                                    $invoice['nominal_diskon6'] + 
                                    $invoice['nominal_diskon7'] + 
                                    $invoice['nominal_diskon8'] + 
                                    $invoice['nominal_diskon9'] + 
                                    $invoice['nominal_diskon10'] + 
                                    $invoice['nominal_diskon11'] + 
                                    $invoice['nominal_diskon12'] + 
                                    $invoice['adjustment'];
                                
                                $invoice['saldo_masuk'] = (float) $rowData['JUMLAH MASUK PEMBAYARAN'];
                                $invoice['outstanding'] = $invoice['nominal_fix'] - $invoice['saldo_masuk'];
                                
                                $orderData['invoices'][] = $invoice;
                                
                                // Add the order data to the preview data
                                $previewData[] = $orderData;
                                Log::info("Added order data for order {$orderData['no_order']} with " . count($orderData['invoices']) . " invoices");
                            }
                        }
                    }
                    
                    // Tambahkan issues jika ada
                    if (!empty($rowIssues)) {
                        $issues[$rowNumber] = $rowIssues;
                    }
                    
                    // Tambahkan status validasi ke data
                    $rowData['_valid'] = empty($rowIssues);
                    $rowData['_issues'] = $rowIssues;
                    $rowData['_row'] = $rowNumber;
                    
                    $data[] = $rowData;
                } else {
                    $issues[$rowNumber] = ['Jumlah kolom tidak sesuai dengan header'];
                }
            }
        } catch (\Exception $e) {
            Log::error('Shopee2 import error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage() . ' (Check log for details)');
        }
        
        // Hitung statistik
        $totalRows = count($data);
        $validRows = count(array_filter($data, function($row) { return $row['_valid']; }));
        $invalidRows = $totalRows - $validRows;
        
        // Define kolom tampilan untuk preview
        $previewHeaders = [
            'tanggal_order', 'hari_order', 'no_order', 'invoices',
            'tanggal_masuk_pembayaran', 'hari_masuk_pembayaran'
        ];
        
        // Label header yang lebih user-friendly
        $headerLabels = [
            'tanggal_order' => 'Tanggal Order',
            'hari_order' => 'Hari Order',
            'no_order' => 'No. Order',
            'invoices' => 'Invoices',
            'tanggal_masuk_pembayaran' => 'Tanggal Masuk Pembayaran',
            'hari_masuk_pembayaran' => 'Hari Masuk Pembayaran'
        ];
        
        // Save all data to session
        session(['shopee2_import_data' => $data]);
        session(['shopee2_import_issues' => $issues]);
        session(['shopee2_preview_data' => $previewData]);
        session(['shopee2_preview_headers' => $previewHeaders]);
        session(['shopee2_header_labels' => $headerLabels]);
        session(['shopee2_total_rows' => $totalRows]);
        session(['shopee2_valid_rows' => $validRows]);
        session(['shopee2_invalid_rows' => $invalidRows]);
        
        // Generate and store process token for secure processing
        $processToken = uniqid('shopee2_', true);
        session(['shopee2_process_token' => $processToken]);
        
        return view('financial.shopee2.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
    }

    public function importProcess(Request $request)
    {
        return $this->process($request);
    }

    public function process(Request $request)
    {
        try {
            // Increase execution time limit for large imports
            set_time_limit(1200); // 20 minutes for better performance
            ini_set('memory_limit', '2048M'); // Increased memory limit to 2GB
            
            Log::info('Starting Shopee2 financial import process');
            
            // Validate process token
            $processToken = $request->input('process_token');
            if (!$processToken || $processToken !== session('shopee2_process_token')) {
                return redirect()->route('finance.shopee2.import')
                    ->with('error', 'Token proses tidak valid. Silakan upload ulang file.');
            }
            
            // Get data from session instead of POST data
            $importData = session('shopee2_import_data');
            if (!$importData) {
                return redirect()->route('finance.shopee2.import')
                    ->with('error', 'Data import tidak ditemukan. Silakan upload ulang file.');
            }
            
            // Filter only valid data
            $validData = array_filter($importData, function($rowData) {
                return isset($rowData['_valid']) && $rowData['_valid'] === true;
            });
            
            if (empty($validData)) {
                return redirect()->route('finance.shopee2.import')
                    ->with('error', 'Tidak ada data valid untuk diproses.');
            }
            
            DB::beginTransaction();
            $importCount = 0;
            $skippedCount = 0;
            $skippedReasons = [];
            
            // Process in smaller batches to avoid memory issues
            $batchSize = 10; // Much smaller batch size to avoid SQL statement length issues
            $batches = array_chunk($validData, $batchSize);
            
            Log::info('Processing ' . count($validData) . ' valid records in ' . count($batches) . ' batches');
            
            // Collect all order numbers first for batch queries
            $allOrderNumbers = [];
            $processedRows = [];
            
            foreach ($batches as $batchIndex => $batch) {
                Log::info('Processing batch ' . ($batchIndex + 1) . ' of ' . count($batches));
                
                foreach ($batch as $index => $rowData) {
                    // Skip if no order number provided
                    if (!isset($rowData['NOMOR PESANAN']) || empty($rowData['NOMOR PESANAN'])) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Nomor order kosong";
                        Log::warning("Skipping row - missing order number");
                        continue;
                    }
                    
                    $allOrderNumbers[] = $rowData['NOMOR PESANAN'];
                    $processedRows[] = [
                        'index' => $index,
                        'rowData' => $rowData
                    ];
                }
            }
            
            // Batch query: Get all orders and existing transactions
            $orders = [];
            $existingTransactions = [];
            
            if (!empty($allOrderNumbers)) {
                // Remove duplicates
                $allOrderNumbers = array_unique($allOrderNumbers);
                
                // Get all orders with their items in one query
                $orders = Order::whereIn('order_number', $allOrderNumbers)
                    ->with(['orderItems.warehouseStock'])
                    ->get()
                    ->keyBy('order_number');
                
                // Get all existing transactions in one query
                $existingTransactions = Shopee2FinancialTransaction::whereIn('no_order', $allOrderNumbers)
                    ->pluck('no_order')
                    ->toArray();
                
                Log::info("Batch loaded " . count($orders) . " orders and " . count($existingTransactions) . " existing transactions");
            }
            
            // Process rows with pre-loaded data
            $validOrders = [];
            
            foreach ($processedRows as $rowInfo) {
                $index = $rowInfo['index'];
                $rowData = $rowInfo['rowData'];
                
                try {
                    $orderNumber = $rowData['NOMOR PESANAN'];
                    
                    // Check if order exists in pre-loaded data
                    if (!isset($orders[$orderNumber])) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} tidak ditemukan di database";
                        Log::warning("Skipping order {$orderNumber} - order not found in database");
                        continue;
                    }
                    
                    $order = $orders[$orderNumber];

                    // PERBAIKAN: Skip order yang sudah diretur penuh (retur draft/selesai)
                    // agar transaksi finance tidak dibuat setelah retur full diproses
                    if ($order->isFullyReturned()) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} sudah diretur penuh";
                        Log::warning("Skipping order {$orderNumber} - order is fully returned");
                        continue;
                    }

                    // Check if a transaction with this order number already exists
                    if (in_array($orderNumber, $existingTransactions)) {
                        // Skip this order since a transaction already exists
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} sudah memiliki transaksi";
                        Log::warning("Skipping order {$orderNumber} - transaction already exists");
                        continue;
                    }
                    
                    // Check for required data
                    if (!isset($rowData['TANGGAL MASUK PEMBAYARAN']) || empty($rowData['TANGGAL MASUK PEMBAYARAN'])) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} - tanggal masuk pembayaran kosong";
                        Log::warning("Skipping order {$orderNumber} - missing payment date");
                        continue;
                    }
                    
                    if (!isset($rowData['HARI MASUK PEMBAYARAN']) || empty($rowData['HARI MASUK PEMBAYARAN'])) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} - hari masuk pembayaran kosong";
                        Log::warning("Skipping order {$orderNumber} - missing payment day");
                        continue;
                    }
                    
                    // Get order items (already loaded with warehouse stocks)
                    $orderItems = $order->orderItems;
                    
                    if ($orderItems->isEmpty()) {
                        $skippedCount++;
                        $skippedReasons[] = "Baris #" . ($index + 1) . ": Order {$orderNumber} tidak memiliki item";
                        Log::warning("Skipping order {$orderNumber} - no order items found");
                        continue;
                    }
                    
                    // Order is valid, add to the list of valid orders
                    $validOrders[$order->id] = [
                        'order' => $order,
                        'rowData' => $rowData,
                    ];
                    
                } catch (\Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Baris #" . ($index + 1) . ": Error - " . $e->getMessage();
                    Log::error("Error processing row " . ($index + 1) . ": " . $e->getMessage());
                    Log::error($e->getTraceAsString());
                    continue;
                }
            }
            
            // Process valid orders with bulk operations
            $totalValidOrders = count($validOrders);
            $processedCount = 0;
            $transactionsToInsert = [];
            
            Log::info("Processing $totalValidOrders valid orders");
            
            foreach ($validOrders as $orderId => $orderData) {
                $processedCount++;
                if ($processedCount % 50 == 0) {
                    Log::info("Processed $processedCount of $totalValidOrders orders");
                    // Force garbage collection every 50 orders to free memory
                    gc_collect_cycles();
                }
                
                $order = $orderData['order'];
                $rowData = $orderData['rowData'];
                
                try {
                    // Calculate total quantity across all order items
                    $totalQty = $order->orderItems->sum('quantity');
                    
                    // Calculate total invoice value (price_after_discount × quantity)
                    $totalInvoiceValue = 0;
                    foreach ($order->orderItems as $item) {
                        $totalInvoiceValue += $item->price_after_discount * $item->quantity;
                    }
                    
                    // Process discount values from the import data
                    $nominal_diskon1 = !empty($rowData['Voucher Ditanggung Penjual']) ? -abs((float)$rowData['Voucher Ditanggung Penjual']) : 0;
                    $nominal_diskon2 = !empty($rowData['KOMISI AMS/AFFILIATE']) ? -abs((float)$rowData['KOMISI AMS/AFFILIATE']) : 0;
                    $nominal_diskon3 = !empty($rowData['BIAYA ADMIN']) ? -abs((float)$rowData['BIAYA ADMIN']) : 0;
                    $nominal_diskon4 = !empty($rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) ? -abs((float)$rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) : 0;
                    $nominal_diskon5 = !empty($rowData['DISKON 5']) ? -abs((float)$rowData['DISKON 5']) : 0;
                    $nominal_diskon6 = !empty($rowData['DISKON 6']) ? -abs((float)$rowData['DISKON 6']) : 0;
                    $nominal_diskon7 = !empty($rowData['DISKON 7']) ? -abs((float)$rowData['DISKON 7']) : 0;
                    $nominal_diskon8 = !empty($rowData['DISKON 8']) ? -abs((float)$rowData['DISKON 8']) : 0;
                    $nominal_diskon9 = !empty($rowData['DISKON 9']) ? -abs((float)$rowData['DISKON 9']) : 0;
                    $nominal_diskon10 = !empty($rowData['DISKON 10']) ? -abs((float)$rowData['DISKON 10']) : 0;
                    $nominal_diskon11 = !empty($rowData['DISKON 11']) ? -abs((float)$rowData['DISKON 11']) : 0;
                    $nominal_diskon12 = !empty($rowData['DISKON 12']) ? -abs((float)$rowData['DISKON 12']) : 0;
                    
                    $saldo_masuk = (float)$rowData['JUMLAH MASUK PEMBAYARAN'];
                    
                    // Calculate nominal_fix and outstanding
                    $nominal_fix = $totalInvoiceValue + $nominal_diskon1 + $nominal_diskon2 + $nominal_diskon3 + 
                                  $nominal_diskon4 + $nominal_diskon5 + $nominal_diskon6 + $nominal_diskon7 + 
                                  $nominal_diskon8 + $nominal_diskon9 + $nominal_diskon10 + $nominal_diskon11 + $nominal_diskon12;
                    
                    $outstanding = $nominal_fix - $saldo_masuk;
                    
                    // Add validation for potential overpayment scenarios
                    if ($saldo_masuk > 0 && $nominal_fix > 0) {
                        $ratio = $saldo_masuk / $nominal_fix;
                        if ($ratio > 3.0) { // If payment is more than 3x the expected amount
                            Log::warning("Potential overpayment detected for order {$order->order_number}: saldo_masuk={$saldo_masuk}, nominal_fix={$nominal_fix}");
                        }
                    }
                    
                    // Prepare transaction data for bulk insert
                    $transactionsToInsert[] = [
                        'tanggal_order' => $order->tanggal,
                        'hari_order' => $order->hari,
                        'no_order' => $order->order_number,
                        'no_invoice' => $this->generateInvoiceForOrder($order),
                        'order_id' => $order->id,
                        'qty' => $totalQty,
                        'nominal_harga' => $totalInvoiceValue,
                        'nominal_diskon1' => $nominal_diskon1,
                        'nominal_diskon2' => $nominal_diskon2,
                        'nominal_diskon3' => $nominal_diskon3,
                        'nominal_diskon4' => $nominal_diskon4,
                        'nominal_diskon5' => $nominal_diskon5,
                        'nominal_diskon6' => $nominal_diskon6,
                        'nominal_diskon7' => $nominal_diskon7,
                        'nominal_diskon8' => $nominal_diskon8,
                        'nominal_diskon9' => $nominal_diskon9,
                        'nominal_diskon10' => $nominal_diskon10,
                        'nominal_diskon11' => $nominal_diskon11,
                        'nominal_diskon12' => $nominal_diskon12,
                        'tanggal_masuk_pembayaran' => $rowData['TANGGAL MASUK PEMBAYARAN'],
                        'hari_masuk_pembayaran' => $rowData['HARI MASUK PEMBAYARAN'],
                        'saldo_masuk' => $saldo_masuk,
                        'nominal_fix' => $nominal_fix,
                        'outstanding' => $outstanding,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                } catch (\Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Order {$order->order_number}: Error - " . $e->getMessage();
                    Log::error("Error creating transaction for order {$order->order_number}: " . $e->getMessage());
                    Log::error($e->getTraceAsString());
                    continue;
                }
            }
            
            // Bulk insert all transactions with error handling
            if (!empty($transactionsToInsert)) {
                Log::info("Bulk inserting " . count($transactionsToInsert) . " transactions");
                try {
                    Shopee2FinancialTransaction::insert($transactionsToInsert);
                    $importCount = count($transactionsToInsert);
                    Log::info("Successfully bulk inserted $importCount transactions");
                } catch (\Exception $e) {
                    Log::error('Bulk insert failed, trying individual inserts', [
                        'error' => $e->getMessage(),
                        'count' => count($transactionsToInsert)
                    ]);
                    
                    // Fallback to individual inserts if bulk insert fails
                    $importCount = 0;
                    foreach ($transactionsToInsert as $transaction) {
                        try {
                            Shopee2FinancialTransaction::create($transaction);
                            $importCount++;
                        } catch (\Exception $individualError) {
                            Log::error('Individual insert failed', [
                                'error' => $individualError->getMessage(),
                                'transaction' => $transaction
                            ]);
                            $skippedCount++;
                            $skippedReasons[] = "Failed to insert transaction for order: " . $transaction['no_order'];
                        }
                    }
                }
            }
            
            DB::commit();
            
            // Force garbage collection to free memory after processing
            gc_collect_cycles();
            
            // Clean up session data after successful processing
            session()->forget([
                'shopee2_import_data',
                'shopee2_import_issues', 
                'shopee2_preview_data',
                'shopee2_preview_headers',
                'shopee2_header_labels',
                'shopee2_total_rows',
                'shopee2_valid_rows', 
                'shopee2_invalid_rows',
                'shopee2_process_token'
            ]);
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopee2Cache();
            
            // Store skipped reasons in session if any (platform-specific)
            if (!empty($skippedReasons)) {
                session(['shopee2_skipped_reasons' => $skippedReasons]);
            } else {
                // Clear any old skipped reasons if no new ones
                session()->forget('shopee2_skipped_reasons');
            }
            
            return redirect()->route('finance.shopee2.index')
                ->with('success', "Berhasil mengimpor $importCount transaksi finansial. $skippedCount transaksi dilewati.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during Shopee2 financial import: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error memproses data: ' . $e->getMessage());
        }
    }

    /**
     * Clear Shopee2-related cache
     */
    private function clearShopee2Cache()
    {
        // Clear totals cache patterns
        $cacheKeys = [
            'shopee2_totals_*',
            'shopee2_missing_orders_*'
        ];
        
        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need to implement cache tag clearing
                // For now, we'll clear specific cache keys
                cache()->forget('shopee2_totals_' . md5(serialize(request()->all())));
                cache()->forget('shopee2_missing_orders_' . date('Y-m-d-H'));
            }
        }
        
        Log::info('Shopee2 cache cleared after data update');
    }

    /**
     * Group order items by tax_id from barang keluar
     * 
     * @param Order $order
     * @return array Array of tax_id => [order_items, total_qty, total_nominal]
     */
    protected function groupOrderItemsByTaxId($order)
    {
        // First, check BarangKeluar records associated with this order
        $barangKeluarItems = \App\Models\BarangKeluar::whereHas('orderItem', function($query) use ($order) {
            $query->where('order_id', $order->id);
        })->with(['warehouseStock', 'orderItem'])->get();
        
        // Group by tax_id
        $taxGroups = [];
        
        foreach ($barangKeluarItems as $bk) {
            if ($bk->warehouseStock) {
                $taxId = $bk->warehouseStock->tax_id ?? 4;
                if (!$bk->warehouseStock->tax_id) {
                    Log::warning("WarehouseStock #{$bk->warehouseStock->id} has NULL tax_id, using default 4 (Non-PKP) for order grouping");
                }
                
                if (!isset($taxGroups[$taxId])) {
                    $taxGroups[$taxId] = [
                        'order_items' => [],
                        'barang_keluar' => [],
                        'total_qty' => 0,
                        'total_nominal' => 0,
                    ];
                }
                
                // Add barang keluar item
                $taxGroups[$taxId]['barang_keluar'][] = $bk;
                $taxGroups[$taxId]['total_qty'] += $bk->qty;
                
                // Add order item if not already added
                if ($bk->orderItem) {
                    $orderItemId = $bk->orderItem->id;
                    if (!isset($taxGroups[$taxId]['order_items'][$orderItemId])) {
                        $taxGroups[$taxId]['order_items'][$orderItemId] = $bk->orderItem;
                        $taxGroups[$taxId]['total_nominal'] += $bk->orderItem->price_after_discount * $bk->orderItem->quantity;
                    }
                }
            }
        }
        
        // If no BarangKeluar items, fall back to order items
        if (empty($taxGroups)) {
            $orderItems = $order->orderItems()->with('warehouseStock')->get();
            
            foreach ($orderItems as $item) {
                if ($item->warehouseStock) {
                    $taxId = $item->warehouseStock->tax_id ?? 4;
                    if (!$item->warehouseStock->tax_id) {
                        Log::warning("WarehouseStock #{$item->warehouseStock->id} has NULL tax_id, using default 4 (Non-PKP) for order grouping");
                    }
                    
                    if (!isset($taxGroups[$taxId])) {
                        $taxGroups[$taxId] = [
                            'order_items' => [],
                            'barang_keluar' => [],
                            'total_qty' => 0,
                            'total_nominal' => 0,
                        ];
                    }
                    
                    $taxGroups[$taxId]['order_items'][$item->id] = $item;
                    $taxGroups[$taxId]['total_qty'] += $item->quantity;
                    $taxGroups[$taxId]['total_nominal'] += $item->price_after_discount * $item->quantity;
                }
            }
        }
        
        return $taxGroups;
    }

    /**
     * Generate invoice number for an order based on warehouse stock tax_id
     * 
     * @param Order $order
     * @param int $taxId
     * @return string
     */
    protected function generateInvoiceForOrder($order, $taxId = null)
    {
        // If tax_id is provided, use it directly
        if ($taxId !== null) {
            return Shopee2FinancialTransaction::generateInvoiceNumber($order, $taxId);
        }
        
        // Otherwise, find the tax_id with the highest quantity (backward compatibility)
        $taxGroups = $this->groupOrderItemsByTaxId($order);
        
        if (empty($taxGroups)) {
            // Default to Non-PKP if no tax_id found
            return Shopee2FinancialTransaction::generateInvoiceNumber($order, 4);
        }
        
        // Find the tax_id with the highest quantity
        $maxQty = 0;
        $dominantTaxId = null;
        foreach ($taxGroups as $tid => $group) {
            if ($group['total_qty'] > $maxQty) {
                $maxQty = $group['total_qty'];
                $dominantTaxId = $tid;
            }
        }
        
        return Shopee2FinancialTransaction::generateInvoiceNumber($order, $dominantTaxId ?? 4);
    }

    public function manual()
    {
        // Get Shopee2 platform ID dynamically
        $platformModel = Platform::whereRaw('LOWER(name) = ?', ['shopee liefmarket'])->first();
        $shopee2PlatformId = $platformModel ? $platformModel->id : 2; // fallback ke ID 2
        $platformLabel = 'Shopee Liefmarket';
        
        // Get order_id from request if available
        $orderId = request('order_id');
        $order = null;
        
        // If order_id is provided, try to find the order
        if ($orderId) {
            $order = Order::where('platform_id', $shopee2PlatformId)->find($orderId);
        }
        
        return view('financial.shopee2.manual', compact('order', 'platformLabel'));
    }

    /**
     * AJAX endpoint: Get total harga & qty for a given order
     */
    public function getOrderTotal($orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order tidak ditemukan'], 404);
        }

        $order->loadMissing('orderItems');
        $totalHarga = 0;
        $totalQty = 0;
        foreach ($order->orderItems as $item) {
            $totalHarga += $item->price_after_discount * $item->quantity;
            $totalQty += $item->quantity;
        }

        return response()->json([
            'total_harga' => $totalHarga,
            'total_qty' => $totalQty,
            'formatted' => number_format($totalHarga, 0, ',', '.'),
        ]);
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'saldo_masuk' => 'required|numeric',
            'tanggal_masuk_pembayaran' => 'required|date',
            'hari_masuk_pembayaran' => 'required|string',
            'nominal_diskon1' => 'nullable|numeric',
            'nominal_diskon2' => 'nullable|numeric',
            'nominal_diskon3' => 'nullable|numeric',
            'nominal_diskon4' => 'nullable|numeric',
            'nominal_diskon5' => 'nullable|numeric',
            'nominal_diskon6' => 'nullable|numeric',
            'adjustment' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();
            
            // Get Shopee2 platform ID dynamically
            $platformModel = Platform::whereRaw('LOWER(name) = ?', ['shopee liefmarket'])->first();
            $shopee2PlatformId = $platformModel ? $platformModel->id : 2; // fallback ke ID 2
            
            $order = Order::findOrFail($request->order_id);
            
            // Check if order is from Shopee2 platform (by platform_id)
            if (!$order->platform_id || $order->platform_id !== $shopee2PlatformId) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Nomor pesanan yang dipilih bukan dari platform Shopee2.');
            }
            
            // Cek jika sudah ada transaksi untuk order ini
            $exists = Shopee2FinancialTransaction::where('order_id', $order->id)->exists();
            if ($exists) {
                return redirect()->back()->with('error', 'Transaksi untuk order ini sudah ada.');
            }
            
            // Group order items by tax_id from barang keluar
            $taxGroups = $this->groupOrderItemsByTaxId($order);
            
            if (empty($taxGroups)) {
                return redirect()->back()->with('error', 'Tidak ada barang keluar dengan tax_id untuk order ini.');
            }
            
            $invoiceMessages = [];
            $totalSaldoMasuk = $request->saldo_masuk ?? 0;
            $totalNominalFix = 0;
            
            // Calculate total nominal_fix for all tax groups first
            foreach ($taxGroups as $taxId => $group) {
                $groupNominalHarga = $group['total_nominal'];
                $groupNominalFix = $groupNominalHarga + 
                    ($request->nominal_diskon1 ? -abs((float)$request->nominal_diskon1) : 0) +
                    ($request->nominal_diskon2 ? -abs((float)$request->nominal_diskon2) : 0) +
                    ($request->nominal_diskon3 ? -abs((float)$request->nominal_diskon3) : 0) +
                    ($request->nominal_diskon4 ? -abs((float)$request->nominal_diskon4) : 0) +
                    ($request->nominal_diskon5 ? -abs((float)$request->nominal_diskon5) : 0) +
                    ($request->nominal_diskon6 ? -abs((float)$request->nominal_diskon6) : 0) +
                    ($request->adjustment ?? 0);
                $totalNominalFix += $groupNominalFix;
            }
            
            // Process each tax group separately
            foreach ($taxGroups as $taxId => $group) {
                $transaction = new Shopee2FinancialTransaction();
                $transaction->setDataFromOrder($order);
                $transaction->order_id = $order->id;
                
                $transaction->nominal_harga = $group['total_nominal'];
                
                // Distribute discounts proportionally based on nominal_harga
                $totalOrderNominal = $order->orderItems->sum(function($item) {
                    return $item->price_after_discount * $item->quantity;
                });
                $proportion = $totalOrderNominal > 0 ? ($group['total_nominal'] / $totalOrderNominal) : (1 / count($taxGroups));
                
                // Store discounts as negative values (consistent with import logic)
                $transaction->nominal_diskon1 = $request->nominal_diskon1 ? -abs((float)$request->nominal_diskon1 * $proportion) : 0;
                $transaction->nominal_diskon2 = $request->nominal_diskon2 ? -abs((float)$request->nominal_diskon2 * $proportion) : 0;
                $transaction->nominal_diskon3 = $request->nominal_diskon3 ? -abs((float)$request->nominal_diskon3 * $proportion) : 0;
                $transaction->nominal_diskon4 = $request->nominal_diskon4 ? -abs((float)$request->nominal_diskon4 * $proportion) : 0;
                $transaction->nominal_diskon5 = $request->nominal_diskon5 ? -abs((float)$request->nominal_diskon5 * $proportion) : 0;
                $transaction->nominal_diskon6 = $request->nominal_diskon6 ? -abs((float)$request->nominal_diskon6 * $proportion) : 0;
                $transaction->adjustment = ($request->adjustment ?? 0) * $proportion;
                $transaction->saldo_masuk = $totalSaldoMasuk * $proportion;
                $transaction->tanggal_masuk_pembayaran = $request->tanggal_masuk_pembayaran;
                $transaction->hari_masuk_pembayaran = $request->hari_masuk_pembayaran;
                
                // Generate invoice number for this tax_id
                $transaction->no_invoice = $this->generateInvoiceForOrder($order, $taxId);
                
                $transaction->calculateNominalFix()
                    ->calculateOutstanding()
                    ->calculatePercentages()
                    ->save();
                
                $taxCategory = in_array($taxId, [1, 3, 5, 7]) ? 'PKP' : 'Non-PKP';
                $invoiceMessages[] = "Invoice {$transaction->no_invoice} berhasil dibuat untuk barang {$taxCategory}";
            }
            
            DB::commit();
            
            return redirect()->route('finance.shopee2.index')
                ->with('success', implode('<br>', $invoiceMessages));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            $transaction = Shopee2FinancialTransaction::findOrFail($id);
            $transaction->delete();
            
            return redirect()->back()->with('success', 'Transaksi berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function adjust(Request $request, $id)
    {
        $request->validate([
            'adjustment' => 'required|numeric',
            'adjustment_description' => 'nullable|string'
        ]);

        try {
            $transaction = Shopee2FinancialTransaction::findOrFail($id);
            
            // Record adjustment history
            AdjustmentHistory::create([
                'transaction_type' => 'shopee2_financial',
                'transaction_id' => $transaction->id,
                'old_adjustment' => $transaction->adjustment,
                'new_adjustment' => $request->adjustment,
                'description' => $request->adjustment_description,
                'adjusted_by' => auth()->id(),
            ]);
            
            $transaction->adjustment = $request->adjustment;
            $transaction->calculateNominalFix()
                ->calculateOutstanding()
                ->save();
            
            return redirect()->back()->with('success', 'Adjustment berhasil disimpan');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function printInvoice($id)
    {
        $transaction = Shopee2FinancialTransaction::with([
            'order.orderItems.platformProduct.mappingBarang.product',
            'order.orderItems.warehouseStock.tax'
        ])->findOrFail($id);
        
        $bankAccountInfo = Shopee2FinancialTransaction::getBankAccountInfo();
        
        $pdf = Pdf::loadView('financial.shopee2.print-invoice', compact('transaction', 'bankAccountInfo'));
        return $pdf->stream("invoice-shopee2-{$transaction->no_order}.pdf");
    }

    public function history($id)
    {
        $transaction = Shopee2FinancialTransaction::findOrFail($id);
        $adjustments = AdjustmentHistory::where('transaction_type', 'shopee2_financial')
            ->where('transaction_id', $id)
            ->with('adjustedBy')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('financial.shopee2.history', compact('transaction', 'adjustments'));
    }

    public function lock($id)
    {
        try {
            $transaction = Shopee2FinancialTransaction::findOrFail($id);
            $transaction->lock(auth()->id());
            
            return redirect()->back()->with('success', 'Transaksi berhasil dikunci');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function unlock($id)
    {
        try {
            $transaction = Shopee2FinancialTransaction::findOrFail($id);
            $transaction->unlock();
            
            return redirect()->back()->with('success', 'Transaksi berhasil dibuka');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new Shopee2FinanceAnalyticsExport($request), 'shopee2-finance-analytics.xlsx');
    }

    public function exportPdf(Request $request)
    {
        // Implementation for PDF export
        return redirect()->back()->with('info', 'PDF export akan segera tersedia');
    }

    public function syncOrderDates()
    {
        try {
            $updated = Shopee2FinancialTransaction::syncAllOrderDates();
            return redirect()->back()->with('success', "Berhasil sinkronisasi {$updated} transaksi");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function exportCashFlow(Request $request)
    {
        return Excel::download(new Shopee2CashFlowExport($request), 'shopee2-cash-flow.xlsx');
    }
}
