<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;

    /**
     * Override RefreshDatabase jika ada test yang menggunakannya.
     * Database sudah di-setup dari SQL dump — tidak perlu refresh.
     */
    protected function refreshDatabase()
    {
        // Do nothing — database sudah siap dari dump
    }

    /**
     * Set up main category session. Mengambil kategori aktif pertama dari database.
     */
    protected function setMainCategorySkincare(): void
    {
        $mainCategory = \App\Models\MainCategory::where('name', 'Kosmetik')->orWhere('name', 'SKINCARE')->first() 
            ?? \App\Models\MainCategory::where('name', '!=', 'Kopi')->first()
            ?? \App\Models\MainCategory::where('is_active', true)->first();
        if ($mainCategory) {
            session(['main_category_id' => $mainCategory->id]);
            session(['main_category_name' => $mainCategory->name]);
        }
    }

    /**
     * Get current main category ID from session or first active.
     */
    protected function getMainCategoryId(): int
    {
        if ($mainCategoryId = session('main_category_id')) {
            return $mainCategoryId;
        }
        $mainCategory = \App\Models\MainCategory::where('is_active', true)->first();
        return $mainCategory ? $mainCategory->id : 1;
    }

    /**
     * Create a superadmin user and authenticate, with main category.
     */
    protected function loginAsSuperadmin(): \App\Models\User
    {
        $user = \App\Models\User::where('email', 'superadmin@example.com')->first();
        if (!$user) {
            $user = \App\Models\User::factory()->create([
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'password' => bcrypt('password'),
                'role_id' => 1,
                'is_active' => true,
            ]);
        }

        $this->actingAs($user);
        $this->setMainCategorySkincare();

        return $user;
    }

    /**
     * Create a regular admin user and authenticate, with main category.
     */
    protected function loginAsAdmin(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create([
            'username' => 'admin_test_' . uniqid(),
            'email' => 'admin_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'role_id' => 2,
            'is_active' => true,
        ]);

        $permissions = \App\Models\Permission::whereIn('name', [
            'warehouse.view', 'warehouse.create', 'warehouse.edit',
        ])->get();
        $user->role->permissions()->syncWithoutDetaching($permissions->pluck('id')->toArray());

        $this->actingAs($user);
        $this->setMainCategorySkincare();

        return $user;
    }

    // ==================== TEST HELPERS ====================

    /**
     * Create a test product with full hierarchy (all NOT NULL fields filled).
     */
    protected function createTestProduct(array $overrides = []): \App\Models\Product
    {
        $mainCategory = \App\Models\MainCategory::where('is_active', true)->first()
            ?? \App\Models\MainCategory::factory()->create(['name' => 'SKINCARE', 'is_active' => true]);

        $brand = \App\Models\Brand::firstOrCreate(
            ['name' => 'Test Brand', 'main_category_id' => $mainCategory->id],
            ['is_active' => true]
        );
        $subBrand = \App\Models\SubBrand::firstOrCreate(
            ['name' => 'Test SubBrand', 'brand_id' => $brand->id],
            ['is_active' => true]
        );
        $category = \App\Models\ProductCategory::firstOrCreate(
            ['name' => 'Test Category', 'sub_brand_id' => $subBrand->id],
            ['is_active' => true]
        );
        $type = \App\Models\ProductType::firstOrCreate(
            ['name' => 'Test Type', 'product_category_id' => $category->id],
            ['is_active' => true]
        );
        $size = \App\Models\ProductSize::firstOrCreate(
            ['name' => 'Test Size', 'product_type_id' => $type->id],
            ['is_active' => true]
        );

        return \App\Models\Product::create(array_merge([
            'name' => 'Test Product ' . uniqid(),
            'main_category_id' => $mainCategory->id,
            'brand_id' => $brand->id,
            'sub_brand_id' => $subBrand->id,
            'product_category_id' => $category->id,
            'product_type_id' => $type->id,
            'product_size_id' => $size->id,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Create test warehouse stock with product + lokasi.
     */
    protected function createTestWarehouseStock(array $overrides = []): \App\Models\WarehouseStock
    {
        $product = $this->createTestProduct();
        $lokasi = \App\Models\Lokasi::firstOrCreate(
            ['kode' => 'GDG_A'],
            ['nama' => 'Gudang A', 'deskripsi' => '']
        );
        $taxCategory = \App\Models\TaxCategory::where('is_active', true)->first()
            ?? \App\Models\TaxCategory::create([
                'name' => 'NONPKP',
                'main_category_id' => session('main_category_id', 1),
                'tax_percentage' => 0,
                'is_active' => true,
            ]);

        return \App\Models\WarehouseStock::create(array_merge([
            'product_id' => $product->id,
            'lokasi_id' => $lokasi->id,
            'tax_id' => $taxCategory->id,
            'qty' => 100,
            'source_type' => 'penerimaan',
            'source_id' => 1,
        ], $overrides));
    }

    /**
     * Create test offline sale item with warehouse_stock_id filled.
     */
    protected function createTestOfflineSaleItem(int $offlineSaleId, ?\App\Models\WarehouseStock $stock = null): \App\Models\OfflineSaleItem
    {
        if (!$stock) {
            $stock = $this->createTestWarehouseStock();
        }

        return \App\Models\OfflineSaleItem::create([
            'offline_sale_id' => $offlineSaleId,
            'product_id' => $stock->product_id,
            'warehouse_stock_id' => $stock->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
        ]);
    }
}
