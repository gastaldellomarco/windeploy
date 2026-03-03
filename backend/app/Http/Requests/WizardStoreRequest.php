<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WizardStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required','string','max:150'],
            'templateid' => ['nullable','exists:templates,id'],
            'noteinterne' => ['nullable','string'],

            // The frontend sends "configurazione" as JSON (often via multipart as a string)
            'configurazione' => ['required'],
        ];
    }
}
