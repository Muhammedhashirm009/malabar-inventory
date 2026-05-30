<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->unique();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('mrp', 10, 2)->default(0);
            $table->decimal('getting_rate', 10, 2)->default(0);
            $table->decimal('sale_rate', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
