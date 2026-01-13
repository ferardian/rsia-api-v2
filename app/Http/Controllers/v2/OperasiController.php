<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\BookingOperasi;
use App\Models\Dokter;
use App\Models\PaketOperasi;
use App\Models\RegPeriksa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsToTracker;
use App\Models\RsiaOperasiSafe;
use App\Models\Pegawai;

class OperasiController extends Controller
{
    use LogsToTracker;
    public function index(Request $request)
    {
        $query = BookingOperasi::with(['regPeriksa.pasien', 'paketOperasi', 'dokter', 'diagnosaOperasi', 'laporanOperasi']);

        if ($request->has('tgl_awal') && $request->has('tgl_akhir')) {
            $query->whereBetween('tanggal', [$request->tgl_awal, $request->tgl_akhir]);
        }

        if ($request->has('no_rawat')) {
            $query->where('no_rawat', $request->no_rawat);
        }
        
        if ($request->has('kd_dokter')) {
            $query->where('kd_dokter', $request->kd_dokter);
        }

        $data = $query->orderBy('tanggal', 'desc')->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Data Booking Operasi',
            'data' => $data
        ]);
    }

    public function paket(Request $request)
    {
        $query = PaketOperasi::query();

        // Only show active packages (status <> 0)
        $query->where('status', '<>', '0');

        if ($request->has('keyword')) {
            $query->where('nm_perawatan', 'like', '%' . $request->keyword . '%');
        }

        $data = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'message' => 'Data Paket Operasi',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_rawat' => 'required',
            'kode_paket' => 'required',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'status' => 'required|in:Menunggu,Proses Operasi,Selesai',
            'kd_dokter' => 'required',
            'diagnosa' => 'nullable|string|max:100'
        ]);

        $booking = BookingOperasi::create($validated);

        // Log to trackersql
        $sqlLog = "INSERT INTO booking_operasi VALUES ('{$validated['no_rawat']}', '{$validated['kode_paket']}', '{$validated['tanggal']}', '{$validated['jam_mulai']}', '{$validated['jam_selesai']}', '{$validated['status']}', '{$validated['kd_dokter']}')";
        $this->logTracker($sqlLog, $request);

        // Save diagnosa if provided
        if (!empty($validated['diagnosa'])) {
            \App\Models\DiagnosaOperasi::create([
                'no_rawat' => $validated['no_rawat'],
                'diagnosa' => $validated['diagnosa'],
                'kode_paket' => $validated['kode_paket']
            ]);
            
            // Log diagnosa to trackersql
            $diagnosaSql = "INSERT INTO rsia_diagnosa_operasi VALUES ('{$validated['no_rawat']}', '{$validated['diagnosa']}', '{$validated['kode_paket']}')";
            $this->logTracker($diagnosaSql, $request);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking Operasi berhasil disimpan',
            'data' => $booking
        ]);
    }

    public function update(Request $request, $no_rawat, $tanggal)
    {
        $validated = $request->validate([
            'status' => 'required|in:Menunggu,Proses Operasi,Selesai'
        ]);

        $booking = BookingOperasi::where('no_rawat', $no_rawat)
            ->where('tanggal', $tanggal)
            ->firstOrFail();

        $booking->update($validated);

        // Log to trackersql
        $sqlLog = "UPDATE booking_operasi SET status='{$validated['status']}' WHERE no_rawat='{$no_rawat}' AND tanggal='{$tanggal}'";
        $this->logTracker($sqlLog, $request);

        return response()->json([
            'success' => true,
            'message' => 'Status operasi berhasil diupdate',
            'data' => $booking->fresh()
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required',
            'tanggal' => 'required|date'
        ]);

        $booking = BookingOperasi::where('no_rawat', $request->no_rawat)
            ->where('tanggal', $request->tanggal)
            ->firstOrFail();

        // Prevent deletion if status is Selesai
        if ($booking->status === 'Selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus jadwal operasi yang sudah selesai'
            ], 400);
        }

        // Delete related diagnosa first
        \App\Models\DiagnosaOperasi::where('no_rawat', $request->no_rawat)->delete();
        
        // Log diagnosa deletion to trackersql
        $diagnosaSql = "DELETE FROM rsia_diagnosa_operasi WHERE no_rawat='{$request->no_rawat}'";
        $this->logTracker($diagnosaSql, $request);

        // Delete booking
        $booking->delete();
        
        // Log booking deletion to trackersql
        $sqlLog = "DELETE FROM booking_operasi WHERE no_rawat='{$request->no_rawat}' AND tanggal='{$request->tanggal}'";
        $this->logTracker($sqlLog, $request);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal operasi berhasil dihapus'
        ]);
    }

    public function indexLaporan(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $q = $request->input('q');

            $query = RsiaOperasiSafe::with(['detailPaket', 'regPeriksa.pasien']);

            if ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('no_rawat', 'like', "%{$q}%")
                        ->orWhereHas('regPeriksa.pasien', function ($sub) use ($q) {
                            $sub->where('nm_pasien', 'like', "%{$q}%")
                                ->orWhere('no_rkm_medis', 'like', "%{$q}%");
                        });
                });
            }

            if ($request->has('bulan')) {
                $query->whereMonth('tgl_operasi', $request->bulan);
            }
            if ($request->has('tahun')) {
                $query->whereYear('tgl_operasi', $request->tahun);
            }
            
            if ($request->start && $request->end) {
                $query->whereBetween('tgl_operasi', [$request->start, $request->end]);
            }

            $data = $query->orderBy('tgl_operasi', 'desc')->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error indexLaporan: " . $th->getMessage());
            \Log::error($th->getTraceAsString());
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function getLaporan(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required',
            'kode_paket' => 'required',
            'tgl_operasi' => 'required' // Date portion
        ]);

        // Note: tgl_operasi in DB is datetime. 
        // We might need to match generic date or exact datetime.
        // Usually booking has date.
        
        $laporan = RsiaOperasiSafe::where('no_rawat', $request->no_rawat)
            ->where('kode_paket', $request->kode_paket)
             // Using whereDate if exact match isn't guaranteed, 
             // but schema says PK is (no_rawat, tgl_operasi, kode_paket).
             // Let's assume frontend sends exact datetime or we match by date.
             // Ideally we match exact datetime if it comes from booking.
            ->whereDate('tgl_operasi', $request->tgl_operasi) 
            ->first();

        return response()->json([
            'success' => true,
            'data' => $laporan
        ]);
    }

    public function storeLaporan(Request $request) 
    {
        // Validation matches table schema
        $validated = $request->validate([
            'no_rawat' => 'required',
            'kode_paket' => 'required',
            'tgl_operasi' => 'required|date', // This acts as ID part
            'tgl_selesai' => 'required|date',
            'operator1' => 'required',
            'jenis_anestesi' => 'required',
            'kategori' => 'nullable',
            'diagnosa_preop' => 'nullable',
            'diagnosa_postop' => 'nullable',
            'laporan_operasi' => 'nullable',
            // ... add other fields as nullable or required based on strictness
            'asisten_operator1' => 'nullable',
            'asisten_operator2' => 'nullable',
            'dokter_anak' => 'nullable',
            'dokter_anestesi' => 'nullable',
            'asisten_anestesi' => 'nullable',
            'onloop' => 'nullable',
            'jaringan_insisi' => 'nullable',
            'pemeriksaan_pa' => 'nullable',
            'dr_anestesi_hadir' => 'nullable',
            'dr_anak_hadir' => 'nullable',
            'darah_masuk' => 'nullable',
            'darah_hilang' => 'nullable',
            'komplikasi' => 'nullable'
        ]);

        // Use defaults for nullable fields if missing to match table definition if strict
        $data = array_merge([
            'asisten_operator1' => '-',
            'asisten_operator2' => '-',
            'dokter_anak' => '-',
            'dokter_anestesi' => '-',
            'asisten_anestesi' => '-',
            'onloop' => '-',
        ], $validated);


        try {
            // Use DB facade to bypass Eloquent composite key issues
            // Keys: no_rawat, tgl_operasi, kode_paket
            
            $exists = \DB::table('rsia_operasi_safe')
                ->where('no_rawat', $data['no_rawat'])
                ->where('kode_paket', $data['kode_paket'])
                ->where('tgl_operasi', $data['tgl_operasi'])
                ->exists();
            
            if ($exists) {
                // Update existing record
                \DB::table('rsia_operasi_safe')
                    ->where('no_rawat', $data['no_rawat'])
                    ->where('kode_paket', $data['kode_paket'])
                    ->where('tgl_operasi', $data['tgl_operasi'])
                    ->update($data);
                    
                $sqlLog = "UPDATE rsia_operasi_safe SET ... WHERE no_rawat='{$data['no_rawat']}' AND kode_paket='{$data['kode_paket']}'";
            } else {
                // Insert new record
                \DB::table('rsia_operasi_safe')->insert($data);
                
                $sqlLog = "INSERT INTO rsia_operasi_safe VALUES (no_rawat='{$data['no_rawat']}', kode_paket='{$data['kode_paket']}', ...)";
            }

            // Log Tracker
            $this->logTracker($sqlLog, $request);

            // Fetch the record to return
            $laporan = RsiaOperasiSafe::where('no_rawat', $data['no_rawat'])
                ->where('kode_paket', $data['kode_paket'])
                ->where('tgl_operasi', $data['tgl_operasi'])
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Laporan operasi berhasil disimpan',
                'data' => $laporan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDokter()
    {
        $data = Dokter::where('status', '1')->select('kd_dokter', 'nm_dokter')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getPegawai()
    {
        // For assistants, usually paramedics/nurses
        // Filter by specific department if needed, or just all active employees
        // Assuming 'sakit' or 'cuti' etc status check exists? Or just 'pegawai' table?
        // Let's return minimal data
        $data = Pegawai::select('nik', 'nama')->where('stts_aktif', 'AKTIF')->get();
         return response()->json(['success' => true, 'data' => $data]);
    }
}
