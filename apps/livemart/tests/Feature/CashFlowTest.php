<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ShopeeFinancialTransaction;
use App\Models\Platform;
use App\Models\MainCategory;

/**
 * Feature Test: Cash Flow (Arus Kas) & Finance Import pages
 */
class CashFlowTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MainCategorySeeder::class);
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\SuperadminRoleSeeder::class);

        $skincare = MainCategory::where('name', 'SKINCARE')->first();
        session(['main_category_id' => $skincare->id]);
        $this->loginAsSuperadmin();
    }

    /** @test */
    public function arus_kas_shopee_index_accessible()
    {
        $response = $this->get(route('finance.aruskasshopee.index'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function arus_kas_shopee_import_form_accessible()
    {
        $response = $this->get(route('finance.aruskasshopee.import'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function arus_kas_tiktok_index_accessible()
    {
        $response = $this->get(route('finance.aruskastiktok.index'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function arus_kas_tiktok_import_form_accessible()
    {
        $response = $this->get(route('finance.aruskastiktok.import'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function arus_kas_shopee2_index_accessible()
    {
        $response = $this->get(route('finance.aruskasshopee2.index'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function arus_kas_tiktok2_index_accessible()
    {
        $response = $this->get(route('finance.aruskastiktok2.index'));
        $this->assertContains($response->status(), [200, 302]);
    }

    /** @test */
    public function finance_shopee_import_form_accessible()
    {
        $response = $this->get(route('finance.shopee.import'));
        $response->assertStatus(200);
    }

    /** @test */
    public function finance_shopee_manual_form_accessible()
    {
        $response = $this->get(route('finance.shopee.manual'));
        $response->assertStatus(200);
    }

    /** @test */
    public function finance_tiktok_import_form_accessible()
    {
        $response = $this->get(route('finance.tiktok.import'));
        $response->assertStatus(200);
    }

    /** @test */
    public function finance_tiktok_manual_form_accessible()
    {
        $response = $this->get(route('finance.tiktok.manual'));
        $response->assertStatus(200);
    }

    /** @test */
    public function finance_shopee_export_pdf_accessible()
    {
        $response = $this->get(route('finance.shopee.export.pdf'));
        $response->assertStatus(200);
    }

    /** @test */
    public function finance_tiktok_cash_flow_export_accessible()
    {
        $response = $this->get(route('finance.tiktok.export.cash-flow'));
        $response->assertStatus(200);
    }

    /** @test */
    public function guest_blocked()
    {
        auth()->logout();
        $this->get(route('finance.aruskasshopee.index'))->assertRedirect(route('login'));
    }
}
