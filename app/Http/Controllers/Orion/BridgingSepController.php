<?php

namespace App\Http\Controllers\Orion;

use Illuminate\Http\Request;

class BridgingSepController extends \Orion\Http\Controllers\Controller
{
    /**
     * The model class name used in the controller.
     *
     * @var string
     */
    use \Orion\Concerns\DisableAuthorization;

    /**
     * The model class name used in the controller.
     *
     * @var string
     */
    protected $model = \App\Models\BridgingSep::class;

    /**
     * Override the index method to handle ERM requests with pagination
     */
    public function index(Request $request)
    {
        // Increase memory limit for this operation
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes timeout

        // Check if this is an ERM request
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');
        $isFromErmPage = false;

        // Simple ERM detection
        if ($userAgent && (strpos($userAgent, 'nuxt') !== false || strpos($userAgent, 'node') !== false)) {
            $isFromErmPage = true;
        }

        if ($referer && strpos($referer, '/erm/') !== false) {
            $isFromErmPage = true;
        }

        // For ERM requests, force pagination to prevent memory issues
        if ($isFromErmPage) {
            // Check if pagination parameters exist, if not add them
            if (!$request->has('page')) {
                $request->merge(['page' => 1]);
            }

            if (!$request->has('limit')) {
                $request->merge(['limit' => 10]); // Reduce to 10 per page for ERM to prevent memory issues
            }

            // Jika tidak ada 'with' parameter, gunakan default includes
            // JANGAN timpa yang sudah ada dari Vue request
            if (!$request->has('with')) {
                $request->merge(['with' => 'reg_periksa,pasien,reg_periksa.poliklinik,reg_periksa.dokter']);
            }
        }

        // Use parent::index() with error wrapper to catch the actual error
        try {
            // Log debugging untuk memastikan includes terload dengan benar
            if ($isFromErmPage) {
                error_log("ERM Request - Includes: " . json_encode($request->get('with', 'default')));
            }
            return parent::index($request);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("ERM Request Error: " . $e->getMessage());
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
            error_log("Request Data: " . json_encode($request->all()));

            // Check for common errors
            if (strpos($e->getMessage(), 'memory') !== false || strpos($e->getMessage(), 'Allowed memory size') !== false) {
                return response()->json([
                    'message' => 'Memory limit exceeded - Query returned too much data',
                    'error' => 'Please use filters, reduce page size, or specify specific relationships only.',
                    'code' => 'MEMORY_ERROR',
                    'suggestion' => 'Try adding ?limit=5&with=reg_periksa to your request',
                    'details' => $e->getMessage()
                ], 500);
            }

            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                return response()->json([
                    'message' => 'Database column error',
                    'error' => $e->getMessage(),
                    'code' => 'COLUMN_ERROR'
                ], 500);
            }

            // Generic error response
            return response()->json([
                'message' => 'Error processing request',
                'error' => $e->getMessage(),
                'code' => 'UNKNOWN_ERROR',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Retrieves currently authenticated user based on the guard.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function resolveUser()
    {
        return \Illuminate\Support\Facades\Auth::guard('user-aes')->user();
    }

    public function exposedScopes(): array
    {
        return [
            'withLos',
            'hasBerkasPerawatan',
            'notHasBerkasPerawatan',
            'notHasStatusKlaim',
            'selectColumns',
        ];
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    public function sortableBy(): array
    {
        return [
            'tglsep',
            'no_rawat',
            'tglrujukan',
            'nama_pasien',
            'status_klaim.status',
            'reg_periksa.tgl_registrasi',
            'reg_periksa.jam_reg',
            'kamar_inap.tgl_keluar',
            'kamar_inap.jam_keluar',
            'tanggal_pulang.tgl_keluar'
        ];
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return [
            'nomr',
            'tglsep',
            'no_sep',
            'no_rawat',
            'no_kartu',
            'klsrawat',
            'nama_pasien',
            'jnspelayanan',
            'status_klaim.status',
            'groupStage.code_cbg',
            'reg_periksa.kd_poli',
            'reg_periksa.tgl_registrasi',
            'kamar_inap.tgl_keluar',
            'kamar_inap.jam_keluar',
            'kamar_inap.stts_pulang',
            'tanggal_pulang.tgl_keluar'
        ];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return [
            'nomr',
            'no_sep',
            'no_rawat',
            'no_kartu',
            'klsrawat',
            'nama_pasien',
            'dokter.nm_dokter',
            'poliklinik.nm_poli'
        ];
    }

    /**
     * The relations that are used for including.
     *
     * @return array
     * */
    public function includes(): array
    {
        return [
            'chunk',
            'pasien',
            'kamar_inap',
            'groupStage',
            'status_klaim',
            'tanggal_pulang',
            'berkasPerawatan',
            'terkirim_online',
            'status_klaim.log',
            'rsia_klaim_idrg',
            'inacbgDataTerkirim',
            'reg_periksa',
            'reg_periksa.dokter',
            'reg_periksa.poliklinik',
            'reg_periksa.dokter.spesialis',
            // Tambahkan alias untuk compatibility
            'regPeriksa',
            'regPeriksa.dokter',
            'regPeriksa.poliklinik',
            'regPeriksa.dokter.spesialis',
        ];
    }
}