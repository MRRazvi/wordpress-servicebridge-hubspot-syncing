<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->integer('sb_account_id');
            $table->string('estimate_id')->unique();
            $table->string('contact_id');
            $table->string('customer_id');
            $table->string('email');
            $table->string('status')->nullable();
            $table->integer('version')->nullable();
            $table->boolean('synced')->default(false);
            $table->integer('tries')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('estimates');
    }
};
