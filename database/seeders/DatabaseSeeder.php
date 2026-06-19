<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'walterfernandofloresalvarres@gmail.com',
        ], [
            'name' => 'Walter Flores Alvarez',
            'username' => 'walter',
            'password' => Hash::make('password'),
            'role' => 'Administrador',
            'status' => 'Activo',
        ]);

        User::updateOrCreate([
            'email' => 'vortexdevgroup@l.com',
        ], [
            'name' => 'Vortex Dev Group',
            'username' => 'vortexdevgroup',
            'password' => Hash::make('password'),
            'role' => 'Administrador',
            'status' => 'Activo',
        ]);
    }
}
