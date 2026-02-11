<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The phone number to send the OTP
     * 
     * @var string
     * */
    protected $noHp;

    /**
     * The OTP code
     * 
     * @var string
     * */
    protected $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(String $noHp, String $message)
    {
        $this->noHp = $noHp;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Get WhatsApp API configuration from config (mapped from .env)
        $apiUrl = config('services.whatsapp.url');
        $apiKey = config('services.whatsapp.key');
        $sessionName = config('services.whatsapp.session');
        
        // Format phone number (remove leading 0, add 62 for Indonesia)
        $phone = $this->noHp;
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        try {
            // WAHA API format - using /api/sendText endpoint
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("$apiUrl/api/sendText", [
                'session' => $sessionName,
                'chatId' => $phone . '@c.us',
                'text' => $this->message
            ]);

            if ($response->successful()) {
                \App\Helpers\Logger\RSIALogger::notifications("OTP sent to $this->noHp via WAHA/Sumopod");
            } else {
                \App\Helpers\Logger\RSIALogger::notifications(
                    "Failed to send OTP to $this->noHp. Status: " . $response->status() . " Response: " . $response->body(), 
                    'error'
                );
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::notifications(
                "Exception sending OTP to $this->noHp: " . $e->getMessage(), 
                'error'
            );
        }
    }
}
