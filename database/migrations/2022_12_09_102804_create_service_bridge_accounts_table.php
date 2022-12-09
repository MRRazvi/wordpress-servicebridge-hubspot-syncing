<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('service_bridge_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('user_pass');
            $table->string('city');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_bridge_accounts');
    }
};
