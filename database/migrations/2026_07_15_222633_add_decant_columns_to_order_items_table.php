<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('decant_id')->nullable()->after('product_id')->constrained('decants')->nullOnDelete();
            $table->unsignedInteger('decant_ml')->nullable()->after('decant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decant_id');
            $table->dropColumn('decant_ml');
        });
    }
};
