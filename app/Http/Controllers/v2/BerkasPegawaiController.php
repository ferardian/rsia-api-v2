<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BerkasPegawaiController  extends Controller
{
    /**
     * Menampilkan daftar berkas pegawai.
     *
     * @param  string  $nik
     * @return \App\Http\Resources\Berkas\CompleteCollection
     */
    public function index($nik, Request $request)
    {
        $select = $request->query('select', '*');
        $berkas = \App\Models\BerkasPegawai::where('nik', $nik)->with('masterBerkasPegawai')->get(explode(',', $select));

        return new \App\Http\Resources\Berkas\CompleteCollection($berkas);
    }

    /**
     * Menampilkan form untuk membuat berkas pegawai baru.
     *
     * > fungsi ini tidak digunakan dalam API.
     * 
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Menyimpan berkas pegawai baru.
     *
     * @param  string  $nik
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store($nik, Request $request)
    {
        $request->validate(self::validationRule());

        try {
            // TODO : tambahkan upload file, jika file gagal diupload maka berkas tidak disimpan
            \App\Models\BerkasPegawai::create($request->all());
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to save data', 'store_failed', $e->getMessage(), 500);
        }

        return \App\Helpers\ApiResponse::success('Data saved successfully');
    }

    /**
     * Menampilkan detail berkas pegawai.
     *
     * @param  string  $nik
     * @param  string  $kode_berkas
     * @return \Illuminate\Http\Response
     */
    public function show($nik, $kode_berkas, Request $request)
    {
        $select = $request->query('select', '*');

        $berkas = \App\Models\BerkasPegawai::select(explode(',', $select))->where('nik', $nik)->where('kode_berkas', $kode_berkas)->first();
        if (!$berkas) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        return \App\Http\Resources\Berkas\CompleteResource::make($berkas);
    }

    /**
     * Menampilkan form untuk mengedit berkas pegawai.
     *
     * > fungsi ini tidak digunakan dalam API.
     * 
     * @param  string  $kode_berkas
     * @return \Illuminate\Http\Response
     */
    public function edit($kode_berkas)
    {
    }

    /**
     * Update berkas pegawai.
     * 
     * > catatan: data key pada body request harus sesuai dengan field pada tabel berkas_pegawai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $kode_berkas
     * @return \Illuminate\Http\Response
     */
    public function update($nik, Request $request, $kode_berkas)
    {
        $request->validate(self::validationRule(false));

        $berkas = \App\Models\BerkasPegawai::where('nik', $nik)->where('kode_berkas', $kode_berkas)->exists();
        if (!$berkas) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        try {
            // TODO : tambahkan upload file, jika file gagal diupload maka berkas tidak disimpan
            \App\Models\BerkasPegawai::where('nik', $nik)->where('kode_berkas', $kode_berkas)->update($request->all());
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to update data', 'update_failed', $e->getMessage(), 500);
        }

        return \App\Helpers\ApiResponse::success('Data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $kode_berkas
     * @return \Illuminate\Http\Response
     */
    public function destroy($nik, $kode_berkas)
    {
        $berkas = \App\Models\BerkasPegawai::where('nik', $nik)->where('kode_berkas', $kode_berkas)->first();
        if (!$berkas) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        try {
            // Delete file from SFTP
            $st = new \Illuminate\Support\Facades\Storage();
            $location = \Illuminate\Support\Str::finish(env('DOCUMENT_SAVE_LOCATION', 'webapps/penggajian/pages/berkaspegawai/berkas/'), '/');
            
            // Extract filename to avoid doubling paths if database contains path prefix
            $filename = basename($berkas->berkas);
            $fullPath = $location . $filename;

            if ($st::disk('sftp_pegawai')->exists($fullPath)) {
                $st::disk('sftp_pegawai')->delete($fullPath);
            }

            $berkas->delete();
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to delete data', 'delete_failed', $e->getMessage(), 500);
        }

        return \App\Helpers\ApiResponse::success('Data deleted successfully');
    }

    // ==================== LEGACY METHODS (Adopting from e-personal-next) ====================

    /**
     * Get employee documents (POST style)
     */
    public function getBerkas(Request $request)
    {
        $request->validate(['nik' => 'required|string|exists:pegawai,nik']);
        $nik = $request->input('nik');

        $pegawai = \App\Models\Pegawai::select('nik', 'nama', 'jbtn', 'departemen')
            ->with(['dep' => function($q) { $q->select('dep_id', 'nama'); }])
            ->where('nik', $nik)
            ->first();

        if (!$pegawai) {
            return \App\Helpers\ApiResponse::notFound('Pegawai not found');
        }

        $berkas = \App\Models\BerkasPegawai::where('nik', $nik)
            ->with('masterBerkasPegawai')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => [
                'nik' => $pegawai->nik,
                'nama' => $pegawai->nama,
                'jbtn' => $pegawai->jbtn,
                'bidang' => $pegawai->dep ? $pegawai->dep->nama : $pegawai->departemen,
                'save_location' => \Illuminate\Support\Str::finish(env('DOCUMENT_SAVE_LOCATION', 'webapps/penggajian/pages/berkaspegawai/berkas/'), '/'),
                'berkas' => $berkas
            ]
        ]);
    }

    /**
     * Get document categories
     */
    public function getBerkasKategori()
    {
        $kategori = \App\Models\MasterBerkasPegawai::select('kategori')
            ->distinct()
            ->orderBy('kategori', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $kategori
        ]);
    }

    /**
     * Get document names by category
     */
    public function getNamaBerkas(Request $request)
    {
        $kategori = $request->query('kategori');
        $query = \App\Models\MasterBerkasPegawai::select('kode', 'nama_berkas as nama');
        
        if ($kategori) {
            $query->where('kategori', $kategori);
        }

        $data = $query->orderBy('nama_berkas', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Upload employee document
     */
    public function uploadBerkas(Request $request)
    {
        $request->validate([
            'nik' => 'required|string|exists:pegawai,nik',
            'berkas' => 'required|string|exists:master_berkas_pegawai,kode',
            'file_berkas' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048'
        ]);

        $nik = $request->input('nik');
        $kode_berkas = $request->input('berkas');
        $file = $request->file('file_berkas');

        // Fetch additional info for naming
        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();
        $master_berkas = \App\Models\MasterBerkasPegawai::where('kode', $kode_berkas)->first();

        if (!$pegawai || !$master_berkas) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai atau master berkas tidak ditemukan'
            ], 404);
        }

        // Format NIK: replace dots with dashes
        $nik_formatted = str_replace('.', '-', $nik);

        // Format Nama Berkas: uppercase, replace spaces/special chars with dashes
        $nama_berkas_formatted = str_replace(' ', '-', strtoupper($master_berkas->nama_berkas));
        $nama_berkas_formatted = preg_replace('/[^A-Z0-9\-]/', '-', $nama_berkas_formatted);
        $nama_berkas_formatted = preg_replace('/-+/', '-', $nama_berkas_formatted);
        $nama_berkas_formatted = trim($nama_berkas_formatted, '-');

        // Format Nama Pegawai: replace spaces/special chars with dashes
        $nama_pegawai_formatted = str_replace(' ', '-', $pegawai->nama);
        $nama_pegawai_formatted = preg_replace('/[^A-Za-z0-9\-]/', '-', $nama_pegawai_formatted);
        $nama_pegawai_formatted = preg_replace('/-+/', '-', $nama_pegawai_formatted);
        $nama_pegawai_formatted = trim($nama_pegawai_formatted, '-');

        // Generate filename: NIK-NamaBerkas-NamaPegawai.ext
        $file_name = $nik_formatted . '-' . $nama_berkas_formatted . '-' . $nama_pegawai_formatted . '.' . $file->getClientOriginalExtension();

        try {
            $st = new \Illuminate\Support\Facades\Storage();
            $location = env('DOCUMENT_SAVE_LOCATION', 'webapps/penggajian/pages/berkaspegawai/berkas/');
            
            // Ensure location ends with a slash
            if ($location && !\Illuminate\Support\Str::endsWith($location, '/')) {
                $location .= '/';
            }

            if (!$st::disk('sftp_pegawai')->exists($location)) {
                $st::disk('sftp_pegawai')->makeDirectory($location);
            }

            // Move file to SFTP
            $st::disk('sftp_pegawai')->put($location . $file_name, file_get_contents($file));

            // Update or Create record in database
            // Legacy consistency: prepend "pages/berkaspegawai/berkas/" to the filename
            $db_file_name = "pages/berkaspegawai/berkas/" . $file_name;
            
            \App\Models\BerkasPegawai::updateOrCreate(
                ['nik' => $nik, 'kode_berkas' => $kode_berkas],
                [
                    'tgl_uploud' => date('Y-m-d'),
                    'berkas' => $db_file_name
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Berkas berhasil diupload',
                'file' => $file_name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah berkas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee document (POST style)
     */
    public function deleteBerkas(Request $request)
    {
        $request->validate([
            'nik' => 'required|string',
            'kode' => 'required|string',
            'berkas' => 'required|string'
        ]);

        return $this->destroy($request->nik, $request->kode);
    }

    private static function validationRule($withRequired = true)
    {
        return [
            "nik"         => "required|string|exists:pegawai,nik",
            "tgl_uploud"  => "required|date",
            "kode_berkas" => "required|string|exists:master_berkas_pegawai,kode",
            "berkas"      => "required|string",
        ];
    }
}
