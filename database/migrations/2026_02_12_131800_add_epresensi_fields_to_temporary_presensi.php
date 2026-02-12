<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('temporary_presensi', function (Blueprint $table) {
            // E-Presensi GPS fields
            $table->decimal('latitude', 10, 8)->nullable()->after('photo');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->decimal('accuracy', 5, 2)->nullable()->after('longitude');
            
            // E-Presensi face detection fields
            $table->float('face_confidence')->nullable()->after('accuracy');
            $table->json('liveness_data')->nullable()->after('face_confidence');
            $table->string('device_info', 255)->nullable()->after('liveness_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('temporary_presensi', function (Blueprint $table) {
            $table->dropColumn([
                'latitude',
                'longitude',
                'accuracy',
                'face_confidence',
                'liveness_data',
                'device_info'
            ]);
        });
    }
};
