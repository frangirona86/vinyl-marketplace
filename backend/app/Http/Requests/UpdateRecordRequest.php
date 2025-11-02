<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            //'artist_name' => 'sometimes|string|max:255',
            'artist_id' => 'sometimes|exists:artists,id',
            'label' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'description' => 'nullable|string|max:5000',
            'metadata' => 'nullable|array',
        ];
    }

    /**
    * Get the validation messages that apply to the request.
    * English messages
    *
    * @return array<string, string>
    */
    public function messages(): array
    {
        return [
            'title.string' => 'The title must be text',
            'year.integer' => 'The year must be a number',
            'year.min' => 'The year must be greater than 1900',
            'year.max' => 'The year cannot be in the future',
        ];
    }
}
