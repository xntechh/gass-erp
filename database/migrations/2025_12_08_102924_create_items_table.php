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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            // Relasi ke Kategori
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            
            $table->string('name');
            $table->string('code')->unique(); // SKU / Kode Barang
            $table->string('unit'); // Satuan (Pcs, Kg, Box)
            
            // Buat alert kalau stok menipis (Fitur Dashboard poin 1)
            $table->integer('min_stock')->default(10); 
            
            // Harga rata-rata (disimpan biar gak ribet hitung mundur)
            $table->decimal('avg_cost', 15, 2)->default(0); 
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
