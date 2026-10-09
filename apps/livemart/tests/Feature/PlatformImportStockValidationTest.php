<?php

namespace Tests\Feature;

use App\Models\MappingBarang;
use App\Models\PlatformProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseStock;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PlatformImportStockValidationTest extends TestCase
{
    protected $user;
    protected $product;
    protected $stockRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMainCategorySkincare();
        $this->user = $this->loginAsSuperadmin();

        $sample = Product::first();
        $this->product = Product::create([
            'name' => 'Auto Test Stock Shortage Product',
            'sku' => 'TEST-SHORT-' . uniqid(),
            'main_category_id' => $sample->main_category_id,
            'tax_category_id' => $sample->tax_category_id,
            'brand_id' => $sample->brand_id,
            'sub_brand_id' => $sample->sub_brand_id,
            'product_category_id' => $sample->product_category_id,
            'product_type_id' => $sample->product_type_id,
            'product_size_id' => $sample->product_size_id,
            'product_variant_id' => $sample->product_variant_id,
            'is_active' => 1,
        ]);

        // Berikan stok hanya 3 pcs
        $this->stockRecord = WarehouseStock::create([
            'product_id' => $this->product->id,
            'lokasi_id' => 1,
            'tax_id' => 1,
            'qty' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper to create Excel file requesting given quantity
     */
    protected function createExcelFile(string $productName, int $requestedQty): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['NOMOR PESANAN', 'NOMOR RESI', 'HARI', 'STATUS HARI', 'TANGGAL', 'NAMA PRODUK', 'VARIASI', 'QTY', 'HARGA SETELAH DISKON'];
        foreach ($headers as $idx => $h) {
            $sheet->setCellValueByColumnAndRow($idx + 1, 1, $h);
        }

        $sheet->setCellValueByColumnAndRow(1, 2, 'ORD-SHORTAGE-' . uniqid());
        $sheet->setCellValueByColumnAndRow(2, 2, 'RESI-SHORTAGE-' . uniqid());
        $sheet->setCellValueByColumnAndRow(3, 2, 'Senin');
        $sheet->setCellValueByColumnAndRow(4, 2, 'Normal');
        $sheet->setCellValueByColumnAndRow(5, 2, '2026-10-01');
        $sheet->setCellValueByColumnAndRow(6, 2, $productName);
        $sheet->setCellValueByColumnAndRow(7, 2, '');
        $sheet->setCellValueByColumnAndRow(8, 2, $requestedQty);
        $sheet->setCellValueByColumnAndRow(9, 2, 50000);

        $tempFile = tempnam(sys_get_temp_dir(), 'stock_val_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempFile);

        return $tempFile;
    }

    /**
     * Test platform preview import with insufficient stock
     *
     * @dataProvider platformProvider
     */
    public function test_platform_preview_detects_stock_shortage_and_blocks_proceed(
        int $platformId,
        string $controllerClass,
        string $routeUrl,
        string $expectedView
    ) {
        $platformProductName = "Platform Item P{$platformId}";

        $platformProduct = PlatformProduct::create([
            'platform_id' => $platformId,
            'platform_product_name' => $platformProductName,
            'variant' => '',
            'sku' => 'PL-SKU-' . $platformId . '-' . uniqid(),
            'price' => 50000,
        ]);

        $mapping = MappingBarang::create([
            'platform_product_id' => $platformProduct->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'is_active' => true,
        ]);

        // Minta 10 pcs, stok hanya ada 3 pcs (Kekurangan = 7 pcs)
        $excelPath = $this->createExcelFile($platformProductName, 10);
        $uploadedFile = new UploadedFile(
            $excelPath,
            'order_import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->withSession([
                'main_category_id' => $this->getMainCategoryId(),
                'main_category_name' => 'Kosmetik',
            ])
            ->post($routeUrl, [
                'excel_file' => $uploadedFile,
            ]);

        // 1. HTTP 200 OK
        $response->assertStatus(200);

        // 2. View yang di-render sesuai
        $response->assertViewIs($expectedView);

        // 3. canProceed bernilai false
        $response->assertViewHas('canProceed', false);

        // 4. Session menyimpan insufficient_stock_products
        $this->assertTrue(session()->has('insufficient_stock_products'));
        $sessionInsufficient = session('insufficient_stock_products');
        $this->assertNotEmpty($sessionInsufficient);

        // 5. Data kekurangan stok akurat (required: 10, available: 3, shortage: 7)
        $viewInsufficient = $response->viewData('insufficientStockProducts');
        $this->assertNotEmpty($viewInsufficient);
        $issue = $viewInsufficient[0];

        $required = $issue['total_required'] ?? $issue['required_qty'] ?? 0;
        $available = (int) $issue['available_qty'];
        $shortage = (int) $issue['shortage'];

        $this->assertEquals(10, $required, "Required quantity harus 10");
        $this->assertEquals(3, $available, "Available quantity harus 3");
        $this->assertEquals(7, $shortage, "Shortage quantity harus 7");

        // 6. Teks peringatan stok kurang dan tabel kekurangan ada di HTML preview
        $response->assertSee('Stok Tidak Mencukupi');
        $response->assertSee('Silakan tambahkan stok terlebih dahulu sebelum melanjutkan import');
        $response->assertSee($this->product->name);

        // 7. Tombol simpan/proses import tidak boleh muncul jika stok kurang
        $content = $response->getContent();
        $this->assertStringNotContainsString('type="submit" class="btn btn-success"', $content);
        $this->assertTrue(
            str_contains($content, 'Import Tidak Dapat Dilanjutkan') || str_contains($content, 'Stok tidak mencukupi. Import tidak dapat dilanjutkan'),
            'Halaman harus menampilkan pesan blokir import'
        );

        @unlink($excelPath);
    }

    public static function platformProvider(): array
    {
        return [
            'Shopee Lamourad' => [
                1,
                \App\Http\Controllers\ShopeeController::class,
                '/sales/shopee/preview-import',
                'sales.shopee.preview-import',
            ],
            'Shopee Liefmarket' => [
                2,
                \App\Http\Controllers\Shopee2Controller::class,
                '/sales/shopee2/preview-import',
                'sales.shopee2.preview-import',
            ],
            'Tiktok Lamourad' => [
                3,
                \App\Http\Controllers\TiktokController::class,
                '/sales/tiktok/preview-import',
                'sales.tiktok.preview-import',
            ],
            'Tiktok Liefmarket' => [
                4,
                \App\Http\Controllers\Tiktok2Controller::class,
                '/sales/tiktok2/preview-import',
                'sales.tiktok2.preview-import',
            ],
        ];
    }
}
