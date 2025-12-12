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
        Schema::table('items', function (Blueprint $table) {
            // 1. Hapus kolom lama
            $table->dropColumn('unit');
            
            // 2. Ganti jadi relasi ke tabel units
            // Kita bikin nullable dulu biar data lama gak error
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            //
        });
    }
};
