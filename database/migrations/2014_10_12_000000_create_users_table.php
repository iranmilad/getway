<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('siteUrl')->unique();
            $table->string('activeLicense', 12)->nullable();
            $table->date('expireActiveLicense')->nullable();
            $table->string('holooDatabaseName');
            $table->string('holooCustomerID')->unique();
            $table->string('wordpressToken')->nullable();
            $table->string('consumerKey')->nullable();
            $table->string('consumerSecret')->nullable();
            $table->timestamps();
            $table->date('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
