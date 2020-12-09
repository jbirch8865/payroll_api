<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDrivetimesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('drivetimes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_refreshed');
            $table->unsignedBigInteger('need_id');
            $table->unsignedBigInteger('paid_time_allowable');
            $table->unsignedBigInteger('paid_distance_allowable');
            $table->unsignedBigInteger('actual_time')->nullable();
            $table->unsignedBigInteger('actual_distance')->nullable();
            $table->string('user_override')->nullable();
            $table->string('justification')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('drivetimes');
    }
}
