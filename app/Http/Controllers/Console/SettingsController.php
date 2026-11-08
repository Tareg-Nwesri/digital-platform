<?php

namespace App\Http\Controllers\Console;

use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Organisation\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LLD 08 §5: platform settings (super admin; settings.manage is reserved).
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(): Response
    {
        return Inertia::render('console/settings/Index', [
            'settings' => collect(UpdateSettingsRequest::FIELDS)
                ->map(fn (string $key): mixed => $this->settings->get($key))
                ->all(),
            'departments' => Department::query()->orderBy('name_en')->get()
                ->map(fn (Department $department): array => ['id' => $department->getKey(), 'name' => (string) $department->localized('name')])
                ->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        foreach ($request->settings() as $key => $value) {
            if ($this->settings->get($key) !== $value) {
                $this->settings->set($key, $value, $request->user());
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.flash.settings_saved')]);

        return back();
    }
}
