<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'fields' => 'required|array|min:1',
                'timeLimit' => 'nullable|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        }

        $form = Form::create([
            'name' => $validated['name'],
            'fields' => json_encode($validated['fields']),
            'timeLimit' => $validated['timeLimit'] ?? null,
        ]);

        return response()->json($form, 201);
    }

    public function index()
    {
        return response()->json(Form::all(), 200);
    }

    public function show($id)
    {
        $form = Form::findOrFail($id);
        return response()->json($form, 200);
    }

    public function update(Request $request, $id)
    {

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'fields' => 'sometimes|array|min:1',
                'timeLimit' => 'sometimes|nullable|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        }

        $form = Form::findOrFail($id);
        $form->update([
            'name' => $validated['name'] ?? $form->name,
            'fields' => array_key_exists('fields', $validated) ? json_encode($validated['fields']) : $form->fields,
            'timeLimit' => array_key_exists('timeLimit', $validated) ? $validated['timeLimit'] : $form->timeLimit,
        ]);

        return response()->json($form, 200);
    }

    public function destroy($id)
    {
        $form = Form::findOrFail($id);
        $form->delete();
        return response()->json(['message' => 'Form deleted successfully'], 200);
    }

    public function submitForm(Request $request, $formId)
    {
        try {
            $validated = $request->validate([
                'answers' => 'required|array|min:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        }

        $submission = FormSubmission::create([
            'user_id' => Auth::id(),
            'form_id' => $formId,
            'answers' => json_encode($validated['answers']),
        ]);

        return response()->json($submission, 201);
    }
    public function getFormAnswers($formId)
    {
        try {
            $form = Form::findOrFail($formId);

            $submissions = FormSubmission::where('form_id', $formId)
                ->with('user:id,name,email')
                ->get()
                ->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'user' => $submission->user ? [
                            'id' => $submission->user->id,
                            'name' => $submission->user->name,
                            'email' => $submission->user->email,
                        ] : null,
                        'answers' => json_decode($submission->answers),
                        'created_at' => $submission->created_at,
                    ];
                });

            return response()->json([
                'form' => [
                    'id' => $form->id,
                    'name' => $form->name,
                ],
                'submissions' => $submissions
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Form not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                //'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteAnswer($submissionId)
    {
        try {
            $submission = FormSubmission::findOrFail($submissionId);

            $submission->delete();

            return response()->json(['message' => 'Answer deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Answer not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

}
