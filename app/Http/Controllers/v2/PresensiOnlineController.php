<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\TemporaryPresensi;
use App\Models\RekapPresensi;
use App\Models\JadwalPegawai;
use App\Models\Pegawai;
use App\Models\PegawaiFaceMaster;
use App\Models\JamMasuk;
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
     * AI Face Recognition: Compare two faces
     */
    private function verifyFace($masterPath, $submittedPath)
    {
        $pythonPath = '/usr/bin/python3'; // As found in research
        $scriptPath = base_path('face_verify.py');
        
        // Ensure absolute paths
        $img1 = storage_path('app/public/' . $masterPath);
        $img2 = storage_path('app/public/' . $submittedPath);

        if (!file_exists($img1)) return ['success' => false, 'error' => 'Master photo not found'];
        if (!file_exists($img2)) return ['success' => false, 'error' => 'Submitted photo not found'];

        $command = "{$pythonPath} {$scriptPath} ".escapeshellarg($img1)." ".escapeshellarg($img2);
        $output = shell_exec($command);
        
        $result = json_decode($output, true);
        
        if (!$result || !isset($result['success']) || !$result['success']) {
            \Log::error("Face verification error: " . ($result['error'] ?? 'Unknown error'));
            return ['success' => false, 'error' => $result['error'] ?? 'Verification failed'];
        }

        return $result;
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

        // 6. Store Photo (temporary)
        $photoPath = $request->file('photo')->store('presensi/temp/' . date('Y-m-d'), 'public');

        // 6.1 AI Face Verification (only if not first time)
        if (!$isFirstTime && isset($faceCheck['master'])) {
            $verification = $this->verifyFace($faceCheck['master']->photo_path, $photoPath);
            
            if (!$verification['success']) {
                Storage::disk('public')->delete($photoPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses verifikasi wajah: ' . ($verification['error'] ?? 'Unknown Error'),
                ], 400);
            }

            if (!$verification['verified']) {
                Storage::disk('public')->delete($photoPath);
                
                $score = isset($verification['distance']) ? round($verification['distance'], 3) : 'N/A';
                return response()->json([
                    'success' => false,
                    'message' => 'Wajah tidak cocok (Score: ' . $score . '). Harap absen dengan wajah sendiri.',
                    'distance' => $verification['distance'] ?? null,
                    'threshold' => $verification['threshold'] ?? null
                ], 400);
            }
        }

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

        // 3. Ensure face is registered (auto-register if missing during checkout)
        $faceCheck = $this->ensureFaceRegistered($pegawai, $request->file('photo'));
        $isFirstTime = $faceCheck['registered'] ?? false;

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

        // 4.0 AI Face Verification (only if not first time)
        // Store current photo temporarily for verification
        $photoPathOut = $request->file('photo')->store('presensi/temp/' . date('Y-m-d'), 'public');

        if (!$isFirstTime && isset($faceCheck['master'])) {
            $verification = $this->verifyFace($faceCheck['master']->photo_path, $photoPathOut);
            
            if (!$verification['success']) {
                Storage::disk('public')->delete($photoPathOut);
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memproses verifikasi wajah: ' . ($verification['error'] ?? 'Unknown Error'),
                ], 400);
            }

            if (!$verification['verified']) {
                Storage::disk('public')->delete($photoPathOut);
                
                $score = isset($verification['distance']) ? round($verification['distance'], 3) : 'N/A';
                return response()->json([
                    'success' => false,
                    'message' => 'Wajah tidak cocok (Score: ' . $score . '). Harap absen dengan wajah sendiri.',
                    'distance' => $verification['distance'] ?? null,
                    'threshold' => $verification['threshold'] ?? null
                ], 400);
            }
        }

        // 4.1 Enforce "1 hour before shift end" rule
        $shiftInfo = JamMasuk::where('shift', $tempPresensi->shift)->first();
        if ($shiftInfo) {
            $jamDatang = $tempPresensi->jam_datang;
            $jamPulangShift = Carbon::createFromFormat('H:i:s', $shiftInfo->jam_pulang);
            $jamMasukShift = Carbon::createFromFormat('H:i:s', $shiftInfo->jam_masuk);
            
            // Determine actual scheduled return datetime
            $scheduledReturn = $jamDatang->clone()->setTimeFrom($jamPulangShift);
            
            // Overnight shift handling: if jam_pulang < jam_masuk, pulang is next day
            if ($jamPulangShift->lt($jamMasukShift)) {
                $scheduledReturn->addDay();
            }
            
            $earliestCheckout = $scheduledReturn->clone()->subHour();
            $now = Carbon::now();
            
            if ($now->lt($earliestCheckout)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum masuk waktu pulang. Minimal jam ' . $earliestCheckout->format('H:i'),
                ], 400);
            }
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

        // 6. Calculate duration
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
            // Try cleaning NIK (remove dots) if not found
            $cleanNik = str_replace('.', '', $nik);
            $pegawai = Pegawai::where('nik', $cleanNik)->first();
            
            if (!$pegawai) {
                return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
            }
        }

        // Check if face is registered
        $faceMaster = PegawaiFaceMaster::where('pegawai_id', $pegawai->id)
            ->where('is_active', 1)
            ->first();

        // Use Database Date to ensure consistency with stored data
        // Check temporary_presensi (check-in only)
        $tempPresensi = TemporaryPresensi::where('id', $pegawai->id)
            ->whereRaw('DATE(jam_datang) = CURDATE()')
            ->first();
            
        // Check rekap_presensi (complete record)
        $rekapPresensi = RekapPresensi::where('id', $pegawai->id)
            ->whereRaw('DATE(jam_datang) = CURDATE()')
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
