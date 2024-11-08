<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFieldsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('appId');
            $table->unsignedBigInteger('revision');
            $table->json('properties');

            $table->boolean('batch')->default(false);  // appテーブルにスキーマ反映済みか

            $table->index(['appId', 'batch', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('fields');
    }
}
