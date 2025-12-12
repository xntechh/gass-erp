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
        Schema::table('stock_opname_details', function (Blueprint $table) {
            // Kita tambah kolom baru yg boleh kosong (nullable)
            $table->string('description')->nullable()->after('physical_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_details', function (Blueprint $table) {
            //
        });
    }
};
