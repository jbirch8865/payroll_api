<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDriveTimeColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('drivetimes', function (Blueprint $table) {
            $table->float('paid_time_allowable',8,2)->change();
            $table->float('paid_distance_allowable',8,2)->change();
            $table->float('actual_time',8,2)->change();
            $table->float('actual_distance',8,2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('drivetimes', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_time_allowable')->change();
            $table->unsignedBigInteger('paid_distance_allowable')->change();
            $table->unsignedBigInteger('actual_time')->change();
            $table->unsignedBigInteger('actual_distance')->change();
        });
    }
}
