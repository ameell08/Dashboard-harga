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
        if (!Schema::hasTable('detik_tfidf_harian')) {
            Schema::create('detik_tfidf_harian', function (Blueprint $table) {
                $table->id();
                $table->date('tanggal_terbit');
                $table->string('jenis_komoditas', 100);
                $table->string('komoditas', 150)->nullable();
                $table->string('term', 150);
                $table->decimal('total_tfidf', 12, 6)->default(0);
                $table->integer('jumlah_berita')->default(0);
                $table->decimal('rata_rata_tfidf', 12, 6)->default(0);
                $table->integer('rank_term')->default(1);
                $table->timestamps();

                $table->index(['tanggal_terbit', 'jenis_komoditas', 'rank_term'], 'idx_detik_tfidf_filter');
                $table->index('term', 'idx_detik_tfidf_term');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detik_tfidf_harian');
    }
};
