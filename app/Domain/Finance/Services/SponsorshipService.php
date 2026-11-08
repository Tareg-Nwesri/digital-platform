<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Sponsor;
use App\Domain\Finance\Models\Sponsorship;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Saves a sponsorship and replaces its split rules in one go.
 */
final class SponsorshipService
{
    /**
     * @param  array<string, mixed>  $data  validated SponsorshipRequest data
     */
    public function save(Sponsor $sponsor, array $data, User $by, ?Sponsorship $sponsorship = null): Sponsorship
    {
        return DB::transaction(function () use ($sponsor, $data, $by, $sponsorship): Sponsorship {
            $currency = (string) $data['currency'];
            $attributes = [
                'sponsor_id' => $sponsor->getKey(),
                'title' => $data['title'],
                'kind' => $data['kind'],
                'pledged_minor' => Money::of((string) $data['pledged'], $currency)->getMinorAmount()->toInt(),
                'currency' => $currency,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'restrictions' => $data['restrictions'] ?? null,
                'status' => $data['status'],
                'default_account_id' => $data['default_account_id'] ?? null,
            ];

            if ($sponsorship === null) {
                $sponsorship = Sponsorship::query()->create([...$attributes, 'created_by' => $by->getKey()]);
            } else {
                $sponsorship->update($attributes);
                $sponsorship->splitRules()->delete();
            }

            foreach (array_values($data['rules'] ?? []) as $position => $rule) {
                $sponsorship->splitRules()->create([
                    'position' => $position,
                    'account_id' => (int) $rule['account_id'],
                    'percent' => filled($rule['percent'] ?? null) ? $rule['percent'] : null,
                    'fixed_minor' => filled($rule['fixed'] ?? null) ? Money::of((string) $rule['fixed'], $currency)->getMinorAmount()->toInt() : null,
                ]);
            }

            activity('finance')->performedOn($sponsorship)->causedBy($by)
                ->withProperties(['rules' => count($data['rules'] ?? [])])
                ->event('finance.sponsorship.saved')->log('Sponsorship saved');

            return $sponsorship;
        });
    }
}
