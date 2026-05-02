<?php

namespace Fluxio\Tasks\Http\Requests;

use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(Task::STATUSES)],
            'priority' => ['sometimes', 'string', Rule::in(Task::PRIORITIES)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'lead_id' => ['sometimes', 'nullable', 'integer', 'exists:leads,id'],
        ];
    }
}
