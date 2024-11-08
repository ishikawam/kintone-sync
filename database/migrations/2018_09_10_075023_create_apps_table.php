<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->increments('appId');
            $table->string('code');
            $table->string('name');
            $table->text('description');
            $table->string('createdAt', 30);
            $table->string('creator/code');
            $table->string('creator/name');
            $table->string('modifiedAt', 30);
            $table->string('modifier/code');
            $table->string('modifier/name');
            $table->unsignedInteger('spaceId')->nullable();
            $table->unsignedInteger('threadId')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('apps');
    }
}
