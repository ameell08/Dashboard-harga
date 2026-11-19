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
        if (!Schema::hasTable('x_clean')) {
            Schema::create('x_clean', function (Blueprint $table) {
                $table->id();
                $table->string('id_x', 100)->nullable();
                $table->date('tanggal');
                $table->text('body')->nullable();
                $table->text('text_clean')->nullable();
                $table->string('komoditas', 150)->nullable();
                $table->string('jenis_komoditas', 100)->nullable();
                $table->string('kategori', 100)->nullable();
                $table->string('cakupan_cuitan', 150)->nullable();
                $table->decimal('skor_relevansi', 8, 4)->nullable();
                $table->string('tingkat_relevansi', 20)->nullable();
                $table->timestamps();

                $table->index(['tanggal', 'tingkat_relevansi'], 'idx_x_tgl_rel');
                $table->index('jenis_komoditas', 'idx_x_jenis_komoditas');
                $table->index('komoditas', 'idx_x_komoditas');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('x_clean');
    }
};
