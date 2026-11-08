<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\MessageStatus;
use App\Domain\Organisation\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $department_id
 * @property string $name
 * @property string $email
 * @property string $subject
 * @property string $message
 * @property string $locale
 * @property MessageStatus $status
 * @property int|null $assigned_to
 * @property string|null $ip_hash
 * @property-read Department|null $department
 * @property-read User|null $assignee
 */
#[Fillable(['department_id', 'name', 'email', 'subject', 'message', 'locale', 'status', 'assigned_to', 'ip_hash'])]
class ContactMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => MessageStatus::class];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<ContactMessageNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactMessageNote::class)->oldest();
    }
}
