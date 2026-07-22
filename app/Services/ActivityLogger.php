<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\ApplicationLink;
use App\Models\Banner;
use App\Models\Questionnaire;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Central activity-log writer.
 *
 * Contract:
 * - activity_logs.user_id is always the actor (the person who performed it).
 * - subject_* identifies the record affected by the action.
 * - properties stores non-sensitive before/after context for audit purposes.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        Request $request,
        string $type,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
        ?int $actorId = null,
        ?int $applicationId = null,
        ?int $questionnaireId = null,
        ?string $subjectType = null,
        ?string $subjectLabel = null,
    ): ActivityLog {
        $actorId ??= $request->user()?->getAuthIdentifier();

        $subjectType ??= $subject ? $this->subjectType($subject) : null;
        $subjectLabel ??= $subject ? $this->subjectLabel($subject) : null;

        if ($subject instanceof Application) {
            $applicationId ??= (int) $subject->getKey();
        } elseif ($subject instanceof ApplicationLink) {
            $applicationId ??= (int) $subject->application_id;
        } elseif ($subject instanceof Questionnaire) {
            $questionnaireId ??= (int) $subject->getKey();
        }

        return ActivityLog::create([
            'user_id' => $actorId,
            'application_id' => $applicationId,
            'questionnaire_id' => $questionnaireId,
            'subject_type' => $subjectType,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'activity_type' => $type,
            'description' => $description,
            'properties' => $properties === [] ? null : $this->normalize($properties),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Return only values that actually changed, split into before/after snapshots.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function changes(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $beforeValue = $this->normalize($before[$key] ?? null);
            $afterValue = $this->normalize($after[$key] ?? null);

            if ($beforeValue === $afterValue) {
                continue;
            }

            $old[$key] = $beforeValue;
            $new[$key] = $afterValue;
        }

        return ['before' => $old, 'after' => $new];
    }

    /** @param array{before: array<string, mixed>, after: array<string, mixed>} $changes */
    public function hasChanges(array $changes): bool
    {
        return $changes['before'] !== [] || $changes['after'] !== [];
    }

    private function subjectType(Model $subject): string
    {
        return match (true) {
            $subject instanceof User => 'user',
            $subject instanceof Application => 'application',
            $subject instanceof ApplicationLink => 'application_link',
            $subject instanceof Banner => 'banner',
            $subject instanceof Questionnaire => 'questionnaire',
            default => Str::snake(class_basename($subject)),
        };
    }

    private function subjectLabel(Model $subject): string
    {
        foreach (['name', 'title', 'label', 'code', 'nip_nik'] as $attribute) {
            if (filled($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $value;
    }
}
