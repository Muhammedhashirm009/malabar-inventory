<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes to ledger tables for faster balance lookups.
     * These indexes speed up the frequently-used ORDER BY transaction_date DESC, id DESC queries.
     */
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->index(['customer_id', 'transaction_date', 'id'], 'cl_customer_date_id_idx');
            $table->index(['customer_id', 'reference_type', 'reference_id'], 'cl_customer_ref_idx');
        });

        Schema::table('supplier_ledger', function (Blueprint $table) {
            $table->index(['supplier_id', 'transaction_date', 'id'], 'sl_supplier_date_id_idx');
            $table->index(['supplier_id', 'reference_type', 'reference_id'], 'sl_supplier_ref_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropIndex('cl_customer_date_id_idx');
            $table->dropIndex('cl_customer_ref_idx');
        });

        Schema::table('supplier_ledger', function (Blueprint $table) {
            $table->dropIndex('sl_supplier_date_id_idx');
            $table->dropIndex('sl_supplier_ref_idx');
        });
    }
};
