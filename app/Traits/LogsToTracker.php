<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait LogsToTracker
{
    /**
     * Log SQL statement to trackersql table
     * 
     * @param string $sql The SQL statement to log
     * @param Request|null $request The request object to extract user from
     * @return void
     */
    protected function logTracker($sql, Request $request = null)
    {
        try {
            $nip = '-';
            
            // Try to get user from request first (works with auth middleware)
            $user = $request ? $request->user() : auth()->user();
            
            if ($user) {
                // Try to load detail relation if not already loaded
                if (!$user->relationLoaded('detail')) {
                    $user->load('detail');
                }
                
                // Get NIP from pegawai relation
                if ($user->detail && $user->detail->nik) {
                    $nip = $user->detail->nik;
                } else {
                    // Fallback: direct query to pegawai table
                    $pegawai = DB::table('pegawai')
                        ->where('nik', $user->id_user)
                        ->first();
                    
                    $nip = $pegawai->nik ?? $user->id_user;
                }
            } else {
                // User not in auth guard, try to get from JWT token
                if ($request && ($token = $request->bearerToken())) {
                    try {
                        $jwt = (\Lcobucci\JWT\Configuration::forSymmetricSigner(
                            new \Lcobucci\JWT\Signer\Rsa\Sha256(),
                            \Lcobucci\JWT\Signer\Key\InMemory::plainText('empty', 'empty')
                        )->parser()->parse($token));
                        
                        // Get user ID from JWT sub claim
                        if ($jwt->claims()->has('sub')) {
                            $userId = $jwt->claims()->get('sub');
                            
                            // Query pegawai table directly
                            $pegawai = DB::table('pegawai')
                                ->where('nik', $userId)
                                ->first();
                            
                            $nip = $pegawai->nik ?? $userId;
                        }
                    } catch (\Exception $e) {
                        \Log::error("Failed to parse JWT in LogsToTracker: " . $e->getMessage());
                    }
                }
            }
            
            DB::table('trackersql')->insert([
                'tanggal' => Carbon::now(),
                'sqle' => $sql,
                'usere' => $nip
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to log to trackersql: " . $e->getMessage());
        }
    }
}
