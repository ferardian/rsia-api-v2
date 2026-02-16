<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KeluargaPegawai;
use App\Traits\LogsToTracker;
use App\Helpers\ApiResponse;

class KeluargaPegawaiController extends Controller
{
    use LogsToTracker;

    /**
     * Store a newly created family member.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $nik
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $nik)
    {
        $request->validate([
            'nama'      => 'required|string|max:100',
            'hubungan'  => 'required|in:Suami,Istri,Anak,Ayah,Ibu,Saudara',
            'no_ktp'    => 'nullable|string|max:20',
            'no_bpjs'   => 'nullable|string|max:20',
            'tgl_lahir' => 'nullable|date',
            'jk'        => 'nullable|in:L,P',
            'pekerjaan' => 'nullable|string|max:100',
            'keterangan'=> 'nullable|string|max:255',
        ]);

        try {
            $keluarga = KeluargaPegawai::create(array_merge(
                $request->all(),
                ['nik' => $nik]
            ));

            $this->logTracker("INSERT INTO rsia_keluarga_pegawai FOR NIK: {$nik}, NAMA: {$request->nama}", $request);

            return ApiResponse::success('Data keluarga berhasil ditambahkan', $keluarga);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan data keluarga', 'store_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified family member.
     *
     * @param  string  $nik
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($nik, $id)
    {
        try {
            $keluarga = KeluargaPegawai::where('nik', $nik)->where('id', $id)->first();

            if (!$keluarga) {
                return ApiResponse::notFound('Data keluarga tidak ditemukan');
            }

            $nama = $keluarga->nama;
            $keluarga->delete();

            $this->logTracker("DELETE FROM rsia_keluarga_pegawai WHERE ID: {$id} (NIK: {$nik}, NAMA: {$nama})", request());

            return ApiResponse::success('Data keluarga berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus data keluarga', 'delete_failed', $e->getMessage(), 500);
        }
    }
}
