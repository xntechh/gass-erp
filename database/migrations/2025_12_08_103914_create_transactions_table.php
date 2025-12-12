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
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                
                $table->enum('type', ['IN', 'OUT']); // Masuk atau Keluar
                $table->date('trx_date'); // Tanggal Transaksi
                $table->string('code')->unique(); // No Bon (TRX-001)
                $table->text('notes')->nullable();
                
                // Status: DRAFT (belum ngurangin stok) / APPROVED (udah ngurangin)
                $table->enum('status', ['DRAFT', 'APPROVED'])->default('DRAFT');
                
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
