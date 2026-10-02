<?php

namespace App\Models;

use App\Enums\LeadAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'actor_id', 'action', 'changes'])]
class LeadActivity extends Model
{
    public $timestamps = false;

    protected $casts = [
        'action' => LeadAction::class,
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
