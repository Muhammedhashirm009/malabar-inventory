<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed initial default configurations
        $defaults = [
            ['key' => 'shop_name', 'value' => 'Malabar Inventory'],
            ['key' => 'shop_address', 'value' => 'Main Road, Malabar, Kerala'],
            ['key' => 'shop_phone', 'value' => '+91 98765 43210'],
            ['key' => 'shop_email', 'value' => 'info@malabarinventory.com'],
            ['key' => 'shop_gstin', 'value' => ''],
            ['key' => 'sale_invoice_prefix', 'value' => 'SAL'],
            ['key' => 'sale_invoice_suffix', 'value' => ''],
            ['key' => 'purchase_invoice_prefix', 'value' => 'PUR'],
            ['key' => 'purchase_invoice_suffix', 'value' => ''],
        ];

        foreach ($defaults as $default) {
            DB::table('settings')->insert(array_merge($default, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
