<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactMergeLogsTable extends Migration
{
    public function up()
    {
        Schema::create('contact_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('secondary_contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->json('changes'); // structured JSON describing what was merged/kept
            $table->foreignId('performed_by')->nullable(); // optional: user id
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_merge_logs');
    }
}
