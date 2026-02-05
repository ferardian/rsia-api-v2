<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\ResepObat;
use App\Models\DetailPemberianObat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResepController extends Controller
{
    public function show(string $no_resep)
    {
        $resep = ResepObat::with(['dokter', 'regPeriksa.pasien'])
            ->where('no_resep', $no_resep)
            ->first();

        if (!$resep) {
            return response()->json([
                'success' => false,
                'message' => 'Resep tidak ditemukan'
            ], 404);
        }

        $no_rawat = $resep->no_rawat;
        $tgl_perawatan = $resep->tgl_perawatan;
        $jam = $resep->jam;

        // Logic similar to ErmController for non-racikan
        $details = DetailPemberianObat::with(['aturanPakai', 'obat.jenis'])
            ->where('tgl_perawatan', $tgl_perawatan)
            ->where('jam', $jam)
            ->where('no_rawat', $no_rawat)
            ->get()
            ->filter(function($detail) {
                $obat = $detail->obat;
                if (!$obat || !$obat->jenis) {
                    return true;
                }
                return $obat->jenis->nama !== 'ALKES';
            });

        $obatList = [];
        foreach ($details as $detail) {
            $aturanPakai = null;
            $resepDokterData = DB::table('resep_dokter')
                ->where('no_resep', $no_resep)
                ->where('kode_brng', $detail->kode_brng)
                ->first();

            if ($resepDokterData && $resepDokterData->aturan_pakai) {
                $aturanPakai = $resepDokterData->aturan_pakai;
            } elseif ($detail->aturanPakai) {
                $aturanPakai = $detail->aturanPakai->aturan ?? $detail->aturanPakai;
            }

            $obatList[] = [
                'kode_brng' => $detail->kode_brng,
                'nama_brng' => $detail->obat->nama_brng ?? $detail->kode_brng,
                'jml' => $detail->jml,
                'aturan_pakai' => $aturanPakai,
                'kategori' => 'Obat', // Simplified
                'status_resep' => 'Non Racik'
            ];
        }

        // Racikan
        $resepRacikan = DB::table('resep_dokter_racikan')
            ->where('no_resep', $no_resep)
            ->get();

        foreach ($resepRacikan as $racikan) {
            $obatList[] = [
                'kode_brng' => 'RACIKAN-' . $racikan->no_racik,
                'nama_brng' => $racikan->nama_racik,
                'jml' => $racikan->jml_dr,
                'aturan_pakai' => $racikan->aturan_pakai,
                'keterangan' => $racikan->keterangan,
                'status_resep' => 'Racik'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'no_resep' => $resep->no_resep,
                'no_rawat' => $resep->no_rawat,
                'tgl_peresepan' => $resep->tgl_peresepan,
                'dokter' => $resep->dokter->nm_dokter ?? 'Dokter',
                'obat' => $obatList
            ]
        ]);
    }
}
