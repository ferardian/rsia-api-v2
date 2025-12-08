<?php

namespace App\Http\Controllers;

use App\Models\PeriksaLab;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class RiwayatLabController extends Controller
{
    /**
     * Get riwayat pemeriksaan laboratorium pasien
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRiwayatLab(Request $request): JsonResponse
    {
        // Debug: Log request yang masuk
        file_put_contents(storage_path('logs/lab_debug.log'), date('Y-m-d H:i:s') . " - REQUEST RECEIVED: " . json_encode($request->all()) . "\n", FILE_APPEND);
        Log::info('RIWAYAT LAB REQUEST RECEIVED: ' . json_encode($request->all()));

        $request->validate([
            'no_rkm_medis' => 'required_without_all:no_rawat|string|exists:pasien,no_rkm_medis',
            'no_rawat' => 'required_without_all:no_rkm_medis|string|exists:reg_periksa,no_rawat',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'tanggal_dari' => 'nullable|date',
            'tanggal_sampai' => 'nullable|date|after_or_equal:tanggal_dari'
        ]);

        Log::info('RIWAYAT LAB VALIDATION PASSED');

        try {
            $no_rkm_medis = $request->get('no_rkm_medis');
            $no_rawat = $request->get('no_rawat');
            $limit = $request->get('limit', 20);
            $page = $request->get('page', 1);
            $tanggalDari = $request->get('tanggal_dari');
            $tanggalSampai = $request->get('tanggal_sampai');

            // Query utama untuk mendapatkan riwayat pemeriksaan lab
            $query = PeriksaLab::with([
                'regPeriksa' => function ($q) {
                    $q->select('no_rawat', 'no_rkm_medis', 'tgl_registrasi', 'jam_reg', 'kd_poli', 'kd_dokter', 'status_lanjut');
                },
                'regPeriksa.pasien' => function ($q) use ($no_rkm_medis) {
                    $q->select('no_rkm_medis', 'nm_pasien', 'jk', 'tmp_lahir', 'tgl_lahir', 'alamat');
                },
                'regPeriksa.poliklinik' => function ($q) {
                    $q->select('kd_poli', 'nm_poli');
                },
                'jenisPerawatan' => function ($q) {
                    $q->select('kd_jenis_prw', 'nm_perawatan');
                },
                'dokter' => function ($q) {
                    $q->select('kd_dokter', 'nm_dokter');
                },
                'perujuk' => function ($q) {
                    $q->select('kd_dokter', 'nm_dokter');
                },
                'petugas' => function ($q) {
                    $q->select('nip', 'nama');
                },
                'detailPeriksaLab.template' => function ($q) {
                    $q->select('id_template', 'Pemeriksaan', 'satuan', 'nilai_rujukan_ld', 'nilai_rujukan_la', 'nilai_rujukan_pd', 'nilai_rujukan_pa');
                }
            ]);

            // Filter berdasarkan parameter yang diberikan
            if ($no_rkm_medis) {
                $query->whereHas('regPeriksa', function ($q) use ($no_rkm_medis) {
                    $q->where('no_rkm_medis', $no_rkm_medis);
                });
            } elseif ($no_rawat) {
                $query->where('no_rawat', $no_rawat);
            }

            // Filter berdasarkan tanggal
            if ($tanggalDari) {
                $query->where('tgl_periksa', '>=', $tanggalDari);
            }
            if ($tanggalSampai) {
                $query->where('tgl_periksa', '<=', $tanggalSampai);
            }

            // Order by
            $query->orderBy('tgl_periksa', 'desc')
                  ->orderBy('jam', 'desc');

            // Pagination
            $offset = ($page - 1) * $limit;
            $total = $query->count();
            $totalPages = ceil($total / $limit);

            $riwayatLab = $query->offset($offset)->limit($limit)->get();

            // Format data untuk response
            $data = $riwayatLab->map(function ($item) {
                return [
                    'no_rawat' => $item->no_rawat,
                    'tgl_periksa' => $item->tgl_periksa,
                    'jam' => $item->jam,
                    'jenis_perawatan' => $item->jenisPerawatan ? [
                        'kd_jenis_prw' => $item->jenisPerawatan->kd_jenis_prw,
                        'nm_perawatan' => $item->jenisPerawatan->nm_perawatan,
                    ] : null,
                    'dokter' => $item->dokter ? [
                        'kd_dokter' => $item->dokter->kd_dokter,
                        'nm_dokter' => $item->dokter->nm_dokter,
                    ] : null,
                    'dokter_perujuk' => $item->perujuk ? [
                        'kd_dokter' => $item->perujuk->kd_dokter,
                        'nm_dokter' => $item->perujuk->nm_dokter,
                    ] : null,
                    'petugas' => $item->petugas ? [
                        'nip' => $item->petugas->nip,
                        'nama' => $item->petugas->nama,
                    ] : null,
                    'status' => $item->status,
                    'kategori' => $item->kategori,
                    'biaya' => $item->biaya,
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
                            ] : null,
                        ];
                    }),
                    'registrasi' => $item->regPeriksa ? [
                        'no_rawat' => $item->regPeriksa->no_rawat,
                        'tgl_registrasi' => $item->regPeriksa->tgl_registrasi,
                        'jam_reg' => $item->regPeriksa->jam_reg,
                        'kd_poli' => $item->regPeriksa->kd_poli,
                        'poliklinik' => $item->regPeriksa->poliklinik ? [
                            'kd_poli' => $item->regPeriksa->poliklinik->kd_poli,
                            'nm_poli' => $item->regPeriksa->poliklinik->nm_poli,
                        ] : null,
                        'status_lanjut' => $item->regPeriksa->status_lanjut,
                    ] : null,
                ];
            });

            // LOG: Debug data yang akan dikirim
            $debugInfo = [];
            $logMessage = "RIWAYAT LAB DEBUG - Data yang akan dikirim:\n";
            $logMessage .= "Total data: " . $data->count() . "\n";

            // Tulis ke file log terpisah
            file_put_contents(storage_path('logs/lab_debug.log'), date('Y-m-d H:i:s') . " - " . $logMessage, FILE_APPEND);
            Log::info('RIWAYAT LAB DEBUG - Data yang akan dikirim:');
            Log::info('Total data: ' . $data->count());

            foreach ($data as $index => $labItem) {
                Log::info("Data item {$index}:");
                Log::info('No Rawat: ' . $labItem['no_rawat']);
                Log::info('Tanggal: ' . $labItem['tgl_periksa']);
                Log::info('Detail count: ' . count($labItem['detail_periksa_lab']));

                $debugInfo[] = [
                    'no_rawat' => $labItem['no_rawat'],
                    'tanggal' => $labItem['tgl_periksa'],
                    'detail_count' => count($labItem['detail_periksa_lab']),
                    'details' => []
                ];

                foreach ($labItem['detail_periksa_lab'] as $detailIndex => $detail) {
                    Log::info("  Detail {$detailIndex}:");
                    Log::info('    ID Template: ' . $detail['id_template']);
                    Log::info('    Pemeriksaan: ' . ($detail['pemeriksaan'] ?? 'NULL'));
                    Log::info('    Nilai: ' . $detail['nilai']);
                    Log::info('    Satuan: ' . ($detail['satuan'] ?? 'NULL'));
                    Log::info('    Template Pemeriksaan: ' . ($detail['template']['Pemeriksaan'] ?? 'NULL'));

                    $detailLog = "  Detail {$detailIndex}: ID Template: {$detail['id_template']}, Pemeriksaan: " . ($detail['pemeriksaan'] ?? 'NULL') . ", Template Pemeriksaan: " . ($detail['template']['Pemeriksaan'] ?? 'NULL') . "\n";
                    file_put_contents(storage_path('logs/lab_debug.log'), date('Y-m-d H:i:s') . " - " . $detailLog, FILE_APPEND);

                    $debugInfo[$index]['details'][] = [
                        'id_template' => $detail['id_template'],
                        'pemeriksaan' => $detail['pemeriksaan'] ?? 'NULL',
                        'nilai' => $detail['nilai'],
                        'satuan' => $detail['satuan'] ?? 'NULL',
                        'template_pemeriksaan' => $detail['template']['Pemeriksaan'] ?? 'NULL'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Riwayat pemeriksaan lab berhasil diambil',
                'data' => $data,
                'debug_info' => $debugInfo, // Debug data
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $total,
                    'per_page' => $limit,
                    'has_next_page' => $page < $totalPages,
                    'has_prev_page' => $page > 1,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Get ringkasan riwayat lab pasien (tanpa detail)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRingkasanRiwayatLab(Request $request): JsonResponse
    {
        $request->validate([
            'no_rkm_medis' => 'required|string|exists:pasien,no_rkm_medis',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        try {
            $no_rkm_medis = $request->no_rkm_medis;
            $limit = $request->get('limit', 10);

            $riwayatLab = PeriksaLab::with([
                'regPeriksa' => function ($q) {
                    $q->select('no_rawat', 'no_rkm_medis', 'tgl_registrasi', 'kd_poli', 'status_lanjut');
                },
                  'jenisPerawatan' => function ($q) {
                    $q->select('kd_jenis_prw', 'nm_perawatan');
                }
            ])
            ->whereHas('regPeriksa', function ($q) use ($no_rkm_medis) {
                $q->where('no_rkm_medis', $no_rkm_medis);
            })
            ->orderBy('tgl_periksa', 'desc')
            ->orderBy('jam', 'desc')
            ->limit($limit)
            ->get();

            $data = $riwayatLab->map(function ($item) {
                return [
                    'no_rawat' => $item->no_rawat,
                    'tgl_periksa' => $item->tgl_periksa,
                    'jam' => $item->jam,
                    'jenis_perawatan' => $item->jenisPerawatan ? [
                        'kd_jenis_prw' => $item->jenisPerawatan->kd_jenis_prw,
                        'nm_perawatan' => $item->jenisPerawatan->nm_perawatan,
                    ] : null,
                    'status' => $item->status,
                    'kategori' => $item->kategori,
                    'biaya' => $item->biaya,
                    'jumlah_pemeriksaan' => $item->detailPeriksaLab->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Ringkasan riwayat lab berhasil diambil',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}