<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPetugasData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
   // Isi command
  public function handle() {
      // Get data dari sikrsia.petugas
      $petugasLama = DB::connection('sikrsia')
          ->table('petugas')
          ->get();

      foreach ($petugasLama as $petugas) {
          // Insert/update ke rsia_petugas
          DB::table('rsia_petugas')
              ->updateOrInsert(
                  ['nip' => $petugas->nip],
                  [
                      'nama' => $petugas->nama,
                      'jk' => $petugas->jk,
                      // ... other fields
                      'updated_at' => now()
                  ]
              );
      }

      $this->info('Data petugas berhasil disync!');
  }
}
