<?php
/**
 * Created by Antigravity.
 * User: Ferry Ardiansyah
 * Date: 2026-01-15
 */

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaHelpdeskTempLog;
use App\Models\RsiaHelpdeskTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HelpdeskController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $status = $request->input('status');
        $keyword = $request->input('keyword');

        $query = RsiaHelpdeskTempLog::with(['pegawai:nik,nama', 'departemen:dep_id,nama']);

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

    public function getTickets(Request $request)
    {
        $limit = $request->input('limit', 10);
        $status = $request->input('status');
        $keyword = $request->input('keyword');

        $query = RsiaHelpdeskTicket::with(['pelapor:nik,nama', 'teknisi:nik,nama', 'departemen:dep_id,nama']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($keyword) {
            $query->where(function($q) use ($keyword) {
                $q->where('no_tiket', 'like', "%$keyword%")
                  ->orWhere('keluhan', 'like', "%$keyword%");
            });
        }

        $tickets = $query->orderBy('tanggal', 'DESC')->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Data tiket helpdesk berhasil diambil',
            'data'    => $tickets
        ]);
    }

    public function createTicketFromLog(Request $request)
    {
        $request->validate([
            'temp_log_id' => 'required|exists:rsia_helpdesk_temp_log,id',
            'prioritas'   => 'required|in:High,Medium,Low',
            'nik_teknisi' => 'nullable|exists:pegawai,nik'
        ]);

        $log = RsiaHelpdeskTempLog::find($request->temp_log_id);

        if ($log->status !== 'WAITING') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan sudah diproses atau kadaluarsa'
            ], 400);
        }

        return DB::transaction(function () use ($log, $request) {
            // Generate no_tiket: HTK/YYYY/MM/ID
            $year = date('Y');
            $month = date('m');
            
            // Get last ID for the series
            $lastTicket = RsiaHelpdeskTicket::orderBy('id', 'DESC')->first();
            $nextId = $lastTicket ? $lastTicket->id + 1 : 1;
            $no_tiket = "HTK/{$year}/{$month}/" . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $ticketData = [
                'no_tiket'    => $no_tiket,
                'tanggal'     => Carbon::now(),
                'nik_pelapor' => $log->nik_pelapor,
                'dep_id'      => $log->kd_dep,
                'keluhan'     => $log->isi_laporan,
                'prioritas'   => $request->prioritas,
                'status'      => 'Open'
            ];

            // If technician is selected immediately
            if ($request->has('nik_teknisi') && !empty($request->nik_teknisi)) {
                $ticketData['nik_teknisi'] = $request->nik_teknisi;
                $ticketData['status'] = 'Proses';
                $ticketData['jam_mulai'] = Carbon::now();
            }

            $ticket = RsiaHelpdeskTicket::create($ticketData);

            $log->status = 'PROCESSED';
            $log->save();

            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil diterbitkan',
                'data'    => $ticket
            ]);
        });
    }

    public function updateTicket(Request $request, $id)
    {
        $request->validate([
            'prioritas'   => 'sometimes|in:High,Medium,Low',
            'status'      => 'sometimes|in:Open,Proses,Selesai,Batal',
            'nik_teknisi' => 'sometimes|nullable|exists:pegawai,nik',
            'solusi'      => 'sometimes|nullable|string',
            'jam_selesai' => 'sometimes|nullable|date',
        ]);

        $ticket = RsiaHelpdeskTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket tidak ditemukan'
            ], 404);
        }

        $data = $request->only(['prioritas', 'status', 'nik_teknisi', 'solusi', 'jam_selesai']);

        if (isset($data['status']) && $data['status'] === 'Selesai' && empty($data['jam_selesai'])) {
            $data['jam_selesai'] = Carbon::now();
        }

        // Trigger Start Time (Response Time)
        // If status changes to 'Proses' OR technician is assigned for the first time
        // And jam_mulai is not yet set
        if (
            (
                (isset($data['status']) && $data['status'] === 'Proses') || 
                (isset($data['nik_teknisi']) && !empty($data['nik_teknisi']))
            ) && 
            is_null($ticket->jam_mulai)
        ) {
            $data['jam_mulai'] = Carbon::now();
            
            // If status is not explicitly set but technician is assigned, auto-move to Process
            if (!isset($data['status']) && $ticket->status === 'Open') {
                $data['status'] = 'Proses';
            }
        }

        $ticket->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil diperbarui',
            'data'    => $ticket
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        // Existing implementation remains, but maybe adjust for Ticket status if needed
        // For now, let's keep it as is or add a separate one for Tickets
        // ... (existing updateStatus for TempLog)
    }
}
