<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferImunisasiController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('riwayat_imunisasi')
            ->join('master_imunisasi', 'riwayat_imunisasi.kode_imunisasi', '=', 'master_imunisasi.kode_imunisasi')
            ->join('pasien', 'riwayat_imunisasi.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->select(
                'riwayat_imunisasi.no_rkm_medis',
                'pasien.nm_pasien',
                'pasien.tgl_lahir',
                'riwayat_imunisasi.kode_imunisasi',
                'master_imunisasi.nama_imunisasi',
                'riwayat_imunisasi.no_imunisasi'
            );

        if ($request->has('q')) {
            $query->where('pasien.nm_pasien', 'like', '%' . $request->q . '%')
                ->orWhere('riwayat_imunisasi.no_rkm_medis', 'like', '%' . $request->q . '%');
        }

        return $query->paginate($request->per_page ?? 15);
    }

    public function store(Request $request)
    {
        // Define Mapping: [OldKode => [NoImunisasi => NewID]] or [OldKode => NewID (if no_imunisasi doesn't matter)]
        // Using strict mapping based on user discussion and common logic
        $mapping = [
            '01' => 1, // HB 0 -> All doses map to ID 1
            '02' => 2, // BCG -> All doses map to ID 2
            '05' => [  // DPT PENTABIO (Specific)
                1 => 4, 
                2 => 6, 
                3 => 8, 
            ], 
            '07' => 10, // IPV -> ID 10
            '08' => 11, // MR -> ID 11
            '16' => 11, // Campak -> ID 11 (MR)
        ];

        // Fetch all legacy data that hasn't been migrated yet? 
        // Or just migrate everything selected? 
        // For now, let's migrate all or filtered by request.
        // To avoid duplicates, we should check if exists in rsia_riwayat_imunisasi

        $query = DB::table('riwayat_imunisasi')
            ->join('pasien', 'riwayat_imunisasi.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->select('riwayat_imunisasi.*', 'pasien.tgl_lahir');

        if ($request->has('q') && !empty($request->q)) {
            $query->where(function($q) use ($request) {
                $q->where('pasien.nm_pasien', 'like', '%' . $request->q . '%')
                  ->orWhere('riwayat_imunisasi.no_rkm_medis', 'like', '%' . $request->q . '%');
            });
        }

        $legacyData = $query->get();

        $count = 0;
        $errors = [];

        foreach ($legacyData as $row) {
            $newId = null;

            // Check mapping
            if (isset($mapping[$row->kode_imunisasi])) {
                if (is_array($mapping[$row->kode_imunisasi])) {
                    if (isset($mapping[$row->kode_imunisasi][$row->no_imunisasi])) {
                        $newId = $mapping[$row->kode_imunisasi][$row->no_imunisasi];
                    }
                } else {
                    $newId = $mapping[$row->kode_imunisasi];
                }
            }

            if ($newId) {
                // Check if already exists to prevent duplicate
                $exists = DB::table('rsia_riwayat_imunisasi')
                    ->where('no_rkm_medis', $row->no_rkm_medis)
                    ->where('master_imunisasi_id', $newId)
                    ->exists();

                if (!$exists) {
                    try {
                        // Get usia_bulan from master
                        $masterVaksin = DB::table('rsia_master_imunisasi')->where('id', $newId)->first();
                        $usiaBulan = $masterVaksin->usia_bulan ?? 0;
                        
                        // Calculate tgl_pemberian = tgl_lahir + usia_bulan
                        $tglLahir = \Carbon\Carbon::parse($row->tgl_lahir);
                        $tglPemberian = $tglLahir->addMonths($usiaBulan)->format('Y-m-d');

                        DB::table('rsia_riwayat_imunisasi')->insert([
                            'no_rkm_medis' => $row->no_rkm_medis,
                            'master_imunisasi_id' => $newId,
                            'tgl_pemberian' => $tglPemberian,
                            'catatan' => 'Migrasi Data Lama (Kode: ' . $row->kode_imunisasi . ', No: ' . $row->no_imunisasi . ')',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $count++;
                    } catch (\Exception $e) {
                        $errors[] = "Error migrating {$row->no_rkm_medis}: {$e->getMessage()}";
                    }
                }
            }
        }

        return response()->json([
            'message' => "Berhasil memigrasikan {$count} data.",
            'errors' => $errors
        ]);
    }
}
