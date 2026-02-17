<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaHfisSkJadwal;
use App\Models\RsiaHfisSkJadwalDetail;
use App\Models\Dokter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class RsiaHfisSkJadwalController extends Controller
{
    public function index(Request $request)
    {
        $query = RsiaHfisSkJadwal::with(['dokter.spesialis', 'detail']);

        if ($request->has('kd_dokter')) {
            $query->where('kd_dokter', $request->kd_dokter);
        }

        if ($request->has('keyword')) {
            $query->whereHas('dokter', function($q) use ($request) {
                $q->where('nm_dokter', 'like', '%' . $request->keyword . '%');
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate($request->get('limit', 10))
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required',
            'tgl_surat' => 'required|date',
            'details' => 'required|array|min:1'
        ]);

        try {
            DB::beginTransaction();

            $header = RsiaHfisSkJadwal::create($request->only([
                'kd_dokter', 'nama_pic', 'jabatan_pic', 'tgl_surat', 
                'faskes_sip1', 'faskes_sip2', 'faskes_sip3'
            ]));

            foreach ($request->details as $detail) {
                $header->detail()->create($detail);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil disimpan',
                'data' => $header->load('detail')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $data = RsiaHfisSkJadwal::with(['dokter.spesialis', 'detail'])->find($id);

        if (!$data) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function update(Request $request, $id)
    {
        $header = RsiaHfisSkJadwal::find($id);

        if (!$header) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        $request->validate([
            'kd_dokter' => 'required',
            'tgl_surat' => 'required|date',
            'details' => 'required|array|min:1'
        ]);

        try {
            DB::beginTransaction();

            $header->update($request->only([
                'kd_dokter', 'nama_pic', 'jabatan_pic', 'tgl_surat', 
                'faskes_sip1', 'faskes_sip2', 'faskes_sip3'
            ]));

            // Simple approach: delete all details and recreate
            $header->detail()->delete();
            foreach ($request->details as $detail) {
                $header->detail()->create($detail);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil diupdate',
                'data' => $header->load('detail')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $header = RsiaHfisSkJadwal::find($id);

        if (!$header) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        $header->delete();
        return response()->json(['status' => 'success', 'message' => 'Data berhasil dihapus']);
    }

    public function generatePdf($id)
    {
        $data = RsiaHfisSkJadwal::with(['dokter.spesialis', 'detail'])->find($id);

        if (!$data) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        $items = collect([$data]);
        $pdf = Pdf::loadView('pdf.hfis.sk_jadwal', compact('items'))
                    ->setPaper('a4', 'portrait');

        return $pdf->stream('sk_jadwal_hfis_' . $data->id . '.pdf');
    }

    public function generatePdfBulk(Request $request)
    {
        $ids = $request->get('ids');
        if (!$ids || !is_array($ids)) {
            return response()->json(['status' => 'error', 'message' => 'ID tidak valid'], 400);
        }

        $items = RsiaHfisSkJadwal::with(['dokter.spesialis', 'detail'])
                    ->whereIn('id', $ids)
                    ->get();

        if ($items->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        $pdf = Pdf::loadView('pdf.hfis.sk_jadwal', compact('items'))
                    ->setPaper('a4', 'portrait');

        return $pdf->stream('sk_jadwal_hfis_bulk.pdf');
    }
}
