<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userRole = config('roles.models.role')::where('name', '=', 'User')->first();
        $adminRole = config('roles.models.role')::where('name', '=', 'Admin')->first();
        $permissions = config('roles.models.permission')::all();

        /*
         * Add Admin
         */
        if (config('roles.models.defaultUser')::where('email', '=', 'admin@admin.com')->first() === null) {
            $adminUser = config('roles.models.defaultUser')::create([
                'name'     => 'Admin',
                'email'    => 'admin@admin.com',
                'password' => bcrypt('password'),
            ]);

            // Attach both Admin and User roles
            $adminUser->attachRole($adminRole);
            $adminUser->attachRole($userRole);

            // Give all permissions
            foreach ($permissions as $permission) {
                $adminUser->attachPermission($permission);
            }
        }

        /*
         * Add Regular User
         */
        if (config('roles.models.defaultUser')::where('email', '=', 'user@user.com')->first() === null) {
            $normalUser = config('roles.models.defaultUser')::create([
                'name'     => 'User',
                'email'    => 'user@user.com',
                'password' => bcrypt('password'),
            ]);

            $normalUser->attachRole($userRole);
        }
    }
}
