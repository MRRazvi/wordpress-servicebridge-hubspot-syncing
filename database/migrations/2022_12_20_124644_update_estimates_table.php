<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('estimates', function ($table) {
            $table->integer('tries')->default(0);
        });
    }

    public function down()
    {
        Schema::table('estimates', function ($table) {
            $table->dropColumn('tries');
        });
    }
};
