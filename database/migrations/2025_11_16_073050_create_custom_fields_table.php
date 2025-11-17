<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateCustomFieldsTable extends Migration
{
public function up()
{
Schema::create('custom_fields', function (Blueprint $table) {
$table->id();
$table->string('label'); // e.g. 'Birthday'
$table->string('name')->unique(); // e.g. 'birthday' used as key
$table->enum('type', ['text','textarea','date','number','select','checkbox','radio','file'])->default('text');
$table->text('meta')->nullable(); // JSON for options, e.g. select options
$table->boolean('required')->default(false);
$table->timestamps();
});
}


public function down()
{
Schema::dropIfExists('custom_fields');
}
}
