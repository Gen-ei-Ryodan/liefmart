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

class Tiktok2Import implements ToCollection, WithMultipleSheets
{
    protected $platform;

    protected $data = [];

    protected $unmappedProducts = [];

    protected $invalidData = [];

    protected $headerIssues = [];

    protected $insufficientStockProducts = [];
    
    protected $ordersWithStockIssues = [];
    
    protected $stockIssuesSummary = [];

    protected $headerRowIndex = 0;

    protected $columnMapping = [];

    protected $headers = [];

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
                $this->platform = Platform::where('name', 'tiktok2')->first();
            }
        }

        // Jika platform tidak ditemukan, throw exception
        if (!$this->platform) {
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

        // Cari indeks baris header
        $headerRowIndex = $this->findHeaderRow($rows);

        if ($headerRowIndex === false) {
            $this->headerIssues[] = 'Format header tidak ditemukan. Pastikan file memiliki header yang sesuai.';
            return;
        }

        // Debug: Log header row index
        \Log::info('Header row index:', ['index' => $headerRowIndex]);

        $this->headerRowIndex = $headerRowIndex;

        // Ambil header dari baris yang ditemukan
        $headers = $rows[$headerRowIndex];

        // Store headers for later reference
        $this->headers = $headers->toArray();
        
        // Debug: Log headers
        \Log::info('Headers:', $headers->toArray());

        // Buat mapping kolom
        $this->mapColumns($headers);

        // Validasi mapping kolom
        if (!$this->validateColumnMapping()) {
            return;
        }

        // Tracking nomor pesanan untuk mencegah duplikasi dalam satu file
        $orderNumbersInFile = [];
        
        // Log the column mapping for debugging
        \Log::info('Final Column Mapping:', $this->columnMapping);

        // Proses data dimulai dari baris setelah header
        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Debug: Log setiap baris
            \Log::info("Row $i:", $row->toArray());

            // Pastikan baris tidak kosong
            if ($this->isEmptyRow($row)) {
                \Log::info("Empty row skipped: $i");
                continue;
            }

            // Ambil data sesuai mapping
            $processedRow = $this->processRow($row);

            // Debug: Log processed row
            \Log::info("Processed Row $i:", $processedRow);

            // Validasi data
            $validationErrors = $this->validateRow($processedRow);
            if (!empty($validationErrors)) {
                $this->invalidData[] = 'Baris #' . ($i - $headerRowIndex) . ': ' . implode(', ', $validationErrors);
                \Log::warning("Validation errors for row $i:", $validationErrors);
                continue;
            }

            // Skip jika tanggal, no_resi, atau hari tidak ada meskipun header ada
            if ($this->shouldSkipRow($processedRow)) {
                $this->skippedOrders++;
                \Log::info("Row skipped due to missing required field: $i");
                continue;
            }

            // Cek apakah produk sudah di-mapping dan dapatkan platform product
            $platformProduct = $this->checkProductMapping($processedRow['nama_barang'], $processedRow['variasi'] ?? null);

            // Set platform product ID jika ditemukan
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
        }

        // Debug: Log final data dan unmapped products
        \Log::info('Final Data Count:', ['count' => count($this->data)]);
        \Log::info('Unmapped Products Count:', ['count' => count($this->unmappedProducts)]);
        
        $this->calculateStockIssues();
    }

    /**
     * Memeriksa apakah baris harus dilewati karena field yang diperlukan tidak ada
     *
     * @param  array  $row
     * @return bool
     */
    protected function shouldSkipRow($row)
    {
        // Periksa jika header tanggal atau no_resi ada tetapi datanya kosong
        $shouldSkip = false;

        if (isset($this->columnMapping['tanggal']) && $this->columnMapping['tanggal'] !== null && empty($row['tanggal'])) {
            $this->skippedForMissingDate++;
            $shouldSkip = true;
        }

        if (isset($this->columnMapping['no_resi']) && $this->columnMapping['no_resi'] !== null && isset($row['no_resi']) && empty($row['no_resi'])) {
            $this->skippedForMissingResi++;
            $shouldSkip = true;
        }

        // Catat saja jika hari kosong tapi jangan lewati baris
        if (isset($this->columnMapping['hari']) && $this->columnMapping['hari'] !== null && isset($row['hari']) && empty($row['hari'])) {
            $this->skippedForMissingDay++;
            // Jangan set $shouldSkip = true; agar tidak melewati baris
        }

        return $shouldSkip;
    }

    /**
     * Cari baris header dalam data dengan pendekatan yang lebih fleksibel untuk TikTok2
     *
     * @return int|bool
     */
    protected function findHeaderRow(Collection $rows)
    {
        // Daftar header yang kita cari, termasuk variasi untuk fleksibilitas
        $headerVariations = [
            'no_order' => ['NOMOR PESANAN', 'ORDER ID', 'NO PESANAN', 'ORDER NUMBER'],
            'nama_barang' => ['NAMA PRODUK', 'NAMA BARANG', 'PRODUCT NAME'],
            'variasi' => ['VARIASI', 'VARIANT', 'VARIAN'],
            'qty' => ['QTY', 'JUMLAH', 'QUANTITY'],
            'harga_setelah_diskon' => ['HARGA SETELAH DISKON', 'HARGA', 'PRICE', 'SUBTOTAL'],
            'tanggal' => ['TANGGAL', 'TANGGAL PESANAN', 'ORDER DATE', 'TANGGAL ORDER'],
            'hari' => ['HARI', 'DAY'],
            'status_hari' => ['STATUS HARI', 'STATUS'],
            'no_resi' => ['NOMOR RESI', 'TRACKING NUMBER', 'NO RESI', 'AWB']
        ];

        // Buat list header yang flat untuk pencocokan
        $allPossibleHeaders = [];
        foreach ($headerVariations as $variations) {
            $allPossibleHeaders = array_merge($allPossibleHeaders, $variations);
        }

        // Array untuk menyimpan skor kecocokan untuk setiap baris
        $matchScores = [];

        // Cek 30 baris pertama untuk efisiensi
        foreach ($rows as $index => $row) {
            if ($index > 30) {
                break;
            }

            $matchScores[$index] = 0;
            $headerMatches = [];

            // Periksa setiap sel dalam baris
            foreach ($row as $cellIndex => $cell) {
                if (!is_string($cell)) {
                    continue;
                }

                $normalizedCell = trim(strtoupper($cell));
                
                // Abaikan sel kosong atau terlalu pendek
                if (empty($normalizedCell) || strlen($normalizedCell) < 3) {
                    continue;
                }

                // Cek kecocokan persis & substring
                foreach ($allPossibleHeaders as $header) {
                    $normalizedHeader = trim(strtoupper($header));
                    
                    // Kecocokan persis
                    if ($normalizedCell === $normalizedHeader) {
                        $matchScores[$index] += 2;
                        $headerMatches[] = $header;
                        break;
                    } 
                    // Kecocokan sebagian (untuk header yang lebih panjang)
                    else if (strpos($normalizedCell, $normalizedHeader) !== false) {
                        $matchScores[$index] += 1;
                        $headerMatches[] = $header;
                        break;
                    }
                }
            }

            // Log semua header yang ditemukan di baris ini
            if (count($headerMatches) > 0) {
                \Log::info("Row $index has " . count($headerMatches) . " header matches: " . implode(", ", $headerMatches));
            }
        }

        // Cari baris dengan skor tertinggi
        arsort($matchScores);
        \Log::info('Header match scores:', $matchScores);

        // Ambil baris dengan skor tertinggi
        $bestMatchIndex = key($matchScores);
        $bestMatchScore = $matchScores[$bestMatchIndex];

        // Minimal harus memiliki 4 kecocokan header untuk valid
        if ($bestMatchScore >= 4) {
            return $bestMatchIndex;
        }

        return false;
    }

    /**
     * Mapping kolom dari header dengan toleransi tinggi untuk berbagai format TikTok2
     *
     * @param  Collection  $headers
     * @return void
     */
    protected function mapColumns($headers)
    {
        \Log::info('Original Headers:', $headers->toArray());

        // Daftar HEADER yang TEPAT dari Excel tanpa alias (STRICT MATCHING)
        // PENTING: Untuk variasi, cari 'VARIASI', 'VARIANT', atau 'VARIAN' (case-insensitive)
        $exactHeaders = [
            'no_order' => 'NOMOR PESANAN',
            'nama_barang' => 'NAMA PRODUK',
            'variasi' => ['VARIASI', 'VARIANT', 'VARIAN'], // Menerima VARIASI, VARIANT, atau VARIAN (case-insensitive)
            'qty' => 'QTY',
            'harga_setelah_diskon' => 'HARGA SETELAH DISKON',
            'hari' => 'HARI',
            'tanggal' => 'TANGGAL',
            'status_hari' => 'STATUS HARI',
            'no_resi' => 'NOMOR RESI'
        ];

        $this->columnMapping = [];

        // Inisialisasi dengan null
        foreach (array_keys($exactHeaders) as $key) {
            $this->columnMapping[$key] = null;
        }

        // Cari exact match untuk setiap header yang dibutuhkan
        foreach ($headers as $index => $header) {
            if (!is_string($header)) {
                continue;
            }

            // Cek untuk setiap header yang dibutuhkan
            foreach ($exactHeaders as $key => $exactHeader) {
                // Handle array untuk field yang memiliki multiple header options (seperti variasi)
                $headerOptions = is_array($exactHeader) ? $exactHeader : [$exactHeader];
                $matched = false;
                
                foreach ($headerOptions as $headerOption) {
                    // Case-insensitive comparison
                    if (strtoupper(trim($header)) === strtoupper(trim($headerOption))) {
                        $this->columnMapping[$key] = $index;
                        \Log::info("Mapped column '$key' to exact header '$header' (matched '$headerOption') at index $index");
                        $matched = true;
                        break;
                    }
                }
                
                if ($matched) {
                    
                    // Untuk tanggal, simpan juga kolom referensi tanggal
                    if ($key === 'tanggal') {
                        // Catat kolom referensi untuk tanggal (untuk handling formula)
                        if (!isset($this->additionalColumns)) {
                            $this->additionalColumns = [];
                        }
                            
                        // Catat kolom "Order created time" untuk referensi
                        $dateColIndex = array_search('Order created time', $headers->toArray());
                        if ($dateColIndex !== false) {
                            $this->additionalColumns['date_order_created_time'] = $dateColIndex;
                            \Log::info("Found date reference column 'Order created time' at index $dateColIndex");
                        }
                    }
                    break;
                }
            }
        }

        \Log::info('Final Column Mapping: ' . json_encode($this->columnMapping));
    }

    /**
     * Validasi mapping kolom yang dibutuhkan
     *
     * @return bool
     */
    protected function validateColumnMapping()
    {
        // Daftar kolom yang wajib ada
        $requiredColumns = ['no_order', 'nama_barang', 'qty', 'harga_setelah_diskon', 'tanggal', 'no_resi'];
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if (!isset($this->columnMapping[$column]) || $this->columnMapping[$column] === null) {
                $missingColumns[] = str_replace('_', ' ', ucfirst($column));
            }
        }

        if (!empty($missingColumns)) {
            $headerNames = array_map(function ($columnKey) {
                return match($columnKey) {
                    'no_order' => 'NOMOR PESANAN',
                    'nama_barang' => 'NAMA PRODUK',
                    'qty' => 'QTY',
                    'harga_setelah_diskon' => 'HARGA SETELAH DISKON',
                    'tanggal' => 'TANGGAL',
                    'no_resi' => 'NOMOR RESI',
                    default => str_replace('_', ' ', strtoupper($columnKey))
                };
            }, $requiredColumns);
            
            $this->headerIssues[] = 'Header yang dibutuhkan tidak ditemukan: ' . implode(', ', $missingColumns) . '. Pastikan file Excel memiliki header tepat berikut: ' . implode(', ', $headerNames);

            return false;
        }

        return true;
    }

    /**
     * Find and extract the value from cell when the value is an Excel formula
     * 
     * @param string $formulaValue Formula text
     * @param array $row Row data (must be array, not Collection)
     * @param int|null $defaultIndex Default index to try, can be null
     * @return mixed
     */
    protected function extractValueFromFormula($formulaValue, $row, $defaultIndex = null)
    {
        // Make sure row is array
        $rowArray = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : $row;
        
        \Log::info("Extracting value from formula: " . $formulaValue);
        
        // For formulas like =LEFT(AF2874,10) try to get the referenced cell
        if (is_string($formulaValue) && preg_match('/=LEFT\(([A-Z]+)/', $formulaValue, $matches)) {
            $refColumn = $matches[1] ?? null;
            \Log::info("Found reference to column: " . $refColumn);
            
            if ($refColumn) {
                // Convert column letter to index
                $colIndex = $this->convertExcelColToIndex($refColumn);
                \Log::info("Converted column $refColumn to index: " . ($colIndex !== false ? $colIndex : 'invalid'));
                
                if ($colIndex !== false && isset($rowArray[$colIndex])) {
                    $cellValue = $rowArray[$colIndex];
                    \Log::info("Found value at column $refColumn: " . (is_string($cellValue) ? $cellValue : 'non-string'));
                    
                    // Get the first 10 characters if it's a date-like string
                    if (is_string($cellValue)) {
                        // For date+time format, extract just the date part
                        if (strpos($cellValue, ' ') !== false && (strpos($cellValue, '/') !== false || strpos($cellValue, '-') !== false)) {
                            $parts = explode(' ', $cellValue);
                            \Log::info("Extracted date part from column $refColumn: " . $parts[0]);
                            return $parts[0];
                        }
                        // If it's just a date, return as is
                        if (strpos($cellValue, '/') !== false || strpos($cellValue, '-') !== false) {
                            \Log::info("Using date value from column $refColumn: " . $cellValue);
                            return $cellValue;
                        }
                        
                        // If it's any other string value, just return it
                        return $cellValue;
                    }
                }
            }
        }
        
        // If we couldn't extract from the formula and a defaultIndex is provided, try it
        if ($defaultIndex !== null && isset($rowArray[$defaultIndex])) {
            $defaultValue = $rowArray[$defaultIndex];
            \Log::info("Using default index $defaultIndex value: " . (is_string($defaultValue) ? $defaultValue : 'non-string'));
            
            // If it's a date with time, extract just the date part
            if (is_string($defaultValue) && strpos($defaultValue, ' ') !== false && 
                (strpos($defaultValue, '/') !== false || strpos($defaultValue, '-') !== false)) {
                $parts = explode(' ', $defaultValue);
                \Log::info("Extracted date part from default index: " . $parts[0]);
                return $parts[0];
            }
            return $defaultValue;
        }
        
        \Log::warning("Could not extract value from formula");
        return null;
    }
    
    /**
     * Convert Excel column letter to index
     * 
     * @param string $colLetter Column letter (e.g. 'A', 'AB')
     * @return int|false
     */
    protected function convertExcelColToIndex($colLetter)
    {
        // For simplicity, let's handle some common columns directly
        $commonCols = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 
            'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21,
            'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25, 'AA' => 26, 'AB' => 27, 'AC' => 28,
            'AD' => 29, 'AE' => 30, 'AF' => 31, 'AG' => 32, 'AH' => 33, 'AI' => 34, 'AJ' => 35,
            'AK' => 36, 'AL' => 37, 'AM' => 38, 'AN' => 39
        ];
        
        if (isset($commonCols[$colLetter])) {
            return $commonCols[$colLetter];
        }
        
        // General algorithm for other columns
        $colLetter = strtoupper($colLetter);
        $index = 0;
        $pow = 1;
        
        for ($i = strlen($colLetter) - 1; $i >= 0; $i--) {
            $char = $colLetter[$i];
            $charVal = ord($char) - 64; // A=1, B=2, etc.
            
            if ($i < strlen($colLetter) - 1) {
                $pow *= 26;
            }
            
            $index += $charVal * $pow;
        }
        
        return $index - 1; // Convert to zero-based
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
        
        // Debug untuk melihat row asli - Convert Collection to array if needed
        $rowData = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : $row;
        \Log::info("Processing row data:", array_slice($rowData, 0, min(40, count($rowData))));

        foreach ($this->columnMapping as $key => $index) {
            // Skip jika index tidak ditemukan
            if ($index === null) {
                $result[$key] = null;
                continue;
            }
            
            // Ambil nilai dari kolom yang ditentukan - Handle both array and Collection
            $value = null;
            if ($row instanceof \Illuminate\Support\Collection) {
                $value = $row->has($index) ? $row->get($index) : null;
            } else {
                $value = isset($row[$index]) ? $row[$index] : null;
            }
            
            // Use our new calculateValue method to get the proper values
            // This ensures date values come from the right source
            $calculatedValue = $this->calculateValue($key, $value, $rowData);
            $result[$key] = $calculatedValue;
            
            // Enhanced logging for important fields
            if ($key === 'tanggal') {
                \Log::info("TANGGAL processed: original value = " . (is_string($value) ? $value : 'non-string') . 
                           ", calculated value = " . (is_string($calculatedValue) ? $calculatedValue : 'non-string'));
            }
            elseif ($key === 'harga_setelah_diskon') {
                $originalValueLog = is_string($value) ? $value : (is_numeric($value) ? strval($value) : 'non-string');
                $calculatedValueLog = is_string($calculatedValue) ? $calculatedValue : (is_numeric($calculatedValue) ? strval($calculatedValue) : 'non-string');
                
                \Log::info("HARGA processed: original value = $originalValueLog, calculated value = $calculatedValueLog");
            }
        }

        \Log::info("Processed row result:", $result);
        return $result;
    }

    /**
     * Periksa apakah produk sudah dimapping di database
     * 
     * PENTING: $variation parameter HARUS diambil dari kolom variant Excel, BUKAN dari parsing nama produk
     * Meskipun di nama produk ada simbol seperti "-", "+", dll, JANGAN parsing untuk mendapatkan variant
     *
     * @param  string  $productName Nama produk dari kolom nama_barang Excel
     * @param  string|null  $variation Variant dari kolom variasi Excel (BUKAN dari parsing nama produk)
     * @return \App\Models\PlatformProduct|null
     */
    protected function checkProductMapping($productName, $variation = null)
    {
        if (empty($productName)) {
            return null;
        }

        // PENTING: Variant ($variation) sudah diambil dari kolom variant Excel, bukan dari parsing nama produk
        // Buat nama produk lengkap dengan variasi jika ada (hanya untuk logging/display)
        $fullProductName = $productName;
        if (!empty($variation)) {
            $fullProductName .= " - " . $variation;
        }

        // Debug: Log nama produk yang sedang diperiksa
        \Log::info("Checking product mapping for: $fullProductName");

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

        // Jika tidak ditemukan dengan exact matching, coba dengan format yang berbeda
        // untuk menangani kasus seperti "Product Name - 100ml" vs "Product Name" dengan variant "100ml - PAKET"
        if (!$platformProduct && !empty($variation)) {
            // Coba cari dengan format: nama produk tanpa "- 100ml" dan variant dengan "100ml - " + variant asli
            $baseProductName = preg_replace('/\s*-\s*100ml$/', '', $productName);
            $newVariant = '100ml - ' . $variation;
            
            if ($baseProductName !== $productName) {
                \Log::info("Trying alternative format: baseName='$baseProductName', newVariant='$newVariant'");
                
                $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                    ->where('platform_product_name', $baseProductName)
                    ->where('variant', $newVariant)
                    ->first();
                    
                if ($platformProduct) {
                    \Log::info("Found platform product with alternative format: ID={$platformProduct->id}");
                }
            }
        }

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

            // Periksa mapping
            $mappingExists = MappingBarang::where('platform_product_id', $platformProduct->id)->exists();

            // Debug: Log status mapping
            \Log::info('Mapping exists: ' . ($mappingExists ? 'Yes' : 'No'));

            // Jika tidak ada mapping, tambahkan ke unmapped
            if (!$mappingExists) {
                // Simpan dalam format array untuk mempertahankan nama dan variant terpisah
                $productData = [
                    'name' => $productName,
                    'variant' => $variation,
                    'full_name' => $fullProductName
                ];
                
                if (!in_array($productData, $this->unmappedProducts)) {
                    $this->unmappedProducts[] = $productData;
                }
            }
        } else {
            // Debug: Tidak menemukan platform product
            \Log::warning("No platform product found for: $fullProductName");

            // Tambahkan ke unmapped jika tidak ditemukan
            // Simpan dalam format array untuk mempertahankan nama dan variant terpisah
            $productData = [
                'name' => $productName,
                'variant' => $variation,
                'full_name' => $fullProductName
            ];
            
            if (!in_array($productData, $this->unmappedProducts)) {
                $this->unmappedProducts[] = $productData;
            }
        }
        
        return $platformProduct;
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
        if (empty($row['qty']) || !is_numeric($row['qty']) || $row['qty'] <= 0) {
            $errors[] = 'Quantity harus berupa angka positif';
        } else if (floor($row['qty']) != $row['qty']) {
            $errors[] = 'Quantity tidak boleh berupa angka desimal (ditemukan: ' . $row['qty'] . '). Harap perbaiki file Excel Anda.';
        }
        
        // Validasi harga yang lebih toleran terhadap input
        $harga = $row['harga_setelah_diskon'];
        if (empty($harga)) {
            $errors[] = 'Harga tidak boleh kosong';
        } else {
            // Ensure we have a clean numeric value for validation
            $numericHarga = $harga;
            
            // Coba konversi ke numeric jika string
            if (is_string($harga)) {
                // Hapus karakter non-numeric kecuali titik desimal
                $numericHarga = preg_replace('/[^\d.]/', '', $harga);
            }
            
            if (!is_numeric($numericHarga) || (float)$numericHarga < 0) {
                $errors[] = 'Harga harus berupa angka tidak negatif';
            }
        }

        if (!empty($errors)) {
            \Log::info('Validation Errors:', $errors);
            $this->invalidData[] = 'Baris: ' . json_encode($row) . ' -> ' . implode(', ', $errors);
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
            if (!empty($cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse tanggal dari berbagai format ke format Y-m-d
     * 
     * @param string $dateStr
     * @return string|null
     */
    protected function parseDate($dateStr)
    {
        // Validasi yang lebih ketat untuk tanggal kosong
        if (empty($dateStr) || is_null($dateStr) || trim((string)$dateStr) === '') {
            \Log::warning("parseDate: Empty or null date string provided");
            return null;
        }

        try {
            // Log the original date string
            \Log::info("Parsing date: " . $dateStr);
            
            // First, ensure date string is properly formatted for parsing
            // Clean any unwanted characters
            $dateStr = trim($dateStr);
            
            // Try to parse with various formats - IMPORTANT: order matters!
            // For TikTok2, prioritize d/m/Y format (day/month/year)
            $formats = ['d/m/Y', 'd/m/y', 'j/n/Y', 'j/n/y', 'Y-m-d', 'd-m-Y'];
            
            foreach ($formats as $format) {
                try {
                    $dateObj = Carbon::createFromFormat($format, $dateStr);
                    if ($dateObj) {
                        $result = $dateObj->format('Y-m-d');
                        \Log::info("Successfully parsed date: $dateStr to $result using format $format");
                        return $result;
                    }
                } catch (\Exception $e) {
                    // Just log the error and continue to next format
                    \Log::debug("Failed to parse date with format $format: " . $e->getMessage());
                    continue;
                }
            }
            
            // Last resort - try Carbon's parse method with additional error handling
            // But first, validate that we have a meaningful date string
            if (empty(trim($dateStr)) || strlen(trim($dateStr)) < 3) {
                \Log::error("Date string too short or empty for Carbon parsing: '$dateStr'");
                return null;
            }
            
            try {
                $carbonDate = Carbon::parse($dateStr);
                $result = $carbonDate->format('Y-m-d');
                \Log::info("Successfully parsed date using Carbon::parse: $dateStr to $result");
                return $result;
            } catch (\Exception $e) {
                \Log::error("Failed to parse date with Carbon::parse: " . $e->getMessage());
                return null;
            }
        } catch (\Exception $e) {
            \Log::error("Failed to parse date: $dateStr - " . $e->getMessage());
            return null;
        }
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
            'failed_orders' => [],
        ];

        // Jika ada produk yang belum di-mapping atau data yang tidak valid, return error
        if (!empty($this->unmappedProducts) || !empty($this->invalidData)) {
            return [
                'success' => 0,
                'errors' => [
                    'unmappedProducts' => $this->unmappedProducts,
                    'invalidData' => $this->invalidData,
                ],
            ];
        }

        // Kelompokkan data berdasarkan no_order untuk menangani beberapa item dalam 1 order
        $groupedData = [];
        foreach ($this->data as $row) {
            $groupedData[$row['no_order']][] = $row;
        }

        // Gunakan database transaction untuk memastikan semua operasi sukses atau tidak sama sekali
        DB::beginTransaction();

        try {
            // Proses import data ke database
            foreach ($groupedData as $orderNumber => $orderItems) {
                try {
                    // Ambil item pertama untuk informasi order
                    $firstItem = $orderItems[0];

                    // Validasi tanggal kosong sebelum parsing
                    if (empty($firstItem['tanggal']) || is_null($firstItem['tanggal']) || trim((string)$firstItem['tanggal']) === '') {
                        $results['skipped']++;
                        \Log::warning("Skipping order $orderNumber due to empty date: " . ($firstItem['tanggal'] ?? 'null'));
                        continue;
                    }
                    
                    // Parse tanggal menggunakan helper method
                    $tanggal = $this->parseDate($firstItem['tanggal']);
                    
                    if (empty($tanggal)) {
                        $results['skipped']++;
                        \Log::warning("Skipping order $orderNumber due to invalid date format: " . ($firstItem['tanggal'] ?? 'null'));
                        continue;
                    }

                    // Tentukan hari dari tanggal jika hari tidak tersedia
                    $hari = !empty($firstItem['hari']) ? $firstItem['hari'] : null;

                    // Ambil status_hari dari data
                    $statusHari = !empty($firstItem['status_hari']) ? $firstItem['status_hari'] : null;

                    // Cek apakah order sudah ada di database
                    $existingOrder = Order::where('platform_id', $this->platform->id)->where('order_number', $orderNumber)->first();

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

                        // Jika tidak ditemukan dengan exact matching, coba dengan format yang berbeda
                        // untuk menangani kasus seperti "Product Name - 100ml" vs "Product Name" dengan variant "100ml - PAKET"
                        if (!$platformProduct && !empty($variation)) {
                            // Coba cari dengan format: nama produk tanpa "- 100ml" dan variant dengan "100ml - " + variant asli
                            $baseProductName = preg_replace('/\s*-\s*100ml$/', '', $productName);
                            $newVariant = '100ml - ' . $variation;
                            
                            if ($baseProductName !== $productName) {
                                \Log::info("Trying alternative format in processImport: baseName='$baseProductName', newVariant='$newVariant'");
                                
                                $platformProduct = PlatformProduct::where('platform_id', $this->platform->id)
                                    ->where('platform_product_name', $baseProductName)
                                    ->where('variant', $newVariant)
                                    ->first();
                                    
                                if ($platformProduct) {
                                    \Log::info("Found platform product with alternative format in processImport: ID={$platformProduct->id}");
                                }
                            }
                        }

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

                        if (!$platformProduct) {
                            throw new \Exception("Produk '$fullProductName' tidak ditemukan di database.");
                        }

                        // Ensure price is a proper numeric value
                        $priceValue = $item['harga_setelah_diskon'];
                        if (is_string($priceValue)) {
                            $priceValue = preg_replace('/[^\d.]/', '', $priceValue);
                        }
                        
                        // Buat order item
                        $orderItem = new OrderItem([
                            'order_id' => $order->id,
                            'platform_product_id' => $platformProduct->id,
                            'quantity' => $item['qty'],
                            'price_after_discount' => $priceValue,
                            'tracking_number' => $item['no_resi'] ?? null,
                            'variasi' => $item['variasi'] ?? null,
                        ]);
                        $orderItem->save();

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
                    
                    // Buat pesan error yang lebih user-friendly
                    $userFriendlyMessage = $e->getMessage();
                    if (strpos($e->getMessage(), 'Stok tidak cukup') !== false) {
                        $userFriendlyMessage = "Stok tidak mencukupi untuk order $orderNumber. Silakan periksa stok warehouse.";
                    }
                    
                    // Mulai transaksi baru untuk order berikutnya
                    DB::beginTransaction();
                    
                    // Jangan throw exception, lanjutkan ke order berikutnya
                    // Kecuali jika semua order gagal, akan ditangani di akhir
                    continue;
                }
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
        // Ambil semua mapping barang AKTIF untuk platform product ini
        $mappings = MappingBarang::where('platform_product_id', $platformProduct->id)
            ->where('is_active', true)
            ->get();

        foreach ($mappings as $mapping) {
            // Hitung jumlah yang perlu dikurangi dari stok
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

    protected function calculateStockIssues()
    {
        $this->insufficientStockProducts = [];
        $this->ordersWithStockIssues = [];
        $this->stockIssuesSummary = [];
        
        if (empty($this->data)) {
            return;
        }
        
        $totalProductQuantities = [];
        $orderProductQuantities = [];
        
        $platformProductIds = array_values(array_unique(array_filter(array_map(function ($row) {
            return $row['platform_product_id'] ?? null;
        }, $this->data))));
        
        if (empty($platformProductIds)) {
            return;
        }
        
        $mappingsByPlatformProduct = MappingBarang::whereIn('platform_product_id', $platformProductIds)
            ->where('is_active', true)
            ->get(['platform_product_id', 'product_id', 'quantity'])
            ->groupBy('platform_product_id');
        
        foreach ($this->data as $row) {
            $orderNumber = $row['no_order'] ?? 'N/A';
            if (!isset($orderProductQuantities[$orderNumber])) {
                $orderProductQuantities[$orderNumber] = [];
            }
            
            $platformProductId = $row['platform_product_id'] ?? null;
            if (!$platformProductId) {
                continue;
            }
            
            $mappings = $mappingsByPlatformProduct->get($platformProductId, collect());
            if ($mappings->isEmpty()) {
                continue;
            }
            
            $itemQty = $row['qty'] ?? 0;
            foreach ($mappings as $mapping) {
                $requiredQty = $itemQty * $mapping->quantity;
                
                if (!isset($orderProductQuantities[$orderNumber][$mapping->product_id])) {
                    $orderProductQuantities[$orderNumber][$mapping->product_id] = 0;
                }
                $orderProductQuantities[$orderNumber][$mapping->product_id] += $requiredQty;
                
                if (!isset($totalProductQuantities[$mapping->product_id])) {
                    $totalProductQuantities[$mapping->product_id] = 0;
                }
                $totalProductQuantities[$mapping->product_id] += $requiredQty;
            }
        }
        
        if (empty($totalProductQuantities)) {
            return;
        }
        
        foreach ($totalProductQuantities as $productId => $totalRequiredQty) {
            $stocks = WarehouseStock::where('product_id', $productId)
                ->where('qty', '>', 0)
                ->orderBy('warehouse_stock.created_at')
                ->orderBy('warehouse_stock.tax_id', 'asc')
                ->get(['warehouse_stock.qty']);
            
            $remainingQty = $totalRequiredQty;
            foreach ($stocks as $stock) {
                if ($remainingQty <= 0) {
                    break;
                }
                
                $qtyToTake = min($remainingQty, $stock->qty);
                
                $remainingQty -= $qtyToTake;
            }
            
            if ($remainingQty > 0) {
                $product = Product::find($productId);
                $productName = $product ? $product->name : "Product ID: {$productId}";
                $effectiveAvailableStock = $totalRequiredQty - $remainingQty;
                
                $affectedOrders = [];
                foreach ($orderProductQuantities as $orderNumber => $products) {
                    if (isset($products[$productId]) && $products[$productId] > 0) {
                        $affectedOrders[] = $orderNumber;
                    }
                }
                
                $this->stockIssuesSummary[$productId] = [
                    'product_name' => $productName,
                    'total_required' => $totalRequiredQty,
                    'available_qty' => $effectiveAvailableStock,
                    'shortage' => $remainingQty,
                    'affected_orders' => $affectedOrders,
                ];
            }
        }
        
        foreach ($orderProductQuantities as $orderNumber => $products) {
            $orderStockIssues = [];
            foreach ($products as $productId => $requiredQty) {
                if (isset($this->stockIssuesSummary[$productId])) {
                    $product = Product::find($productId);
                    $productName = $product ? $product->name : "Product ID: {$productId}";
                    
                    $orderStockIssues[] = [
                        'product_name' => $productName,
                        'required_qty' => $requiredQty,
                        'available_qty' => $this->stockIssuesSummary[$productId]['available_qty'],
                        'shortage' => $requiredQty - $this->stockIssuesSummary[$productId]['available_qty'],
                    ];
                }
            }
            
            if (!empty($orderStockIssues)) {
                $this->ordersWithStockIssues[$orderNumber] = $orderStockIssues;
            }
        }
        
        $this->insufficientStockProducts = array_values($this->stockIssuesSummary);
    }

    public function getInsufficientStockProducts()
    {
        return $this->insufficientStockProducts;
    }

    public function getOrdersWithStockIssues()
    {
        return $this->ordersWithStockIssues;
    }

    public function getStockIssuesSummary()
    {
        return $this->stockIssuesSummary;
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
                'catatan' => "Penjualan online tiktok2 - Order #{$orderItem->order->order_number}",
                
            ]);

            \Log::info("Recorded BarangKeluar: $kodeBarangKeluar for OrderItem ID: {$orderItem->id}, Stock ID: {$stock->id}, Qty: $quantity");
        } catch (\Exception $e) {
            \Log::error('Error recording BarangKeluar: ' . $e->getMessage());
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
            \Log::info('Found mappings: ' . $mappings->count());

            foreach ($mappings as $mapping) {
                // Hitung jumlah yang perlu dikurangi dari stok
                $qtyToReduce = $quantity * $mapping->quantity;
                \Log::info("Processing mapping for product ID: {$mapping->product_id}, Qty to reduce: {$qtyToReduce}");

                // Ambil stok produk dari warehouse berdasarkan FIFO + prioritas HGN
                // PERBAIKAN: lockForUpdate() mencegah race condition saat 2 import berjalan bersamaan
                $stocks = WarehouseStock::where('product_id', $mapping->product_id)
                    ->where('qty', '>', 0)
                    ->orderBy('created_at') // Layer 1: FIFO berdasarkan tanggal penerimaan
                    ->orderBy('tax_id', 'asc') // Layer 2: HGN (tax_id=3) dulu, baru LM (tax_id=4)
                    ->lockForUpdate()
                    ->get();
                \Log::info('Found stock records: ' . $stocks->count());

                $remainingQty = $qtyToReduce;
                $isFirstStock = true;  // Flag untuk menandai stok pertama

                foreach ($stocks as $stock) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    // Hitung quantity yang akan dikurangi dari stok ini
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
            \Log::error('Error in reduceStock: ' . $e->getMessage());
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

    /**
     * Set data untuk diimport
     *
     * @param array $data
     * @return void
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
     * Extract and calculate a value for a specific field type from the row data
     * Used primarily for specific value processing while respecting header mappings
     * 
     * @param string $fieldType Type of field (tanggal, no_order, etc)
     * @param mixed $rawValue The raw value from the Excel cell
     * @param array $rowData Complete row data array
     * @return mixed Processed value
     */
    protected function calculateValue($fieldType, $rawValue, $rowData)
    {
        \Log::info("Calculating value for $fieldType, raw value: " . (is_string($rawValue) ? $rawValue : 'non-string'));
        
        // Special handling for tanggal field
        if ($fieldType === 'tanggal') {
            if (is_string($rawValue) && !empty($rawValue)) {
                // If it has time component, extract just the date part
                if (strpos($rawValue, ' ') !== false) {
                    $parts = explode(' ', $rawValue);
                    $dateOnly = $parts[0];
                    \Log::info("Using direct date value (split): $dateOnly");
                    return $dateOnly;
                }
                \Log::info("Using direct date value: $rawValue");
                return $rawValue;
            }
            
            \Log::warning("Empty or invalid date value");
            return null;
        }
        
        // Special handling for harga_setelah_diskon field
        if ($fieldType === 'harga_setelah_diskon') {
            // Clean up price value if it's a string
            if (is_string($rawValue)) {
                $cleanPrice = preg_replace('/[^\d.]/', '', $rawValue);
                if (!empty($cleanPrice)) {
                    \Log::info("Using cleaned price value: $cleanPrice (from $rawValue)");
                    return $cleanPrice;
                }
            } else if (is_numeric($rawValue)) {
                \Log::info("Using direct numeric price value: $rawValue");
                return $rawValue;
            }
            
            \Log::warning("Empty or invalid price value");
            return 0;
        }
        
        // For no_order field
        if ($fieldType === 'no_order') {
            if (is_string($rawValue) && !empty($rawValue)) {
                // Clean up any quotes or whitespace
                $cleanValue = trim(str_replace('"', '', $rawValue));
                \Log::info("Using cleaned order number: $cleanValue");
                return $cleanValue;
            }
            return $rawValue;
        }

        // PENTING: For variasi field - return as is from Excel, no trimming or normalization
        // VARIANT HARUS diambil HANYA dari kolom variant Excel, BUKAN dari parsing nama produk
        // Meskipun di nama produk ada simbol seperti "-", "+", dll, JANGAN parsing untuk mendapatkan variant
        // Variant hanya dari kolom variant Excel saja
        if ($fieldType === 'variasi') {
            // Return exactly as in Excel, preserve all characters including +, -, spaces, etc.
            // NO PARSING from product name - variant comes ONLY from variant column
            return $rawValue;
        }
        
        // PENTING: For nama_barang field - return as is from Excel, no trimming or normalization
        // Nama produk digunakan apa adanya, TIDAK ada parsing untuk mendapatkan variant
        if ($fieldType === 'nama_barang') {
            // Return exactly as in Excel, preserve all characters including +, -, spaces, etc.
            // NO PARSING - product name is used as-is, variant comes from variant column only
            return $rawValue;
        }
        
        // For no_resi field
        if ($fieldType === 'no_resi') {
            if (is_string($rawValue)) {
                return trim($rawValue);
            }
            return $rawValue;
        }
        
        // For status_hari field - support multiple values separated by comma
        if ($fieldType === 'status_hari') {
            if (is_string($rawValue) && !empty($rawValue)) {
                $value = trim($rawValue);
                // If value contains comma, it's already in the correct format for multiple values
                // If not, it's a single value
                if (strpos($value, ',') === false) {
                    // Single value, keep as is
                    return $value;
                } else {
                    // Multiple values separated by comma, keep as is
                    return $value;
                }
            }
            return $rawValue;
        }
        
        // Default: return the raw value
        return $rawValue;
    }

    /**
     * Get header value for a specific column index
     * 
     * @param int $columnIndex Index of the column
     * @return string|null Header value
     */
    protected function getHeaderValue($columnIndex)
    {
        if ($this->headerRowIndex === false || empty($this->headers)) {
            return null;
        }
        
        return $this->headers[$columnIndex] ?? null;
    }
}
