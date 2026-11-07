<?php

namespace App\Domain\Events\Models;

use App\Support\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $registration_id
 * @property int|null $placement
 * @property string|null $score
 * @property string|null $award_en
 * @property string|null $award_ar
 * @property string|null $notes
 * @property-read EventRegistration $registration
 */
#[Fillable(['registration_id', 'placement', 'score', 'award_en', 'award_ar', 'notes'])]
class EventResult extends Model
{
    use HasLocalizedFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}
