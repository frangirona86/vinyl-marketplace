<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            //'artist_name' => 'required|string|max:255',
            'artist_id' => 'required|exists:artists,id',
            'label' => 'nullable|string|max:255',
            'genre' => 'nullable|string|max:100',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
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
            'title.required' => 'The title is required',
            //'artist_name.required' => 'The artist name is required',
            'artist_id.required' => 'The artist ID is required',
            'artist_id.exists' => 'The artist ID does not exist',
            'year.required' => 'The year is required',
            'year.min' => 'The year must be greater than 1900',
            'year.max' => 'The year cannot be in the future',
        ];
    }
}
