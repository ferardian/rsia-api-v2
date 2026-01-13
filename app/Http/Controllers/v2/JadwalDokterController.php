<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\JadwalPoli;
use App\Models\Dokter;
use App\Models\Poliklinik;
use Illuminate\Http\Request;

class JadwalDokterController extends Controller
{
    /**
     * Get all jadwal with relations
     */
    public function index(Request $request)
    {
        $query = JadwalPoli::with(['dokter.spesialis', 'dokter.pegawai', 'poliklinik']);

        // Filters
        if ($request->has('kd_poli')) {
            $query->where('kd_poli', $request->kd_poli);
        }

        if ($request->has('hari_kerja')) {
            $query->where('hari_kerja', $request->hari_kerja);
        }

        if ($request->has('kd_dokter')) {
            $query->where('kd_dokter', $request->kd_dokter);
        }

        $limit = $request->get('limit', 100);
        $jadwal = $query->limit($limit)->get();

        return response()->json([
            'data' => $jadwal
        ]);
    }

    /**
     * Store new jadwal
     */
    public function store(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required',
            'hari_kerja' => 'required',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'kd_poli' => 'required'
        ]);

        $jadwal = JadwalPoli::create($request->all());

        return response()->json([
            'data' => $jadwal->load(['dokter.spesialis', 'poliklinik'])
        ], 201);
    }

    /**
     * Update jadwal by composite key
     */
    public function update(Request $request)
    {
        $kd_dokter = $request->input('_kd_dokter');
        $hari_kerja = $request->input('_hari_kerja');
        $jam_mulai_old = $request->input('_jam_mulai');

        $jadwal = JadwalPoli::where('kd_dokter', $kd_dokter)
            ->where('hari_kerja', $hari_kerja)
            ->where('jam_mulai', $jam_mulai_old)
            ->first();

        if (!$jadwal) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        // Get new data (excluding search keys)
        $newData = $request->except(['_kd_dokter', '_hari_kerja', '_jam_mulai']);
        
        // Delete old record and create new one (because jam_mulai is part of composite key)
        $jadwal->delete();
        $newJadwal = JadwalPoli::create($newData);

        return response()->json([
            'data' => $newJadwal->load(['dokter.spesialis', 'poliklinik'])
        ]);
    }

    /**
     * Delete jadwal by composite key
     */
    public function destroy(Request $request)
    {
        $kd_dokter = $request->input('kd_dokter');
        $hari_kerja = $request->input('hari_kerja');
        $jam_mulai = $request->input('jam_mulai');

        $deleted = JadwalPoli::where('kd_dokter', $kd_dokter)
            ->where('hari_kerja', $hari_kerja)
            ->where('jam_mulai', $jam_mulai)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        return response()->json(['message' => 'Schedule deleted successfully']);
    }

    /**
     * Get all dokter for dropdown
     */
    public function getDokter()
    {
        $dokter = Dokter::select('kd_dokter', 'nm_dokter', 'kd_sps')
            ->with('spesialis:kd_sps,nm_sps')
            ->limit(1000)
            ->get();

        return response()->json(['data' => $dokter]);
    }

    /**
     * Get all poliklinik for dropdown
     */
    public function getPoliklinik()
    {
        $poli = Poliklinik::select('kd_poli', 'nm_poli')
            ->limit(1000)
            ->get();

        return response()->json(['data' => $poli]);
    }
}
