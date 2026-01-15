<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@dentalhub.com',
            'is_employee' => true,
            'password' => env('ADM_PAS'), // password
        ]);
        Role::factory()->create([
            'type' => 'admin',
        ]);
        Role::factory()->create([
            'type' => 'sales',
        ]);
        Role::factory()->create([
            'type' => 'accounting',
        ]);
        Role::factory()->create([
            'type' => 'inventory',
        ]);
        Role::factory()->create([
            'type' => 'reporting',
        ]);
        DB::table('role_user')->insert([
            'role_id' => 1,
            'user_id' => 1,
        ]);
        Category::factory()->create([
            'name' => 'other',
            's_name' => 'other',
            'enabled' => true,
        ]);
    }
}
