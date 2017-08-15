<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tests', function (Blueprint $table) {
        	$table->increments('id');
            $table->string('title');
            $table->string('keywords');
            $table->integer('user_id')->index();
            $table->text('json');
            $table->enum('type', ['vars','past','book','home','pastM']);
            $table->timestamps();
            $table->boolean('live')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tests');
    }
}
