<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DiklatController extends Controller
{
    public function getDiklatByNik($nik)
    {
        // Cari pegawai berdasarkan NIK untuk mendapatkan ID
        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan',
                'data' => []
            ], 404);
        }

        // Cari diklat berdasarkan id_peg
        $diklat = \App\Models\RsiaDiklat::with('kegiatan')
            ->where('id_peg', $pegawai->id)
            ->get();

        if ($diklat->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data diklat tidak ditemukan',
                'data' => []
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data diklat ditemukan',
            'data' => $diklat
        ], 200);
    }
    public function downloadBerkas($file)
    {
        // Guess possible paths
        $paths = [
            'rsiap/file/diklat/' . $file, // User suggestion
            'webapps/berkas/' . $file, // Common Khanza path
            'webapps/penggajian/' . $file, // Common Khanza path
        ];

        foreach ($paths as $path) {
            if (\Illuminate\Support\Facades\Storage::disk('sftp')->exists($path)) {
                $fileContent = \Illuminate\Support\Facades\Storage::disk('sftp')->get($path);
                $mimeType = \Illuminate\Support\Facades\Storage::disk('sftp')->mimeType($path);

                return response($fileContent, 200)
                    ->header('Content-Type', $mimeType)
                    ->header('Content-Disposition', 'inline; filename="' . $file . '"');
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Berkas tidak ditemukan di server.',
        ], 404);
    }
}
