<?php

namespace Fluxio\Tasks\Models;

use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'normal',
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }
}
