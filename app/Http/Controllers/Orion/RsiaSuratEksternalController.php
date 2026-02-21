<?php

namespace App\Http\Controllers\Orion;

use Illuminate\Http\Request;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class RsiaSuratEksternalController extends Controller
{
    use DisableAuthorization;

    /**
     * Fully-qualified model class name
     */
    protected $model = \App\Models\RsiaSuratEksternal::class;

    /**
     * Request class for the current resource
     * 
     * @var string $request
     */
    protected $request = \App\Http\Requests\SuratEksternalRequest::class;

    /**
     * The relations that are allowed to be included together with a resource.
     * 
     * @param Request $request
     * @param array $requestedRelations
     * @return \Illuminate\Database\Eloquent\Builder 
     */
    protected function buildIndexFetchQuery(Request $request, array $requestedRelations): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::buildIndexFetchQuery($request, $requestedRelations);

        if ($request->has('departemen') && $request->departemen !== '' && $request->departemen !== '-') {
            $query->whereHas('penanggungJawab', function ($q) use ($request) {
                $q->where('departemen', $request->departemen);
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Fills attributes on the given entity and stores it in database.
     *
     * @param \Orion\Http\Requests\Request $request
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @param array $attributes
     */
    protected function performStore(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity, array $attributes): void
    {
        $suratData = [
            'perihal'      => $request->perihal,
            'alamat'       => $request->alamat,
            'tgl_terbit'   => $request->tgl_terbit,
            'pj'           => $request->pj,
            'status'       => $request->status ?? "pengajuan",
            'requested_by' => $this->resolveUser()->id_user,
        ];

        if ($request->status == 'disetujui') {
            $lastNomor = \App\Models\RsiaSuratEksternal::select('no_surat')
                ->orderBy('created_at', 'desc')
                ->where('no_surat', '<>', null)
                ->whereYear('tgl_terbit', \Carbon\Carbon::parse($request->tgl_terbit)->year)
                ->first();

            if ($lastNomor) {
                $n = explode('/', $lastNomor->no_surat);
                $n[0] = str_pad($n[0] + 1, 3, '0', STR_PAD_LEFT);
                $n[3] = \Carbon\Carbon::parse($request->tgl_terbit)->format('dmy');
                $n = implode('/', $n);
            } else {
                $n = '001/B/S-RSIA' . \Carbon\Carbon::parse($request->tgl_terbit)->format('dmy');
            }

            $suratData['no_surat'] = $n;
            $suratData['verified_at'] = now();
        }

        $this->performFill($request, $entity, $suratData);
        $entity->save();
    }

    /**
     * Fills attributes on the given entity and persists changes in database.
     *
     * @param Request $request
     * @param Model $entity
     * @param array $attributes
     */
    protected function performUpdate(Request $request, \Illuminate\Database\Eloquent\Model $e, array $attributes): void
    {
        $suratData = [
            'perihal'      => $request->perihal,
            'alamat'       => $request->alamat,
            'tgl_terbit'   => $request->tgl_terbit,
            'pj'           => $request->pj,
            'status'       => $request->status ?? "pengajuan",
            'requested_by' => $this->resolveUser()->id_user,
            'verified_at' => $request->status == 'disetujui' ? now() : null,
        ];

        if ($request->status == 'disetujui' && !$e->no_surat) {
            $lastNomor = \App\Models\RsiaSuratEksternal::select('no_surat')
                ->orderBy('created_at', 'desc')
                ->where('no_surat', '<>', null)
                ->whereYear('tgl_terbit', \Carbon\Carbon::parse($request->tgl_terbit)->year)
                ->first();

            if ($lastNomor) {
                $n = explode('/', $lastNomor->no_surat);
                $n[0] = str_pad($n[0] + 1, 3, '0', STR_PAD_LEFT);
                $n[3] = \Carbon\Carbon::parse($request->tgl_terbit)->format('dmy');
                $n = implode('/', $n);
            } else {
                $n = '001/B/S-RSIA' . \Carbon\Carbon::parse($request->tgl_terbit)->format('dmy');
            }

            $suratData['no_surat'] = $n;
            $suratData['verified_at'] = now();
        }

        if (!$e->requested_by) {
            $suratData['requested_by'] = $this->resolveUser()->id_user;
        }

        $this->performFill($request, $e, $suratData);
        $e->save();

        if ($suratData['status'] == 'pengajuan') {
            $this->sendNotificationToKoordinatorDiklat($request);
        }

        if ($suratData['status'] == 'disetujui') {
            $this->sendNotificationToPJ($request, $e->no_surat ?? $suratData['no_surat']);
        }
    }

    /**
     * Send WhatsApp notification to Koordinator Diklat
     */
    private function sendNotificationToKoordinatorDiklat(Request $request)
    {
        try {
            $pikRole = \App\Models\RsiaRole::where('nama_role', 'Koordinator Diklat')->first();
            if (!$pikRole) return;

            $pj = \App\Models\Pegawai::where('nik', $request->pj)->first();
            $pjName = $pj ? $pj->nama : $request->pj;

            $pics = \App\Models\RsiaUserRole::where('id_role', $pikRole->id_role)
                ->where('is_active', 1)
                ->with('petugas')
                ->get();

            foreach ($pics as $pic) {
                if ($pic->petugas && $pic->petugas->no_telp) {
                    $no_telp = $pic->petugas->no_telp;
                    if (str_starts_with($no_telp, '0')) $no_telp = '62' . substr($no_telp, 1);

                    $msg = "📢 *Pemberitahuan Pengajuan Surat EKSTERNAL*\n\n";
                    $msg .= "Terdapat pengajuan surat eksternal baru yang memerlukan persetujuan.\n";
                    $msg .= "------------------------------------\n";
                    $msg .= "📌 *Perihal* : " . $request->perihal . "\n";
                    $msg .= "🏠 *Alamat* : " . $request->alamat . "\n";
                    $msg .= "👤 *PJ/Pengaju* : " . $pjName . "\n";
                    $msg .= "📅 *Tgl Terbit* : " . \Carbon\Carbon::parse($request->tgl_terbit)->translatedFormat('d F Y') . "\n";
                    $msg .= "------------------------------------\n";
                    $msg .= "Mohon segera lakukan pengecekan pada aplikasi RSIAP V2. Terima kasih 🙏";

                    \App\Jobs\SendWhatsApp::dispatch($no_telp, $msg);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Gagal mengirim notifikasi WA pengajuan surat eksternal: ' . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp notification to PJ (Penanggung Jawab)
     */
    private function sendNotificationToPJ(Request $request, $nomorSurat)
    {
        try {
            $pj = \App\Models\Petugas::where('nip', $request->pj)->first();
            if (!$pj || !$pj->no_telp) return;

            $no_telp = $pj->no_telp;
            if (str_starts_with($no_telp, '0')) $no_telp = '62' . substr($no_telp, 1);

            $msg = "📢 *Surat Eksternal Telah Disetujui*\n\n";
            $msg .= "Pengajuan surat eksternal Anda telah disetujui dan nomor surat telah terbit.\n";
            $msg .= "------------------------------------\n";
            $msg .= "📌 *Perihal* : " . $request->perihal . "\n";
            $msg .= "🔢 *No. Surat* : " . $nomorSurat . "\n";
            $msg .= "📅 *Tgl Terbit* : " . \Carbon\Carbon::parse($request->tgl_terbit)->translatedFormat('d F Y') . "\n";
            $msg .= "------------------------------------\n";
            $msg .= "Silakan cek detailnya pada aplikasi RSIAP V2. Terima kasih 🙏";

            \App\Jobs\SendWhatsApp::dispatch($no_telp, $msg);
        } catch (\Exception $e) {
            \Log::error('Gagal mengirim notifikasi WA approval eksternal ke PJ: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics for external letters.
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        $query = \App\Models\RsiaSuratEksternal::selectRaw('status, COUNT(*) as count');

        if ($request->has('departemen') && $request->departemen !== '' && $request->departemen !== '-') {
            $query->whereHas('penanggungJawab', function ($q) use ($request) {
                $q->where('departemen', $request->departemen);
            });
        }

        $stats = $query->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => array_sum($stats),
                'pengajuan' => $stats['pengajuan'] ?? 0,
                'disetujui' => $stats['disetujui'] ?? 0,
                'ditolak' => $stats['ditolak'] ?? 0,
            ]
        ]);
    }

    /**
     * Retrieves currently authenticated user based on the guard.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function resolveUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return ['tanggal', 'status', 'no_surat', 'pj'];
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    public function sortableBy(): array
    {
        return ['penanggungJawab.nama', 'pj', 'alamat', 'no_surat', 'created_at'];
    }

    /**
     * The relations that are always included together with a resource.
     *
     * @return array
     */
    public function alwaysIncludes(): array
    {
        return ['penanggungJawab'];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     *
     * @return array
     */
    public function includes(): array
    {
        return ['penanggungJawab'];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return ['perihal', 'alamat', 'penanggungJawabSimple.nama'];
    }
}
