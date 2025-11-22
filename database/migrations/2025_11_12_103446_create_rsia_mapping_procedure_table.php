<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRsiaMappingProcedureTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rsia_mapping_procedure', function (Blueprint $table) {
            // Primary key: kode jenis perawatan/prosedur
            $table->string('kd_jenis_prw', 15)->primary();

            // SNOMED CT mapping
            $table->string('code', 20)->nullable(); // SNOMED CT concept code
            $table->string('system', 50)->default('http://snomed.info/sct'); // SNOMED CT system URI
            $table->string('display', 255)->nullable(); // SNOMED CT preferred term
            $table->text('description')->nullable(); // Optional description

            // Metadata
            $table->string('status', 20)->default('active'); // active, inactive, draft
            $table->text('notes')->nullable(); // Additional notes
            $table->string('created_by', 50)->nullable(); // User who created the mapping
            $table->string('updated_by', 50)->nullable(); // User who updated the mapping

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('status');
            $table->fullText(['display', 'description']); // For search functionality

            // Foreign key to jns_perawatan or jns_perawatan_inap
            $table->foreign('kd_jenis_prw')
                  ->references('kd_jenis_prw')
                  ->on('jns_perawatan')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rsia_mapping_procedure');
    }
}
