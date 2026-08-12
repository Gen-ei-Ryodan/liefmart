<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Penerimaan;
use App\Models\PenerimaanDetail;
use App\Models\WarehouseStock;
use App\Models\Product;
use Shared\Models\PaymentMethod;
use Shared\Helpers\NumberFormatter;
use Carbon\Carbon;

class PenerimaanSeeder extends Seeder
{
    public function run()
    {
        // Path to import directory
        $importPath = storage_path('app/imports/barangdatang');
        if (!is_dir($importPath)) {
            $this->command->warn('Import directory not found: ' . $importPath);
            return;
        }
        $files = glob($importPath . '/*.csv');
        if (empty($files)) {
            $this->command->warn('No CSV files found in: ' . $importPath);
            return;
        }
        foreach ($files as $filePath) {
            // Detect PKP or NONPKP from filename
            $type = stripos($filePath, 'NON PKP') !== false ? 'NONPKP' : 'PKP';
            $this->importCsv($type, $filePath);
        }
    }

    /**
     * Import a CSV file as penerimaan
     * @param string $type PKP or NONPKP
     * @param string $filePath
     */
    private function importCsv($type, $filePath)
    {
        $taxCategoryId = $type === 'PKP' ? 3 : 4;
        $paymentMethod = PaymentMethod::find(1);
        if (!$paymentMethod) {
            $this->command->error('Payment method with ID 1 not found');
            return;
        }
        $csv = array_map('str_getcsv', file($filePath));
        $header = array_shift($csv);
        $header = array_map('trim', $header); // Trim whitespace from headers
        
        // Check for both old format (without ED) and new format (with ED)
        $expectedHeadersOld = ['NAMA BARANG', 'QTY', 'HARGA'];
        $expectedHeadersNew = ['NAMA BARANG', 'QTY', 'HARGA', 'ED'];
        
        if (array_map('strtoupper', $header) !== $expectedHeadersOld && array_map('strtoupper', $header) !== $expectedHeadersNew) {
            $this->command->error('CSV format incorrect in ' . basename($filePath) . '. Expected: NAMA BARANG, QTY, HARGA or NAMA BARANG, QTY, HARGA, ED but got: ' . implode(', ', $header));
            return;
        }
        
        $hasEd = array_map('strtoupper', $header) === $expectedHeadersNew;
        $totalHarga = 0;
        $details = [];
        foreach ($csv as $row) {
            if (count($row) < 3) {
                $this->command->error("Row with insufficient data: " . implode(',', $row));
                throw new \Exception("Row with insufficient data: " . implode(',', $row));
            }
            $productName = trim($row[0]);
            $qty = NumberFormatter::parseNumericValue(trim($row[1]));
            $harga = NumberFormatter::parseNumericValue(trim($row[2]));
            $ed = $hasEd && isset($row[3]) ? trim($row[3]) : null;
            
            $product = Product::where('name', $productName)->first();
            if (!$product) {
                $this->command->error("Product not found: {$productName} (file: " . basename($filePath) . ")");
                throw new \Exception("Product not found: {$productName} (file: " . basename($filePath) . ")");
            }
            $subtotal = NumberFormatter::calculateSubtotal($harga, $qty);
            $totalHarga += $subtotal;
            $details[] = [
                'product_id' => $product->id,
                'qty' => $qty,
                'satuan_id' => 1,
                'harga_hpp' => $harga,
                'subtotal' => $subtotal,
                'ed' => $ed,
            ];
        }
        if (empty($details)) {
            $this->command->error('No valid products found in ' . basename($filePath));
            throw new \Exception('No valid products found in ' . basename($filePath));
        }
        $nomorPo = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $tanggalPenerimaan = Carbon::now();
        $tanggalJatuhTempo = method_exists($paymentMethod, 'calculateDueDate') ? $paymentMethod->calculateDueDate($tanggalPenerimaan) : $tanggalPenerimaan;
        $penerimaan = Penerimaan::create([
            'kode_penerimaan' => 'PR-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId(),
            'tax_category_id' => $taxCategoryId,
            'nomor_po' => $nomorPo,
            'tanggal_penerimaan' => $tanggalPenerimaan,
            'metode_pembayaran' => 1,
            'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
            'total_harga' => $totalHarga,
            'status' => 'Unlocated',
            'catatan' => null,
            'lokasi_id' => 1,
        ]);
        foreach ($details as $detail) {
            $penerimaanDetail = PenerimaanDetail::create([
                'penerimaan_id' => $penerimaan->id,
                'product_id' => $detail['product_id'],
                'qty' => $detail['qty'],
                'satuan_id' => $detail['satuan_id'],
                'harga_hpp' => $detail['harga_hpp'],
                'diskon_persen_1' => 0,
                'diskon_nominal_1' => 0,
                'diskon_persen_2' => 0,
                'diskon_nominal_2' => 0,
                'diskon_persen_3' => 0,
                'diskon_nominal_3' => 0,
                'diskon_persen_4' => 0,
                'diskon_nominal_4' => 0,
                'diskon_persen_5' => 0,
                'diskon_nominal_5' => 0,
                'is_free' => 0,
                'subtotal' => $detail['subtotal'],
                'catatan' => null,
            ]);

            // Create WarehouseStock if ED is provided
            if ($detail['ed']) {
                $this->createWarehouseStocks($detail, $penerimaanDetail, $penerimaan);
            }
        }
        $this->command->info("Imported " . count($details) . " items from " . basename($filePath) . " as {$type} penerimaan.");
    }

    /**
     * Create WarehouseStock entries based on ED format
     * Handles various ED formats including complex ones like "2 PCS (11/01/25) 4 PCS (5/01/26)"
     */
    private function createWarehouseStocks($detail, $penerimaanDetail, $penerimaan)
    {
        $ed = $detail['ed'];
        
        // Handle complex ED format like "2 PCS (11/01/25) 4 PCS (5/01/26)"
        if (preg_match_all('/(\d+)\s+PCS\s+\((\d{1,2})\/(\d{1,2})\/(\d{2,4})\)/', $ed, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $qty = (int) $match[1];
                $day = (int) $match[2];
                $month = (int) $match[3];
                $year = (int) $match[4];
                
                // Convert 2-digit year to 4-digit
                if ($year < 100) {
                    $year += 2000;
                }
                
                $expiredDate = Carbon::createFromDate($year, $month, $day);
                
                WarehouseStock::create([
                    'product_id' => $detail['product_id'],
                    'lokasi_id' => $penerimaan->lokasi_id,
                    'penerimaan_detail_id' => $penerimaanDetail->id,
                    'tax_id' => $penerimaan->tax_category_id,
                    'qty' => $qty,
                    'expired_date' => $expiredDate,
                    'status_ed' => 'aman', // Will be calculated by model mutator
                    'catatan' => null,
                    'source_type' => 'penerimaan',
                    'source_id' => $penerimaan->id,
                    'source_date' => $penerimaan->tanggal_penerimaan,
                ]);
            }
        }
        // Handle simple ED format like "2026-12" or "2026-12-15"
        elseif (preg_match('/^(\d{4})-(\d{2})$/', $ed, $matches)) {
            $expiredDate = Carbon::createFromFormat('Y-m', $ed)->endOfMonth();
            
            WarehouseStock::create([
                'product_id' => $detail['product_id'],
                'lokasi_id' => $penerimaan->lokasi_id,
                'penerimaan_detail_id' => $penerimaanDetail->id,
                'tax_id' => $penerimaan->tax_category_id,
                'qty' => $detail['qty'],
                'expired_date' => $expiredDate,
                'status_ed' => 'aman', // Will be calculated by model mutator
                'catatan' => null,
                'source_type' => 'penerimaan',
                'source_id' => $penerimaan->id,
                'source_date' => $penerimaan->tanggal_penerimaan,
            ]);
        }
        elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ed, $matches)) {
            $expiredDate = Carbon::createFromFormat('Y-m-d', $ed);
            
            WarehouseStock::create([
                'product_id' => $detail['product_id'],
                'lokasi_id' => $penerimaan->lokasi_id,
                'penerimaan_detail_id' => $penerimaanDetail->id,
                'tax_id' => $penerimaan->tax_category_id,
                'qty' => $detail['qty'],
                'expired_date' => $expiredDate,
                'status_ed' => 'aman', // Will be calculated by model mutator
                'catatan' => null,
                'source_type' => 'penerimaan',
                'source_id' => $penerimaan->id,
                'source_date' => $penerimaan->tanggal_penerimaan,
            ]);
        }
        // Handle format like "6/1/2027" (M/D/Y)
        elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $ed, $matches)) {
            $month = (int) $matches[1];
            $day = (int) $matches[2];
            $year = (int) $matches[3];
            
            $expiredDate = Carbon::createFromDate($year, $month, $day);
            
            WarehouseStock::create([
                'product_id' => $detail['product_id'],
                'lokasi_id' => $penerimaan->lokasi_id,
                'penerimaan_detail_id' => $penerimaanDetail->id,
                'tax_id' => $penerimaan->tax_category_id,
                'qty' => $detail['qty'],
                'expired_date' => $expiredDate,
                'status_ed' => 'aman', // Will be calculated by model mutator
                'catatan' => null,
                'source_type' => 'penerimaan',
                'source_id' => $penerimaan->id,
                'source_date' => $penerimaan->tanggal_penerimaan,
            ]);
        }
        else {
            // If ED format is not recognized, log warning but don't create WarehouseStock
            $this->command->warn("Unrecognized ED format: '{$ed}' for product ID {$detail['product_id']}. Skipping WarehouseStock creation.");
        }
    }
}