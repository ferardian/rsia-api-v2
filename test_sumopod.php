<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "🧪 Testing Sumopod WhatsApp API...\n\n";

$apiUrl = env('WAHA_BASE_URL', env('API_WHATSAPP_URL'));
$apiKey = env('WHATSAPP_API_KEY');
$sessionName = env('WAHA_DASHBOARD_USERNAME', 'default');
$testPhone = '6285290272706'; // Format: 62xxx

echo "📍 API URL: $apiUrl\n";
echo "🔑 API Key: " . substr($apiKey, 0, 10) . "...\n";
echo "📱 Phone: $testPhone\n";
echo "🎯 Session: $sessionName\n\n";

try {
    $response = Http::withHeaders([
        'X-Api-Key' => $apiKey,
        'Content-Type' => 'application/json'
    ])->post("$apiUrl/api/sendText", [
        'session' => $sessionName,
        'chatId' => $testPhone . '@c.us',
        'text' => "Test OTP dari RSIA: 123456\n\nIni adalah pesan test."
    ]);

    echo "✅ Response Status: " . $response->status() . "\n";
    echo "📦 Response Body:\n";
    echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n\n";

    if ($response->successful()) {
        echo "✅ SUCCESS! Message sent to WhatsApp\n";
    } else {
        echo "❌ FAILED! Check response above\n";
    }
} catch (\Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}
