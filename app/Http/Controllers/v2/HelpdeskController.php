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
use App\Helpers\Notification\FirebaseCloudMessaging;
use Kreait\Firebase\Messaging\CloudMessage;

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

    public function history(Request $request) {
        $user = $request->user();
        if (!$user->relationLoaded('detail')) {
            $user->load('detail');
        }

        $nik = $user->id_user; // user-aes maps id_user to nik
        $dep_id = $user->detail->departemen ?? '-';

        $limit = $request->input('limit', 10);
        $status = $request->input('status');

        $query = RsiaHelpdeskTempLog::with(['pegawai:nik,nama', 'departemen:dep_id,nama']);

        // IF NOT IT (dep_id != 'IT'), then filter by department
        // Assuming 'IT' is the department code for Information Technology
        if ($dep_id !== 'IT') {
            $query->where('kd_dep', $dep_id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tickets = $query->orderBy('created_at', 'DESC')->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat tiket berhasil diambil',
            'data'    => $tickets
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'isi_laporan' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) { // Fallback if user not found strictly
             return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            // Ensure detail (Pegawai) and petugas relationships are loaded
            if (!$user->relationLoaded('detail')) {
                $user->load('detail.petugas');
            } else if (!$user->detail->relationLoaded('petugas')) {
                $user->detail->load('petugas');
            }

            // Get phone number from petugas table
            $nomor_wa = '-';
            if ($user->detail && $user->detail->petugas && !empty($user->detail->petugas->no_telp)) {
                $phone = trim($user->detail->petugas->no_telp);
                
                // Format to 62...
                if (str_starts_with($phone, '0')) {
                    $nomor_wa = '62' . substr($phone, 1);
                } elseif (str_starts_with($phone, '62')) {
                    $nomor_wa = $phone;
                } elseif (str_starts_with($phone, '+62')) {
                    $nomor_wa = substr($phone, 1);
                } else {
                    $nomor_wa = '62' . $phone;
                }
            }

            $log = new RsiaHelpdeskTempLog();
            $log->nomor_wa = $nomor_wa;
            $log->nik_pelapor = $user->id_user;
            $log->kd_dep = $user->detail ? $user->detail->departemen : '-';
            $log->isi_laporan = $request->isi_laporan;
            $log->raw_message = 'Reported via Mobile App';
            $log->status = 'WAITING';
            $log->save();
            \Log::info("Helpdesk report saved successfully. ID: " . $log->id);

            // Trigger notification to IT
            try {
                \Log::info("Attempting to send FCM notification to 'it' topic.");
                $msg = (new FirebaseCloudMessaging)->buildNotification(
                    'it',
                    'Laporan Helpdesk Baru',
                    "Keluhan: " . (strlen($log->isi_laporan) > 50 ? substr($log->isi_laporan, 0, 47) . '...' : $log->isi_laporan),
                    [
                        'route' => 'helpdesk_main',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ]
                );
                FirebaseCloudMessaging::send($msg);
                \Log::info("FCM notification dispatched to 'it' topic.");
            } catch (\Exception $e) {
                \Log::error("FCM Error for Helpdesk: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dikirim'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim laporan: ' . $e->getMessage()
            ], 500);
        }
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
