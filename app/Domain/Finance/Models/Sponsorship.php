<?php

namespace App\Domain\Finance\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Finance\Enums\DocumentStatus;
use App\Domain\Finance\Enums\SponsorshipKind;
use App\Domain\Finance\Enums\SponsorshipStatus;
use App\Domain\Teams\Models\Team;
use Brick\Money\Money;
use Database\Factories\SponsorshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sponsor_id
 * @property string $title
 * @property SponsorshipKind $kind
 * @property int $pledged_minor
 * @property string $currency
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $purpose
 * @property string|null $restrictions
 * @property int|null $team_id
 * @property int|null $event_id
 * @property int|null $default_account_id
 * @property SponsorshipStatus $status
 * @property-read Sponsor $sponsor
 * @property-read FinanceAccount|null $defaultAccount
 */
#[Fillable([
    'sponsor_id', 'title', 'kind', 'pledged_minor', 'currency', 'starts_on', 'ends_on', 'purpose', 'restrictions',
    'team_id', 'event_id', 'default_account_id', 'status', 'created_by',
])]
#[UseFactory(SponsorshipFactory::class)]
class Sponsorship extends Model
{
    /** @use HasFactory<SponsorshipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SponsorshipKind::class,
            'status' => SponsorshipStatus::class,
            'pledged_minor' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Sponsor, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * @return BelongsTo<FinanceAccount, $this>
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'default_account_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<SplitRule, $this>
     */
    public function splitRules(): HasMany
    {
        return $this->hasMany(SplitRule::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Receipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function pledged(): Money
    {
        return Money::ofMinor($this->pledged_minor, $this->currency);
    }

    /**
     * Posted receipts in the sponsorship's currency.
     */
    public function received(): Money
    {
        $minor = (int) $this->receipts()
            ->where('status', DocumentStatus::Posted)
            ->where('currency', $this->currency)
            ->sum('amount_minor');

        return Money::ofMinor($minor, $this->currency);
    }

    public function outstanding(): Money
    {
        $outstanding = $this->pledged()->minus($this->received());

        return $outstanding->isNegative() ? Money::zero($this->currency) : $outstanding;
    }
}
