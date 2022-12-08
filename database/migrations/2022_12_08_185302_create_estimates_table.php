<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->string('estimate_id')->unique();
            $table->string('status');
            $table->json('blob');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('estimates');
    }
};