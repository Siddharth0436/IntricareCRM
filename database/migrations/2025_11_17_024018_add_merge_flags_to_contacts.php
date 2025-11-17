<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMergeFlagsToContacts extends Migration
{
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('merged_to')->nullable()->constrained('contacts')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('updated_at');
        });
    }

    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_to');
            $table->dropColumn('is_active');
        });
    }
}
