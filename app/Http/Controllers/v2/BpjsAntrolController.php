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

        // Enrich response with local data (Patient Name & Doctor Name)
        if (isset($response['response']) && is_array($response['response'])) {
            foreach ($response['response'] as &$item) {
                // Default values
                $item['nama_pasien'] = '-';
                $item['nama_dokter'] = '-';

                // Strategy 1: Match via referensi_mobilejkn_bpjs (most reliable for bridging)
                $ref = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $item['kodebooking'])->first();
                
                if ($ref) {
                    $reg = \App\Models\RegPeriksa::where('no_rawat', $ref->no_rawat)
                        ->with(['pasien', 'dokter'])
                        ->first();
                    
                    if ($reg) {
                        $item['nama_pasien'] = $reg->pasien->nm_pasien ?? '-';
                        $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                        continue; // Found, move to next item
                    }
                }

                // Strategy 2: Match via BPJS Card verify against local patient data
                // This is a fallback if bridging data is missing but patient exists
                if (!empty($item['nopeserta'])) {
                    $pasien = \App\Models\Pasien::where('no_kartu', $item['nopeserta'])->first();
                    if ($pasien) {
                         $item['nama_pasien'] = $pasien->nm_pasien;
                         
                         // Try to find registration by no_rkm_medis and date to get doctor
                         $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                            ->where('tgl_registrasi', $tanggal)
                            ->with('dokter')
                            ->first();

                         if ($reg) {
                             $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                         }
                         continue; // Found, move to next item
                    }
                }

                // Strategy 3: Match via Medical Record Number (RM)
                // Fallback for cases where BPJS card number in local DB might differ or be empty
                if (!empty($item['norekammedis']) && $item['nama_pasien'] == '-') {
                    $pasien = \App\Models\Pasien::where('no_rkm_medis', $item['norekammedis'])->first();
                    if ($pasien) {
                        $item['nama_pasien'] = $pasien->nm_pasien;

                        // Try to find registration by no_rkm_medis and date to get doctor
                        $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                            ->where('tgl_registrasi', $tanggal)
                            ->with('dokter')
                            ->first();

                        if ($reg) {
                            $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                        }
                    }
                }
            }
        }

        return response()->json($response);
    }

    /**
     * Get queue registration list by date range
     * 
     * @param string $tglAwal Format Y-m-d
     * @param string $tglAkhir Format Y-m-d
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendaftaranByRange($tglAwal, $tglAkhir)
    {
        $startDate = new \DateTime($tglAwal);
        $endDate = new \DateTime($tglAkhir);
        $interval = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        $allPendaftaran = [];
        $metadata = ['code' => 200, 'message' => 'OK'];

        foreach ($dateRange as $date) {
            $currentDate = $date->format('Y-m-d');
            $endpoint = "/antrean/pendaftaran/tanggal/" . $currentDate;
            $response = $this->antrolService->get($endpoint);

            if (isset($response['response']) && is_array($response['response'])) {
                foreach ($response['response'] as $item) {
                    $item['nama_pasien'] = '-';
                    $item['nama_dokter'] = '-';

                    $ref = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $item['kodebooking'])->first();
                    if ($ref) {
                        $reg = \App\Models\RegPeriksa::where('no_rawat', $ref->no_rawat)
                            ->with(['pasien', 'dokter'])
                            ->first();
                        if ($reg) {
                            $item['nama_pasien'] = $reg->pasien->nm_pasien ?? '-';
                            $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                        }
                    }

                    if ($item['nama_pasien'] == '-' && !empty($item['nopeserta'])) {
                        $pasien = \App\Models\Pasien::where('no_kartu', $item['nopeserta'])->first();
                        if ($pasien) {
                             $item['nama_pasien'] = $pasien->nm_pasien;
                             $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                                ->where('tgl_registrasi', $currentDate)
                                ->with('dokter')
                                ->first();
                             if ($reg) $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                        }
                    }

                    if ($item['nama_pasien'] == '-' && !empty($item['norekammedis'])) {
                        $pasien = \App\Models\Pasien::where('no_rkm_medis', $item['norekammedis'])->first();
                        if ($pasien) {
                            $item['nama_pasien'] = $pasien->nm_pasien;
                            $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                                ->where('tgl_registrasi', $currentDate)
                                ->with('dokter')
                                ->first();
                            if ($reg) $item['nama_dokter'] = $reg->dokter->nm_dokter ?? '-';
                        }
                    }
                    $allPendaftaran[] = $item;
                }
            } else if (isset($response['metadata'])) {
                $code = $response['metadata']['code'];
                // Only consider it a range-blocking error if it's not 200/201/204
                if ($code != 200 && $code != 201 && $code != 204) {
                    $metadata = $response['metadata'];
                }
            } else {
                \Illuminate\Support\Facades\Log::warning("No data for $currentDate. Metadata might be missing.");
            }
        }
        
        // If we have aggregated data, ensure metadata is 200
        if (count($allPendaftaran) > 0) {
            $metadata = ['code' => 200, 'message' => 'OK'];
        }

        return response()->json([
            'metadata' => $metadata,
            'response' => $allPendaftaran
        ]);
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
             if ($reg) {
                 $no_rawat = $kodebooking;
             } else if (!empty($referensi->norm) && !empty($referensi->tanggalperiksa)) {
                 // Fallback logic
                 $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $referensi->norm)
                     ->where('tgl_registrasi', $referensi->tanggalperiksa)
                     ->orderBy('jam_reg', 'desc')
                     ->first();
                 if ($reg) {
                     $no_rawat = $reg->no_rawat;
                 }
             }
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

        // Check Resep Obat - Pick the one with valid prescription date/time
        $resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)
            ->where('tgl_peresepan', '!=', '0000-00-00')
            ->orderBy('no_resep', 'desc')
            ->first();

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

        $reg = \App\Models\RegPeriksa::where('no_rawat', $no_rawat)->first();
        if (!$reg && $referensi) {
             $reg = \App\Models\RegPeriksa::where('no_rawat', $kodebooking)->first();
             if ($reg) {
                 $no_rawat = $kodebooking;
             } else if (!empty($referensi->norm) && !empty($referensi->tanggalperiksa)) {
                 // Fallback: search by norm (Medical Record) and date
                 $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $referensi->norm)
                     ->where('tgl_registrasi', $referensi->tanggalperiksa)
                     ->orderBy('jam_reg', 'desc')
                     ->first();
                 if ($reg) {
                     $no_rawat = $reg->no_rawat; // Overwrite to correctly mapped registration
                 }
             }
        }

        if (!$reg) {
            return response()->json([
                'metadata' => ['code' => 404, 'message' => 'Data Pendaftaran tidak ditemukan untuk booking ini.']
            ]);
        }

        $pemeriksaan = \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->first();
        $estimasi = \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->first();
        $selesai = \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->first();
        $resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)
            ->where('tgl_peresepan', '!=', '0000-00-00')
            ->orderBy('no_resep', 'desc')
            ->first();

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

    public function syncTaskQueue(Request $request)
    {
        $kodebooking = $request->kodebooking;
        
        // Get registration data using ReferensiMobilejknBpjs
        $referensi = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        $no_rawat = $referensi ? $referensi->no_rawat : $kodebooking;

        $reg = \App\Models\RegPeriksa::where('no_rawat', $no_rawat)->first();
        if (!$reg && $referensi) {
             $reg = \App\Models\RegPeriksa::where('no_rawat', $kodebooking)->first();
             if ($reg) {
                 $no_rawat = $kodebooking;
             } else if (!empty($referensi->norm) && !empty($referensi->tanggalperiksa)) {
                 $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $referensi->norm)
                     ->where('tgl_registrasi', $referensi->tanggalperiksa)
                     ->orderBy('jam_reg', 'desc')
                     ->first();
                 if ($reg) {
                     $no_rawat = $reg->no_rawat;
                 }
             }
        }

        if (!$reg) {
            return response()->json(['metadata' => ['code' => 404, 'message' => 'Data antrean tidak ditemukan']]);
        }
        
        // Check if prescription exists
        $hasResep = \App\Models\ResepObat::where('no_rawat', $no_rawat)->exists();
        
        try {
            $results = [];
            
            // Task 3: Mulai Pemeriksaan
            $task3 = \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->first();
            if ($task3) {
                $waktu3 = strtotime($task3->tgl_perawatan . ' ' . $task3->jam_rawat) * 1000;
                $res3 = $this->antrolService->post("/antrean/updatewaktu", [
                    'kodebooking' => $kodebooking,
                    'taskid' => 3,
                    'waktu' => $waktu3
                ]);
                $results['task3'] = $res3;
            }
            
            // Task 4: Estimasi Selesai Pemeriksaan
            $task4 = \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->first();
            if ($task4) {
                $waktu4 = strtotime($task4->jam_periksa) * 1000;
                $res4 = $this->antrolService->post("/antrean/updatewaktu", [
                    'kodebooking' => $kodebooking,
                    'taskid' => 4,
                    'waktu' => $waktu4
                ]);
                $results['task4'] = $res4;
            }
            
            // Task 5: Selesai Pemeriksaan / Peresepan
            $task5Resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)
                ->where('tgl_peresepan', '!=', '0000-00-00')
                ->orderBy('no_resep', 'desc')
                ->first();
            if ($task5Resep) {
                $waktu5 = strtotime($task5Resep->tgl_peresepan . ' ' . $task5Resep->jam_peresepan) * 1000;
            } else {
                $task5Selesai = \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->first();
                if ($task5Selesai) {
                    $waktu5 = strtotime($task5Selesai->jam_periksa) * 1000;
                }
            }
            if (isset($waktu5)) {
                $res5 = $this->antrolService->post("/antrean/updatewaktu", [
                    'kodebooking' => $kodebooking,
                    'taskid' => 5,
                    'waktu' => $waktu5
                ]);
                $results['task5'] = $res5;
            }
            
            // Only sync tasks 6-7 if prescription exists
            if ($hasResep) {
                // Task 6: Racik Obat
                $task6 = \App\Models\ResepObat::where('no_rawat', $no_rawat)
                    ->where('tgl_peresepan', '!=', '0000-00-00')
                    ->orderBy('no_resep', 'desc')
                    ->first();
                if ($task6 && $task6->tgl_perawatan && $task6->jam) {
                    $waktu6 = strtotime($task6->tgl_perawatan . ' ' . $task6->jam) * 1000;
                    $res6 = $this->antrolService->post("/antrean/updatewaktu", [
                        'kodebooking' => $kodebooking,
                        'taskid' => 6,
                        'waktu' => $waktu6
                    ]);
                    $results['task6'] = $res6;
                }
                
                // Task 7: Serah Terima Obat
                if ($task6 && $task6->tgl_penyerahan && $task6->jam_penyerahan) {
                    $waktu7 = strtotime($task6->tgl_penyerahan . ' ' . $task6->jam_penyerahan) * 1000;
                    $res7 = $this->antrolService->post("/antrean/updatewaktu", [
                        'kodebooking' => $kodebooking,
                        'taskid' => 7,
                        'waktu' => $waktu7
                    ]);
                    $results['task7'] = $res7;
                }
            }
            
            return response()->json([
                'metadata' => ['code' => 200, 'message' => 'Sinkronisasi task berhasil'],
                'response' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['metadata' => ['code' => 500, 'message' => $e->getMessage()]]);
        }
    }

    /**
     * Get Outpatient SEP list (excluding IGD) for a given date
     */
    public function getSepCount($tanggal)
    {
        $seps = \App\Models\BridgingSep::where('tglsep', $tanggal)
            ->where('jnspelayanan', '2') // Rawat Jalan
            ->where('kdpolitujuan', '<>', 'IGD')
            ->select('no_kartu', 'nama_pasien', 'no_sep', 'no_rawat')
            ->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $seps
        ]);
    }

    /**
     * Get Outpatient SEP list (excluding IGD) for a given date range
     * 
     * @param string $tglAwal Format Y-m-d
     * @param string $tglAkhir Format Y-m-d
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSepCountByRange($tglAwal, $tglAkhir)
    {
        $seps = \App\Models\BridgingSep::whereBetween('tglsep', [$tglAwal, $tglAkhir])
            ->where('jnspelayanan', '2') // Rawat Jalan
            ->where('kdpolitujuan', '<>', 'IGD')
            ->select('no_kartu', 'nama_pasien', 'no_sep', 'no_rawat')
            ->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $seps
        ]);
    }

    public function syncTaskQueueBulk(Request $request)
    {
        $request->validate([
            'kodebookings' => 'required|array',
            'kodebookings.*' => 'string'
        ]);

        $kodebookings = $request->kodebookings;
        $results = [];

        foreach ($kodebookings as $kodebooking) {
            $referensi = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
            $no_rawat = $referensi ? $referensi->no_rawat : $kodebooking;

            $reg = \App\Models\RegPeriksa::where('no_rawat', $no_rawat)->first();
            if (!$reg && $referensi) {
                 $reg = \App\Models\RegPeriksa::where('no_rawat', $kodebooking)->first();
                 if ($reg) {
                     $no_rawat = $kodebooking;
                 } else if (!empty($referensi->norm) && !empty($referensi->tanggalperiksa)) {
                     $reg = \App\Models\RegPeriksa::where('no_rkm_medis', $referensi->norm)
                         ->where('tgl_registrasi', $referensi->tanggalperiksa)
                         ->orderBy('jam_reg', 'desc')
                         ->first();
                     if ($reg) {
                         $no_rawat = $reg->no_rawat;
                     }
                 }
            }

            if (!$reg) {
                $results[$kodebooking] = ['status' => 'Data antrean tidak ditemukan'];
                continue;
            }
            $hasResep = \App\Models\ResepObat::where('no_rawat', $no_rawat)->exists();

            $patientTasks = [];
            
            try {
                // Task 3: Mulai Pemeriksaan
                $task3 = \App\Models\PemeriksaanRalan::where('no_rawat', $no_rawat)->first();
                if ($task3) {
                    $waktu3 = strtotime($task3->tgl_perawatan . ' ' . $task3->jam_rawat) * 1000;
                    $patientTasks['task3'] = $this->antrolService->post("/antrean/updatewaktu", [
                        'kodebooking' => $kodebooking,
                        'taskid' => 3,
                        'waktu' => $waktu3
                    ]);
                }
                
                // Task 4: Estimasi Selesai Pemeriksaan
                $task4 = \Illuminate\Support\Facades\DB::table('rsia_estimasi_poli')->where('no_rawat', $no_rawat)->first();
                if ($task4) {
                    $waktu4 = strtotime($task4->jam_periksa) * 1000;
                    $patientTasks['task4'] = $this->antrolService->post("/antrean/updatewaktu", [
                        'kodebooking' => $kodebooking,
                        'taskid' => 4,
                        'waktu' => $waktu4
                    ]);
                }
                
                // Task 5: Selesai Pemeriksaan / Peresepan
                $task5Resep = \App\Models\ResepObat::where('no_rawat', $no_rawat)
                    ->where('tgl_peresepan', '!=', '0000-00-00')
                    ->orderBy('no_resep', 'desc')
                    ->first();
                if ($task5Resep) {
                    $waktu5 = strtotime($task5Resep->tgl_peresepan . ' ' . $task5Resep->jam_peresepan) * 1000;
                } else {
                    $task5Selesai = \Illuminate\Support\Facades\DB::table('rsia_selesai_poli')->where('no_rawat', $no_rawat)->first();
                    if ($task5Selesai) {
                        $waktu5 = strtotime($task5Selesai->jam_periksa) * 1000;
                    }
                }
                if (isset($waktu5)) {
                    $patientTasks['task5'] = $this->antrolService->post("/antrean/updatewaktu", [
                        'kodebooking' => $kodebooking,
                        'taskid' => 5,
                        'waktu' => $waktu5
                    ]);
                }
                
                if ($hasResep) {
                    $task6 = \App\Models\ResepObat::where('no_rawat', $no_rawat)
                        ->where('tgl_peresepan', '!=', '0000-00-00')
                        ->orderBy('no_resep', 'desc')
                        ->first();
                    if ($task6 && $task6->tgl_perawatan && $task6->jam) {
                        $waktu6 = strtotime($task6->tgl_perawatan . ' ' . $task6->jam) * 1000;
                        $patientTasks['task6'] = $this->antrolService->post("/antrean/updatewaktu", [
                            'kodebooking' => $kodebooking,
                            'taskid' => 6,
                            'waktu' => $waktu6
                        ]);
                    }
                    if ($task6 && $task6->tgl_penyerahan && $task6->jam_penyerahan) {
                        $waktu7 = strtotime($task6->tgl_penyerahan . ' ' . $task6->jam_penyerahan) * 1000;
                        $patientTasks['task7'] = $this->antrolService->post("/antrean/updatewaktu", [
                            'kodebooking' => $kodebooking,
                            'taskid' => 7,
                            'waktu' => $waktu7
                        ]);
                    }
                }
                $results[$kodebooking] = $patientTasks;
            } catch (\Exception $e) {
                $results[$kodebooking] = ['error' => $e->getMessage()];
            }
        }
        
        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'Sinkronisasi bulk berhasil'],
            'response' => $results
        ]);
    }

    /**
     * Cancel queue
     */
    public function cancelAntrean(Request $request)
    {
        $request->validate([
            'kodebooking' => 'required|string',
            'keterangan' => 'required|string'
        ]);

        $payload = [
            'kodebooking' => $request->kodebooking,
            'keterangan' => $request->keterangan
        ];

        $response = $this->antrolService->cancelAntrean($payload);

        return response()->json($response);
    }
}
