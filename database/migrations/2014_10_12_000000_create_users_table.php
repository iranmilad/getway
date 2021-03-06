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

        // developer kazemi.milad@gmail.com

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('siteUrl')->unique();
            $table->string('holooCustomerID')->unique();

            $table->string('activeLicense', 12)->nullable();
            $table->date('expireActiveLicense')->nullable();

            $table->enum('holo_unit', ['rial', 'toman'])->default('rial');
            $table->enum('plugin_unit', ['rial', 'toman'])->default('toman');

            $table->string('serial')->default('10304923');
            $table->string('holooDatabaseName')->default('Holoo1');
            $table->string('apiKey')->default('B06978A4BDC049EB9CFC17E7FDF329350BADB97DACA44E338C664E31F5EEB078');
            $table->enum('user_traffic', ['heavy', 'normal','light'])->default('light');
            $table->boolean('allow_insert_product')->default(false);

            $table->text('cloudToken')->nullable();
            $table->date('cloudTokenExDate')->nullable();

            $table->text('wordpressToken')->nullable();

            $table->string('consumerKey')->nullable();
            $table->string('consumerSecret')->nullable();

            $table->string('server_url',500)->nullable();
            $table->json('config')->nullable();

            $table->string('password');
            $table->rememberToken();

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
