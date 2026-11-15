<?php

namespace App\Http\Requests\Workspace;

use App\Domain\Teams\Enums\TeamFeature;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Services\TeamFeatures;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The team's public profile. Showing it on the website needs the
 * public.profile tier feature.
 */
class TeamProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('team.settings');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => ['nullable', 'string', 'max:120'],
            'focus_en' => ['nullable', 'string', 'max:255'],
            'focus_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'official_url' => ['nullable', 'url:http,https', 'max:255'],
            'is_public' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Team $team */
                $team = $this->route('team');

                if ($this->boolean('is_public') && ! app(TeamFeatures::class)->active($team, TeamFeature::PublicProfile)) {
                    $validator->errors()->add('is_public', __('teams.errors.public_profile_off'));
                }
            },
        ];
    }
}
