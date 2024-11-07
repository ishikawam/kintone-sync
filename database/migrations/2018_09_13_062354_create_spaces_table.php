<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('defaultThread');
            $table->boolean('isPrivate');
            $table->string('creator/code');
            $table->string('creator/name');
            $table->string('modifier/code');
            $table->string('modifier/name');
            $table->unsignedInteger('memberCount');
            $table->string('coverType');
            $table->string('coverKey');
            $table->string('coverUrl');
            $table->text('body');
            $table->boolean('useMultiThread');
            $table->boolean('isGuest');
            $table->string('fixedMember');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spaces');
    }
}
