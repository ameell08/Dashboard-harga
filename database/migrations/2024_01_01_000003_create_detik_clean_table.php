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
        if (!Schema::hasTable('detik_clean')) {
            Schema::create('detik_clean', function (Blueprint $table) {
                $table->id();
                $table->string('sumber', 100)->nullable();
                $table->text('judul')->nullable();
                $table->longText('clean_text')->nullable();
                $table->date('tanggal_terbit');
                $table->string('komoditas', 150)->nullable();
                $table->string('jenis_komoditas', 100)->nullable();
                $table->string('kategori', 100)->nullable();
                $table->string('cakupan_berita', 150)->nullable();
                $table->decimal('skor_relevansi', 8, 4)->nullable();
                $table->string('tingkat_relevansi', 20)->nullable();
                $table->text('url')->nullable();
                $table->timestamps();

                $table->index(['tanggal_terbit', 'tingkat_relevansi'], 'idx_detik_tgl_rel');
                $table->index('jenis_komoditas', 'idx_detik_jenis_komoditas');
                $table->index('komoditas', 'idx_detik_komoditas');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detik_clean');
    }
};
