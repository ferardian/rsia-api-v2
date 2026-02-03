<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\MappingDokterBpjs;
use App\Services\BpjsVclaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BpjsVclaimController extends Controller
{
    protected $vclaimService;

    public function __construct(BpjsVclaimService $vclaimService)
    {
        $this->vclaimService = $vclaimService;
    }

    /**
     * Get all mapped doctors
     */
    public function indexMapping()
    {
        $mappings = MappingDokterBpjs::with('dokter')->get();
        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $mappings
        ]);
    }

    /**
     * Get reference doctors from VClaim
     */
    public function getRefDokter(Request $request)
    {
        $request->validate([
            'pelayanan' => 'required',
            'tgl' => 'required|date_format:Y-m-d',
            'spesialis' => 'required'
        ]);

        $endpoint = "/referensi/dokter/pelayanan/{$request->pelayanan}/tglPelayanan/{$request->tgl}/Spesialis/{$request->spesialis}";
        $response = $this->vclaimService->get($endpoint);

        return response()->json($response);
    }

    /**
     * Get participant data by card number
     * {Base URL}/{Service Name}/Peserta/nokartu/{parameter 1}/tglSEP/{parameter 2}
     */
    public function getPesertaByNoKartu($no_kartu, $tgl_sep)
    {
        $endpoint = "/Peserta/nokartu/{$no_kartu}/tglSEP/{$tgl_sep}";
        $response = $this->vclaimService->get($endpoint);
        return response()->json($response);
    }

    /**
     * Get participant data by NIK
     * {Base URL}/{Service Name}/Peserta/nik/{parameter 1}/tglSEP/{parameter 2}
     */
    public function getPesertaByNik($nik, $tgl_sep)
    {
        $endpoint = "/Peserta/nik/{$nik}/tglSEP/{$tgl_sep}";
        $response = $this->vclaimService->get($endpoint);
        return response()->json($response);
    }

    /**
     * Store mapping
     */
    public function storeMapping(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required',
            'kd_dokter_bpjs' => 'required',
            'nm_dokter_bpjs' => 'required',
        ]);

        try {
            $mapping = MappingDokterBpjs::updateOrCreate(
                ['kd_dokter' => $request->kd_dokter],
                [
                    'kd_dokter_bpjs' => $request->kd_dokter_bpjs,
                    'nm_dokter_bpjs' => $request->nm_dokter_bpjs,
                ]
            );

            return response()->json([
                'metadata' => ['code' => 200, 'message' => 'Mapping saved successfully'],
                'response' => $mapping
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving mapping: " . $e->getMessage());
            return response()->json([
                'metadata' => ['code' => 500, 'message' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Delete mapping
     */
    public function destroyMapping($kd_dokter)
    {
        try {
            MappingDokterBpjs::where('kd_dokter', $kd_dokter)->delete();
            return response()->json([
                'metadata' => ['code' => 200, 'message' => 'Mapping deleted successfully']
            ]);
        } catch (\Exception $e) {
            Log::error("Error deleting mapping: " . $e->getMessage());
            return response()->json([
                'metadata' => ['code' => 500, 'message' => $e->getMessage()]
            ], 500);
        }
    }
}
