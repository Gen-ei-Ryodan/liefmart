<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ShopeeFinancialTransaction;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\InvoiceSequence;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ShopeeFinanceAnalyticsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AdjustmentHistory;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class PembayaranShopeeController extends Controller
{
    public function index(Request $request)
    {
        // Get platform by name (Shopee Lamourad)
        $platformModel = Platform::whereRaw('LOWER(name) = ?', ['shopee lamourad'])->first();
        
        // Jika tidak ditemukan berdasarkan nama, cari dengan LIKE
        if (!$platformModel) {
            $platformModel = Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee lamourad%'])
                ->whereRaw('LOWER(name) NOT LIKE ?', ['%shopee liefmarket%'])
                ->first();
        }
        
        if (!$platformModel) {
            return redirect()->back()->with('error', 'Platform Shopee Lamourad tidak ditemukan');
        }
        $platformId = $platformModel->id;
        $platform = 'shopee';
        $platformLabel = 'Shopee Lamourad';
        
        // Optimized eager loading - only load what's needed for display
        $query = ShopeeFinancialTransaction::with([
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
                // Lunas: outstanding harus tepat 0 (dengan toleransi kecil untuk floating point)
                $query->whereRaw('ABS(outstanding) <= 0.01');
            } elseif ($request->outstanding_status === '1') {
                // Outstanding: outstanding tidak sama dengan 0 (ada selisih)
                $query->whereRaw('ABS(outstanding) > 0.01');
            }
        }
        
        // Get all transactions matching the query (without pagination yet)
        $allTransactions = $query->orderBy('tanggal_order', 'desc')->get();

        // Filter out fully returned orders (retur full), keep partial returns (retur sebagian)
        // Uses Order::isFullyReturned() for consistent logic across all platforms
        $filteredCollection = $allTransactions->filter(function($transaction) {
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
        $perPage = 15;
        $currentPage = Paginator::resolveCurrentPage('page');
        $transactions = new LengthAwarePaginator(
            $filteredCollection->forPage($currentPage, $perPage)->values(),
            $filteredCollection->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );
        
        // Get all orders that don't have financial transactions
        $missingOrdersQuery = Order::with(['orderItems', 'orderItems.platformProduct.mappingBarang'])
            ->whereDoesntHave('shopeeFinancialTransactions')
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
        
        return view('financial.shopee.index', compact(
            'transactions', 
            'groupedTransactions', 
            'platform', 
            'platformLabel',
            'missingOrders',
            'totalCount',
            'totalNominalFix',
            'totalSaldoMasuk',
            'totalOutstanding'
        ));
    }

    /**
     * Show the form for importing financial data.
     *
     * @return \Illuminate\Http\Response
     */
    public function importForm()
    {
        return view('financial.shopee.import');
    }

    public function import()
    {
        return view('financial.shopee.import');
    }

    /**
     * Preview imported data with optimized performance
     */
    public function previewDuplicateRemoved(Request $request)
    {
        // If this is a GET request, check if we have data in the session
        if ($request->isMethod('get')) {
            if (!session()->has('shopee_import_data')) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
            }
            
            // Get the data from the session
            $data = session('shopee_import_data');
            $previewData = session('shopee_preview_data');
            $previewHeaders = session('shopee_preview_headers');
            $headerLabels = session('shopee_header_labels');
            $issues = session('shopee_import_issues');
            $totalRows = session('shopee_total_rows');
            $validRows = session('shopee_valid_rows');
            $invalidRows = session('shopee_invalid_rows');
            
            // If any of the required data is missing, redirect to import
            if (!$previewData || !$previewHeaders || !$headerLabels) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Data preview tidak lengkap. Silakan upload file kembali.');
            }
            
            return view('financial.shopee.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
        }
        
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
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
            Log::info('Starting Shopee financial import preview', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);
            
            // Load Excel file with memory optimization
            $reader = IOFactory::createReader(IOFactory::identify($path));
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Set additional reader options for better performance
            if (method_exists($reader, 'setReadFilter')) {
                // Only read first 10000 rows to prevent memory issues
                $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
                    private $maxRow = 10000;
                    
                    public function readCell($column, $row, $worksheetName = '') {
                        return $row <= $this->maxRow;
                    }
                });
            }
            
            $spreadsheet = $reader->load($path);
            
            // Free up memory immediately after loading
            unset($reader);
            
            // Look for the 'Order details' sheet
            $orderDetailsSheet = null;
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                Log::info("Found sheet: " . $sheet->getTitle());
                if (strtolower($sheet->getTitle()) === 'order details') {
                    $orderDetailsSheet = $sheet;
                    break;
                }
            }
            
            if (!$orderDetailsSheet) {
                // Let's try to use the first sheet if Order details isn't found
                $orderDetailsSheet = $spreadsheet->getActiveSheet();
                Log::info("Using active sheet: " . $orderDetailsSheet->getTitle());
            }
            
            $worksheet = $orderDetailsSheet;
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            Log::info("Processing sheet: " . $worksheet->getTitle() . " (Rows: $highestRow, Columns: $highestColumn)");
            
            // Get headers from first row
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
            $headers = array_filter($headerRow, function($value) {
                return !empty(trim($value));
            });
            
            // Standardize header names
            $headerMapping = [
                'Order ID' => 'ORDER_ID',
                'Order Number' => 'ORDER_NUMBER', 
                'Order Date' => 'ORDER_DATE',
                'Payment Date' => 'PAYMENT_DATE',
                'Payment Amount' => 'PAYMENT_AMOUNT',
                'Voucher Ditanggung Penjual' => 'VOUCHER_DITANGGUNG_PENJUAL',
                'KOMISI AMS/AFFILIATE' => 'KOMISI_AMS_AFFILIATE',
                'BIAYA ADMIN' => 'BIAYA_ADMIN',
                'BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)' => 'BIAYA_LAYANAN',
                'DISKON 5' => 'DISKON_5',
                'DISKON 6' => 'DISKON_6'
            ];
            
            $standardizedHeaders = [];
            foreach ($headers as $header) {
                $standardizedHeaders[] = $headerMapping[$header] ?? $header;
            }
            
            Log::info("Mapped headers: " . json_encode($standardizedHeaders));
            
            // Check for missing required headers
            $requiredHeaders = ['ORDER_ID', 'ORDER_NUMBER', 'PAYMENT_DATE', 'PAYMENT_AMOUNT'];
            $foundRequiredHeaders = [];
            foreach ($requiredHeaders as $requiredHeader) {
                if (in_array($requiredHeader, $standardizedHeaders)) {
                    $foundRequiredHeaders[] = $requiredHeader;
                }
            }
            $missingHeaders = array_diff($requiredHeaders, $foundRequiredHeaders);
            if (!empty($missingHeaders)) {
                Log::warning("Missing required headers: " . json_encode($missingHeaders));
                return redirect()->back()->with('error', 'Format file tidak sesuai. Kolom yang tidak ditemukan: ' . implode(', ', $missingHeaders));
            }
            
            // Get column indexes for mapped headers
            $columnIndices = [];
            foreach ($standardizedHeaders as $index => $header) {
                $columnIndices[$header] = $index;
            }
            
            // Helper function to get value from row using standardized header name
            $getValue = function($row, $standardHeader) use ($columnIndices, $standardizedHeaders) {
                // Find the index for this standard header
                $index = array_search($standardHeader, $standardizedHeaders);
                if ($index !== false && isset($row[$index])) {
                    return $row[$index];
                }
                
                // Try alternate method using column indices
                if (isset($columnIndices[$standardHeader]) && isset($row[$columnIndices[$standardHeader]])) {
                    return $row[$columnIndices[$standardHeader]];
                }
                
                // Default to empty string if not found
                return '';
            };
            
            // Get data from Excel with row limit
            $dataRange = 'A2:' . $highestColumn . min($highestRow, 10000);
            $rows = $worksheet->rangeToArray($dataRange, null, true, false);
            
            // Free up memory from spreadsheet
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $worksheet);
            
            // Force garbage collection to free memory
            gc_collect_cycles();
            
            // Read data
            $rowNumber = 1;
            $previewData = [];
            
            $totalRows = count($rows);
            
            // Collect all order numbers first for batch query
            $orderNumbers = [];
            $processedRows = [];
            
            // First pass: collect order numbers and process basic data
            foreach ($rows as $row) {
                $rowNumber++;
                
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                // Create data array based on headers
                $rowData = [];
                $rowData['ORDER_ID'] = $getValue($row, 'ORDER_ID');
                $rowData['ORDER_NUMBER'] = $getValue($row, 'ORDER_NUMBER');
                $rowData['ORDER_DATE'] = $getValue($row, 'ORDER_DATE');
                $rowData['PAYMENT_DATE'] = $getValue($row, 'PAYMENT_DATE');
                $rowData['PAYMENT_AMOUNT'] = $getValue($row, 'PAYMENT_AMOUNT');
                $rowData['VOUCHER_DITANGGUNG_PENJUAL'] = $getValue($row, 'VOUCHER_DITANGGUNG_PENJUAL');
                $rowData['KOMISI_AMS_AFFILIATE'] = $getValue($row, 'KOMISI_AMS_AFFILIATE');
                $rowData['BIAYA_ADMIN'] = $getValue($row, 'BIAYA_ADMIN');
                $rowData['BIAYA_LAYANAN'] = $getValue($row, 'BIAYA_LAYANAN');
                $rowData['DISKON_5'] = $getValue($row, 'DISKON_5');
                $rowData['DISKON_6'] = $getValue($row, 'DISKON_6');
                
                // Collect order number for batch query
                if (!empty($rowData['ORDER_NUMBER'])) {
                    $orderNumbers[] = $rowData['ORDER_NUMBER'];
                }
                
                $processedRows[] = [
                    'rowNumber' => $rowNumber,
                    'rowData' => $rowData
                ];
            }
            
            // Batch query: Get all orders and their items in one query
            $orders = [];
            $existingTransactions = [];
            
            if (!empty($orderNumbers)) {
                // Remove duplicates to avoid unnecessary queries
                $orderNumbers = array_unique($orderNumbers);
                
                // Get all orders with their items in one query
                $orders = Order::whereIn('order_number', $orderNumbers)
                    ->with(['orderItems.warehouseStock'])
                    ->get()
                    ->keyBy('order_number');
                
                // Get all existing transactions in one query
                $existingTransactions = ShopeeFinancialTransaction::whereIn('no_order', $orderNumbers)
                    ->pluck('no_order')
                    ->toArray();
                
                Log::info("Batch loaded " . count($orders) . " orders and " . count($existingTransactions) . " existing transactions");
            }
            
            // Second pass: process rows with pre-loaded data
            foreach ($processedRows as $processedRow) {
                $rowNumber = $processedRow['rowNumber'];
                $rowData = $processedRow['rowData'];
                
                // Validate row data
                $rowIssues = [];
                
                // 1. Validate order number
                if (empty($rowData['ORDER_NUMBER'])) {
                    $rowIssues[] = 'Nomor pesanan kosong';
                }
                
                // 2. Validate date format
                if (!empty($rowData['PAYMENT_DATE'])) {
                    // If date is an Excel date object
                    if ($rowData['PAYMENT_DATE'] instanceof \DateTime) {
                        $rowData['PAYMENT_DATE'] = $rowData['PAYMENT_DATE']->format('Y-m-d');
                    } else {
                        // Try to parse date in various formats
                        $date = null;
                        $dateValue = $rowData['PAYMENT_DATE'];
                        
                        // If it's a numeric value (Excel serial date)
                        if (is_numeric($dateValue)) {
                            try {
                                $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                                $rowData['PAYMENT_DATE'] = $excelDate->format('Y-m-d');
                                $date = true;
                            } catch (\Exception $e) {
                                Log::warning("Failed to convert Excel date: " . $e->getMessage());
                            }
                        } else {
                            // Try multiple formats
                            $formats = [
                                'Y-m-d', 'Y/m/d', 'd/m/Y', 'Y-m-d H:i:s', 'Y/m/d H:i:s',
                                'd-m-Y', 'm/d/Y', 'Y.m.d', 'd.m.Y', 'm.d.Y'
                            ];
                            
                            foreach ($formats as $format) {
                                $parsedDate = \DateTime::createFromFormat($format, $dateValue);
                                if ($parsedDate && $parsedDate->format($format) == $dateValue) {
                                    $rowData['PAYMENT_DATE'] = $parsedDate->format('Y-m-d');
                                    $date = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!$date) {
                            Log::warning("Invalid date format: " . $dateValue);
                            $rowIssues[] = 'Format tanggal tidak valid. Format yang didukung: YYYY-MM-DD, YYYY/MM/DD, DD/MM/YYYY';
                        }
                    }
                } else {
                    $rowIssues[] = 'Tanggal pembayaran kosong';
                }
                
                // 3. Validate payment amount
                if (!isset($rowData['PAYMENT_AMOUNT'])) {
                    $rowIssues[] = 'Jumlah pembayaran tidak ditemukan';
                } elseif ($rowData['PAYMENT_AMOUNT'] === '') {
                    $rowIssues[] = 'Jumlah pembayaran kosong';
                }
                
                // 4. Check if order exists in database (using pre-loaded data)
                $order = $orders[$rowData['ORDER_NUMBER']] ?? null;
                if (!$order) {
                    Log::warning("Order tidak ditemukan: " . $rowData['ORDER_NUMBER']);
                    $rowIssues[] = 'Nomor order tidak ditemukan di database';
                    
                    // Skip this transaction instead of creating placeholder data
                    $rowData['_valid'] = false;
                    $rowData['_issues'] = $rowIssues;
                    $rowData['_row'] = $rowNumber;
                    
                    $data[] = $rowData;
                    $issues[$rowNumber] = $rowIssues;
                    continue;
                }
                
                // 5. Check if transaction already exists (using pre-loaded data)
                $transactionExists = in_array($rowData['ORDER_NUMBER'], $existingTransactions);
                if ($transactionExists) {
                    Log::warning("Transaksi sudah ada untuk order: " . $rowData['ORDER_NUMBER']);
                    $rowIssues[] = 'Transaksi untuk order ini sudah ada';
                }
                
                // Create preview data even if transaction exists to show in the preview
                // Calculate total price from order items considering quantity (using pre-loaded data)
                $nominal_harga = 0;
                foreach ($order->orderItems as $item) {
                    $nominal_harga += $item->price_after_discount * $item->quantity;
                }
                
                // Calculate total quantity across all order items (using pre-loaded data)
                $totalQty = $order->orderItems->sum('quantity');
                
                // Preview data
                $previewData[] = [
                    'tanggal_order' => $order->tanggal,
                    'hari_order' => $order->hari,
                    'no_order' => $order->order_number,
                    'no_invoice' => 'PREVIEW-' . $order->order_number,
                    'qty' => $totalQty,
                    'nominal_harga' => $nominal_harga,
                    'nominal_diskon1' => !empty($rowData['VOUCHER_DITANGGUNG_PENJUAL']) ? -abs((float) $rowData['VOUCHER_DITANGGUNG_PENJUAL']) : 0,
                    'nominal_diskon2' => !empty($rowData['KOMISI_AMS_AFFILIATE']) ? -abs((float) $rowData['KOMISI_AMS_AFFILIATE']) : 0,
                    'nominal_diskon3' => !empty($rowData['BIAYA_ADMIN']) ? -abs((float) $rowData['BIAYA_ADMIN']) : 0,
                    'nominal_diskon4' => !empty($rowData['BIAYA_LAYANAN']) ? -abs((float) $rowData['BIAYA_LAYANAN']) : 0,
                    'nominal_diskon5' => !empty($rowData['DISKON_5']) ? -abs((float) $rowData['DISKON_5']) : 0,
                    'nominal_diskon6' => !empty($rowData['DISKON_6']) ? -abs((float) $rowData['DISKON_6']) : 0,
                    'nominal_diskon7' => !empty($rowData['DISKON_7']) ? -abs((float) $rowData['DISKON_7']) : 0,
                    'nominal_diskon8' => !empty($rowData['DISKON_8']) ? -abs((float) $rowData['DISKON_8']) : 0,
                    'nominal_diskon9' => !empty($rowData['DISKON_9']) ? -abs((float) $rowData['DISKON_9']) : 0,
                    'nominal_diskon10' => !empty($rowData['DISKON_10']) ? -abs((float) $rowData['DISKON_10']) : 0,
                    'nominal_diskon11' => !empty($rowData['DISKON_11']) ? -abs((float) $rowData['DISKON_11']) : 0,
                    'nominal_diskon12' => !empty($rowData['DISKON_12']) ? -abs((float) $rowData['DISKON_12']) : 0,
                    'adjustment' => 0,
                    'saldo_masuk' => !empty($rowData['PAYMENT_AMOUNT']) ? (float) $rowData['PAYMENT_AMOUNT'] : 0,
                    'tanggal_masuk_pembayaran' => $rowData['PAYMENT_DATE'],
                    'hari_masuk_pembayaran' => \Carbon\Carbon::parse($rowData['PAYMENT_DATE'])->format('l'),
                    'nominal_fix' => $nominal_harga + 
                        (!empty($rowData['VOUCHER_DITANGGUNG_PENJUAL']) ? -abs((float) $rowData['VOUCHER_DITANGGUNG_PENJUAL']) : 0) +
                        (!empty($rowData['KOMISI_AMS_AFFILIATE']) ? -abs((float) $rowData['KOMISI_AMS_AFFILIATE']) : 0) +
                        (!empty($rowData['BIAYA_ADMIN']) ? -abs((float) $rowData['BIAYA_ADMIN']) : 0) +
                        (!empty($rowData['BIAYA_LAYANAN']) ? -abs((float) $rowData['BIAYA_LAYANAN']) : 0) +
                        (!empty($rowData['DISKON_5']) ? -abs((float) $rowData['DISKON_5']) : 0) +
                        (!empty($rowData['DISKON_6']) ? -abs((float) $rowData['DISKON_6']) : 0) +
                        (!empty($rowData['DISKON_7']) ? -abs((float) $rowData['DISKON_7']) : 0) +
                        (!empty($rowData['DISKON_8']) ? -abs((float) $rowData['DISKON_8']) : 0) +
                        (!empty($rowData['DISKON_9']) ? -abs((float) $rowData['DISKON_9']) : 0) +
                        (!empty($rowData['DISKON_10']) ? -abs((float) $rowData['DISKON_10']) : 0) +
                        (!empty($rowData['DISKON_11']) ? -abs((float) $rowData['DISKON_11']) : 0) +
                        (!empty($rowData['DISKON_12']) ? -abs((float) $rowData['DISKON_12']) : 0) +
                        0,
                    'outstanding' => 0,
                    'status' => 'preview'
                ];
                
                // Store row data with validation info
                $rowData['_valid'] = empty($rowIssues);
                $rowData['_issues'] = $rowIssues;
                $rowData['_row'] = $rowNumber;
                
                $data[] = $rowData;
                
                if (!empty($rowIssues)) {
                    $issues[$rowNumber] = $rowIssues;
                }
            }
            
            // Store data in session
            session([
                'shopee_import_data' => $data,
                'shopee_preview_data' => $previewData,
                'shopee_preview_headers' => $standardizedHeaders,
                'shopee_header_labels' => $headers,
                'shopee_import_issues' => $issues,
                'shopee_total_rows' => $totalRows,
                'shopee_valid_rows' => count(array_filter($data, function($row) { return $row['_valid']; })),
                'shopee_invalid_rows' => count(array_filter($data, function($row) { return !$row['_valid']; }))
            ]);
            
            // Generate and store process token for secure processing
            $processToken = uniqid('shopee_', true);
            session(['shopee_process_token' => $processToken]);
            
            return view('financial.shopee.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
            
        } catch (\Exception $e) {
            Log::error("Error during Shopee financial import preview: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error memproses data: ' . $e->getMessage());
        }
    }

    /**
     * Alias for preview method to match route name
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importPreview(Request $request)
    {
        return $this->preview($request);
    }

    /**
     * Alias for process method to match route name
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importProcess(Request $request)
    {
        return $this->process($request);
    }

    public function preview(Request $request)
    {
        // If this is a GET request, check if we have data in the session
        if ($request->isMethod('get')) {
            if (!session()->has('shopee_import_data')) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
            }
            
            // Get the data from the session
            $data = session('shopee_import_data');
            $previewData = session('shopee_preview_data');
            $previewHeaders = session('shopee_preview_headers');
            $headerLabels = session('shopee_header_labels');
            $issues = session('shopee_issues');
            $totalRows = session('shopee_total_rows');
            $validRows = session('shopee_valid_rows');
            $invalidRows = session('shopee_invalid_rows');
            
            Log::info("preview GET: previewData is " . (is_array($previewData) ? "array with " . count($previewData) . " items" : "not an array"));
            
            // If any of the required data is missing, redirect to import
            if (!$previewData || !$previewHeaders || !$headerLabels) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Data preview tidak lengkap. Silakan upload file kembali.');
            }
            
            return view('financial.shopee.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
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
            Log::info('Starting Shopee financial import preview', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize()
            ]);
            
            // Membaca file Excel tanpa batasan
            $reader = IOFactory::createReader(IOFactory::identify($path));
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Set additional reader options for better performance
            // No row limit - read all data
            
            $spreadsheet = $reader->load($path);
            
            // Keep reader for potential reuse
            
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
                        $transactionExists = ShopeeFinancialTransaction::where('no_order', $rowData['NOMOR PESANAN'])->exists();
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
                                $invoice['adjustment'] = 0;
                                
                                // Calculate nominal_fix for display
                                $invoice['nominal_fix'] = $invoice['nominal_harga'] + 
                                    $invoice['nominal_diskon1'] + 
                                    $invoice['nominal_diskon2'] + 
                                    $invoice['nominal_diskon3'] + 
                                    $invoice['nominal_diskon4'] + 
                                    $invoice['nominal_diskon5'] + 
                                    $invoice['nominal_diskon6'] + 
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
            Log::error('Shopee import error: ' . $e->getMessage());
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
        session(['shopee_import_data' => $data]);
        session(['shopee_import_issues' => $issues]);
        session(['shopee_preview_data' => $previewData]);
        session(['shopee_preview_headers' => $previewHeaders]);
        session(['shopee_header_labels' => $headerLabels]);
        session(['shopee_total_rows' => $totalRows]);
        session(['shopee_valid_rows' => $validRows]);
        session(['shopee_invalid_rows' => $invalidRows]);
        
        // Generate and store process token for secure processing
        $processToken = uniqid('shopee_', true);
        session(['shopee_process_token' => $processToken]);
        
        return view('financial.shopee.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
    }

    /**
     * Show the preview page for GET requests
     * This method checks if there's preview data in the session and displays it
     * If no data is found, it redirects to the import page
     */
    public function showPreview()
    {
        // Check if there's preview data in the session
        if (!session()->has('shopee_import_data')) {
            return redirect()->route('finance.shopee.import')
                ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
        }
        
        // Get the data from the session
        $data = session('shopee_import_data');
        $previewData = session('shopee_preview_data');
        $previewHeaders = session('shopee_preview_headers');
        $headerLabels = session('shopee_header_labels');
        $issues = session('shopee_issues');
        $totalRows = session('shopee_total_rows');
        $validRows = session('shopee_valid_rows');
        $invalidRows = session('shopee_invalid_rows');
        
        Log::info("showPreview: previewData is " . (is_array($previewData) ? "array with " . count($previewData) . " items" : "not an array"));
        
        // If any of the required data is missing, redirect to import
        if (!$previewData || !$previewHeaders || !$headerLabels) {
            return redirect()->route('finance.shopee.import')
                ->with('error', 'Data preview tidak lengkap. Silakan upload file kembali.');
        }
        
        return view('financial.shopee.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
    }

    public function process(Request $request)
    {
        try {
            // Increase execution time limit for large imports
            set_time_limit(1200); // 20 minutes for better performance
            ini_set('memory_limit', '2048M'); // Increased memory limit to 2GB
            
            Log::info('Starting Shopee financial import process');
            
            // Validate process token
            $processToken = $request->input('process_token');
            if (!$processToken || $processToken !== session('shopee_process_token')) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Token proses tidak valid. Silakan upload ulang file.');
            }
            
            // Get data from session instead of POST data
            $importData = session('shopee_import_data');
            if (!$importData) {
                return redirect()->route('finance.shopee.import')
                    ->with('error', 'Data import tidak ditemukan. Silakan upload ulang file.');
            }
            
            // Filter only valid data
            $validData = array_filter($importData, function($rowData) {
                return isset($rowData['_valid']) && $rowData['_valid'] === true;
            });
            
            if (empty($validData)) {
                return redirect()->route('finance.shopee.import')
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
                $existingTransactions = ShopeeFinancialTransaction::whereIn('no_order', $allOrderNumbers)
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
                    // Group order items by tax_id from barang keluar
                    $taxGroups = $this->groupOrderItemsByTaxId($order);
                    
                    if (empty($taxGroups)) {
                        $skippedCount++;
                        $skippedReasons[] = "Order {$order->order_number}: Tidak ada barang keluar dengan tax_id";
                        Log::warning("Skipping order {$order->order_number} - no tax groups found");
                        continue;
                    }
                    
                    // Calculate total invoice value for proportion calculation
                    $totalInvoiceValue = $order->orderItems->sum(function($item) {
                        return $item->price_after_discount * $item->quantity;
                    });
                    
                    // Process discount values from the import data
                    $total_nominal_diskon1 = !empty($rowData['Voucher Ditanggung Penjual']) ? -abs((float)$rowData['Voucher Ditanggung Penjual']) : 0;
                    $total_nominal_diskon2 = !empty($rowData['KOMISI AMS/AFFILIATE']) ? -abs((float)$rowData['KOMISI AMS/AFFILIATE']) : 0;
                    $total_nominal_diskon3 = !empty($rowData['BIAYA ADMIN']) ? -abs((float)$rowData['BIAYA ADMIN']) : 0;
                    $total_nominal_diskon4 = !empty($rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) ? -abs((float)$rowData['BIAYA LAYANAN (GRATIS ONGKIR + CASHBACK)']) : 0;
                    $total_nominal_diskon5 = !empty($rowData['DISKON 5']) ? -abs((float)$rowData['DISKON 5']) : 0;
                    $total_nominal_diskon6 = !empty($rowData['DISKON 6']) ? -abs((float)$rowData['DISKON 6']) : 0;
                    $total_nominal_diskon7 = !empty($rowData['DISKON 7']) ? -abs((float)$rowData['DISKON 7']) : 0;
                    $total_nominal_diskon8 = !empty($rowData['DISKON 8']) ? -abs((float)$rowData['DISKON 8']) : 0;
                    $total_nominal_diskon9 = !empty($rowData['DISKON 9']) ? -abs((float)$rowData['DISKON 9']) : 0;
                    $total_nominal_diskon10 = !empty($rowData['DISKON 10']) ? -abs((float)$rowData['DISKON 10']) : 0;
                    $total_nominal_diskon11 = !empty($rowData['DISKON 11']) ? -abs((float)$rowData['DISKON 11']) : 0;
                    $total_nominal_diskon12 = !empty($rowData['DISKON 12']) ? -abs((float)$rowData['DISKON 12']) : 0;
                    
                    $total_saldo_masuk = (float)$rowData['JUMLAH MASUK PEMBAYARAN'];
                    
                    // Create a transaction for each tax_id
                    foreach ($taxGroups as $taxId => $group) {
                        // Calculate proportion based on nominal_harga
                        $proportion = $totalInvoiceValue > 0 ? ($group['total_nominal'] / $totalInvoiceValue) : (1 / count($taxGroups));
                        
                        // Distribute discounts proportionally
                        $nominal_diskon1 = $total_nominal_diskon1 * $proportion;
                        $nominal_diskon2 = $total_nominal_diskon2 * $proportion;
                        $nominal_diskon3 = $total_nominal_diskon3 * $proportion;
                        $nominal_diskon4 = $total_nominal_diskon4 * $proportion;
                        $nominal_diskon5 = $total_nominal_diskon5 * $proportion;
                        $nominal_diskon6 = $total_nominal_diskon6 * $proportion;
                        $nominal_diskon7 = $total_nominal_diskon7 * $proportion;
                        $nominal_diskon8 = $total_nominal_diskon8 * $proportion;
                        $nominal_diskon9 = $total_nominal_diskon9 * $proportion;
                        $nominal_diskon10 = $total_nominal_diskon10 * $proportion;
                        $nominal_diskon11 = $total_nominal_diskon11 * $proportion;
                        $nominal_diskon12 = $total_nominal_diskon12 * $proportion;
                        $saldo_masuk = $total_saldo_masuk * $proportion;
                        
                        // Calculate nominal_fix and outstanding for this tax group
                        $nominal_fix = $group['total_nominal'] + $nominal_diskon1 + $nominal_diskon2 + $nominal_diskon3 + 
                                      $nominal_diskon4 + $nominal_diskon5 + $nominal_diskon6 + $nominal_diskon7 + 
                                      $nominal_diskon8 + $nominal_diskon9 + $nominal_diskon10 + $nominal_diskon11 + $nominal_diskon12;
                        
                        $outstanding = $nominal_fix - $saldo_masuk;
                        
                        // Add validation for potential overpayment scenarios
                        if ($saldo_masuk > 0 && $nominal_fix > 0) {
                            $ratio = $saldo_masuk / $nominal_fix;
                            if ($ratio > 3.0) { // If payment is more than 3x the expected amount
                                Log::warning("Potential overpayment detected for order {$order->order_number} (tax_id: {$taxId}): saldo_masuk={$saldo_masuk}, nominal_fix={$nominal_fix}");
                            }
                        }
                        
                        // Generate invoice number for this tax_id
                        $invoiceNumber = $this->generateInvoiceForOrder($order, $taxId);
                        
                        // Prepare transaction data for bulk insert
                        $transactionsToInsert[] = [
                            'tanggal_order' => $order->tanggal,
                            'hari_order' => $order->hari,
                            'no_order' => $order->order_number,
                            'no_invoice' => $invoiceNumber,
                            'order_id' => $order->id,
                            'qty' => $group['total_qty'],
                            'nominal_harga' => $group['total_nominal'],
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
                    }
                    
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
                    ShopeeFinancialTransaction::insert($transactionsToInsert);
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
                            ShopeeFinancialTransaction::create($transaction);
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
                'shopee_import_data',
                'shopee_import_issues', 
                'shopee_preview_data',
                'shopee_preview_headers',
                'shopee_header_labels',
                'shopee_total_rows',
                'shopee_valid_rows', 
                'shopee_invalid_rows',
                'shopee_process_token'
            ]);
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopeeCache();
            
            // Store skipped reasons in session if any (platform-specific)
            if (!empty($skippedReasons)) {
                session(['shopee_skipped_reasons' => $skippedReasons]);
            } else {
                // Clear any old skipped reasons if no new ones
                session()->forget('shopee_skipped_reasons');
            }
            
            return redirect()->route('finance.shopee.index')
                ->with('success', "Berhasil mengimpor $importCount transaksi finansial. $skippedCount transaksi dilewati.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during Shopee financial import: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error memproses data: ' . $e->getMessage());
        }
    }

    /**
     * Clear Shopee-related cache
     */
    private function clearShopeeCache()
    {
        // Clear totals cache patterns
        $cacheKeys = [
            'shopee_totals_*',
            'shopee_missing_orders_*'
        ];
        
        foreach ($cacheKeys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we'd need to implement cache tag clearing
                // For now, we'll clear specific cache keys
                cache()->forget('shopee_totals_' . md5(serialize(request()->all())));
                cache()->forget('shopee_missing_orders_' . date('Y-m-d-H'));
            }
        }
        
        Log::info('Shopee cache cleared after data update');
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
        })->with(['warehouseStock', 'orderItem.platformProduct.mappingBarang'])->get();
        
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
                
                // Add order item if not already added
                if ($bk->orderItem) {
                    $orderItemId = $bk->orderItem->id;
                    if (!isset($taxGroups[$taxId]['order_items'][$orderItemId])) {
                        $taxGroups[$taxId]['order_items'][$orderItemId] = $bk->orderItem;
                        $taxGroups[$taxId]['total_nominal'] += $bk->orderItem->price_after_discount * $bk->orderItem->quantity;

                        $packageQuantity = $bk->orderItem->platformProduct?->mappingBarang
                            ?->where('is_active', true)
                            ?->sum('quantity') ?: 1;
                        $taxGroups[$taxId]['total_qty'] += $bk->orderItem->quantity * $packageQuantity;
                    }
                }
            }
        }
        
        // If no BarangKeluar items, fall back to order items
        if (empty($taxGroups)) {
            $orderItems = $order->orderItems()->with(['warehouseStock', 'platformProduct.mappingBarang'])->get();
            
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
                    $taxGroups[$taxId]['total_nominal'] += $item->price_after_discount * $item->quantity;

                    $packageQuantity = $item->platformProduct?->mappingBarang
                        ?->where('is_active', true)
                        ?->sum('quantity') ?: 1;
                    $taxGroups[$taxId]['total_qty'] += $item->quantity * $packageQuantity;
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
            return ShopeeFinancialTransaction::generateInvoiceNumber($order, $taxId);
        }
        
        // Otherwise, find the tax_id with the highest quantity (backward compatibility)
        $taxGroups = $this->groupOrderItemsByTaxId($order);
        
        if (empty($taxGroups)) {
            // Default to Non-PKP if no tax_id found
            return ShopeeFinancialTransaction::generateInvoiceNumber($order, 4);
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
        
        return ShopeeFinancialTransaction::generateInvoiceNumber($order, $dominantTaxId ?? 4);
    }
    
    public function manual()
    {
        $shopeePlatformId = \App\Models\Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee%lamourad%'])->value('id') ?? 99;
        $platformLabel = 'Shopee Lamourad';
        
        // Get order_id from request if available
        $orderId = request('order_id');
        $order = null;
        
        // If order_id is provided, try to find the order
        if ($orderId) {
            $order = Order::where('platform_id', $shopeePlatformId)->find($orderId);
        }
        
        return view('financial.shopee.manual', compact('order', 'platformLabel'));
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
            'nominal_diskon7' => 'nullable|numeric',
            'nominal_diskon8' => 'nullable|numeric',
            'nominal_diskon9' => 'nullable|numeric',
            'nominal_diskon10' => 'nullable|numeric',
            'nominal_diskon11' => 'nullable|numeric',
            'nominal_diskon12' => 'nullable|numeric',
            'adjustment' => 'nullable|numeric',
        ]);
        
        try {
            DB::beginTransaction();
            
            $shopeePlatformId = \App\Models\Platform::whereRaw('LOWER(name) LIKE ?', ['%shopee%lamourad%'])->value('id') ?? 99;
            
            $order = Order::findOrFail($request->order_id);
            
            // Check if order is from Shopee platform (by platform_id)
            if (!$order->platform_id || $order->platform_id !== $shopeePlatformId) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Nomor pesanan yang dipilih bukan dari platform Shopee.');
            }

            if ($order->isFullyReturned()) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Order yang sudah diretur penuh tidak dapat dibuatkan transaksi finance.');
            }
            
            // Cek jika sudah ada transaksi untuk order ini
            $exists = ShopeeFinancialTransaction::where('order_id', $order->id)->exists();
            if ($exists) {
                return redirect()->back()->with('error', 'Transaksi untuk order ini sudah ada.');
            }
            
            // Group order items by tax_id from barang keluar
            $taxGroups = $this->groupOrderItemsByTaxId($order);
            
            if (empty($taxGroups)) {
                return redirect()->back()->with('error', 'Tidak ada barang keluar dengan tax_id untuk order ini.');
            }
            
            $invoiceMessages = [];
            $totalSaldoMasuk = $request->saldo_masuk;
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
                    ($request->nominal_diskon7 ? -abs((float)$request->nominal_diskon7) : 0) +
                    ($request->nominal_diskon8 ? -abs((float)$request->nominal_diskon8) : 0) +
                    ($request->nominal_diskon9 ? -abs((float)$request->nominal_diskon9) : 0) +
                    ($request->nominal_diskon10 ? -abs((float)$request->nominal_diskon10) : 0) +
                    ($request->nominal_diskon11 ? -abs((float)$request->nominal_diskon11) : 0) +
                    ($request->nominal_diskon12 ? -abs((float)$request->nominal_diskon12) : 0) +
                    ($request->adjustment ?? 0);
                $totalNominalFix += $groupNominalFix;
            }
            
            // Process each tax group separately
            foreach ($taxGroups as $taxId => $group) {
                $transaction = new ShopeeFinancialTransaction();
                $transaction->tanggal_order = $order->tanggal;
                $transaction->hari_order = $order->hari;
                $transaction->no_order = $order->order_number;
                $transaction->order_id = $order->id;
                
                // Calculate quantity and nominal for this tax group
                $transaction->qty = $group['total_qty'];
                $transaction->nominal_harga = $group['total_nominal'];
                
                // Distribute discounts proportionally based on nominal_harga
                $proportion = $totalNominalFix > 0 ? ($group['total_nominal'] / $order->orderItems->sum(function($item) {
                    return $item->price_after_discount * $item->quantity;
                })) : (1 / count($taxGroups));
                
                $transaction->nominal_diskon1 = $request->nominal_diskon1 ? -abs((float)$request->nominal_diskon1 * $proportion) : 0;
                $transaction->nominal_diskon2 = $request->nominal_diskon2 ? -abs((float)$request->nominal_diskon2 * $proportion) : 0;
                $transaction->nominal_diskon3 = $request->nominal_diskon3 ? -abs((float)$request->nominal_diskon3 * $proportion) : 0;
                $transaction->nominal_diskon4 = $request->nominal_diskon4 ? -abs((float)$request->nominal_diskon4 * $proportion) : 0;
                $transaction->nominal_diskon5 = $request->nominal_diskon5 ? -abs((float)$request->nominal_diskon5 * $proportion) : 0;
                $transaction->nominal_diskon6 = $request->nominal_diskon6 ? -abs((float)$request->nominal_diskon6 * $proportion) : 0;
                $transaction->nominal_diskon7 = $request->nominal_diskon7 ? -abs((float)$request->nominal_diskon7 * $proportion) : 0;
                $transaction->nominal_diskon8 = $request->nominal_diskon8 ? -abs((float)$request->nominal_diskon8 * $proportion) : 0;
                $transaction->nominal_diskon9 = $request->nominal_diskon9 ? -abs((float)$request->nominal_diskon9 * $proportion) : 0;
                $transaction->nominal_diskon10 = $request->nominal_diskon10 ? -abs((float)$request->nominal_diskon10 * $proportion) : 0;
                $transaction->nominal_diskon11 = $request->nominal_diskon11 ? -abs((float)$request->nominal_diskon11 * $proportion) : 0;
                $transaction->nominal_diskon12 = $request->nominal_diskon12 ? -abs((float)$request->nominal_diskon12 * $proportion) : 0;
                $transaction->adjustment = ($request->adjustment ?? 0) * $proportion;
                $transaction->saldo_masuk = $totalSaldoMasuk * $proportion;
                $transaction->tanggal_masuk_pembayaran = $request->tanggal_masuk_pembayaran;
                $transaction->hari_masuk_pembayaran = $request->hari_masuk_pembayaran;
                
                // Generate invoice number for this tax_id
                $transaction->no_invoice = $this->generateInvoiceForOrder($order, $taxId);
                
                // Use the model's helper methods to calculate values
                $transaction->calculateNominalFix()
                            ->calculateOutstanding()
                            ->calculatePercentages();
                
                // Add validation for potential overpayment scenarios
                if ($transaction->saldo_masuk > 0 && $transaction->nominal_fix > 0) {
                    $ratio = $transaction->saldo_masuk / $transaction->nominal_fix;
                    if ($ratio > 3.0) { // If payment is more than 3x the expected amount
                        Log::warning("Potential overpayment detected for order {$order->order_number} (tax_id: {$taxId}): saldo_masuk={$transaction->saldo_masuk}, nominal_fix={$transaction->nominal_fix}");
                    }
                }
                
                $transaction->save();
                
                $taxCategory = in_array($taxId, [1, 3, 5, 7]) ? 'PKP' : 'Non-PKP';
                $invoiceMessages[] = "Invoice {$transaction->no_invoice} berhasil dibuat untuk barang {$taxCategory}";
            }
            
            DB::commit();
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopeeCache();
            
            return redirect()->route('finance.shopee.index')
                ->with('success', implode('<br>', $invoiceMessages));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menambahkan transaksi Shopee manual: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Adjust transaction value
     */
    public function adjust(Request $request, $id)
    {
        $request->validate([
            'adjustment' => 'required|numeric',
            'adjustment_description' => 'nullable|string|max:500',
        ]);
        
        try {
            DB::beginTransaction();
            
            $transaction = ShopeeFinancialTransaction::findOrFail($id);
            
            // Check if the transaction is locked
            if ($transaction->isLocked()) {
                return redirect()->back()->with('error', 'Transaksi ini telah dikunci dan tidak dapat diubah. Hubungi administrator untuk membuka kunci.');
            }
            
            // Save previous values for history
            $oldValues = [
                'adjustment' => $transaction->adjustment,
                'adjustment_description' => $transaction->adjustment_description,
                'nominal_fix' => $transaction->nominal_fix,
                'outstanding' => $transaction->outstanding
            ];
            
            $oldAdjustment = $transaction->adjustment;
            $transaction->adjustment = $request->adjustment;
            $transaction->adjustment_description = $request->adjustment_description;
            
            // Use the model's helper methods to recalculate all values
            $transaction->calculateNominalFix()
                       ->calculateOutstanding()
                       ->calculatePercentages();
            
            // Simpan ke adjustment_histories
            AdjustmentHistory::create([
                'transaction_id' => $transaction->id,
                'platform' => 'shopee',
                'old_value' => $oldAdjustment,
                'new_value' => $transaction->adjustment,
                'description' => $request->adjustment_description,
                'user_id' => auth()->id(),
            ]);
            
            $transaction->save();
            
            DB::commit();
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopeeCache();
            
            return redirect()->route('finance.shopee.index')
                ->with('success', 'Adjustment berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat adjust transaksi Shopee: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a transaction
     */
    public function delete($id)
    {
        try {
            $transaction = ShopeeFinancialTransaction::findOrFail($id);
            $transaction->delete();
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopeeCache();
            
            return redirect()->route('finance.shopee.index')
                ->with('success', 'Transaksi berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Error saat menghapus transaksi Shopee: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Print invoice for a transaction
     * 
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function printInvoice($id)
    {
        try {
            // Only fetch the transaction without complex relationships
            $transaction = ShopeeFinancialTransaction::findOrFail($id);
            
            // Determine the tax_id from the invoice number
            $taxId = null;
            $logoFile = 'PKP.jpeg'; // Default logo file
            $isPKP = true; // Default to PKP
            
            // Format baru: 0001/2601/AMP/01 atau 0001/2601/AMP/02
            if (strpos($transaction->no_invoice, 'AMP/01') !== false) {
                $taxId = 3; // PKP - Skincare (HGN -> PKP)
                $logoFile = 'PKP.jpeg';
                $isPKP = true;
            } elseif (strpos($transaction->no_invoice, 'AMP/02') !== false) {
                $taxId = 4; // Non PKP - Skincare (LM -> NON PKP)
                $logoFile = 'NONPKP.jpeg';
                $isPKP = false;
            } else {
                // If we can't determine from pattern, extract the last 2 digits
                preg_match('/\/(\d{2})$/', $transaction->no_invoice, $matches);
                if (!empty($matches[1])) {
                    $taxId = (int) $matches[1];
                    $isPKP = in_array($taxId, [1, 3, 5, 7]);
                    $logoFile = $isPKP ? 'PKP.jpeg' : 'NONPKP.jpeg';
                }
            }
            
            // Fetch minimal order data for display purposes only
            $orderNumber = $transaction->no_order;
            
            // Get simple product name from order if available, otherwise use generic name
            $productName = "Produk Shopee";
            
            try {
                if ($transaction->order_id) {
                    // Just get basic order info without detailed relationships
                    $order = \App\Models\Order::select('id', 'order_number')->find($transaction->order_id);
                    if ($order) {
                        // Try to get at least the first product name for display purposes
                        $firstItem = $order->orderItems()
                            ->select('id', 'platform_product_id')
                            ->with(['platformProduct:id,platform_product_name'])
                            ->first();
                        
                        if ($firstItem && $firstItem->platformProduct) {
                            $productName = $firstItem->platformProduct->platform_product_name;
                        }
                    }
                }
            } catch (\Exception $e) {
                // If there's any error getting product info, just use the default
                Log::warning("Couldn't fetch product name for invoice {$transaction->no_invoice}: " . $e->getMessage());
            }
            
            // Log transaction details for monitoring
            Log::info("Printing simplified invoice {$transaction->no_invoice}, QTY: {$transaction->qty}, HPP: {$transaction->nominal_harga}");
            
            return view('financial.shopee.print-invoice', compact('transaction', 'logoFile', 'isPKP', 'productName'));
        } catch (\Exception $e) {
            Log::error("Error saat print invoice Shopee: " . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat mencetak invoice: ' . $e->getMessage());
        }
    }

    /**
     * Lock a transaction
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function lock($id)
    {
        try {
            $transaction = ShopeeFinancialTransaction::findOrFail($id);
            $transaction->lock(auth()->id());
            
            return redirect()->back()->with('success', 'Transaksi berhasil dikunci.');
        } catch (\Exception $e) {
            Log::error("Error saat mengunci transaksi Shopee: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Unlock a transaction
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function unlock($id)
    {
        try {
            $transaction = ShopeeFinancialTransaction::findOrFail($id);
            
            // Only admin or the person who locked it can unlock
            if (auth()->user()->role != 'admin' && $transaction->locked_by != auth()->id()) {
                return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk membuka kunci transaksi ini.');
            }
            
            $transaction->unlock();
            
            // Clear cache to ensure fresh data on next load
            $this->clearShopeeCache();
            
            return redirect()->back()->with('success', 'Kunci transaksi berhasil dibuka.');
        } catch (\Exception $e) {
            Log::error("Error saat membuka kunci transaksi Shopee: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * View transaction history
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function history($id)
    {
        try {
            $transaction = ShopeeFinancialTransaction::with('lockedByUser')->findOrFail($id);
            $adjustmentHistories = \App\Models\AdjustmentHistory::where('transaction_id', $transaction->id)
                ->where('platform', 'shopee')
                ->orderBy('created_at', 'desc')
                ->with('user')
                ->get();
            return view('financial.shopee.history', compact('transaction', 'adjustmentHistories'));
        } catch (\Exception $e) {
            Log::error("Error saat melihat history transaksi Shopee: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Export data to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel(Request $request)
    {
        $filename = 'shopee_finance_analytics_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new ShopeeFinanceAnalyticsExport($request->all()), $filename);
    }

    /**
     * Export data to PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(Request $request)
    {
        $query = ShopeeFinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Apply the same filters as in index method
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
                // Lunas: outstanding harus tepat 0 (dengan toleransi kecil untuk floating point)
                $query->whereRaw('ABS(outstanding) <= 0.01');
            } elseif ($request->outstanding_status === '1') {
                // Outstanding: outstanding tidak sama dengan 0 (ada selisih)
                $query->whereRaw('ABS(outstanding) > 0.01');
            }
        }
        
        // Exclude only fully returned orders (retur full), not partial returns (retur sebagian)
        // Orders with partial returns should still appear in finance index
        $query->whereNotExists(function($subQuery) {
            $subQuery->select(DB::raw(1))
                ->from('retur_penjualans as rp')
                ->join('orders as o', 'rp.order_id', '=', 'o.id')
                ->join('retur_penjualan_details as rpd', 'rp.id', '=', 'rpd.retur_penjualan_id')
                ->join('order_items as oi', 'rpd.order_item_id', '=', 'oi.id')
                ->whereColumn('o.order_number', 'shopee_financial_transactions.no_order')
                ->whereIn('rp.status', ['draft', 'selesai'])
                ->whereNotNull('o.order_number')
                ->where('o.order_number', '!=', '')
                ->groupBy('o.id')
                ->havingRaw('SUM(rpd.qty) >= (
                    SELECT COALESCE(SUM(oi2.quantity * COALESCE((
                        SELECT SUM(mb.quantity)
                        FROM mapping_barangs mb
                        WHERE mb.platform_product_id = oi2.platform_product_id
                          AND mb.is_active = 1
                    ), 1)), 0)
                    FROM order_items oi2
                    WHERE oi2.order_id = o.id
                )');
        });
        
        $transactions = $query->orderBy('tanggal_order', 'desc')->get();
        
        $pdf = Pdf::loadView('exports.financial.shopee', compact('transactions'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download('shopee_finance_analytics_' . date('Y-m-d_H-i-s') . '.pdf');
    }
}
