# SQL Tracking Best Practice

## Menggunakan Trait `LogsToTracker`

Untuk menambahkan SQL tracking di controller lain, gunakan trait `LogsToTracker`:

### 1. Import dan Use Trait

```php
<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Traits\LogsToTracker;  // Import trait
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    use LogsToTracker;  // Use trait di controller
    
    // Your methods here...
}
```

### 2. Panggil Method `logTracker`

Di method store/update/delete, panggil `$this->logTracker($sql, $request)`:

```php
public function store(Request $request)
{
    $validated = $request->validate([...]);
    
    $model = Model::create($validated);
    
    // Log to trackersql
    $sql = "INSERT INTO table_name VALUES ('{$validated['field1']}', '{$validated['field2']}')";
    $this->logTracker($sql, $request);
    
    return response()->json(['success' => true]);
}

public function update(Request $request, $id)
{
    $model = Model::findOrFail($id);
    $model->update($validated);
    
    // Log to trackersql
    $sql = "UPDATE table_name SET field='{$validated['field']}' WHERE id='{$id}'";
    $this->logTracker($sql, $request);
    
    return response()->json(['success' => true]);
}

public function destroy(Request $request, $id)
{
    $model = Model::findOrFail($id);
    $model->delete();
    
    // Log to trackersql
    $sql = "DELETE FROM table_name WHERE id='{$id}'";
    $this->logTracker($sql, $request);
    
    return response()->json(['success' => true]);
}
```

## Keuntungan Menggunakan Trait

✅ **DRY (Don't Repeat Yourself)** - Tidak perlu copy-paste code
✅ **Konsisten** - Semua controller menggunakan logic yang sama
✅ **Mudah Maintenance** - Update di 1 tempat, semua controller ter-update
✅ **Automatic NIP Detection** - Support auth guard dan JWT token

## Contoh Controller yang Sudah Menggunakan

- `StokOpnameController` (manual implementation)
- `OperasiController` (manual implementation, bisa direfactor ke trait)

## Migration ke Trait (Optional)

Untuk controller yang sudah punya method `logTracker` sendiri:

1. Hapus method `logTracker` dari controller
2. Tambahkan `use LogsToTracker;` di class
3. Done! ✅
