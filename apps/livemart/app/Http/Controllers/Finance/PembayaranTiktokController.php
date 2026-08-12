<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\TiktokFinancialTransaction;
use App\Models\Order;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use App\Models\InvoiceSequence;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TiktokFinanceAnalyticsExport;
use App\Exports\TiktokCashFlowExport;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AdjustmentHistory;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class PembayaranTiktokController extends Controller
{
    public function index(Request $request)
    {
        // Get platform by name (Tiktok Lamourad)
        $platformModel = Platform::whereRaw('LOWER(name) = ?', ['tiktok lamourad'])->first();
        
        // Jika tidak ditemukan berdasarkan nama, cari dengan LIKE
        if (!$platformModel) {
            $platformModel = Platform::whereRaw('LOWER(name) LIKE ?', ['%tiktok lamourad%'])
                ->whereRaw('LOWER(name) NOT LIKE ?', ['%tiktok liefmarket%'])
                ->first();
        }
        
        if (!$platformModel) {
            return redirect()->back()->with('error', 'Platform Tiktok Lamourad tidak ditemukan');
        }
        $platformId = $platformModel->id;
        $platform = 'tiktok';
        $platformLabel = 'Tiktok Lamourad';
        
        $query = TiktokFinancialTransaction::with([
            'order.orderItems.platformProduct.mappingBarang', 
            'order.orderItems.warehouseStock.tax', 
            'order.mainCategory'
        ]);
        
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tiktok_financial_transactions.tanggal_masuk_pembayaran', '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->whereDate('tiktok_financial_transactions.tanggal_masuk_pembayaran', '<=', $request->to_date);
        }
        
        // Filter by order date range
        if ($request->filled('from_order_date')) {
            $query->whereDate('tiktok_financial_transactions.tanggal_order', '>=', $request->from_order_date);
        }
        
        if ($request->filled('to_order_date')) {
            $query->whereDate('tiktok_financial_transactions.tanggal_order', '<=', $request->to_order_date);
        }
        
        // Filter by order number - use table alias to avoid ambiguity
        if ($request->filled('order_number')) {
            $query->where('tiktok_financial_transactions.no_order', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by invoice number - use table alias to avoid ambiguity
        if ($request->filled('invoice_number')) {
            $query->where('tiktok_financial_transactions.no_invoice', 'like', '%' . $request->invoice_number . '%');
        }
        
        // Filter by tax ID - use table alias to avoid ambiguity
        if ($request->filled('tax_id')) {
            $taxIds = (array) $request->tax_id;
            $query->where(function($q) use ($taxIds) {
                foreach ($taxIds as $taxId) {
                    $q->orWhere('tiktok_financial_transactions.no_invoice', 'like', '%/' . str_pad($taxId, 2, '0', STR_PAD_LEFT));
                }
            });
        }
        
        // Filter by payment date - use table alias to avoid ambiguity
        if ($request->filled('payment_date')) {
            $query->whereDate('tiktok_financial_transactions.tanggal_masuk_pembayaran', $request->payment_date);
        }
        
        // Filter by nominal range - use table alias to avoid ambiguity
        if ($request->filled('min_nominal')) {
            $query->where('tiktok_financial_transactions.nominal_fix', '>=', $request->min_nominal);
        }
        
        if ($request->filled('max_nominal')) {
            $query->where('tiktok_financial_transactions.nominal_fix', '<=', $request->max_nominal);
        }
        
        // Filter by outstanding status - OPTIMIZED
        // Menggunakan JOIN dengan derived table yang lebih efisien daripada whereIn
        // Derived table hanya di-evaluate sekali, bukan untuk setiap row
        if ($request->filled('outstanding_status')) {
            if ($request->outstanding_status === '0') {
                // Filter: hanya tampilkan transaksi yang nomor order-nya memiliki total outstanding = 0
                // Optimasi: gunakan INNER JOIN dengan derived table (lebih cepat dari whereIn)
                $query->join(DB::raw('(
                    SELECT no_order
                    FROM tiktok_financial_transactions
                    GROUP BY no_order
                    HAVING SUM(outstanding) = 0
                ) as outstanding_zero'), 'tiktok_financial_transactions.no_order', '=', 'outstanding_zero.no_order');
            } elseif ($request->outstanding_status === '1') {
                // Filter: hanya tampilkan transaksi yang nomor order-nya memiliki total outstanding != 0
                // Optimasi: gunakan INNER JOIN dengan derived table (lebih cepat dari whereIn)
                $query->join(DB::raw('(
                    SELECT no_order
                    FROM tiktok_financial_transactions
                    GROUP BY no_order
                    HAVING SUM(outstanding) != 0
                ) as outstanding_nonzero'), 'tiktok_financial_transactions.no_order', '=', 'outstanding_nonzero.no_order');
            }
        }
        
        // Get all transactions matching the query (without pagination yet)
        $allTransactions = $query->orderBy('tiktok_financial_transactions.tanggal_order', 'desc')->get();

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
            
        // Group transactions by order number for display
        $groupedTransactions = $transactions->getCollection()->groupBy('no_order');
        
        // Get unpaid orders with optimized SQL query
        // Calculate total_value and days_since_order directly in SQL
        // Filter fully returned orders at SQL level (NOT EXISTS)
        // Use pagination to limit data load
        $missingOrdersQuery = Order::query()
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.tanggal',
                'orders.status',
                DB::raw('COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0) as total_value'),
                DB::raw('COALESCE(DATEDIFF(NOW(), orders.tanggal), 0) as days_since_order'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as order_total_quantity'),
                DB::raw('COALESCE((
                    SELECT SUM(rpd.qty)
                    FROM retur_penjualan_details rpd
                    INNER JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                    INNER JOIN order_items oi ON rpd.order_item_id = oi.id
                    WHERE oi.order_id = orders.id
                    AND rp.status IN ("draft", "selesai")
                ), 0) as returned_quantity')
            ])
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->whereDoesntHave('tiktokFinancialTransactions')
            ->where('orders.platform_id', $platformId)
            // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
            // Fully returned = all remaining order-item qty == 0 AND has retur (draft/selesai)
            ->where(function($q) {
                $q->whereHas('orderItems', function($itemQuery) {
                    $itemQuery->where('quantity', '>', 0);
                })->orWhereDoesntHave('returPenjualan', function($returQuery) {
                    $returQuery->whereIn('status', ['draft', 'selesai']);
                });
            })
            ->groupBy('orders.id', 'orders.order_number', 'orders.tanggal', 'orders.status');
        
        // Apply filters for unpaid orders
        $fromUnpaidDate = $request->filled('from_order_date') ? $request->from_order_date : $request->from_date;
        $toUnpaidDate = $request->filled('to_order_date') ? $request->to_order_date : $request->to_date;
        
        if (!empty($fromUnpaidDate)) {
            $missingOrdersQuery->whereDate('orders.tanggal', '>=', $fromUnpaidDate);
        }
        
        if (!empty($toUnpaidDate)) {
            $missingOrdersQuery->whereDate('orders.tanggal', '<=', $toUnpaidDate);
        }
        
        // Filter by order number
        if ($request->filled('order_number')) {
            $missingOrdersQuery->where('orders.order_number', 'like', '%' . $request->order_number . '%');
        }
        
        // Filter by min/max value (using having clause after groupBy)
        if ($request->filled('min_nominal')) {
            $missingOrdersQuery->havingRaw('total_value >= ?', [$request->min_nominal]);
        }
        
        if ($request->filled('max_nominal')) {
            $missingOrdersQuery->havingRaw('total_value <= ?', [$request->max_nominal]);
        }
        
        // Filter by age (days since order)
        if ($request->filled('min_age')) {
            $missingOrdersQuery->havingRaw('days_since_order >= ?', [$request->min_age]);
        }
        
        if ($request->filled('max_age')) {
            $missingOrdersQuery->havingRaw('days_since_order <= ?', [$request->max_age]);
        }
        
        // Order by
        $orderBy = $request->get('sort_by', 'tanggal');
        $orderDirection = $request->get('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        
        if ($orderBy === 'tanggal') {
            $missingOrdersQuery->orderBy('orders.tanggal', $orderDirection);
        } elseif ($orderBy === 'order_number') {
            $missingOrdersQuery->orderBy('orders.order_number', $orderDirection);
        } elseif ($orderBy === 'total_value') {
            $missingOrdersQuery->orderBy('total_value', $orderDirection);
        } else {
            $missingOrdersQuery->orderBy('orders.tanggal', 'desc');
        }
        
        // Paginate with 30 items per page
        $missingOrders = $missingOrdersQuery->paginate(30, ['*'], 'unpaid_page')
            ->withQueryString(); // Preserve query parameters
        
        // Calculate total unpaid nominal using SQL (for summary card)
        $unpaidQueryParams = [$platformId];
        $unpaidInnerFilter = "";
        $unpaidOuterFilter = "";
        
        if (!empty($fromUnpaidDate)) {
            $unpaidInnerFilter .= " AND DATE(orders.tanggal) >= ?";
            $unpaidQueryParams[] = $fromUnpaidDate;
        }
        
        if (!empty($toUnpaidDate)) {
            $unpaidInnerFilter .= " AND DATE(orders.tanggal) <= ?";
            $unpaidQueryParams[] = $toUnpaidDate;
        }
        
        if ($request->filled('order_number')) {
            $unpaidInnerFilter .= " AND orders.order_number LIKE ?";
            $unpaidQueryParams[] = '%' . $request->order_number . '%';
        }
        
        if ($request->filled('min_nominal')) {
            $unpaidOuterFilter .= " AND total_value >= ?";
            $unpaidQueryParams[] = $request->min_nominal;
        }
        
        if ($request->filled('max_nominal')) {
            $unpaidOuterFilter .= " AND total_value <= ?";
            $unpaidQueryParams[] = $request->max_nominal;
        }
        
        if ($request->filled('min_age')) {
            $unpaidOuterFilter .= " AND days_since_order >= ?";
            $unpaidQueryParams[] = $request->min_age;
        }
        
        if ($request->filled('max_age')) {
            $unpaidOuterFilter .= " AND days_since_order <= ?";
            $unpaidQueryParams[] = $request->max_age;
        }

        $unpaidNominal = DB::selectOne("
            SELECT COALESCE(SUM(total_value), 0) as total
            FROM (
                SELECT 
                    orders.id,
                    orders.order_number,
                    COALESCE(SUM(order_items.price_after_discount * order_items.quantity), 0) as total_value,
                    COALESCE(DATEDIFF(NOW(), orders.tanggal), 0) as days_since_order,
                    COALESCE(SUM(order_items.quantity), 0) as order_total_quantity,
                    COALESCE((
                        SELECT SUM(rpd.qty)
                        FROM retur_penjualan_details rpd
                        INNER JOIN retur_penjualans rp ON rpd.retur_penjualan_id = rp.id
                        INNER JOIN order_items oi ON rpd.order_item_id = oi.id
                        WHERE oi.order_id = orders.id
                        AND rp.status IN ('draft', 'selesai')
                    ), 0) as returned_quantity
                FROM orders
                LEFT JOIN order_items ON orders.id = order_items.order_id
                WHERE orders.platform_id = ?
                {$unpaidInnerFilter}
                AND NOT EXISTS (
                    SELECT 1 FROM tiktok_financial_transactions 
                    WHERE tiktok_financial_transactions.order_id = orders.id
                )
                AND (
                    EXISTS (
                        SELECT 1 FROM order_items oi3
                        WHERE oi3.order_id = orders.id
                        AND oi3.quantity > 0
                    )
                    OR NOT EXISTS (
                        SELECT 1 FROM retur_penjualans rp2
                        WHERE rp2.order_id = orders.id
                        AND rp2.status IN ('draft', 'selesai')
                    )
                )
                GROUP BY orders.id, orders.order_number, orders.tanggal
            ) as unpaid_orders
            WHERE 1=1
            {$unpaidOuterFilter}
        ", $unpaidQueryParams);
        
        $unpaidNominal = $unpaidNominal->total ?? 0;
            
        return view('financial.tiktok.index', compact(
            'transactions', 
            'groupedTransactions', 
            'platform', 
            'platformLabel',
            'missingOrders',
            'unpaidNominal',
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
        return view('financial.tiktok.import');
    }

    public function import()
    {
        return view('financial.tiktok.import');
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
            if (!session()->has('tiktok_import_data')) {
                return redirect()->route('finance.tiktok.import')
                    ->with('error', 'Tidak ada data preview. Silakan upload file terlebih dahulu.');
            }
            
            // Get the data from the session
            $data = session('tiktok_import_data');
            $previewData = session('tiktok_preview_data');
            $previewHeaders = session('tiktok_preview_headers');
            $headerLabels = session('tiktok_header_labels');
            $issues = session('tiktok_import_issues');
            $totalRows = session('tiktok_total_rows');
            $validRows = session('tiktok_valid_rows');
            $invalidRows = session('tiktok_invalid_rows');
            
            // If any of the required data is missing, redirect to import
            if (!$previewData || !$previewHeaders || !$headerLabels) {
                return redirect()->route('finance.tiktok.import')
                    ->with('error', 'Data preview tidak lengkap. Silakan upload file kembali.');
            }
            
            return view('financial.tiktok.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
        }
        
        $request->validate([
            'file' => 'required|mimes:xlsx,xls', // Remove max file size limit to use hosting settings
        ]);
    
        $file = $request->file('file');
        $path = $file->getRealPath();
        
        $data = [];
        $headers = [];
        $issues = [];
        
        try {
            // Log file processing start
            Log::info('Starting TikTok financial import preview', [
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
                Log::warning("Sheet 'Order details' not found, attempting to use first sheet");
                $orderDetailsSheet = $spreadsheet->getSheet(0);
                if (!$orderDetailsSheet) {
                    // Free memory
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                    
                    return redirect()->back()->with('error', 'Sheet "Order details" tidak ditemukan dalam file Excel.');
                }
            }
            
            $worksheet = $orderDetailsSheet;
            Log::info("Using sheet: " . $worksheet->getTitle());
            
            // Get highest row and column to limit processing
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestDataColumn();
            
            Log::info("Excel file dimensions: {$highestRow} rows x {$highestColumn} columns");
            
            // Limit processing to prevent memory issues
            if ($highestRow > 10000) {
                Log::warning("File too large, limiting to first 10000 rows");
                $highestRow = 10000;
            }
            
            // Get the header (first row)
            $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
            
            // Clean headers from extra spaces
            $headers = array_map('trim', $headerRow);
            Log::info("Headers found: " . json_encode($headers));
            
            // Validate required headers (only essential fields)
            $requiredHeaders = [
                'NOMOR PESANAN',
                'TANGGAL MASUK PEMBAYARAN',
                'HARI MASUK PEMBAYARAN',
                'JUMLAH MASUK PEMBAYARAN'
            ];
            
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
                
                'jumlah masuk pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                'jumlah pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                'amount' => 'JUMLAH MASUK PEMBAYARAN',
                'jumlah' => 'JUMLAH MASUK PEMBAYARAN',
                'nominal pembayaran' => 'JUMLAH MASUK PEMBAYARAN',
                
                'biaya admin' => 'BIAYA ADMIN',
                'admin fee' => 'BIAYA ADMIN',
                
                'affiliate commission' => 'AFFILIATE COMMISSION',
                'komisi affiliate' => 'AFFILIATE COMMISSION',
                'affiliasi' => 'AFFILIATE COMMISSION',
                
                'seller shipping fee + sfp service fee' => 'SELLER SHIPPING FEE + SFP SERVICE FEE',
                'seller shipping fee + sfp' => 'SELLER SHIPPING FEE + SFP SERVICE FEE',
                'shipping fee' => 'SELLER SHIPPING FEE + SFP SERVICE FEE',
                'biaya pengiriman' => 'SELLER SHIPPING FEE + SFP SERVICE FEE',
                
                'voucher xtra service fee' => 'VOUCHER XTRA SERVICE FEE',
                'voucher service fee' => 'VOUCHER XTRA SERVICE FEE',
                'biaya voucher' => 'VOUCHER XTRA SERVICE FEE',
                
                'cashback fee' => 'CASHBACK FEE',
                'biaya cashback' => 'CASHBACK FEE',
                
                'biaya6' => 'BIAYA6',
                'biaya 6' => 'BIAYA6',
                'biaya_6' => 'BIAYA6',
                'cost6' => 'BIAYA6',
                'cost 6' => 'BIAYA6',
                
                'biaya7' => 'BIAYA7',
                'biaya 7' => 'BIAYA7',
                'biaya_7' => 'BIAYA7',
                'cost7' => 'BIAYA7',
                'cost 7' => 'BIAYA7',
                
                'biaya8' => 'BIAYA8',
                'biaya 8' => 'BIAYA8',
                'biaya_8' => 'BIAYA8',
                'cost8' => 'BIAYA8',
                'cost 8' => 'BIAYA8',
                
                'biaya9' => 'BIAYA9',
                'biaya 9' => 'BIAYA9',
                'biaya_9' => 'BIAYA9',
                'cost9' => 'BIAYA9',
                'cost 9' => 'BIAYA9',
                
                'biaya10' => 'BIAYA10',
                'biaya 10' => 'BIAYA10',
                'biaya_10' => 'BIAYA10',
                'cost10' => 'BIAYA10',
                'cost 10' => 'BIAYA10',
                
                'biaya11' => 'BIAYA11',
                'biaya 11' => 'BIAYA11',
                'biaya_11' => 'BIAYA11',
                'cost11' => 'BIAYA11',
                'cost 11' => 'BIAYA11',
                
                'biaya12' => 'BIAYA12',
                'biaya 12' => 'BIAYA12',
                'biaya_12' => 'BIAYA12',
                'cost12' => 'BIAYA12',
                'cost 12' => 'BIAYA12'
            ];
            
            // Map the headers to standard names
            $mappedHeaders = [];
            foreach ($headers as $index => $header) {
                // Convert to lowercase for case-insensitive matching
                $lowerHeader = strtolower($header);
                if (isset($headerMapping[$lowerHeader])) {
                    $mappedHeaders[$index] = $headerMapping[$lowerHeader];
                } else {
                    $mappedHeaders[$index] = $header;
                }
            }
            
            // Replace original headers with mapped ones for further processing
            $headers = $mappedHeaders;
            Log::info("Mapped headers: " . json_encode($headers));
            
            // Check for missing required headers
            $foundRequiredHeaders = [];
            foreach ($requiredHeaders as $requiredHeader) {
                if (in_array($requiredHeader, $headers)) {
                    $foundRequiredHeaders[] = $requiredHeader;
                }
            }
            $missingHeaders = array_diff($requiredHeaders, $foundRequiredHeaders);
            if (!empty($missingHeaders)) {
                Log::warning("Missing required headers: " . json_encode($missingHeaders));
                return redirect()->back()->with('error', 'Format file tidak sesuai. Kolom yang tidak ditemukan: ' . implode(', ', $missingHeaders));
            }
            
            // Check for optional headers
            $hasDiskon6 = in_array('DISKON6', $headers);
            
            // Get column indexes for mapped headers
            $columnIndices = [];
            foreach ($headers as $index => $header) {
                $columnIndices[$header] = $index;
            }
            
            // Helper function to get value from row using standardized header name
            $getValue = function($row, $standardHeader) use ($columnIndices, $headers) {
                // Find the index for this standard header
                $index = array_search($standardHeader, $headers);
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
                // Use standard header names for consistency
                $rowData['NOMOR PESANAN'] = $getValue($row, 'NOMOR PESANAN');
                $rowData['TANGGAL MASUK PEMBAYARAN'] = $getValue($row, 'TANGGAL MASUK PEMBAYARAN');
                $rowData['HARI MASUK PEMBAYARAN'] = $getValue($row, 'HARI MASUK PEMBAYARAN');
                $rowData['JUMLAH MASUK PEMBAYARAN'] = $getValue($row, 'JUMLAH MASUK PEMBAYARAN');
                $rowData['BIAYA ADMIN'] = $getValue($row, 'BIAYA ADMIN');
                $rowData['AFFILIATE COMMISSION'] = $getValue($row, 'AFFILIATE COMMISSION');
                $rowData['SELLER SHIPPING FEE + SFP SERVICE FEE'] = $getValue($row, 'SELLER SHIPPING FEE + SFP SERVICE FEE');
                $rowData['VOUCHER XTRA SERVICE FEE'] = $getValue($row, 'VOUCHER XTRA SERVICE FEE');
                $rowData['CASHBACK FEE'] = $getValue($row, 'CASHBACK FEE');
                // Optional fee columns default to 0 if header/value missing
                $rowData['BIAYA6'] = $getValue($row, 'BIAYA6') !== '' ? $getValue($row, 'BIAYA6') : 0;
                $rowData['BIAYA7'] = $getValue($row, 'BIAYA7') !== '' ? $getValue($row, 'BIAYA7') : 0;
                $rowData['BIAYA8'] = $getValue($row, 'BIAYA8') !== '' ? $getValue($row, 'BIAYA8') : 0;
                $rowData['BIAYA9'] = $getValue($row, 'BIAYA9') !== '' ? $getValue($row, 'BIAYA9') : 0;
                $rowData['BIAYA10'] = $getValue($row, 'BIAYA10') !== '' ? $getValue($row, 'BIAYA10') : 0;
                $rowData['BIAYA11'] = $getValue($row, 'BIAYA11') !== '' ? $getValue($row, 'BIAYA11') : 0;
                $rowData['BIAYA12'] = $getValue($row, 'BIAYA12') !== '' ? $getValue($row, 'BIAYA12') : 0;
                
                // Collect order number for batch query - gunakan trim()
                if (!empty($rowData['NOMOR PESANAN'])) {
                    $orderNumbers[] = trim($rowData['NOMOR PESANAN']);
                }
                
                $processedRows[] = [
                    'rowNumber' => $rowNumber,
                    'rowData' => $rowData
                ];
            }
            
            // Batch query: Get all orders and their items in one query
            $orders = [];
            $existingTransactionsByOrder = [];
            
            if (!empty($orderNumbers)) {
                // Remove duplicates to avoid unnecessary queries
                $orderNumbers = array_unique($orderNumbers);
                
                // Get all orders with their items in one query
                $orders = Order::whereIn('order_number', $orderNumbers)
                    ->with(['orderItems'])
                    ->get()
                    ->keyBy('order_number');
                
                // Get all existing transactions in one query
                $existingTransactions = TiktokFinancialTransaction::whereIn('no_order', $orderNumbers)
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
                if (empty($rowData['NOMOR PESANAN'])) {
                    $rowIssues[] = 'Nomor pesanan kosong';
                }
                
                // 2. Validate date format
                if (!empty($rowData['TANGGAL MASUK PEMBAYARAN'])) {
                    // If date is an Excel date object
                    if ($rowData['TANGGAL MASUK PEMBAYARAN'] instanceof \DateTime) {
                        $rowData['TANGGAL MASUK PEMBAYARAN'] = $rowData['TANGGAL MASUK PEMBAYARAN']->format('Y-m-d');
                    } else {
                        // Try to parse date in various formats
                        $date = null;
                        $dateValue = $rowData['TANGGAL MASUK PEMBAYARAN'];
                        
                        // If it's a numeric value (Excel serial date)
                        if (is_numeric($dateValue)) {
                            try {
                                $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateValue);
                                $rowData['TANGGAL MASUK PEMBAYARAN'] = $excelDate->format('Y-m-d');
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
                                    $rowData['TANGGAL MASUK PEMBAYARAN'] = $parsedDate->format('Y-m-d');
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
                
                // 3. Validate payment day
                if (empty($rowData['HARI MASUK PEMBAYARAN'])) {
                    $rowIssues[] = 'Hari pembayaran kosong';
                }
                
                // 4. Validate payment amount - allow 0 values
                if (!isset($rowData['JUMLAH MASUK PEMBAYARAN'])) {
                    $rowIssues[] = 'Jumlah pembayaran tidak ditemukan';
                } elseif ($rowData['JUMLAH MASUK PEMBAYARAN'] === '') {
                    $rowIssues[] = 'Jumlah pembayaran kosong';
                }
                
                // 5. Check if order exists in database (using pre-loaded data)
                // Gunakan trim() untuk menghindari masalah spasi
                $orderNumber = trim($rowData['NOMOR PESANAN']);
                $order = $orders[$orderNumber] ?? null;
                
                if (!$order) {
                    Log::warning("Order tidak ditemukan untuk nomor pesanan: {$orderNumber}");
                    $rowIssues[] = 'Nomor order tidak ditemukan di database';
                    
                    // Skip this transaction instead of creating placeholder data
                    // This prevents incorrect nominal_fix calculations
                    $rowData['_valid'] = false;
                    $rowData['_issues'] = $rowIssues;
                    $rowData['_row'] = $rowNumber;
                    
                    $data[] = $rowData;
                    $issues[$rowNumber] = $rowIssues;
                    continue; // Skip to next transaction
                }
                
                // 6. Check if transaction already exists (using pre-loaded data)
                $transactionExists = in_array($orderNumber, $existingTransactions);
                if ($transactionExists) {
                    Log::warning("Transaksi sudah ada untuk order: {$orderNumber}");
                    $rowIssues[] = 'Transaksi untuk order ini sudah ada';
                }
                
                // Create preview data even if transaction exists to show in the preview
                // Ensure order date is always valid - ambil dari database berdasarkan order_number
                $orderDate = $order->tanggal 
                    ?? Order::where('order_number', $orderNumber)->value('tanggal')
                    ?? throw new \Exception("Tanggal order tidak ditemukan untuk No Order {$orderNumber}");
                
                // Calculate total price from order items considering quantity (using pre-loaded data)
                $nominal_harga = 0;
                foreach ($order->orderItems as $item) {
                    $nominal_harga += $item->price_after_discount * $item->quantity;
                }
                
                // Calculate total quantity across all order items (using pre-loaded data)
                $totalQty = $order->orderItems->sum('quantity');
                
                // Preview data
                $previewData[] = [
                    'tanggal_order' => $orderDate,
                    'hari_order' => $order->hari,
                    'no_order' => $order->order_number,
                    'no_invoice' => 'PREVIEW-' . $order->order_number,
                    'qty' => $totalQty,
                    'nominal_harga' => $nominal_harga,
                    'nominal_diskon1' => $nd1 = !empty($rowData['BIAYA ADMIN']) ? -abs((float) $rowData['BIAYA ADMIN']) : 0,
                    'nominal_diskon2' => $nd2 = !empty($rowData['AFFILIATE COMMISSION']) ? -abs((float) $rowData['AFFILIATE COMMISSION']) : 0,
                    'nominal_diskon3' => $nd3 = !empty($rowData['SELLER SHIPPING FEE + SFP SERVICE FEE']) ? -abs((float) $rowData['SELLER SHIPPING FEE + SFP SERVICE FEE']) : 0,
                    'nominal_diskon4' => $nd4 = !empty($rowData['VOUCHER XTRA SERVICE FEE']) ? -abs((float) $rowData['VOUCHER XTRA SERVICE FEE']) : 0,
                    'nominal_diskon5' => $nd5 = !empty($rowData['CASHBACK FEE']) ? -abs((float) $rowData['CASHBACK FEE']) : 0,
                    'nominal_diskon6' => $nd6 = !empty($rowData['BIAYA6']) ? -abs((float) $rowData['BIAYA6']) : 0,
                    'nominal_diskon7' => $nd7 = !empty($rowData['BIAYA7']) ? -abs((float) $rowData['BIAYA7']) : 0,
                    'nominal_diskon8' => $nd8 = !empty($rowData['BIAYA8']) ? -abs((float) $rowData['BIAYA8']) : 0,
                    'nominal_diskon9' => $nd9 = !empty($rowData['BIAYA9']) ? -abs((float) $rowData['BIAYA9']) : 0,
                    'nominal_diskon10' => $nd10 = !empty($rowData['BIAYA10']) ? -abs((float) $rowData['BIAYA10']) : 0,
                    'nominal_diskon11' => $nd11 = !empty($rowData['BIAYA11']) ? -abs((float) $rowData['BIAYA11']) : 0,
                    'nominal_diskon12' => $nd12 = !empty($rowData['BIAYA12']) ? -abs((float) $rowData['BIAYA12']) : 0,
                    'adjustment' => 0,
                    'nominal_fix' => $fix = $nominal_harga + $nd1 + $nd2 + $nd3 + $nd4 + $nd5 + $nd6 + $nd7 + $nd8 + $nd9 + $nd10 + $nd11 + $nd12,
                    'saldo_masuk' => $sm = isset($rowData['JUMLAH MASUK PEMBAYARAN']) ? (float) $rowData['JUMLAH MASUK PEMBAYARAN'] : 0,
                    'tanggal_masuk_pembayaran' => $rowData['TANGGAL MASUK PEMBAYARAN'],
                    'hari_masuk_pembayaran' => $rowData['HARI MASUK PEMBAYARAN'],
                    'outstanding' => $fix - $sm,
                    'persentase_diskon1' => 0,
                    'persentase_diskon2' => 0,
                    'persentase_diskon3' => 0,
                    'persentase_diskon4' => 0,
                    'persentase_diskon5' => 0,
                    'persentase_diskon6' => 0,
                    'persentase_diskon7' => 0,
                    'persentase_diskon8' => 0,
                    'persentase_diskon9' => 0,
                    'persentase_diskon10' => 0,
                    'persentase_diskon11' => 0,
                    'persentase_diskon12' => 0,
                    'total_persentase' => 0,
                ];
                
                // Add issues if any
                if (!empty($rowIssues)) {
                    $issues[$rowNumber] = $rowIssues;
                }
                
                // Add validation status to data
                $rowData['_valid'] = empty($rowIssues);
                $rowData['_issues'] = $rowIssues;
                $rowData['_row'] = $rowNumber;
                
                $data[] = $rowData;
            }
        } catch (\Exception $e) {
            Log::error("Excel file processing error: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }
        
        // Define display columns for preview
        $previewHeaders = [
            'tanggal_order', 'hari_order', 'no_order', 'no_invoice', 
            'nominal_harga', 'qty', 'nominal_diskon1', 'nominal_diskon2', 
            'nominal_diskon3', 'nominal_diskon4', 'nominal_diskon5', 
            'nominal_diskon6', 'nominal_diskon7', 'nominal_diskon8', 
            'nominal_diskon9', 'nominal_diskon10', 'nominal_diskon11', 
            'nominal_diskon12', 'adjustment', 'nominal_fix', 'saldo_masuk', 
            'tanggal_masuk_pembayaran', 'hari_masuk_pembayaran', 
            'outstanding', 'persentase_diskon1', 'persentase_diskon2', 
            'persentase_diskon3', 'persentase_diskon4', 'persentase_diskon5',
            'persentase_diskon6', 'persentase_diskon7', 'persentase_diskon8',
            'persentase_diskon9', 'persentase_diskon10', 'persentase_diskon11',
            'persentase_diskon12', 'total_persentase'
        ];
        
        // More user-friendly header labels
        $headerLabels = [
            'tanggal_order' => 'Tanggal Order',
            'hari_order' => 'Hari Order',
            'no_order' => 'No. Order',
            'no_invoice' => 'No. Invoice',
            'nominal_harga' => 'Nominal Harga',
            'qty' => 'Quantity',
            'nominal_diskon1' => 'Biaya Admin',
            'nominal_diskon2' => 'Affiliate Commission',
            'nominal_diskon3' => 'Seller Shipping Fee + SFP',
            'nominal_diskon4' => 'Voucher Xtra Service Fee',
            'nominal_diskon5' => 'Cashback Fee',
            'nominal_diskon6' => 'Biaya 6',
            'nominal_diskon7' => 'Biaya 7',
            'nominal_diskon8' => 'Biaya 8',
            'nominal_diskon9' => 'Biaya 9',
            'nominal_diskon10' => 'Biaya 10',
            'nominal_diskon11' => 'Biaya 11',
            'nominal_diskon12' => 'Biaya 12',
            'adjustment' => 'Adjustment',
            'nominal_fix' => 'Nominal Fix',
            'saldo_masuk' => 'Saldo Masuk',
            'tanggal_masuk_pembayaran' => 'Tanggal Masuk Pembayaran',
            'hari_masuk_pembayaran' => 'Hari Masuk Pembayaran',
            'outstanding' => 'Outstanding',
            'persentase_diskon1' => '% Biaya 1',
            'persentase_diskon2' => '% Biaya 2',
            'persentase_diskon3' => '% Biaya 3',
            'persentase_diskon4' => '% Biaya 4',
            'persentase_diskon5' => '% Biaya 5',
            'persentase_diskon6' => '% Biaya 6',
            'persentase_diskon7' => '% Biaya 7',
            'persentase_diskon8' => '% Biaya 8',
            'persentase_diskon9' => '% Biaya 9',
            'persentase_diskon10' => '% Biaya 10',
            'persentase_diskon11' => '% Biaya 11',
            'persentase_diskon12' => '% Biaya 12',
            'total_persentase' => 'Total %'
        ];
        
        // Save preview data in session
        session(['tiktok_import_data' => $data]);
        session(['tiktok_import_issues' => $issues]);
        session(['tiktok_preview_data' => $previewData]);
        session(['tiktok_preview_headers' => $previewHeaders]);
        session(['tiktok_header_labels' => $headerLabels]);
        
        // Calculate statistics
        $totalRows = count($data);
        $validRows = count(array_filter($data, function($row) { return $row['_valid']; }));
        $invalidRows = $totalRows - $validRows;
        
        session(['tiktok_total_rows' => $totalRows]);
        session(['tiktok_valid_rows' => $validRows]);
        session(['tiktok_invalid_rows' => $invalidRows]);
        
        // Generate and store process token for secure processing
        $processToken = uniqid('tiktok_', true);
        session(['tiktok_process_token' => $processToken]);
        
        return view('financial.tiktok.preview', compact('data', 'previewData', 'previewHeaders', 'headerLabels', 'issues', 'totalRows', 'validRows', 'invalidRows'));
    }

    /**
     * Process the imported data
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function process(Request $request)
    {
        try {
            // Increase execution time limit for large imports
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');
            
            Log::info('Starting TikTok financial import process');
            
            // Validate process token
            $processToken = $request->input('process_token');
            if (!$processToken || $processToken !== session('tiktok_process_token')) {
                return redirect()->route('finance.tiktok.import')
                    ->with('error', 'Token proses tidak valid. Silakan upload ulang file.');
            }
            
            // Get data from session instead of POST data
            $importData = session('tiktok_import_data');
            if (!$importData) {
                return redirect()->route('finance.tiktok.import')
                    ->with('error', 'Data import tidak ditemukan. Silakan upload ulang file.');
            }
            
            // Filter only valid data
            $validData = array_filter($importData, function($rowData) {
                return isset($rowData['_valid']) && ($rowData['_valid'] === true || $rowData['_valid'] === 'true');
            });
            
            if (empty($validData)) {
                return redirect()->route('finance.tiktok.import')
                    ->with('error', 'Tidak ada data valid untuk diproses.');
            }
            
            DB::beginTransaction();
            $importCount = 0;
            $skippedCount = 0;
            $skippedReasons = [];
            
            // Process in smaller batches to avoid memory issues
            $batchSize = 50; // Reduced batch size for better performance
            $batches = array_chunk($validData, $batchSize);
            
            Log::info('Processing ' . count($validData) . ' valid records in ' . count($batches) . ' batches');
            
            // Collect all order numbers first for batch queries
            $allOrderNumbers = [];
            $processedRows = [];
            
            foreach ($batches as $batchIndex => $batch) {
                Log::info('Processing batch ' . ($batchIndex + 1) . ' of ' . count($batches));
                
                foreach ($batch as $index => $rowData) {
                    // Skip if no order number provided
                    if (empty($rowData['NOMOR PESANAN'])) {
                        $skippedCount++;
                        $skippedReasons[] = "Row #$index has no order number";
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
                
                // Get all existing transactions (no_order + no_invoice) in one query
                $existingTransRows = TiktokFinancialTransaction::whereIn('no_order', $allOrderNumbers)
                    ->get(['no_order', 'no_invoice']);
                // Group by order and map to existing tax status set (PKP/NON_PKP)
                foreach ($existingTransRows as $row) {
                    $orderNo = $row->no_order;
                    $inv = $row->no_invoice ?? '';
                    $status = null;
                    if (strpos($inv, '/01') !== false) {
                        $status = 'PKP';
                    } elseif (strpos($inv, '/02') !== false) {
                        $status = 'NON_PKP';
                    }
                    if (!isset($existingTransactionsByOrder[$orderNo])) {
                        $existingTransactionsByOrder[$orderNo] = [];
                    }
                    if ($status) {
                        $existingTransactionsByOrder[$orderNo][$status] = true;
                    } else {
                        // If no recognizable suffix, mark as generic existing
                        $existingTransactionsByOrder[$orderNo]['ANY'] = true;
                    }
                }
                
                Log::info("Batch loaded " . count($orders) . " orders and " . count($existingTransRows) . " existing transactions");
            }
            
            // Process rows with pre-loaded data
            $validOrders = [];
            $validTaxGroups = [];
            
            foreach ($processedRows as $processedRow) {
                $index = $processedRow['index'];
                $rowData = $processedRow['rowData'];
                
                try {
                    // Check if order exists (using pre-loaded data)
                    $order = $orders[$rowData['NOMOR PESANAN']] ?? null;
                    if (!$order) {
                        $skippedCount++;
                        $skippedReasons[] = "Row #$index: Order {$rowData['NOMOR PESANAN']} not found";
                        continue;
                    }

                    // PERBAIKAN: Skip order yang sudah diretur penuh (retur draft/selesai)
                    // agar transaksi finance tidak dibuat setelah retur full diproses
                    if ($order->isFullyReturned()) {
                        $skippedCount++;
                        $skippedReasons[] = "Row #$index: Order {$rowData['NOMOR PESANAN']} sudah diretur penuh";
                        \Log::warning("Skipping order {$rowData['NOMOR PESANAN']} - order is fully returned");
                        continue;
                    }
                    
                    // Do not skip entire order if any transaction exists; we will create only missing PKP/NON_PKP groups
                    
                    // Use pre-loaded order items
                    $orderItems = $order->orderItems;
                    
                    // Group OrderItems by tax status (PKP/Non-PKP) instead of tax_id
                    // This ensures 1 order with same tax status only gets 1 invoice
                    $itemsByTaxStatus = [];
                    $totalQty = 0;
                    
                    foreach ($orderItems as $item) {
                        // Get tax_id from warehouse_stock, or use a default tax_id if not available
                        $taxId = null;
                        if ($item->warehouseStock && $item->warehouseStock->tax_id) {
                            $taxId = $item->warehouseStock->tax_id;
                        } else {
                            // Default to tax_id 4 (Non PKP - Skincare) for items without tax info
                            // This ensures all order items are included in financial transactions
                            $taxId = 4;
                        }
                        
                        // Determine tax status (PKP or Non-PKP)
                        $isPKP = in_array($taxId, [1, 3, 5, 7]);
                        $taxStatus = $isPKP ? 'PKP' : 'NON_PKP';
                        
                        if (!isset($itemsByTaxStatus[$taxStatus])) {
                            $itemsByTaxStatus[$taxStatus] = [];
                        }
                        $itemsByTaxStatus[$taxStatus][] = [
                            'item' => $item,
                            'tax_id' => $taxId
                        ];
                        $totalQty += $item->quantity;
                    }
                    
                    // Order is valid, add to the list of valid orders
                    $validOrders[$order->id] = [
                        'order' => $order,
                        'rowData' => $rowData,
                        'taxGroups' => $itemsByTaxStatus,
                        'totalQty' => $totalQty
                    ];
                    
                    // Track all tax status groups needed
                    foreach ($itemsByTaxStatus as $taxStatus => $items) {
                        if (!isset($validTaxGroups[$taxStatus])) {
                            $validTaxGroups[$taxStatus] = 0;
                        }
                        $validTaxGroups[$taxStatus]++;
                    }
                    
                } catch (\Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Row #$index: Error - " . $e->getMessage();
                    Log::error("Error processing row $index: " . $e->getMessage());
                    Log::error($e->getTraceAsString());
                    continue;
                }
            }
            
            // Sekarang, dapatkan nomor invoice untuk setiap kelompok status pajak secara batch
            // Grouping berdasarkan PKP/Non-PKP saja, bukan per tax_id
            $invoiceNumbersByTaxStatus = [];
            foreach ($validTaxGroups as $taxStatus => $count) {
                // Cari order pertama untuk mendapatkan tanggal order dan kategori dominan
                $firstOrder = null;
                $dominantCategory = InvoiceSequence::CATEGORY_SKINCARE; // Default SKINCARE untuk TikTok
                
                foreach ($validOrders as $orderData) {
                    if (isset($orderData['taxGroups'][$taxStatus])) {
                        $firstOrder = $orderData['order'];
                        
                        // Tentukan kategori dominan berdasarkan tax_id yang ada di group ini
                        $taxIdsInGroup = [];
                        foreach ($orderData['taxGroups'][$taxStatus] as $itemData) {
                            $taxIdsInGroup[] = $itemData['tax_id'];
                        }
                        
                        // Jika ada KOPI (tax_id 1,2,5,6), gunakan KOPI, else SKINCARE
                        $hasKopi = false;
                        foreach ($taxIdsInGroup as $tid) {
                            if (in_array($tid, [1, 2, 5, 6])) {
                                $hasKopi = true;
                                break;
                            }
                        }
                        $dominantCategory = $hasKopi ? InvoiceSequence::CATEGORY_KOPI : InvoiceSequence::CATEGORY_SKINCARE;
                        break;
                    }
                }
                
                $orderDate = $firstOrder ? $firstOrder->tanggal : null;
                
                // Mendapatkan jenis penjualan (untuk Tiktok selalu ONLINE)
                $salesType = InvoiceSequence::SALES_ONLINE;
                
                // Convert tax status string ke format InvoiceSequence
                $taxStatusEnum = ($taxStatus === 'PKP') 
                    ? InvoiceSequence::TAX_PKP 
                    : InvoiceSequence::TAX_NON_PKP;
                
                // Mendapatkan batch nomor invoice dengan tanggal order
                $invoiceNumbersByTaxStatus[$taxStatus] = InvoiceSequence::getBatchInvoiceNumbers(
                    $dominantCategory, 
                    $salesType, 
                    $taxStatusEnum, 
                    $count,
                    $orderDate
                );
            }
            
            // Selanjutnya, proses setiap order yang valid
            $processedCount = 0;
            $totalValidOrders = count($validOrders);
            Log::info("Processing $totalValidOrders valid orders");
            
            foreach ($validOrders as $orderId => $orderData) {
                $processedCount++;
                if ($processedCount % 100 == 0) {
                    Log::info("Processed $processedCount of $totalValidOrders orders");
                }
                $order = $orderData['order'];
                $rowData = $orderData['rowData'];
                $itemsByTaxStatus = $orderData['taxGroups'];
                $totalQty = $orderData['totalQty'];
                
                try {
                    // Calculate total order price
                    $totalOrderPrice = 0;
                    foreach ($order->orderItems as $item) {
                        $totalOrderPrice += $item->price_after_discount * $item->quantity;
                    }
                    
                    // Process discount values from the import data
                    $nominal_diskon1 = !empty($rowData['BIAYA ADMIN']) ? -abs((float) $rowData['BIAYA ADMIN']) : 0;
                    $nominal_diskon2 = !empty($rowData['AFFILIATE COMMISSION']) ? -abs((float) $rowData['AFFILIATE COMMISSION']) : 0;
                    $nominal_diskon3 = !empty($rowData['SELLER SHIPPING FEE + SFP SERVICE FEE']) ? -abs((float) $rowData['SELLER SHIPPING FEE + SFP SERVICE FEE']) : 0;
                    $nominal_diskon4 = !empty($rowData['VOUCHER XTRA SERVICE FEE']) ? -abs((float) $rowData['VOUCHER XTRA SERVICE FEE']) : 0;
                    $nominal_diskon5 = !empty($rowData['CASHBACK FEE']) ? -abs((float) $rowData['CASHBACK FEE']) : 0;
                    $nominal_diskon6 = !empty($rowData['BIAYA6']) ? -abs((float) $rowData['BIAYA6']) : 0;
                    $nominal_diskon7 = !empty($rowData['BIAYA7']) ? -abs((float) $rowData['BIAYA7']) : 0;
                    $nominal_diskon8 = !empty($rowData['BIAYA8']) ? -abs((float) $rowData['BIAYA8']) : 0;
                    $nominal_diskon9 = !empty($rowData['BIAYA9']) ? -abs((float) $rowData['BIAYA9']) : 0;
                    $nominal_diskon10 = !empty($rowData['BIAYA10']) ? -abs((float) $rowData['BIAYA10']) : 0;
                    $nominal_diskon11 = !empty($rowData['BIAYA11']) ? -abs((float) $rowData['BIAYA11']) : 0;
                    $nominal_diskon12 = !empty($rowData['BIAYA12']) ? -abs((float) $rowData['BIAYA12']) : 0;
                    
                    // Sort tax status groups to ensure consistent processing order: PKP first, then Non-PKP
                    $sortedTaxStatuses = ['PKP', 'NON_PKP'];
                    $sortedItemsByTaxStatus = [];
                    foreach ($sortedTaxStatuses as $status) {
                        if (isset($itemsByTaxStatus[$status])) {
                            $sortedItemsByTaxStatus[$status] = $itemsByTaxStatus[$status];
                        }
                    }
                    
                    // Process each tax status group and create a transaction for each
                    foreach ($sortedItemsByTaxStatus as $taxStatus => $itemsData) {
                        // Skip if this taxStatus already has a transaction for this order
                        $existingForOrder = $existingTransactionsByOrder[$order->order_number] ?? [];
                        if (!empty($existingForOrder[$taxStatus])) {
                            continue;
                        }
                        // Get the next invoice number from the pre-generated batch
                        $invoiceData = array_shift($invoiceNumbersByTaxStatus[$taxStatus]);
                        if (!$invoiceData) {
                            throw new \Exception("No invoice number available for tax status $taxStatus");
                        }
                        
                        // Create transaction for this tax status group
                        $transaction = new TiktokFinancialTransaction();
                        // Use the actual order's tanggal directly, not the derived orderDate
                        $transaction->tanggal_order = $order->tanggal;
                        $transaction->hari_order = $order->hari;
                        $transaction->no_order = $order->order_number;
                        $transaction->no_invoice = $invoiceData['invoice_number'];
                        $transaction->order_id = $order->id;
                        
                        // Calculate value-based proportion for this tax status group
                        $groupQty = 0;
                        $groupValue = 0;
                        
                        foreach ($itemsData as $itemData) {
                            $item = $itemData['item'];
                            $itemQty = $item->quantity;
                            $groupQty += $itemQty;
                            
                            // Calculate value using OrderItem (price_after_discount is per unit)
                            $unitPrice = $item->price_after_discount;
                            $groupValue += $unitPrice * $itemQty;
                        }
                        
                        // Calculate proportion based on VALUE, not quantity
                        $proportion = ($totalOrderPrice > 0) ? $groupValue / $totalOrderPrice : 1;
                        
                        // Set values based on value-based proportion
                        $transaction->nominal_harga = round($groupValue, 2); // Use actual group value
                        $transaction->qty = $groupQty; // Set the group quantity
                        $transaction->nominal_diskon1 = round($nominal_diskon1 * $proportion, 2);
                        $transaction->nominal_diskon2 = round($nominal_diskon2 * $proportion, 2);
                        $transaction->nominal_diskon3 = round($nominal_diskon3 * $proportion, 2);
                        $transaction->nominal_diskon4 = round($nominal_diskon4 * $proportion, 2);
                        $transaction->nominal_diskon5 = round($nominal_diskon5 * $proportion, 2);
                        $transaction->nominal_diskon6 = round($nominal_diskon6 * $proportion, 2);
                        $transaction->nominal_diskon7 = round($nominal_diskon7 * $proportion, 2);
                        $transaction->nominal_diskon8 = round($nominal_diskon8 * $proportion, 2);
                        $transaction->nominal_diskon9 = round($nominal_diskon9 * $proportion, 2);
                        $transaction->nominal_diskon10 = round($nominal_diskon10 * $proportion, 2);
                        $transaction->nominal_diskon11 = round($nominal_diskon11 * $proportion, 2);
                        $transaction->nominal_diskon12 = round($nominal_diskon12 * $proportion, 2);
                        
                        // Set payment info
                        $transaction->tanggal_masuk_pembayaran = $rowData['TANGGAL MASUK PEMBAYARAN'];
                        $transaction->hari_masuk_pembayaran = $rowData['HARI MASUK PEMBAYARAN'];
                        
                        // Set adjustment to 0 by default
                        $transaction->adjustment = 0;
                        
                        // Calculate nominal_fix
                        $transaction->nominal_fix = $transaction->nominal_harga + 
                            $transaction->nominal_diskon1 + 
                            $transaction->nominal_diskon2 + 
                            $transaction->nominal_diskon3 + 
                            $transaction->nominal_diskon4 + 
                            $transaction->nominal_diskon5 + 
                            $transaction->nominal_diskon6 + 
                            $transaction->nominal_diskon7 + 
                            $transaction->nominal_diskon8 + 
                            $transaction->nominal_diskon9 + 
                            $transaction->nominal_diskon10 + 
                            $transaction->nominal_diskon11 + 
                            $transaction->nominal_diskon12 + 
                            $transaction->adjustment;
                        
                        // Set saldo_masuk from Excel data - use exact value, no fallback
                        // If JUMLAH MASUK PEMBAYARAN is 0 or empty, keep it as 0
                        $totalSaldoMasuk = isset($rowData['JUMLAH MASUK PEMBAYARAN']) ? 
                            (float) $rowData['JUMLAH MASUK PEMBAYARAN'] : 0;
                        
                        // Apply proportion to saldo_masuk like other values
                        $transaction->saldo_masuk = round($totalSaldoMasuk * $proportion, 2);
                        
                        // Calculate outstanding based on actual saldo_masuk
                        $transaction->outstanding = $transaction->nominal_fix - $transaction->saldo_masuk;
                        
                        // Add validation for potential overpayment scenarios
                        if ($transaction->saldo_masuk > 0 && $transaction->nominal_fix > 0) {
                            $ratio = $transaction->saldo_masuk / $transaction->nominal_fix;
                            if ($ratio > 3.0) { // If payment is more than 3x the expected amount
                                Log::warning("Potential overpayment detected for order {$order->order_number}: saldo_masuk={$transaction->saldo_masuk}, nominal_fix={$transaction->nominal_fix}");
                            }
                        }
                        
                        // Calculate percentages
                        if ($transaction->nominal_harga > 0) {
                            $transaction->persentase_diskon1 = ($transaction->nominal_diskon1 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon2 = ($transaction->nominal_diskon2 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon3 = ($transaction->nominal_diskon3 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon4 = ($transaction->nominal_diskon4 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon5 = ($transaction->nominal_diskon5 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon6 = ($transaction->nominal_diskon6 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon7 = ($transaction->nominal_diskon7 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon8 = ($transaction->nominal_diskon8 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon9 = ($transaction->nominal_diskon9 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon10 = ($transaction->nominal_diskon10 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon11 = ($transaction->nominal_diskon11 / $transaction->nominal_harga) * 100;
                            $transaction->persentase_diskon12 = ($transaction->nominal_diskon12 / $transaction->nominal_harga) * 100;
                            $transaction->total_persentase = $transaction->persentase_diskon1 + $transaction->persentase_diskon2 + 
                                                         $transaction->persentase_diskon3 + $transaction->persentase_diskon4 +
                                                         $transaction->persentase_diskon5 + $transaction->persentase_diskon6 +
                                                         $transaction->persentase_diskon7 + $transaction->persentase_diskon8 +
                                                         $transaction->persentase_diskon9 + $transaction->persentase_diskon10 +
                                                         $transaction->persentase_diskon11 + $transaction->persentase_diskon12;
                        }
                        
                        $transaction->save();
                        $importCount++;
                    }
                } catch (\Exception $e) {
                    $skippedCount++;
                    $skippedReasons[] = "Order {$order->order_number}: Error - " . $e->getMessage();
                    Log::error("Error creating transaction for order {$order->order_number}: " . $e->getMessage());
                    Log::error($e->getTraceAsString());
                    continue;
                }
            }
            
            DB::commit();
            
            // Clean up session data after successful processing
            session()->forget([
                'tiktok_import_data',
                'tiktok_import_issues',
                'tiktok_preview_data', 
                'tiktok_preview_headers',
                'tiktok_header_labels',
                'tiktok_total_rows',
                'tiktok_valid_rows',
                'tiktok_invalid_rows',
                'tiktok_process_token'
            ]);
            
            // Store skipped reasons in session if any (platform-specific)
            if (!empty($skippedReasons)) {
                session(['tiktok_skipped_reasons' => $skippedReasons]);
            } else {
                // Clear any old skipped reasons if no new ones
                session()->forget('tiktok_skipped_reasons');
            }
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', "Berhasil mengimpor $importCount transaksi finansial. $skippedCount transaksi dilewati.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error during Tiktok financial import: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Error memproses data: ' . $e->getMessage());
        }
    }

    public function manual()
    {
        $tiktokPlatformId = \App\Models\Platform::whereRaw('LOWER(name) LIKE ?', ['%tiktok%lamourad%'])->value('id') ?? 99;
        $platformLabel = 'Tiktok Lamourad';
        
        // Get order_id from request if available
        $orderId = request('order_id');
        $order = null;
        
        // If order_id is provided, try to find the order
        if ($orderId) {
            $order = Order::where('platform_id', $tiktokPlatformId)->find($orderId);
        }
        
        return view('financial.tiktok.manual', compact('order', 'platformLabel'));
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'order_id' => [
                'required',
                'exists:orders,id',
            ],
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
        ], [
            'order_id.required' => 'Nomor pesanan wajib dipilih. Silakan pilih nomor pesanan dari dropdown.',
            'order_id.exists' => 'Nomor pesanan yang dipilih tidak valid atau tidak ditemukan.',
        ]);
        
        try {
            DB::beginTransaction();
            
            $tiktokPlatformId = \App\Models\Platform::whereRaw('LOWER(name) LIKE ?', ['%tiktok%lamourad%'])->value('id') ?? 99;
            
            $order = Order::with(['orderItems.warehouseStock.tax', 'mainCategory', 'platform'])->findOrFail($request->order_id);
            
            // Check if order is from TikTok platform (by platform_id)
            if (!$order->platform_id || $order->platform_id !== $tiktokPlatformId) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Nomor pesanan yang dipilih bukan dari platform TikTok.');
            }
            
            // Check if transaction already exists for this order
            $exists = TiktokFinancialTransaction::where('order_id', $order->id)->exists();
            if ($exists) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Transaksi untuk nomor pesanan "' . $order->order_number . '" sudah ada. Silakan pilih nomor pesanan lain yang belum memiliki transaksi.');
            }
            
            // Group order items by tax_id from barang keluar
            $barangKeluarItems = \App\Models\BarangKeluar::whereHas('orderItem', function($query) use ($order) {
                $query->where('order_id', $order->id);
            })->with(['warehouseStock', 'orderItem'])->get();
            
            // Group by tax_id
            $taxGroups = [];
            
            foreach ($barangKeluarItems as $bk) {
                if ($bk->warehouseStock) {
                    $taxId = $bk->warehouseStock->tax_id ?? 4;
                    if (!$bk->warehouseStock->tax_id) {
                        Log::warning("WarehouseStock #{$bk->warehouseStock->id} has NULL tax_id, using default 4 (Non-PKP) for Tiktok order grouping");
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
                            Log::warning("WarehouseStock #{$item->warehouseStock->id} has NULL tax_id, using default 4 (Non-PKP) for Tiktok order grouping");
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
            
            if (empty($taxGroups)) {
                return redirect()->back()->with('error', 'Tidak ada barang keluar dengan tax_id untuk order ini.');
            }
            
            // Calculate total order price for proportion
            $totalOrderPrice = 0;
            foreach ($order->orderItems as $item) {
                $totalOrderPrice += $item->price_after_discount * $item->quantity;
            }
            
            // Process discount values from the form
            $nominal_diskon1 = !empty($request->nominal_diskon1) ? -abs((float) $request->nominal_diskon1) : 0;
            $nominal_diskon2 = !empty($request->nominal_diskon2) ? -abs((float) $request->nominal_diskon2) : 0;
            $nominal_diskon3 = !empty($request->nominal_diskon3) ? -abs((float) $request->nominal_diskon3) : 0;
            $nominal_diskon4 = !empty($request->nominal_diskon4) ? -abs((float) $request->nominal_diskon4) : 0;
            $nominal_diskon5 = !empty($request->nominal_diskon5) ? -abs((float) $request->nominal_diskon5) : 0;
            $nominal_diskon6 = !empty($request->nominal_diskon6) ? -abs((float) $request->nominal_diskon6) : 0;
            $nominal_diskon7 = !empty($request->nominal_diskon7) ? -abs((float) $request->nominal_diskon7) : 0;
            $nominal_diskon8 = !empty($request->nominal_diskon8) ? -abs((float) $request->nominal_diskon8) : 0;
            $nominal_diskon9 = !empty($request->nominal_diskon9) ? -abs((float) $request->nominal_diskon9) : 0;
            $nominal_diskon10 = !empty($request->nominal_diskon10) ? -abs((float) $request->nominal_diskon10) : 0;
            $nominal_diskon11 = !empty($request->nominal_diskon11) ? -abs((float) $request->nominal_diskon11) : 0;
            $nominal_diskon12 = !empty($request->nominal_diskon12) ? -abs((float) $request->nominal_diskon12) : 0;
            
            // Process each tax_id group and create a transaction for each
            $invoiceMessages = [];
            
            foreach ($taxGroups as $taxId => $group) {
                // Generate invoice number for this tax_id
                $invoiceNumber = TiktokFinancialTransaction::generateInvoiceNumber($order, $taxId);
                
                // Create transaction for this tax_id group
                $transaction = new TiktokFinancialTransaction();
                $transaction->tanggal_order = $order->tanggal;
                $transaction->hari_order = $order->hari;
                $transaction->no_order = $order->order_number;
                $transaction->no_invoice = $invoiceNumber;
                $transaction->order_id = $order->id;
                
                // Calculate value-based proportion for this tax_id group
                $groupQty = $group['total_qty'];
                $groupValue = $group['total_nominal'];
                
                // Calculate proportion based on VALUE, not quantity
                $proportion = ($totalOrderPrice > 0) ? $groupValue / $totalOrderPrice : (1 / count($taxGroups));
                
                // Set values based on value-based proportion
                $transaction->nominal_harga = round($groupValue, 2); // Use actual group value
                $transaction->qty = $groupQty; // Set the group quantity
                $transaction->nominal_diskon1 = round($nominal_diskon1 * $proportion, 2);
                $transaction->nominal_diskon2 = round($nominal_diskon2 * $proportion, 2);
                $transaction->nominal_diskon3 = round($nominal_diskon3 * $proportion, 2);
                $transaction->nominal_diskon4 = round($nominal_diskon4 * $proportion, 2);
                $transaction->nominal_diskon5 = round($nominal_diskon5 * $proportion, 2);
                $transaction->nominal_diskon6 = round($nominal_diskon6 * $proportion, 2);
                $transaction->nominal_diskon7 = round($nominal_diskon7 * $proportion, 2);
                $transaction->nominal_diskon8 = round($nominal_diskon8 * $proportion, 2);
                $transaction->nominal_diskon9 = round($nominal_diskon9 * $proportion, 2);
                $transaction->nominal_diskon10 = round($nominal_diskon10 * $proportion, 2);
                $transaction->nominal_diskon11 = round($nominal_diskon11 * $proportion, 2);
                $transaction->nominal_diskon12 = round($nominal_diskon12 * $proportion, 2);
                
                // Set payment info
                $transaction->tanggal_masuk_pembayaran = $request->tanggal_masuk_pembayaran;
                $transaction->hari_masuk_pembayaran = $request->hari_masuk_pembayaran;
                $transaction->saldo_masuk = round($request->saldo_masuk * $proportion, 2);
                
                // Set adjustment from form or 0 by default
                $transaction->adjustment = ($request->adjustment ?? 0) * $proportion;
                
                // Calculate nominal_fix
                $transaction->calculateNominalFix();
                
                // Calculate outstanding
                $transaction->calculateOutstanding();
                
                // Calculate percentages
                $transaction->calculatePercentages();
                
                $transaction->save();
                
                $taxCategory = in_array($taxId, [1, 3, 5, 7]) ? 'PKP' : 'Non-PKP';
                $invoiceMessages[] = "Invoice {$invoiceNumber} berhasil dibuat untuk barang {$taxCategory}";
            }
            
            DB::commit();
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', implode('<br>', $invoiceMessages));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to show proper error messages
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat menambahkan transaksi TikTok manual: " . $e->getMessage());
            Log::error("Request data: " . json_encode($request->all()));
            
            // Get order info if available for better error message
            $orderInfo = '';
            if ($request->has('order_id') && $request->order_id) {
                try {
                    $order = Order::find($request->order_id);
                    if ($order) {
                        $orderInfo = " untuk nomor pesanan: {$order->order_number}";
                    }
                } catch (\Exception $orderEx) {
                    // Ignore if we can't get order info
                }
            }
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan data' . $orderInfo . ': ' . $e->getMessage());
        }
    }

    /**
     * Adjust transaction value
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function adjust(Request $request, $id)
    {
        $request->validate([
            'adjustment' => 'required|numeric',
            'adjustment_description' => 'nullable|string|max:500',
        ]);
        
        try {
            DB::beginTransaction();
            
            $transaction = TiktokFinancialTransaction::findOrFail($id);
            
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
                'platform' => 'tiktok',
                'old_value' => $oldAdjustment,
                'new_value' => $transaction->adjustment,
                'description' => $request->adjustment_description,
                'user_id' => auth()->id(),
            ]);
            
            $transaction->save();
            
            DB::commit();
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', 'Adjustment berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error saat adjust transaksi TikTok: " . $e->getMessage());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete a transaction
     * 
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete($id)
    {
        try {
            $transaction = TiktokFinancialTransaction::findOrFail($id);
            $transaction->delete();
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', 'Transaksi berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error("Error saat menghapus transaksi TikTok: " . $e->getMessage());
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
            $transaction = TiktokFinancialTransaction::with([
                    'order.orderItems.platformProduct',
                    'order.orderItems.warehouseStock.tax'
                ])
                ->findOrFail($id);
                
            // Determine the tax_id from the invoice number and set logo accordingly
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
                preg_match('/(\d{2})$/', $transaction->no_invoice, $matches);
                if (!empty($matches[1])) {
                    $taxId = (int) $matches[1];
                    $isPKP = in_array($taxId, [1, 3, 5, 7]);
                    $logoFile = $isPKP ? 'PKP.jpeg' : 'NONPKP.jpeg';
                }
            }
            
            return view('financial.tiktok.print-invoice', compact('transaction', 'logoFile', 'isPKP'));
        } catch (\Exception $e) {
            Log::error("Error saat print invoice TikTok: " . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat mencetak invoice: ' . $e->getMessage());
        }
    }

    /**
     * Generate an invoice number for a manual order
     * 
     * @param Order $order
     * @return string
     */
    protected function generateInvoiceForOrder($order)
    {
        // For manual orders, default to tax_id 3 (SKINCARE - PKP ONLINE)
        return TiktokFinancialTransaction::generateInvoiceNumber($order, 3);
    }

    /**
     * Create a sample transaction for testing (temporary helper)
     */
    public function createSample()
    {
        try {
            DB::beginTransaction();
            
            // Find a TikTok order
            $order = Order::where('platform', 'tiktok')->first();
            
            if (!$order) {
                return redirect()->route('finance.tiktok.index')
                    ->with('error', 'No TikTok orders found in the database');
            }
            
            // Ensure order date is always valid
            $orderDate = $order->tanggal 
                ?? Order::where('id', $order->id)->value('tanggal')
                ?? throw new \Exception("Tanggal order tidak ditemukan untuk Order ID {$order->id}");
            
            // Check if transaction already exists for this order
            $exists = TiktokFinancialTransaction::where('order_id', $order->id)->exists();
            if ($exists) {
                return redirect()->route('finance.tiktok.index')
                    ->with('error', 'Transaction already exists for this order');
            }
            
            // Create a sample transaction
            $transaction = new TiktokFinancialTransaction();
            $transaction->order_id = $order->id;
            $transaction->tanggal_order = $orderDate;
            $transaction->hari_order = $order->hari;
            $transaction->no_order = $order->order_number;
            
            // Generate invoice number
            $transaction->no_invoice = TiktokFinancialTransaction::generateInvoiceNumber($order, 3);
            
            // Calculate total price from order items considering quantity
            $totalPrice = 0;
            foreach ($order->orderItems as $item) {
                $totalPrice += $item->price_after_discount * $item->quantity;
            }
            $transaction->nominal_harga = $totalPrice;
            
            // Sample discount values
            $transaction->nominal_diskon1 = -5000;  // BIAYA ADMIN
            $transaction->nominal_diskon2 = -2000;  // AFFILIATE COMMISSION
            $transaction->nominal_diskon3 = -7000;  // SELLER SHIPPING FEE
            $transaction->nominal_diskon4 = -3000;  // VOUCHER XTRA SERVICE FEE
            $transaction->nominal_diskon5 = -1000;  // CASHBACK FEE
            $transaction->nominal_diskon6 = 0;      // BIAYA 6
            $transaction->nominal_diskon7 = 0;      // BIAYA 7
            $transaction->nominal_diskon8 = 0;      // BIAYA 8
            $transaction->nominal_diskon9 = 0;      // BIAYA 9
            $transaction->nominal_diskon10 = 0;     // BIAYA 10
            $transaction->nominal_diskon11 = 0;     // BIAYA 11
            $transaction->nominal_diskon12 = 0;     // BIAYA 12
            $transaction->adjustment = 0;
            
            // Sample payment info
            $transaction->saldo_masuk = $totalPrice - 18000; // Total minus discounts
            $transaction->tanggal_masuk_pembayaran = Carbon::now();
            $transaction->hari_masuk_pembayaran = Carbon::now()->format('l');
            
            // Calculate nominal_fix and outstanding
            $transaction->calculateNominalFix();
            $transaction->calculateOutstanding();
            
            // Calculate percentages
            if ($transaction->nominal_harga > 0) {
                $transaction->persentase_diskon1 = ($transaction->nominal_diskon1 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon2 = ($transaction->nominal_diskon2 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon3 = ($transaction->nominal_diskon3 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon4 = ($transaction->nominal_diskon4 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon5 = ($transaction->nominal_diskon5 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon6 = ($transaction->nominal_diskon6 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon7 = ($transaction->nominal_diskon7 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon8 = ($transaction->nominal_diskon8 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon9 = ($transaction->nominal_diskon9 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon10 = ($transaction->nominal_diskon10 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon11 = ($transaction->nominal_diskon11 / $transaction->nominal_harga) * 100;
                $transaction->persentase_diskon12 = ($transaction->nominal_diskon12 / $transaction->nominal_harga) * 100;
                
                $transaction->total_persentase = 
                    $transaction->persentase_diskon1 + 
                    $transaction->persentase_diskon2 + 
                    $transaction->persentase_diskon3 + 
                    $transaction->persentase_diskon4 + 
                    $transaction->persentase_diskon5 + 
                    $transaction->persentase_diskon6 +
                    $transaction->persentase_diskon7 + 
                    $transaction->persentase_diskon8 + 
                    $transaction->persentase_diskon9 + 
                    $transaction->persentase_diskon10 + 
                    $transaction->persentase_diskon11 + 
                    $transaction->persentase_diskon12;
            }
            
            $transaction->save();
            
            DB::commit();
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', 'Sample transaction created successfully');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating sample transaction: " . $e->getMessage());
            return redirect()->route('finance.tiktok.index')
                ->with('error', 'Error creating sample transaction: ' . $e->getMessage());
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
            $transaction = TiktokFinancialTransaction::findOrFail($id);
            $transaction->lock(auth()->id());
            
            return redirect()->back()->with('success', 'Transaksi berhasil dikunci.');
        } catch (\Exception $e) {
            Log::error("Error saat mengunci transaksi TikTok: " . $e->getMessage());
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
            $transaction = TiktokFinancialTransaction::findOrFail($id);
            
            // Only admin or the person who locked it can unlock
            if (auth()->user()->role != 'admin' && $transaction->locked_by != auth()->id()) {
                return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk membuka kunci transaksi ini.');
            }
            
            $transaction->unlock();
            
            return redirect()->back()->with('success', 'Kunci transaksi berhasil dibuka.');
        } catch (\Exception $e) {
            Log::error("Error saat membuka kunci transaksi TikTok: " . $e->getMessage());
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
            $transaction = TiktokFinancialTransaction::with('lockedByUser')->findOrFail($id);
            $adjustmentHistories = \App\Models\AdjustmentHistory::where('transaction_id', $transaction->id)
                ->where('platform', 'tiktok')
                ->orderBy('created_at', 'desc')
                ->with('user')
                ->get();
            return view('financial.tiktok.history', compact('transaction', 'adjustmentHistories'));
        } catch (\Exception $e) {
            Log::error("Error saat melihat history transaksi TikTok: " . $e->getMessage());
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
        $filename = 'tiktok_finance_analytics_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new TiktokFinanceAnalyticsExport($request->all()), $filename);
    }

    /**
     * Export cash flow data to Excel (compatible with arus kas import)
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportCashFlow(Request $request)
    {
        $filename = 'tiktok_cash_flow_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new TiktokCashFlowExport($request->all()), $filename);
    }

    /**
     * Synchronize order dates for all TikTok financial transactions
     * This method ensures that tanggal_order matches the order's tanggal
     */
    public function syncOrderDates(Request $request)
    {
        try {
            $updated = TiktokFinancialTransaction::syncAllOrderDates();
            
            return redirect()->route('finance.tiktok.index')
                ->with('success', "Berhasil menyinkronkan {$updated} transaksi dengan tanggal order yang benar.");
                
        } catch (\Exception $e) {
            Log::error("Error synchronizing TikTok order dates: " . $e->getMessage());
            
            return redirect()->route('finance.tiktok.index')
                ->with('error', 'Error menyinkronkan tanggal order: ' . $e->getMessage());
        }
    }

    /**
     * Export data to PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(Request $request)
    {
        $query = TiktokFinancialTransaction::with(['order.orderItems.warehouseStock.tax', 'order.mainCategory']);
        
        // Apply the same filters as in index method
        // Filter by payment date range
        if ($request->filled('from_date')) {
            $query->whereDate('tanggal_masuk_pembayaran', '>=', $request->from_date);
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
        
        // Exclude only fully returned orders (retur full), keep partial returns (retur sebagian)
        $query->where(function($q) {
            // Keep transactions whose order still has remaining qty (partial return) or has no retur
            $q->whereHas('order.orderItems', function($itemQuery) {
                $itemQuery->where('quantity', '>', 0);
            })->orWhereDoesntHave('order.returPenjualan', function($returQuery) {
                $returQuery->whereIn('status', ['draft', 'selesai']);
            });
        });
        
        $transactions = $query->orderBy('tanggal_order', 'desc')->get();
        
        $pdf = Pdf::loadView('exports.financial.tiktok', compact('transactions'))
                  ->setPaper('a4', 'landscape');
        
        return $pdf->download('tiktok_finance_analytics_' . date('Y-m-d_H-i-s') . '.pdf');
    }
} 
