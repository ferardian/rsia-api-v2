<?php

$url = "http://localhost:8010/api/v2/pasien/auth/register/cek-nik";
$nik = "1234567890123456";

echo "🧪 [RATE LIMIT TEST] Starting test for $url\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

for ($i = 1; $i <= 7; $i++) {
    $ch = curl_init($url);
    
    $jsonData = json_encode(['nik' => $nik]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    echo "Request #$i: Status $httpCode\n";
    
    if ($httpCode == 429) {
        echo "✅ [SUCCESS] Rate limit (429) was triggered as expected on request #$i.\n";
        break;
    }
    
    usleep(200000); // 0.2s delay between requests
}

if ($httpCode != 429) {
    echo "❌ [FAILED] Rate limit was NOT triggered after 7 requests.\n";
}
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
