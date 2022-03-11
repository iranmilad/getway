<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::table('users')->insert([
            'siteUrl' => "https://www.wordpress.clawar-services.org",
            'holooCustomerID' => Str::random(10),
            'activeLicense' => "5lqrW4Il3eD0",
            'expireActiveLicense' => '2023-03-09',

            'consumerKey' => "ck_b0cfddb4a5ca768ace85c1ea3f67a2828f9d3ffd",
            'consumerSecret' => "cs_fa36295d13742a958a8242976634b4dadaab5bf6",
            'email' => Str::random(10).'@gmail.com',
            'password' => Hash::make("5lqrW4Il3eD0"),
        ]);

        DB::table('users')->insert([
            'siteUrl' => "https://wpdemoo.ir/wordpress",
            'holooCustomerID' => Str::random(10),
            'activeLicense' => "3j41JEOgUOr0",
            'expireActiveLicense' => '2023-03-09',

            'consumerKey' => "ck_aba449a275ac2b54c6e42f726c836a62b71a7d15",
            'consumerSecret' => "cs_6288c57cea24005081b2d074965595473a4e533a",
            'email' => Str::random(10).'@gmail.com',
            'password' => Hash::make("3j41JEOgUOr0"),
        ]);

    }
}
