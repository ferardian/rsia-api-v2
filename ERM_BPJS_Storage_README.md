# Penyimpanan Data ERM BPJS

## Overview

Fitur baru ini ditambahkan untuk menyimpan data Electronic Medical Record (ERM) yang dikirim ke BPJS, termasuk:

1. **Bundle FHIR asli** sebelum enkripsi dan kompresi
2. **Response dari BPJS** setelah API call
3. **Metadata lengkap** untuk debugging dan audit

## Struktur Database

Tabel `rsia_erm_bpjs`:
```sql
CREATE TABLE `rsia_erm_bpjs` (
  `nosep` varchar(40) NOT NULL,
  `erm_request` longtext DEFAULT NULL,
  `erm_response` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`nosep`),
  CONSTRAINT `rsia_erm_bpjs_bridging_sep_FK` FOREIGN KEY (`nosep`) REFERENCES `bridging_sep` (`no_sep`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
```

## Struktur Data

### erm_request
```json
{
  "bundle": {
    // Bundle FHIR lengkap (belum dikompres/enkripsi)
  },
  "metadata": {
    "jnsPelayanan": "1",
    "bulan": "12",
    "tahun": "2024",
    "bundle_entries_count": 15,
    "bundle_type": "Bundle",
    "user_agent": "Mozilla/5.0...",
    "ip_address": "192.168.1.100"
  },
  "timestamp": "2024-12-04T10:30:00.000Z"
}
```

### erm_response
```json
{
  "response": {
    // Response dari BPJS API
  },
  "metadata": {
    "http_status": 200,
    "response_headers": {...},
    "request_metadata": {
      "service_url": "https://api.bpjs-kesehatan.go.id/...",
      "timestamp": "1701646200",
      "payload_size": 2048,
      "is_retry": false
    },
    "processing_time": 2.45
  },
  "timestamp": "2024-12-04T10:30:02.000Z"
}
```

## API Endpoints

### 1. Simpan ERM (otomatis saat insertMedicalRecord)
```
POST /api/v2/bpjs/rekammedis/insert
```

### 2. Mendapatkan data ERM lengkap
```
GET /api/v2/bpjs/erm/{noSep}
```

### 3. Mendapatkan bundle FHIR asli
```
GET /api/v2/bpjs/erm/{noSep}/bundle
```

### 4. Mendapatkan response BPJS
```
GET /api/v2/bpjs/erm/{noSep}/response
```

## Penggunaan Model

### Menyimpan data ERM
```php
use App\Models\RsiaErmBpjs;

// Simpan request saja
RsiaErmBpjs::saveErmRequest($noSep, $fhirBundle, $metadata);

// Simpan response saja
RsiaErmBpjs::saveErmResponse($noSep, $bpjsResponse, $metadata);

// Simpan keduanya
RsiaErmBpjs::saveErmData($noSep, $fhirBundle, $bpjsResponse, $requestMetadata, $responseMetadata);
```

### Mengakses data
```php
$ermData = RsiaErmBpjs::where('nosep', $noSep)->first();

// Bundle FHIR asli
$bundle = $ermData->bundle;

// Response BPJS
$response = $ermData->response;

// Metadata
$requestMetadata = $ermData->request_metadata;
$responseMetadata = $ermData->response_metadata;

// Relasi ke SEP
$sepData = $ermData->sep;
```

## Fitur Debugging

1. **Complete Audit Trail**: Setiap request dan response tersimpan lengkap
2. **Before/After Comparison**: Bundle asli vs response yang diterima
3. **Performance Monitoring**: Processing time, payload size, HTTP status
4. **Error Tracking**: Metadata error untuk troubleshooting
5. **Retry Detection**: Menandai jika ada retry attempt

## Keamanan

- Data tersimpan dengan format JSON terstruktur
- Automatic foreign key constraint ke `bridging_sep`
- Timestamp untuk tracking
- Metadata lengkap untuk audit

## Manfaat

1. **Debugging**: Mudah trace error dengan melihat bundle asli yang dikirim
2. **Audit**: Complete log untuk compliance
3. **Recovery**: Bisa resend data jika diperlukan
4. **Analysis**: Analisis performance dan error pattern
5. **Testing**: Mock response untuk development testing

## Contoh Response

### GET /api/v2/bpjs/erm/123456789
```json
{
  "message": "Data ERM ditemukan",
  "data": {
    "nosep": "123456789",
    "erm_request": {
      "bundle": {...},
      "metadata": {...},
      "timestamp": "2024-12-04T10:30:00.000Z"
    },
    "erm_response": {
      "response": {...},
      "metadata": {...},
      "timestamp": "2024-12-04T10:30:02.000Z"
    },
    "created_at": "2024-12-04T10:30:00.000Z",
    "updated_at": "2024-12-04T10:30:02.000Z"
  }
}
```

## Error Handling

Semua operasi penyimpanan menggunakan try-catch untuk memastikan proses BPJS tetap berjalan meskipun penyimpanan ke database gagal. Error akan di-log ke Laravel log system.