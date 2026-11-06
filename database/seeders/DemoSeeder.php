<?php

namespace Database\Seeders;

use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use App\Domain\Organisation\Services\DepartmentService;
use App\Domain\Teams\Enums\TeamRole;
use App\Domain\Teams\Models\Team;
use App\Domain\Teams\Models\Tier;
use App\Domain\Teams\Services\TeamMemberService;
use App\Domain\Teams\Services\TeamService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local and staging demo data (LLD 11 §5). Refuses to run in production and
 * does nothing when the demo organisation already exists.
 *
 * Every demo login uses an @demo.test address and the DEMO_PASSWORD value.
 * The seeder grows with each module (projects, catalogue, events, finance).
 */
class DemoSeeder extends Seeder
{
    /**
     * Departments with their ceilings and the permissions delegated to each member.
     *
     * @var array<string, array{name_en: string, name_ar: string, ceiling: list<string>, members: list<array{key: string, name_en: string, name_ar: string, permissions: list<string>}>}>
     */
    private const DEPARTMENTS = [
        'events' => [
            'name_en' => 'Events',
            'name_ar' => 'الفعاليات',
            'ceiling' => ['events.view', 'events.edit', 'teams.view', 'reports.view'],
            'members' => [
                ['key' => 'events.editor', 'name_en' => 'Salma Ali', 'name_ar' => 'سلمى علي', 'permissions' => ['events.edit', 'teams.view']],
                ['key' => 'events.viewer', 'name_en' => 'Omar Khaled', 'name_ar' => 'عمر خالد', 'permissions' => ['events.view']],
            ],
        ],
        'procurement' => [
            'name_en' => 'Procurement',
            'name_ar' => 'المشتريات',
            'ceiling' => ['catalogue.view', 'catalogue.edit', 'reports.view'],
            'members' => [
                ['key' => 'procurement.editor', 'name_en' => 'Huda Mansour', 'name_ar' => 'هدى منصور', 'permissions' => ['catalogue.edit']],
                ['key' => 'procurement.viewer', 'name_en' => 'Yousef Tarek', 'name_ar' => 'يوسف طارق', 'permissions' => ['catalogue.view', 'reports.view']],
            ],
        ],
        'finance' => [
            'name_en' => 'Finance',
            'name_ar' => 'المالية',
            'ceiling' => ['finance.view', 'finance.edit', 'reports.view', 'reports.edit'],
            'members' => [
                ['key' => 'finance.editor', 'name_en' => 'Mariam Saleh', 'name_ar' => 'مريم صالح', 'permissions' => ['finance.edit']],
                ['key' => 'finance.viewer', 'name_en' => 'Adam Faraj', 'name_ar' => 'آدم فرج', 'permissions' => ['finance.view', 'reports.view']],
            ],
        ],
        'media' => [
            'name_en' => 'Media',
            'name_ar' => 'الإعلام',
            'ceiling' => ['content.view', 'content.edit', 'inbox.view', 'inbox.edit', 'reports.view'],
            'members' => [
                ['key' => 'media.editor', 'name_en' => 'Nour Hassan', 'name_ar' => 'نور حسن', 'permissions' => ['content.edit', 'inbox.edit']],
                ['key' => 'media.viewer', 'name_en' => 'Ali Mahmoud', 'name_ar' => 'علي محمود', 'permissions' => ['content.view', 'inbox.view']],
            ],
        ],
    ];

    public function run(
        DepartmentService $departments,
        DepartmentPermissionService $permissions,
        TeamService $teams,
        TeamMemberService $members,
    ): void {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder does not run in production.');

            return;
        }

        $this->call([PermissionCatalogueSeeder::class, TierSeeder::class, SuperAdminSeeder::class]);

        if (Department::query()->where('slug', 'events')->exists()) {
            $this->command?->info('Demo data already present; nothing to do.');

            return;
        }

        $superAdmin = User::query()->where('email', config('platform.super_admin.email'))->firstOrFail();

        DB::transaction(function () use ($departments, $permissions, $teams, $members, $superAdmin): void {
            $seeded = $this->seedDepartments($departments, $permissions, $superAdmin);
            $this->seedTeams($teams, $members, $superAdmin, $seeded['events']);
        });

        $this->command?->info('Demo organisation seeded. Demo logins use @demo.test addresses and DEMO_PASSWORD.');
    }

    /**
     * @return array<string, Department>
     */
    private function seedDepartments(DepartmentService $departments, DepartmentPermissionService $permissions, User $superAdmin): array
    {
        $seeded = [];

        foreach (self::DEPARTMENTS as $slug => $definition) {
            $department = $departments->create([
                'name_en' => $definition['name_en'],
                'name_ar' => $definition['name_ar'],
                'slug' => $slug,
                'is_public' => true,
            ], $superAdmin);

            $permissions->setCeiling($department, $definition['ceiling'], $superAdmin);

            $admin = $this->login("{$slug}.admin", "{$definition['name_en']} Lead", "مسؤول {$definition['name_ar']}");
            $departments->addMember($department, $admin, isAdmin: true, by: $superAdmin);

            foreach ($definition['members'] as $member) {
                $user = $this->login($member['key'], $member['name_en'], $member['name_ar']);
                $departments->addMember($department, $user, by: $admin);
                $permissions->syncMember($department, $user, $member['permissions'], $admin);
            }

            $seeded[$slug] = $department;
        }

        return $seeded;
    }

    private function seedTeams(TeamService $teams, TeamMemberService $members, User $superAdmin, Department $events): void
    {
        $starter = $teams->create([
            'name' => 'Falcons Robotics',
            'slug' => 'falcons',
            'tier_id' => Tier::query()->where('slug', 'starter')->value('id'),
            'location' => 'Tripoli',
        ], $superAdmin);

        $competition = $teams->create([
            'name' => 'Desert Bots',
            'slug' => 'desert-bots',
            'tier_id' => Tier::query()->where('slug', 'competition')->value('id'),
            'department_id' => $events->getKey(),
            'location' => 'Benghazi',
        ], $superAdmin);

        $this->staffTeam($members, $starter, 'falcons', $superAdmin, [
            [TeamRole::Captain, 'Rami Ahmed', 'رامي أحمد'],
            [TeamRole::Coach, 'Laila Omar', 'ليلى عمر'],
            [TeamRole::Member, 'Ziad Nabil', 'زياد نبيل'],
        ], ['Hamza Sami', 'حمزة سامي', '2012-03-14']);

        $this->staffTeam($members, $competition, 'desert-bots', $superAdmin, [
            [TeamRole::Captain, 'Sara Ibrahim', 'سارة إبراهيم'],
            [TeamRole::Coach, 'Khalid Younis', 'خالد يونس'],
            [TeamRole::Member, 'Aya Fathi', 'آية فتحي'],
            [TeamRole::Viewer, 'Mohamed Adel', 'محمد عادل'],
        ], ['Jana Walid', 'جنى وليد', '2011-09-02']);
    }

    /**
     * @param  list<array{0: TeamRole, 1: string, 2: string}>  $logins
     * @param  array{0: string, 1: string, 2: string}  $withoutLogin
     */
    private function staffTeam(TeamMemberService $members, Team $team, string $prefix, User $superAdmin, array $logins, array $withoutLogin): void
    {
        foreach ($logins as [$role, $nameEn, $nameAr]) {
            $members->join($team, $this->login("{$prefix}.{$role->value}", $nameEn, $nameAr), $role, $superAdmin);
        }

        $members->addWithoutLogin($team, [
            'name_en' => $withoutLogin[0],
            'name_ar' => $withoutLogin[1],
            'birthdate' => $withoutLogin[2],
        ], TeamRole::Member, $superAdmin);
    }

    private function login(string $key, string $nameEn, string $nameAr): User
    {
        $user = new User([
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'email' => "{$key}@demo.test",
            'password' => config('platform.demo_password'),
            'can_login' => true,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
