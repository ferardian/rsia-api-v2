<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaTemporaryLembur;
use App\Models\RsiaRekapLembur;
use App\Models\Pegawai;
use App\Models\PegawaiFaceMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LemburController extends Controller
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
        $pythonPath = env('PYTHON_AI_PATH', '/home/sysadmin/ai_env_presensi/bin/python3');
        $scriptPath = base_path('face_verify.py');
        
        $img1 = storage_path('app/public/' . $masterPath);
        $img2 = storage_path('app/public/' . $submittedPath);

        if (!file_exists($img1) || !file_exists($img2)) {
            return ['success' => false, 'error' => 'Photo missing'];
        }

        $command = "{$pythonPath} {$scriptPath} ".escapeshellarg($img1)." ".escapeshellarg($img2);
        $output = shell_exec($command . " 2>&1");
        $result = json_decode($output, true);
        
        return $result ?? ['success' => false, 'error' => 'Verification failed'];
    }

    private function isWithinRadius($lat, $lng)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat - $this->centerLat);
        $dLon = deg2rad($lng - $this->centerLng);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($this->centerLat)) * cos(deg2rad($lat)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return ($earthRadius * $c) <= $this->maxRadius;
    }

    public function status(Request $request)
    {
        $pegawai = Pegawai::where('nik', $request->nik)->first();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $active = RsiaTemporaryLembur::where('id', $pegawai->id)->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'status' => $active ? 'started' : 'none',
                'jam_datang' => $active ? $active->jam_datang->format('H:i') : null,
                'config' => [
                    'center_lat' => $this->centerLat,
                    'center_lng' => $this->centerLng,
                    'radius' => $this->maxRadius,
                ]
            ]
        ]);
    }

    public function checkIn(Request $request)
    {
        $this->validate($request, [
            'nik' => 'required|exists:pegawai,nik',
            'photo' => 'required|image|max:5120',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $pegawai = Pegawai::where('nik', $request->nik)->first();
        
        if (!$this->isWithinRadius($request->latitude, $request->longitude)) {
            return response()->json(['success' => false, 'message' => 'Di luar radius kantor'], 400);
        }

        $existing = RsiaTemporaryLembur::where('id', $pegawai->id)->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memulai lembur'], 400);
        }

        $faceCheck = $this->ensureFaceRegistered($pegawai, $request->file('photo'));
        $photoPath = $request->file('photo')->store('lembur/temp/' . date('Y-m-d'), 'public');

        if (!($faceCheck['registered'] ?? false) && isset($faceCheck['master'])) {
            $verification = $this->verifyFace($faceCheck['master']->photo_path, $photoPath);
            if (!$verification['success'] || !$verification['verified']) {
                Storage::disk('public')->delete($photoPath);
                return response()->json(['success' => false, 'message' => 'Wajah tidak cocok'], 400);
            }
        }

        RsiaTemporaryLembur::create([
            'id' => $pegawai->id,
            'jam_datang' => Carbon::now(),
            'photo' => $photoPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mulai lembur!',
            'first_time' => $faceCheck['registered'] ?? false
        ]);
    }

    public function checkOut(Request $request)
    {
        $this->validate($request, [
            'nik' => 'required|exists:pegawai,nik',
            'photo' => 'required|image|max:5120',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'kegiatan' => 'required|string|max:200',
        ]);

        $pegawai = Pegawai::where('nik', $request->nik)->first();
        
        if (!$this->isWithinRadius($request->latitude, $request->longitude)) {
            return response()->json(['success' => false, 'message' => 'Di luar radius kantor'], 400);
        }

        $temp = RsiaTemporaryLembur::where('id', $pegawai->id)->first();
        if (!$temp) {
            return response()->json(['success' => false, 'message' => 'Anda belum memulai lembur'], 400);
        }

        $faceCheck = $this->ensureFaceRegistered($pegawai, $request->file('photo'));
        $photoPathOut = $request->file('photo')->store('lembur/temp/' . date('Y-m-d'), 'public');

        if (!($faceCheck['registered'] ?? false) && isset($faceCheck['master'])) {
            $verification = $this->verifyFace($faceCheck['master']->photo_path, $photoPathOut);
            if (!$verification['success'] || !$verification['verified']) {
                Storage::disk('public')->delete($photoPathOut);
                return response()->json(['success' => false, 'message' => 'Wajah tidak cocok'], 400);
            }
        }

        $jamPulang = Carbon::now();
        $duration = $temp->jam_datang->diff($jamPulang);
        $durationFormatted = $duration->format('%H:%I:%S');

        RsiaRekapLembur::create([
            'id' => $pegawai->id,
            'jam_datang' => $temp->jam_datang,
            'jam_pulang' => $jamPulang,
            'durasi' => $durationFormatted,
            'durasi_pengajuan' => $durationFormatted,
            'durasi_acc' => '00:00:00',
            'photo' => $temp->photo,
            'kegiatan' => $request->kegiatan,
            'status' => 'PENGAJUAN',
        ]);

        $temp->delete();
        Storage::disk('public')->delete($photoPathOut);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil menyelesaikan lembur!',
            'data' => [
                'durasi' => $durationFormatted
            ]
        ]);
    }

    public function history(Request $request)
    {
        $this->validate($request, [
            'nik' => 'required|exists:pegawai,nik',
        ]);

        $pegawai = Pegawai::where('nik', $request->nik)->first();

        $history = RsiaRekapLembur::where('id', $pegawai->id)
            ->orderBy('jam_datang', 'desc')
            ->paginate($request->limit ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat lembur berhasil diambil',
            'data' => $history
        ]);
    }
}
