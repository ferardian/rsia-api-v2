<?php

namespace App\Http\Controllers\v2; // Pastikan namespace ini benar

use App\Http\Controllers\Controller;
use App\Models\BridgingSep; // Import model BridgingSep
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Facades\Log; // Import Log facade
use App\Models\RegPeriksa;
use App\Models\DiagnosaPasien;
use App\Models\ProsedurPasien;
use App\Models\DetailPemberianObat;
use App\Models\PeriksaLab;
use App\Models\PeriksaRadiologi;
use App\Models\DetailPeriksaLab;
use App\Models\TemplateLaboratorium;
use App\Models\GambarRadiologi;
use App\Models\PemeriksaanRanap;
use App\Models\CatatanPerawatan;
use App\Models\PemeriksaanRalan;
use App\Models\PemeriksaanRalanKlaim;
use App\Models\ResepObat;
use App\Models\ResepPulang;
use App\Models\TarifTindakanPerawat;
use App\Models\TindakanRanap;
use App\Models\TarifPemeriksaanRanap;
use App\Models\KamarInap;
use Illuminate\Http\Request;

class ErmController extends Controller
{
    /**
     * Mengambil detail ERM untuk satu nomor SEP.
     *
     * @param  string  $no_sep
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDetails(string $no_sep) // Ganti nama method atau gunakan parameter $identifier
    {
       $bridging = BridgingSep::where('no_sep', $no_sep)->first();

        if (!$bridging || !$bridging->no_rawat) {
            return response()->json(['success' => false, 'message' => 'Nomor Rawat tidak ditemukan untuk SEP ' . $no_sep], 404);
        }

        $no_rawat = $bridging->no_rawat;

        // --- Perubahan di sini ---
        // Muat RegPeriksa beserta relasi prosedurPasien DAN icd9 di dalam prosedurPasien
        $regPeriksa = RegPeriksa::with([
                'pasien', // Muat relasi pasien jika perlu
                // 'penjab', // Muat relasi penanggung jawab jika perlu
                'poliklinik', // Muat relasi poliklinik jika perlu
                'dokter', // Muat relasi dokter jika perlu
                'dokter.pegawai', // <<<----- Muat relasi pegawai dari dokter (untuk no_ktp)
                'diagnosaPasien.penyakit', // Muat diagnosa dan relasi penyakitnya
                'prosedurPasien.penyakit', // <<<----- Muat prosedur DAN relasi icd9 nya
                'notaJalan' // <<<----- Muat nota jalan untuk period end time
            ])
            ->where('no_rawat', $no_rawat)
            ->first();
        // ------------------------

        if (!$regPeriksa) {
            return response()->json(['success' => false, 'message' => 'Data registrasi tidak ditemukan untuk No. Rawat ' . $no_rawat], 404);
        }

        // Ambil data prosedur yang sudah dimuat (termasuk icd9 nya)
        $prosedur = $regPeriksa->prosedurPasien->map(fn($item) => [
            'kode' => $item->kode,
            // Akses deskripsi dari relasi icd9 yang sudah dimuat
            'deskripsi' => $item->penyakit->deskripsi_panjang ?? 'Deskripsi ICD9 tidak ditemukan',
            'status' => $item->status,
        ]);

        // Ambil diagnosa yang sudah dimuat
        $diagnosa = $regPeriksa->diagnosaPasien->map(fn($item) => [
            'kode' => $item->kd_penyakit,
            'deskripsi' => $item->penyakit->nm_penyakit ?? 'Deskripsi Penyakit tidak ditemukan',
            'status' => $item->status,
        ]);

        // Query untuk data obat - bedakan antara rawat jalan dan rawat inap
        $isRawatInap = $regPeriksa->status_lanjut == 'Ranap';

        if ($isRawatInap) {
            // Rawat Inap - gunakan data dari resep_pulang
            $obat = ResepPulang::with(['dataBarang', 'bangsal'])
                ->where('no_rawat', $no_rawat)
                ->get()
                ->map(function($resep) {
                    $namaObat = $resep->dataBarang->nama_brng ?? $resep->kode_brng . ' (Tidak ada di master DataBarang)';
                    $satuan = $resep->dataBarang->kode_sat ?? 'N/A';
                    $kategori = $resep->dataBarang->kategori ?? 'N/A';
                    $bangsalNama = $resep->bangsal->nm_bangsal ?? 'N/A';

                    return [
                        'tipe_rawatan' => 'Rawat Inap',
                        'no_resep' => null, // Resep pulang tidak punya no_resep
                        'tgl_peresepan' => $resep->tanggal,
                        'jam_peresepan' => $resep->jam,
                        'kode_brng' => $resep->kode_brng,
                        'jml' => $resep->jml_barang,
                        'h_beli' => $resep->harga,
                        'total' => $resep->total,
                        'tgl_perawatan' => $resep->tanggal,
                        'jam' => $resep->jam,
                        'no_batch' => $resep->no_batch,
                        'no_faktur' => $resep->no_faktur,
                        'status_obat' => null, // Resep pulang tidak punya status obat detail
                        'status_resep' => null, // Resep pulang tidak punya status resep
                        'dokter_nama' => 'Dokter', // Resep pulang tidak ada relasi dokter
                        'nama_brng' => $namaObat,
                        'kode_sat' => $satuan,
                        'kategori' => $kategori,
                        'dosis' => $resep->dosis,
                        'kd_bangsal' => $resep->kd_bangsal,
                        'nama_bangsal' => $bangsalNama,
                        'aturan_pakai' => null // Resep pulang tidak punya aturan pakai terstruktur
                    ];
                });
        } else {
            // Rawat Jalan - Prioritize detail_pemberian_obat with cross-check to resep_dokter for aturan pakai
            $obat = ResepObat::with(['dokter'])
                ->where('no_rawat', $no_rawat)
                ->get()
                ->flatMap(function($resep) {
                    $results = [];

                    // Primary: Get data from detail_pemberian_obat (administrasi/pemberian obat)
                    $details = DetailPemberianObat::with(['aturanPakai'])
                        ->where('tgl_perawatan', $resep->tgl_perawatan)
                        ->where('jam', $resep->jam)
                        ->where('no_rawat', $resep->no_rawat)
                        ->get();

                    foreach ($details as $detail) {
                        $dataBarang = \App\Models\DataBarang::where('kode_brng', $detail->kode_brng)->first();
                        $namaObat = $dataBarang->nama_brng ?? ($detail->kode_brng . ' (Tidak ada di master DataBarang)');
                        $satuan = $dataBarang->kode_sat ?? 'N/A';

                        // Get category name
                        $kategori = 'Obat';
                        if ($dataBarang && $dataBarang->kode_kategori) {
                            $kategoriData = DB::table('kategori_barang')
                                ->where('kode', $dataBarang->kode_kategori)
                                ->first();
                            $kategori = $kategoriData->nama ?? 'Obat';
                        }

                        // Cross-check to resep_dokter for aturan pakai (prioritas 1)
                        $aturanPakai = null;

                        // Debug: Log the search parameters
                        Log::info("Searching for aturan pakai - no_resep: " . $resep->no_resep . ", kode_brng: " . $detail->kode_brng);

                        $resepDokterData = DB::table('resep_dokter')
                            ->where('no_resep', $resep->no_resep)
                            ->where('kode_brng', $detail->kode_brng)
                            ->first();

                        Log::info("Resep dokter data found: ", (array)$resepDokterData);

                        if ($resepDokterData && $resepDokterData->aturan_pakai) {
                            $aturanPakai = $resepDokterData->aturan_pakai;
                            Log::info("Using aturan pakai from resep_dokter: " . $aturanPakai);
                        } elseif ($detail->aturanPakai) {
                            // Use aturanPakai from detail_pemberian_obat (prioritas 2)
                            if (is_object($detail->aturanPakai) && isset($detail->aturanPakai->aturan)) {
                                $aturanPakai = $detail->aturanPakai->aturan;
                                Log::info("Using aturan pakai from detail->aturanPakai->aturan: " . $aturanPakai);
                            } elseif (is_string($detail->aturanPakai)) {
                                $aturanPakai = $detail->aturanPakai;
                                Log::info("Using aturan pakai from detail->aturanPakai: " . $aturanPakai);
                            }
                            Log::info("Detail aturanPakai data: ", (array)$detail->aturanPakai);
                        }

                        // Fallback: Jika tidak ada aturan pakai, gunakan nilai default
                        if (!$aturanPakai) {
                            $aturanPakai = null; // Biarkan frontend yang handle fallback
                            Log::info("No aturan pakai found, using null");
                        }

                        $results[] = [
                            'tipe_rawatan' => 'Rawat Jalan',
                            'no_resep' => $resep->no_resep,
                            'tgl_peresepan' => $resep->tgl_peresepan,
                            'jam_peresepan' => $resep->jam_peresepan,
                            'kode_brng' => $detail->kode_brng,
                            'jml' => $detail->jml,
                            'h_beli' => $detail->h_beli,
                            'total' => $detail->total,
                            'tgl_perawatan' => $detail->tgl_perawatan,
                            'jam' => $detail->jam,
                            'no_batch' => $detail->no_batch,
                            'no_faktur' => $detail->no_faktur,
                            'status_obat' => $detail->status,
                            'status_resep' => 'Non Racik',
                            'dokter_nama' => $resep->dokter->nm_dokter ?? 'Dokter',
                            'nama_brng' => $namaObat,
                            'kode_sat' => $satuan,
                            'kategori' => $kategori,
                            'dosis' => null,
                            'kd_bangsal' => null,
                            'nama_bangsal' => null,
                            'aturan_pakai' => $aturanPakai,
                            'jenis_resep' => 'Non Racik'
                        ];
                    }

                    // Secondary: Check for racikan data from resep_dokter_racikan
                    $resepRacikan = DB::table('resep_dokter_racikan')
                        ->where('no_resep', $resep->no_resep)
                        ->get();

                    foreach ($resepRacikan as $racikan) {
                        $racikanDetails = DB::table('resep_dokter_racikan_detail')
                            ->join('databarang', 'resep_dokter_racikan_detail.kode_brng', '=', 'databarang.kode_brng')
                            ->leftJoin('kategori_barang', 'databarang.kode_kategori', '=', 'kategori_barang.kode')
                            ->where('resep_dokter_racikan_detail.no_resep', $racikan->no_resep)
                            ->where('resep_dokter_racikan_detail.no_racik', $racikan->no_racik)
                            ->select(
                                'resep_dokter_racikan_detail.kode_brng',
                                'resep_dokter_racikan_detail.jml',
                                'resep_dokter_racikan_detail.p1',
                                'resep_dokter_racikan_detail.p2',
                                'resep_dokter_racikan_detail.kandungan',
                                'databarang.nama_brng',
                                'kategori_barang.nama as kategori'
                            )
                            ->get();

                        $results[] = [
                            'tipe_rawatan' => 'Rawat Jalan',
                            'no_resep' => $resep->no_resep,
                            'tgl_peresepan' => $resep->tgl_peresepan,
                            'jam_peresepan' => $resep->jam_peresepan,
                            'kode_brng' => 'RACIKAN-' . $racikan->no_racik,
                            'jml' => $racikan->jml_dr,
                            'h_beli' => 0,
                            'total' => 0,
                            'tgl_perawatan' => $resep->tgl_perawatan,
                            'jam' => $resep->jam,
                            'no_batch' => null,
                            'no_faktur' => null,
                            'status_obat' => null,
                            'status_resep' => 'Racik',
                            'dokter_nama' => $resep->dokter->nm_dokter ?? 'Dokter',
                            'nama_brng' => $racikan->nama_racik,
                            'kode_sat' => 'RACIK',
                            'kategori' => 'Obat Racikan',
                            'dosis' => null,
                            'kd_bangsal' => null,
                            'nama_bangsal' => null,
                            'aturan_pakai' => [
                                'aturan' => $racikan->aturan_pakai,
                                'keterangan' => $racikan->keterangan
                            ],
                            'keterangan_aturan' => $racikan->keterangan,
                            'nama_racik' => $racikan->nama_racik,
                            'kd_racik' => $racikan->kd_racik,
                            'jenis_resep' => 'Racik',
                            'racikan_detail' => $racikanDetails->map(function($detail) {
                                return [
                                    'kode_brng' => $detail->kode_brng,
                                    'nama_brng' => $detail->nama_brng,
                                    'jml' => $detail->jml,
                                    'p1' => $detail->p1,
                                    'p2' => $detail->p2,
                                    'kandungan' => $detail->kandungan,
                                    'kategori' => $detail->kategori ?? 'Obat'
                                ];
                            })
                        ];
                    }

                    return $results;
                });
        }

        // New code
$lab = PeriksaLab::with('jenisPerawatan', 'detailPeriksaLab.template') // Correct relationship name, and nested load for template
     ->where('no_rawat', $no_rawat)
     ->get();

        $radiologi = PeriksaRadiologi::with('jenisPerawatan', 'hasilRadiologi')
             ->with(['gambarRadiologi' => function($query) {
                 $query->select('no_rawat', 'tgl_periksa', 'jam', 'lokasi_gambar');
             }])
             ->where('no_rawat', $no_rawat)
             ->get()
             ->map(function ($item) {
                 $baseUrl = 'https://sim.rsiaaisyiyah.com/webapps/radiologi/';
                 if ($item->gambarRadiologi) {
                     $item->gambarRadiologi = $item->gambarRadiologi->map(function ($gambar) use ($baseUrl) {
                         $gambar->lokasi_gambar = $baseUrl . $gambar->lokasi_gambar;
                         return $gambar;
                     });
                 }
                 return $item;
             });

        $cppt_pemeriksaan_ralan = PemeriksaanRalan::where('no_rawat', $no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->get();
        $cppt_pemeriksaan = PemeriksaanRanap::with('petugas')->where('no_rawat', $no_rawat)->orderBy('tgl_perawatan', 'desc')->orderBy('jam_rawat', 'desc')->get();
        $cppt_catatan = CatatanPerawatan::where('no_rawat', $no_rawat)->orderBy('tanggal', 'desc')->orderBy('jam', 'desc')->get();

        // Get kamar_inap data for admission information
        $kamar_inap = KamarInap::with('kamar')
            ->where('no_rawat', $no_rawat)
            ->orderBy('tgl_masuk', 'desc')
            ->orderBy('jam_masuk', 'desc')
            ->first();

        $data = [
            // Gunakan $regPeriksa yang sudah berisi banyak relasi
            'registrasi' => $regPeriksa,
            'diagnosa' => $diagnosa, // Data diagnosa hasil map
            'prosedur' => $prosedur, // Data prosedur hasil map
            'obat' => $obat,
            'lab' => $lab,
            'radiologi' => $radiologi,
            'cppt_pemeriksaan_ralan' => $cppt_pemeriksaan_ralan,
            'cppt_pemeriksaan' => $cppt_pemeriksaan,
            'cppt_catatan' => $cppt_catatan,
            'kamar_inap' => $kamar_inap, // Data kamar inap untuk informasi kunjungan
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Mendapatkan daftar gambar radiologi per periksa untuk tabel perbandingan.
     *
     * @param string $no_rawat
     * @return \Illuminate\Http\JsonResponse
     */
    public function gambarRadiologi(string $no_rawat): JsonResponse
    {
        try {
            if (empty($no_rawat)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor rawat harus diisi'
                ], 400);
            }

            // Ambil data periksa radiologi terlebih dahulu
            $periksaRadiologi = PeriksaRadiologi::with('jenisPerawatan')
                ->where('no_rawat', $no_rawat)
                ->orderBy('tgl_periksa', 'desc')
                ->orderBy('jam', 'desc')
                ->get();

            // Ambil gambar untuk setiap periksa radiologi
            $gambarList = [];
            $baseUrl = 'https://sim.rsiaaisyiyah.com/webapps/radiologi/';

            foreach ($periksaRadiologi as $periksa) {
                $gambar = GambarRadiologi::whereNoRawat($no_rawat)
                    ->whereTglPeriksa($periksa->tgl_periksa)
                    ->whereJam($periksa->jam)
                    ->get()
                    ->map(function ($item) use ($baseUrl) {
                        return [
                            'id' => $item->lokasi_gambar,
                            'path' => $baseUrl . $item->lokasi_gambar,
                            'deskripsi' => $item->lokasi_gambar
                        ];
                    })
                    ->toArray();

                $gambarList[] = [
                    'tanggal' => $periksa->tgl_periksa,
                    'jam' => $periksa->jam,
                    'jenis_perawatan' => $periksa->jenisPerawatan,
                    'gambar' => $gambar
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $gambarList
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan riwayat laboratorium pasien.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRiwayatLab(Request $request): JsonResponse
    {
        try {
            $no_rkm_medis = $request->query('no_rkm_medis');
            $no_rawat = $request->query('no_rawat');
            $limit = $request->query('limit', 20);
            $page = $request->query('page', 1);
            $tanggal_dari = $request->query('tanggal_dari');
            $tanggal_sampai = $request->query('tanggal_sampai');

            // Gunakan no_rkm_medis atau no_rawat yang tersedia
            $identifier = $no_rkm_medis ?? $no_rawat;

            if (empty($identifier)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor rekam medis atau rawat harus diisi'
                ], 400);
            }

            // Query untuk riwayat laboratorium dengan detail hasil
            $query = PeriksaLab::with(['jenisPerawatan', 'dokter', 'detailPeriksaLab.template'])
                ->whereHas('regPeriksa', function($q) use ($no_rkm_medis) {
                    if ($no_rkm_medis) {
                        $q->where('no_rkm_medis', $no_rkm_medis);
                    }
                })
                ->when($no_rawat, function($q) use ($no_rawat) {
                    $q->where('no_rawat', $no_rawat);
                });

            // Filter tanggal jika ada
            if ($tanggal_dari) {
                $query->where('tgl_periksa', '>=', $tanggal_dari);
            }
            if ($tanggal_sampai) {
                $query->where('tgl_periksa', '<=', $tanggal_sampai);
            }

            // Pagination
            $offset = ($page - 1) * $limit;
            $total = $query->count();
            $riwayatLab = $query->orderBy('tgl_periksa', 'desc')
                ->orderBy('jam', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            // Format data
            $data = $riwayatLab->map(function ($item) {
                return [
                    'tgl_periksa' => $item->tgl_periksa,
                    'jam' => $item->jam,
                    'no_rawat' => $item->no_rawat,
                    'kd_jenis_prw' => $item->kd_jenis_prw,
                    'nip' => $item->nip,
                    'dokter_perujuk' => $item->dokter_perujuk,
                    'kd_dokter' => $item->kd_dokter,
                    'bagian_rs' => $item->bagian_rs,
                    'bhp' => $item->bhp,
                    'tarif_perujuk' => $item->tarif_perujuk,
                    'tarif_tindakan_dokter' => $item->tarif_tindakan_dokter,
                    'tarif_tindakan_petugas' => $item->tarif_tindakan_petugas,
                    'kso' => $item->kso,
                    'menejemen' => $item->menejemen,
                    'biaya' => $item->biaya,
                    'status' => $item->status,
                    'kategori' => $item->kategori,
                    'jenis_perawatan' => $item->jenisPerawatan ? [
                        'nm_perawatan' => $item->jenisPerawatan->nm_perawatan,
                        'kd_pj' => $item->jenisPerawatan->kd_pj,
                        'status' => $item->jenisPerawatan->status,
                        'kelas' => $item->jenisPerawatan->kelas
                    ] : null,
                    'dokter' => $item->dokter ? [
                        'nm_dokter' => $item->dokter->nm_dokter,
                        'kd_dokter' => $item->dokter->kd_dokter
                    ] : null,
                    'petugas' => $item->petugas ? [
                        'nip' => $item->petugas->nip,
                        'nama' => $item->petugas->nama
                    ] : null,
                    'detail_periksa_lab' => $item->detailPeriksaLab->map(function ($detail) {
                        return [
                            'id_template' => $detail->id_template,
                            'pemeriksaan' => $detail->template ? $detail->template->Pemeriksaan : null,
                            'nilai' => $detail->nilai,
                            'satuan' => $detail->template ? $detail->template->satuan : null,
                            'nilai_rujukan' => $detail->nilai_rujukan,
                            'keterangan' => $detail->keterangan,
                            'template' => $detail->template ? [
                                'id_template' => $detail->template->id_template,
                                'Pemeriksaan' => $detail->template->Pemeriksaan,
                                'satuan' => $detail->template->satuan,
                                'nilai_rujukan_ld' => $detail->template->nilai_rujukan_ld,
                                'nilai_rujukan_la' => $detail->template->nilai_rujukan_la,
                                'nilai_rujukan_pd' => $detail->template->nilai_rujukan_pd,
                                'nilai_rujukan_pa' => $detail->template->nilai_rujukan_pa,
                            ] : null
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}