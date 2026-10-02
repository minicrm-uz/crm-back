<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function view(User $user, Lead $lead): bool
    {
        return $lead->owner_id === $user->id;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $lead->owner_id === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $lead->owner_id === $user->id;
    }
}
