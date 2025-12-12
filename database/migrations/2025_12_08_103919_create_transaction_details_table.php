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
            Schema::create('transaction_details', function (Blueprint $table) {
                $table->id();
                // Nempel ke Header Transaksi
                $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
                
                // Barang apa yg dimutasi?
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                
                $table->integer('quantity');
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};
