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
        if (!Schema::hasTable('cuaca_raw')) {
            Schema::create('cuaca_raw', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal');
                $table->string('kabupaten_kota', 150);
                $table->decimal('suhu_rata_rata', 5, 2)->nullable();
                $table->decimal('suhu_maksimum', 5, 2)->nullable();
                $table->decimal('suhu_minimum', 5, 2)->nullable();
                $table->decimal('kelembapan_rata_rata', 5, 2)->nullable();
                $table->decimal('curah_hujan_mm', 8, 2)->nullable();
                $table->decimal('durasi_hujan_jam', 5, 2)->nullable();
                $table->decimal('kecepatan_angin_maks_kmh', 5, 2)->nullable();
                $table->timestamps();

                $table->index(['tanggal', 'kabupaten_kota'], 'idx_cuaca_tanggal_kab');
                $table->index('tanggal', 'idx_cuaca_tanggal');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuaca_raw');
    }
};
