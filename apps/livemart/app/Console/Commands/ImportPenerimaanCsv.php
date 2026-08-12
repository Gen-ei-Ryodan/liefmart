<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Shared\Models\PaymentMethod;
use App\Models\Penerimaan;
use Shared\Helpers\NumberFormatter;

class ImportPenerimaanCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:penerimaan {type : PKP or NONPKP} {file : Path to CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Penerimaan data from CSV file';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $type = $this->argument('type');
        $filePath = $this->argument('file');

        if (!in_array($type, ['PKP', 'NONPKP'])) {
            $this->error('Type must be either PKP or NONPKP');
            return;
        }

        if (!file_exists($filePath)) {
            $this->error('File not found');
            return;
        }

        $taxCategoryId = $type === 'PKP' ? 3 : 4;
        $paymentMethod = PaymentMethod::find(1);
        
        if (!$paymentMethod) {
            $this->error('Payment method with ID 1 not found');
            return;
        }

        $csv = array_map('str_getcsv', file($filePath));
        $header = array_shift($csv);

        if ($header !== ['NAMA BARANG', 'QTY', 'HARGA']) {
            $this->error('CSV format is incorrect. Expected headers: NAMA BARANG, QTY, HARGA');
            return;
        }

        $totalHarga = 0;
        $details = [];

        foreach ($csv as $row) {
            $productName = trim($row[0]);
            $qty = NumberFormatter::parseNumericValue(trim($row[1]));
            $harga = NumberFormatter::parseNumericValue(trim($row[2]));

            $product = Product::where('name', $productName)->first();

            if (!$product) {
                $this->warn("Product not found: {$productName}");
                continue;
            }

            $subtotal = NumberFormatter::calculateSubtotal($harga, $qty);
            $totalHarga += $subtotal;

            $details[] = [
                'product_id' => $product->id,
                'qty' => $qty,
                'satuan_id' => 1,
                'harga_hpp' => $harga,
                'subtotal' => $subtotal,
            ];
        }

        if (empty($details)) {
            $this->error('No valid products found in CSV');
            return;
        }

        $nomorPo = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $tanggalPenerimaan = now();
        $tanggalJatuhTempo = $paymentMethod->calculateDueDate($tanggalPenerimaan);

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
            $penerimaan->details()->create([
                'penerimaan_id' => $penerimaan->id,
                'product_id' => $detail['product_id'],
                'qty' => $detail['qty'],
                'satuan_id' => $detail['satuan_id'],
                'harga_hpp' => $detail['harga_hpp'],
                'diskon_persen_1' => null,
                'diskon_nominal_1' => null,
                'diskon_persen_2' => null,
                'diskon_nominal_2' => null,
                'diskon_persen_3' => null,
                'diskon_nominal_3' => null,
                'diskon_persen_4' => null,
                'diskon_nominal_4' => null,
                'diskon_persen_5' => null,
                'diskon_nominal_5' => null,
                'is_free' => 0,
                'subtotal' => $detail['subtotal'],
                'catatan' => null,
            ]);
        }

        $this->info("Successfully imported {$type} penerimaan with {$penerimaan->details()->count()} items");
    }
}
