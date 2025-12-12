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
        // 👇 CEK DULU: Kalau kolom 'price' BELUM ADA, baru buat.
        if (!Schema::hasColumn('transaction_details', 'price')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->decimal('price', 15, 2)->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
