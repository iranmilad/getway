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
            $table->string('email')->unique();
            $table->string('siteUrl')->unique();
            $table->string('holooCustomerID')->unique();

            $table->string('activeLicense', 12)->nullable();
            $table->date('expireActiveLicense')->nullable();

            $table->enum('holo_unit', ['rial', 'toman'])->default('rial');
            $table->enum('plugin_unit', ['rial', 'toman'])->default('toman');

            $table->string('serial')->default('10304923');
            $table->string('holooDatabaseName')->default('Holoo1');
            $table->string('apiKey')->default('E5D3A60D3689D3CB8BD8BE91E5E29E934A830C2258B573B5BC28711F3F1D4B70');

            $table->text('cloudToken')->nullable();
            $table->date('cloudTokenExDate')->nullable();

            $table->text('wordpressToken')->nullable();

            $table->string('consumerKey')->nullable();
            $table->string('consumerSecret')->nullable();

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
