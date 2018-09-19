<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLayoutTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('layout', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('appId');
            $table->unsignedBigInteger('revision');
            $table->text('layout');

            $table->boolean('batch')->default(false);  // appテーブルにスキーマ反映済みか

            $table->index(['appId', 'batch', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('layout');
    }
}
