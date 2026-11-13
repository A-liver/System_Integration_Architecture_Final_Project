<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stands in for the older student records system, so its naming and formats
// deliberately differ from the portal's. Only LegacyStudentRecords reads it.
return new class extends Migration
{
    protected $connection = 'legacy';

    public function up(): void
    {
        // migrate:fresh only wipes the default connection, so clear this one here.
        $this->down();

        Schema::connection('legacy')->create('STU_MASTER', function (Blueprint $table) {
            $table->string('STU_NO', 9)->primary();
            $table->string('NM_FULL', 80);
            $table->char('STAT_CD', 1);
        });

        Schema::connection('legacy')->create('STU_CRS_HIST', function (Blueprint $table) {
            $table->string('STU_NO', 9);
            $table->string('CRS_CD', 10);
            $table->string('GRADE', 4);
            $table->primary(['STU_NO', 'CRS_CD']);
        });

        Schema::connection('legacy')->create('ENRL_REC', function (Blueprint $table) {
            $table->string('STU_NO', 9);
            $table->string('CRS_CD', 10);
            $table->string('TERM_CD', 6);
            $table->string('PAY_REF', 64);
            $table->unsignedInteger('ENRL_DT');
            $table->primary(['STU_NO', 'CRS_CD', 'TERM_CD']);
        });
    }

    public function down(): void
    {
        Schema::connection('legacy')->dropIfExists('ENRL_REC');
        Schema::connection('legacy')->dropIfExists('STU_CRS_HIST');
        Schema::connection('legacy')->dropIfExists('STU_MASTER');
    }
};
