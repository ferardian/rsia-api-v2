# SQL Migration for rsia_menu

Silakan jalankan perintah SQL berikut pada database Anda untuk menambahkan dukungan multi-platform:

```sql
-- 1. Tambah kolom platform (web/mobile) dengan default 'web'
ALTER TABLE `rsia_menu` 
ADD COLUMN `platform` ENUM('web', 'mobile') DEFAULT 'web' AFTER `is_active`;

-- 2. Pastikan semua data existing diatur ke 'web'
UPDATE `rsia_menu` SET `platform` = 'web';

-- 3. (Opsional) Contoh insert menu untuk Mobile
-- INSERT INTO `rsia_menu` (nama_menu, icon, route, is_active, platform) 
-- VALUES ('Presensi Mobile', 'fingerprint', 'menu_presensi', 1, 'mobile');
```

# Strategi Ikon Mobile (Flutter)

Agar ikon dari database bisa tampil di Flutter, ada 2 opsi yang disarankan:

### Opsi 1: Menggunakan Nama Material Icon (Direkomendasikan)
Simpan nama ikon di kolom `icon` (misal: `fingerprint`, `calendar_today`, `payments`).

Di Flutter, gunakan fungsi mapper sederhana:
```dart
IconData getIcon(String iconName) {
  switch (iconName) {
    case 'presensi': return Icons.more_time;
    case 'cuti': return Icons.calendar_month;
    case 'slip_jaspel': return Icons.payments;
    default: return Icons.help_outline;
  }
}
```

### Opsi 2: Menggunakan Font Awesome
Jika Anda ingin ikon yang sama persis antara Web dan Mobile, simpan class Font Awesome (misal: `fas fa-fingerprint`) dan gunakan package `font_awesome_flutter` di aplikasi Mobile.

---

# Format Route Mobile (Flutter)

Untuk kolom `route` di platform mobile, disarankan menggunakan format **Key/Alias** alih-alih path URL.

**Contoh Format:**
- Dashboard: `menu_dashboard`
- Presensi: `menu_presensi`
- Cuti: `menu_cuti`

**Implementasi di Flutter:**
Nanti di sisi Flutter, kita buatkan fungsi navigasi berdasarkan key tersebut:
```dart
void navigateTo(String routeKey) {
  switch (routeKey) {
    case 'menu_dashboard': 
      Navigator.push(context, MaterialPageRoute(builder: (context) => IndexScreen()));
      break;
    case 'menu_presensi':
      Navigator.push(context, MaterialPageRoute(builder: (context) => Presensi()));
      break;
    default:
      print("Menu belum diimplementasi");
  }
}
```

---

### 1. Perubahan di API Backend
Saya telah menyisipkan filter `platform` pada API Menu. Sekarang endpoint `getUserMenus` mendukung parameter query `platform`.

**Endpoint:**
`GET /api/menu/user?platform=mobile`

### 2. Perubahan di Flutter
Pada aplikasi Flutter, saat melakukan request pengambilan menu, tambahkan parameter query tersebut.

Contoh logic (ide):
```dart
var res = await Api().getData("/menu/user?platform=mobile");
// Gunakan 'route' sebagai key unik untuk navigasi di Flutter
// misal: if (menu['route'] == 'menu_presensi') Navigator.push(...)
```

### 3. Keamanan Menu Web
Menu Web tidak akan terganggu karena secara default API akan mengambil data dengan `platform='web'` jika parameter tidak disertakan (atau Anda bisa set eksplisit di aplikasi Web).
