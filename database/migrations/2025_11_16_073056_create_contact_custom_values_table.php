<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateContactCustomValuesTable extends Migration
{
public function up()
{
Schema::create('contact_custom_values', function (Blueprint $table) {
$table->id();
$table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
$table->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
$table->text('value')->nullable();
$table->timestamps();


$table->unique(['contact_id','custom_field_id']);
});
}


public function down()
{
Schema::dropIfExists('contact_custom_values');
}
}