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
        // 👇 CEK JUGA BUAT ITEM
        if (!Schema::hasColumn('items', 'avg_cost')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('avg_cost', 15, 2)->default(0)->after('min_stock');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('avg_cost');
        });
    }
};
