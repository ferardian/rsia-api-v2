<?php

namespace App\Http\Controllers\v2;

use App\Models\RsiaSk;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendWhatsApp;

class RsiaSkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $select = $request->input('select', '*');

        $query = RsiaSk::select(array_map('trim', explode(',', $select)))
            ->with([
                'penanggungJawab' => function ($q) { $q->select('nik', 'nama', 'jbtn'); },
                'targetPegawai' => function ($q) { $q->select('nik', 'nama', 'jbtn', 'pendidikan', 'departemen'); }
            ]);

        if ($request->has('status_approval')) {
            $query->where('status_approval', $request->input('status_approval'));
        }

        $data = $query->orderBy('created_at', 'desc')
            ->paginate(10, array_map('trim', explode(',', $select)), 'page', $page);

        return new \App\Http\Resources\Berkas\CompleteCollection($data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'jenis'      => 'required|string',
            'judul'      => 'required|string',
            'pj'         => 'required|exists:pegawai,nik',
            'nik'        => 'nullable|exists:pegawai,nik',
            'tgl_terbit' => 'required|date',
            'file'       => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:102400',
            'status_approval' => 'nullable|in:pengajuan,disetujui,ditolak',
        ]);

        $file = $request->file('file');
        $file_name = '';
        if ($file) {
            $extension = $file->getClientOriginalExtension();
            $prefix = str_replace(' ', '_', $request->prefix ?? 'SK-RSIA');
            $judul = str_replace([' ', '/', '\\'], '_', $request->judul);
            $file_name = strtotime(now()) . '-' . $prefix . '_' . $judul . '.' . $extension;
        }

        try {
            DB::transaction(function () use ($request, $file, $file_name) {
                $tglTerbit = \Carbon\Carbon::parse($request->tgl_terbit);
                $lastNomor = RsiaSk::whereYear('tgl_terbit', $tglTerbit->year)->where('jenis', $request->jenis)->max('nomor');

                if (!$lastNomor) {
                    $lastNomor = 0;
                }

                $newNomor = $lastNomor + 1;

                $request->merge([
                    'nomor'     => $newNomor,
                    'berkas'    => $file_name,
                ]);

                $data = RsiaSk::create($request->all());

                if ($file) {
                    $st = new Storage();



                    \App\Helpers\Logger\RSIALogger::berkas("UPLOADED", 'info', ['file_name' => $file_name, 'file_size' => $file->getSize(), 'data' => $request->all()]);
                    $st::disk('sftp_pegawai')->put(env('DOCUMENT_SK_SAVE_LOCATION') . $file_name, file_get_contents($file));

                    // Sync to Berkas Pegawai if applicable
                    $this->syncToBerkasPegawai($data);
                }
            });
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::berkas("STORE FAILED", 'error', ['data' => $request->all(), 'error' => $e->getMessage()]);
            return ApiResponse::error('failed to save data', 'store_failed', $e->getMessage(), 500);
        }

        if ($request->status_approval === 'pengajuan') {
            $rsiaRole = \App\Models\RsiaRole::where('nama_role', 'Koordinator Diklat')->first();
            $koorDiklat = $rsiaRole ? \App\Models\RsiaUserRole::where('id_role', $rsiaRole->id_role)->where('is_active', 1)->with('petugas')->first() : null;
            $adminPhone = $koorDiklat && $koorDiklat->petugas ? $koorDiklat->petugas->no_telp : null;
            
            if ($adminPhone) {
                $pjNama = \App\Models\Pegawai::where('nik', $request->pj)->value('nama') ?? $request->pj;
                $waMessage = "🚨 *PENGAJUAN NOMOR SPK RKK* 🚨\n\n"
                    . "Terdapat pengajuan Nomor SPK RKK baru yang menunggu persetujuan:\n\n"
                    . "Perihal: *" . $request->judul . "*\n"
                    . "Tgl. Terbit: " . \Carbon\Carbon::parse($request->tgl_terbit)->translatedFormat('d F Y') . "\n"
                    . "Penanggung Jawab: " . $pjNama . "\n\n"
                    . "Silakan cek di menu Persetujuan Nomor Surat pada RSIAP v2.";

                SendWhatsApp::dispatchAfterResponse($adminPhone, $waMessage);
            }
        }

        \App\Helpers\Logger\RSIALogger::berkas("STORED", 'info', ['data' => $request->all()]);
        return ApiResponse::success('data saved successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($identifier)
    {
        if (!base64_decode($identifier, true)) {
            return ApiResponse::error("Invalid parameter : Parameter tidak valid, pastikan parameter adalah base64 encoded dari nomor, jenis dan tanggal misal : 53.B.2024-03-28", "params_invalid", null, 400);
        }

        $decodedId = base64_decode($identifier);
        [$nomor, $jenis, $tgl_terbit] = explode('.', $decodedId);

        $data = RsiaSk::where('nomor', $nomor)
            ->where('jenis', $jenis)
            ->whereDate('tgl_terbit', $tgl_terbit)
            ->with(['penanggungJawab' => function ($query) {
                $query->select('nik', 'nama');
            }])->first();

        if (!$data) {
            return ApiResponse::error('data not found -- identifier : ' . $identifier, 'resource_not_found', 404);
        }

        return new \App\Http\Resources\Berkas\CompleteResource($data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $identifier)
    {
        $request->validate([
            'jenis'      => 'required|string',
            'judul'      => 'required|string',
            'pj'         => 'required|string',
            'nik'        => 'nullable|exists:pegawai,nik',
            'tgl_terbit' => 'required|date',
            'file'       => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:102400',
        ]);

        if (!base64_decode($identifier, true)) {
            return ApiResponse::error("Invalid parameter : Parameter tidak valid, pastikan parameter adalah base64 encoded dari nomor, jenis dan tanggal misal : 53.B.2024-03-28", "params_invalid", null, 400);
        }

        $decodedId = base64_decode($identifier);
        [$nomor, $jenis, $tgl_terbit] = explode('.', $decodedId);

        $data = RsiaSk::where('nomor', $nomor)
            ->where('jenis', $jenis)
            ->whereDate('tgl_terbit', $tgl_terbit)
            ->first();

        if (!$data) {
            return ApiResponse::error('data not found -- identifier : ' . $identifier, 'resource_not_found', 404);
        }

        $oldData   = $data->toArray();
        $oldFile   = $data->berkas;
        $file      = $request->file('file');
        
        $file_name = $data->berkas;
        if ($file) {
            $extension = $file->getClientOriginalExtension();
            $prefix = str_replace(' ', '_', $data->prefix);
            $judul = str_replace([' ', '/', '\\'], '_', $data->judul);
            // Example format: 1770774101-SPK_RKK_dr._Naily_Mei_Rina_Wati.pdf
            $file_name = strtotime(now()) . '-' . $prefix . '_' . $judul . '.' . $extension;
        }

        $request->merge([
            'berkas' => $file_name,
        ]);

        // unset file from request
        $request->offsetUnset('file');

        try {
            DB::transaction(function () use ($request, $file, $file_name, $data, $oldFile) {
                $data->update($request->except(['created_at', 'penanggung_jawab', 'file']));

                if ($file) {
                    $st = new Storage();

                    $st::disk('sftp_pegawai')->put(env('DOCUMENT_SK_SAVE_LOCATION') . $file_name, file_get_contents($file));

                    if ($oldFile && $oldFile != '' && $st::disk('sftp_pegawai')->exists(env('DOCUMENT_SK_SAVE_LOCATION') . $oldFile)) {
                        \App\Helpers\Logger\RSIALogger::berkas("DELETING OLD FILE", 'info', ['file_name' => $oldFile]);
                        $st::disk('sftp_pegawai')->delete(env('DOCUMENT_SK_SAVE_LOCATION') . $oldFile);
                    }

                    $data->update(['berkas' => $file_name]);
                }

                // Sync to Berkas Pegawai if applicable (even if just updating NIK)
                $this->syncToBerkasPegawai($data);
                
            });
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::berkas("STORE FAILED", 'error', ['data' => $request->all(), 'error' => $e->getMessage()]);
            return ApiResponse::error('failed to save data', 'update_failed', $e->getMessage(), 500);
        }

        \App\Helpers\Logger\RSIALogger::berkas("UPDATED", 'info', ['old_data' => $oldData, 'data' => $request->all()]);
        return ApiResponse::success('data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($identifier)
    {
        if (!base64_decode($identifier, true)) {
            return ApiResponse::error("Invalid parameter : Parameter tidak valid, pastikan parameter adalah base64 encoded dari nomor, jenis dan tanggal misal : 53.B.2024-03-28", "params_invalid", null, 400);
        }

        $decodedId = base64_decode($identifier);
        [$nomor, $jenis, $tgl_terbit] = explode('.', $decodedId);

        $data = RsiaSk::where('nomor', $nomor)
            ->where('jenis', $jenis)
            ->whereDate('tgl_terbit', $tgl_terbit)
            ->first();

        if (!$data) {
            return ApiResponse::error('data not found -- identifier : ' . $identifier, 'resource_not_found', 404);
        }

        try {
            DB::transaction(function () use ($data) {
               $data->delete();
            });
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::berkas("DELETE FAILED", 'error', ['data' => $data, 'error' => $e->getMessage()]);
            return ApiResponse::error('failed to delete data', 'delete_failed', $e->getMessage(), 500);
        }
        
        $st = new Storage();
        if ($data->berkas && $data->berkas != '' && $st::disk('sftp_pegawai')->exists(env('DOCUMENT_SK_SAVE_LOCATION') . $data->berkas)) {
            \App\Helpers\Logger\RSIALogger::berkas("DELETING FILE", 'info', ['file_name' => $data->berkas]);
            $st::disk('sftp_pegawai')->delete(env('DOCUMENT_SK_SAVE_LOCATION') . $data->berkas);
        }
        
        \App\Helpers\Logger\RSIALogger::berkas("DELETED", 'info', ['data' => $data]);
        return ApiResponse::success('data deleted successfully');
    }

    /**
     * Approve a Kredensial request.
     *
     * @param  string  $identifier
     * @return \Illuminate\Http\Response
     */
    public function approve_kredensial($identifier)
    {
        if (!base64_decode($identifier, true)) {
            return ApiResponse::error("Invalid parameter", "params_invalid", null, 400);
        }

        $decodedId = base64_decode($identifier);
        [$nomor, $jenis, $tgl_terbit] = explode('.', $decodedId);

        $data = RsiaSk::where('nomor', $nomor)
            ->where('jenis', $jenis)
            ->whereDate('tgl_terbit', $tgl_terbit)
            ->first();

        if (!$data) {
            return ApiResponse::error('data not found', 'resource_not_found', 404);
        }

        try {
            DB::transaction(function () use ($data) {
                $data->update(['status_approval' => 'disetujui']);
            });

            // Send WA Notification to Penanggung Jawab
            $pjPegawai = \App\Models\Pegawai::where('nik', $data->pj)->with('petugas')->first();
            $pjPhone = $pjPegawai && $pjPegawai->petugas ? $pjPegawai->petugas->no_telp : null;
            
            if ($pjPhone) {
                $waMessage = "✅ *PENGAJUAN NOMOR SPK RKK DISETUJUI* ✅\n\n"
                    . "Pengajuan Nomor SPK RKK Anda dengan perihal *" . $data->judul . "* telah disetujui.\n\n"
                    . "Silakan cek selengkapnya di menu Dokumen / Komite pada RSIAP v2.";

                \App\Jobs\SendWhatsApp::dispatchAfterResponse($pjPhone, $waMessage);
            }
            
        } catch (\Exception $e) {
            return ApiResponse::error('failed to approve data', 'update_failed', $e->getMessage(), 500);
        }

        return ApiResponse::success('data approved successfully');
    }

    /**
     * Sync Credentialing SK to Berkas Pegawai table
     */
    private function syncToBerkasPegawai($sk)
    {
        // Only sync ifjudul contains "SPK RKK" and nik is provided
        if (!$sk->nik || !str_contains(strtoupper($sk->judul), 'SPK RKK')) {
            return;
        }

        if (!$sk->berkas) {
            return;
        }

        $pegawai = \App\Models\Pegawai::where('nik', $sk->nik)->first();
        if (!$pegawai) {
            return;
        }

        // Determine kode_berkas based on profession/education
        $kode_berkas = 'MBP0045'; // Default: Profesi Lain
        $pendidikan = strtoupper($pegawai->pendidikan);
        $jabatan = strtoupper($pegawai->jbtn);

        if (str_contains($jabatan, 'SPESIALIS')) {
            $kode_berkas = 'MBP0019'; // Tenaga klinis Dokter Spesialis
        } elseif (str_contains($jabatan, 'DOKTER') || str_contains($pendidikan, 'DOKTER')) {
            $kode_berkas = 'MBP0006'; // Tenaga klinis Dokter Umum
        } elseif (str_contains($jabatan, 'PERAWAT') || str_contains($jabatan, 'BIDAN')) {
            $kode_berkas = 'MBP0032'; // Tenaga klinis Perawat dan Bidan
        }

        try {
            $st = new Storage();
            $skPath = env('DOCUMENT_SK_SAVE_LOCATION') . $sk->berkas;
            $berkasPegawaiLocation = env('DOCUMENT_SAVE_LOCATION', 'webapps/penggajian/pages/berkaspegawai/berkas/');
            
            // Format Filename for Berkas Pegawai (Legacy style consistency)
            $nik_formatted = str_replace('.', '-', $sk->nik);
            $nama_pegawai_formatted = preg_replace('/[^A-Za-z0-9\-]/', '-', str_replace(' ', '-', $pegawai->nama));
            $nama_pegawai_formatted = trim(preg_replace('/-+/', '-', $nama_pegawai_formatted), '-');
            
            $ext = pathinfo($sk->berkas, PATHINFO_EXTENSION);
            $berkasFileName = $nik_formatted . '-SPK-RKK-' . $nama_pegawai_formatted . '.' . $ext;
            
            $fullDestPath = rtrim($berkasPegawaiLocation, '/') . '/' . $berkasFileName;

            // Copy file on SFTP
            if ($st::disk('sftp_pegawai')->exists($skPath)) {
                $fileContent = $st::disk('sftp_pegawai')->get($skPath);
                $st::disk('sftp_pegawai')->put($fullDestPath, $fileContent);
                
                // Update/Create record in berkas_pegawai
                \App\Models\BerkasPegawai::updateOrCreate(
                    ['nik' => $sk->nik, 'kode_berkas' => $kode_berkas],
                    [
                        'tgl_uploud' => date('Y-m-d'),
                        'berkas' => "pages/berkaspegawai/berkas/" . $berkasFileName
                    ]
                );
                
                \App\Helpers\Logger\RSIALogger::berkas("SYNCED TO BERKAS PEGAWAI", 'info', [
                    'sk_nomor' => $sk->nomor,
                    'nik' => $sk->nik,
                    'kode_berkas' => $kode_berkas,
                    'file' => $berkasFileName
                ]);
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::berkas("SYNC TO BERKAS PEGAWAI FAILED", 'error', ['error' => $e->getMessage()]);
        }
    }
}
