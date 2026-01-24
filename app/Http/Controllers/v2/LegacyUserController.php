<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LegacyUser;
use App\Traits\LogsToTracker;
use Illuminate\Support\Facades\DB;

class LegacyUserController extends Controller
{
    use LogsToTracker;

    /**
     * Display a listing of legacy users
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);
        $search = $request->query('search', '');

        $query = LegacyUser::select('id_user');

        if ($search) {
            $query->where('id_user', 'LIKE', "%{$search}%");
        }

        $users = $query->orderBy('id_user', 'asc')
                       ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    /**
     * Set password for a specific user
     */
    public function setPassword(Request $request, $id_user)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        try {
            $keyIdUser = env('MYSQL_AES_KEY_IDUSER', 'nur');
            $keyPassword = env('MYSQL_AES_KEY_PASSWORD', 'windi');
            
            // Find user by decrypting id_user in database
            $user = LegacyUser::whereRaw("AES_DECRYPT(id_user, '{$keyIdUser}') = ?", [$id_user])->first();

            // If user doesn't exist, create new user with encrypted id_user and password
            if (!$user) {
                // Use raw MySQL query to insert with AES_ENCRYPT
                DB::connection('mysql')->insert("
                    INSERT INTO user (id_user, password, penyakit, obat_penyakit, dokter, jadwal_praktek, 
                                     petugas, pasien, registrasi, tindakan_ralan, kamar_inap, tindakan_ranap)
                    VALUES (AES_ENCRYPT(?, ?), AES_ENCRYPT(?, ?), 'false', 'false', 'false', 'false', 
                           'false', 'false', 'false', 'false', 'false', 'false')
                ", [$id_user, $keyIdUser, $request->password, $keyPassword]);

                $this->logTracker("CREATE NEW USER with encrypted id_user: {$id_user}", $request);

                return \App\Helpers\ApiResponse::success('User created and password set successfully');
            }

            // User exists, just update password using MySQL AES_ENCRYPT
            DB::connection('mysql')->update("
                UPDATE user 
                SET password = AES_ENCRYPT(?, ?)
                WHERE AES_DECRYPT(id_user, ?) = ?
            ", [$request->password, $keyPassword, $keyIdUser, $id_user]);

            $this->logTracker("SET PASSWORD for user: {$id_user}", $request);

            return \App\Helpers\ApiResponse::success('Password updated successfully');
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to set password', 'update_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Encrypt password using AES encryption (matching legacy system)
     */
    private function encryptAES($password)
    {
        // Use AES encryption with the key from environment
        $key = env('MYSQL_AES_KEY_PASSWORD', 'windi');
        
        // AES-128-ECB encryption (matching legacy SIMRS system)
        $encrypted = openssl_encrypt(
            $password,
            'AES-128-ECB',
            $key,
            OPENSSL_RAW_DATA
        );
        
        return base64_encode($encrypted);
    }

    /**
     * Encrypt id_user using AES encryption (matching legacy system)
     */
    private function encryptAESIdUser($id_user)
    {
        // Use AES encryption with the key from environment for id_user
        $key = env('MYSQL_AES_KEY_IDUSER', 'nur');
        
        // AES-128-ECB encryption (matching legacy SIMRS system)
        $encrypted = openssl_encrypt(
            $id_user,
            'AES-128-ECB',
            $key,
            OPENSSL_RAW_DATA
        );
        
        return base64_encode($encrypted);
    }

    /**
     * Check if user exists
     */
    public function checkUser($id_user)
    {
        $user = LegacyUser::find($id_user);

        return response()->json([
            'success' => true,
            'exists' => $user !== null
        ]);
    }

    /**
     * Get existing password for a user
     */
    public function getPassword($id_user)
    {
        try {
            $keyIdUser = env('MYSQL_AES_KEY_IDUSER', 'nur');
            $keyPassword = env('MYSQL_AES_KEY_PASSWORD', 'windi');

            // Use direct SELECT with AES_DECRYPT and CAST to properly decrypt
            $result = DB::connection('mysql')->select("
                SELECT 
                    CAST(AES_DECRYPT(id_user, ?) AS CHAR) as decrypted_id,
                    CAST(AES_DECRYPT(password, ?) AS CHAR) as decrypted_password
                FROM user 
                WHERE AES_DECRYPT(id_user, ?) = ?
                LIMIT 1
            ", [$keyIdUser, $keyPassword, $keyIdUser, $id_user]);

            if (empty($result)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            $user = $result[0];

            return response()->json([
                'success' => true,
                'data' => [
                    'id_user' => $id_user,
                    'password' => $user->decrypted_password ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil password: ' . $e->getMessage()
            ], 500);
        }
    }
}
