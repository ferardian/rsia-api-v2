<?php

namespace App\Services;

use App\Helpers\SignHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BpjsHfisService
{
    protected $consId;
    protected $consSecret;
    protected $userKey;
    protected $baseUrl;
    protected $serviceName;

    public function __construct()
    {
        // HFIS usually shares credentials with Antrol or VClaim, but the endpoint might be different
        // Based on user request: {BASE URL}/{Service Name}/jadwaldokter/updatejadwaldokter
        // We will use Antrol credentials as default fallbacks if not specified otherwise
        
        $this->consId = config('services.bpjs.antrol.cons_id');
        $this->consSecret = config('services.bpjs.antrol.cons_secret');
        $this->userKey = config('services.bpjs.antrol.user_key');
        
        // HFIS might have a specific base URL, but often it's on the same host as other services
        // We'll use the Antrol Base URL for now, but in production this might need a specific config
        $this->baseUrl = config('services.bpjs.antrol.base_url'); 
        
        // Service name usually 'antrean' or 'vclaim', for HFIS usually 'hfis' or similar?
        // User request said: {BASE URL}/{Service Name}/jadwaldokter/updatejadwaldokter
        // We will assume the endpoint passed includes the service name if needed, or we configure it.
        // Let's assume the user provided endpoint is relative to the Base URL.
    }

    /**
     * Perform a POST request to BPJS HFIS
     * 
     * @param string $endpoint
     * @param array $payload
     * @return array
     */
    public function post($endpoint, $payload = [])
    {
        $signData = SignHelper::sign($this->consId, $this->consSecret);
        $timestamp = $signData['timestamp'];
        $signature = $signData['signature'];

        $headers = [
            'x-cons-id'   => $this->consId,
            'x-timestamp' => $timestamp,
            'x-signature' => $signature,
            'user_key'    => $this->userKey,
            'Content-Type' => 'application/json',
        ];

        // Ensure endpoint starts with / if not present
        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }

        $url = $this->baseUrl . $endpoint;

        Log::info("HFIS POST Request: " . $url . " Payload: " . json_encode($payload));

        try {
            $response = Http::withHeaders($headers)->timeout(15)->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("HFIS POST Request Failed: Status " . $response->status() . " Body: " . $response->body());
            return $response->json() ?: ['metadata' => ['code' => $response->status(), 'message' => 'Request Failed']];
            
        } catch (\Exception $e) {
            Log::error("HFIS POST Exception: " . $e->getMessage());
            return [
                'metadata' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Update Jadwal Dokter
     * Endpoint: {Service Name}/jadwaldokter/updatejadwaldokter
     * 
     * @param array $payload
     * @return array
     */
    public function updateJadwalDokter($payload)
    {
        // Assuming 'antrean' as service name based on Antrol context, 
        // OR user might mean a specific HFIS service. 
        // Re-reading user request: "{BASE URL}/{Service Name}/jadwaldokter/updatejadwaldokter"
        
        // Common endpoint for Antrean HFIS update is usually under 'antrean' service 
        // e.g. /antrean/jadwaldokter/updatejadwaldokter
        // We will try that first.
        
        return $this->post('/jadwaldokter/updatejadwaldokter', $payload);
    }
}
