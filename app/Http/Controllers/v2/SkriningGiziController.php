<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaSkriningGizi;
use Illuminate\Http\Request;

class SkriningGiziController extends Controller
{
    /**
     * Display a listing of skrining gizi
     */
    public function index(Request $request)
    {
        try {
            $query = RsiaSkriningGizi::with(['regPeriksa.pasien', 'kamarInap.kamar.bangsal']);

            // Date Filter
            $tgl_awal = $request->input('tgl_awal');
            $tgl_akhir = $request->input('tgl_akhir');

            if ($tgl_awal && $tgl_akhir) {
                $query->whereHas('regPeriksa', function($q) use ($tgl_awal, $tgl_akhir) {
                    $q->whereBetween('tgl_registrasi', [$tgl_awal, $tgl_akhir]);
                });
            } else {
                // Default: Belum Pulang (stts_pulang = '-')
                $query->whereHas('kamarInap', function($q) {
                    $q->where('stts_pulang', '-');
                });
            }

            // Filter by no_rawat
            if ($request->has('no_rawat')) {
                $query->where('no_rawat', $request->no_rawat);
            }

            $limit = $request->input('limit', 15);
            $data = $query->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error index skrining gizi: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified skrining gizi
     */
    public function show($no_rawat)
    {
        try {
            $data = RsiaSkriningGizi::where('no_rawat', $no_rawat)
                ->with('regPeriksa.pasien')
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data skrining gizi tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error show skrining gizi: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created skrining gizi
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_rawat' => 'required|string|exists:reg_periksa,no_rawat',
            'bb' => 'nullable|numeric|min:0',
            'tb' => 'nullable|numeric|min:0',
            'imt' => 'nullable|numeric',
            'lila' => 'nullable|numeric|min:0',
            'skor' => 'nullable|integer',
            'keterangan' => 'nullable|string|max:200',
            'jenis_diet' => 'nullable|in:Diet Nasi,Diet Bubur,Diet Nasi Tim,Diet Cair,Puasa,Diet Bubur Tim,Diet Bubur Tim Saring',
            'status_jenis_diet' => 'nullable|in:0,1',
            'status_assesment_lanjut' => 'nullable|in:Sudah,Belum,Tidak',
            'diagnosa_medis' => 'nullable|string|max:200',
            'hb' => 'nullable|numeric',
            'hiv' => 'nullable|in:Reaktif,Non Reaktif,Tidak Periksa',
            'hbsag' => 'nullable|in:Reaktif,Non Reaktif,Tidak Periksa',
            'syphilis' => 'nullable|in:Reaktif,Non Reaktif,Tidak Periksa',
            'cb_obgyn' => 'nullable|string|max:200',
            'cb_anak1' => 'nullable|string|max:200',
            'cb_anak2' => 'nullable|string|max:200',
            'kategori' => 'nullable|in:OBGYN,ANAK',
            'q_anak' => 'nullable|string|max:200',
            'q_obgyn' => 'nullable|string|max:200'
        ]);

        try {
            // Check if exists
            $exists = RsiaSkriningGizi::where('no_rawat', $validated['no_rawat'])->exists();

            if ($exists) {
                // Update
                RsiaSkriningGizi::where('no_rawat', $validated['no_rawat'])
                    ->update($validated);
            } else {
                // Insert
                RsiaSkriningGizi::create($validated);
            }

            // Fetch the record
            $data = RsiaSkriningGizi::where('no_rawat', $validated['no_rawat'])
                ->with('regPeriksa.pasien')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Skrining gizi berhasil disimpan',
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error store skrining gizi: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan skrining gizi: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified skrining gizi
     */
    public function destroy($no_rawat)
    {
        try {
            $deleted = RsiaSkriningGizi::where('no_rawat', $no_rawat)->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data skrining gizi tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Skrining gizi berhasil dihapus'
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error delete skrining gizi: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus skrining gizi: ' . $th->getMessage()
            ], 500);
        }
    }
}
