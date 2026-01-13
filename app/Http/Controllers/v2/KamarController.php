<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Kamar;
use Illuminate\Http\Request;

class KamarController extends Controller
{
    /**
     * Get all kamar with bangsal relation
     */
    public function index(Request $request)
    {
        $query = Kamar::with('bangsal:kd_bangsal,nm_bangsal')
            ->where('statusdata', '1'); // Only active rooms

        // Search by kd_kamar
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('kd_kamar', 'like', "%{$search}%");
        }

        // Filter by bangsal
        if ($request->has('kd_bangsal')) {
            $query->where('kd_bangsal', $request->kd_bangsal);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by kelas
        if ($request->has('kelas')) {
            $query->where('kelas', $request->kelas);
        }

        $limit = $request->get('limit', 100);
        $kamar = $query->limit($limit)->get();

        return response()->json([
            'data' => $kamar
        ]);
    }

    /**
     * Get unique bangsal list for filter
     */
    public function getBangsal()
    {
        $bangsal = \App\Models\Bangsal::select('kd_bangsal', 'nm_bangsal')
            ->where('status', '1')
            ->get();
        return response()->json(['data' => $bangsal]);
    }

    /**
     * Store new kamar
     */
    public function store(Request $request)
    {
        $request->validate([
            'kd_kamar' => 'required|unique:kamar,kd_kamar',
            'kd_bangsal' => 'required',
            'kelas' => 'required',
            'status' => 'required',
            'trf_kamar' => 'required|numeric',
            'keterangan_booking' => 'required_if:status,DIBOOKING'
        ]);

        $data = $request->except(['keterangan_booking']);
        $data['statusdata'] = '1'; // Set as active

        $kamar = Kamar::create($data);

        // If status is DIBOOKING, create indent record
        if ($request->status === 'DIBOOKING' && $request->keterangan_booking) {
            \App\Models\RsiaIndentKamar::create([
                'kd_kamar' => $kamar->kd_kamar,
                'pasien' => $request->keterangan_booking,
                'tanggal_input' => now()
            ]);
        }

        return response()->json([
            'data' => $kamar->load('bangsal')
        ], 201);
    }

    /**
     * Update kamar
     */
    public function update(Request $request, $kd_kamar)
    {
        $kamar = Kamar::where('kd_kamar', $kd_kamar)->first();

        if (!$kamar) {
            return response()->json(['message' => 'Kamar not found'], 404);
        }

        $request->validate([
            'kd_bangsal' => 'required',
            'kelas' => 'required',
            'status' => 'required',
            'trf_kamar' => 'required|numeric',
            'keterangan_booking' => 'required_if:status,DIBOOKING'
        ]);

        $oldStatus = $kamar->status;
        $kamar->update($request->except(['keterangan_booking']));

        // Handle indent kamar
        if ($request->status === 'DIBOOKING' && $request->keterangan_booking) {
            // Delete old indent if exists
            \App\Models\RsiaIndentKamar::where('kd_kamar', $kd_kamar)->delete();
            
            // Create new indent
            \App\Models\RsiaIndentKamar::create([
                'kd_kamar' => $kd_kamar,
                'pasien' => $request->keterangan_booking,
                'tanggal_input' => now()
            ]);
        } elseif ($oldStatus === 'DIBOOKING' && $request->status !== 'DIBOOKING') {
            // If changing from DIBOOKING to other status, delete indent
            \App\Models\RsiaIndentKamar::where('kd_kamar', $kd_kamar)->delete();
        }

        return response()->json([
            'data' => $kamar->load('bangsal')
        ]);
    }

    /**
     * Get indent kamar list
     */
    public function getIndent(Request $request)
    {
        $query = \App\Models\RsiaIndentKamar::with(['kamar.bangsal']);

        // Search by kd_kamar or pasien
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('kd_kamar', 'like', "%{$search}%")
                  ->orWhere('pasien', 'like', "%{$search}%");
            });
        }

        $limit = $request->get('limit', 100);
        $indent = $query->orderBy('tanggal_input', 'desc')->limit($limit)->get();

        return response()->json([
            'data' => $indent
        ]);
    }

    /**
     * Update indent kamar
     */
    public function updateIndent(Request $request, $kd_indent)
    {
        $indent = \App\Models\RsiaIndentKamar::find($kd_indent);

        if (!$indent) {
            return response()->json(['message' => 'Indent not found'], 404);
        }

        $request->validate([
            'pasien' => 'required'
        ]);

        $indent->update([
            'pasien' => $request->pasien,
            'tanggal_input' => now()
        ]);

        return response()->json([
            'data' => $indent->load(['kamar.bangsal'])
        ]);
    }

    /**
     * Delete indent kamar
     */
    public function deleteIndent($kd_indent)
    {
        $indent = \App\Models\RsiaIndentKamar::find($kd_indent);

        if (!$indent) {
            return response()->json(['message' => 'Indent not found'], 404);
        }

        $indent->delete();

        return response()->json(['message' => 'Indent deleted successfully']);
    }

    /**
     * Delete kamar (soft delete by setting statusdata = 0)
     */
    public function destroy($kd_kamar)
    {
        $kamar = Kamar::where('kd_kamar', $kd_kamar)->first();

        if (!$kamar) {
            return response()->json(['message' => 'Kamar not found'], 404);
        }

        // Soft delete by setting statusdata to 0
        $kamar->update(['statusdata' => '0']);

        return response()->json(['message' => 'Kamar deleted successfully']);
    }
}
