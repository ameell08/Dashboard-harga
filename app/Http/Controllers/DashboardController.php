<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Menampilkan halaman Dashboard Eksekutif dengan data aktual dari PostgreSQL (database: kestra).
     */
    public function index(Request $request)
    {
        $conn = DB::connection('pgsql');

        // 1. Ambil daftar komoditas aktual dari deteksi_lonjakan_kabupaten (tanpa Bawang Merah & Bawang Putih)
        $komoditasRows = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select('komoditas_nama')
            ->whereNotIn('komoditas_nama', ['Bawang Merah', 'Bawang Putih Sinco/Honan'])
            ->whereRaw("komoditas_nama NOT ILIKE '%bawang%'")
            ->distinct()
            ->orderBy('komoditas_nama')
            ->get();

        $daftarKomoditas = $komoditasRows->pluck('komoditas_nama')->values()->all();

        // 2. Ambil daftar kabupaten/kota aktual dari deteksi_lonjakan_kabupaten
        $kabupatenRows = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select('kabupaten')
            ->distinct()
            ->orderBy('kabupaten')
            ->get();

        $daftarKabupaten = $kabupatenRows->pluck('kabupaten')->values()->all();

        // 3. Ambil rentang tanggal minimum dan maksimum aktual dari deteksi_lonjakan_kabupaten
        $rentangDb = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->selectRaw('MIN(tanggal) AS min_tanggal, MAX(tanggal) AS max_tanggal')
            ->first();

        $minTanggalDb = $rentangDb->min_tanggal ?? '2026-01-01';
        $maxTanggalDb = $rentangDb->max_tanggal ?? '2026-07-31';

        // 4. Validasi input filter dari query string
        $validator = Validator::make($request->query(), [
            'komoditas'       => ['nullable', 'string', 'max:100'],
            'kabupaten'       => ['nullable', 'string', 'max:100'],
            'tanggal_mulai'   => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $validated = $validator->fails() ? [] : $validator->validated();

        // Default komoditas: Cabe Rawit Merah jika tersedia, atau komoditas pertama
        $defaultKomoditas = in_array('Cabe Rawit Merah', $daftarKomoditas, true)
            ? 'Cabe Rawit Merah'
            : ($daftarKomoditas[0] ?? 'Beras Medium');

        $selectedKomoditas = $validated['komoditas'] ?? $defaultKomoditas;
        if (!in_array($selectedKomoditas, $daftarKomoditas, true)) {
            $selectedKomoditas = $defaultKomoditas;
        }

        // Default kabupaten: 'ALL' (Semua Kabupaten/Kota) atau Kabupaten/Kota spesifik
        $selectedKabupaten = $validated['kabupaten'] ?? 'ALL';
        if ($selectedKabupaten !== 'ALL' && !in_array($selectedKabupaten, $daftarKabupaten, true)) {
            $selectedKabupaten = 'ALL';
        }

        // Default rentang tanggal
        $tanggalMulai = $validated['tanggal_mulai'] ?? $minTanggalDb;
        $tanggalSelesai = $validated['tanggal_selesai'] ?? $maxTanggalDb;

        if ($tanggalMulai < $minTanggalDb) {
            $tanggalMulai = $minTanggalDb;
        }
        if ($tanggalSelesai > $maxTanggalDb) {
            $tanggalSelesai = $maxTanggalDb;
        }
        if ($tanggalMulai > $tanggalSelesai) {
            [$tanggalMulai, $tanggalSelesai] = [$tanggalSelesai, $tanggalMulai];
        }

        // 5. Query data deret waktu harga & lonjakan dari public.deteksi_lonjakan_kabupaten
        $sumberTabel = 'public.deteksi_lonjakan_kabupaten';
        $satuanKomoditas = 'kg';

        if ($selectedKabupaten !== 'ALL') {
            $seriesData = $conn->table('public.deteksi_lonjakan_kabupaten')
                ->select([
                    'tanggal',
                    'komoditas_nama',
                    'kabupaten',
                    DB::raw('ROUND(rata_kabupaten, 2) AS harga'),
                    DB::raw('ROUND(selisih, 2) AS selisih'),
                    DB::raw('ROUND(median_x_hari, 2) AS median_x_hari'),
                    DB::raw('ROUND(mad_asli, 2) AS mad_asli'),
                    DB::raw('ROUND(mad_minimum, 2) AS mad_minimum'),
                    DB::raw('ROUND(mad_x_hari, 2) AS mad_x_hari'),   // MAD dipakai
                    DB::raw('ROUND(k_mad, 2) AS k_mad'),
                    DB::raw('ROUND(batas_atas, 2) AS batas_atas'),   // batas dalam satuan SELISIH
                    DB::raw('ROUND(batas_bawah, 2) AS batas_bawah'),
                    // batas dalam satuan HARGA = harga hari sebelumnya + batas selisih
                    DB::raw('ROUND(rata_kabupaten - selisih + batas_atas, 2) AS harga_batas_atas'),
                    DB::raw('ROUND(rata_kabupaten - selisih + batas_bawah, 2) AS harga_batas_bawah'),
                    'status_lonjakan',
                    DB::raw('1 AS jml_kab'),
                    DB::raw("CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END AS jml_kab_naik"),
                    DB::raw("CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END AS jml_kab_turun"),
                ])
                ->where('komoditas_nama', '=', $selectedKomoditas)
                ->where('kabupaten', '=', $selectedKabupaten)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
                ->orderBy('tanggal', 'asc')
                ->get();
        } else {
            // Agregasi harian dari public.deteksi_lonjakan_kabupaten lintas 38 Kabupaten/Kota
            $seriesData = $conn->table('public.deteksi_lonjakan_kabupaten')
                ->select([
                    'tanggal',
                    'komoditas_nama',
                    DB::raw("'Semua Kabupaten/Kota (Rata-rata)' AS kabupaten"),
                    DB::raw('ROUND(AVG(rata_kabupaten), 2) AS harga'),
                    DB::raw('ROUND(AVG(selisih), 2) AS selisih'),
                    DB::raw('ROUND(AVG(median_x_hari), 2) AS median_x_hari'),
                    DB::raw('ROUND(AVG(mad_asli), 2) AS mad_asli'),
                    DB::raw('ROUND(AVG(mad_minimum), 2) AS mad_minimum'),
                    DB::raw('ROUND(AVG(mad_x_hari), 2) AS mad_x_hari'),
                    DB::raw('ROUND(AVG(k_mad), 2) AS k_mad'),
                    DB::raw('ROUND(AVG(batas_atas), 2) AS batas_atas'),
                    DB::raw('ROUND(AVG(batas_bawah), 2) AS batas_bawah'),
                    DB::raw('ROUND(AVG(rata_kabupaten - selisih + batas_atas), 2) AS harga_batas_atas'),
                    DB::raw('ROUND(AVG(rata_kabupaten - selisih + batas_bawah), 2) AS harga_batas_bawah'),
                    DB::raw("COUNT(*) FILTER (WHERE status_lonjakan IN ('LONJAKAN_NAIK','LONJAKAN_TURUN','NORMAL')) AS jml_kab"),
                    DB::raw("SUM(CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END) AS jml_kab_naik"),
                    DB::raw("SUM(CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END) AS jml_kab_turun"),
                    DB::raw("
                        CASE
                            WHEN SUM(CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END) > 0
                                 AND SUM(CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END) >= SUM(CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END)
                                THEN 'LONJAKAN_NAIK'
                            WHEN SUM(CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END) > 0
                                THEN 'LONJAKAN_TURUN'
                            ELSE 'NORMAL'
                        END AS status_lonjakan
                    "),
                ])
                ->where('komoditas_nama', '=', $selectedKomoditas)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
                ->groupBy('tanggal', 'komoditas_nama')
                ->orderBy('tanggal', 'asc')
                ->get();
        }

        // Hitung total kejadian lonjakan aktual dari public.deteksi_lonjakan_kabupaten
        $rekapLonjakanDb = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->selectRaw("
                SUM(CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END) AS total_naik,
                SUM(CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END) AS total_turun,
                SUM(CASE WHEN status_lonjakan = 'NORMAL' THEN 1 ELSE 0 END) AS total_normal,
                SUM(CASE WHEN status_lonjakan NOT IN ('LONJAKAN_NAIK','LONJAKAN_TURUN','NORMAL')
                         OR status_lonjakan IS NULL THEN 1 ELSE 0 END) AS total_belum,
                0 AS total_mad0
            ")
            ->where('komoditas_nama', '=', $selectedKomoditas)
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->when($selectedKabupaten !== 'ALL', function ($q) use ($selectedKabupaten) {
                $q->where('kabupaten', '=', $selectedKabupaten);
            })
            ->first();

        // Daftar baris lonjakan aktual dari public.deteksi_lonjakan_kabupaten untuk keseluruhan rentang filter
        $kejadianLonjakan = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select([
                'kabupaten',
                'tanggal',
                'komoditas_nama',
                DB::raw('ROUND(rata_kabupaten, 2) AS harga'),
                DB::raw('ROUND(selisih, 2) AS selisih'),
                DB::raw('ROUND(batas_atas, 2) AS batas_atas'),
                DB::raw('ROUND(batas_bawah, 2) AS batas_bawah'),
                'status_lonjakan',
            ])
            ->where('komoditas_nama', '=', $selectedKomoditas)
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->whereIn('status_lonjakan', ['LONJAKAN_NAIK', 'LONJAKAN_TURUN'])
            ->when($selectedKabupaten !== 'ALL', function ($q) use ($selectedKabupaten) {
                $q->where('kabupaten', '=', $selectedKabupaten);
            })
            ->orderByDesc('tanggal')
            ->orderBy('kabupaten')
            ->limit(250)
            ->get();

        // 6. Hitung ringkasan KPI dari data aktual
        $ringkasan = $this->hitungRingkasan($seriesData, $rekapLonjakanDb);

        // 7. Siapkan struktur array untuk Chart.js
        $chartPayload = $this->siapkanChartPayload($seriesData);

        // 8. Informasi Rata-rata Cuaca Harian dari public.cuaca_raw
        $ringkasanCuaca = $conn->table('public.cuaca_raw')
            ->selectRaw('
                COUNT(DISTINCT tanggal) AS total_hari,
                COUNT(DISTINCT kabupaten_kota) AS total_kabupaten,
                ROUND(AVG(suhu_rata_rata)::numeric, 1) AS suhu_rata_rata,
                ROUND(AVG(suhu_maksimum)::numeric, 1) AS suhu_maksimum,
                ROUND(AVG(suhu_minimum)::numeric, 1) AS suhu_minimum,
                ROUND(AVG(kelembapan_rata_rata)::numeric, 1) AS kelembapan_rata_rata,
                ROUND(AVG(curah_hujan_mm)::numeric, 2) AS curah_hujan_mm,
                ROUND(AVG(durasi_hujan_jam)::numeric, 1) AS durasi_hujan_jam,
                ROUND(AVG(kecepatan_angin_maks_kmh)::numeric, 1) AS kecepatan_angin_kmh
            ')
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->when($selectedKabupaten !== 'ALL', function ($q) use ($selectedKabupaten) {
                $q->where('kabupaten_kota', '=', $selectedKabupaten);
            })
            ->first();

        $cuacaHarianTerbaru = $conn->table('public.cuaca_raw')
            ->select([
                'tanggal',
                DB::raw($selectedKabupaten !== 'ALL' ? 'kabupaten_kota' : "'Rata-rata Jawa Timur' AS kabupaten_kota"),
                DB::raw('ROUND(AVG(suhu_rata_rata)::numeric, 1) AS suhu_rata_rata'),
                DB::raw('ROUND(AVG(suhu_minimum)::numeric, 1) AS suhu_minimum'),
                DB::raw('ROUND(AVG(suhu_maksimum)::numeric, 1) AS suhu_maksimum'),
                DB::raw('ROUND(AVG(kelembapan_rata_rata)::numeric, 1) AS kelembapan_rata_rata'),
                DB::raw('ROUND(AVG(curah_hujan_mm)::numeric, 2) AS curah_hujan_mm'),
                DB::raw('ROUND(AVG(durasi_hujan_jam)::numeric, 1) AS durasi_hujan_jam'),
                DB::raw('ROUND(AVG(kecepatan_angin_maks_kmh)::numeric, 1) AS kecepatan_angin_kmh'),
            ])
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->when($selectedKabupaten !== 'ALL', function ($q) use ($selectedKabupaten) {
                $q->where('kabupaten_kota', '=', $selectedKabupaten);
            })
            ->groupBy('tanggal')
            ->when($selectedKabupaten !== 'ALL', function ($q) {
                $q->groupBy('kabupaten_kota');
            })
            ->orderByDesc('tanggal')
            ->get();

        // 9. Panel Hasil TF-IDF Berita & X untuk KESELURUHAN RENTANG TANGGAL filter
        [$jenisKomoditasTfidf, $slugKomoditasTfidf] = $this->mapKomoditasKeTfidf($selectedKomoditas);

        $detikTfidfHarian = $this->ambilTfidfHarian(
            $conn,
            'public.detik_tfidf_harian',
            'tanggal_terbit',
            'jumlah_berita',
            $tanggalMulai,
            $tanggalSelesai,
            $jenisKomoditasTfidf,
            $slugKomoditasTfidf
        );

        $xTfidfHarian = $this->ambilTfidfHarian(
            $conn,
            'public.x_tfidf_harian',
            'tanggal',
            'jumlah_cuitan',
            $tanggalMulai,
            $tanggalSelesai,
            $jenisKomoditasTfidf,
            $slugKomoditasTfidf
        );

        // 10. Panel Berita Clean & X Clean Tanpa Komoditas untuk KESELURUHAN RENTANG TANGGAL filter
        $totalBeritaTanpaKomoditas = $conn->table('public.detik_clean')
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal_terbit', [$tanggalMulai, $tanggalSelesai])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->count();

        $beritaCleanTanpaKomoditas = $conn->table('public.detik_clean')
            ->select([
                'id',
                'sumber',
                'judul',
                'clean_text',
                'tanggal_terbit',
                'kategori',
                'cakupan_berita',
                'skor_relevansi',
                'tingkat_relevansi',
                'url',
            ])
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal_terbit', [$tanggalMulai, $tanggalSelesai])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->orderBy('tanggal_terbit', 'asc')
            ->orderByDesc('skor_relevansi')
            ->limit(150)
            ->get();

        $totalXTanpaKomoditas = $conn->table('public.x_clean')
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->count();

        $xCleanTanpaKomoditas = $conn->table('public.x_clean')
            ->select([
                'id',
                'id_x',
                'tanggal',
                'body',
                'text_clean',
                'kategori',
                'cakupan_cuitan',
                'skor_relevansi',
                'tingkat_relevansi',
            ])
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->orderBy('tanggal', 'asc')
            ->orderByDesc('skor_relevansi')
            ->limit(150)
            ->get();

        // 11. Ringkasan Kabupaten/Kota & Lintas Komoditas pada Tanggal Terakhir
        $tanggalSnapshot = $ringkasan['tanggal_terbaru'] ?? $tanggalSelesai;
        $ringkasanKabupatenTerbaru = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select([
                'kabupaten',
                'tanggal',
                'komoditas_nama',
                DB::raw('ROUND(rata_kabupaten, 2) AS harga'),
                DB::raw('ROUND(selisih, 2) AS selisih'),
                DB::raw('ROUND(batas_atas, 2) AS batas_atas'),
                DB::raw('ROUND(batas_bawah, 2) AS batas_bawah'),
                'status_lonjakan',
            ])
            ->where('komoditas_nama', '=', $selectedKomoditas)
            ->where('tanggal', '=', $tanggalSnapshot)
            ->orderByDesc('rata_kabupaten')
            ->get();

        $ringkasanSemuaKomoditas = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select([
                'komoditas_nama',
                'tanggal',
                DB::raw('ROUND(AVG(rata_kabupaten), 0) AS rata_harga'),
                DB::raw('ROUND(MIN(rata_kabupaten), 0) AS min_harga'),
                DB::raw('ROUND(MAX(rata_kabupaten), 0) AS max_harga'),
                DB::raw('ROUND(AVG(selisih), 0) AS rata_selisih'),
                DB::raw("SUM(CASE WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1 ELSE 0 END) AS jml_lonjakan_naik"),
                DB::raw("SUM(CASE WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 1 ELSE 0 END) AS jml_lonjakan_turun"),
                DB::raw('COUNT(DISTINCT kabupaten) AS jml_kabupaten'),
            ])
            ->where('tanggal', '=', $tanggalSnapshot)
            ->whereNotIn('komoditas_nama', ['Bawang Merah', 'Bawang Putih Sinco/Honan'])
            ->whereRaw("komoditas_nama NOT ILIKE '%bawang%'")
            ->when($selectedKabupaten !== 'ALL', function ($q) use ($selectedKabupaten) {
                $q->where('kabupaten', '=', $selectedKabupaten);
            })
            ->groupBy('komoditas_nama', 'tanggal')
            ->orderBy('komoditas_nama')
            ->get();

        return view('dashboard', [
            'daftarKomoditas'           => $daftarKomoditas,
            'daftarKabupaten'           => $daftarKabupaten,
            'minTanggalDb'              => $minTanggalDb,
            'maxTanggalDb'              => $maxTanggalDb,
            'selectedKomoditas'         => $selectedKomoditas,
            'selectedKabupaten'         => $selectedKabupaten,
            'tanggalMulai'              => $tanggalMulai,
            'tanggalSelesai'            => $tanggalSelesai,
            'sumberTabel'               => $sumberTabel,
            'satuanKomoditas'           => $satuanKomoditas,
            'ringkasan'                 => $ringkasan,
            'chartPayload'              => $chartPayload,
            'seriesData'                => $seriesData,
            'kejadianLonjakan'          => $kejadianLonjakan,
            'ringkasanCuaca'            => $ringkasanCuaca,
            'cuacaHarianTerbaru'        => $cuacaHarianTerbaru,
            'jenisKomoditasTfidf'       => $jenisKomoditasTfidf,
            'slugKomoditasTfidf'        => $slugKomoditasTfidf,
            'detikTfidfHarian'          => $detikTfidfHarian,
            'xTfidfHarian'              => $xTfidfHarian,
            'totalBeritaTanpaKomoditas' => $totalBeritaTanpaKomoditas,
            'beritaCleanTanpaKomoditas' => $beritaCleanTanpaKomoditas,
            'totalXTanpaKomoditas'      => $totalXTanpaKomoditas,
            'xCleanTanpaKomoditas'      => $xCleanTanpaKomoditas,
            'tanggalSnapshot'           => $tanggalSnapshot,
            'ringkasanKabupatenTerbaru' => $ringkasanKabupatenTerbaru,
            'ringkasanSemuaKomoditas'   => $ringkasanSemuaKomoditas,
        ]);
    }

    /**
     * Endpoint JSON untuk mengambil detail lonjakan pada tanggal yang diklik user,
     * daftar berita 21 hari sebelumnya, serta data TF-IDF dan Clean yang disesuaikan dengan tanggal klik.
     */
    public function detailLonjakan(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'komoditas' => ['required', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'tanggal'   => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter tanggal atau komoditas tidak valid.',
            ], 422);
        }

        $conn = DB::connection('pgsql');
        $validated = $validator->validated();

        $komoditas = $validated['komoditas'];
        $kabupaten = $validated['kabupaten'] ?? 'ALL';
        $tanggalKlik = $validated['tanggal'];
        $tanggalMulai21 = Carbon::parse($tanggalKlik)->subDays(21)->format('Y-m-d');

        [$jenisKomoditasTfidf, $slugKomoditasTfidf] = $this->mapKomoditasKeTfidf($komoditas);

        // 1. Ambil rincian lonjakan pada tanggal yang diklik
        $detailKabupatenTanggal = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select([
                'kabupaten',
                'tanggal',
                'komoditas_nama',
                DB::raw('ROUND(rata_kabupaten, 0) AS harga'),
                DB::raw('ROUND(selisih, 0) AS selisih'),
                DB::raw('ROUND(batas_bawah, 0) AS batas_bawah'),
                DB::raw('ROUND(batas_atas, 0) AS batas_atas'),
                'status_lonjakan',
            ])
            ->where('komoditas_nama', '=', $komoditas)
            ->where('tanggal', '=', $tanggalKlik)
            ->when($kabupaten !== 'ALL', function ($q) use ($kabupaten) {
                $q->where('kabupaten', '=', $kabupaten);
            })
            ->orderByRaw("
                CASE
                    WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1
                    WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 2
                    WHEN status_lonjakan = 'NORMAL' THEN 3
                    ELSE 4
                END ASC
            ")
            ->orderByDesc(DB::raw('ABS(selisih)'))
            ->get();

        $kabupatenLonjakan = $detailKabupatenTanggal->filter(function ($r) {
            return in_array($r->status_lonjakan, ['LONJAKAN_NAIK', 'LONJAKAN_TURUN'], true);
        })->values();

        // 2. Ambil berita dalam 21 hari sebelumnya (tanggalKlik - 21 hari s/d tanggalKlik)
        // Mencakup berita komoditas terkait maupun berita umum pangan/harga yang relevan
        $berita21Hari = $conn->table('public.detik_clean')
            ->select([
                'id',
                'sumber',
                'judul',
                'clean_text',
                'tanggal_terbit',
                'komoditas',
                'jenis_komoditas',
                'kategori',
                'cakupan_berita',
                'skor_relevansi',
                'tingkat_relevansi',
                'url',
            ])
            ->whereBetween('tanggal_terbit', [$tanggalMulai21, $tanggalKlik])
            ->where(function ($q) use ($jenisKomoditasTfidf) {
                $q->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
                  ->orWhereIn('tingkat_relevansi', ['HIGH', 'MEDIUM']);
            })
            ->orderByRaw("CASE WHEN jenis_komoditas = ? THEN 0 ELSE 1 END ASC", [$jenisKomoditasTfidf])
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('skor_relevansi')
            ->limit(100)
            ->get()
            ->map(function ($row) use ($tanggalKlik) {
                $selisihHari = Carbon::parse($row->tanggal_terbit)->diffInDays(Carbon::parse($tanggalKlik));
                $row->tanggal_fmt = Carbon::parse($row->tanggal_terbit)->format('d/m/Y');
                $row->label_hari = $selisihHari === 0 ? 'Hari-H' : 'H-' . $selisihHari;
                return $row;
            });

        // 3. Ambil Cuitan X terkait dalam 21 hari sebelumnya
        $x21Hari = $conn->table('public.x_clean')
            ->select([
                'id',
                'id_x',
                'tanggal',
                'body',
                'text_clean',
                'komoditas',
                'jenis_komoditas',
                'kategori',
                'cakupan_cuitan',
                'skor_relevansi',
                'tingkat_relevansi',
            ])
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->where(function ($q) use ($jenisKomoditasTfidf, $komoditas) {
                $q->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
                  ->orWhere('komoditas', 'ILIKE', "%{$komoditas}%")
                  ->orWhereIn('tingkat_relevansi', ['HIGH', 'MEDIUM']);
            })
            ->orderByRaw("CASE WHEN jenis_komoditas = ? THEN 0 ELSE 1 END ASC", [$jenisKomoditasTfidf])
            ->orderByDesc('tanggal')
            ->orderByDesc('skor_relevansi')
            ->limit(100)
            ->get()
            ->map(function ($row) use ($tanggalKlik) {
                $selisihHari = Carbon::parse($row->tanggal)->diffInDays(Carbon::parse($tanggalKlik));
                $row->tanggal_fmt = Carbon::parse($row->tanggal)->format('d/m/Y');
                $row->label_hari = $selisihHari === 0 ? 'Hari-H' : 'H-' . $selisihHari;
                return $row;
            });

        // 4. Ambil Data TF-IDF & Data Clean yang disesuaikan dengan Tanggal yang Diklik User
        // Seluruh data dari H-21 sampai tanggal diklik dikirim tanpa batas baris, diurutkan dari tanggal terbaru
        $tfidfBeritaKlik = $conn->table('public.detik_tfidf_harian')
            ->select([
                DB::raw('tanggal_terbit AS tanggal'),
                'jenis_komoditas',
                'komoditas',
                'term',
                DB::raw('ROUND(total_tfidf::numeric, 4) AS total_tfidf'),
                DB::raw('jumlah_berita AS jumlah_dokumen'),
                DB::raw('ROUND(rata_rata_tfidf::numeric, 4) AS rata_rata_tfidf'),
                'rank_term',
            ])
            ->whereBetween('tanggal_terbit', [$tanggalMulai21, $tanggalKlik])
            ->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
            ->orderByDesc('tanggal_terbit')
            ->orderBy('rank_term', 'asc')
            ->get()
            ->map(function ($r) {
                $r->tanggal_fmt = Carbon::parse($r->tanggal)->format('d/m/Y');
                return $r;
            });

        $tfidfXKlik = $conn->table('public.x_tfidf_harian')
            ->select([
                'tanggal',
                'jenis_komoditas',
                'komoditas',
                'term',
                DB::raw('ROUND(total_tfidf::numeric, 4) AS total_tfidf'),
                DB::raw('jumlah_cuitan AS jumlah_dokumen'),
                DB::raw('ROUND(rata_rata_tfidf::numeric, 4) AS rata_rata_tfidf'),
                'rank_term',
            ])
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
            ->orderByDesc('tanggal')
            ->orderBy('rank_term', 'asc')
            ->get()
            ->map(function ($r) {
                $r->tanggal_fmt = Carbon::parse($r->tanggal)->format('d/m/Y');
                return $r;
            });

        $beritaCleanKlik = $conn->table('public.detik_clean')
            ->select([
                'id',
                'sumber',
                'judul',
                'clean_text',
                'tanggal_terbit',
                'kategori',
                'cakupan_berita',
                'skor_relevansi',
                'tingkat_relevansi',
                'url',
            ])
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal_terbit', [$tanggalMulai21, $tanggalKlik])
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('skor_relevansi')
            ->get()
            ->map(function ($r) {
                $r->tanggal_fmt = Carbon::parse($r->tanggal_terbit)->format('d/m/Y');
                return $r;
            });

        $xCleanKlik = $conn->table('public.x_clean')
            ->select([
                'id',
                'id_x',
                'tanggal',
                'body',
                'text_clean',
                'kategori',
                'cakupan_cuitan',
                'skor_relevansi',
                'tingkat_relevansi',
            ])
            ->where(function ($q) {
                $q->whereNull('komoditas')->orWhere('komoditas', '=', '');
            })
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->orderByDesc('tanggal')
            ->orderByDesc('skor_relevansi')
            ->get()
            ->map(function ($r) {
                $r->tanggal_fmt = Carbon::parse($r->tanggal)->format('d/m/Y');
                return $r;
            });

        return response()->json([
            'status'                  => 'ok',
            'tanggal_klik'            => $tanggalKlik,
            'tanggal_klik_fmt'        => Carbon::parse($tanggalKlik)->translatedFormat('d F Y'),
            'tanggal_mulai_21'        => $tanggalMulai21,
            'tanggal_mulai_21_fmt'    => Carbon::parse($tanggalMulai21)->translatedFormat('d F Y'),
            'komoditas'               => $komoditas,
            'kabupaten'               => $kabupaten,
            'jenis_komoditas_tfidf'   => $jenisKomoditasTfidf,
            'detail_kabupaten'        => $detailKabupatenTanggal,
            'kabupaten_lonjakan'      => $kabupatenLonjakan,
            'berita_21_hari'          => $berita21Hari,
            'x_21_hari'               => $x21Hari,
            'tfidf_berita_klik'       => $tfidfBeritaKlik,
            'tfidf_x_klik'            => $tfidfXKlik,
            'berita_clean_klik'       => $beritaCleanKlik,
            'x_clean_klik'            => $xCleanKlik,
        ]);
    }

    /**
     * Endpoint untuk meminta analisis AI (Ollama) mengenai penyebab lonjakan/penurunan harga
     * yang dikaitkan dengan berita dalam 21 hari sebelumnya.
     */
    public function analisisAi(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'komoditas' => ['required', 'string', 'max:100'],
            'kabupaten' => ['nullable', 'string', 'max:100'],
            'tanggal'   => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter komoditas atau tanggal tidak valid.',
            ], 422);
        }

        $conn = DB::connection('pgsql');
        $validated = $validator->validated();

        $komoditas = $validated['komoditas'];
        $kabupaten = $validated['kabupaten'] ?? 'ALL';
        $tanggalKlik = $validated['tanggal'];
        $tanggalMulai21 = Carbon::parse($tanggalKlik)->subDays(21)->format('Y-m-d');

        [$jenisKomoditasTfidf, $slugKomoditasTfidf] = $this->mapKomoditasKeTfidf($komoditas);

        // Ambil data harga & lonjakan pada tanggal tersebut
        $dataHarga = $conn->table('public.deteksi_lonjakan_kabupaten')
            ->select([
                'kabupaten',
                'tanggal',
                'komoditas_nama',
                DB::raw('ROUND(rata_kabupaten, 0) AS harga'),
                DB::raw('ROUND(selisih, 0) AS selisih'),
                DB::raw('ROUND(batas_bawah, 0) AS batas_bawah'),
                DB::raw('ROUND(batas_atas, 0) AS batas_atas'),
                'status_lonjakan',
            ])
            ->where('komoditas_nama', '=', $komoditas)
            ->where('tanggal', '=', $tanggalKlik)
            ->when($kabupaten !== 'ALL', function ($q) use ($kabupaten) {
                $q->where('kabupaten', '=', $kabupaten);
            })
            ->orderByRaw("
                CASE
                    WHEN status_lonjakan = 'LONJAKAN_NAIK' THEN 1
                    WHEN status_lonjakan = 'LONJAKAN_TURUN' THEN 2
                    ELSE 3
                END ASC
            ")
            ->orderByDesc(DB::raw('ABS(selisih)'))
            ->first();

        // 1. Ambil data cuaca selama 21 hari sebelumnya dan hari-H
        $cuaca21 = $conn->table('public.cuaca_raw')
            ->selectRaw('
                ROUND(AVG(suhu_rata_rata)::numeric, 1) AS suhu_rata,
                ROUND(MIN(suhu_minimum)::numeric, 1) AS suhu_min,
                ROUND(MAX(suhu_maksimum)::numeric, 1) AS suhu_maks,
                ROUND(AVG(kelembapan_rata_rata)::numeric, 1) AS kelembapan_rata,
                ROUND(SUM(curah_hujan_mm)::numeric, 1) AS curah_hujan_total,
                ROUND(AVG(curah_hujan_mm)::numeric, 1) AS curah_hujan_rata,
                ROUND(AVG(durasi_hujan_jam)::numeric, 1) AS durasi_hujan_rata
            ')
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->when($kabupaten !== 'ALL', function ($q) use ($kabupaten) {
                $q->where('kabupaten_kota', '=', $kabupaten);
            })
            ->first();

        // 2. Ambil berita relevan 21 hari (baik memiliki komoditas maupun tanpa komoditas, relevansi MEDIUM & HIGH)
        $beritaList = $conn->table('public.detik_clean')
            ->select(['tanggal_terbit', 'judul', 'clean_text', 'kategori', 'cakupan_berita', 'komoditas', 'tingkat_relevansi', 'skor_relevansi'])
            ->whereBetween('tanggal_terbit', [$tanggalMulai21, $tanggalKlik])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->orderByRaw("CASE WHEN jenis_komoditas = ? OR komoditas ILIKE ? THEN 1 WHEN tingkat_relevansi = 'HIGH' THEN 2 ELSE 3 END ASC", [$jenisKomoditasTfidf, "%{$komoditas}%"])
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('skor_relevansi')
            ->limit(15)
            ->get();

        // 3. Ambil percakapan X relevan 21 hari (baik memiliki komoditas maupun tanpa komoditas, relevansi MEDIUM & HIGH)
        $xList = $conn->table('public.x_clean')
            ->select(['tanggal', 'body', 'text_clean', 'kategori', 'cakupan_cuitan', 'komoditas', 'tingkat_relevansi', 'skor_relevansi'])
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->whereIn('tingkat_relevansi', ['HIGH', 'MEDIUM'])
            ->orderByRaw("CASE WHEN jenis_komoditas = ? OR komoditas ILIKE ? THEN 1 WHEN tingkat_relevansi = 'HIGH' THEN 2 ELSE 3 END ASC", [$jenisKomoditasTfidf, "%{$komoditas}%"])
            ->orderByDesc('tanggal')
            ->orderByDesc('skor_relevansi')
            ->limit(12)
            ->get();

        // 4. Ambil Top Kata Kunci TF-IDF Berita (21 hari)
        $topTfidfBerita = $conn->table('public.detik_tfidf_harian')
            ->select('term', DB::raw('ROUND(SUM(total_tfidf)::numeric, 2) AS total_skor'), DB::raw('SUM(jumlah_berita) AS jml_doc'))
            ->whereBetween('tanggal_terbit', [$tanggalMulai21, $tanggalKlik])
            ->where(function ($q) use ($jenisKomoditasTfidf, $komoditas) {
                $q->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
                  ->orWhere('komoditas', '=', $komoditas)
                  ->orWhereNull('komoditas');
            })
            ->groupBy('term')
            ->orderByDesc('total_skor')
            ->limit(8)
            ->get();

        // 5. Ambil Top Kata Kunci TF-IDF X (21 hari)
        $topTfidfX = $conn->table('public.x_tfidf_harian')
            ->select('term', DB::raw('ROUND(SUM(total_tfidf)::numeric, 2) AS total_skor'), DB::raw('SUM(jumlah_cuitan) AS jml_doc'))
            ->whereBetween('tanggal', [$tanggalMulai21, $tanggalKlik])
            ->where(function ($q) use ($jenisKomoditasTfidf, $komoditas) {
                $q->where('jenis_komoditas', '=', $jenisKomoditasTfidf)
                  ->orWhere('komoditas', '=', $komoditas)
                  ->orWhereNull('komoditas');
            })
            ->groupBy('term')
            ->orderByDesc('total_skor')
            ->limit(8)
            ->get();

        // Format rangkuman teks untuk prompt AI
        $newsLines = [];
        foreach ($beritaList as $b) {
            $tglFmt = Carbon::parse($b->tanggal_terbit)->format('d/m/Y');
            $selisihH = Carbon::parse($b->tanggal_terbit)->diffInDays(Carbon::parse($tanggalKlik));
            $hTag = $selisihH === 0 ? 'Hari-H' : 'H-' . $selisihH;
            $tagKom = !empty($b->komoditas) ? "[Komoditas: {$b->komoditas}]" : "[Umum/Tanpa Komoditas]";
            $newsLines[] = "- [{$tglFmt} | {$hTag} | {$b->tingkat_relevansi}] {$tagKom} {$b->judul} (Kategori: {$b->kategori}, Wilayah: {$b->cakupan_berita})";
        }
        $newsContext = !empty($newsLines)
            ? implode("\n", $newsLines)
            : "Tidak ada berita relevan (Medium/High) tercatat dalam kurun waktu 21 hari ini.";

        $xLines = [];
        foreach ($xList as $x) {
            $tglFmt = Carbon::parse($x->tanggal)->format('d/m/Y');
            $tagKom = !empty($x->komoditas) ? "[Komoditas: {$x->komoditas}]" : "[Umum/Tanpa Komoditas]";
            $cleanBody = mb_substr(trim(preg_replace('/\s+/', ' ', $x->body)), 0, 110);
            $xLines[] = "- [{$tglFmt} | {$x->tingkat_relevansi}] {$tagKom} {$cleanBody}";
        }
        $xContext = !empty($xLines)
            ? implode("\n", $xLines)
            : "Tidak ada cuitan X relevan (Medium/High) tercatat dalam kurun waktu 21 hari ini.";

        $tfidfBeritaStr = $topTfidfBerita->isNotEmpty()
            ? implode(', ', $topTfidfBerita->map(fn($t) => "{$t->term} ({$t->total_skor})")->toArray())
            : 'Tidak ada kata kunci dominan';

        $tfidfXStr = $topTfidfX->isNotEmpty()
            ? implode(', ', $topTfidfX->map(fn($t) => "{$t->term} ({$t->total_skor})")->toArray())
            : 'Tidak ada kata kunci dominan';

        $hargaStr = $dataHarga ? 'Rp ' . number_format($dataHarga->harga, 0, ',', '.') : 'Tidak Tercatat';
        $selisihStr = $dataHarga ? (($dataHarga->selisih > 0 ? '+' : '') . 'Rp ' . number_format($dataHarga->selisih, 0, ',', '.')) : 'Tidak Tercatat';
        $statusStr = $dataHarga ? $dataHarga->status_lonjakan : 'Tidak Tercatat';
        $wilayahStr = ($kabupaten !== 'ALL') ? $kabupaten : ($dataHarga->kabupaten ?? 'Jawa Timur');

        $prompt = "Langsung berikan analisis terstruktur dalam Bahasa Indonesia sesuai format tanpa pengantar basa-basi.

Anda adalah pakar analis ekonomi pertanian dan stabilisasi harga pangan. Buatlah analisis komprehensif, tajam, dan objektif mengenai penyebab perubahan harga komoditas pangan berikut:

DATA LONJAKAN HARGA:
- Komoditas: {$komoditas}
- Wilayah Pengamatan: {$wilayahStr}
- Tanggal Lonjakan / Pengamatan: " . Carbon::parse($tanggalKlik)->translatedFormat('d F Y') . "
- Harga: {$hargaStr} / kg
- Perubahan Harian (Selisih): {$selisihStr}
- Status Lonjakan: {$statusStr}

INDIKATOR CUACA 21 HARI TERAKHIR (" . Carbon::parse($tanggalMulai21)->translatedFormat('d M Y') . " s/d " . Carbon::parse($tanggalKlik)->translatedFormat('d M Y') . "):
- Suhu Rata-rata: " . ($cuaca21->suhu_rata ?? '-') . "°C (Min: " . ($cuaca21->suhu_min ?? '-') . "°C, Maks: " . ($cuaca21->suhu_maks ?? '-') . "°C)
- Kelembapan Udara Rata-rata: " . ($cuaca21->kelembapan_rata ?? '-') . "%
- Akumulasi Curah Hujan 21 Hari: " . ($cuaca21->curah_hujan_total ?? '0') . " mm (Rata-rata: " . ($cuaca21->curah_hujan_rata ?? '0') . " mm/hari)
- Rata-rata Durasi Hujan: " . ($cuaca21->durasi_hujan_rata ?? '0') . " jam/hari

KATA KUNCI UTAMA (TF-IDF 21 HARI TERAKHIR):
- Kata Kunci Berita (Detik): {$tfidfBeritaStr}
- Kata Kunci Media Sosial (X/Twitter): {$tfidfXStr}

REKAP BERITA RELEVAN (MEDIUM & HIGH, DENGAN ATAU TANPA KOMODITAS):
{$newsContext}

REKAP PERCAKAPAN X RELEVAN (MEDIUM & HIGH, DENGAN ATAU TANPA KOMODITAS):
{$xContext}

PETUNJUK FORMAT LAPORAN (WAJIB DIIKUTI):
Sajikan hasil analisis secara profesional dalam Bahasa Indonesia dengan format:

### Ringkasan Eksekutif
(Satu paragraf padat 3-4 kalimat menjelaskan faktor utama pemicu perubahan harga pada tanggal tersebut dengan mengaitkan dinamika cuaca, pasokan, dan sentimen publik)

### Matriks Korelasi Multidimensi
Sajikan dalam tabel Markdown per kolom dengan header persis berikut:
| Aspek / Dimensi | Temuan Berita, X, Cuaca & TF-IDF | Korelasi & Analisis Terhadap Harga | Rekomendasi Mitigasi |
| :--- | :--- | :--- | :--- |
| **Kondisi Cuaca & Iklim** | ... | ... | ... |
| **Pasokan & Produksi Lapangan** | ... | ... | ... |
| **Dinamika Berita & Kebijakan** | ... | ... | ... |
| **Sentimen Publik di X (Twitter)** | ... | ... | ... |

### Rekomendasi Strategis & Antisipasi
- (Poin langkah kebijakan mitigasi konkret untuk pemerintah/pelaku usaha)";

        $ollamaUrl = env('OLLAMA_URL', 'http://192.168.11.211:11434/api');
        $model = env('MODEL_OLLAMA', 'gpt-oss:20b');
        $endpoint = rtrim($ollamaUrl, '/');
        if (!str_ends_with($endpoint, '/api') && !str_contains($endpoint, '/api')) {
            $endpoint .= '/api';
        }

        $payload = [
            'model'  => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.2,
                'num_predict' => 3500,
            ],
        ];

        try {
            $start = microtime(true);
            $ch = curl_init($endpoint . '/generate');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);

            $response = curl_exec($ch);
            $elapsed = round(microtime(true) - $start, 2);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError || $httpCode !== 200) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal menghubungi layanan Ollama (' . ($curlError ?: 'HTTP ' . $httpCode) . '). Pastikan Ollama aktif di ' . $endpoint . '.',
                ], 502);
            }

            $resJson = json_decode($response, true);
            $analisisText = $resJson['response'] ?? '';
            if (empty(trim($analisisText)) && !empty($resJson['thinking'])) {
                // Fallback jika response kosong tapi model menghasilkan thinking
                $analisisText = $resJson['thinking'];
            }
            if (empty(trim($analisisText))) {
                $analisisText = 'Tidak ada tanggapan teks yang dihasilkan oleh model.';
            }

            return response()->json([
                'status'                  => 'ok',
                'model'                   => $model,
                'tanggal_klik'            => $tanggalKlik,
                'tanggal_klik_fmt'        => Carbon::parse($tanggalKlik)->translatedFormat('d F Y'),
                'komoditas'               => $komoditas,
                'kabupaten'               => $wilayahStr,
                'status_lonjakan'         => $statusStr,
                'analisis'                => $analisisText,
                'total_berita_dianalisis' => count($beritaList),
                'total_x_dianalisis'      => count($xList),
                'indikator'               => [
                    'cuaca_suhu_rata'       => $cuaca21->suhu_rata ?? '-',
                    'cuaca_hujan_total_mm'  => $cuaca21->curah_hujan_total ?? '0',
                    'cuaca_kelembapan_rata' => $cuaca21->kelembapan_rata ?? '-',
                    'top_tfidf_berita'      => $topTfidfBerita->pluck('term')->toArray(),
                    'top_tfidf_x'           => $topTfidfX->pluck('term')->toArray(),
                ],
                'waktu_proses_detik'      => $elapsed,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat meminta analisis AI: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memetakan nama komoditas pada filter harga ke jenis_komoditas & slug komoditas pada tabel TF-IDF.
     */
    private function mapKomoditasKeTfidf(string $komoditasNama): array
    {
        $map = [
            'Beras Medium'        => ['BERAS', 'beras'],
            'Beras Premium'       => ['BERAS', 'beras'],
            'Cabe Merah Besar'    => ['CABAI', 'cabai_merah_besar'],
            'Cabe Merah Keriting' => ['CABAI', 'cabai_merah_keriting'],
            'Cabe Rawit Merah'    => ['CABAI', 'cabai_rawit_merah'],
            'Telur Ayam Kampung'  => ['TELUR', 'telur_ayam_kampung'],
            'Telur Ayam Ras'      => ['TELUR', 'telur_ayam_ras'],
            'Tomat Merah'         => ['TOMAT', 'tomat'],
        ];

        return $map[$komoditasNama] ?? ['CABAI', 'cabai_rawit_merah'];
    }

    /**
     * Mengambil data TF-IDF harian untuk keseluruhan rentang tanggal filter.
     */
    private function ambilTfidfHarian(
        $conn,
        string $tableName,
        string $dateColumn,
        string $countColumn,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $jenisKomoditas,
        string $slugKomoditas
    ) {
        return $conn->table($tableName)
            ->select([
                DB::raw("{$dateColumn} AS tanggal"),
                'jenis_komoditas',
                'komoditas',
                'term',
                DB::raw('ROUND(total_tfidf::numeric, 4) AS total_tfidf'),
                DB::raw("{$countColumn} AS jumlah_dokumen"),
                DB::raw('ROUND(rata_rata_tfidf::numeric, 4) AS rata_rata_tfidf'),
                'rank_term',
            ])
            ->whereBetween($dateColumn, [$tanggalMulai, $tanggalSelesai])
            ->where('jenis_komoditas', '=', $jenisKomoditas)
            ->where('rank_term', '<=', 3)
            ->orderBy($dateColumn, 'asc')
            ->orderBy('rank_term', 'asc')
            ->limit(250)
            ->get();
    }

    /**
     * Menghitung metrik ringkasan dari koleksi data aktual deteksi_lonjakan_kabupaten.
     */
    private function hitungRingkasan($seriesData, $rekapLonjakanDb): array
    {
        if ($seriesData->isEmpty()) {
            return [
                'ada_data'            => false,
                'total_hari'          => 0,
                'harga_terbaru'       => null,
                'tanggal_terbaru'     => null,
                'status_terbaru'      => null,
                'harga_sebelumnya'    => null,
                'tanggal_sebelumnya'  => null,
                'selisih_harian'      => null,
                'persen_harian'       => null,
                'harga_awal'          => null,
                'tanggal_awal'        => null,
                'selisih_periode'     => null,
                'persen_periode'      => null,
                'harga_rata_rata'     => null,
                'harga_tertinggi'     => null,
                'tanggal_tertinggi'   => null,
                'harga_terendah'      => null,
                'tanggal_terendah'    => null,
                'jml_lonjakan_naik'   => 0,
                'jml_lonjakan_turun'  => 0,
                'jml_normal'          => 0,
                'jml_mad_0'           => 0,
                'jml_belum'           => 0,
                'hari_lonjakan_naik'  => 0,
                'hari_lonjakan_turun' => 0,
            ];
        }

        $first = $seriesData->first();
        $last = $seriesData->last();
        $prev = $seriesData->count() >= 2 ? $seriesData->slice(-2, 1)->first() : null;

        $hargaTerbaru = (float) $last->harga;
        $hargaAwal = (float) $first->harga;
        $hargaSebelumnya = $prev ? (float) $prev->harga : null;

        $selisihHarian = $prev !== null ? ($hargaTerbaru - $hargaSebelumnya) : (float) ($last->selisih ?? 0);
        $persenHarian = ($hargaSebelumnya !== null && $hargaSebelumnya > 0)
            ? round(($selisihHarian / $hargaSebelumnya) * 100, 2)
            : 0.0;

        $selisihPeriode = $hargaTerbaru - $hargaAwal;
        $persenPeriode = $hargaAwal > 0
            ? round(($selisihPeriode / $hargaAwal) * 100, 2)
            : 0.0;

        $maxItem = $seriesData->sortByDesc(fn($r) => (float) $r->harga)->first();
        $minItem = $seriesData->sortBy(fn($r) => (float) $r->harga)->first();
        $rataRata = round($seriesData->avg(fn($r) => (float) $r->harga), 2);

        $hariNaik = $seriesData->where('status_lonjakan', 'LONJAKAN_NAIK')->count();
        $hariTurun = $seriesData->where('status_lonjakan', 'LONJAKAN_TURUN')->count();

        return [
            'ada_data'            => true,
            'total_hari'          => $seriesData->count(),
            'harga_terbaru'       => $hargaTerbaru,
            'tanggal_terbaru'     => $last->tanggal,
            'status_terbaru'      => $last->status_lonjakan,
            'harga_sebelumnya'    => $hargaSebelumnya,
            'tanggal_sebelumnya'  => $prev->tanggal ?? null,
            'selisih_harian'      => $selisihHarian,
            'persen_harian'       => $persenHarian,
            'harga_awal'          => $hargaAwal,
            'tanggal_awal'        => $first->tanggal,
            'selisih_periode'     => $selisihPeriode,
            'persen_periode'      => $persenPeriode,
            'harga_rata_rata'     => $rataRata,
            'harga_tertinggi'     => (float) $maxItem->harga,
            'tanggal_tertinggi'   => $maxItem->tanggal,
            'harga_terendah'      => (float) $minItem->harga,
            'tanggal_terendah'    => $minItem->tanggal,
            'jml_lonjakan_naik'   => (int) ($rekapLonjakanDb->total_naik ?? $hariNaik),
            'jml_lonjakan_turun'  => (int) ($rekapLonjakanDb->total_turun ?? $hariTurun),
            'jml_normal'          => (int) ($rekapLonjakanDb->total_normal ?? 0),
            'jml_mad_0'           => (int) ($rekapLonjakanDb->total_mad0 ?? 0), // tidak dipakai lagi (MAD minimum)
            'jml_belum'           => (int) ($rekapLonjakanDb->total_belum ?? 0),
            'hari_lonjakan_naik'  => $hariNaik,
            'hari_lonjakan_turun' => $hariTurun,
        ];
    }

    /**
     * Menyiapkan array data untuk Chart.js beserta penanda lonjakan naik dan turun.
     *
     * Catatan:
     * - Nilai kosong (mis. tanggal BELUM_TERDETEKSI) dikirim sebagai null, BUKAN 0,
     *   agar garis batas tidak jatuh ke nol dan Chart.js membuat celah.
     * - batas_atas/batas_bawah berada dalam satuan SELISIH harga.
     *   Untuk digambar bersama garis harga dipakai harga_batas_atas/harga_batas_bawah
     *   (= harga hari sebelumnya + batas selisih).
     */
    private function siapkanChartPayload($seriesData): array
    {
        $angka = function ($nilai) {
            return ($nilai === null || $nilai === '') ? null : round((float) $nilai, 2);
        };

        $payload = [
            'labels'            => [],
            'tanggal_raw'       => [],
            'harga'             => [],
            'harga_batas_atas'  => [],
            'harga_batas_bawah' => [],
            'lonjakan_naik'     => [],
            'lonjakan_turun'    => [],
            'status'            => [],
            'selisih'           => [],
            'batas_atas'        => [],
            'batas_bawah'       => [],
            'mad_asli'          => [],
            'mad_minimum'       => [],
            'mad_dipakai'       => [],
            'jml_kab'           => [],
            'jml_kab_naik'      => [],
            'jml_kab_turun'     => [],
        ];

        foreach ($seriesData as $row) {
            $tgl = $row->tanggal;
            $harga = $angka($row->harga);
            $status = (string) ($row->status_lonjakan ?? '');

            $payload['tanggal_raw'][]       = $tgl;
            $payload['labels'][]            = Carbon::parse($tgl)->translatedFormat('d M Y');
            $payload['harga'][]             = $harga;
            $payload['harga_batas_atas'][]  = $angka($row->harga_batas_atas ?? null);
            $payload['harga_batas_bawah'][] = $angka($row->harga_batas_bawah ?? null);
            $payload['status'][]            = $status;
            $payload['selisih'][]           = $angka($row->selisih ?? null);
            $payload['batas_atas'][]        = $angka($row->batas_atas ?? null);
            $payload['batas_bawah'][]       = $angka($row->batas_bawah ?? null);
            $payload['mad_asli'][]          = $angka($row->mad_asli ?? null);
            $payload['mad_minimum'][]       = $angka($row->mad_minimum ?? null);
            $payload['mad_dipakai'][]       = $angka($row->mad_x_hari ?? null);
            $payload['jml_kab'][]           = (int) ($row->jml_kab ?? 0);
            $payload['jml_kab_naik'][]      = (int) ($row->jml_kab_naik ?? 0);
            $payload['jml_kab_turun'][]     = (int) ($row->jml_kab_turun ?? 0);

            // Titik penanda hanya pada tanggal lonjakan; selain itu null (tidak digambar)
            $payload['lonjakan_naik'][]  = ($status === 'LONJAKAN_NAIK') ? $harga : null;
            $payload['lonjakan_turun'][] = ($status === 'LONJAKAN_TURUN') ? $harga : null;
        }

        return $payload;
    }
}