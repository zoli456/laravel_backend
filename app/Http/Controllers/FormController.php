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
    private function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }

        if (is_string($input)) {
            $input = trim($input);
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            $input = preg_replace('/\s+/', ' ', $input);
        }

        return $input;
    }

    public function store(Request $request)
    {
        try {
            $sanitizedData = $this->sanitizeInput($request->all());

            $validated = validator($sanitizedData, [
                'name' => 'required|string|max:255',
                'fields' => 'required|array|min:1',
                'timeLimit' => 'nullable|integer|min:1',
            ])->validate();

            $form = Form::create([
                'name' => $validated['name'],
                'fields' => json_encode($validated['fields']),
                'timeLimit' => $validated['timeLimit'] ?? null,
            ]);

            return response()->json($form, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            // Log and return a more informative response
            \Log::error('Form store error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(), // Optional: remove in production
            ], 500);
        }
    }

    public function index()
    {
        return response()->json(Form::all(), 200);
    }

    public function show($id)
    {
        $sanitizedId = $this->sanitizeInput($id);
        $form = Form::findOrFail($sanitizedId);
        return response()->json($form, 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $sanitizedData = $this->sanitizeInput($request->all());
            $sanitizedId = $this->sanitizeInput($id);

            $validated = validator($sanitizedData, [
                'name' => 'sometimes|string|max:255',
                'fields' => 'sometimes|array|min:1',
                'timeLimit' => 'sometimes|nullable|integer|min:1',
            ])->validate();

            $form = Form::findOrFail($sanitizedId);
            $form->update([
                'name' => $validated['name'] ?? $form->name,
                'fields' => array_key_exists('fields', $validated) ? json_encode($validated['fields']) : $form->fields,
                'timeLimit' => array_key_exists('timeLimit', $validated) ? $validated['timeLimit'] : $form->timeLimit,
            ]);

            return response()->json($form, 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function destroy($id)
    {
        $sanitizedId = $this->sanitizeInput($id);
        $form = Form::findOrFail($sanitizedId);
        $form->delete();
        return response()->json(['message' => 'Form deleted successfully'], 200);
    }

    public function submitForm(Request $request, $formId)
    {
        try {
            $sanitizedData = $this->sanitizeInput($request->all());
            $sanitizedFormId = $this->sanitizeInput($formId);

            $validated = validator($sanitizedData, [
                'answers' => 'required|array|min:1',
            ])->validate();

            $submission = FormSubmission::create([
                'user_id' => Auth::id(),
                'form_id' => $sanitizedFormId,
                'answers' => json_encode($validated['answers']),
            ]);

            return response()->json($submission, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid data provided',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function getFormAnswers($formId)
    {
        try {
            $sanitizedFormId = $this->sanitizeInput($formId);
            $form = Form::findOrFail($sanitizedFormId);

            $submissions = FormSubmission::where('form_id', $sanitizedFormId)
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
            ], 500);
        }
    }

    public function deleteAnswer($submissionId)
    {
        try {
            $sanitizedSubmissionId = $this->sanitizeInput($submissionId);
            $submission = FormSubmission::findOrFail($sanitizedSubmissionId);

            $submission->delete();

            return response()->json(['message' => 'Answer deleted successfully'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Answer not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
