<?php

namespace App\Integration;

use App\Models\Integration\ApiClient;
use App\Models\Integration\InboxEvent;
use App\Models\Integration\ProgramRecord;
use App\Models\Registry\Startup;
use App\Models\Registry\StartupAlias;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies events from a connected system exactly once (idempotent inbox).
 * Only whitelisted fields are kept: never scores, comments or answers.
 */
class EventIngestor
{
    /** type => allowed data fields */
    public const TYPES = [
        'ims.application.submitted.v1' => ['application_id', 'program_title', 'cohort_name', 'submitted_at'],
        'ims.application.status_changed.v1' => ['application_id', 'status', 'program_title', 'cohort_name', 'changed_at'],
        'ims.program.enrolled.v1' => ['application_id', 'program_title', 'cohort_name', 'start_date', 'end_date'],
    ];

    public const STATUSES = ['Submitted', 'Under Review', 'Shortlisted', 'Accepted', 'Selected', 'Rejected', 'Withdrawn'];

    /**
     * @return array{0: int, 1: array} HTTP status and JSON body
     */
    public function ingest(ApiClient $client, array $envelope): array
    {
        $eventId = (string) ($envelope['event_id'] ?? '');
        $type = (string) ($envelope['type'] ?? '');
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId)) {
            return [400, ['error' => 'invalid_event', 'message' => 'event_id must be a UUID.']];
        }
        if (InboxEvent::query()->whereKey($eventId)->exists()) {
            return [200, ['status' => 'duplicate']];
        }
        if (! array_key_exists($type, self::TYPES) || ($envelope['source'] ?? null) !== $client->recipient_system) {
            return $this->reject($client, $eventId, $type, null, 'unsupported_type', []);
        }
        $data = array_intersect_key((array) ($envelope['data'] ?? []), array_flip(self::TYPES[$type]));
        $applicationId = trim((string) ($data['application_id'] ?? ''));
        if ($applicationId === '' || strlen($applicationId) > 100) {
            return $this->reject($client, $eventId, $type, null, 'missing_application_id', $data);
        }
        if ($type === 'ims.application.status_changed.v1' && ! in_array($data['status'] ?? null, self::STATUSES, true)) {
            return $this->reject($client, $eventId, $type, null, 'unknown_status', $data);
        }

        $startup = $this->resolveStartup((string) ($envelope['subject_uuid'] ?? ''));
        if (! $startup) {
            return $this->reject($client, $eventId, $type, (string) ($envelope['subject_uuid'] ?? ''), 'unknown_subject', $data);
        }
        $existingAlias = StartupAlias::query()->where('source_system', $client->recipient_system)->where('external_id', $applicationId)->first();
        if ($existingAlias && $existingAlias->startup_uuid !== $startup->uuid) {
            return $this->reject($client, $eventId, $type, $startup->uuid, 'application_linked_to_another_startup', $data);
        }

        try {
            DB::transaction(function () use ($client, $eventId, $type, $envelope, $startup, $data, $applicationId, $existingAlias) {
                InboxEvent::query()->create([
                    'event_id' => $eventId, 'api_client_id' => $client->id, 'type' => $type, 'subject_uuid' => $startup->uuid,
                    'status' => 'processed', 'data' => $data, 'occurred_at' => $this->time($envelope['occurred_at'] ?? null),
                ]);
                if (! $existingAlias) {
                    StartupAlias::query()->create(['source_system' => $client->recipient_system, 'external_id' => $applicationId, 'startup_uuid' => $startup->uuid]);
                }
                $record = ProgramRecord::query()->firstOrNew(['source_system' => $client->recipient_system, 'external_application_id' => $applicationId]);
                $record->startup_uuid = $startup->uuid;
                $record->program_title = $data['program_title'] ?? $record->program_title;
                $record->cohort_name = $data['cohort_name'] ?? $record->cohort_name;
                match ($type) {
                    'ims.application.submitted.v1' => $record->fill([
                        'status' => $record->exists ? $record->status : 'Submitted',
                        'submitted_at' => $this->time($data['submitted_at'] ?? null) ?? now(),
                    ]),
                    'ims.application.status_changed.v1' => $record->fill([
                        'status' => $data['status'],
                        'status_changed_at' => $this->time($data['changed_at'] ?? null) ?? now(),
                    ]),
                    'ims.program.enrolled.v1' => $record->fill([
                        'status' => $record->status ?? 'Selected',
                        'enrolled' => true,
                        'program_start_date' => $this->date($data['start_date'] ?? null),
                        'program_end_date' => $this->date($data['end_date'] ?? null),
                    ]),
                };
                $record->status ??= 'Submitted';
                $record->save();
            });
        } catch (QueryException $exception) {
            // A concurrent delivery of the same event won the race.
            if (InboxEvent::query()->whereKey($eventId)->exists()) {
                return [200, ['status' => 'duplicate']];
            }
            throw $exception;
        }

        return [202, ['status' => 'processed']];
    }

    private function resolveStartup(string $uuid): ?Startup
    {
        $startup = Startup::query()->find($uuid);
        // Follow merges so events sent with an old ID still land on the surviving record.
        for ($hops = 0; $startup && $startup->status === 'merged' && $startup->merged_into_uuid && $hops < 5; $hops++) {
            $startup = Startup::query()->find($startup->merged_into_uuid);
        }

        return $startup && $startup->status === 'active' ? $startup : null;
    }

    private function reject(ApiClient $client, string $eventId, string $type, ?string $subject, string $error, array $data): array
    {
        InboxEvent::query()->firstOrCreate(['event_id' => $eventId], [
            'api_client_id' => $client->id, 'type' => mb_substr($type, 0, 60), 'subject_uuid' => $subject && strlen($subject) === 36 ? $subject : null,
            'status' => 'rejected', 'error' => $error, 'data' => $data,
        ]);

        return [400, ['error' => $error]];
    }

    private function time(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
