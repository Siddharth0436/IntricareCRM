<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateContactsTable extends Migration
{
public function up()
{
Schema::create('contacts', function (Blueprint $table) {
$table->id();
$table->string('name');
$table->string('email')->nullable()->index();
$table->string('phone')->nullable()->index();
$table->enum('gender', ['male','female','other'])->nullable();
$table->string('profile_image')->nullable(); // stored path
$table->string('additional_file')->nullable(); // stored path
$table->timestamps();
});
}


public function down()
{
Schema::dropIfExists('contacts');
}
}