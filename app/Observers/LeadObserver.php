<?php

namespace App\Observers;

use App\Enums\LeadAction;
use App\Models\Lead;
use App\Models\LeadActivity;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class LeadObserver
{
    private const AUDITED_FIELDS = ['name', 'phone', 'email', 'source', 'note'];

    public function created(Lead $lead): void
    {
        $this->record($lead, LeadAction::Created, [
            'after' => $this->snapshot($lead, [...self::AUDITED_FIELDS, 'status']),
        ]);
    }

    public function updated(Lead $lead): void
    {
        // $lead->getOriginal() still holds the pre-save values in the
        // updated event (syncOriginal() runs later in finishSave()), so
        // we can diff old vs new cleanly here.
        $otherFields = array_values(array_filter(
            self::AUDITED_FIELDS,
            fn (string $field) => $lead->wasChanged($field),
        ));

        if (! empty($otherFields)) {
            $this->record($lead, LeadAction::Updated, [
                'before' => $this->snapshotFromOriginal($lead, $otherFields),
                'after' => $this->snapshot($lead, $otherFields),
            ]);
        }

        if ($lead->wasChanged('status')) {
            $this->record($lead, LeadAction::StatusChanged, [
                'before' => ['status' => $this->normalize($lead->getOriginal('status'))],
                'after' => ['status' => $this->normalize($lead->status)],
            ]);
        }
    }

    public function deleted(Lead $lead): void
    {
        $this->record($lead, LeadAction::Deleted, null);
    }

    private function record(Lead $lead, LeadAction $action, ?array $changes): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'actor_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    private function snapshot(Lead $lead, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $this->normalize($lead->getAttribute($field))])
            ->all();
    }

    private function snapshotFromOriginal(Lead $lead, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $this->normalize($lead->getOriginal($field))])
            ->all();
    }

    private function normalize(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
