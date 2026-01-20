<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaKomite;
use App\Models\RsiaJabatanKomite;
use App\Models\RsiaAnggotaKomite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitteeController extends Controller
{
    public function index()
    {
        $committees = RsiaKomite::where('status', 1)->get();
        $positions = RsiaJabatanKomite::all();

        return response()->json([
            'success' => true,
            'message' => 'List data komite dan jabatan',
            'data' => [
                'committees' => $committees,
                'positions' => $positions
            ]
        ]);
    }

    public function indexMembers()
    {
        $members = RsiaAnggotaKomite::with(['komite', 'jabatan', 'pegawai'])
            ->orderBy('komite_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'List semua anggota komite',
            'data' => $members
        ]);
    }

    public function getByNik($nik)
    {
        $memberships = RsiaAnggotaKomite::with(['komite', 'jabatan'])
            ->where('nik', $nik)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data komite pegawai',
            'data' => $memberships
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nik' => 'required|string',
            'komite_id' => 'required|exists:rsia_komite,id',
            'jabatan_id' => 'required|exists:rsia_jabatan_komite,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date',
            'sk_nomor' => 'nullable|string',
        ]);

        try {
            $membership = RsiaAnggotaKomite::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menambahkan anggota komite',
                'data' => $membership
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota komite: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'komite_id' => 'required|exists:rsia_komite,id',
            'jabatan_id' => 'required|exists:rsia_jabatan_komite,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date',
            'sk_nomor' => 'nullable|string',
        ]);

        try {
            $membership = RsiaAnggotaKomite::findOrFail($id);
            $membership->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Berhasil memperbarui data komite',
                'data' => $membership
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data komite: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $membership = RsiaAnggotaKomite::findOrFail($id);
            $membership->delete();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus anggota komite'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota komite: ' . $e->getMessage()
            ], 500);
        }
    }
}
