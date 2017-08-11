<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->index();
            $table->string('paper');
            $table->string('question');
            $table->string('variables',1024);
            $table->enum('event', ['Q','✓','?✓','?✗','✗','Hint','Show','Re-try','Help',':)',':|',':(','Start','End','Edit','M','C','QP','MS','ER','Review']);
            $table->string('answer');
            $table->timestamp('created_at');
            $table->string('comment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('logs');
    }
}
