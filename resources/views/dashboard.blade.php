@extends('adminlte::page')

@section('title', 'Dashboard Eksekutif — Pemantauan Harga, Cuaca, Berita & X')

@section('plugins.Chartjs', true)

@section('content_header')
    <div class="row align-items-center mb-1">
        <div class="col-md-8">
            <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.65rem;">
                <i class="fas fa-chart-line text-primary mr-2"></i>Dashboard Eksekutif Pemantauan Harga Pangan
            </h1>
            <p class="text-muted mb-0 mt-1">
                Visualisasi terpadu pergerakan harga komoditas, deteksi lonjakan harga, rata-rata cuaca harian, serta analisis topik Berita &amp; X.
            </p>
        </div>
        <div class="col-md-4 text-md-right mt-2 mt-md-0">
            <span class="badge badge-info px-3 py-2 shadow-sm d-none" id="autoFilterLoadingBadge">
                <i class="fas fa-spinner fa-spin mr-1"></i> Memperbarui data...
            </span>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid px-0 pb-4">
        {{-- Panel Filter Komoditas, Kabupaten/Kota, dan Rentang Tanggal --}}
        <div class="card card-outline card-primary shadow-sm mb-4">
            <div class="card-header py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-sliders-h text-primary mr-1"></i> Filter Data Visualisasi
                    </h3>
                    <div class="small text-muted">
                        Rentang data tersedia:
                        <strong>{{ \Carbon\Carbon::parse($minTanggalDb)->format('d/m/Y') }}</strong>
                        s/d
                        <strong>{{ \Carbon\Carbon::parse($maxTanggalDb)->format('d/m/Y') }}</strong>
                    </div>
                </div>
            </div>
            <div class="card-body py-3">
                <form method="GET" action="{{ route('dashboard') }}" id="filterDashboardForm">
                    <div class="form-row align-items-end">
                        {{-- Filter Komoditas --}}
                        <div class="col-12 col-md-3 mb-2 mb-md-0">
                            <label for="komoditas" class="small font-weight-bold text-muted mb-1">
                                <i class="fas fa-seedling text-success mr-1"></i> Komoditas
                            </label>
                            <select name="komoditas" id="komoditas" class="form-control form-control-sm auto-submit-filter">
                                @foreach($daftarKomoditas as $kom)
                                    <option value="{{ $kom }}" {{ $selectedKomoditas === $kom ? 'selected' : '' }}>
                                        {{ $kom }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Filter Kabupaten/Kota --}}
                        <div class="col-12 col-md-4 mb-2 mb-md-0">
                            <label for="kabupaten" class="small font-weight-bold text-muted mb-1">
                                <i class="fas fa-map-marker-alt text-danger mr-1"></i> Kabupaten / Kota
                            </label>
                            <select name="kabupaten" id="kabupaten" class="form-control form-control-sm auto-submit-filter">
                                <option value="ALL" {{ $selectedKabupaten === 'ALL' ? 'selected' : '' }}>
                                    Semua Kabupaten/Kota (Rata-rata Jawa Timur)
                                </option>
                                @foreach($daftarKabupaten as $kab)
                                    <option value="{{ $kab }}" {{ $selectedKabupaten === $kab ? 'selected' : '' }}>
                                        {{ $kab }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Filter Rentang Tanggal Mulai --}}
                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <label for="tanggal_mulai" class="small font-weight-bold text-muted mb-1">
                                <i class="far fa-calendar-alt text-primary mr-1"></i> Tanggal Mulai
                            </label>
                            <input
                                type="date"
                                name="tanggal_mulai"
                                id="tanggal_mulai"
                                class="form-control form-control-sm auto-submit-filter"
                                value="{{ $tanggalMulai }}"
                                min="{{ $minTanggalDb }}"
                                max="{{ $maxTanggalDb }}"
                            >
                        </div>

                        {{-- Filter Rentang Tanggal Selesai --}}
                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <label for="tanggal_selesai" class="small font-weight-bold text-muted mb-1">
                                <i class="far fa-calendar-check text-primary mr-1"></i> Tanggal Selesai
                            </label>
                            <input
                                type="date"
                                name="tanggal_selesai"
                                id="tanggal_selesai"
                                class="form-control form-control-sm auto-submit-filter"
                                value="{{ $tanggalSelesai }}"
                                min="{{ $minTanggalDb }}"
                                max="{{ $maxTanggalDb }}"
                            >
                        </div>

                        {{-- Tombol Reset --}}
                        <div class="col-12 col-md-1 d-flex">
                            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary btn-block" title="Reset ke Default">
                                <i class="fas fa-undo mr-1"></i> Reset
                            </a>
                        </div>
                    </div>

                    {{-- Pintasan Rentang Tanggal Cepat --}}
                    <div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between">
                        <div class="small text-muted mb-1 mb-sm-0">
                            <span class="mr-2"><i class="fas fa-bolt text-warning mr-1"></i>Pintasan Periode:</span>
                            <button type="button" class="btn btn-xs btn-outline-primary mr-1 btn-quick-range"
                                data-start="{{ \Carbon\Carbon::parse($maxTanggalDb)->subDays(29)->max(\Carbon\Carbon::parse($minTanggalDb))->format('Y-m-d') }}"
                                data-end="{{ $maxTanggalDb }}">
                                30 Hari Terakhir
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-primary mr-1 btn-quick-range"
                                data-start="{{ \Carbon\Carbon::parse($maxTanggalDb)->subMonths(3)->max(\Carbon\Carbon::parse($minTanggalDb))->format('Y-m-d') }}"
                                data-end="{{ $maxTanggalDb }}">
                                3 Bulan Terakhir
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-primary btn-quick-range"
                                data-start="{{ $minTanggalDb }}"
                                data-end="{{ $maxTanggalDb }}">
                                Seluruh Data ({{ \Carbon\Carbon::parse($minTanggalDb)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($maxTanggalDb)->format('d/m/Y') }})
                            </button>
                        </div>
                        <div class="small text-muted">
                            Menampilkan <strong>{{ $ringkasan['total_hari'] }}</strong> hari observasi untuk
                            <strong>{{ $selectedKomoditas }}</strong>
                            ({{ $selectedKabupaten === 'ALL' ? 'Semua Kabupaten/Kota Jawa Timur' : $selectedKabupaten }}).
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Panel 4 Kartu KPI Utama dihapus sesuai permintaan --}}

        {{-- Grafik Garis Harga Komoditas & Daftar Kejadian Lonjakan (Tinggi Seragam) --}}
        <div class="row mb-4" id="sectionGrafikHarga">
            {{-- Grafik Utama --}}
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="card card-primary card-outline shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-chart-line text-primary mr-1"></i>
                                Grafik Pergerakan Harga &amp; Penanda Lonjakan:
                                <span class="text-primary">{{ $selectedKomoditas }}</span>
                            </h3>
                            <div class="card-tools d-flex align-items-center">
                                <span class="badge badge-info mr-2">
                                    <i class="fas fa-hand-pointer mr-1"></i>Klik titik lonjakan untuk detail &amp; berita 21 hari sebelumnya
                                </span>
                                <span class="badge badge-light border" id="chartStatusBadge">Memuat grafik...</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-2 flex-fill">
                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                            <div class="small text-muted">
                                Wilayah: <strong>{{ $selectedKabupaten === 'ALL' ? 'Semua Kabupaten/Kota (Rata-rata)' : $selectedKabupaten }}</strong>
                                | Periode: <strong>{{ \Carbon\Carbon::parse($tanggalMulai)->format('d M Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d M Y') }}</strong>
                            </div>
                            <div class="d-flex align-items-center small mt-1 mt-sm-0">
                                <span class="mr-3"><i class="fas fa-minus text-primary mr-1"></i> Harga Harian (Rp)</span>
                                <span class="mr-3"><i class="fas fa-caret-up text-danger mr-1"></i> Hari Lonjakan Naik ({{ $ringkasan['hari_lonjakan_naik'] }})</span>
                                <span><i class="fas fa-caret-down text-success mr-1"></i> Hari Lonjakan Turun ({{ $ringkasan['hari_lonjakan_turun'] }})</span>
                            </div>
                        </div>

                        @if($ringkasan['ada_data'])
                            <div class="chart-responsive" style="position: relative; height: 360px;">
                                <canvas id="priceSpikeChart" height="360" style="cursor: pointer;"></canvas>
                            </div>
                        @else
                            <div class="alert alert-warning text-center my-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                Tidak ada data harga untuk filter komoditas, wilayah, dan rentang tanggal yang dipilih.
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-white">
                        <div class="row text-center">
                            <div class="col-6 col-sm-3 border-right">
                                <div class="description-block my-1">
                                    <span class="description-percentage text-muted small">HARGA AWAL PERIODE</span>
                                    <h5 class="description-header font-weight-bold">
                                        {{ $ringkasan['harga_awal'] ? 'Rp ' . number_format($ringkasan['harga_awal'], 0, ',', '.') : '-' }}
                                    </h5>
                                    <span class="description-text text-muted small">{{ $ringkasan['tanggal_awal'] ? \Carbon\Carbon::parse($ringkasan['tanggal_awal'])->format('d/m/Y') : '-' }}</span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 border-right">
                                <div class="description-block my-1">
                                    <span class="description-percentage text-danger small">HARGA TERTINGGI</span>
                                    <h5 class="description-header font-weight-bold text-danger">
                                        {{ $ringkasan['harga_tertinggi'] ? 'Rp ' . number_format($ringkasan['harga_tertinggi'], 0, ',', '.') : '-' }}
                                    </h5>
                                    <span class="description-text text-muted small">{{ $ringkasan['tanggal_tertinggi'] ? \Carbon\Carbon::parse($ringkasan['tanggal_tertinggi'])->format('d/m/Y') : '-' }}</span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3 border-right">
                                <div class="description-block my-1">
                                    <span class="description-percentage text-success small">HARGA TERENDAH</span>
                                    <h5 class="description-header font-weight-bold text-success">
                                        {{ $ringkasan['harga_terendah'] ? 'Rp ' . number_format($ringkasan['harga_terendah'], 0, ',', '.') : '-' }}
                                    </h5>
                                    <span class="description-text text-muted small">{{ $ringkasan['tanggal_terendah'] ? \Carbon\Carbon::parse($ringkasan['tanggal_terendah'])->format('d/m/Y') : '-' }}</span>
                                </div>
                            </div>
                            <div class="col-6 col-sm-3">
                                <div class="description-block my-1">
                                    <span class="description-percentage text-primary small">HARGA AKHIR PERIODE</span>
                                    <h5 class="description-header font-weight-bold text-primary">
                                        {{ $ringkasan['harga_terbaru'] ? 'Rp ' . number_format($ringkasan['harga_terbaru'], 0, ',', '.') : '-' }}
                                    </h5>
                                    <span class="description-text text-muted small">{{ $ringkasan['tanggal_terbaru'] ? \Carbon\Carbon::parse($ringkasan['tanggal_terbaru'])->format('d/m/Y') : '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel Samping: Rincian Tanggal & Wilayah Lonjakan Harga --}}
            <div class="col-lg-4" id="sectionDaftarLonjakan">
                <div class="card card-warning card-outline shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-bolt text-warning mr-1"></i>
                                Rincian Kejadian Lonjakan Harga
                            </h3>
                            <span class="badge badge-warning">
                                {{ $kejadianLonjakan->count() }} data
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0 flex-fill" style="height: 390px; overflow-y: auto;">
                        @if(!$ringkasan['ada_data'])
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center p-4 text-center text-muted">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <p class="mb-0 font-weight-bold text-dark">Tidak Ditemukan Data</p>
                                <small class="mt-1">Data harga tidak tersedia untuk komoditas, wilayah, dan rentang tanggal yang dipilih.</small>
                            </div>
                        @elseif($kejadianLonjakan->isEmpty())
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center p-4 text-center text-muted">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="mb-0 font-weight-bold text-dark">Harga Terkendali</p>
                                <small class="mt-1">Tidak ditemukan lonjakan harga naik maupun turun pada rentang dan wilayah filter ini.</small>
                            </div>
                        @else
                            <table class="table table-sm table-hover table-striped table-head-fixed mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Kabupaten/Kota</th>
                                        <th class="text-right">Harga</th>
                                        <th class="text-right">Selisih</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($kejadianLonjakan as $item)
                                        <tr class="baris-lonjakan-klik" data-tanggal="{{ $item->tanggal }}" style="cursor: pointer;" title="Klik untuk melihat detail lonjakan & berita 21 hari sebelumnya">
                                            <td class="small font-weight-bold text-nowrap text-primary">
                                                <i class="fas fa-search-plus fa-xs mr-1"></i>{{ \Carbon\Carbon::parse($item->tanggal)->format('d/m/Y') }}
                                            </td>
                                            <td class="small">{{ $item->kabupaten }}</td>
                                            <td class="text-right small text-nowrap">
                                                Rp {{ number_format($item->harga, 0, ',', '.') }}
                                            </td>
                                            <td class="text-right small font-weight-bold text-nowrap {{ $item->selisih >= 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $item->selisih >= 0 ? '+' : '-' }}Rp {{ number_format(abs($item->selisih), 0, ',', '.') }}
                                            </td>
                                            <td class="text-center">
                                                @if($item->status_lonjakan === 'LONJAKAN_NAIK')
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-arrow-up mr-1"></i>Naik
                                                    </span>
                                                @else
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-arrow-down mr-1"></i>Turun
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    <div class="card-footer bg-light small text-muted">
                        <div class="d-flex justify-content-between align-items-center">
                            @if($ringkasan['ada_data'])
                                <span>Normal: <strong>{{ number_format($ringkasan['jml_normal'], 0, ',', '.') }}</strong> | Belum diuji: <strong>{{ number_format($ringkasan['jml_belum'] ?? 0, 0, ',', '.') }}</strong></span>
                                <span class="text-primary"><i class="fas fa-mouse-pointer mr-1"></i>Klik baris untuk detail</span>
                            @else
                                <span>Status: <strong>Data tidak tersedia</strong></span>
                                <span>-</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- PANEL INTERAKTIF: Detail Lonjakan & Berita dalam 21 Hari Sebelumnya (Muncul Saat Titik Grafik / Baris Lonjakan Diklik) --}}
        <div class="row mb-4 d-none" id="sectionDetailLonjakan21Hari">
            <div class="col-12">
                <div class="card card-danger card-outline shadow">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h3 class="card-title font-weight-bold text-dark">
                                    <i class="fas fa-search-Location text-danger mr-1"></i>
                                    Detail Titik Lonjakan &amp; Analisis Berita 21 Hari Sebelumnya:
                                    <span class="badge badge-danger px-2 py-1 ml-1" id="labelTanggalKlikDetail">-</span>
                                </h3>
                                <span class="small text-muted ml-2" id="labelRentang21HariDetail"></span>
                            </div>
                            <div class="mt-2 mt-md-0">
                                <button type="button" class="btn btn-xs btn-outline-primary mr-1" id="btnResetKeRentangFilter">
                                    <i class="fas fa-calendar-alt mr-1"></i> Kembalikan Tabel TF-IDF &amp; Clean ke Keseluruhan Rentang Filter
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnTutupDetailLonjakan">
                                    <i class="fas fa-times mr-1"></i> Tutup Detail
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="loadingDetailLonjakan" class="text-center py-4 d-none">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                            <p class="mb-0 text-muted">Memuat detail lonjakan dan berita 21 hari sebelumnya...</p>
                        </div>

                        <div id="kontenDetailLonjakan">
                            {{-- Ringkasan Titik yang Diklik --}}
                            <div class="row mb-3" id="ringkasanTitikKlikRow">
                                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <small class="text-muted d-block font-weight-bold">TANGGAL TITIK DIKLIK</small>
                                        <span class="h6 font-weight-bold text-dark mb-0" id="infoKlikTanggal">-</span>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <small class="text-muted d-block font-weight-bold">HARGA &amp; PERUBAHAN HARIAN</small>
                                        <span class="h6 font-weight-bold text-primary mb-0" id="infoKlikHarga">-</span>
                                        <small class="d-block" id="infoKlikSelisih">-</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-2 mb-sm-0">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <small class="text-muted d-block font-weight-bold">STATUS LONJAKAN &amp; BATAS MAD</small>
                                        <span id="infoKlikStatus">-</span>
                                        <small class="d-block text-muted mt-1" id="infoKlikBatasMad">-</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <small class="text-muted d-block font-weight-bold">JENDELA ANALISIS BERITA (21 HARI)</small>
                                        <span class="font-weight-bold text-dark small" id="infoKlikJendela21">-</span>
                                        <small class="d-block text-muted" id="infoKlikTotalBerita21">-</small>
                                    </div>
                                </div>
                            </div>

                            {{-- CARD RANGKUMAN & ANALISIS AI (OLLAMA) BERBASIS BERITA 21 HARI --}}
                            <div class="card card-outline card-danger shadow-sm mb-3">
                                <div class="card-header bg-white py-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <h4 class="card-title font-weight-bold text-dark m-0" style="font-size: 1.05rem;">
                                            <i class="fas fa-robot text-danger mr-2"></i>Rangkuman &amp; Analisis AI: Korelasi Berita Terhadap Fluktuasi Harga
                                        </h4>
                                        <div class="d-flex align-items-center mt-1 mt-md-0">
                                            <span class="badge badge-light border mr-2" id="badgeModelAi">
                                                <i class="fas fa-brain text-danger mr-1"></i>{{ env('MODEL_OLLAMA', 'gpt-oss:20b') }}
                                            </span>
                                            <button type="button" class="btn btn-xs btn-outline-danger shadow-sm" id="btnUlangiAnalisisAi">
                                                <i class="fas fa-sync-alt mr-1"></i> Buat Ulang Analisis AI
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    {{-- Panel Ringkasan Indikator Data Multidimensi yang Dianalisis --}}
                                    <div class="p-2 mb-3 bg-light border rounded d-none" id="aiIndikatorBadgesRow">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                                            <div class="d-flex flex-wrap align-items-center mb-1 mb-md-0">
                                                <span class="badge badge-danger mr-2 py-1 px-2 font-weight-bold">
                                                    <i class="fas fa-microchip mr-1"></i>Data Masukan AI:
                                                </span>
                                                <span class="badge badge-info mr-1 py-1 px-2" id="badgeAiCuaca">
                                                    <i class="fas fa-cloud-sun-rain mr-1"></i>Cuaca 21 Hari: Memuat...
                                                </span>
                                                <span class="badge badge-primary mr-1 py-1 px-2" id="badgeAiBerita">
                                                    <i class="far fa-newspaper mr-1"></i>Berita Med/High: -
                                                </span>
                                                <span class="badge badge-dark mr-1 py-1 px-2" id="badgeAiX">
                                                    <i class="fas fa-hashtag mr-1"></i>X Med/High: -
                                                </span>
                                            </div>
                                            <div class="small text-muted" id="badgeAiTfidf">
                                                <i class="fas fa-tags text-secondary mr-1"></i>TF-IDF: -
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Loading State AI --}}
                                    <div id="aiLoadingState" class="py-3 text-center d-none">
                                        <div class="spinner-border text-danger mb-2" role="status" style="width: 2rem; height: 2rem;">
                                            <span class="sr-only">Menganalisis...</span>
                                        </div>
                                        <p class="font-weight-bold text-dark mb-0">AI sedang membaca berita 21 hari sebelumnya &amp; menyusun analisis penyebab fluktuasi harga...</p>
                                        <small class="text-muted">Mengkorelasikan data lonjakan harga dengan faktor cuaca, rantai pasok, kebijakan, dan dinamika pasar.</small>
                                    </div>

                                    {{-- Error State AI --}}
                                    <div id="aiErrorState" class="alert alert-warning py-2 mb-0 d-none">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <span id="aiErrorMessage">Gagal memuat analisis AI dari Ollama.</span>
                                            </div>
                                            <button type="button" class="btn btn-xs btn-outline-dark ml-2" id="btnCobaLagiAi">Coba Lagi</button>
                                        </div>
                                    </div>

                                    {{-- Result Content Area --}}
                                    <div id="aiResultContent" class="text-dark" style="line-height: 1.7; font-size: 0.95rem;">
                                        <!-- Analisis AI akan tampil di sini -->
                                    </div>
                                </div>
                                <div class="card-footer bg-light py-1 px-3 d-flex justify-content-between align-items-center small text-muted">
                                    <span id="aiFooterInfo">Analisis dihasilkan otomatis oleh AI (Ollama).</span>
                                    <span id="aiFooterWaktu"></span>
                                </div>
                            </div>

                            <div class="row">
                                {{-- Kolom Kiri: Daftar Kabupaten/Kota yang Mengalami Lonjakan pada Tanggal Tersebut --}}
                                <div class="col-lg-4 mb-3 mb-lg-0">
                                    <div class="border rounded h-100 d-flex flex-column">
                                        <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                            <span class="font-weight-bold small">
                                                <i class="fas fa-map-marker-alt text-danger mr-1"></i>
                                                Status Wilayah pada Tanggal Ini
                                            </span>
                                            <span class="badge badge-danger" id="badgeJmlKabLonjakan">0 wilayah</span>
                                        </div>
                                        <div class="table-responsive flex-fill" style="max-height: 350px;">
                                            <table class="table table-sm table-hover table-striped table-head-fixed mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Kabupaten/Kota</th>
                                                        <th class="text-right">Harga</th>
                                                        <th class="text-right">Selisih</th>
                                                        <th class="text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyDetailKabupatenKlik">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                {{-- Kolom Kanan: Berita & Percakapan X dalam 21 Hari Sebelum Lonjakan (Tab Navigasi) --}}
                                <div class="col-lg-8">
                                    <div class="card card-outline card-primary shadow-none border h-100 mb-0 d-flex flex-column">
                                        <div class="card-header p-2 bg-light d-flex justify-content-between align-items-center flex-wrap">
                                            <ul class="nav nav-pills" id="tabDetailMedia21Hari" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active py-1 px-3 font-weight-bold small" id="tab-berita-21-link" data-toggle="pill" href="#tab-berita-21" role="tab" aria-controls="tab-berita-21" aria-selected="true">
                                                        <i class="far fa-newspaper text-primary mr-1"></i> Berita &amp; Peristiwa (21 Hari)
                                                        <span class="badge badge-primary ml-1" id="badgeJmlBerita21Hari">0</span>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link py-1 px-3 font-weight-bold small text-dark" id="tab-x-21-link" data-toggle="pill" href="#tab-x-21" role="tab" aria-controls="tab-x-21" aria-selected="false">
                                                        <i class="fas fa-hashtag text-dark mr-1"></i> Percakapan X / Twitter (21 Hari)
                                                        <span class="badge badge-dark ml-1" id="badgeJmlX21Hari">0</span>
                                                    </a>
                                                </li>
                                            </ul>
                                            <span class="small text-muted d-none d-sm-inline">
                                                <i class="fas fa-history mr-1"></i>21 Hari Sebelum Titik Lonjakan
                                            </span>
                                        </div>
                                        <div class="card-body p-0 flex-fill">
                                            <div class="tab-content" id="tabContentMedia21Hari">
                                                {{-- Tab 1: Berita & Peristiwa 21 Hari --}}
                                                <div class="tab-pane fade show active" id="tab-berita-21" role="tabpanel" aria-labelledby="tab-berita-21-link">
                                                    <div class="table-responsive" style="max-height: 350px;">
                                                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 105px;">Tanggal</th>
                                                                    <th>Judul Berita &amp; Ringkasan Peristiwa</th>
                                                                    <th style="width: 120px;">Kategori</th>
                                                                    <th class="text-center" style="width: 95px;">Relevansi</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="tbodyBerita21Hari">
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                {{-- Tab 2: Percakapan X (Twitter) 21 Hari --}}
                                                <div class="tab-pane fade" id="tab-x-21" role="tabpanel" aria-labelledby="tab-x-21-link">
                                                    <div class="table-responsive" style="max-height: 350px;">
                                                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 105px;">Tanggal</th>
                                                                    <th>Isi Cuitan &amp; Ringkasan Teks</th>
                                                                    <th style="width: 120px;">Kategori</th>
                                                                    <th class="text-center" style="width: 95px;">Relevansi</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="tbodyX21Hari">
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Informasi Rata-rata Cuaca Harian --}}
        <div class="row mb-4" id="sectionCuacaHarian">
            <div class="col-12">
                <div class="card card-outline card-info shadow-sm mb-0">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-cloud-sun-rain text-info mr-1"></i>
                                Informasi Rata-rata Cuaca Harian
                                — {{ $selectedKabupaten === 'ALL' ? 'Rata-rata Seluruh Kabupaten/Kota' : $selectedKabupaten }}
                            </h3>
                            <span class="badge badge-info">
                                {{ $ringkasanCuaca->total_hari ?? 0 }} Hari Observasi Cuaca
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- 5 Info-Box Rata-rata Cuaca Periode (Tinggi Seragam) --}}
                        <div class="row mb-3">
                            <div class="col-12 col-sm-6 col-md-4 col-lg mb-2 mb-lg-0">
                                <div class="info-box bg-light border shadow-none h-100 mb-0">
                                    <span class="info-box-icon bg-danger">
                                        <i class="fas fa-thermometer-half"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text text-muted small font-weight-bold">Suhu Rata-rata</span>
                                        <span class="info-box-number h5 mb-0">
                                            {{ $ringkasanCuaca->suhu_rata_rata ?? '-' }} &deg;C
                                        </span>
                                        <small class="text-muted text-truncate">
                                            Min: {{ $ringkasanCuaca->suhu_minimum ?? '-' }}&deg;C | Maks: {{ $ringkasanCuaca->suhu_maksimum ?? '-' }}&deg;C
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-4 col-lg mb-2 mb-lg-0">
                                <div class="info-box bg-light border shadow-none h-100 mb-0">
                                    <span class="info-box-icon bg-info">
                                        <i class="fas fa-tint"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text text-muted small font-weight-bold">Kelembapan Udara</span>
                                        <span class="info-box-number h5 mb-0">
                                            {{ $ringkasanCuaca->kelembapan_rata_rata ?? '-' }} %
                                        </span>
                                        <small class="text-muted text-truncate">Rata-rata kelembapan harian</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-4 col-lg mb-2 mb-lg-0">
                                <div class="info-box bg-light border shadow-none h-100 mb-0">
                                    <span class="info-box-icon bg-primary">
                                        <i class="fas fa-cloud-showers-heavy"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text text-muted small font-weight-bold">Curah Hujan Harian</span>
                                        <span class="info-box-number h5 mb-0">
                                            {{ $ringkasanCuaca->curah_hujan_mm ?? '-' }} mm
                                        </span>
                                        <small class="text-muted text-truncate">Rata-rata curah hujan / hari</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6 col-md-6 col-lg mb-2 mb-md-0">
                                <div class="info-box bg-light border shadow-none h-100 mb-0">
                                    <span class="info-box-icon bg-secondary">
                                        <i class="far fa-clock"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text text-muted small font-weight-bold">Durasi Hujan</span>
                                        <span class="info-box-number h5 mb-0">
                                            {{ $ringkasanCuaca->durasi_hujan_jam ?? '-' }} jam/hari
                                        </span>
                                        <small class="text-muted text-truncate">Rata-rata durasi hujan harian</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-12 col-md-6 col-lg">
                                <div class="info-box bg-light border shadow-none h-100 mb-0">
                                    <span class="info-box-icon bg-teal">
                                        <i class="fas fa-wind"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text text-muted small font-weight-bold">Kecepatan Angin Maks</span>
                                        <span class="info-box-number h5 mb-0">
                                            {{ $ringkasanCuaca->kecepatan_angin_kmh ?? '-' }} km/jam
                                        </span>
                                        <small class="text-muted text-truncate">Rata-rata angin maksimum</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabel Riwayat Rata-rata Cuaca Harian --}}
                        <div class="table-responsive border rounded" style="max-height: 250px;">
                            <table class="table table-sm table-hover table-striped table-head-fixed mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Cakupan Wilayah</th>
                                        <th class="text-center">Suhu Rata-rata</th>
                                        <th class="text-center">Suhu Min / Maks</th>
                                        <th class="text-center">Kelembapan</th>
                                        <th class="text-center">Curah Hujan</th>
                                        <th class="text-center">Durasi Hujan</th>
                                        <th class="text-center">Kecepatan Angin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cuacaHarianTerbaru as $cRow)
                                        <tr>
                                            <td class="font-weight-bold">{{ \Carbon\Carbon::parse($cRow->tanggal)->format('d/m/Y') }}</td>
                                            <td>{{ $cRow->kabupaten_kota }}</td>
                                            <td class="text-center font-weight-bold text-danger">{{ $cRow->suhu_rata_rata }} &deg;C</td>
                                            <td class="text-center text-muted">{{ $cRow->suhu_minimum }} &deg;C / {{ $cRow->suhu_maksimum }} &deg;C</td>
                                            <td class="text-center text-info font-weight-bold">{{ $cRow->kelembapan_rata_rata }} %</td>
                                            <td class="text-center text-primary font-weight-bold">{{ $cRow->curah_hujan_mm }} mm</td>
                                            <td class="text-center">{{ $cRow->durasi_hujan_jam }} jam</td>
                                            <td class="text-center">{{ $cRow->kecepatan_angin_kmh }} km/jam</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-3">Tidak ada data cuaca harian pada rentang tanggal ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Baris Status Sinkronisasi Tanggal untuk Panel TF-IDF & Data Clean --}}
        <div class="alert alert-light border shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between py-2 px-3 mb-3" id="barisModeTanggalPanel">
            <div>
                <i class="far fa-calendar-check text-primary mr-1"></i>
                <strong>Cakupan Tanggal Panel TF-IDF &amp; Data Clean:</strong>
                <span class="badge badge-primary ml-1 px-2 py-1" id="badgeRentangPanelAktif">
                    Keseluruhan Rentang Filter ({{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }})
                </span>
            </div>
            <div class="mt-2 mt-md-0 d-none" id="grupTombolModeKlik">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary active" id="btnModeKlikHariH">
                        Tanggal Diklik Saja
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btnModeKlik21Hari">
                        21 Hari Sebelum Tanggal Diklik
                    </button>
                    <button type="button" class="btn btn-primary" id="btnKembalikanSemuaRentang">
                        <i class="fas fa-undo mr-1"></i> Keseluruhan Rentang Filter
                    </button>
                </div>
            </div>
        </div>

        {{-- Panel Hasil TF-IDF Berita dan X (Tinggi Seragam) --}}
        <div class="row mb-4" id="sectionTfidf">
            {{-- Panel TF-IDF Berita --}}
            <div class="col-lg-6 mb-3 mb-lg-0">
                <div class="card card-outline card-primary shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="far fa-newspaper text-primary mr-1"></i>
                                Hasil TF-IDF Berita Harian
                            </h3>
                            <div>
                                <span class="badge badge-light border mr-1" id="badgeTanggalTfidfBerita">
                                    {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                                </span>
                                <span class="badge badge-primary">Kategori: {{ $jenisKomoditasTfidf }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 flex-fill" style="height: 360px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0" id="tabelTfidfBerita">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Komoditas</th>
                                    <th>Term / Kata Kunci</th>
                                    <th class="text-center">Rank</th>
                                    <th class="text-right">Total TF-IDF</th>
                                    <th class="text-right">Rata-rata</th>
                                    <th class="text-center">Jml Berita</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detikTfidfHarian as $tfRow)
                                    <tr data-tanggal="{{ $tfRow->tanggal }}">
                                        <td class="small font-weight-bold text-nowrap">
                                            {{ \Carbon\Carbon::parse($tfRow->tanggal)->format('d/m/Y') }}
                                        </td>
                                        <td class="small">
                                            <span class="badge badge-light border">{{ $tfRow->komoditas ?: $tfRow->jenis_komoditas }}</span>
                                        </td>
                                        <td class="font-weight-bold text-primary">{{ $tfRow->term }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary">#{{ $tfRow->rank_term }}</span>
                                        </td>
                                        <td class="text-right small font-weight-bold">{{ number_format($tfRow->total_tfidf, 4, ',', '.') }}</td>
                                        <td class="text-right small text-muted">{{ number_format($tfRow->rata_rata_tfidf, 4, ',', '.') }}</td>
                                        <td class="text-center small">{{ $tfRow->jumlah_dokumen }}</td>
                                    </tr>
                                @empty
                                    <tr class="baris-kosong-default">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Tidak ada data TF-IDF Berita pada rentang tanggal dan komoditas ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light small text-muted" id="footerTfidfBerita">
                        Menampilkan <strong>{{ $detikTfidfHarian->count() }}</strong> baris kata kunci berita pada keseluruhan rentang filter.
                    </div>
                </div>
            </div>

            {{-- Panel TF-IDF X --}}
            <div class="col-lg-6">
                <div class="card card-outline card-dark shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-hashtag text-dark mr-1"></i>
                                Hasil TF-IDF X Harian
                            </h3>
                            <div>
                                <span class="badge badge-light border mr-1" id="badgeTanggalTfidfX">
                                    {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                                </span>
                                <span class="badge badge-dark">Kategori: {{ $jenisKomoditasTfidf }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 flex-fill" style="height: 360px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0" id="tabelTfidfX">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Komoditas</th>
                                    <th>Term / Kata Kunci</th>
                                    <th class="text-center">Rank</th>
                                    <th class="text-right">Total TF-IDF</th>
                                    <th class="text-right">Rata-rata</th>
                                    <th class="text-center">Jml Cuitan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($xTfidfHarian as $xRow)
                                    <tr data-tanggal="{{ $xRow->tanggal }}">
                                        <td class="small font-weight-bold text-nowrap">
                                            {{ \Carbon\Carbon::parse($xRow->tanggal)->format('d/m/Y') }}
                                        </td>
                                        <td class="small">
                                            <span class="badge badge-light border">{{ $xRow->komoditas ?: $xRow->jenis_komoditas }}</span>
                                        </td>
                                        <td class="font-weight-bold text-info">{{ $xRow->term }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary">#{{ $xRow->rank_term }}</span>
                                        </td>
                                        <td class="text-right small font-weight-bold">{{ number_format($xRow->total_tfidf, 4, ',', '.') }}</td>
                                        <td class="text-right small text-muted">{{ number_format($xRow->rata_rata_tfidf, 4, ',', '.') }}</td>
                                        <td class="text-center small">{{ $xRow->jumlah_dokumen }}</td>
                                    </tr>
                                @empty
                                    <tr class="baris-kosong-default">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Tidak ada data TF-IDF X pada rentang tanggal dan komoditas ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light small text-muted" id="footerTfidfX">
                        Menampilkan <strong>{{ $xTfidfHarian->count() }}</strong> baris kata kunci X pada keseluruhan rentang filter.
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Hasil Berita Clean & X Clean Tanpa Komoditas (Tinggi Seragam) --}}
        <div class="row mb-4" id="sectionCleanTanpaKomoditas">
            {{-- Berita Clean Tanpa Komoditas --}}
            <div class="col-lg-6 mb-3 mb-lg-0">
                <div class="card card-outline card-secondary shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-file-alt text-secondary mr-1"></i>
                                Berita Clean Tanpa Komoditas
                            </h3>
                            <div>
                                <span class="badge badge-light border mr-1" id="badgeTanggalBeritaClean">
                                    {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                                </span>
                                <span class="badge badge-secondary">
                                    Total Rentang: {{ number_format($totalBeritaTanpaKomoditas, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 flex-fill" style="height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0" id="tabelBeritaClean">
                            <thead>
                                <tr>
                                    <th style="width: 90px;">Tanggal</th>
                                    <th>Judul &amp; Ringkasan Teks</th>
                                    <th>Kategori / Cakupan</th>
                                    <th class="text-center">Relevansi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($beritaCleanTanpaKomoditas as $bRow)
                                    <tr data-tanggal="{{ $bRow->tanggal_terbit }}">
                                        <td class="small font-weight-bold text-nowrap">
                                            {{ \Carbon\Carbon::parse($bRow->tanggal_terbit)->format('d/m/Y') }}
                                            <small class="d-block text-muted">{{ $bRow->sumber }}</small>
                                        </td>
                                        <td class="small">
                                            @if(!empty($bRow->url))
                                                <a href="{{ $bRow->url }}" target="_blank" rel="noopener noreferrer" class="font-weight-bold text-dark">
                                                    {{ $bRow->judul }} <i class="fas fa-external-link-alt fa-xs text-muted ml-1"></i>
                                                </a>
                                            @else
                                                <span class="font-weight-bold">{{ $bRow->judul }}</span>
                                            @endif
                                            <div class="text-muted mt-1" style="font-size: 0.78rem;">
                                                {{ \Illuminate\Support\Str::limit($bRow->clean_text, 120) }}
                                            </div>
                                        </td>
                                        <td class="small">
                                            <span class="badge badge-light border d-inline-block mb-1">{{ $bRow->kategori ?: 'UMUM' }}</span>
                                            <small class="d-block text-muted">{{ $bRow->cakupan_berita }}</small>
                                        </td>
                                        <td class="text-center small">
                                            <span class="badge {{ $bRow->tingkat_relevansi === 'HIGH' ? 'badge-success' : ($bRow->tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary') }}">
                                                {{ $bRow->tingkat_relevansi }} ({{ $bRow->skor_relevansi }})
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="baris-kosong-default">
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Tidak ada data berita clean tanpa komoditas pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light small text-muted" id="footerBeritaClean">
                        Menampilkan <strong>{{ $beritaCleanTanpaKomoditas->count() }}</strong> berita tanpa komoditas pada keseluruhan rentang filter.
                    </div>
                </div>
            </div>

            {{-- X Clean Tanpa Komoditas --}}
            <div class="col-lg-6">
                <div class="card card-outline card-secondary shadow-sm h-100 mb-0 d-flex flex-column">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-comment-dots text-secondary mr-1"></i>
                                X Clean Tanpa Komoditas
                            </h3>
                            <div>
                                <span class="badge badge-light border mr-1" id="badgeTanggalXClean">
                                    {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                                </span>
                                <span class="badge badge-secondary">
                                    Total Rentang: {{ number_format($totalXTanpaKomoditas, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 flex-fill" style="height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-hover table-striped table-head-fixed mb-0" id="tabelXClean">
                            <thead>
                                <tr>
                                    <th style="width: 90px;">Tanggal</th>
                                    <th>Isi Cuitan &amp; Ringkasan Teks</th>
                                    <th>Kategori / Cakupan</th>
                                    <th class="text-center">Relevansi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($xCleanTanpaKomoditas as $xClean)
                                    <tr data-tanggal="{{ $xClean->tanggal }}">
                                        <td class="small font-weight-bold text-nowrap">
                                            {{ \Carbon\Carbon::parse($xClean->tanggal)->format('d/m/Y') }}
                                            <small class="d-block text-muted">ID #{{ $xClean->id_x }}</small>
                                        </td>
                                        <td class="small">
                                            <div class="text-dark">{{ \Illuminate\Support\Str::limit($xClean->body, 140) }}</div>
                                            <div class="text-muted mt-1" style="font-size: 0.78rem;">
                                                {{ \Illuminate\Support\Str::limit($xClean->text_clean, 120) }}
                                            </div>
                                        </td>
                                        <td class="small">
                                            <span class="badge badge-light border d-inline-block mb-1">{{ $xClean->kategori ?: 'UMUM' }}</span>
                                            <small class="d-block text-muted">{{ $xClean->cakupan_cuitan }}</small>
                                        </td>
                                        <td class="text-center small">
                                            <span class="badge {{ $xClean->tingkat_relevansi === 'HIGH' ? 'badge-success' : ($xClean->tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary') }}">
                                                {{ $xClean->tingkat_relevansi }} ({{ $xClean->skor_relevansi }})
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="baris-kosong-default">
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Tidak ada data X clean tanpa komoditas pada rentang tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light small text-muted" id="footerXClean">
                        Menampilkan <strong>{{ $xCleanTanpaKomoditas->count() }}</strong> cuitan tanpa komoditas pada keseluruhan rentang filter.
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel 1: Ringkasan Semua Komoditas pada Tanggal Terakhir --}}
        <div class="row mb-4" id="sectionTabelKomoditas">
            <div class="col-12">
                <div class="card card-outline card-info shadow-sm mb-0">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-layer-group text-info mr-1"></i>
                                Ringkasan Harga Lintas Komoditas pada Tanggal Terakhir ({{ \Carbon\Carbon::parse($tanggalSnapshot)->format('d M Y') }})
                                — {{ $selectedKabupaten === 'ALL' ? 'Seluruh Kabupaten/Kota Jawa Timur' : $selectedKabupaten }}
                            </h3>
                            <span class="small text-muted">Klik komoditas untuk memfilter grafik</span>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Komoditas</th>
                                    <th class="text-center">Cakupan Wilayah</th>
                                    <th class="text-right">Rata-rata Harga</th>
                                    <th class="text-right">Harga Terendah</th>
                                    <th class="text-right">Harga Tertinggi</th>
                                    <th class="text-right">Rata-rata Selisih Harian</th>
                                    <th class="text-center">Lonjakan Naik</th>
                                    <th class="text-center">Lonjakan Turun</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ringkasanSemuaKomoditas as $row)
                                    <tr class="{{ $row->komoditas_nama === $selectedKomoditas ? 'table-primary' : '' }}">
                                        <td class="font-weight-bold">
                                            {{ $row->komoditas_nama }}
                                            @if($row->komoditas_nama === $selectedKomoditas)
                                                <span class="badge badge-primary ml-1">Aktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $row->jml_kabupaten }} Kab/Kota</td>
                                        <td class="text-right font-weight-bold">
                                            Rp {{ number_format($row->rata_harga, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right text-success">
                                            Rp {{ number_format($row->min_harga, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right text-danger">
                                            Rp {{ number_format($row->max_harga, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right font-weight-bold {{ $row->rata_selisih > 0 ? 'text-danger' : ($row->rata_selisih < 0 ? 'text-success' : 'text-muted') }}">
                                            {{ $row->rata_selisih > 0 ? '+' : ($row->rata_selisih < 0 ? '-' : '') }}Rp {{ number_format(abs($row->rata_selisih), 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            @if($row->jml_lonjakan_naik > 0)
                                                <span class="badge badge-danger">{{ $row->jml_lonjakan_naik }} Kab/Kota</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($row->jml_lonjakan_turun > 0)
                                                <span class="badge badge-success">{{ $row->jml_lonjakan_turun }} Kab/Kota</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('dashboard', ['komoditas' => $row->komoditas_nama, 'kabupaten' => $selectedKabupaten, 'tanggal_mulai' => $tanggalMulai, 'tanggal_selesai' => $tanggalSelesai]) }}"
                                               class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-chart-line mr-1"></i>Lihat Grafik
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel 2: Perbandingan Harga & Status Lonjakan per Kabupaten/Kota untuk Komoditas Terpilih --}}
        <div class="row">
            <div class="col-12">
                <div class="card card-outline card-secondary shadow-sm mb-0">
                    <div class="card-header border-0">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                            <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                                <i class="fas fa-map-marked-alt text-secondary mr-1"></i>
                                Data Kabupaten/Kota untuk <strong>{{ $selectedKomoditas }}</strong> pada Tanggal <strong>{{ \Carbon\Carbon::parse($tanggalSnapshot)->format('d M Y') }}</strong>
                            </h3>
                            <div class="d-flex align-items-center">
                                <div class="input-group input-group-sm mr-2" style="width: 220px;">
                                    <input type="text" id="cariKabupatenInput" class="form-control" placeholder="Cari kabupaten/kota...">
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                    </div>
                                </div>
                                <select id="filterStatusKabupaten" class="form-control form-control-sm" style="width: 170px;">
                                    <option value="ALL">Semua Status Lonjakan</option>
                                    <option value="LONJAKAN_NAIK">Lonjakan Naik</option>
                                    <option value="LONJAKAN_TURUN">Lonjakan Turun</option>
                                    <option value="NORMAL">Normal</option>
                                    <option value="MAD_0">Stabil</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 420px;">
                        <table class="table table-hover table-striped table-head-fixed mb-0" id="tabelKabupatenTerbaru">
                            <thead>
                                <tr>
                                    <th>Kabupaten / Kota</th>
                                    <th>Tanggal</th>
                                    <th class="text-right">Harga Rata-rata</th>
                                    <th class="text-right">Selisih Harian</th>
                                    <th class="text-right">Batas Bawah MAD</th>
                                    <th class="text-right">Batas Atas MAD</th>
                                    <th class="text-center">Status Lonjakan</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ringkasanKabupatenTerbaru as $kabRow)
                                    <tr data-status="{{ $kabRow->status_lonjakan }}" class="{{ $kabRow->kabupaten === $selectedKabupaten ? 'table-info' : '' }}">
                                        <td class="font-weight-bold">{{ $kabRow->kabupaten }}</td>
                                        <td>{{ \Carbon\Carbon::parse($kabRow->tanggal)->format('d/m/Y') }}</td>
                                        <td class="text-right font-weight-bold">
                                            Rp {{ number_format($kabRow->harga, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right font-weight-bold {{ $kabRow->selisih > 0 ? 'text-danger' : ($kabRow->selisih < 0 ? 'text-success' : 'text-muted') }}">
                                            {{ $kabRow->selisih > 0 ? '+' : ($kabRow->selisih < 0 ? '-' : '') }}Rp {{ number_format(abs($kabRow->selisih), 0, ',', '.') }}
                                        </td>
                                        <td class="text-right text-muted">
                                            Rp {{ number_format($kabRow->batas_bawah, 0, ',', '.') }}
                                        </td>
                                        <td class="text-right text-muted">
                                            Rp {{ number_format($kabRow->batas_atas, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            @if($kabRow->status_lonjakan === 'LONJAKAN_NAIK')
                                                <span class="badge badge-danger px-2 py-1">
                                                    <i class="fas fa-arrow-up mr-1"></i>Lonjakan Naik
                                                </span>
                                            @elseif($kabRow->status_lonjakan === 'LONJAKAN_TURUN')
                                                <span class="badge badge-success px-2 py-1">
                                                    <i class="fas fa-arrow-down mr-1"></i>Lonjakan Turun
                                                </span>
                                            @elseif($kabRow->status_lonjakan === 'NORMAL')
                                                <span class="badge badge-primary px-2 py-1">Normal</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1">Stabil</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('dashboard', ['komoditas' => $selectedKomoditas, 'kabupaten' => $kabRow->kabupaten, 'tanggal_mulai' => $tanggalMulai, 'tanggal_selesai' => $tanggalSelesai]) }}"
                                               class="btn btn-xs btn-outline-secondary">
                                                Fokus Wilayah
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center small text-muted">
                        <span id="infoJumlahKabupaten">
                            Menampilkan <strong>{{ $ringkasanKabupatenTerbaru->count() }}</strong> Kabupaten/Kota.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        /* Paksa seluruh kontainer AdminLTE melebar penuh satu layar */
        body.layout-top-nav .wrapper .content-wrapper > .content-header > .container,
        body.layout-top-nav .wrapper .content-wrapper > .content > .container,
        body.layout-top-nav .wrapper .main-header > .container {
            max-width: 100% !important;
            width: 100% !important;
            padding-left: 1.5rem !important;
            padding-right: 1.5rem !important;
        }
        body.layout-top-nav .content-wrapper,
        body.layout-top-nav .main-header,
        body.layout-top-nav .main-footer {
            margin-left: 0 !important;
        }

        /* Seragamkan tinggi & tata letak 4 kartu KPI */
        .kpi-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-radius: 0.4rem;
            overflow: hidden;
        }
        .kpi-card .inner {
            padding: 1.15rem 1.25rem;
            flex: 1 1 auto;
        }
        .kpi-card .inner h3 {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            white-space: nowrap;
        }
        .kpi-card .small-box-footer {
            padding: 0.45rem 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card {
            border-radius: 0.4rem;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .baris-lonjakan-klik:hover {
            background-color: #fff3cd !important;
        }

        /* Styling Tabel Matriks Multidimensi AI */
        .ai-matrix-table {
            font-size: 0.90rem;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        .ai-matrix-table thead th {
            background-color: #212529 !important;
            color: #ffffff !important;
            border-color: #32383e !important;
            font-weight: 600;
            letter-spacing: 0.3px;
            font-size: 0.88rem;
        }
        .ai-matrix-table td, .ai-matrix-table th {
            padding: 0.75rem 0.85rem;
            vertical-align: top !important;
            line-height: 1.55;
        }
        .ai-matrix-table tbody tr:hover td {
            background-color: #f8f9fc !important;
        }
        .ai-matrix-table td:first-child {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #1a252f;
            border-right: 2px solid #e9ecef;
            width: 19%;
        }

        .ai-callout-summary {
            background-color: #f4f8fd;
            border-left: 4px solid #007bff;
            border-radius: 4px;
            padding: 0.9rem 1.15rem;
        }
        .ai-callout-recommendation {
            background-color: #fffcf4;
            border-left: 4px solid #fd7e14;
            border-radius: 4px;
            padding: 0.9rem 1.15rem;
        }

        /* Nav Pills Tab Berita & X 21 Hari */
        #tabDetailMedia21Hari .nav-link {
            border-radius: 20px;
            margin-right: 6px;
            border: 1px solid transparent;
            transition: all 0.2s ease-in-out;
        }
        #tabDetailMedia21Hari .nav-link:not(.active) {
            background-color: #ffffff;
            border-color: #ced4da;
            color: #495057;
        }
        #tabDetailMedia21Hari .nav-link:not(.active):hover {
            background-color: #e9ecef;
            color: #212529;
        }
        #tabDetailMedia21Hari .nav-link.active {
            box-shadow: 0 2px 4px rgba(0,0,0,0.12);
        }

        @media (max-width: 576px) {
            .kpi-card .inner h3 {
                font-size: 1.4rem;
            }
        }
    </style>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var chartPayload = @json($chartPayload);
            var komoditasNama = @json($selectedKomoditas);
            var kabupatenTerpilih = @json($selectedKabupaten);
            var satuanKomoditas = @json($satuanKomoditas);
            var tanggalMulaiFilterFmt = @json(\Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y'));
            var tanggalSelesaiFilterFmt = @json(\Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y'));
            var detailEndpointUrl = @json(route('dashboard.detail'));
            var aiEndpointUrl = @json(route('dashboard.analisis_ai'));
            var aiAbortController = null;

            var filterForm = document.getElementById('filterDashboardForm');
            var loadingBadge = document.getElementById('autoFilterLoadingBadge');

            function submitFilterOtomatis() {
                if (loadingBadge) {
                    loadingBadge.classList.remove('d-none');
                }
                filterForm.submit();
            }

            // Update otomatis ketika dropdown komoditas, kabupaten, atau tanggal diubah
            document.querySelectorAll('.auto-submit-filter').forEach(function (el) {
                el.addEventListener('change', function () {
                    submitFilterOtomatis();
                });
            });

            // Tombol pintasan periode tanggal
            document.querySelectorAll('.btn-quick-range').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('tanggal_mulai').value = this.getAttribute('data-start');
                    document.getElementById('tanggal_selesai').value = this.getAttribute('data-end');
                    submitFilterOtomatis();
                });
            });

            function formatRupiah(angka) {
                if (angka === null || angka === undefined || isNaN(angka)) return '-';
                var sign = angka < 0 ? '-' : '';
                var absVal = Math.round(Math.abs(Number(angka)));
                return sign + 'Rp ' + absVal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function formatTanggalIndo(ymd) {
                if (!ymd) return '-';
                var parts = ymd.split('-');
                if (parts.length !== 3) return ymd;
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }

            // State untuk sinkronisasi tanggal pada panel TF-IDF & Data Clean
            var currentClickedDate = null;
            var currentStart21Date = null;
            var currentPanelMode = 'HARI_H';
            var lastClickedApiData = null;

            // Simpan isi awal (keseluruhan rentang filter) dari keempat tabel
            var initialTableHtml = {
                tabelTfidfBerita: document.querySelector('#tabelTfidfBerita tbody').innerHTML,
                tabelTfidfX: document.querySelector('#tabelTfidfX tbody').innerHTML,
                tabelBeritaClean: document.querySelector('#tabelBeritaClean tbody').innerHTML,
                tabelXClean: document.querySelector('#tabelXClean tbody').innerHTML
            };
            var initialTableCounts = {
                tabelTfidfBerita: document.querySelectorAll('#tabelTfidfBerita tbody tr[data-tanggal]').length,
                tabelTfidfX: document.querySelectorAll('#tabelTfidfX tbody tr[data-tanggal]').length,
                tabelBeritaClean: document.querySelectorAll('#tabelBeritaClean tbody tr[data-tanggal]').length,
                tabelXClean: document.querySelectorAll('#tabelXClean tbody tr[data-tanggal]').length
            };

            function renderClickedTablesFromApi(mode) {
                currentPanelMode = mode;
                var badgeUtama = document.getElementById('badgeRentangPanelAktif');
                var grupTombol = document.getElementById('grupTombolModeKlik');
                var btnHariH = document.getElementById('btnModeKlikHariH');
                var btn21Hari = document.getElementById('btnModeKlik21Hari');

                if (mode === 'ALL' || !currentClickedDate) {
                    if (grupTombol) grupTombol.classList.add('d-none');
                    if (badgeUtama) {
                        badgeUtama.className = 'badge badge-primary ml-1 px-2 py-1';
                        badgeUtama.textContent = 'Keseluruhan Rentang Filter (' + tanggalMulaiFilterFmt + ' – ' + tanggalSelesaiFilterFmt + ')';
                    }
                    // Kembalikan tabel ke keseluruhan rentang filter
                    [
                        { id: 'tabelTfidfBerita', badge: 'badgeTanggalTfidfBerita', footer: 'footerTfidfBerita', label: 'kata kunci berita' },
                        { id: 'tabelTfidfX', badge: 'badgeTanggalTfidfX', footer: 'footerTfidfX', label: 'kata kunci X' },
                        { id: 'tabelBeritaClean', badge: 'badgeTanggalBeritaClean', footer: 'footerBeritaClean', label: 'berita tanpa komoditas' },
                        { id: 'tabelXClean', badge: 'badgeTanggalXClean', footer: 'footerXClean', label: 'cuitan tanpa komoditas' }
                    ].forEach(function (cfg) {
                        document.querySelector('#' + cfg.id + ' tbody').innerHTML = initialTableHtml[cfg.id];
                        var bEl = document.getElementById(cfg.badge);
                        var fEl = document.getElementById(cfg.footer);
                        if (bEl) {
                            bEl.textContent = tanggalMulaiFilterFmt + ' – ' + tanggalSelesaiFilterFmt;
                            bEl.className = 'badge badge-light border mr-1';
                        }
                        if (fEl) {
                            fEl.innerHTML = 'Menampilkan <strong>' + initialTableCounts[cfg.id] + '</strong> baris ' + cfg.label + ' pada keseluruhan rentang filter.';
                        }
                    });
                    return;
                }

                if (grupTombol) grupTombol.classList.remove('d-none');
                if (mode === 'HARI_H') {
                    btnHariH.classList.add('active');
                    btn21Hari.classList.remove('active');
                    badgeUtama.className = 'badge badge-warning ml-1 px-2 py-1';
                    badgeUtama.textContent = 'Tanggal Titik Diklik (' + formatTanggalIndo(currentClickedDate) + ')';
                } else {
                    btnHariH.classList.remove('active');
                    btn21Hari.classList.add('active');
                    badgeUtama.className = 'badge badge-info ml-1 px-2 py-1';
                    badgeUtama.textContent = '21 Hari Sebelum Tanggal Diklik (' + formatTanggalIndo(currentStart21Date) + ' – ' + formatTanggalIndo(currentClickedDate) + ')';
                }

                if (!lastClickedApiData) return;

                // 1. Render TF-IDF Berita
                var listTfBerita = (lastClickedApiData.tfidf_berita_klik || []).filter(function (r) {
                    return mode === 'HARI_H' ? (r.tanggal === currentClickedDate) : true;
                });
                var tbodyTfBerita = document.querySelector('#tabelTfidfBerita tbody');
                if (listTfBerita.length === 0) {
                    tbodyTfBerita.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data kata kunci berita pada ' + (mode === 'HARI_H' ? 'tanggal ' + formatTanggalIndo(currentClickedDate) : 'rentang 21 hari ini') + '.</td></tr>';
                } else {
                    tbodyTfBerita.innerHTML = listTfBerita.map(function (r) {
                        return '<tr data-tanggal="' + r.tanggal + '">' +
                            '<td class="small font-weight-bold text-nowrap">' + r.tanggal_fmt + '</td>' +
                            '<td class="small"><span class="badge badge-light border">' + (r.komoditas || r.jenis_komoditas || '-') + '</span></td>' +
                            '<td class="font-weight-bold text-primary">' + r.term + '</td>' +
                            '<td class="text-center"><span class="badge badge-secondary">#' + r.rank_term + '</span></td>' +
                            '<td class="text-right small font-weight-bold">' + Number(r.total_tfidf).toFixed(4).replace('.', ',') + '</td>' +
                            '<td class="text-right small text-muted">' + Number(r.rata_rata_tfidf).toFixed(4).replace('.', ',') + '</td>' +
                            '<td class="text-center small">' + r.jumlah_dokumen + '</td>' +
                            '</tr>';
                    }).join('');
                }

                // 2. Render TF-IDF X
                var listTfX = (lastClickedApiData.tfidf_x_klik || []).filter(function (r) {
                    return mode === 'HARI_H' ? (r.tanggal === currentClickedDate) : true;
                });
                var tbodyTfX = document.querySelector('#tabelTfidfX tbody');
                if (listTfX.length === 0) {
                    tbodyTfX.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data kata kunci X pada ' + (mode === 'HARI_H' ? 'tanggal ' + formatTanggalIndo(currentClickedDate) : 'rentang 21 hari ini') + '.</td></tr>';
                } else {
                    tbodyTfX.innerHTML = listTfX.map(function (r) {
                        return '<tr data-tanggal="' + r.tanggal + '">' +
                            '<td class="small font-weight-bold text-nowrap">' + r.tanggal_fmt + '</td>' +
                            '<td class="small"><span class="badge badge-light border">' + (r.komoditas || r.jenis_komoditas || '-') + '</span></td>' +
                            '<td class="font-weight-bold text-info">' + r.term + '</td>' +
                            '<td class="text-center"><span class="badge badge-secondary">#' + r.rank_term + '</span></td>' +
                            '<td class="text-right small font-weight-bold">' + Number(r.total_tfidf).toFixed(4).replace('.', ',') + '</td>' +
                            '<td class="text-right small text-muted">' + Number(r.rata_rata_tfidf).toFixed(4).replace('.', ',') + '</td>' +
                            '<td class="text-center small">' + r.jumlah_dokumen + '</td>' +
                            '</tr>';
                    }).join('');
                }

                // 3. Render Berita Clean Tanpa Komoditas
                var listBeritaClean = (lastClickedApiData.berita_clean_klik || []).filter(function (r) {
                    return mode === 'HARI_H' ? (r.tanggal_terbit === currentClickedDate) : true;
                });
                var tbodyBeritaClean = document.querySelector('#tabelBeritaClean tbody');
                if (listBeritaClean.length === 0) {
                    tbodyBeritaClean.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada berita clean tanpa komoditas pada ' + (mode === 'HARI_H' ? 'tanggal ' + formatTanggalIndo(currentClickedDate) : 'rentang 21 hari ini') + '.</td></tr>';
                } else {
                    tbodyBeritaClean.innerHTML = listBeritaClean.map(function (b) {
                        var badgeRel = b.tingkat_relevansi === 'HIGH' ? 'badge-success' : (b.tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary');
                        var judulHtml = b.url
                            ? '<a href="' + b.url + '" target="_blank" rel="noopener noreferrer" class="font-weight-bold text-dark">' + b.judul + ' <i class="fas fa-external-link-alt fa-xs text-muted ml-1"></i></a>'
                            : '<span class="font-weight-bold">' + b.judul + '</span>';
                        var snippet = b.clean_text ? (b.clean_text.length > 120 ? b.clean_text.substring(0, 120) + '...' : b.clean_text) : '';
                        return '<tr data-tanggal="' + b.tanggal_terbit + '">' +
                            '<td class="small font-weight-bold text-nowrap">' + b.tanggal_fmt + '<small class="d-block text-muted">' + (b.sumber || '') + '</small></td>' +
                            '<td class="small">' + judulHtml + '<div class="text-muted mt-1" style="font-size: 0.78rem;">' + snippet + '</div></td>' +
                            '<td class="small"><span class="badge badge-light border d-inline-block mb-1">' + (b.kategori || 'UMUM') + '</span><small class="d-block text-muted">' + (b.cakupan_berita || '') + '</small></td>' +
                            '<td class="text-center small"><span class="badge ' + badgeRel + '">' + b.tingkat_relevansi + ' (' + b.skor_relevansi + ')</span></td>' +
                            '</tr>';
                    }).join('');
                }

                // 4. Render X Clean Tanpa Komoditas
                var listXClean = (lastClickedApiData.x_clean_klik || []).filter(function (r) {
                    return mode === 'HARI_H' ? (r.tanggal === currentClickedDate) : true;
                });
                var tbodyXClean = document.querySelector('#tabelXClean tbody');
                if (listXClean.length === 0) {
                    tbodyXClean.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada cuitan X clean tanpa komoditas pada ' + (mode === 'HARI_H' ? 'tanggal ' + formatTanggalIndo(currentClickedDate) : 'rentang 21 hari ini') + '.</td></tr>';
                } else {
                    tbodyXClean.innerHTML = listXClean.map(function (x) {
                        var badgeRel = x.tingkat_relevansi === 'HIGH' ? 'badge-success' : (x.tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary');
                        var bodySnippet = x.body ? (x.body.length > 140 ? x.body.substring(0, 140) + '...' : x.body) : '';
                        var cleanSnippet = x.text_clean ? (x.text_clean.length > 120 ? x.text_clean.substring(0, 120) + '...' : x.text_clean) : '';
                        return '<tr data-tanggal="' + x.tanggal + '">' +
                            '<td class="small font-weight-bold text-nowrap">' + x.tanggal_fmt + '<small class="d-block text-muted">ID #' + x.id_x + '</small></td>' +
                            '<td class="small"><div class="text-dark">' + bodySnippet + '</div><div class="text-muted mt-1" style="font-size: 0.78rem;">' + cleanSnippet + '</div></td>' +
                            '<td class="small"><span class="badge badge-light border d-inline-block mb-1">' + (x.kategori || 'UMUM') + '</span><small class="d-block text-muted">' + (x.cakupan_cuitan || '') + '</small></td>' +
                            '<td class="text-center small"><span class="badge ' + badgeRel + '">' + x.tingkat_relevansi + ' (' + x.skor_relevansi + ')</span></td>' +
                            '</tr>';
                    }).join('');
                }

                // Update badge & footer keempat panel
                [
                    { badge: 'badgeTanggalTfidfBerita', footer: 'footerTfidfBerita', count: listTfBerita.length, label: 'kata kunci berita' },
                    { badge: 'badgeTanggalTfidfX', footer: 'footerTfidfX', count: listTfX.length, label: 'kata kunci X' },
                    { badge: 'badgeTanggalBeritaClean', footer: 'footerBeritaClean', count: listBeritaClean.length, label: 'berita tanpa komoditas' },
                    { badge: 'badgeTanggalXClean', footer: 'footerXClean', count: listXClean.length, label: 'cuitan tanpa komoditas' }
                ].forEach(function (cfg) {
                    var bEl = document.getElementById(cfg.badge);
                    var fEl = document.getElementById(cfg.footer);
                    if (bEl) {
                        if (mode === 'HARI_H') {
                            bEl.textContent = 'Tanggal Klik: ' + formatTanggalIndo(currentClickedDate);
                            bEl.className = 'badge badge-warning mr-1';
                        } else {
                            bEl.textContent = formatTanggalIndo(currentStart21Date) + ' – ' + formatTanggalIndo(currentClickedDate);
                            bEl.className = 'badge badge-info mr-1';
                        }
                    }
                    if (fEl) {
                        if (mode === 'HARI_H') {
                            fEl.innerHTML = 'Menampilkan <strong>' + cfg.count + '</strong> baris ' + cfg.label + ' pada tanggal yang diklik (<strong>' + formatTanggalIndo(currentClickedDate) + '</strong>).';
                        } else {
                            fEl.innerHTML = 'Menampilkan <strong>' + cfg.count + '</strong> baris ' + cfg.label + ' dalam 21 hari sebelum <strong>' + formatTanggalIndo(currentClickedDate) + '</strong>.';
                        }
                    }
                });
            }

            // Parser konversi Markdown AI ke format Tabel & Kartu HTML Kolom
            function formatMarkdownToHtml(md) {
                if (!md) return '';

                var text = md;

                // 1. Ekstrak & parse tabel Markdown menjadi Tabel AdminLTE per Kolom yang rapi
                var tableRegex = /((?:^|\n)\|[^\n]+\|\r?\n\|[-:\s|]+\|\r?\n(?:\|[^\n]+\|\r?\n?)+)/g;
                text = text.replace(tableRegex, function (match) {
                    var lines = match.trim().split(/\r?\n/);
                    if (lines.length < 3) return match;

                    var headerCells = lines[0].split('|').map(function (c) { return c.trim(); }).filter(function (c, i, a) { return i > 0 && i < a.length - 1; });
                    var dataRows = lines.slice(2);

                    var theadHtml = '<thead class="thead-dark"><tr>';
                    headerCells.forEach(function (h, idx) {
                        var styleWidth = idx === 0 ? 'width: 18%;' : (idx === 1 ? 'width: 32%;' : (idx === 2 ? 'width: 30%;' : 'width: 20%;'));
                        theadHtml += '<th class="text-nowrap align-middle" style="' + styleWidth + '">' + h + '</th>';
                    });
                    theadHtml += '</tr></thead>';

                    var tbodyHtml = '<tbody>';
                    dataRows.forEach(function (rowStr) {
                        var cells = rowStr.split('|').map(function (c) { return c.trim(); }).filter(function (c, i, a) { return i > 0 && i < a.length - 1; });
                        if (cells.length === 0) return;
                        tbodyHtml += '<tr>';
                        cells.forEach(function (cellText, idx) {
                            var cellHtml = cellText
                                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                                .replace(/•/g, '<span class="text-danger mr-1">•</span>');

                            if (idx === 0) {
                                tbodyHtml += '<td class="align-top font-weight-bold bg-light text-dark">' + cellHtml + '</td>';
                            } else {
                                tbodyHtml += '<td class="align-top">' + cellHtml + '</td>';
                            }
                        });
                        tbodyHtml += '</tr>';
                    });
                    tbodyHtml += '</tbody>';

                    return '<div class="table-responsive my-3 shadow-sm border rounded bg-white">' +
                           '<table class="table table-bordered table-striped table-hover mb-0 ai-matrix-table">' +
                           theadHtml + tbodyHtml +
                           '</table>' +
                           '</div>';
                });

                // 2. Format Header Khusus
                text = text
                    .replace(/^### Ringkasan Eksekutif(.*$)/gim, '<div class="ai-callout-summary mb-3"><h5 class="font-weight-bold text-primary mb-2"><i class="fas fa-lightbulb mr-2"></i>Ringkasan Eksekutif</h5>')
                    .replace(/^### Matriks Korelasi Multidimensi(.*$)/gim, '<h5 class="font-weight-bold text-dark mt-4 mb-2"><i class="fas fa-table mr-2 text-danger"></i>Matriks Korelasi Multidimensi (Cuaca, Berita, X &amp; TF-IDF)</h5>')
                    .replace(/^### Rekomendasi Strategis & Antisipasi(.*$)/gim, '<div class="ai-callout-recommendation mt-3 mb-2"><h5 class="font-weight-bold text-warning text-dark mb-2"><i class="fas fa-tasks mr-2 text-warning"></i>Rekomendasi Strategis &amp; Antisipasi</h5>')
                    .replace(/^### (.*$)/gim, '<h5 class="font-weight-bold text-dark mt-3 mb-2">$1</h5>')
                    .replace(/^## (.*$)/gim, '<h4 class="font-weight-bold text-dark mt-3 mb-2">$1</h4>')
                    .replace(/^# (.*$)/gim, '<h3 class="font-weight-bold text-dark mt-3 mb-2">$1</h3>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/^\s*-\s+(.*$)/gim, '<li class="mb-1">$1</li>')
                    .replace(/^\s*(\d+)\.\s+(.*$)/gim, '<li class="mb-1"><strong>$1.</strong> $2</li>');

                // Tutup div callout jika ada
                if (text.indexOf('ai-callout-summary') !== -1 && text.indexOf('ai-matrix-table') !== -1) {
                    text = text.replace(/<div class="table-responsive/, '</div><div class="table-responsive');
                }
                if (text.indexOf('ai-callout-recommendation') !== -1) {
                    text += '</div>';
                }

                // Bungkus list item
                text = text.replace(/(<li class="mb-1">.*?<\/li>\s*)+/gs, function (match) {
                    return '<ul class="pl-3 mb-2">' + match + '</ul>';
                });

                // Paragraf umum
                var blocks = text.split(/\n\s*\n/);
                var formatted = blocks.map(function (b) {
                    b = b.trim();
                    if (!b) return '';
                    if (b.startsWith('<div') || b.startsWith('<table') || b.startsWith('<h') || b.startsWith('<ul') || b.startsWith('<ol')) {
                        return b;
                    }
                    return '<p class="mb-2">' + b.replace(/\n/g, '<br>') + '</p>';
                }).join('');

                return formatted;
            }

            // Fungsi memanggil API Analisis AI (Ollama)
            function mintaAnalisisAi(tanggalYmd) {
                if (!tanggalYmd) return;

                var loadingEl = document.getElementById('aiLoadingState');
                var errorEl = document.getElementById('aiErrorState');
                var errorMsgEl = document.getElementById('aiErrorMessage');
                var contentEl = document.getElementById('aiResultContent');
                var footerInfoEl = document.getElementById('aiFooterInfo');
                var footerWaktuEl = document.getElementById('aiFooterWaktu');
                var indikatorRow = document.getElementById('aiIndikatorBadgesRow');

                if (aiAbortController) {
                    aiAbortController.abort();
                }
                aiAbortController = new AbortController();

                loadingEl.classList.remove('d-none');
                errorEl.classList.add('d-none');
                if (indikatorRow) indikatorRow.classList.add('d-none');
                contentEl.innerHTML = '';
                footerInfoEl.textContent = 'Menghubungi AI Ollama untuk menganalisis data cuaca, berita, X, dan TF-IDF...';
                footerWaktuEl.textContent = '';

                var url = aiEndpointUrl +
                    '?komoditas=' + encodeURIComponent(komoditasNama) +
                    '&kabupaten=' + encodeURIComponent(kabupatenTerpilih) +
                    '&tanggal=' + encodeURIComponent(tanggalYmd);

                fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: aiAbortController.signal
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, status: res.status, data: data };
                        });
                    })
                    .then(function (resObj) {
                        loadingEl.classList.add('d-none');
                        if (!resObj.ok || resObj.data.status !== 'ok') {
                            var errMsg = (resObj.data && resObj.data.message) ? resObj.data.message : ('Gagal memuat analisis AI (Kode status: ' + resObj.status + ')');
                            errorMsgEl.textContent = errMsg;
                            errorEl.classList.remove('d-none');
                            footerInfoEl.textContent = 'Gagal memuat analisis.';
                            return;
                        }

                        var d = resObj.data;
                        contentEl.innerHTML = formatMarkdownToHtml(d.analisis);

                        // Tampilkan indikator masukan
                        if (indikatorRow && d.indikator) {
                            indikatorRow.classList.remove('d-none');
                            var cuacaEl = document.getElementById('badgeAiCuaca');
                            var beritaEl = document.getElementById('badgeAiBerita');
                            var xEl = document.getElementById('badgeAiX');
                            var tfidfEl = document.getElementById('badgeAiTfidf');

                            if (cuacaEl) {
                                cuacaEl.innerHTML = '<i class="fas fa-cloud-sun-rain mr-1"></i>Cuaca 21 Hari: Suhu ' + d.indikator.cuaca_suhu_rata + '°C | Hujan ' + d.indikator.cuaca_hujan_total_mm + ' mm';
                            }
                            if (beritaEl) {
                                beritaEl.innerHTML = '<i class="far fa-newspaper mr-1"></i>' + d.total_berita_dianalisis + ' Berita (Med/High)';
                            }
                            if (xEl) {
                                xEl.innerHTML = '<i class="fas fa-hashtag mr-1"></i>' + d.total_x_dianalisis + ' Cuitan X (Med/High)';
                            }
                            if (tfidfEl) {
                                var terms = (d.indikator.top_tfidf_berita || []).slice(0, 4).join(', ');
                                tfidfEl.innerHTML = '<i class="fas fa-tags text-secondary mr-1"></i>TF-IDF Dominan: <strong>' + (terms || '-') + '</strong>';
                            }
                        }

                        footerInfoEl.innerHTML = 'Dianalisis dari <strong>' + d.total_berita_dianalisis + '</strong> berita &amp; <strong>' + d.total_x_dianalisis + '</strong> cuitan X dalam 21 hari sebelumnya.';
                        if (d.waktu_proses_detik) {
                            footerWaktuEl.innerHTML = '<i class="far fa-clock mr-1"></i>Waktu respons: ' + d.waktu_proses_detik + ' dtk';
                        }
                    })
                    .catch(function (err) {
                        if (err.name === 'AbortError') return;
                        loadingEl.classList.add('d-none');
                        errorMsgEl.textContent = 'Terjadi kendala koneksi saat menghubungi server analisis AI.';
                        errorEl.classList.remove('d-none');
                        footerInfoEl.textContent = 'Koneksi terputus.';
                    });
            }

            // Fungsi membuka Detail Lonjakan & memuat Berita 21 Hari Sebelumnya
            function bukaDetailLonjakanPadaTanggal(tanggalYmd, indexTitik) {
                if (!tanggalYmd) return;

                // Hitung tanggal 21 hari sebelumnya di sisi klien terlebih dahulu untuk filter tabel TF-IDF & Clean
                var dt = new Date(tanggalYmd + 'T00:00:00');
                var dt21 = new Date(dt.getTime() - (21 * 24 * 60 * 60 * 1000));
                var y = dt21.getFullYear();
                var m = String(dt21.getMonth() + 1).padStart(2, '0');
                var d = String(dt21.getDate()).padStart(2, '0');

                currentClickedDate = tanggalYmd;
                currentStart21Date = y + '-' + m + '-' + d;

                // Tampilkan panel Detail Lonjakan & Berita 21 Hari Sebelumnya
                var sectionDetail = document.getElementById('sectionDetailLonjakan21Hari');
                var loadingEl = document.getElementById('loadingDetailLonjakan');
                var kontenEl = document.getElementById('kontenDetailLonjakan');

                sectionDetail.classList.remove('d-none');
                loadingEl.classList.remove('d-none');
                kontenEl.classList.add('d-none');

                document.getElementById('labelTanggalKlikDetail').textContent = formatTanggalIndo(tanggalYmd);
                document.getElementById('labelRentang21HariDetail').textContent =
                    'Jendela Berita 21 Hari: ' + formatTanggalIndo(currentStart21Date) + ' s/d ' + formatTanggalIndo(tanggalYmd);

                // Jika indexTitik diketahui dari chartPayload, isi ringkasan langsung
                if (typeof indexTitik === 'number' && indexTitik >= 0 && chartPayload.harga[indexTitik] !== undefined) {
                    var hrg = chartPayload.harga[indexTitik];
                    var sls = chartPayload.selisih[indexTitik];
                    var st = chartPayload.status[indexTitik];
                    var bAtas = chartPayload.batas_atas[indexTitik];
                    var bBawah = chartPayload.batas_bawah[indexTitik];

                    document.getElementById('infoKlikTanggal').textContent =
                        formatTanggalIndo(tanggalYmd) + ' (' + chartPayload.labels[indexTitik] + ')';
                    document.getElementById('infoKlikHarga').textContent =
                        formatRupiah(hrg) + ' / ' + satuanKomoditas;
                    document.getElementById('infoKlikSelisih').innerHTML =
                        'Perubahan harian: <strong class="' + (sls >= 0 ? 'text-danger' : 'text-success') + '">' +
                        (sls > 0 ? '+' : '') + formatRupiah(sls) + '</strong>';
                    document.getElementById('infoKlikStatus').innerHTML =
                        st === 'LONJAKAN_NAIK'
                            ? '<span class="badge badge-danger"><i class="fas fa-arrow-up mr-1"></i>Lonjakan Naik</span>'
                            : (st === 'LONJAKAN_TURUN'
                                ? '<span class="badge badge-success"><i class="fas fa-arrow-down mr-1"></i>Lonjakan Turun</span>'
                                : '<span class="badge badge-primary">' + st + '</span>');
                    var hBawah = chartPayload.harga_batas_bawah ? chartPayload.harga_batas_bawah[indexTitik] : null;
                    var hAtas = chartPayload.harga_batas_atas ? chartPayload.harga_batas_atas[indexTitik] : null;
                    document.getElementById('infoKlikBatasMad').textContent = (bAtas === null || bBawah === null)
                        ? 'Batas MAD: belum tersedia (data belum mencukupi 21 hari)'
                        : 'Rentang harga normal: ' + formatRupiah(hBawah) + ' s/d ' + formatRupiah(hAtas) +
                          ' (selisih normal ' + formatRupiah(bBawah) + ' s/d ' + formatRupiah(bAtas) + ')';
                } else {
                    var idxFound = chartPayload.tanggal_raw ? chartPayload.tanggal_raw.indexOf(tanggalYmd) : -1;
                    if (idxFound !== -1) {
                        return bukaDetailLonjakanPadaTanggal(tanggalYmd, idxFound);
                    }
                }

                sectionDetail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // Panggil Analisis AI secara asinkron untuk tanggal ini
                mintaAnalisisAi(tanggalYmd);

                var url = detailEndpointUrl +
                    '?komoditas=' + encodeURIComponent(komoditasNama) +
                    '&kabupaten=' + encodeURIComponent(kabupatenTerpilih) +
                    '&tanggal=' + encodeURIComponent(tanggalYmd);

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        loadingEl.classList.add('d-none');
                        kontenEl.classList.remove('d-none');

                        if (data.status !== 'ok') return;

                        lastClickedApiData = data;
                        // Sesuaikan tanggal pada panel Data Clean dan TF-IDF sesuai tanggal yang diklik user
                        renderClickedTablesFromApi('HARI_H');

                        document.getElementById('infoKlikJendela21').textContent =
                            formatTanggalIndo(data.tanggal_mulai_21) + ' – ' + formatTanggalIndo(data.tanggal_klik);
                        document.getElementById('infoKlikTotalBerita21').textContent =
                            (data.berita_21_hari ? data.berita_21_hari.length : 0) + ' berita ditemukan dalam 21 hari sebelumnya';

                        // Render tabel Kabupaten pada tanggal tersebut
                        var tbodyKab = document.getElementById('tbodyDetailKabupatenKlik');
                        tbodyKab.innerHTML = '';
                        var listKab = (data.kabupaten_lonjakan && data.kabupaten_lonjakan.length > 0)
                            ? data.kabupaten_lonjakan
                            : (data.detail_kabupaten || []);

                        document.getElementById('badgeJmlKabLonjakan').textContent =
                            (data.kabupaten_lonjakan ? data.kabupaten_lonjakan.length : 0) + ' lonjakan';

                        if (listKab.length === 0) {
                            tbodyKab.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data wilayah pada tanggal ini.</td></tr>';
                        } else {
                            listKab.forEach(function (k) {
                                var badgeSt = k.status_lonjakan === 'LONJAKAN_NAIK'
                                    ? '<span class="badge badge-danger">Naik</span>'
                                    : (k.status_lonjakan === 'LONJAKAN_TURUN'
                                        ? '<span class="badge badge-success">Turun</span>'
                                        : '<span class="badge badge-secondary">' + k.status_lonjakan + '</span>');
                                var clsSls = Number(k.selisih) >= 0 ? 'text-danger' : 'text-success';
                                var tr = document.createElement('tr');
                                tr.innerHTML =
                                    '<td class="small font-weight-bold">' + k.kabupaten + '</td>' +
                                    '<td class="text-right small text-nowrap">' + formatRupiah(k.harga) + '</td>' +
                                    '<td class="text-right small font-weight-bold text-nowrap ' + clsSls + '">' + (Number(k.selisih) > 0 ? '+' : '') + formatRupiah(k.selisih) + '</td>' +
                                    '<td class="text-center">' + badgeSt + '</td>';
                                tbodyKab.appendChild(tr);
                            });
                        }

                        // Render tabel Berita dalam 21 Hari Sebelumnya
                        var tbodyBerita = document.getElementById('tbodyBerita21Hari');
                        tbodyBerita.innerHTML = '';
                        var beritaList = data.berita_21_hari || [];
                        var badgeBeritaEl = document.getElementById('badgeJmlBerita21Hari');
                        if (badgeBeritaEl) badgeBeritaEl.textContent = beritaList.length;

                        if (beritaList.length === 0) {
                            tbodyBerita.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ditemukan berita terkait dalam 21 hari sebelum tanggal ' + formatTanggalIndo(tanggalYmd) + '.</td></tr>';
                        } else {
                            beritaList.forEach(function (b) {
                                var badgeRel = b.tingkat_relevansi === 'HIGH'
                                    ? 'badge-success'
                                    : (b.tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary');
                                var badgeHari = b.label_hari === 'Hari-H'
                                    ? '<span class="badge badge-danger">' + b.label_hari + '</span>'
                                    : '<span class="badge badge-light border">' + b.label_hari + '</span>';
                                var judulHtml = b.url
                                    ? '<a href="' + b.url + '" target="_blank" rel="noopener noreferrer" class="font-weight-bold text-dark">' + b.judul + ' <i class="fas fa-external-link-alt fa-xs text-muted ml-1"></i></a>'
                                    : '<span class="font-weight-bold text-dark">' + b.judul + '</span>';
                                var cleanSnippet = b.clean_text ? (b.clean_text.length > 130 ? b.clean_text.substring(0, 130) + '...' : b.clean_text) : '';

                                var tr = document.createElement('tr');
                                tr.innerHTML =
                                    '<td class="small font-weight-bold text-nowrap">' +
                                        b.tanggal_fmt +
                                        '<div class="mt-1">' + badgeHari + '</div>' +
                                    '</td>' +
                                    '<td class="small">' +
                                        judulHtml +
                                        '<div class="text-muted mt-1" style="font-size: 0.78rem;">' + cleanSnippet + '</div>' +
                                    '</td>' +
                                    '<td class="small">' +
                                        '<span class="badge badge-light border d-inline-block mb-1">' + (b.kategori || 'HARGA') + '</span>' +
                                        '<small class="d-block text-muted">' + (b.jenis_komoditas || b.cakupan_berita || '') + '</small>' +
                                    '</td>' +
                                    '<td class="text-center small">' +
                                        '<span class="badge ' + badgeRel + '">' + b.tingkat_relevansi + ' (' + b.skor_relevansi + ')</span>' +
                                    '</td>';
                                tbodyBerita.appendChild(tr);
                            });
                        }

                        // Render tabel Percakapan X dalam 21 Hari Sebelumnya
                        var tbodyX = document.getElementById('tbodyX21Hari');
                        if (tbodyX) {
                            tbodyX.innerHTML = '';
                            var xList = data.x_21_hari || [];
                            var badgeXEl = document.getElementById('badgeJmlX21Hari');
                            if (badgeXEl) badgeXEl.textContent = xList.length;

                            if (xList.length === 0) {
                                tbodyX.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ditemukan cuitan X terkait dalam 21 hari sebelum tanggal ' + formatTanggalIndo(tanggalYmd) + '.</td></tr>';
                            } else {
                                xList.forEach(function (x) {
                                    var badgeRel = x.tingkat_relevansi === 'HIGH'
                                        ? 'badge-success'
                                        : (x.tingkat_relevansi === 'MEDIUM' ? 'badge-info' : 'badge-secondary');
                                    var badgeHari = x.label_hari === 'Hari-H'
                                        ? '<span class="badge badge-danger">' + x.label_hari + '</span>'
                                        : '<span class="badge badge-light border">' + x.label_hari + '</span>';
                                    var bodySnippet = x.body ? (x.body.length > 140 ? x.body.substring(0, 140) + '...' : x.body) : '';
                                    var cleanSnippet = x.text_clean ? (x.text_clean.length > 120 ? x.text_clean.substring(0, 120) + '...' : x.text_clean) : '';

                                    var tr = document.createElement('tr');
                                    tr.innerHTML =
                                        '<td class="small font-weight-bold text-nowrap">' +
                                            x.tanggal_fmt +
                                            '<div class="mt-1">' + badgeHari + '</div>' +
                                        '</td>' +
                                        '<td class="small">' +
                                            '<div class="text-dark font-weight-500">' + bodySnippet + '</div>' +
                                            '<div class="text-muted mt-1" style="font-size: 0.78rem;">' + cleanSnippet + '</div>' +
                                            '<small class="text-muted">ID #' + (x.id_x || x.id || '-') + '</small>' +
                                        '</td>' +
                                        '<td class="small">' +
                                            '<span class="badge badge-light border d-inline-block mb-1">' + (x.kategori || 'UMUM') + '</span>' +
                                            '<small class="d-block text-muted">' + (x.jenis_komoditas || x.cakupan_cuitan || '') + '</small>' +
                                        '</td>' +
                                        '<td class="text-center small">' +
                                            '<span class="badge ' + badgeRel + '">' + x.tingkat_relevansi + ' (' + (x.skor_relevansi || '0') + ')</span>' +
                                        '</td>';
                                    tbodyX.appendChild(tr);
                                });
                            }
                        }
                    })
                    .catch(function () {
                        loadingEl.classList.add('d-none');
                        kontenEl.classList.remove('d-none');
                    });
            }

            // Event listener tombol mode tanggal pada panel TF-IDF & Clean
            document.getElementById('btnModeKlikHariH').addEventListener('click', function () {
                renderClickedTablesFromApi('HARI_H');
            });
            document.getElementById('btnModeKlik21Hari').addEventListener('click', function () {
                renderClickedTablesFromApi('21_HARI');
            });
            document.getElementById('btnKembalikanSemuaRentang').addEventListener('click', function () {
                currentClickedDate = null;
                currentStart21Date = null;
                lastClickedApiData = null;
                renderClickedTablesFromApi('ALL');
            });
            document.getElementById('btnResetKeRentangFilter').addEventListener('click', function () {
                currentClickedDate = null;
                currentStart21Date = null;
                lastClickedApiData = null;
                renderClickedTablesFromApi('ALL');
            });
            document.getElementById('btnTutupDetailLonjakan').addEventListener('click', function () {
                document.getElementById('sectionDetailLonjakan21Hari').classList.add('d-none');
            });

            var btnUlangiAi = document.getElementById('btnUlangiAnalisisAi');
            if (btnUlangiAi) {
                btnUlangiAi.addEventListener('click', function () {
                    if (currentClickedDate) {
                        mintaAnalisisAi(currentClickedDate);
                    }
                });
            }

            var btnCobaLagi = document.getElementById('btnCobaLagiAi');
            if (btnCobaLagi) {
                btnCobaLagi.addEventListener('click', function () {
                    if (currentClickedDate) {
                        mintaAnalisisAi(currentClickedDate);
                    }
                });
            }

            // Klik baris pada tabel Rincian Kejadian Lonjakan Harga
            document.querySelectorAll('.baris-lonjakan-klik').forEach(function (tr) {
                tr.addEventListener('click', function () {
                    var tgl = this.getAttribute('data-tanggal');
                    bukaDetailLonjakanPadaTanggal(tgl);
                });
            });

            var canvas = document.getElementById('priceSpikeChart');
            var statusBadge = document.getElementById('chartStatusBadge');

            if (canvas && chartPayload.harga && chartPayload.harga.length > 0) {
                if (typeof window.Chart !== 'undefined') {
                    statusBadge.textContent = 'Chart.js Aktif';
                    statusBadge.className = 'badge badge-success';

                    var ctx = canvas.getContext('2d');
                    var priceChart = new window.Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartPayload.labels,
                            datasets: [
                                {
                                    label: 'Lonjakan Naik',
                                    data: chartPayload.lonjakan_naik,
                                    backgroundColor: '#dc3545',
                                    borderColor: '#ffffff',
                                    borderWidth: 2,
                                    pointRadius: 7,
                                    pointHoverRadius: 10,
                                    pointStyle: 'triangle',
                                    showLine: false,
                                    fill: false
                                },
                                {
                                    label: 'Lonjakan Turun',
                                    data: chartPayload.lonjakan_turun,
                                    backgroundColor: '#28a745',
                                    borderColor: '#ffffff',
                                    borderWidth: 2,
                                    pointRadius: 7,
                                    pointHoverRadius: 10,
                                    pointStyle: 'circle',
                                    showLine: false,
                                    fill: false
                                },
                                {
                                    label: 'Harga ' + komoditasNama + ' (Rp/' + satuanKomoditas + ')',
                                    data: chartPayload.harga,
                                    backgroundColor: 'rgba(0, 123, 255, 0.10)',
                                    borderColor: '#007bff',
                                    pointBackgroundColor: '#007bff',
                                    pointBorderColor: '#ffffff',
                                    pointRadius: 3,
                                    pointHoverRadius: 6,
                                    borderWidth: 2.5,
                                    fill: true,
                                    lineTension: 0.2
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            responsive: true,
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            onClick: function (evt) {
                                var activePoints = priceChart.getElementsAtEventForMode(evt, 'index', { intersect: false });
                                if (activePoints && activePoints.length > 0) {
                                    var idx = activePoints[0]._index;
                                    var clickedYmd = chartPayload.tanggal_raw[idx];
                                    if (clickedYmd) {
                                        bukaDetailLonjakanPadaTanggal(clickedYmd, idx);
                                    }
                                }
                            },
                            tooltips: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    title: function (tooltipItems) {
                                        if (!tooltipItems.length) return '';
                                        var idx = tooltipItems[0].index;
                                        return 'Tanggal: ' + chartPayload.tanggal_raw[idx] + ' (' + chartPayload.labels[idx] + ')';
                                    },
                                    label: function (tooltipItem) {
                                        if (tooltipItem.datasetIndex !== 2) {
                                            return null;
                                        }
                                        var idx = tooltipItem.index;
                                        var harga = chartPayload.harga[idx];
                                        var selisih = chartPayload.selisih[idx];
                                        var status = chartPayload.status[idx];
                                        var batasAtas = chartPayload.batas_atas[idx];
                                        var batasBawah = chartPayload.batas_bawah[idx];
                                        var kabNaik = chartPayload.jml_kab_naik ? chartPayload.jml_kab_naik[idx] : 0;
                                        var kabTurun = chartPayload.jml_kab_turun ? chartPayload.jml_kab_turun[idx] : 0;

                                        var tanda = selisih > 0 ? '+' : '';
                                        var info = [
                                            'Harga: ' + formatRupiah(harga) + ' / ' + satuanKomoditas,
                                            'Perubahan Harian: ' + tanda + formatRupiah(selisih),
                                            'Status Lonjakan: ' + status,
                                            'Batas MAD: [' + formatRupiah(batasBawah) + ' s/d ' + formatRupiah(batasAtas) + ']'
                                        ];
                                        if (kabNaik > 0 || kabTurun > 0) {
                                            info.push('Kab/Kota Lonjakan: Naik ' + kabNaik + ', Turun ' + kabTurun);
                                        }
                                        info.push('Klik titik untuk melihat detail & berita 21 hari sebelumnya');
                                        return info;
                                    }
                                }
                            },
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        callback: function (value) {
                                            return formatRupiah(value);
                                        }
                                    },
                                    gridLines: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                }],
                                xAxes: [{
                                    ticks: {
                                        maxTicksLimit: 14,
                                        maxRotation: 45,
                                        minRotation: 0
                                    },
                                    gridLines: {
                                        display: false
                                    }
                                }]
                            }
                        }
                    });
                }
            } else if (statusBadge) {
                statusBadge.textContent = 'Tidak Ada Data';
                statusBadge.className = 'badge badge-secondary';
            }

            // Pencarian & Filter pada Tabel Kabupaten/Kota
            var cariKabInput = document.getElementById('cariKabupatenInput');
            var statusKabSelect = document.getElementById('filterStatusKabupaten');
            var barisKab = document.querySelectorAll('#tabelKabupatenTerbaru tbody tr');
            var infoJumlahKab = document.getElementById('infoJumlahKabupaten');

            function saringTabelKabupaten() {
                var q = (cariKabInput.value || '').toLowerCase();
                var st = statusKabSelect.value;
                var count = 0;

                barisKab.forEach(function (tr) {
                    var matchText = tr.textContent.toLowerCase().indexOf(q) !== -1;
                    var rowSt = tr.getAttribute('data-status');
                    var matchStatus = (st === 'ALL') || (rowSt === st);

                    if (matchText && matchStatus) {
                        tr.style.display = '';
                        count++;
                    } else {
                        tr.style.display = 'none';
                    }
                });

                if (infoJumlahKab) {
                    infoJumlahKab.innerHTML = 'Menampilkan <strong>' + count + '</strong> dari <strong>' + barisKab.length + '</strong> Kabupaten/Kota.';
                }
            }

            if (cariKabInput) cariKabInput.addEventListener('input', saringTabelKabupaten);
            if (statusKabSelect) statusKabSelect.addEventListener('change', saringTabelKabupaten);
        });
    </script>
@stop