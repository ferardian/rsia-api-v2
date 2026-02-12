<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\TemporaryPresensi;
use App\Models\RekapPresensi;
use App\Models\JadwalPegawai;
use App\Models\Pegawai;
use App\Models\PegawaiFaceMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PresensiOnlineController extends Controller
{
    private $centerLat = -6.94159449034943;
    private $centerLng = 109.65221083435888;
    private $maxRadius = 200; // meters

    /**
     * Check if employee has registered face, if not auto-register
     */
    private function ensureFaceRegistered($pegawai, $photoFile)
    {
        $faceMaster = PegawaiFaceMaster::where('pegawai_id', $pegawai->id)
            ->where('is_active', 1)
            ->first();

        if (!$faceMaster) {
            // Auto-register face on first use
            $photoPath = $photoFile->store('face_master', 'public');
            
            PegawaiFaceMaster::create([
                'pegawai_id' => $pegawai->id,
                'nik' => $pegawai->nik,
                'photo_path' => $photoPath,
                'registered_at' => Carbon::now(),
                'is_active' => 1,
            ]);

            return ['registered' => true, 'message' => 'Wajah berhasil didaftarkan'];
        }

        return ['registered' => false, 'master' => $faceMaster];
    }

    /**
     * Check-In: Save to temporary_presensi
     */
    public function checkIn(Request $request)
    {
        $this->validate($request, [
            'nik' => 'required|exists:pegawai,nik',
            'photo' => 'required|image|max:5120', // Max 5MB
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // 1. Get Pegawai
        $pegawai = Pegawai::where('nik', $request->nik)->first();
        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan',
            ], 404);
        }

        // 2. Validate Location
        if (!$this->isWithinRadius($request->latitude, $request->longitude)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda berada di luar radius kantor (' . $this->maxRadius . 'm)',
            ], 400);
        }

        // 3. Ensure face is registered (auto-register if first time)
        $faceCheck = $this->ensureFaceRegistered($pegawai, $request->file('photo'));
        $isFirstTime = $faceCheck['registered'] ?? false;

        // 4. Read Jadwal Pegawai for today's shift
        $today = Carbon::today();
        $currentMonth = $today->month;
        $currentYear = $today->year;
        $currentDay = $today->day;
        
        $jadwal = JadwalPegawai::where('id', $pegawai->id)
            ->where('tahun', $currentYear)
            ->where('bulan', $currentMonth)
            ->first();

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan untuk bulan ini',
            ], 400);
        }

        // Get shift for today (H1, H2, ... H31)
        $shiftColumn = 'H' . $currentDay;
        $shift = $jadwal->$shiftColumn;

        if (!$shift || $shift === '-') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki jadwal hari ini',
            ], 400);
        }

        // 5. Check if already checked in today
        $existing = TemporaryPresensi::where('id', $pegawai->id)
            ->whereDate('jam_datang', $today)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan check-in hari ini pada ' . $existing->jam_datang->format('H:i'),
            ], 400);
        }

        // 6. Store Photo (temporary, will be deleted after verification)
        // In production, you might want to compare with master photo here
        $photoPath = $request->file('photo')->store('presensi/temp/' . date('Y-m-d'), 'public');

        // 7. Determine status (Tepat Waktu / Terlambat)
        // TODO: Compare with shift start time from jam_masuk table
        $status = 'Tepat Waktu'; // Simplified for now
        $keterlambatan = '00:00:00';

        // 8. Create Record in temporary_presensi (clean, no GPS/face data)
        $presensi = TemporaryPresensi::create([
            'id' => $pegawai->id,
            'shift' => $shift,
            'jam_datang' => Carbon::now(),
            'status' => $status,
            'keterlambatan' => $keterlambatan,
            'keterangan' => '',
            'photo' => $photoPath,
        ]);

        // 9. Delete temporary photo (optional, or keep for audit)
        // Storage::disk('public')->delete($photoPath);

        $response = [
            'success' => true,
            'message' => 'Check-in berhasil!',
            'data' => [
                'shift' => $shift,
                'jam_datang' => $presensi->jam_datang->format('Y-m-d H:i:s'),
                'status' => $status,
            ]
        ];

        if ($isFirstTime) {
            $response['message'] = 'Wajah berhasil didaftarkan dan check-in berhasil!';
            $response['first_time'] = true;
        }

        return response()->json($response);
    }

    /**
     * Check-Out: Move from temporary_presensi to rekap_presensi
     */
    public function checkOut(Request $request)
    {
        $this->validate($request, [
            'nik' => 'required|exists:pegawai,nik',
            'photo' => 'required|image|max:5120',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // 1. Get Pegawai
        $pegawai = Pegawai::where('nik', $request->nik)->first();
        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan',
            ], 404);
        }

        // 2. Validate Location
        if (!$this->isWithinRadius($request->latitude, $request->longitude)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda berada di luar radius kantor',
            ], 400);
        }

        // 3. Verify face is registered
        $faceMaster = PegawaiFaceMaster::where('pegawai_id', $pegawai->id)
            ->where('is_active', 1)
            ->first();

        if (!$faceMaster) {
            return response()->json([
                'success' => false,
                'message' => 'Wajah belum terdaftar. Silakan check-in terlebih dahulu.',
            ], 400);
        }

        // 4. Check if checked in today
        $today = Carbon::today();
        $tempPresensi = TemporaryPresensi::where('id', $pegawai->id)
            ->whereDate('jam_datang', $today)
            ->first();

        if (!$tempPresensi) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum melakukan check-in hari ini',
            ], 400);
        }

        // 5. Check if already checked out
        $existingRekap = RekapPresensi::where('id', $pegawai->id)
            ->whereDate('jam_datang', $today)
            ->whereNotNull('jam_pulang')
            ->first();

        if ($existingRekap) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan check-out hari ini',
            ], 400);
        }

        // 6. Store Checkout Photo (temporary)
        $photoPathOut = $request->file('photo')->store('presensi/temp/' . date('Y-m-d'), 'public');

        // 7. Calculate duration
        $jamPulang = Carbon::now();
        $duration = $tempPresensi->jam_datang->diff($jamPulang);
        $durationFormatted = $duration->format('%H:%I:%S');

        // 8. Create final record in rekap_presensi (clean, no GPS/face data)
        $rekap = RekapPresensi::create([
            'id' => $tempPresensi->id,
            'shift' => $tempPresensi->shift,
            'jam_datang' => $tempPresensi->jam_datang,
            'jam_pulang' => $jamPulang,
            'status' => $tempPresensi->status,
            'keterlambatan' => $tempPresensi->keterlambatan,
            'durasi' => $durationFormatted,
            'keterangan' => $tempPresensi->keterangan,
            'photo' => $tempPresensi->photo, // Keep check-in photo only
        ]);

        // 9. Delete temporary record
        $tempPresensi->delete();

        // 10. Delete temporary photos (optional)
        // Storage::disk('public')->delete($photoPathOut);

        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil!',
            'data' => [
                'jam_datang' => $rekap->jam_datang->format('Y-m-d H:i:s'),
                'jam_pulang' => $rekap->jam_pulang->format('Y-m-d H:i:s'),
                'durasi' => $durationFormatted,
            ]
        ]);
    }

    /**
     * Get Status: Check today's attendance status
     */
    public function getStatus(Request $request)
    {
        $nik = $request->nik ?? $request->user()->nik;
        if (!$nik) {
            return response()->json(['success' => false, 'message' => 'NIK required'], 400);
        }

        $pegawai = Pegawai::where('nik', $nik)->first();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        // Check if face is registered
        $faceMaster = PegawaiFaceMaster::where('pegawai_id', $pegawai->id)
            ->where('is_active', 1)
            ->first();

        $today = Carbon::today();
        
        // Check temporary_presensi (check-in only)
        $tempPresensi = TemporaryPresensi::where('id', $pegawai->id)
            ->whereDate('jam_datang', $today)
            ->first();
            
        // Check rekap_presensi (complete record)
        $rekapPresensi = RekapPresensi::where('id', $pegawai->id)
            ->whereDate('jam_datang', $today)
            ->first();

        $status = 'none'; // none, checked_in, checked_out
        $data = [
            'jam_masuk' => null,
            'jam_pulang' => null,
            'face_registered' => $faceMaster ? true : false,
        ];

        if ($rekapPresensi) {
            $status = 'checked_out';
            $data['jam_masuk'] = $rekapPresensi->jam_datang->format('H:i');
            $data['jam_pulang'] = $rekapPresensi->jam_pulang ? $rekapPresensi->jam_pulang->format('H:i') : null;
        } elseif ($tempPresensi) {
            $status = 'checked_in';
            $data['jam_masuk'] = $tempPresensi->jam_datang->format('H:i');
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($data, [
                'status' => $status,
                'config' => [
                    'center_lat' => $this->centerLat,
                    'center_lng' => $this->centerLng,
                    'radius' => $this->maxRadius,
                ]
            ])
        ]);
    }
    
    public function getConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'center_latitude' => $this->centerLat,
                'center_longitude' => $this->centerLng,
                'max_radius_meters' => $this->maxRadius,
            ]
        ]);
    }

    private function isWithinRadius($lat, $lng)
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat - $this->centerLat);
        $dLon = deg2rad($lng - $this->centerLng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($this->centerLat)) * cos(deg2rad($lat)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance <= $this->maxRadius;
    }
}
