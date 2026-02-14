<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaMasterImunisasi;
use App\Models\RsiaRiwayatImunisasi;
use App\Models\Pasien;
use Illuminate\Http\Request;

class VaccinationController extends Controller
{
    public function index()
    {
        $vaccines = RsiaMasterImunisasi::orderBy('usia_bulan', 'asc')->get();
        return response()->json([
            'success' => true,
            'message' => 'Data master imunisasi berhasil diambil',
            'data' => $vaccines
        ]);
    }

    public function history(Request $request)
    {
        $request->validate([
            'no_rkm_medis' => 'required',
            'tgl_lahir' => 'required|date' // Needed to calculate due dates
        ]);

        $noRkmMedis = $request->no_rkm_medis;
        $tglLahir = $request->tgl_lahir;

        // Get all standard vaccines
        $masterVaccines = RsiaMasterImunisasi::orderBy('usia_bulan', 'asc')->get();

        // Get patient's history
        $history = RsiaRiwayatImunisasi::where('no_rkm_medis', $noRkmMedis)->get()->keyBy('master_imunisasi_id');

        $result = $masterVaccines->map(function ($vaccine) use ($history, $tglLahir) {
            $isDone = isset($history[$vaccine->id]);
            $historyData = $isDone ? $history[$vaccine->id] : null;

            // Calculate due date based on birth date + age (months)
            $dueDate = date('Y-m-d', strtotime("+$vaccine->usia_bulan months", strtotime($tglLahir)));
            
            // Status: Done, Due Soon, Overdue, or Upcoming
            $status = 'upcoming';
            $today = date('Y-m-d');

            if ($isDone) {
                $status = 'done';
            } else {
                if ($today > $dueDate) {
                    $status = 'overdue';
                } elseif (date('Y-m-d', strtotime("+7 days")) >= $dueDate && $today <= $dueDate) {
                    $status = 'due_soon';
                }
            }

            return [
                'id' => $vaccine->id,
                'nama_vaksin' => $vaccine->nama_vaksin,
                'usia_bulan' => $vaccine->usia_bulan,
                'deskripsi' => $vaccine->deskripsi,
                'due_date' => $dueDate,
                'status' => $status,
                'transaksi' => $historyData
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat imunisasi berhasil diambil',
            'data' => $result
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_rkm_medis' => 'required|exists:pasien,no_rkm_medis',
            'master_imunisasi_id' => 'required|exists:rsia_master_imunisasi,id',
            'tgl_pemberian' => 'required|date',
            'catatan' => 'nullable|string'
        ]);

        $history = RsiaRiwayatImunisasi::updateOrCreate(
            [
                'no_rkm_medis' => $request->no_rkm_medis,
                'master_imunisasi_id' => $request->master_imunisasi_id
            ],
            [
                'tgl_pemberian' => $request->tgl_pemberian,
                'catatan' => $request->catatan
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Data imunisasi berhasil disimpan',
            'data' => $history
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'no_rkm_medis' => 'required',
            'master_imunisasi_id' => 'required'
        ]);

        $deleted = RsiaRiwayatImunisasi::where('no_rkm_medis', $request->no_rkm_medis)
            ->where('master_imunisasi_id', $request->master_imunisasi_id)
            ->delete();

        if ($deleted) {
             return response()->json([
                'success' => true,
                'message' => 'Data imunisasi berhasil dihapus'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus data'
        ], 400);
    }
}
