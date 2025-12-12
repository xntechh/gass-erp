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
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            
            // Kunci: Stok itu milik Gudang apa & Barang apa
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            
            // Jumlah Stok
            $table->integer('quantity')->default(0);
            
            // Lokasi Rak (Opsional, user bisa isi manual nanti)
            $table->string('rack_location')->nullable(); 

            $table->timestamps();

            // ATURAN SAKTI: Cegah duplikasi. 
            // Item A di Gudang 1 gak boleh dicatat 2 kali.
            $table->unique(['warehouse_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};
