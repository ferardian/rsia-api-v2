<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Pegawai;

use App\Traits\LogsToTracker;

class PegawaiController extends Controller
{
    use LogsToTracker;
    /**
     * Menampilkan daftar pegawai.
     * 
     * @queryParam page int Halaman yang ditampilkan. Defaults to 1. Example: 1
     * @queryParam select string Kolom yang ingin ditampilkan. Defaults to '*'. Example: nik,nama
     *
     * @return \App\Http\Resources\Pegawai\CompleteCollection
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $select = $request->query('select', '*');

        // Optimized query with LEFT JOIN to get role data in single query
        $pegawaiQuery = \DB::table('pegawai as p')
            ->leftJoin('rsia_user_role as ur', function($join) {
                $join->on('ur.nip', '=', 'p.nik')
                     ->where('ur.is_active', 1);
            })
            ->leftJoin('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
            ->leftJoin('petugas as pt', 'pt.nip', '=', 'p.nik')
            ->leftJoin('rsia_email_pegawai as rep', 'rep.nik', '=', 'p.nik')
            ->leftJoin('rsia_nomor_kartu_pegawai as rnk', 'rnk.nip', '=', 'p.nik') // Added
            ->leftJoin('departemen as d', 'd.dep_id', '=', 'p.departemen')
            ->leftJoin('rsia_keluarga_pegawai as rkp', 'rkp.nik', '=', 'p.nik') // Added for family count
            ->select([
                'p.nik as id_user',
                'p.nik',
                'p.nama',
                'p.jk',
                'p.tmp_lahir',
                'p.tgl_lahir',
                'p.alamat',
                'p.pendidikan',
                'p.no_ktp',
                'rnk.no_bpjs', // Added
                'rnk.no_bpjstk', // Added
                'pt.no_telp',
                'rep.email',
                'p.jbtn',
                'p.departemen',
                'd.nama as nama_departemen',
                'p.mulai_kerja',
                'p.stts_aktif',
                'p.photo',
                \DB::raw('COUNT(DISTINCT rkp.no_ktp) as jml_keluarga'), // Added
                \DB::raw('GROUP_CONCAT(DISTINCT r.id_role SEPARATOR ",") as id_role'),
                \DB::raw('GROUP_CONCAT(DISTINCT r.nama_role SEPARATOR ", ") as nama_role')
            ])
            ->groupBy('p.nik', 'p.nama', 'p.jk', 'p.tmp_lahir', 'p.tgl_lahir', 'p.alamat', 'p.pendidikan', 'p.no_ktp', 'rnk.no_bpjs', 'rnk.no_bpjstk', 'pt.no_telp', 'rep.email', 'p.jbtn', 'p.departemen', 'd.nama', 'p.mulai_kerja', 'p.stts_aktif', 'p.photo')
            ->orderBy('p.nama', 'asc');
            // ->where('p.stts_aktif', 'AKTIF') // Removed hardcoded filter
            // ->where('pt.kd_jbtn', '<>', '-');

        // Apply Filters
        if ($request->has('stts_aktif')) {
            if ($request->stts_aktif === 'ALL') {
                // Show all, no status filter
            } else if ($request->stts_aktif === 'NON-AKTIF') {
                $pegawaiQuery->where('p.stts_aktif', '<>', 'AKTIF');
            } else {
                $pegawaiQuery->where('p.stts_aktif', $request->stts_aktif);
            }
        } else {
            // Default to AKTIF if no status filter provided
            $pegawaiQuery->where('p.stts_aktif', 'AKTIF');
        }

        if ($request->has('filter.departemen')) {
            $pegawaiQuery->where('p.departemen', $request->input('filter.departemen'));
        }

        // Apply Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $pegawaiQuery->where(function($q) use ($search) {
                $q->where('p.nama', 'like', "%{$search}%")
                  ->orWhere('p.nik', 'like', "%{$search}%");
            });
        }

        $limit = $request->query('limit', 50);
        
        // Ensure limit is reasonable (e.g. max 5000 to prevent OOM)
        $limit = ($limit > 5000) ? 5000 : $limit;

        $pegawai = $pegawaiQuery->paginate($limit, ['*'], 'page', $page);

        // Transform data to match frontend expectations (convert to array)
        $transformedData = [];
        foreach ($pegawai->items() as $item) {
            // Handle multiple roles (pick first one for ID, show all for name)
            $roleIds = $item->id_role ? explode(',', $item->id_role) : [];
            $primaryRoleId = count($roleIds) > 0 ? (int) $roleIds[0] : null;

            $transformedData[] = [
                'id_user' => $item->id_user,
                'nik' => $item->nik,
                'nip' => $item->nik,
                'nama' => $item->nama,
                'jk' => $item->jk,
                'tmp_lahir' => $item->tmp_lahir,
                'tgl_lahir' => $item->tgl_lahir,
                'alamat' => $item->alamat,
                'pendidikan' => $item->pendidikan,
                'no_ktp' => $item->no_ktp,
                'no_telp' => $item->no_telp,
                'no_bpjs' => $item->no_bpjs, // Added
                'no_bpjstk' => $item->no_bpjstk, // Added
                'username' => $item->nik, // fallback to nik
                'email' => $item->email, // Added
                'id_role' => $primaryRoleId,
                'role_name' => $item->nama_role ?: 'Belum ada role',
                'stts_aktif' => $item->stts_aktif,
                'status' => $item->stts_aktif === 'AKTIF' ? 1 : 0,
                'jbtn' => $item->jbtn,
                'departemen' => $item->nama_departemen ?? $item->departemen, // Use Name OR Code as fallback
                'mulai_kerja' => $item->mulai_kerja,
                'jml_keluarga' => $item->jml_keluarga ?? 0, // Added
                'photo' => $item->photo,
                'created_at' => null, // created_at doesn't exist in pegawai table
                'updated_at' => null, // updated_at doesn't exist in pegawai table
            ];
        }

        // Return formatted response
        return response()->json([
            'success' => true,
            'debug_message' => 'Using v2 PegawaiController with LEFT JOIN',
            'sample_data' => $transformedData[0] ?? null,
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $pegawai->currentPage(),
                'last_page' => $pegawai->lastPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
                'from' => $pegawai->firstItem(),
                'to' => $pegawai->lastItem(),
            ]
        ]);
    }

    /**
     * Menampilkan form untuk membuat pegawai baru.
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
     * Menyimpan pegawai baru.
     * 
     * > catatan: data key pada body request harus sesuai dengan field pada tabel pegawai.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate(self::validationRule());
        $file = $request->file('file');

        if ($file) {
            $file_name = time() . $file->getClientOriginalName() . '.' . $file->extension();
            $st = new Storage();

            if (!$st::disk('sftp')->exists(env('FOTO_PEGAWAI_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('FOTO_PEGAWAI_SAVE_LOCATION'));
            }

            $request->merge(['photo' => $file_name]);
        }

        try {
            \DB::transaction(function () use ($request) {
                // Determine JK for Petugas (L/P)
                $jkPetugas = $request->jk == 'Pria' ? 'L' : 'P';
                
                // Create Pegawai
                \App\Models\Pegawai::create($request->all());
                
                // Create Petugas if not exists
                \App\Models\Petugas::updateOrCreate(['nip' => $request->nik], [
                    'nama'       => $request->nama,
                    'jk'         => $jkPetugas,
                    'tmp_lahir'  => $request->tmp_lahir,
                    'tgl_lahir'  => $request->tgl_lahir,
                    'gol_darah'  => $request->gol_darah ?? '-',
                    'agama'      => $request->agama ?? '-',
                    'stts_nikah' => $request->stts_nikah ?? 'BELUM MENIKAH',
                    'alamat'     => $request->alamat,
                    'kd_jbtn'    => $request->kd_jbtn ?? '-',
                    'no_telp'    => $request->no_telp ?? '-',
                    'status'     => 1
                ]);

                // Create or Update Email
                if ($request->email) {
                    \App\Models\RsiaEmailPegawai::updateOrCreate(['nik' => $request->nik], [
                        'email' => $request->email
                    ]);
                }

                // Create or Update Card Number
                if ($request->no_bpjs || $request->no_bpjstk) {
                    \App\Models\RsiaNomorKartuPegawai::updateOrCreate(['nip' => $request->nik], [
                        'no_bpjs' => $request->no_bpjs,
                        'no_bpjstk' => $request->no_bpjstk
                    ]);
                }

                $sql = "INSERT/UPDATE pegawai & petugas & email & kartu for nik: {$request->nik}";
                $this->logTracker($sql, $request);
            });
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to save data', 'store_failed', $e->getMessage(), 500);
        }

        if ($file) {
            $st::disk('sftp')->put(env('FOTO_PEGAWAI_SAVE_LOCATION') . $file_name, file_get_contents($file));
        }

        return \App\Helpers\ApiResponse::success('Data saved successfully');
    }

    /**
     * Menampilkan data pegawai berdasarkan NIK.
     *
     * @queryParam select string Kolom yang ingin ditampilkan. Defaults to '*'. Example: nik,nama
     *
     * @param  string  $id NIK pegawai. Example: 3.928.0623
     * @return \App\Http\Resources\Pegawai\CompleteResource
     */
    public function show($id, Request $request)
    {
        $select = $request->query('select', '*');

        // Use array for select, or * if default
        $fields = $select === '*' ? ['*'] : explode(',', $select);
        
        // Ensure internal keys are present if we have a specific select
        if ($select !== '*') {
            if (!in_array('nik', $fields)) $fields[] = 'nik';
            if (!in_array('no_ktp', $fields)) $fields[] = 'no_ktp';
        }

        // Debug: log apa yang ada di database
        $pegawaiRaw = \App\Models\Pegawai::where('nik', $id)->first();
        \Log::info('Raw pegawai data for nik ' . $id, [
            'nik' => $pegawaiRaw->nik ?? 'null',
            'no_ktp' => $pegawaiRaw->no_ktp ?? 'null',
            'all_data' => $pegawaiRaw ? $pegawaiRaw->toArray() : 'not found'
        ]);

        $pegawai = \App\Models\Pegawai::select($fields)
            ->where('nik', $id)
            ->first();
        
        // Handle includes manually since we are not using Orion's automatic handling here
        if ($request->has('include')) {
            $includes = explode(',', $request->query('include'));
            $allowedIncludes = ['dep', 'petugas', 'email', 'statusKerja', 'nomorKartu', 'keluarga'];
            $validIncludes = array_intersect($includes, $allowedIncludes);
            
            if (!empty($validIncludes)) {
                $pegawai->load($validIncludes);
            }
        }

        if (!$pegawai) {
            return \App\Helpers\ApiResponse::notFound('Pegawai not found');
        }

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Pegawai\CompleteResource($pegawai)
        ]);
    }

    /**
     * Menampilkan form untuk mengedit pegawai.
     *
     * > fungsi ini tidak digunakan dalam API.
     * 
     * @param  string  $id Nik pegawai
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update pegawai berdasarkan NIK.
     * 
     * > catatan: data key pada body request harus sesuai dengan field pada tabel pegawai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id Nik pegawai. Example: 3.928.0623
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $file = $request->file('file');
        $request->validate(self::validationRule(false));

        $pegawai = \App\Models\Pegawai::find($id);
        $oldPhoto = $pegawai->photo;
        if (!$pegawai) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        if ($file) {
            $file_name = time() . $file->getClientOriginalName() . '.' . $file->extension();
            $st = new Storage();

            if (!$st::disk('sftp')->exists(env('FOTO_PEGAWAI_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('FOTO_PEGAWAI_SAVE_LOCATION'));
            }

            $request->merge(['photo' => $file_name]);
        }

        try {
            \DB::transaction(function () use ($request, $pegawai, $id) {
                // Update Pegawai
                $pegawai->update($request->all());

                // Determine JK for Petugas (L/P)
                $jkPetugas = $request->jk == 'Pria' ? 'L' : 'P';

                // Update or Create Petugas
                \App\Models\Petugas::updateOrCreate(['nip' => $id], [
                    'nama'       => $request->nama,
                    'jk'         => $jkPetugas,
                    'tmp_lahir'  => $request->tmp_lahir,
                    'tgl_lahir'  => $request->tgl_lahir,
                    'gol_darah'  => $request->gol_darah ?? '-',
                    'agama'      => $request->agama ?? '-',
                    'stts_nikah' => $request->stts_nikah ?? 'BELUM MENIKAH',
                    'alamat'     => $request->alamat,
                    'kd_jbtn'    => $request->kd_jbtn ?? '-',
                    'no_telp'    => $request->no_telp ?? '-',
                    'status'     => (isset($request->stts_aktif) && $request->stts_aktif == 'AKTIF') ? 1 : 0
                ]);

                // Update or Create Email
                if ($request->email) {
                    \App\Models\RsiaEmailPegawai::updateOrCreate(['nik' => $id], [
                        'email' => $request->email
                    ]);
                }

                // Update or Create Card Number
                if ($request->no_bpjs || $request->no_bpjstk) {
                    \App\Models\RsiaNomorKartuPegawai::updateOrCreate(['nip' => $id], [
                        'no_bpjs' => $request->no_bpjs,
                        'no_bpjstk' => $request->no_bpjstk
                    ]);
                }

                $sql = "UPDATE pegawai & petugas & email & kartu for nik: {$id}";
                $this->logTracker($sql, $request);
            });
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to update data', 'update_failed', $e->getMessage(), 500);
        }

        if ($request->delete_old_photo) {
            if ($pegawai && $file && $oldPhoto != '' && $st::disk('sftp')->exists(env('FOTO_PEGAWAI_SAVE_LOCATION') . $oldPhoto)) {
                $st::disk('sftp')->delete(env('FOTO_PEGAWAI_SAVE_LOCATION') . $oldPhoto);
            }
        }

        if ($file) {
            $st::disk('sftp')->put(env('FOTO_PEGAWAI_SAVE_LOCATION') . $file_name, file_get_contents($file));
        }

        return \App\Helpers\ApiResponse::success('Data updated successfully');
    }

    /**
     * Menghapus pegawai berdasarkan NIK.
     * 
     * > catatan : data yang di hapus tidak dapat dikembalikan.
     * 
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $pegawai = \App\Models\Pegawai::find($id);
        if (!$pegawai) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        try {
            $pegawai->delete();
            $sql = "DELETE FROM pegawai WHERE nik='{$id}'";
            $this->logTracker($sql, request());
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to delete data', 'delete_failed', $e->getMessage(), 500);
        }

        return \App\Helpers\ApiResponse::success('Data deleted successfully');
    }

    public function get(Request $request)
    {
        if (!$request->user()) {
            return \App\Helpers\ApiResponse::unauthorized('Unauthorized');
        }

        $pegawai = \App\Models\Pegawai::with('dep')
            ->select('nik', 'nama', 'jk', 'jbtn', 'departemen', 'photo')
            ->where('nik', $request->user()->id_user)->first();

        if (!$pegawai) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        return \App\Helpers\ApiResponse::success('Data retrieved successfully', $pegawai);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'email'   => 'required|email',
            'alamat'  => 'required|string',
            'no_telp' => 'required|numeric|digits_between:11,13'
        ]);

        $user = \Illuminate\Support\Facades\Auth::guard('user-aes')->user();

        $pegawai = \App\Models\Pegawai::where('nik', $user->id_user)->first();
        $petugas = \App\Models\Petugas::where('nip', $user->id_user)->first();

        if (!$pegawai || !$petugas) {
            return \App\Helpers\ApiResponse::notFound('Resource not found');
        }

        try {
            \DB::transaction(function () use ($request, $pegawai, $petugas) {
                \App\Models\Petugas::where('nip', $petugas->nip)->update([
                    'alamat'  => $request->alamat,
                    'no_telp' => $request->no_telp,
                ]);

                \App\Models\RsiaEmailPegawai::updateOrCreate(['nik' => $pegawai->nik], [
                    'email' => $request->email,
                ]);

                \App\Models\Pegawai::whereNik($pegawai->nik)->update([
                    'alamat' => $request->alamat,
                ]);

                $sql = "UPDATE pegawai SET alamat='{$request->alamat}' WHERE nik='{$pegawai->nik}'; UPDATE petugas SET alamat='{$request->alamat}' WHERE nip='{$pegawai->nik}'";
                $this->logTracker($sql, $request);
            });
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to update data', 'update_failed', $e->getMessage(), 500);
        }

        return \App\Helpers\ApiResponse::success('Data updated successfully');
    }

    /**
     * Get real KTP number (pegawai.no_ktp) based on doctor kd_dokter = pegawai.nik
     * kd_dokter = NIP (nomor pegawai)
     * no_ktp = NIK (nomor KTP, 16 digit)
     */
    public function getKtpNumber($kdDokter, Request $request)
    {
        \Log::info('Getting KTP number for doctor kd_dokter (NIP): ' . $kdDokter);

        // Query pegawai where nik (NIP) = kd_dokter
        $pegawai = \App\Models\Pegawai::select('nik', 'nama', 'no_ktp')
            ->where('nik', $kdDokter)
            ->first();

        if (!$pegawai) {
            \Log::info('Pegawai not found for kd_dokter: ' . $kdDokter);
            return \App\Helpers\ApiResponse::notFound('Pegawai not found for kd_dokter: ' . $kdDokter);
        }

        \Log::info('Found pegawai with KTP data', [
            'nik' => $pegawai->nik,          // NIP
            'nama' => $pegawai->nama,        // nama pegawai
            'no_ktp' => $pegawai->no_ktp     // NIK (nomor KTP)
        ]);

        return \App\Helpers\ApiResponse::success('KTP number retrieved', [
            'nip' => $pegawai->nik,          // NIP pegawai
            'nama' => $pegawai->nama,        // nama pegawai
            'no_ktp' => $pegawai->no_ktp     // NO_KTP (nomor KTP asli, 16 digit)
        ]);
    }

    private static function validationRule($withRequired = true)
    {
        return [
            "file"          => "nullable|mimes:jpeg,jpg,png|max:20480",

            "nik"            => ($withRequired ? 'required|' : '') . "string|regex:/^\d{1,3}\.\d{1,3}\.\d{1,4}$/",
            "nama"           => "required|string",
            "jk"             => "required|string|in:Wanita,Pria",
            "jbtn"           => "required|string",
            "jnj_jabatan"    => "required|string|exists:jnj_jabatan,kode",
            "kode_kelompok"  => ($withRequired ? "required|" : "") . "string|exists:kelompok_jabatan,kode_kelompok",
            "kode_resiko"    => "required|string|exists:resiko_kerja,kode_resiko",
            "kode_emergency" => ($withRequired ? "required|" : "") . "string|exists:emergency_index,kode_emergency",
            "status_koor"    => ($withRequired ? "required|" : "") . "string|in:0,1",
            "departemen"     => "required|string|exists:departemen,dep_id",
            "bidang"         => "required|string|exists:bidang,nama",
            "stts_wp"        => ($withRequired ? "required|" : "") . "string|exists:stts_wp,stts",
            "stts_kerja"     => ($withRequired ? "required|" : "") . "string|exists:stts_kerja,stts",
            "npwp"           => ($withRequired ? "required|" : "") . "string",
            "pendidikan"     => ($withRequired ? "required|" : "") . "string|exists:pendidikan,tingkat",
            "gapok"          => ($withRequired ? "required|" : "") . "numeric",
            "tmp_lahir"      => ($withRequired ? "required|" : "") . "string",
            "tgl_lahir"      => ($withRequired ? "required|" : "") . "date",
            "alamat"         => ($withRequired ? "required|" : "") . "string",
            "kota"           => ($withRequired ? "required|" : "") . "string",
            "mulai_kerja"    => "required|date",
            "ms_kerja"       => ($withRequired ? "required|" : "") . "string|in:<1,PT,FT>1",
            "indexins"       => ($withRequired ? "required|" : "") . "string|exists:departemen,dep_id",
            "bpd"            => ($withRequired ? "required|" : "") . "string|exists:bank,namabank",
            "rekening"       => ($withRequired ? "required|" : "") . "string",
            "stts_aktif"     => "required|string|in:AKTIF,CUTI,KELUAR,TENAGA LUAR",
            "wajibmasuk"     => ($withRequired ? "required|" : "") . "integer",
            "pengurang"      => ($withRequired ? "required|" : "") . "numeric",
            "indek"          => ($withRequired ? "required|" : "") . "integer",
            "mulai_kontrak"  => ($withRequired ? "required|" : "") . "date",
            "cuti_diambil"   => ($withRequired ? "required|" : "") . "integer",
            "dankes"         => ($withRequired ? "required|" : "") . "numeric",
            "photo"          => ($withRequired ? "required|" : "") . "string",
            "no_ktp"         => ($withRequired ? "required|" : "") . "string",
        ];
    }

    /**
     * Search pegawai
     */
    public function search(Request $request)
    {
        try {
            $query = $request->get('q');
            $limit = $request->get('limit', 50);

            if (!$query) {
                return response()->json([
                    'success' => false,
                    'error' => 'Query parameter is required'
                ], 400);
            }

            $pegawaiQuery = \DB::table('pegawai as p')
                ->leftJoin('petugas as pt', 'pt.nip', '=', 'p.nik')
                ->leftJoin('rsia_user_role as ur', function($join) {
                    $join->on('ur.nip', '=', 'p.nik')
                         ->where('ur.is_active', 1);
                })
                ->leftJoin('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
                ->leftJoin('departemen as d', 'd.dep_id', '=', 'p.departemen')
                ->leftJoin('rsia_nomor_kartu_pegawai as rnk', 'rnk.nip', '=', 'p.nik') // Added
                ->leftJoin('rsia_keluarga_pegawai as rkp', 'rkp.nik', '=', 'p.nik'); // Added for family count
                // ->where('p.stts_aktif', 'AKTIF') // Removed hardcoded filter
                // ->where('pt.kd_jbtn', '<>', '-');

            // Status Filter in Search
            if ($request->has('stts_aktif')) {
                if ($request->stts_aktif === 'ALL') {
                    // Show all
                } else if ($request->stts_aktif === 'NON-AKTIF') {
                    $pegawaiQuery->where('p.stts_aktif', '<>', 'AKTIF');
                } else {
                    $pegawaiQuery->where('p.stts_aktif', $request->stts_aktif);
                }
            } else {
                // Default to AKTIF for search as well if no status specified
                $pegawaiQuery->where('p.stts_aktif', 'AKTIF');
            }

            if ($request->has('filter.departemen')) {
                $dept = $request->input('filter.departemen');
                $pegawaiQuery->where(function($q) use ($dept) {
                    $q->where('d.nama', 'like', "%{$dept}%")
                      ->orWhere('p.departemen', $dept);
                });
            }

            $pegawai = $pegawaiQuery->where(function($q) use ($query) {
                    $q->where('p.nama', 'like', "%{$query}%")
                      ->orWhere('p.nik', 'like', "%{$query}%")
                      ->orWhere('p.jbtn', 'like', "%{$query}%")
                      ->orWhere('p.departemen', 'like', "%{$query}%");
                })
                ->select([
                    'p.nik as id_user',
                    'p.nik',
                    'p.nama',
                    'p.jk',
                    'p.tmp_lahir',
                    'p.tgl_lahir',
                    'p.alamat',
                    'p.pendidikan',
                    'p.no_ktp',
                    'rnk.no_bpjs', // Added
                    'rnk.no_bpjstk', // Added
                    'pt.no_telp',
                    'p.jbtn',
                    'p.departemen',
                    'd.nama as nama_departemen',
                    'p.mulai_kerja',
                    'p.stts_aktif',
                    'p.photo',
                    \DB::raw('COUNT(DISTINCT rkp.no_ktp) as jml_keluarga'), // Added
                    \DB::raw('GROUP_CONCAT(DISTINCT r.id_role SEPARATOR ",") as id_role'),
                    \DB::raw('GROUP_CONCAT(DISTINCT r.nama_role SEPARATOR ", ") as nama_role')
                ])
                ->groupBy('p.nik', 'p.nama', 'p.jk', 'p.tmp_lahir', 'p.tgl_lahir', 'p.alamat', 'p.pendidikan', 'p.no_ktp', 'rnk.no_bpjs', 'rnk.no_bpjstk', 'pt.no_telp', 'p.jbtn', 'p.departemen', 'd.nama', 'p.mulai_kerja', 'p.stts_aktif', 'p.photo')
                ->limit($limit)
                ->get();

            // Transform data to match frontend expectations
            $transformedData = [];
            foreach ($pegawai as $item) {
                $roleIds = $item->id_role ? explode(',', $item->id_role) : [];
                $primaryRoleId = count($roleIds) > 0 ? (int) $roleIds[0] : null;

                $transformedData[] = [
                    'id_user' => $item->id_user,
                    'nik' => $item->nik, // Added for frontend compatibility
                    'nip' => $item->nik,
                    'nama' => $item->nama,
                    'jk' => $item->jk,
                    'tmp_lahir' => $item->tmp_lahir,
                    'tgl_lahir' => $item->tgl_lahir,
                    'alamat' => $item->alamat,
                    'pendidikan' => $item->pendidikan,
                    'no_ktp' => $item->no_ktp,
                    'no_telp' => $item->no_telp,
                    'no_bpjs' => $item->no_bpjs, // Added
                    'no_bpjstk' => $item->no_bpjstk, // Added
                    'username' => $item->nik,
                    'email' => null,
                    'id_role' => $primaryRoleId,
                    'role_name' => $item->nama_role ?: 'Belum ada role',
                    'stts_aktif' => $item->stts_aktif,
                    'status' => $item->stts_aktif === 'AKTIF' ? 1 : 0,
                    'jbtn' => $item->jbtn,
                    'departemen' => $item->nama_departemen ?? $item->departemen,
                    'mulai_kerja' => $item->mulai_kerja,
                    'jml_keluarga' => $item->jml_keluarga ?? 0, // Added
                    'photo' => $item->photo,
                    'created_at' => null,
                    'updated_at' => null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user roles for specific pegawai
     */
    public function getUserRoles($nip)
    {
        try {
            $userRoles = \DB::table('rsia_user_role as ur')
                ->join('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
                ->where('ur.nip', $nip)
                ->where('ur.is_active', 1)
                ->select('ur.nip', 'ur.id_role', 'ur.id_user', 'r.nama_role', 'r.deskripsi')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $userRoles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign role to pegawai
     */
    public function assignRole(Request $request, $nip)
    {
        try {
            $request->validate([
                'access_level_id' => 'required|integer|exists:rsia_role,id_role',
                'user_id' => 'required'
            ]);

            // Check if pegawai exists
            $pegawai = \DB::table('pegawai')->where('nik', $nip)->first();
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pegawai not found'
                ], 404);
            }

            // Check if validation passed - using simple validation now
            
            \DB::beginTransaction();

            // Deactivate ALL existing roles for this user first (enforce single active role)
            \DB::table('rsia_user_role')
                ->where('nip', $nip)
                ->update(['is_active' => 0]);

            // Check if assignment already exists
            $existingAssignment = \DB::table('rsia_user_role')
                ->where('nip', $nip)
                ->where('id_role', $request->access_level_id)
                ->first();

            if ($existingAssignment) {
                // Reactivate
                \DB::table('rsia_user_role')
                    ->where('nip', $nip)
                    ->where('id_role', $request->access_level_id)
                    ->update([
                        'is_active' => 1,
                        'updated_by' => $request->user_id
                    ]);
            } else {
                // Create new assignment
                \DB::table('rsia_user_role')->insert([
                    'nip' => $nip,
                    'id_role' => $request->access_level_id,
                    'id_user' => $nip,  // Fixed: Use target employee's NIP, not logged-in user ID
                    'is_active' => 1,
                    'created_by' => $request->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $sql = "INSERT/UPDATE rsia_user_role SET is_active=1 WHERE nip='{$nip}' AND id_role='{$request->access_level_id}'";
            $this->logTracker($sql, $request);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil ditugaskan ke pegawai (role sebelumnya dinonaktifkan)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove role from pegawai
     */
    public function removeRole($nip, $roleId)
    {
        try {
            $affected = \DB::table('rsia_user_role')
                ->where('nip', $nip)
                ->where('id_role', $roleId)
                ->update([
                    'is_active' => 0,
                    'updated_at' => now()
                ]);

            $sql = "UPDATE rsia_user_role SET is_active=0 WHERE nip='{$nip}' AND id_role='{$roleId}'";
            $this->logTracker($sql, request());

            if ($affected === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Assignment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus dari pegawai'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove role from pegawai
     */
    protected function getMedisEducationList()
    {
        // Cache for 60 minutes to reduce DB load
        return \Illuminate\Support\Facades\Cache::remember('medis_education_list', 60 * 60, function () {
            return \Illuminate\Support\Facades\DB::table('rsia_pendidikan_str')
                ->pluck('kode_tingkat')
                ->toArray();
        });
    }

    /**
     * Get pegawai statistics
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total_pegawai' => \DB::table('pegawai')->where('stts_aktif', 'AKTIF')->count(),
                'active_users' => \DB::table('pegawai')->where('stts_aktif', 'AKTIF')->count(),
                'users_with_roles' => \DB::table('rsia_user_role')
                    ->where('is_active', 1)
                    ->distinct('nip')
                    ->count(),
                'users_without_roles' => \DB::table('pegawai')
                    ->where('stts_aktif', 'AKTIF')
                    ->whereNotIn('nik', function($query) {
                        $query->select('nip')
                              ->from('rsia_user_role')
                              ->where('is_active', 1);
                    })
                    ->count(),
                'total_roles' => \DB::table('rsia_role')->where('is_active', 1)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get simplified pegawai list for dropdown
     */
    protected $medisKeywords = [
        'Dokter', 'Perawat', 'Bidan', 'Apoteker', 'Farmasi', 
        'Analis', 'Radiografer', 'Fisioterapis', 'Gizi', 
        'Sanitarian', 'Perekam Medis', 'Terafis', 'Elektromedis'
    ];

    protected $kelompokRules = [
        'PERAWAT' => ['keywords' => ['Perawat', 'Ners']],
        'BIDAN' => ['keywords' => ['Bidan']],
        'MEDIS' => ['keywords' => ['Dokter']],
        'FARMASI' => ['keywords' => ['Farmasi', 'Apoteker', 'TTK', 'Tenaga Teknis Kefarmasian']],
        'ANALIS' => ['keywords' => ['Analis', 'Laborat', 'ATLM']],
        'GIZI' => ['keywords' => ['Nutrisionis', 'Koordinator Gizi']],
        'SANITARIAN' => ['keywords' => ['Sanitarian', 'Kesehatan Lingkungan', 'Kesling']],
        'TEKNISI ELEKTROMEDIS' => ['keywords' => ['Elektromedis', 'ATEM', 'Teknisi Medis', 'Elektromedik']],
        'RADIOGRAFER' => ['keywords' => ['Radiografer', 'Radiologi']],
        'RM' => ['keywords' => ['Perekam Medis', 'Rekam Medis']],
    ];

    public function list(Request $request)
    {
        $limit = $request->input('limit', 0);
        $offset = $request->input('offset', 0);
        $search = $request->input('search', null);
        
        // Filter params
        $status_kerja = $request->input('stts_kerja', null);
        $jk = $request->input('jk', null);
        $departemen = $request->input('departemen', null);
        $kategori = $request->input('kategori', null); // Jabatan name
        
        $query = Pegawai::query()
            ->with(['dep', 'statusKerja', 'petugas.jabatan']) 
            ->select('nik', 'nama', 'jbtn', 'departemen', 'jk', 'stts_kerja', 'pendidikan')
            ->orderBy('nama', 'ASC') // Default sort by Name
            ->where('stts_aktif', 'AKTIF')
            ->whereHas('petugas', function($q) {
                $q->where('kd_jbtn', '!=', '-');
            });

        // 1. Filter by Gender
        $query->when($request->jk, function ($q, $jk) {
            if ($jk == 'L') {
                return $q->whereIn('jk', ['L', 'LAKI-LAKI', 'PRIA']);
            } elseif ($jk == 'P') {
                return $q->whereIn('jk', ['P', 'PEREMPUAN', 'WANITA']);
            }
            return $q->where('jk', $jk);
        });

        // 2. Filter by Status Kerja (Name or Code)
        $query->when($request->stts_kerja, function ($q, $stts) {
            return $q->whereHas('statusKerja', function($sub) use ($stts) {
                $sub->where('ktg', 'like', "%{$stts}%")
                    ->orWhere('stts', $stts);
            });
        });

        // 3. Filter by Unit Kerja/Departemen (Name or Code)
        $query->when($request->departemen, function ($q, $dept) {
            return $q->whereHas('dep', function($sub) use ($dept) {
                $sub->where('nama', 'like', "%{$dept}%")
                    ->orWhere('dep_id', $dept);
            });
        });

        // 4. Filter by Medis/Non-Medis (EDUCATION BASED)
        if ($request->has('is_medis')) {
            $val = strtolower((string)$request->is_medis);
            $isMedis = ($val === 'true' || $val === '1');
            $medisEducation = $this->getMedisEducationList();

            if ($isMedis) {
                $query->whereIn('pendidikan', $medisEducation);
            } else {
                $query->whereNotIn('pendidikan', $medisEducation);
            }
        }
        
        // 5. Filter by Kelompok Profesi (New)
        if ($request->has('kelompok')) {
            $kelompok = strtoupper($request->kelompok);

            if ($kelompok === 'NON_MEDIS_LAIN') {
                 // SPECIAL CASE: Show Only Non-Medis that are NOT in any other group
                 // 1. Must be Non-Medis by Education
                 $medisEducation = $this->getMedisEducationList();
                 $query->whereNotIn('pendidikan', $medisEducation);

                 // 2. Must NOT match any other Kelompok keywords
                 $query->where(function($sub) {
                     foreach ($this->kelompokRules as $rule) {
                         $keywords = $rule['keywords'];
                         foreach ($keywords as $k) {
                             $sub->where('jbtn', 'not like', "%$k%")
                                 ->whereDoesntHave('petugas.jabatan', function($rel) use ($k) {
                                     $rel->where('nm_jbtn', 'like', "%$k%");
                                 });
                         }
                     }
                 });

            } elseif (isset($this->kelompokRules[$kelompok])) {
                $keywords = $this->kelompokRules[$kelompok]['keywords'];
                $query->where(function($q) use ($keywords, $kelompok) {
                    // 1. Keyword Match
                    foreach ($keywords as $k) {
                        $q->orWhere('jbtn', 'like', "%$k%")
                          ->orWhereHas('petugas.jabatan', function($sub) use ($k) {
                              $sub->where('nm_jbtn', 'like', "%$k%");
                          });
                    }

                    // 2. Education Match (Specific for PERAWAT and BIDAN)
                    if ($kelompok === 'PERAWAT') {
                         $q->orWhere('pendidikan', 'like', '%Keperawatan%')
                           ->orWhere('pendidikan', 'like', '%Ners%');
                    } elseif ($kelompok === 'BIDAN') {
                         $q->orWhere('pendidikan', 'like', '%Kebidanan%')
                           ->orWhere('pendidikan', 'like', '%Bidan%');
                    } elseif ($kelompok === 'MEDIS') {
                         $q->orWhere('pendidikan', 'like', '%Dokter%')
                           ->orWhere('pendidikan', 'like', '%Profesi Dokter%')
                           ->orWhere('pendidikan', 'like', '%S2 Medis%');
                    }
                });
            }
        } elseif ($request->has('pendidikan') && $request->pendidikan) {
            // Filter by Education Level
            $pendidikan = $request->pendidikan;
            $query->where('pendidikan', $pendidikan);
        } elseif ($request->has('kategori') && $request->kategori) {
             // Legacy/Specific drilldown
            $kategori = $request->kategori;
            $query->where('jbtn', 'like', "%{$kategori}%");
        }

        // 6. Search
        $query->when($request->search, function ($q, $search) {
            return $q->where(function($sub) use ($search) {
                $sub->where('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        });

        $limit = (int) $request->input('limit', 500);
        $data = $query->limit($limit)->get();

        $data->transform(function($item) {
            $item->departemen = $item->dep ? $item->dep->nama : ($item->departemen ?: '-');
            $item->stts_kerja = $item->statusKerja ? $item->statusKerja->ktg : ($item->stts_kerja ?: '-');
            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Data Pegawai berhasil diambil',
            'meta' => [
                'filters' => $request->all(),
                'limit' => $limit,
                'count' => $data->count()
            ],
            'data' => $data
        ]);
    }

    public function statistik()
    {
        // Get all active employees with valid petugas position
        $pegawai = Pegawai::where('stts_aktif', 'AKTIF')
            ->whereHas('petugas', function($q) {
                $q->where('kd_jbtn', '!=', '-');
            })
            ->with(['dep', 'statusKerja', 'petugas.jabatan']) // Eager load department & status kerja & jabatan petugas
            ->select('nik', 'jbtn', 'jnj_jabatan', 'jk', 'stts_kerja', 'departemen', 'pendidikan')
            ->get();

        $total = $pegawai->count();
        
        // --- 1. Profesi (Medis vs Non-Medis) BY DEPARTEMEN NAME ---
        // USING EDUCATION BASED LOGIC
        $medisEducation = $this->getMedisEducationList();

        $profesiStats = [
            'medis' => ['total' => 0, 'details' => []],
            'non_medis' => ['total' => 0, 'details' => []]
        ];

        // Grouping
        $medisGroups = [];
        $nonMedisGroups = [];

        foreach ($pegawai as $p) {
            $jabatan = $p->jbtn ?: '';
            // Use Department Name
            $deptName = $p->dep ? $p->dep->nama : ($p->departemen ?: 'Lain-lain');

            // Check Medis based on Education
            $isMedis = in_array($p->pendidikan, $medisEducation);

            if ($isMedis) {
                $profesiStats['medis']['total']++;
                if (!isset($medisGroups[$deptName])) $medisGroups[$deptName] = 0;
                $medisGroups[$deptName]++;
            } else {
                $profesiStats['non_medis']['total']++;
                if (!isset($nonMedisGroups[$deptName])) $nonMedisGroups[$deptName] = 0;
                $nonMedisGroups[$deptName]++;
            }
        }
        
        // Format Details
        foreach ($medisGroups as $dept => $count) {
            $profesiStats['medis']['details'][] = ['name' => $dept, 'count' => $count];
        }
        foreach ($nonMedisGroups as $dept => $count) {
            $profesiStats['non_medis']['details'][] = ['name' => $dept, 'count' => $count];
        }
        
        // Sorting
        usort($profesiStats['medis']['details'], fn($a, $b) => $b['count'] <=> $a['count']);
        usort($profesiStats['non_medis']['details'], fn($a, $b) => $b['count'] <=> $a['count']);


        // --- 2. Gender (L/P) ---
        $genderStats = $pegawai->groupBy(function($item) {
            $jk = strtoupper(trim($item->jk));
            if (in_array($jk, ['L', 'LAKI-LAKI', 'PRIA'])) return 'L';
            if (in_array($jk, ['P', 'PEREMPUAN', 'WANITA'])) return 'P';
            return 'OTHER';
        })->map(function ($group) {
            return $group->count();
        });

        // Rename keys
        $genderFormatted = [
            ['name' => 'Laki-laki', 'code' => 'L', 'count' => $genderStats['L'] ?? 0],
            ['name' => 'Perempuan', 'code' => 'P', 'count' => $genderStats['P'] ?? 0]
        ];


        // --- 3. Status Kerja ---
        // Group by Status Kerja Name (ktg)
        $statusKerjaGroups = $pegawai->groupBy(function($item) {
            return $item->statusKerja ? $item->statusKerja->ktg : ($item->stts_kerja ?: 'Lain-lain');
        })->map(function ($group) {
            return $group->count();
        })->sortDesc();
        
        $statusKerjaFormatted = [];
        foreach ($statusKerjaGroups as $name => $count) {
            $statusKerjaFormatted[] = ['name' => $name, 'count' => $count];
        }


        // --- 4. Unit Kerja (Departemen) ---
        // Group by Department Name
        $unitKerjaGroups = $pegawai->groupBy(function($item) {
            return $item->dep ? $item->dep->nama : ($item->departemen ?: 'Lain-lain');
        })->map(function ($group) {
            return $group->count();
        })->sortDesc();

        $unitKerjaFormatted = [];
        foreach ($unitKerjaGroups as $name => $count) {
            $unitKerjaFormatted[] = ['name' => $name, 'count' => $count];
        }


        // --- 5. Pendidikan (Education Level) ---
        $pendidikanGroups = $pegawai->groupBy(function($item) {
            return $item->pendidikan ?: 'Tidak Diketahui';
        })->map(function ($group) {
            return $group->count();
        })->sortDesc();

        $pendidikanFormatted = [];
        foreach ($pendidikanGroups as $name => $count) {
            $pendidikanFormatted[] = ['name' => $name, 'count' => $count];
        }


        // --- 6. Kelompok Profesi (Based on Reference) ---
        $kelompokStats = [
            'NON MEDIS' => ['count' => 0, 'filter_key' => 'kelompok', 'filter_val' => 'NON_MEDIS_LAIN']
        ];
        
        // Initialize counts
        foreach ($this->kelompokRules as $key => $rule) {
            $kelompokStats[$key] = [
                'count' => 0, 
                'filter_key' => 'kelompok', 
                'filter_val' => $key
            ];
        }

        foreach ($pegawai as $p) {
            $jabatan = $p->jbtn ?: '';
            
            // Get Jabatan from Petugas if available
            $jabatanPetugas = ($p->petugas && $p->petugas->jabatan) ? $p->petugas->jabatan->nm_jbtn : '';

            $matched = false;

            foreach ($this->kelompokRules as $key => $rule) {
                // Check Keywords
                foreach ($rule['keywords'] as $k) {
                    if (stripos($jabatan, $k) !== false || stripos($jabatanPetugas, $k) !== false) {
                        $kelompokStats[$key]['count']++;
                        $matched = true;
                        break 2;
                    }
                }

                // Check Education (Specific for PERAWAT and BIDAN)
                if ($key === 'PERAWAT') {
                    if (stripos($p->pendidikan, 'Keperawatan') !== false || stripos($p->pendidikan, 'Ners') !== false) {
                         $kelompokStats[$key]['count']++;
                         $matched = true;
                         break;
                    }
                } elseif ($key === 'BIDAN') {
                    if (stripos($p->pendidikan, 'Kebidanan') !== false || stripos($p->pendidikan, 'Bidan') !== false) {
                         $kelompokStats[$key]['count']++;
                         $matched = true;
                         break;
                    }
                } elseif ($key === 'MEDIS') {
                    if (stripos($p->pendidikan, 'Dokter') !== false || stripos($p->pendidikan, 'Profesi Dokter') !== false || stripos($p->pendidikan, 'S2 Medis') !== false) {
                         $kelompokStats[$key]['count']++;
                         $matched = true;
                         break;
                    }
                }
            }

            if (!$matched) {
                // Check Medis based on Education
                $isMedis = in_array($p->pendidikan, $medisEducation);
                
                if (!$isMedis) {
                    $kelompokStats['NON MEDIS']['count']++;
                } else {
                    // Falls into 'Medis Lainnya' if we had a category for it
                }
            }
        }

        // Format for response
        $kelompokFormatted = [];
        foreach ($kelompokStats as $name => $data) {
            if ($data['count'] > 0) {
                $kelompokFormatted[] = [
                    'name' => $name,
                    'count' => $data['count'],
                    'filter_key' => $data['filter_key'],
                    'filter_val' => $data['filter_val']
                ];
            }
        }
        
        // Sort: Medis types explicitly then others? Or just count?
        // Let's sort by count desc
        usort($kelompokFormatted, fn($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'success' => true,
            'message' => 'Statistik Pegawai berhasil diambil',
            'data' => $stats = [ // Assign to variable for return 
                'total' => $total,
                'profesi' => $profesiStats,
                'gender' => $genderFormatted,
                'status_kerja' => $statusKerjaFormatted,
                'unit_kerja' => $unitKerjaFormatted,
                'pendidikan' => $pendidikanFormatted,
                'kelompok_profesi' => $kelompokFormatted
            ]
        ]);
    }

    public function tanpaEmail()
    {
        try {
            $pegawai = \DB::table('pegawai as p')
                ->leftJoin('rsia_email_pegawai as rep', 'rep.nik', '=', 'p.nik')
                ->leftJoin('departemen as d', 'd.dep_id', '=', 'p.departemen')
                ->join('petugas as pt', 'pt.nip', '=', 'p.nik')
                ->where('p.stts_aktif', 'AKTIF')
                ->where('pt.kd_jbtn', '!=', '-')
                ->where(function($q) {
                    $q->whereNull('rep.email')
                      ->orWhere('rep.email', '')
                      ->orWhere('rep.email', '-')
                      ->orWhere('rep.email', 'NOT REGEXP', '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$');
                })
                ->select([
                    'p.nik',
                    'p.nama',
                    'd.nama as nama_departemen',
                    'rep.email'
                ])
                ->get();

            $transformedData = [];
            foreach ($pegawai as $item) {
                $transformedData[] = [
                    'nik' => $item->nik,
                    'nama' => $item->nama,
                    'departemen' => $item->nama_departemen,
                    'email' => $item->email ?? '(Belum Terdaftar)'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $transformedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateEmail(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'email' => 'required|email'
        ]);

        try {
            $email = \App\Models\RsiaEmailPegawai::updateOrCreate(
                ['nik' => $request->nik],
                ['email' => $request->email]
            );

            return response()->json([
                'success' => true,
                'message' => 'Email pegawai berhasil diperbarui',
                'data' => $email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
