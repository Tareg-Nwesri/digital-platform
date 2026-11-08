<?php

namespace App\Http\Controllers\Console;

use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Models\ContactMessageNote;
use App\Domain\Content\Services\ContactMessageService;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ContactNoteRequest;
use App\Http\Requests\Console\UpdateContactMessageRequest;
use App\Models\User;
use App\Support\Tables\TableQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 08 §5: the contact inbox.
 */
class InboxController extends Controller
{
    public function __construct(
        private readonly ContactMessageService $messages,
        private readonly DepartmentAccess $access,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContactMessage::class);

        $user = $request->user();
        $query = ContactMessage::query()->with(['department', 'assignee']);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('department_id', $this->departmentIds($user));
        }

        $messages = TableQuery::for($query, $request)
            ->searchable(['name', 'email', 'subject'])
            ->sortable(['created_at', 'subject'], default: 'created_at', direction: 'desc')
            ->filters([
                'status' => fn (Builder $query, mixed $value) => $query->where('status', (string) $value),
                'assignee' => fn (Builder $query, mixed $value) => $value === 'me'
                    ? $query->where('assigned_to', $user->getKey())
                    : $query->whereNull('assigned_to'),
            ])
            ->paginate(fn (ContactMessage $message): array => [
                'id' => $message->getKey(),
                'name' => $message->name,
                'subject' => $message->subject,
                'status' => $message->status->value,
                'department' => $message->department?->localized('name'),
                'assignee' => $message->assignee?->name,
                'createdAt' => $message->created_at?->toIso8601String(),
            ]);

        return Inertia::render('console/inbox/Index', [
            'messages' => $messages,
            'statuses' => array_column(MessageStatus::cases(), 'value'),
        ]);
    }

    public function show(Request $request, ContactMessage $message): Response
    {
        $this->authorize('view', $message);

        $message->loadMissing(['department', 'assignee', 'notes.user']);

        return Inertia::render('console/inbox/Show', [
            'message' => [
                'id' => $message->getKey(),
                'name' => $message->name,
                'email' => $message->email,
                'subject' => $message->subject,
                'body' => $message->message,
                'locale' => $message->locale,
                'status' => $message->status->value,
                'department' => $message->department?->localized('name'),
                'assigneeId' => $message->assigned_to,
                'assignee' => $message->assignee?->name,
                'createdAt' => $message->created_at?->toIso8601String(),
            ],
            'notes' => $message->notes->map(fn (ContactMessageNote $note): array => [
                'id' => $note->getKey(),
                'body' => $note->body,
                'author' => $note->user?->name,
                'createdAt' => $note->created_at?->toIso8601String(),
            ])->all(),
            'statuses' => array_column(MessageStatus::cases(), 'value'),
            'assignees' => fn (): array => $this->assignees($message),
            'can' => ['edit' => $request->user()->can('update', $message)],
        ]);
    }

    public function update(UpdateContactMessageRequest $request, ContactMessage $message): RedirectResponse
    {
        $user = $request->user();

        if ($request->has('status')) {
            $this->messages->setStatus($message, MessageStatus::from($request->validated('status')), $user);
        }

        if ($request->has('assigned_to')) {
            $assignee = filled($request->validated('assigned_to')) ? User::query()->find($request->validated('assigned_to')) : null;
            $this->messages->assign($message, $assignee, $user);
        }

        return $this->done('updated');
    }

    public function notes(ContactNoteRequest $request, ContactMessage $message): RedirectResponse
    {
        $this->messages->addNote($message, $request->validated('body'), $request->user());

        return $this->done('note_added');
    }

    /**
     * @return list<int>
     */
    private function departmentIds(User $user): array
    {
        return array_values(array_unique([
            ...$this->access->departmentIdsWith($user, 'inbox.view'),
            ...$this->access->departmentIdsWith($user, 'inbox.edit'),
        ]));
    }

    /**
     * Staff who can read this message, for the assignee picker.
     *
     * @return list<array{id: int, name: string}>
     */
    private function assignees(ContactMessage $message): array
    {
        if (! $message->department instanceof Department) {
            return [];
        }

        return $message->department->members()->orderBy('name_en')->get()
            ->filter(fn (User $member): bool => $member->can('view', $message))
            ->map(fn (User $member): array => ['id' => $member->getKey(), 'name' => $member->name])
            ->values()
            ->all();
    }

    private function done(string $key): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.flash.'.$key)]);

        return back();
    }
}
