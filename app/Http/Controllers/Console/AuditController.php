<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 08 §3: the platform-wide audit log, for the super admin.
 */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = TableQuery::for(Activity::query()->with('causer'), $request)
            ->searchable(['description', 'event'])
            ->sortable(['created_at'], default: 'created_at', direction: 'desc')
            ->filters([
                'log' => fn (Builder $query, mixed $value) => $query->where('log_name', (string) $value),
                'causer' => fn (Builder $query, mixed $value) => $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', (int) $value),
                'impersonated' => fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? $query->whereNotNull('impersonator_id') : $query,
                'subject' => fn (Builder $query, mixed $value) => $query->where('subject_type', (string) $value),
                'from' => fn (Builder $query, mixed $value) => $query->where('created_at', '>=', Carbon::parse((string) $value)->startOfDay()),
                'to' => fn (Builder $query, mixed $value) => $query->where('created_at', '<=', Carbon::parse((string) $value)->endOfDay()),
            ])
            ->paginate(fn (Activity $entry): array => self::row($entry));

        return Inertia::render('console/audit/Index', [
            'entries' => $entries,
            'logs' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values()->all(),
            'subjects' => Activity::query()->distinct()->whereNotNull('subject_type')->orderBy('subject_type')->pluck('subject_type')
                ->map(fn (string $type): array => ['value' => $type, 'label' => class_basename($type)])
                ->all(),
        ]);
    }

    /**
     * @return array{id: int, description: string, event: string|null, log: string|null, causer: string|null, impersonated: bool, subject: string|null, at: string|null, properties: array<string, mixed>}
     */
    public static function row(Activity $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'description' => $entry->description,
            'event' => $entry->event,
            'log' => $entry->log_name,
            'causer' => $entry->causer?->name,
            'impersonated' => $entry->impersonator_id !== null,
            'subject' => $entry->subject_type === null ? null : class_basename($entry->subject_type).' #'.$entry->subject_id,
            'at' => $entry->created_at?->toIso8601String(),
            'properties' => collect($entry->properties ?? [])->except(['attributes', 'old'])->take(6)->all(),
        ];
    }
}
