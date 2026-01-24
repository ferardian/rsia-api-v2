<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\PermintaanLab;
use App\Models\RsiaInmutLab;
use App\Models\RsiaSiha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InmutLabController extends Controller
{
    /**
     * Get today's lab requests with patient info and existing indicators.
     */
    public function index(Request $request)
    {
        $date = $request->get('tgl', date('Y-m-d'));

        try {
            $data = PermintaanLab::with([
                'regPeriksa.pasien',
                'perujuk',
            ])
            ->where('tgl_permintaan', $date)
            ->get()
            ->map(function($order) {
                // 1. Try to get from actual examinations (periksa_lab)
                $examNames = DB::table('periksa_lab')
                    ->join('jns_perawatan_lab', 'periksa_lab.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                    ->where('periksa_lab.no_rawat', $order->no_rawat)
                    ->where('periksa_lab.tgl_periksa', $order->tgl_permintaan)
                    ->pluck('jns_perawatan_lab.nm_perawatan')
                    ->toArray();

                // 2. If empty, try to get from requests (PK/Regular)
                if (empty($examNames)) {
                    $examNames = DB::table('permintaan_pemeriksaan_lab')
                        ->join('jns_perawatan_lab', 'permintaan_pemeriksaan_lab.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                        ->where('permintaan_pemeriksaan_lab.noorder', $order->noorder)
                        ->pluck('jns_perawatan_lab.nm_perawatan')
                        ->toArray();
                }

                // 3. Check for sub-details if still empty (templates)
                if (empty($examNames)) {
                    $examNames = DB::table('permintaan_detail_permintaan_lab')
                        ->join('template_laboratorium', 'permintaan_detail_permintaan_lab.id_template', '=', 'template_laboratorium.id_template')
                        ->where('permintaan_detail_permintaan_lab.noorder', $order->noorder)
                        ->pluck('template_laboratorium.Pemeriksaan')
                        ->toArray();
                }

                // 4. Check MB and PA types
                if (empty($examNames)) {
                    $mb = DB::table('permintaan_pemeriksaan_labmb')
                        ->join('jns_perawatan_lab', 'permintaan_pemeriksaan_labmb.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                        ->where('permintaan_pemeriksaan_labmb.noorder', $order->noorder)
                        ->pluck('jns_perawatan_lab.nm_perawatan')
                        ->toArray();
                    
                    $pa = DB::table('permintaan_pemeriksaan_labpa')
                        ->join('jns_perawatan_lab', 'permintaan_pemeriksaan_labpa.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                        ->where('permintaan_pemeriksaan_labpa.noorder', $order->noorder)
                        ->pluck('jns_perawatan_lab.nm_perawatan')
                        ->toArray();
                    
                    $examNames = array_merge($mb, $pa);
                }

                $pemeriksaanStr = implode(', ', array_unique($examNames));
                $hasHiv = false;
                foreach ($examNames as $name) {
                    if (stripos($name, 'HIV') !== false) {
                        $hasHiv = true;
                        break;
                    }
                }

                // Check if quality indicator already exists
                $inmut = RsiaInmutLab::where('no_rawat', $order->no_rawat)
                    ->where('tgl_periksa', $order->tgl_permintaan)
                    ->first();
                
                // Check if SIHA exists
                $siha = RsiaSiha::where('no_rawat', $order->no_rawat)->first();

                return [
                    'noorder' => $order->noorder,
                    'no_rawat' => $order->no_rawat,
                    'tgl_permintaan' => $order->tgl_permintaan,
                    'jam_permintaan' => $order->jam_permintaan,
                    'tgl_sampel' => $order->tgl_sampel,
                    'jam_sampel' => $order->jam_sampel,
                    'tgl_hasil' => $order->tgl_hasil,
                    'jam_hasil' => $order->jam_hasil,
                    'pasien' => $order->regPeriksa->pasien ?? null,
                    'dokter' => $order->perujuk ?? null,
                    'pemeriksaan' => $pemeriksaanStr,
                    'inmut' => $inmut,
                    'siha' => $siha,
                    'hasHiv' => $hasHiv
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            Log::error("Error Inmut Lab: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data indikator lab: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update Lab Quality Indicators & SIHA.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_rawat' => 'required|string',
            'kategori_pasien' => 'required|in:OBSGYN,ANAK,UMUM',
            'jam_ambil_sampel' => 'required',
            'petugas_sampling' => 'required|string',
            'supervisi' => 'required|string',
            'identifikasi_sebelum_pengambilan_darah' => 'required|in:YA,TIDAK',
            'lisis' => 'required|in:YA,TIDAK,BUKAN DARAH',
            'jam_selesai' => 'required',
            'lab_kritis' => 'required|in:YA,TIDAK',
            'keterangan_hasil_lab_kritis' => 'nullable|string',
            'jam_lapor_lab_kritis' => 'nullable',
            'waktu_lapor_lab_kritis' => 'nullable|in:YA,TIDAK',
            'petugas_entri' => 'required|string',
            'tgl_periksa' => 'required|date',
            
            // SIHA fields (Optional)
            'status_kehamilan' => 'nullable|in:TM1,TM2,TM3,Tidak Hamil',
            'penyakit_penyerta' => 'nullable|string',
            'keterangan_siha' => 'nullable|string',
            'status_siha' => 'nullable|integer'
        ]);

        DB::beginTransaction();
        try {
            // Calculate waiting time in minutes
            $waktuTunggu = null;
            if ($validated['jam_ambil_sampel'] && $validated['jam_selesai']) {
                $start = strtotime($validated['jam_ambil_sampel']);
                $end = strtotime($validated['jam_selesai']);
                if ($end >= $start) {
                    $waktuTunggu = ($end - $start) / 60;
                }
            }

            // 1. Handle Inmut Lab
            $inmut = RsiaInmutLab::updateOrCreate(
                [
                    'no_rawat' => $validated['no_rawat'],
                    'tgl_periksa' => $validated['tgl_periksa']
                ],
                [
                    'kategori_pasien' => $validated['kategori_pasien'],
                    'jam_ambil_sampel' => $validated['jam_ambil_sampel'],
                    'petugas_sampling' => $validated['petugas_sampling'],
                    'supervisi' => $validated['supervisi'],
                    'identifikasi_sebelum_pengambilan_darah' => $validated['identifikasi_sebelum_pengambilan_darah'],
                    'lisis' => $validated['lisis'],
                    'jam_selesai' => $validated['jam_selesai'],
                    'lab_kritis' => $validated['lab_kritis'],
                    'keterangan_hasil_lab_kritis' => $validated['keterangan_hasil_lab_kritis'] ?? '',
                    'jam_lapor_lab_kritis' => $validated['jam_lapor_lab_kritis'] ?? '00:00:00',
                    'waktu_lapor_lab_kritis' => $validated['waktu_lapor_lab_kritis'] ?? 'TIDAK',
                    'waktu_tunggu_hasil_lab' => $waktuTunggu,
                    'petugas_entri' => $validated['petugas_entri'],
                    'jam' => date('H:i:s')
                ]
            );

            // 2. Handle SIHA if data provided
            if ($request->has('status_kehamilan') && $request->status_kehamilan) {
                 RsiaSiha::updateOrCreate(
                    ['no_rawat' => $validated['no_rawat']],
                    [
                        'status_kehamilan' => $validated['status_kehamilan'],
                        'penyakit_penyerta' => $validated['penyakit_penyerta'] ?? '',
                        'keterangan' => $validated['keterangan_siha'] ?? '',
                        'status' => $validated['status_siha'] ?? 0
                    ]
                );
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Indikator Mutu Lab berhasil disimpan',
                'data' => $inmut
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Error store Inmut Lab: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $th->getMessage()
            ], 500);
        }
    }
}
