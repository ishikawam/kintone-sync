<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
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
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('apps');
    }
}
