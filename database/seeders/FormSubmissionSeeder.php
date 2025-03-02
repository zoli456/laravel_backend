<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Form;

class FormSubmissionSeeder extends Seeder
{
    public function run()
    {
        $user = User::first(); // Get the first user
        $form = Form::first(); // Get the first form

        if ($user && $form) {
            FormSubmission::create([
                'user_id' => $user->id,
                'form_id' => $form->id,
                'answers' => json_encode([
                    'Name' => 'John Doe',
                    'Email' => 'johndoe@example.com',
                    'Message' => 'Hello, this is a test submission!'
                ]),
            ]);
        }
    }
}
