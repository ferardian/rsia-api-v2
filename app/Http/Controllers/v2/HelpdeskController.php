<?php
/**
 * Created by Antigravity.
 * User: Ferry Ardiansyah
 * Date: 2026-01-15
 */

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaHelpdeskTempLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelpdeskController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $status = $request->input('status');
        $keyword = $request->input('keyword');

        $query = RsiaHelpdeskTempLog::with('pegawai:nik,nama');

        if ($status) {
            $query->where('status', $status);
        }

        if ($keyword) {
            $query->where(function($q) use ($keyword) {
                $q->where('nomor_wa', 'like', "%$keyword%")
                  ->orWhere('isi_laporan', 'like', "%$keyword%")
                  ->orWhere('nik_pelapor', 'like', "%$keyword%");
            });
        }

        $tickets = $query->orderBy('created_at', 'DESC')->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Data tiket helpdesk berhasil diambil',
            'data'    => $tickets
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:WAITING,PROCESSED,EXPIRED'
        ]);

        $ticket = RsiaHelpdeskTempLog::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan'
            ], 404);
        }

        $ticket->status = $request->input('status');
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Status tiket berhasil diperbarui',
            'data'    => $ticket
        ]);
    }
}
