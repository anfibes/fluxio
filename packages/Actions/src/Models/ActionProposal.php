<?php

namespace Fluxio\Actions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionProposal extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'intent',
        'status',
        'confidence',
        'source_text',
        'entities',
        'missing',
        'warnings',
        'editable_fields',
        'changes',
        'needs_confirmation',
        'confirmed_at',
        'executed_at',
        'failed_at',
        'failure_reason',
        'failure_reason_code',
        'execution_result',
        'last_refinement',
        'ambiguities',
        'resolved_entities',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'entities' => 'array',
            'missing' => 'array',
            'warnings' => 'array',
            'editable_fields' => 'array',
            'changes' => 'array',
            'needs_confirmation' => 'boolean',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
            'failed_at' => 'datetime',
            'execution_result' => 'array',
            'last_refinement' => 'array',
            'ambiguities' => 'array',
            'resolved_entities' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
