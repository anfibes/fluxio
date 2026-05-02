<?php

namespace Fluxio\Tasks\Http\Requests;

use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ];
    }
}
