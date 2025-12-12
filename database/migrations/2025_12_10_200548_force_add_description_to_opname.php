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
            // Kita cek dulu biar gak error kalau ternyata kolomnya udah ada
            if (!Schema::hasColumn('stock_opname_details', 'description')) {
                $table->string('description')->nullable()->after('physical_qty');
            }
         });
    }

    public function down(): void
    {
        Schema::table('stock_opname_details', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
