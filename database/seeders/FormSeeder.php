<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Form;

class FormSeeder extends Seeder
{
    public function run()
    {
        Form::create([
            'name' => 'Contact Form',
            'fields' => json_encode([
                ['label' => 'Name', 'type' => 'text'],
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Message', 'type' => 'textarea']
            ]),
        ]);
    }
}
