<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Customer;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_accessible(): void
    {
        $response = $this->get('/settings');
        $response->assertStatus(200);
        $response->assertSee('Shop & Invoice Settings', false);
    }

    public function test_settings_can_be_updated(): void
    {
        $response = $this->post('/settings', [
            'shop_name'               => 'LedgerX Solutions',
            'shop_address'            => '123 Tech Park, India',
            'shop_phone'              => '+91 99999 88888',
            'shop_email'              => 'admin@ledgerx.com',
            'shop_gstin'              => 'GST123456789',
            'sale_invoice_prefix'     => 'INV',
            'sale_invoice_suffix'     => '-LTD',
            'purchase_invoice_prefix' => 'PO',
            'purchase_invoice_suffix' => '-CORP',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('settings', [
            'key'   => 'shop_name',
            'value' => 'LedgerX Solutions',
        ]);

        $this->assertDatabaseHas('settings', [
            'key'   => 'sale_invoice_prefix',
            'value' => 'INV',
        ]);
        
        $this->assertDatabaseHas('settings', [
            'key'   => 'sale_invoice_suffix',
            'value' => '-LTD',
        ]);
    }

    public function test_invoice_generator_respects_custom_settings(): void
    {
        // Update database configurations
        Setting::updateOrCreate(['key' => 'sale_invoice_prefix'], ['value' => 'INV']);
        Setting::updateOrCreate(['key' => 'sale_invoice_suffix'], ['value' => '-LTD']);

        // Set configuration keys to simulate middleware/AppServiceProvider boot load
        config(['settings.sale_invoice_prefix' => 'INV']);
        config(['settings.sale_invoice_suffix' => '-LTD']);

        $saleService = app(SaleService::class);
        $invoiceNumber = $saleService->generateInvoiceNumber();

        $today = now()->format('Ymd');
        $this->assertEquals("INV-{$today}-001-LTD", $invoiceNumber);
    }
}
