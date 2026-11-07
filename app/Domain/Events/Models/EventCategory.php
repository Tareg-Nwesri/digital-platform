<?php

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Support\Concerns\HasLocalizedFields;
use Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property int|null $age_min
 * @property int|null $age_max
 * @property int $members_min
 * @property int $members_max
 * @property int $robots_per_team
 * @property int|null $capacity
 * @property int|null $max_weight_g
 * @property int|null $max_length_mm
 * @property int|null $max_width_mm
 * @property int|null $max_height_mm
 * @property int $position
 * @property-read Event $event
 */
#[Fillable([
    'event_id', 'name_en', 'name_ar', 'age_min', 'age_max', 'members_min', 'members_max', 'robots_per_team',
    'capacity', 'max_weight_g', 'max_length_mm', 'max_width_mm', 'max_height_mm', 'position',
])]
#[UseFactory(EventCategoryFactory::class)]
class EventCategory extends Model
{
    /** @use HasFactory<EventCategoryFactory> */
    use HasFactory, HasLocalizedFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_min' => 'integer',
            'age_max' => 'integer',
            'members_min' => 'integer',
            'members_max' => 'integer',
            'robots_per_team' => 'integer',
            'capacity' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'category_id');
    }

    public function hasAgeLimits(): bool
    {
        return $this->age_min !== null || $this->age_max !== null;
    }

    public function isFull(): bool
    {
        return $this->capacity !== null
            && $this->registrations()->where('status', RegistrationStatus::Approved)->count() >= $this->capacity;
    }
}
