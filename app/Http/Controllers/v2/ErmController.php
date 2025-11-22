<?php

namespace App\Http\Controllers\v2; // Pastikan namespace ini benar

use App\Http\Controllers\Controller;
use App\Models\BridgingSep; // Import model BridgingSep
use App\Models\RegPeriksa;
use App\Models\DiagnosaPasien;
use App\Models\ProsedurPasien;
use App\Models\DetailPemberianObat;
use App\Models\PeriksaLab;
use App\Models\PeriksaRadiologi;
use App\Models\PemeriksaanRanap;
use App\Models\CatatanPerawatan;
use App\Models\PemeriksaanRalan;
use App\Models\PemeriksaanRalanKlaim;
use Illuminate\Http\Request;

class ErmController extends Controller
{
    /**
     * Mengambil detail ERM untuk satu nomor SEP.
     *
     * @param  string  $no_sep
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDetails(string $no_sep) // Ganti nama method atau gunakan parameter $identifier
    {
       $bridging = BridgingSep::where('no_sep', $no_sep)->first();

        if (!$bridging || !$bridging->no_rawat) {
            return response()->json(['success' => false, 'message' => 'Nomor Rawat tidak ditemukan untuk SEP ' . $no_sep], 404);
        }

        $no_rawat = $bridging->no_rawat;

        // --- Perubahan di sini ---
        // Muat RegPeriksa beserta relasi prosedurPasien DAN icd9 di dalam prosedurPasien
        $regPeriksa = RegPeriksa::with([
                'pasien', // Muat relasi pasien jika perlu
                // 'penjab', // Muat relasi penanggung jawab jika perlu
                'poliklinik', // Muat relasi poliklinik jika perlu
                'dokter', // Muat relasi dokter jika perlu
                'dokter.pegawai', // <<<----- Muat relasi pegawai dari dokter (untuk no_ktp)
                'diagnosaPasien.penyakit', // Muat diagnosa dan relasi penyakitnya
                'prosedurPasien.penyakit', // <<<----- Muat prosedur DAN relasi icd9 nya
                'notaJalan' // <<<----- Muat nota jalan untuk period end time
            ])
            ->where('no_rawat', $no_rawat)
            ->first();
        // ------------------------

        if (!$regPeriksa) {
            return response()->json(['success' => false, 'message' => 'Data registrasi tidak ditemukan untuk No. Rawat ' . $no_rawat], 404);
        }

        // Ambil data prosedur yang sudah dimuat (termasuk icd9 nya)
        $prosedur = $regPeriksa->prosedurPasien->map(fn($item) => [
            'kode' => $item->kode,
            // Akses deskripsi dari relasi icd9 yang sudah dimuat
            'deskripsi' => $item->penyakit->deskripsi_panjang ?? 'Deskripsi ICD9 tidak ditemukan',
            'status' => $item->status,
        ]);

        // Ambil diagnosa yang sudah dimuat
        $diagnosa = $regPeriksa->diagnosaPasien->map(fn($item) => [
            'kode' => $item->kd_penyakit,
            'deskripsi' => $item->penyakit->nm_penyakit ?? 'Deskripsi Penyakit tidak ditemukan',
            'status' => $item->status,
        ]);

        // Query untuk data lain (obat, lab, radiologi, cppt) tetap sama
        $obat = DetailPemberianObat::with('obat', 'aturanPakai')
            ->where('no_rawat', $no_rawat)
            ->get();

        // New code
$lab = PeriksaLab::with('jenisPerawatan', 'detailPeriksaLab.template') // Correct relationship name, and nested load for template
     ->where('no_rawat', $no_rawat)
     ->get();

        $radiologi = PeriksaRadiologi::with('jnsPerawatanRadiologi', 'hasil', 'gambar')
             ->where('no_rawat', $no_rawat)
             ->get();

        $cppt_pemeriksaan_ralan = PemeriksaanRalan::where('no_rawat', $no_rawat)->orderBy('tgl_perawatan', 'asc')->orderBy('jam_rawat', 'asc')->get();
        $cppt_pemeriksaan = PemeriksaanRanap::where('no_rawat', $no_rawat)->orderBy('tgl_perawatan', 'asc')->orderBy('jam_rawat', 'asc')->get();
        $cppt_catatan = CatatanPerawatan::where('no_rawat', $no_rawat)->orderBy('tanggal', 'asc')->orderBy('jam', 'asc')->get();

        $data = [
            // Gunakan $regPeriksa yang sudah berisi banyak relasi
            'registrasi' => $regPeriksa,
            'diagnosa' => $diagnosa, // Data diagnosa hasil map
            'prosedur' => $prosedur, // Data prosedur hasil map
            'obat' => $obat,
            'lab' => $lab,
            'radiologi' => $radiologi,
            'cppt_pemeriksaan_ralan' => $cppt_pemeriksaan_ralan,
            'cppt_pemeriksaan' => $cppt_pemeriksaan,
            'cppt_catatan' => $cppt_catatan,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}