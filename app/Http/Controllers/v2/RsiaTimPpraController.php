<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RsiaTimPpra;

class RsiaTimPpraController extends Controller
{
    public function index(Request $request)
    {
        $query = RsiaTimPpra::with(['pegawai' => function ($q) {
            $q->select('nik', 'nama', 'jbtn', 'photo');
        }]);

        if ($request->has('keyword') && $request->keyword != '') {
            $keyword = $request->keyword;
            $query->where('jabatan', 'like', "%{$keyword}%")
                  ->orWhere('role', 'like', "%{$keyword}%")
                  ->orWhereHas('pegawai', function ($q) use ($keyword) {
                      $q->where('nama', 'like', "%{$keyword}%")
                        ->orWhere('nik', 'like', "%{$keyword}%");
                  });
        }

        $tim = $query->orderByRaw("FIELD(jabatan, 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota')")->get();

        return response()->json([
            'success' => true,
            'message' => 'Data Tim PPRA fetched successfully',
            'data'    => $tim
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nik'     => 'required|exists:pegawai,nik',
            'jabatan' => 'required|string',
            'role'    => 'nullable|string',
        ]);

        // Check if NIK already exists in tim_ppra
        if (RsiaTimPpra::where('nik', $request->nik)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai ini sudah masuk dalam Tim PPRA'
            ], 422);
        }

        $tim = new RsiaTimPpra();
        $tim->nik = $validated['nik'];
        $tim->jabatan = $validated['jabatan'];
        $tim->role = $validated['role'] ?? null;
        $tim->save();

        return response()->json([
            'success' => true,
            'message' => 'Anggota Tim PPRA berhasil ditambahkan',
            'data'    => $tim
        ]);
    }

    public function update(Request $request, $id)
    {
        $tim = RsiaTimPpra::find($id);

        if (!$tim) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'jabatan' => 'required|string',
            'role'    => 'nullable|string',
        ]);

        $tim->jabatan = $validated['jabatan'];
        $tim->role = $validated['role'] ?? null;
        $tim->save();

        return response()->json([
            'success' => true,
            'message' => 'Data Tim PPRA berhasil diperbarui',
            'data'    => $tim
        ]);
    }

    public function destroy($id)
    {
        $tim = RsiaTimPpra::find($id);

        if (!$tim) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $tim->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anggota Tim PPRA berhasil dihapus'
        ]);
    }
}
