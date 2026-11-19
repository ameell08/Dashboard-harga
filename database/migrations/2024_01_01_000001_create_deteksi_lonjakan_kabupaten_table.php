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
        if (!Schema::hasTable('deteksi_lonjakan_kabupaten')) {
            Schema::create('deteksi_lonjakan_kabupaten', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->string('komoditas_nama', 150);
                $table->string('kabupaten', 150);
                $table->decimal('rata_kabupaten', 15, 2)->default(0);
                $table->decimal('selisih', 15, 2)->default(0);
                $table->decimal('median_x_hari', 15, 2)->nullable();
                $table->decimal('mad_asli', 15, 2)->nullable();
                $table->decimal('mad_minimum', 15, 2)->nullable();
                $table->decimal('mad_x_hari', 15, 2)->nullable();
                $table->decimal('k_mad', 15, 2)->nullable();
                $table->decimal('batas_atas', 15, 2)->nullable();
                $table->decimal('batas_bawah', 15, 2)->nullable();
                $table->string('status_lonjakan', 50)->nullable();
                $table->timestamps();

                $table->index(['tanggal', 'komoditas_nama'], 'idx_dlk_tanggal_komoditas');
                $table->index(['kabupaten', 'tanggal'], 'idx_dlk_kabupaten_tanggal');
                $table->index('status_lonjakan', 'idx_dlk_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deteksi_lonjakan_kabupaten');
    }
};
