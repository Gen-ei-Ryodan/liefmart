<?php

namespace App\Imports;

use App\Models\BarangKeluar;
use App\Models\MappingBarang;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Platform;
use App\Models\PlatformProduct;
use App\Models\Product;
use App\Models\WarehouseStock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Shopee2Import implements ToCollection, WithMultipleSheets
{
    protected $platform;

    protected $data = [];

    protected $unmappedProducts = [];

    protected $invalidData = [];

    protected $headerIssues = [];

    protected $headerRowIndex = 0;

    protected $columnMapping = [];

    // Counter untuk statistik impor
    protected $totalRows = 0;

    protected $skippedOrders = 0;

    protected $duplicateOrders = 0;
    
    protected $duplicateOrdersInFile = [];
    
    protected $duplicateOrdersInDatabase = [];

    protected $skippedForMissingDate = 0;

    protected $skippedForMissingDay = 0;

    protected $skippedForMissingResi = 0;

    /**
     * Constructor
     * 
     * @param int|null $platformId ID platform (jika null, akan menggunakan platform_id dari session atau default)
     */
    public function __construct($platformId = null)
    {
        // Jika platform_id diberikan, gunakan itu
        if ($platformId !== null) {
            $this->platform = Platform::find($platformId);
        } else {
            // Coba ambil dari session jika ada
            $platformId = session('platform_id');
            if ($platformId) {
                $this->platform = Platform::find($platformId);
            } else {
                // Fallback: cari berdasarkan nama (untuk backward compatibility)
                $this->platform = Platform::where('name', 'shopee2')->first();
            }
        }

        // Jika platform tidak ditemukan, throw exception
        if (! $this->platform) {
            throw new \Exception('Platform tidak ditemukan di database.');
        }
    }

    public function sheets(): array
    {
        return [
            0 => $this, // Gunakan sheet pertama (indeks 0)
        ];
    }

    public function collection(Collection $rows)
    {
        // Set total baris awal
        $this->totalRows = count($rows);

        // Debug: Log semua baris
        \Log::info('Total rows in Excel:', ['count' => $this->totalRows]);
        
        // Log sample of first few rows to help debug format issues
        try {
            $sampleRows = $rows->take(min(5, $this->totalRows))->toArray();
            \Log::info('First 5 rows sample:', ['rows' => json_encode($sampleRows)]);
        } catch (\Exception $e) {
            \Log::error('Error logging sample rows: ' . $e->getMessage());
        }

        // Cari indeks baris header
        try {
            $headerRowIndex = $this->findHeaderRow($rows);
            
            if ($headerRowIndex === false) {
                $this->headerIssues[] = 'Format header tidak ditemukan. Pastikan file memiliki header yang sesuai.';
                \Log::error('Header row not found in Shopee2 Excel file');
                return;
            }

            // Debug: Log header row index
            \Log::info('Header row index:', ['index' => $headerRowIndex]);
            
            $this->headerRowIndex = $headerRowIndex;

            // Ambil header dari baris yang ditemukan
            $headers = $rows[$headerRowIndex];

            // Debug: Log headers
            \Log::info('Headers:', $headers->toArray());
        } catch (\Exception $e) {
            \Log::error('Error finding header row: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            $this->headerIssues[] = 'Error saat mencari header: ' . $e->getMessage();
            return;
        }

        // Buat mapping kolom
        try {
            $this->mapColumns($headers);
        } catch (\Exception $e) {
            \Log::error('Error mapping columns: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            $this->headerIssues[] = 'Error saat mapping kolom: ' . $e->getMessage();
            return;
        }

        // Validasi mapping kolom
        if (! $this->validateColumnMapping()) {
            \Log::error('Column mapping validation failed', ['mapping' => $this->columnMapping]);
            return;
        }

        // Tracking nomor pesanan untuk mencegah duplikasi dalam satu file
        $orderNumbersInFile = [];

        // Proses data dimulai dari baris setelah header
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            try {
                $row = $rows[$i];

                // Debug: Log setiap baris
                if ($i < $headerRowIndex + 6) { // Log only first 5 data rows
                    \Log::info("Row $i:", $row->toArray());
                }

                // Pastikan baris tidak kosong
                if ($this->isEmptyRow($row)) {
                    \Log::info("Empty row skipped: $i");
                    continue;
                }

                // Ambil data sesuai mapping
                $processedRow = $this->processRow($row);

                // Debug: Log processed row
                if ($i < $headerRowIndex + 6) { // Log only first 5 processed rows
                    \Log::info("Processed Row $i:", $processedRow);
                }

                // Validasi data
                $validationErrors = $this->validateRow($processedRow);
                if (! empty($validationErrors)) {
                    $this->invalidData[] = 'Baris #'.($i - $headerRowIndex).': '.implode(', ', $validationErrors);
                    \Log::warning("Validation errors for row $i:", $validationErrors);
                    continue;
                }

                // Skip jika tanggal, no_resi, atau hari tidak ada meskipun header ada
                if ($this->shouldSkipRow($processedRow)) {
                    $this->skippedOrders++;
                    \Log::info("Row skipped due to missing required field: $i");
                    continue;
                }

                // Cek apakah produk dan variasi sudah di-mapping
                $this->checkProductMapping($processedRow['nama_barang'], $processedRow['variasi'] ?? null);

                // Cari platform product ID untuk validasi stok di preview
                $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                    ->where('platform_product_name', $processedRow['nama_barang'])
                    ->where('variant', $processedRow['variasi'] ?? '')
                    ->first();
                
                if ($platformProduct) {
                    $processedRow['platform_product_id'] = $platformProduct->id;
                }

                // Logic duplikat baru:
                // 1. Jika dalam 1 Excel no order sama bisa diproses jika barang berbeda
                // 2. Jika berbeda Excel no order sama langsung ditolak
                $orderNumber = $processedRow['no_order'];
                $productName = $processedRow['nama_barang'];
                $variation = $processedRow['variasi'] ?? '';
                $fullProductName = !empty($variation) ? "$productName - $variation" : $productName;
                
                // Cek duplikat dalam file (no order + produk sama)
                $duplicateKey = $orderNumber . '|' . $fullProductName;
                if (isset($orderNumbersInFile[$duplicateKey])) {
                    // Duplikat dalam file - skip
                    $this->duplicateOrders++;
                    $this->duplicateOrdersInFile[] = [
                        'order_number' => $orderNumber,
                        'product_name' => $fullProductName,
                        'row' => $i
                    ];
                    \Log::info("Duplicate order+product in file: {$orderNumber} - {$fullProductName}");
                    continue; // Skip duplikat dalam file
                }
                
                // Cek duplikat di database (no order sama)
                $existingOrder = \App\Models\Order::where('order_number', $orderNumber)->first();
                if ($existingOrder) {
                    // Duplikat di database - skip tapi tetap catat
                    $this->duplicateOrdersInDatabase[] = [
                        'order_number' => $orderNumber,
                        'product_name' => $fullProductName,
                        'row' => $i
                    ];
                    \Log::info("Duplicate order in database: {$orderNumber} - will be skipped");
                    continue; // Skip duplikat di database
                }
                
                // Tandai order+produk ini sudah diproses
                $orderNumbersInFile[$duplicateKey] = 1;
                
                // Tambahkan ke data yang valid
                $this->data[] = $processedRow;
            } catch (\Exception $e) {
                \Log::error("Error processing row $i: " . $e->getMessage());
                \Log::error($e->getTraceAsString());
                $this->invalidData[] = 'Baris #'.($i - $headerRowIndex).': Error: ' . $e->getMessage();
            }
        }

        // Debug: Log final data dan unmapped products
        \Log::info('Final Data count:', ['count' => count($this->data)]);
        \Log::info('Unmapped Products count:', ['count' => count($this->unmappedProducts)]);
    }

    /**
     * Memeriksa apakah baris harus dilewati karena field yang diperlukan tidak ada
     *
     * @param  array  $row
     * @return bool
     */
    protected function shouldSkipRow($row)
    {
        // Periksa jika header tanggal, no_resi, atau hari ada tetapi datanya kosong
        $shouldSkip = false;

        // For tanggal, numeric values (Excel dates) are valid and should NOT be skipped
        if ($this->columnMapping['tanggal'] !== null && 
            empty($row['tanggal']) && !is_numeric($row['tanggal'])) {
            $this->skippedForMissingDate++;
            $shouldSkip = true;
            \Log::info("Skipping row due to missing date: " . json_encode($row));
        }

        if ($this->columnMapping['no_resi'] !== null && empty($row['no_resi'])) {
            $this->skippedForMissingResi++;
            $shouldSkip = true;
        }

        // We'll calculate the day from the date if missing, so don't skip for missing day
        if ($this->columnMapping['hari'] !== null && empty($row['hari']) && empty($row['tanggal']) && !is_numeric($row['tanggal'])) {
            $this->skippedForMissingDay++;
            $shouldSkip = true;
        }

        return $shouldSkip;
    }

    /**
     * Cari baris header dalam data
     *
     * @return int|bool
     */
    protected function findHeaderRow(Collection $rows)
    {
        \Log::info('Starting to search for header row in ' . count($rows) . ' rows');
        
        // Log the first few rows for debugging
        \Log::info('===== DEBUGGING EXCEL CONTENT =====');
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            try {
                $rowContent = $rows[$i]->toArray();
                \Log::info("Row $i content: " . json_encode($rowContent, JSON_UNESCAPED_UNICODE));
            } catch (\Exception $e) {
                \Log::error("Error logging row $i: " . $e->getMessage());
            }
        }
        \Log::info('===== END DEBUGGING EXCEL CONTENT =====');
        
        // Safety check for empty dataset
        if ($rows->isEmpty()) {
            \Log::error("Excel file is empty or contains no data");
            $this->headerIssues[] = 'File Excel kosong atau tidak berisi data.';
            return false;
        }
        
        foreach ($rows as $index => $row) {
            // Log header row search for first 10 rows
            if ($index < 10) {
                try {
                    \Log::info("Checking row $index as possible header:", $row->toArray());
                } catch (\Exception $e) {
                    \Log::error("Error logging row $index: " . $e->getMessage());
                }
            }
            
            // Periksa apakah baris mengandung header yang diharapkan
            if ($this->isHeaderRow($row)) {
                \Log::info("Header row found at index $index");
                return $index;
            }
        }

        \Log::error("No header row found in the file");
        $this->headerIssues[] = 'Format header tidak ditemukan. Pastikan file memiliki header yang sesuai dengan format Shopee2.';
        return false;
    }

    /**
     * Periksa apakah baris adalah baris header
     *
     * @param  Collection  $row
     * @return bool
     */
    protected function isHeaderRow($row)
    {
        // Daftar header yang diharapkan - hanya header yang spesifik ini yang diperbolehkan
        // PENTING: Untuk variant, terima 'VARIASI', 'VARIANT', atau 'VARIAN' (case-insensitive)
        $expectedHeaders = [
            'NOMOR PESANAN',
            'NOMOR RESI',
            'HARI',
            'STATUS HARI',
            'TANGGAL',
            'NAMA PRODUK',
            'VARIASI', // Juga menerima 'VARIANT' dan 'VARIAN' (case-insensitive)
            'QTY',
            'HARGA SETELAH DISKON'
        ];
        
        // Variant header options untuk matching
        $variantHeaderOptions = ['VARIASI', 'VARIANT', 'VARIAN'];

        // Log for debugging
        \Log::info("Checking if row is a header row");
        try {
            // Check if row contains values or is empty
            if (count(array_filter($row->toArray())) === 0) {
                \Log::info("Row is empty, skipping header check");
                return false;
            }
            
            \Log::info("Row content:", $row->toArray());
        } catch (\Exception $e) {
            \Log::error("Error logging row content: " . $e->getMessage());
        }

        $foundColumns = 0;
        $matchedColumns = [];
        $unmatchedColumns = [];
        
        // Log each cell in the row to help debug
        \Log::info('===== DETAILED HEADER CELL CHECK =====');
        foreach ($row as $cellIndex => $cell) {
            $cellType = gettype($cell);
            $cellValue = is_string($cell) ? $cell : (is_numeric($cell) ? "numeric: $cell" : "non-string type: " . $cellType);
            \Log::info("Cell $cellIndex: Type = $cellType, Value = $cellValue");
        }
        \Log::info('===== END DETAILED HEADER CELL CHECK =====');
        
        // Cek kecocokan header dengan nilai yang diharapkan
        foreach ($expectedHeaders as $header) {
            $headerMatched = false;
            
            // Untuk header VARIASI, cek juga VARIANT dan VARIAN
            $headerOptions = ($header === 'VARIASI') ? $variantHeaderOptions : [$header];
            
            foreach ($row as $cellIndex => $cell) {
                if (!is_string($cell)) {
                    continue;
                }
                
                $normalizedCell = trim(strtoupper($cell));
                
                // Cek setiap opsi header (case-insensitive)
                foreach ($headerOptions as $headerOption) {
                    $normalizedHeader = trim(strtoupper($headerOption));
                    
                    // Hanya cocokkan jika sama persis
                    if ($normalizedCell === $normalizedHeader) {
                        $foundColumns++;
                        $matchedColumns[] = $header . " (matched with: $cell)";
                        $headerMatched = true;
                        \Log::info("Match found for header: $header (matched '$headerOption') at cell index $cellIndex");
                        break 2; // Break dari kedua loop
                    }
                }
            }
            
            if (!$headerMatched) {
                $unmatchedColumns[] = $header;
            }
        }

        // Logging untuk debug
        \Log::info("Found columns: $foundColumns out of " . count($expectedHeaders));
        \Log::info("Matched columns: " . implode(", ", $matchedColumns));
        if (!empty($unmatchedColumns)) {
            \Log::info("Unmatched columns: " . implode(", ", $unmatchedColumns));
        }

        // Perbolehkan header ditemukan jika minimal 6 kolom ditemukan
        // Ini lebih ketat dari sebelumnya, perlu minimal 6 dari 9 kolom yang diharapkan
        $isHeader = $foundColumns >= 6;
        \Log::info($isHeader ? "This IS a header row ($foundColumns columns matched)" : "This is NOT a header row (only $foundColumns columns matched)");
        
        return $isHeader;
    }

    /**
     * Mapping kolom dari header
     *
     * @param  Collection  $headers
     * @return void
     */
    protected function mapColumns($headers)
    {
        \Log::info('Original Headers:', $headers->toArray());

        // Hanya gunakan header yang spesifik sesuai permintaan
        // PENTING: Untuk variasi, cari 'VARIASI', 'VARIANT', atau 'VARIAN' (case-insensitive)
        $exactHeaderMapping = [
            'no_order' => 'NOMOR PESANAN',
            'no_resi' => 'NOMOR RESI',
            'hari' => 'HARI',
            'status_hari' => 'STATUS HARI',
            'tanggal' => 'TANGGAL',
            'nama_barang' => 'NAMA PRODUK',
            'variasi' => ['VARIASI', 'VARIANT', 'VARIAN'], // Menerima VARIASI, VARIANT, atau VARIAN (case-insensitive)
            'qty' => 'QTY',
            'harga_setelah_diskon' => 'HARGA SETELAH DISKON'
        ];

        // Inisialisasi column mapping
        $this->columnMapping = [
            'no_order' => null,
            'tanggal' => null,
            'nama_barang' => null,
            'variasi' => null,
            'qty' => null,
            'harga_setelah_diskon' => null,
            'no_resi' => null,
            'hari' => null,
            'status_hari' => null,
        ];
        
        // Log all headers and their values
        \Log::info("Header values:");
        foreach ($headers as $index => $header) {
            $headerType = gettype($header);
            $headerValue = is_string($header) ? $header : ($headerType . ': ' . json_encode($header));
            \Log::info("Header index $index: $headerValue");
        }

        // Cari indeks kolom berdasarkan nama header yang tepat
        foreach ($exactHeaderMapping as $field => $exactHeader) {
            $columnIndex = null;
            
            // Handle array untuk field yang memiliki multiple header options (seperti variasi)
            $headerOptions = is_array($exactHeader) ? $exactHeader : [$exactHeader];
            
            foreach ($headers as $index => $header) {
                if (!is_string($header)) {
                    continue;
                }
                
                // Cek setiap opsi header (case-insensitive)
                foreach ($headerOptions as $headerOption) {
                    // Hanya cocokkan jika header sama persis (case-insensitive)
                    if (strtoupper(trim($header)) === strtoupper(trim($headerOption))) {
                        $columnIndex = $index;
                        \Log::info("Exact match found for '$headerOption' (field: $field) at index $index");
                        break 2; // Break dari kedua loop
                    }
                }
            }
            
            $this->columnMapping[$field] = $columnIndex;
            \Log::info("Field '$field' mapping result: " . ($columnIndex !== null ? $columnIndex : 'Not found'));
        }

        \Log::info('Final Column Mapping:', $this->columnMapping);
    }

    /**
     * Validasi mapping kolom yang dibutuhkan
     *
     * @return bool
     */
    protected function validateColumnMapping()
    {
        // Semua kolom wajib ada kecuali variasi dan status_hari yang opsional
        $requiredColumns = [
            'no_order', 
            'nama_barang', 
            'qty', 
            'harga_setelah_diskon',
            'tanggal',
            'hari',
            'no_resi'
        ];
        
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if ($this->columnMapping[$column] === null) {
                $missingColumns[] = str_replace('_', ' ', strtoupper($column));
            }
        }

        if (!empty($missingColumns)) {
            $this->headerIssues[] = 'Kolom yang dibutuhkan tidak ditemukan: '.implode(', ', $missingColumns);
            \Log::error('Required columns not found: ' . implode(', ', $missingColumns));
            return false;
        }

        \Log::info('Column mapping validation passed - all required columns found');
        return true;
    }

    /**
     * Proses baris data sesuai mapping kolom
     *
     * @param  Collection  $row
     * @return array
     */
    protected function processRow($row)
    {
        $result = [];

        foreach ($this->columnMapping as $key => $index) {
            // Skip if the index is null
            if ($index === null) {
                $result[$key] = null;
                continue;
            }

            // Get value directly from the mapped column
            $value = isset($row[$index]) ? $row[$index] : null;
            
            // Basic value cleaning based on field type
            if ($key === 'tanggal') {
                // Handle Excel serial date, DateTime, or string
                if (is_numeric($value)) {
                    try {
                        // Excel serial date
                        $carbonDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                        $value = $carbonDate instanceof \DateTime ? $carbonDate->format('Y-m-d') : null;
                        \Log::info("Numeric date value $value converted from Excel serial date: $value");
                    } catch (\Exception $e) {
                        \Log::error("Error converting Excel date: " . $e->getMessage() . " for value: $value");
                        $value = null;
                    }
                } elseif ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d');
                    \Log::info("DateTime object converted to: $value");
                } elseif (is_string($value) && !empty($value)) {
                    // If date has time component, extract just the date part
                    $originalValue = $value;
                    if (strpos($value, ' ') !== false) {
                        $parts = explode(' ', $value);
                        $value = $parts[0];
                    }
                    
                    // Try to parse string date
                    try {
                        $carbonDate = \Carbon\Carbon::parse($value);
                        $value = $carbonDate->format('Y-m-d');
                        \Log::info("String date '$originalValue' parsed to: $value");
                    } catch (\Exception $e) {
                        \Log::error("Error parsing date string: " . $e->getMessage() . " for value: $originalValue");
                        // If parsing fails, try different formats
                        $parsedDate = $this->tryParseDateWithFormats($originalValue);
                        if ($parsedDate) {
                            $value = $parsedDate;
                            \Log::info("Date string '$originalValue' parsed with alternative format to: $value");
                        } else {
                            $value = null;
                        }
                    }
                }
            } 
            else if ($key === 'harga_setelah_diskon') {
                // Clean up price value if it's a string
                if (is_string($value)) {
                    $originalValue = $value;
                    $cleanPrice = preg_replace('/[^\d.]/', '', $value);
                    if (!empty($cleanPrice)) {
                        $value = $cleanPrice;
                        \Log::info("Price value cleaned from '$originalValue' to '$value'");
                    }
                }
            }
            // PENTING: For nama_barang and variasi, preserve exactly as in Excel (no processing)
            // VARIANT HARUS diambil HANYA dari kolom variant Excel, BUKAN dari parsing nama produk
            // Meskipun di nama produk ada simbol seperti "-", "+", dll, JANGAN parsing untuk mendapatkan variant
            // Variant hanya dari kolom variant Excel saja
            else if ($key === 'nama_barang' || $key === 'variasi') {
                // Return exactly as in Excel, preserve all characters including +, -, spaces, etc.
                // NO PARSING from product name - variant comes ONLY from variant column
                $result[$key] = $value;
                continue;
            }
            else if ($key === 'qty') {
                // Clean up quantity value if it's a string
                if (is_string($value)) {
                    $originalValue = $value;
                    $cleanQty = preg_replace('/[^\d.]/', '', $value);
                    if (!empty($cleanQty)) {
                        $value = $cleanQty;
                        \Log::info("Quantity value cleaned from '$originalValue' to '$value'");
                    }
                }
                // Ensure quantity is numeric
                if (is_numeric($value)) {
                    $value = (float) $value;
                }
            }
            else if (in_array($key, ['no_order', 'no_resi']) && is_string($value)) {
                // Trim and clean string values (variasi already handled above, no trim)
                $value = trim($value);
            }
            else if ($key === 'status_hari' && is_string($value)) {
                // Trim and clean status_hari value, support multiple values separated by comma
                $value = trim($value);
                // If value contains comma, it's already in the correct format
                // If not, it's a single value
                if (!empty($value) && strpos($value, ',') === false) {
                    // Single value, keep as is
                    $value = $value;
                }
                // If it contains comma, it's already in the correct format for multiple values
            }
            
            $result[$key] = $value;
            
            // Log specific values for better debugging
            if ($key === 'tanggal') {
                \Log::info("Extracted date value: " . var_export($value, true) . 
                           " (type: " . gettype($value) . ") from index: " . $index);
            }
            if ($key === 'qty') {
                \Log::info("Extracted quantity value: " . var_export($value, true) . 
                           " (type: " . gettype($value) . ") from index: " . $index);
            }
        }
        
        // Log all extracted values for debugging
        \Log::info("Processed row data: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }
    
    /**
     * Try to parse date string with various formats
     * 
     * @param string $dateString
     * @return string|null Formatted Y-m-d date or null if parsing fails
     */
    protected function tryParseDateWithFormats($dateString) 
    {
        // Common date formats to try
        $formats = [
            'd/m/Y', // 31/01/2023
            'm/d/Y', // 01/31/2023
            'Y-m-d', // 2023-01-31
            'd-m-Y', // 31-01-2023
            'Y/m/d', // 2023/01/31
            'd.m.Y', // 31.01.2023
            'Y.m.d', // 2023.01.31
            'j F Y', // 31 January 2023
            'F j, Y', // January 31, 2023
            'j-M-Y', // 31-Jan-2023
            'j/n/Y', // 31/1/2023
            'n/j/Y', // 1/31/2023
            'j-n-Y', // 31-1-2023
            'Y-n-j'  // 2023-1-31
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                \Log::info("Successfully parsed date '$dateString' using format '$format'");
                return $date->format('Y-m-d');
            }
        }
        
        \Log::warning("Failed to parse date string: '$dateString' with any format");
        return null;
    }
    
    /**
     * Validasi baris data
     *
     * @param  array  $row
     * @return array
     */
    protected function validateRow($row)
    {
        $errors = [];

        if (empty($row['no_order'])) {
            $errors[] = 'Nomor Order tidak boleh kosong';
        }
        if (empty($row['nama_barang'])) {
            $errors[] = 'Nama Barang tidak boleh kosong';
        }
        
        // Qty validation with more detailed error messages
        if (empty($row['qty'])) {
            $errors[] = 'Quantity tidak boleh kosong';
        } else if (!is_numeric($row['qty'])) {
            $errors[] = 'Quantity harus berupa angka (ditemukan: ' . gettype($row['qty']) . ' - ' . $row['qty'] . ')';
        } else if ($row['qty'] <= 0) {
            $errors[] = 'Quantity harus lebih dari 0 (ditemukan: ' . $row['qty'] . ')';
        } else if (floor($row['qty']) != $row['qty']) {
            $errors[] = 'Quantity tidak boleh berupa angka desimal (ditemukan: ' . $row['qty'] . '). Harap perbaiki file Excel Anda.';
        }
        
        // Price validation with more detailed error messages
        if (empty($row['harga_setelah_diskon'])) {
            $errors[] = 'Harga tidak boleh kosong';
        } else if (!is_numeric($row['harga_setelah_diskon'])) {
            $errors[] = 'Harga harus berupa angka (ditemukan: ' . gettype($row['harga_setelah_diskon']) . ' - ' . $row['harga_setelah_diskon'] . ')';
        } else if ($row['harga_setelah_diskon'] < 0) {
            $errors[] = 'Harga tidak boleh negatif (ditemukan: ' . $row['harga_setelah_diskon'] . ')';
        }

        // Date validation
        if (isset($row['tanggal']) && $row['tanggal'] === null && $this->columnMapping['tanggal'] !== null) {
            $errors[] = 'Format Tanggal tidak valid';
        }

        if (! empty($errors)) {
            \Log::info('Validation Errors:', $errors);
            $this->invalidData[] = 'Baris: '.json_encode($row).' -> '.implode(', ', $errors);
        }

        return $errors;
    }

    /**
     * Periksa apakah baris kosong
     *
     * @param  Collection  $row
     * @return bool
     */
    protected function isEmptyRow($row)
    {
        foreach ($row as $cell) {
            if (! empty($cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Proses import data ke database
     *
     * @return array
     */
    public function processImport()
    {
        $results = [
            'success' => 0,
            'errors' => [],
            'duplicates' => 0,
            'skipped' => 0,
            'unmapped_skipped' => 0,
            'failed_orders' => [],
        ];

        // Jika ada data yang tidak valid, return error
        if (! empty($this->invalidData)) {
            return [
                'success' => 0,
                'errors' => [
                    'invalidData' => $this->invalidData,
                ],
            ];
        }

        // Kelompokkan data berdasarkan no_order untuk menangani beberapa item dalam 1 order
        $groupedData = [];
        foreach ($this->data as $row) {
            $groupedData[$row['no_order']][] = $row;
        }

        \Log::info("Starting database import with " . count($groupedData) . " orders");

        // Gunakan database transaction untuk memastikan semua operasi sukses atau tidak sama sekali
        DB::beginTransaction();

        try {
            // Check for tax_id consolidation setting
            $consolidateByTaxId = \App\Models\WarehouseStock::$consolidateOrderItemsByProduct ?? false;
            \Log::info("Tax ID consolidation is " . ($consolidateByTaxId ? "ENABLED" : "DISABLED") . " for this import");
            
            // Tracking untuk order dengan produk unmapped
            $ordersWithUnmappedProducts = [];
            
            // Proses import data ke database
            foreach ($groupedData as $orderNumber => $orderItems) {
                try {
                    \Log::info("Processing order: $orderNumber with " . count($orderItems) . " items");
                    
                    // Cek apakah order ini memiliki produk yang belum dimapping
                    $hasUnmappedProducts = false;
                    foreach ($orderItems as $item) {
                        $productName = $item['nama_barang'];
                        $variation = $item['variasi'] ?? null;
                        $fullProductName = !empty($variation) ? "$productName - $variation" : $productName;
                        
                        if (in_array($fullProductName, $this->unmappedProducts)) {
                            $hasUnmappedProducts = true;
                            \Log::info("Order $orderNumber has unmapped product: $fullProductName");
                            break;
                        }
                    }
                    
                    // Jika order ini memiliki produk yang belum dimapping, skip
                    if ($hasUnmappedProducts) {
                        $ordersWithUnmappedProducts[] = $orderNumber;
                        $results['unmapped_skipped']++;
                        \Log::info("Skipping order $orderNumber due to unmapped products");
                        continue;
                    }
                    
                    // Ambil item pertama untuk informasi order
                    $firstItem = $orderItems[0];

                    // Parse tanggal
                    $tanggal = null;
                    if (! empty($firstItem['tanggal'])) {
                        try {
                            // Log the original date value for debugging
                            \Log::info("Original date value: " . $firstItem['tanggal']);
                            
                            // Check if the date is a numeric timestamp or Excel serial date
                            if (is_numeric($firstItem['tanggal'])) {
                                // Convert Excel serial date to PHP DateTime
                                // Excel dates start from 1900-01-01 (serial number 1)
                                try {
                                    // These are definitely Excel serial dates (common range for recent dates)
                                    if ($firstItem['tanggal'] >= 1 && $firstItem['tanggal'] <= 2958465) { // Range covers Excel dates through year 9999
                                        // Use PhpSpreadsheet's Date utility for proper conversion
                                        $excelBaseDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$firstItem['tanggal']);
                                        $tanggal = $excelBaseDate->format('Y-m-d');
                                        \Log::info("Converted Excel serial date: " . $firstItem['tanggal'] . " to " . $tanggal);
                                    } else {
                                        // Likely a Unix timestamp
                                        $tanggal = Carbon::createFromTimestamp($firstItem['tanggal'])->format('Y-m-d');
                                        \Log::info("Converted timestamp: " . $firstItem['tanggal'] . " to " . $tanggal);
                                    }
                                } catch (\Exception $e) {
                                    \Log::error("Failed to convert numeric date: " . $firstItem['tanggal'] . " - Error: " . $e->getMessage());
                                    throw new \Exception("Cannot convert numeric date: " . $firstItem['tanggal']);
                                }
                            } else if ($firstItem['tanggal'] instanceof \DateTime) {
                                // If it's already a DateTime object
                                $tanggal = Carbon::instance($firstItem['tanggal'])->format('Y-m-d');
                                \Log::info("Converted DateTime object: " . $tanggal);
                            } else {
                                // Try to parse various date formats
                                $dateValue = trim((string)$firstItem['tanggal']);
                                
                                // Try parsing with our helper method first
                                $tanggal = $this->tryParseDateWithFormats($dateValue);
                                
                                // If that fails, fall back to Carbon's parse
                                if (!$tanggal) {
                                    try {
                                        $tanggal = Carbon::parse($dateValue)->format('Y-m-d');
                                        \Log::info("Parsed date using Carbon: " . $tanggal);
                                    } catch (\Exception $innerEx) {
                                        \Log::error("Carbon failed to parse date: " . $innerEx->getMessage());
                                        throw new \Exception("Cannot parse date: " . $dateValue);
                                    }
                                }
                            }
                            
                            // Validate the resulting date
                            if (!$tanggal || $tanggal === '1970-01-01') {
                                \Log::warning("Invalid date detected: " . $firstItem['tanggal'] . " - parsed as: " . $tanggal);
                                throw new \Exception("Invalid date format: " . $firstItem['tanggal']);
                            }
                        } catch (\Exception $e) {
                            // Log the error in detail
                            \Log::error("Date parsing error: " . $e->getMessage() . " for date: " . $firstItem['tanggal']);
                            // Jika format tanggal tidak valid, lewati order ini
                            $results['skipped']++;
                            continue;
                        }
                    } else {
                        // Jika tanggal kosong, skip order ini
                        $results['skipped']++;
                        \Log::warning("Skipping order $orderNumber due to empty date");
                        continue;
                    }

                    // Tentukan hari dari tanggal jika hari tidak tersedia
                    $hari = ! empty($firstItem['hari']) ? $firstItem['hari'] : null;
                    
                    // If the date was parsed successfully but we don't have the day, calculate it
                    if ($tanggal && empty($hari)) {
                        $hari = $this->getDayOfWeek($tanggal);
                        \Log::info("Calculated day of week for $tanggal: $hari");
                    }

                    // Get status hari from the data
                    $statusHari = ! empty($firstItem['status_hari']) ? $firstItem['status_hari'] : null;

                    // Cek apakah order sudah ada di database
                    $existingOrder = Order::where('platform_id', $this->platform->id)
                        ->where('order_number', $orderNumber)
                        ->first();

                    if ($existingOrder) {
                        // Order sudah ada, increment counter dan skip
                        $results['duplicates']++;
                        \Log::info("Duplicate order skipped: $orderNumber");

                        continue;
                    }

                    // Buat order baru
                    $order = new Order([
                        'platform_id' => $this->platform->id,
                        'order_number' => $orderNumber,
                        'tanggal' => $tanggal,
                        'hari' => $hari,
                        'status_hari' => $statusHari,
                        'status' => 'imported',
                    ]);
                    $order->save();
                    \Log::info("Created new order with ID: {$order->id}");

                    // Proses setiap item dalam order
                    foreach ($orderItems as $item) {
                        // PENTING: Variant diambil HANYA dari kolom variant Excel ($item['variasi'])
                        // BUKAN dari parsing nama produk, meskipun di nama produk ada simbol seperti "-", "+", dll
                        $productName = $item['nama_barang'];
                        $variation = $item['variasi'] ?? null; // Variant HANYA dari kolom variant Excel
                        $fullProductName = !empty($variation) ? "$productName - $variation" : $productName;
                        
                        \Log::info("Processing item: $fullProductName, qty: {$item['qty']}");
                        
                        // Ambil platform_product dengan pencarian yang lebih fleksibel
                        $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                            ->where(function ($query) use ($productName, $variation, $fullProductName) {
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

                        // Jika tidak ditemukan dengan pencarian exact, coba dengan pencarian yang lebih fleksibel
                        // TETAPI hanya jika tidak ada variant yang spesifik
                        if (!$platformProduct) {
                            if (!empty($variation)) {
                                // Jika ada variant spesifik, jangan gunakan flexible search
                                // Biarkan platformProduct tetap null agar bisa ditangani sebagai unmapped product
                                \Log::warning("Exact match not found for product: $productName, variant: $variation. Skipping flexible search for variant-specific products.");
                            } else {
                                // Hanya gunakan flexible search jika tidak ada variant
                                \Log::warning("Exact match not found for product: $productName (no variant), trying flexible search");
                                
                                $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                                    ->where(function ($query) use ($productName, $fullProductName) {
                                        // Coba cari dengan nama lengkap
                                        $query->where('platform_product_name', $fullProductName)
                                            ->orWhere('platform_product_name', 'LIKE', '%'.$fullProductName.'%')
                                            // Juga coba cari dengan nama produk saja
                                            ->orWhere('platform_product_name', $productName)
                                            ->orWhere('platform_product_name', 'LIKE', '%'.$productName.'%')
                                            ->orWhere(DB::raw('LOWER(platform_product_name)'), 'LIKE', '%'.strtolower($productName).'%');
                                    })
                                    ->first();
                            }
                        }

                        if (! $platformProduct) {
                            throw new \Exception("Produk '$fullProductName' tidak ditemukan di database.");
                        }
                        
                        \Log::info("Found platform product ID: {$platformProduct->id}");

                        // Buat order item
                        $orderItem = new OrderItem([
                            'order_id' => $order->id,
                            'platform_product_id' => $platformProduct->id,
                            'quantity' => $item['qty'],
                            'price_after_discount' => $item['harga_setelah_diskon'],
                            'tracking_number' => $item['no_resi'] ?? null,
                        ]);
                        $orderItem->save();
                        \Log::info("Created order item with ID: {$orderItem->id}");

                        // Kurangi stok sesuai mapping dan catat barang keluar
                        $this->reduceStock($platformProduct, $item['qty'], $orderItem);
                    }

                    $results['success']++;
                    \Log::info("Successfully processed order: $orderNumber");
                } catch (\Exception $e) {
                    // Rollback order yang gagal ini saja
                    DB::rollBack();
                    
                    $errorMessage = "Error memproses order $orderNumber: " . $e->getMessage();
                    $results['errors'][] = $errorMessage;
                    $results['failed_orders'][] = [
                        'order_number' => $orderNumber,
                        'error' => $e->getMessage()
                    ];
                    \Log::error("Import error for order $orderNumber: " . $e->getMessage());
                    
                    // Mulai transaksi baru untuk order berikutnya
                    DB::beginTransaction();
                    
                    // Jangan throw exception, lanjutkan ke order berikutnya
                    // Kecuali jika semua order gagal, akan ditangani di akhir
                    continue;
                }
            }
            
            // Tambahkan informasi orders dengan produk unmapped ke hasil
            if (!empty($ordersWithUnmappedProducts)) {
                $results['orders_with_unmapped_products'] = $ordersWithUnmappedProducts;
                \Log::info(count($ordersWithUnmappedProducts) . " orders skipped due to unmapped products");
            }

            // Commit transaksi jika ada minimal 1 order yang berhasil
            if ($results['success'] > 0) {
                DB::commit();
                \Log::info("Transaction committed successfully. {$results['success']} orders imported.");
            } else {
                // Jika semua order gagal, rollback
                DB::rollBack();
                \Log::error('All orders failed. Transaction rolled back.');
            }
        } catch (\Exception $e) {
            // Jika ada error kritis yang tidak terduga, rollback semua perubahan
            DB::rollBack();
            $results['errors'][] = 'Error dalam transaksi: ' . $e->getMessage();
            \Log::error('Transaction error in processImport: ' . $e->getMessage());
        }

        // Tambahkan informasi tentang order yang dilewati
        $results['skipped'] += $this->skippedOrders;
        $results['duplicates'] += $this->duplicateOrders;
        
        \Log::info("Import process completed with results: " . json_encode($results));

        return $results;
    }

    /**
     * Memeriksa apakah stok mencukupi tanpa menguranginya
     *
     * @param  PlatformProduct  $platformProduct
     * @param  int  $quantity
     * @return array
     */
    protected function checkStock($platformProduct, $quantity)
    {
        $mappings = MappingBarang::where('platform_product_id', $platformProduct->id)
            ->where('is_active', true)
            ->get();

        foreach ($mappings as $mapping) {
            $qtyToReduce = $quantity * $mapping->quantity;

            $remainingQty = $qtyToReduce;
            $stocks = WarehouseStock::where('product_id', $mapping->product_id)
                ->where('qty', '>', 0)
                ->orderBy('warehouse_stock.created_at')
                ->orderBy('warehouse_stock.tax_id', 'asc')
                ->get(['warehouse_stock.qty']);

            foreach ($stocks as $stock) {
                if ($remainingQty <= 0) {
                    break;
                }

                $qtyToTake = min($remainingQty, $stock->qty);

                $remainingQty -= $qtyToTake;
            }

            if ($remainingQty > 0) {
                $product = Product::find($mapping->product_id);
                $productName = $product ? $product->name : 'Unknown Product';
                $effectiveAvailableStock = $qtyToReduce - $remainingQty;

                \Log::error("CheckStock - Insufficient stock: {$productName}, Required: {$qtyToReduce}, Effective Available: {$effectiveAvailableStock}, Remaining: {$remainingQty}");

                return [
                    'success' => false,
                    'product_id' => $mapping->product_id,
                    'product_name' => $productName,
                    'required' => $qtyToReduce,
                    'available' => $effectiveAvailableStock,
                    'shortage' => $remainingQty,
                ];
            }
        }

        return ['success' => true];
    }

    /**
     * Catat barang keluar
     *
     * @param  OrderItem  $orderItem
     * @param  WarehouseStock  $stock
     * @param  float  $quantity
     * @return void
     */
    protected function recordBarangKeluar($orderItem, $stock, $quantity)
    {
        try {
            $kodeBarangKeluar = BarangKeluar::generateKode();

            BarangKeluar::create([
                'kode_barang_keluar' => $kodeBarangKeluar,
                'order_item_id' => $orderItem->id,
                'warehouse_stock_id' => $stock->id,
                'qty' => $quantity,
                'tanggal_keluar' => $orderItem->order->tanggal,
                'catatan' => "Penjualan online Shopee2 - Order #{$orderItem->order->order_number}",
            ]);

            \Log::info("Recorded BarangKeluar: $kodeBarangKeluar for OrderItem ID: {$orderItem->id}, Stock ID: {$stock->id}, Qty: $quantity");
        } catch (\Exception $e) {
            \Log::error('Error recording BarangKeluar: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Kurangi stok produk berdasarkan mapping dan catat sebagai barang keluar
     *
     * @param  PlatformProduct  $platformProduct
     * @param  int  $quantity
     * @param  OrderItem  $orderItem
     * @return void
     */
    protected function reduceStock($platformProduct, $quantity, $orderItem)
    {
        try {
            // Ambil semua mapping barang AKTIF untuk platform product ini
            $mappings = MappingBarang::where('platform_product_id', $platformProduct->id)
                ->where('is_active', true)
                ->get();
            \Log::info("Reducing stock for platform product: {$platformProduct->platform_product_name}, Quantity: {$quantity}");
            \Log::info('Found mappings: '.$mappings->count());

            foreach ($mappings as $mapping) {
                // Hitung jumlah yang perlu dikurangi dari stok
                $qtyToReduce = $quantity * $mapping->quantity;
                \Log::info("Processing mapping for product ID: {$mapping->product_id}");
                \Log::info("Order quantity: {$quantity} (type: " . gettype($quantity) . ")");
                \Log::info("Mapping quantity: {$mapping->quantity} (type: " . gettype($mapping->quantity) . ")");
                \Log::info("Calculated qty to reduce: {$qtyToReduce} (type: " . gettype($qtyToReduce) . ")");

                // Ambil stok produk dari warehouse berdasarkan FIFO + prioritas HGN
                // PERBAIKAN: lockForUpdate() mencegah race condition saat 2 import berjalan bersamaan
                $stocks = WarehouseStock::where('product_id', $mapping->product_id)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at') // Layer 1: FIFO berdasarkan tanggal penerimaan
                    ->orderBy('tax_id', 'asc') // Layer 2: HGN (tax_id=3) dulu, baru LM (tax_id=4)
                    ->lockForUpdate()
                    ->get();
                \Log::info('Found stock records: '.$stocks->count());

                $remainingQty = $qtyToReduce;
                $isFirstStock = true;  // Flag untuk menandai stok pertama

                // FIFO processing - tax_id hanya untuk prioritas sorting (HGN dulu), bukan untuk operasi pembagian
                // Tax_id adalah label dari masa pembelian, tidak boleh dibagi
                foreach ($stocks as $stock) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    // Hitung quantity yang akan dikurangi dari stok ini
                    // Pastikan qty minimal 1 (tidak ada desimal seperti 0.5)
                    $qtyToTake = min($remainingQty, $stock->qty);
                    
                    \Log::info("Reducing from stock ID: {$stock->id}, Available: {$stock->qty}, Taking: {$qtyToTake}");

                    // Set warehouse_stock_id pada order item jika ini adalah stok pertama yang digunakan
                    if ($isFirstStock) {
                        $orderItem->warehouse_stock_id = $stock->id;
                        $orderItem->save();
                        $isFirstStock = false;
                        \Log::info("Set warehouse_stock_id: {$stock->id} for OrderItem ID: {$orderItem->id}");
                    }

                    // Catat barang keluar sebelum kurangi stok
                    $this->recordBarangKeluar($orderItem, $stock, $qtyToTake);

                    // Kurangi stok
                    $stock->qty -= $qtyToTake;
                    $stock->save();

                    // Update sisa quantity yang perlu dikurangi
                    $remainingQty -= $qtyToTake;
                }

                // Jika masih ada sisa quantity yang perlu dikurangi, stok tidak cukup
                if ($remainingQty > 0) {
                    \Log::warning("Insufficient stock for product ID: {$mapping->product_id}, Missing qty: {$remainingQty}");
                    $productName = $mapping->product ? $mapping->product->name : "Product ID: {$mapping->product_id}";
                    throw new \Exception("Stok tidak cukup untuk produk {$productName}");
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error in reduceStock: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Dapatkan data yang sudah diproses
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Dapatkan daftar produk yang belum dimapping
     *
     * @return array
     */
    public function getUnmappedProducts()
    {
        return $this->unmappedProducts;
    }

    /**
     * Dapatkan daftar data yang tidak valid
     *
     * @return array
     */
    public function getInvalidData()
    {
        return $this->invalidData;
    }

    /**
     * Dapatkan masalah terkait header
     *
     * @return array
     */
    public function getHeaderIssues()
    {
        return $this->headerIssues;
    }

    /**
     * Dapatkan total baris dalam file Excel
     *
     * @return int
     */
    public function getTotalRows()
    {
        return $this->totalRows;
    }

    /**
     * Dapatkan jumlah baris yang dilewati karena tanggal kosong
     *
     * @return int
     */
    public function getSkippedForMissingDate()
    {
        return $this->skippedForMissingDate;
    }

    /**
     * Dapatkan jumlah baris yang dilewati karena hari kosong
     *
     * @return int
     */
    public function getSkippedForMissingDay()
    {
        return $this->skippedForMissingDay;
    }

    /**
     * Dapatkan jumlah baris yang dilewati karena no resi kosong
     *
     * @return int
     */
    public function getSkippedForMissingResi()
    {
        return $this->skippedForMissingResi;
    }

    /**
     * Set data untuk diimport
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Set unmapped products
     */
    public function setUnmappedProducts(array $unmappedProducts)
    {
        $this->unmappedProducts = $unmappedProducts;
    }

    /**
     * Get day of week name in Indonesian from a date
     *
     * @param  string|Carbon  $date
     * @return string
     */
    protected function getDayOfWeek($date)
    {
        if (!$date) {
            return null;
        }
        
        if (!($date instanceof Carbon)) {
            $date = Carbon::parse($date);
        }
        
        $dayMap = [
            'Sunday' => 'MINGGU',
            'Monday' => 'SENIN',
            'Tuesday' => 'SELASA',
            'Wednesday' => 'RABU',
            'Thursday' => 'KAMIS',
            'Friday' => 'JUMAT',
            'Saturday' => 'SABTU',
        ];
        
        return $dayMap[$date->format('l')] ?? $date->format('l');
    }

    /**
     * Periksa apakah produk dan variasi sudah dimapping di database
     * 
     * PENTING: $variation parameter HARUS diambil dari kolom variant Excel, BUKAN dari parsing nama produk
     * Meskipun di nama produk ada simbol seperti "-", "+", dll, JANGAN parsing untuk mendapatkan variant
     *
     * @param  string  $productName Nama produk dari kolom nama_barang Excel
     * @param  string|null  $variation Variant dari kolom variasi Excel (BUKAN dari parsing nama produk)
     * @return void
     */
    protected function checkProductMapping($productName, $variation = null)
    {
        if (empty($productName)) {
            return;
        }

        // PENTING: Variant ($variation) sudah diambil dari kolom variant Excel, bukan dari parsing nama produk
        // Buat nama produk lengkap dengan variasi jika ada (hanya untuk logging/display)
        $fullProductName = $productName;
        if (!empty($variation)) {
            $fullProductName .= " - " . $variation;
        }

        // Debug: Log nama produk yang sedang diperiksa
        \Log::info("Checking product mapping for: $fullProductName");

        // Debug: Log input parameters
        \Log::info('checkProductMapping called', [
            'productName' => $productName,
            'variation' => $variation,
            'platform_id' => $this->platform->id,
            'platform_name' => $this->platform->name
        ]);

        // Cari produk platform dengan exact matching untuk variant
        $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
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

        // Jika tidak ditemukan dengan exact matching, coba dengan pencarian yang lebih fleksibel
        // TETAPI hanya jika tidak ada variant yang spesifik
        if (!$platformProduct) {
            if (!empty($variation)) {
                // Jika ada variant spesifik, jangan gunakan flexible search
                // Biarkan platformProduct tetap null agar bisa ditangani sebagai unmapped product
                \Log::warning("Exact match not found for product: $productName, variant: $variation. Skipping flexible search for variant-specific products.");
            } else {
                // Hanya gunakan flexible search jika tidak ada variant
                \Log::warning("Exact match not found for product: $productName (no variant), trying flexible search");
                
                $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                    ->where(function ($query) use ($productName, $fullProductName) {
                        // Coba cari dengan nama lengkap
                        $query->where('platform_product_name', $fullProductName)
                            ->orWhere('platform_product_name', 'LIKE', '%'.$fullProductName.'%')
                            // Juga coba cari dengan nama produk saja
                            ->orWhere('platform_product_name', $productName)
                            ->orWhere('platform_product_name', 'LIKE', '%'.$productName.'%')
                            ->orWhere(DB::raw('LOWER(platform_product_name)'), 'LIKE', '%'.strtolower($productName).'%');
                    })
                    ->first();
            }
        }

        if ($platformProduct) {
            // Debug: Log detail platform product
            \Log::info('Platform Product found', [
                'id' => $platformProduct->id,
                'name' => $platformProduct->platform_product_name,
                'variant' => $platformProduct->variant,
            ]);

            // Periksa mapping - cari berdasarkan platform_product_id yang benar
            $mappingExists = MappingBarang::where('platform_product_id', $platformProduct->id)
                ->exists();

            // Debug: Log status mapping
            \Log::info('Mapping exists: '.($mappingExists ? 'Yes' : 'No'));

            // Jika tidak ada mapping, tambahkan ke unmapped
            if (! $mappingExists) {
                // Simpan dalam format array untuk mempertahankan nama dan variant terpisah
                $productData = [
                    'name' => $productName,
                    'variant' => $variation,
                    'full_name' => $fullProductName
                ];
                
                if (! in_array($productData, $this->unmappedProducts)) {
                    $this->unmappedProducts[] = $productData;
                }
            }
        } else {
            // Debug: Tidak menemukan platform product
            \Log::warning("No platform product found for: $fullProductName");

            // Auto-create PlatformProduct baru jika tidak ditemukan
            try {
                \Log::info("Auto-creating new PlatformProduct for: $fullProductName");
                
                $newPlatformProduct = PlatformProduct::create([
                    'platform_id' => $this->platform->id,
                    'platform_product_name' => $productName,
                    'variant' => $variation,
                ]);
                
                \Log::info("Successfully created PlatformProduct with ID: " . $newPlatformProduct->id);
                
                // Setelah dibuat, produk ini akan tetap unmapped karena belum ada mapping
                // User perlu melakukan mapping manual melalui interface
                $productData = [
                    'name' => $productName,
                    'variant' => $variation,
                    'full_name' => $fullProductName,
                    'platform_product_id' => $newPlatformProduct->id
                ];
                
                if (! in_array($productData, $this->unmappedProducts)) {
                    $this->unmappedProducts[] = $productData;
                }
                
            } catch (\Exception $e) {
                \Log::error("Failed to auto-create PlatformProduct: " . $e->getMessage());
                
                // Fallback: tambahkan ke unmapped tanpa auto-create
                $productData = [
                    'name' => $productName,
                    'variant' => $variation,
                    'full_name' => $fullProductName
                ];
                
                if (! in_array($productData, $this->unmappedProducts)) {
                    $this->unmappedProducts[] = $productData;
                }
            }
        }
    }

    /**
     * Mendapatkan order yang mengandung produk yang belum dimapping
     * 
     * @return array
     */
    public function getOrdersWithUnmappedProducts()
    {
        $result = [];
        
        if (empty($this->unmappedProducts)) {
            return $result;
        }
        
        // Group the data by order number
        $groupedData = [];
        foreach ($this->data as $row) {
            $groupedData[$row['no_order']][] = $row;
        }
        
        // Check each order for unmapped products
        foreach ($groupedData as $orderNumber => $items) {
            foreach ($items as $item) {
                $productName = $item['nama_barang'];
                $variation = $item['variasi'] ?? null;
                $fullProductName = !empty($variation) ? "$productName - $variation" : $productName;
                
                if (in_array($fullProductName, $this->unmappedProducts)) {
                    if (!in_array($orderNumber, $result)) {
                        $result[] = $orderNumber;
                    }
                    break;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Get total duplicate orders count
     * 
     * @return int
     */
    public function getDuplicateOrders()
    {
        return $this->duplicateOrders;
    }
    
    /**
     * Get duplicate orders in file
     * 
     * @return array
     */
    public function getDuplicateOrdersInFile()
    {
        return $this->duplicateOrdersInFile;
    }
    
    /**
     * Get duplicate orders in database
     * 
     * @return array
     */
    public function getDuplicateOrdersInDatabase()
    {
        return $this->duplicateOrdersInDatabase;
    }
}
