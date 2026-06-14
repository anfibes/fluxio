<?php

namespace Fluxio\Leads\Models;

use Fluxio\Leads\Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    public const STATUSES = ['new', 'contacted', 'qualified', 'lost', 'won'];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'status',
        'notes',
        'assigned_to_user_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    protected static function newFactory(): LeadFactory
    {
        return LeadFactory::new();
    }
}
