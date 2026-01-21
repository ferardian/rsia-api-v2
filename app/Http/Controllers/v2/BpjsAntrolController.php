<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Services\BpjsAntrolService;
use Illuminate\Http\Request;

class BpjsAntrolController extends Controller
{
    protected $antrolService;

    public function __construct(BpjsAntrolService $antrolService)
    {
        $this->antrolService = $antrolService;
    }

    /**
     * Get queue registration list by date
     * 
     * @param string $tanggal Format Y-m-d
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendaftaranByTanggal($tanggal)
    {
        // Endpoint: /antrean/pendaftaran/tanggal/{tanggal}
        $endpoint = "/antrean/pendaftaran/tanggal/" . $tanggal;
        $response = $this->antrolService->get($endpoint);

        return response()->json($response);
    }

    /**
     * Get dashboard data by date
     * useful for general monitoring
     * 
     * @param string $tanggal Format Y-m-d
     * @param string $waktu rs/sys
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardByTanggal(Request $request)
    {
        $tanggal = $request->query('tanggal', date('Y-m-d'));
        $waktu = $request->query('waktu', 'rs');
        
        $endpoint = "/dashboard/waktu" . $waktu . "/tanggal/" . $tanggal;
        $response = $this->antrolService->get($endpoint);

        return response()->json($response);
    }

    /**
     * Get list of tasks for a booking code
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListTask(Request $request)
    {
        $request->validate([
            'kodebooking' => 'required|string'
        ]);

        $payload = [
            'kodebooking' => $request->kodebooking
        ];

        $endpoint = "/antrean/getlisttask";
        $response = $this->antrolService->post($endpoint, $payload);

        return response()->json($response);
    }

    /**
     * Sync task times from local SIMRS to BPJS
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncTask(Request $request)
    {
        $request->validate([
            'kodebooking' => 'required|string'
        ]);

        $kodebooking = $request->kodebooking;
        
        // 1. Find no_rawat
        $referensi = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        $no_rawat = $referensi ? $referensi->no_rawat : $kodebooking;

        // Check if no_rawat is valid
        $reg = \App\Models\RegPeriksa::where('no_rawat', $no_rawat)->first();
        if (!$reg && $referensi) {
             // maybe it's already no_rawat
             $reg = \App\Models\RegPeriksa::where('no_rawat', $kodebooking)->first();
             if ($reg) $no_rawat = $kodebooking;
        }

        if (!$reg) {
            return response()->json([
                'metadata' => ['code' => 404, 'message' => 'Data No. Rawat tidak ditemukan untuk booking ini.']
            ]);
        }

        $results = [];
        $tasksToSync = [];

        // Task 3: Pemeriksaan Ralan
        $pemeriksaan = \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->first();
        if ($pemeriksaan) {
            $tasksToSync[3] = $pemeriksaan->tgl_perawatan . ' ' . $pemeriksaan->jam_rawat;
        }

        // Task 4: Estimasi Poli
        $estimasi = \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->first();
        if ($estimasi && $estimasi->jam_periksa) {
            $tasksToSync[4] = $estimasi->jam_periksa;
        }

        // Check Resep Obat
        $resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)->first();
        if ($resep) {
            // If prescription exists: 5 (peresepan), 6 (farmasi/jam), 7 (penyerahan)
            if ($resep->tgl_peresepan && $resep->jam_peresepan) {
                $tasksToSync[5] = $resep->tgl_peresepan . ' ' . $resep->jam_peresepan;
            }
            if ($resep->tgl_perawatan && $resep->jam) {
                $tasksToSync[6] = $resep->tgl_perawatan . ' ' . $resep->jam;
            }
            if ($resep->tgl_penyerahan && $resep->jam_penyerahan) {
                $tasksToSync[7] = $resep->tgl_penyerahan . ' ' . $resep->jam_penyerahan;
            }
        } else {
            // Task 5: Selesai Poli (if no prescription)
            $selesai = \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->first();
            if ($selesai && $selesai->jam_periksa) {
                $tasksToSync[5] = $selesai->jam_periksa;
            }
        }

        // Sync to BPJS
        foreach ($tasksToSync as $taskId => $waktuString) {
            $waktuMs = strtotime($waktuString) * 1000;
            if ($waktuMs > 0) {
                $payload = [
                    'kodebooking' => $kodebooking,
                    'taskid' => $taskId,
                    'waktu' => $waktuMs
                ];
                $res = $this->antrolService->post("/antrean/updatewaktu", $payload);
                $results[$taskId] = [
                    'waktu' => $waktuString,
                    'status' => $res['metadata']['message'] ?? 'Unknown',
                    'code' => $res['metadata']['code'] ?? 0
                ];
            }
        }

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'Proses sinkronisasi selesai'],
            'response' => $results,
            'debug' => ['no_rawat' => $no_rawat]
        ]);
    }

    /**
     * Get local task data for adjustment
     */
    public function getLocalData(Request $request)
    {
        $request->validate(['kodebooking' => 'required|string']);
        $kodebooking = $request->kodebooking;

        $referensi = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        $no_rawat = $referensi ? $referensi->no_rawat : $kodebooking;

        $pemeriksaan = \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->first();
        $estimasi = \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->first();
        $selesai = \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->first();
        $resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)->orderBy('no_resep', 'desc')->first();

        $reg = \App\Models\RegPeriksa::where('no_rawat', $no_rawat)->first();
        $nama_pasien = '-';
        if ($reg && $reg->pasien) {
            $nama_pasien = $reg->pasien->nm_pasien;
        }

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => [
                'no_rawat' => $no_rawat,
                'nm_pasien' => $nama_pasien,
                'task3' => $pemeriksaan ? ['tgl' => $pemeriksaan->tgl_perawatan, 'jam' => $pemeriksaan->jam_rawat] : ['tgl' => '', 'jam' => ''],
                'task4' => $estimasi ? $estimasi->jam_periksa : null,
                'task5' => $resep ? ['tgl' => $resep->tgl_peresepan, 'jam' => $resep->jam_peresepan] : ($selesai ? ['tgl' => date('Y-m-d', strtotime($selesai->jam_periksa)), 'jam' => date('H:i:s', strtotime($selesai->jam_periksa))] : ['tgl' => '', 'jam' => '']),
                'task6' => $resep ? ['tgl' => $resep->tgl_perawatan, 'jam' => $resep->jam] : ['tgl' => '', 'jam' => ''],
                'task7' => $resep ? ['tgl' => $resep->tgl_penyerahan, 'jam' => $resep->jam_penyerahan] : ['tgl' => '', 'jam' => ''],
                'has_resep' => !!$resep
            ]
        ]);
    }

    /**
     * Update local task data and sync to BPJS
     */
    public function updateLocalTask(Request $request)
    {
        $request->validate([
            'kodebooking' => 'required|string',
            'no_rawat' => 'required|string',
            'taskid' => 'required|int',
            'waktu' => 'required' // formatted as Y-m-d H:i:s or object with tgl/jam
        ]);

        $no_rawat = $request->no_rawat;
        $taskId = $request->taskid;
        $waktu = $request->waktu;

        // Extract tgl/jam
        if (is_array($waktu)) {
            $tgl = $waktu['tgl'];
            $jam = $waktu['jam'];
            $fullWaktu = $tgl . ' ' . $jam;
        } else {
            $fullWaktu = $waktu;
            $parts = explode(' ', $waktu);
            $tgl = $parts[0] ?? null;
            $jam = $parts[1] ?? null;
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            if ($taskId == 3) {
                \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->update([
                    'tgl_perawatan' => $tgl,
                    'jam_rawat' => $jam
                ]);
            } elseif ($taskId == 4) {
                \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->update([
                    'jam_periksa' => $fullWaktu
                ]);
            } elseif ($taskId == 5) {
                $resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)->first();
                if ($resep) {
                    $resep->update(['tgl_peresepan' => $tgl, 'jam_peresepan' => $jam]);
                } else {
                    \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->update([
                        'jam_periksa' => $fullWaktu
                    ]);
                }
            } elseif ($taskId == 6) {
                \App\Models\ResepObat::where('no_rawat', $no_rawat)->update([
                    'tgl_perawatan' => $tgl,
                    'jam' => $jam
                ]);
            } elseif ($taskId == 7) {
                \App\Models\ResepObat::where('no_rawat', $no_rawat)->update([
                    'tgl_penyerahan' => $tgl,
                    'jam_penyerahan' => $jam
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            // Sync to BPJS
            $waktuMs = strtotime($fullWaktu) * 1000;
            $resBpjs = $this->antrolService->post("/antrean/updatewaktu", [
                'kodebooking' => $request->kodebooking,
                'taskid' => $taskId,
                'waktu' => $waktuMs
            ]);

            return response()->json([
                'metadata' => ['code' => 200, 'message' => 'Update Lokal & BPJS Berhasil'],
                'response' => $resBpjs
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['metadata' => ['code' => 500, 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Get Outpatient SEP count (excluding IGD) for a given date
     */
    public function getSepCount($tanggal)
    {
        $count = \App\Models\BridgingSep::where('tglsep', $tanggal)
            ->where('jnspelayanan', '2') // Rawat Jalan
            ->where('kdpolitujuan', '<>', 'IGD')
            ->count();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $count
        ]);
    }
}
