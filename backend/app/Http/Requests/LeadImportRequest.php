<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadImportRequest extends FormRequest
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
        $maxKilobytes = (int) env('CSV_MAX_UPLOAD_SIZE_KB', 102400);

        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                "max:{$maxKilobytes}",
            ],
            'notification_email' => [
                'nullable',
                'email:rfc,filter',
                'max:255',
            ],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a CSV file to upload.',
            'file.file' => 'The uploaded entity must be a valid file.',
            'file.mimes' => 'The uploaded file must be a CSV file (.csv or .txt format).',
            'file.max' => 'The CSV file exceeds the maximum allowed upload size.',
            'notification_email.email' => 'Please provide a valid email address for notifications.',
        ];
    }
}
