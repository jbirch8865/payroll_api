<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('drivetimes', function (Blueprint $table) {
            $table->float('home_drive_time',8,2);
            $table->float('home_drive_distance',8,2);
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
            $table->dropColumn('home_drive_time');
            $table->dropColumn('home_drive_distance');
        });
    }
}
