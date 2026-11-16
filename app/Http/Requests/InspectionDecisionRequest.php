<?php

namespace App\Http\Requests;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectionDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([Inspection::NO_DAMAGE, Inspection::DAMAGE_CONFIRMED])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
