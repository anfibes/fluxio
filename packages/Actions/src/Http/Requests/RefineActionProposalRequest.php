<?php

namespace Fluxio\Actions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefineActionProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
        ];
    }
}
