<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Models\PartCategory;
use App\Domain\Content\Models\Setting;
use App\Domain\Content\Services\SettingsRepository;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The foundation's real reference data: its departments (with the areas
 * each may work in), the parts catalogue's categories and the settings
 * that point at them. No people.
 *
 * Seeded once after the first migration. Running it again only adds what
 * is missing (matched by slug or key) and never overwrites what admins
 * changed since.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * @var array<string, array{name_en: string, name_ar: string, ceiling: list<string>}>
     */
    public const DEPARTMENTS = [
        'events' => [
            'name_en' => 'Events',
            'name_ar' => 'الفعاليات',
            'ceiling' => ['events.view', 'events.edit', 'teams.view', 'reports.view'],
        ],
        'procurement' => [
            'name_en' => 'Procurement',
            'name_ar' => 'المشتريات',
            'ceiling' => ['catalogue.view', 'catalogue.edit', 'reports.view'],
        ],
        'finance' => [
            'name_en' => 'Finance',
            'name_ar' => 'المالية',
            'ceiling' => ['finance.view', 'finance.edit', 'reports.view', 'reports.edit'],
        ],
        'media' => [
            'name_en' => 'Media',
            'name_ar' => 'الإعلام',
            'ceiling' => ['content.view', 'content.edit', 'inbox.view', 'inbox.edit', 'reports.view'],
        ],
    ];

    /**
     * The categories of the old site's parts catalogue, with Arabic names.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const PART_CATEGORIES = [
        'controllers' => ['Controllers', 'وحدات التحكم'],
        'microcontrollers' => ['Microcontrollers', 'المتحكمات الدقيقة'],
        'sensors' => ['Sensors', 'الحساسات'],
        'actuators' => ['Actuators', 'المشغّلات'],
        'motors' => ['Motors', 'المحركات'],
        'wheels-tracks' => ['Wheels & Tracks', 'العجلات والجنازير'],
        'chassis-frames' => ['Chassis & Frames', 'الهياكل والإطارات'],
        'power-supplies' => ['Power Supplies', 'مصادر الطاقة'],
        'circuit-boards' => ['Circuit Boards', 'اللوحات الإلكترونية'],
        'communication-modules' => ['Communication Modules', 'وحدات الاتصال'],
        'cables-connectors' => ['Cables & Connectors', 'الكابلات والموصلات'],
        'tools-accessories' => ['Tools & Accessories', 'الأدوات والملحقات'],
    ];

    public function run(DepartmentService $departments, DepartmentPermissionService $permissions, SettingsRepository $settings): void
    {
        $superAdmin = User::query()->where('email', config('platform.super_admin.email'))->first();
        $seeded = [];

        foreach (self::DEPARTMENTS as $slug => $definition) {
            $department = Department::query()->where('slug', $slug)->first();

            if ($department === null) {
                $department = $departments->create([
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'slug' => $slug,
                    'is_public' => true,
                ], $superAdmin);

                if ($superAdmin !== null) {
                    $permissions->setCeiling($department, $definition['ceiling'], $superAdmin);
                } else {
                    $this->command?->warn("No super admin yet: set the {$slug} department's areas in the console.");
                }
            }

            $seeded[$slug] = $department;
        }

        $position = 0;

        foreach (self::PART_CATEGORIES as $slug => [$english, $arabic]) {
            PartCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name_en' => $english, 'name_ar' => $arabic, 'position' => $position++],
            );
        }

        $this->settingOnce($settings, 'inbox.department_id', $seeded['media']->getKey());
        $this->settingOnce($settings, 'finance.department_id', $seeded['finance']->getKey());
    }

    private function settingOnce(SettingsRepository $settings, string $key, mixed $value): void
    {
        if (! Setting::query()->where('key', $key)->exists()) {
            $settings->set($key, $value);
        }
    }
}
