<?php

namespace Database\Factories;

use App\Domain\Access\Enums\WorkspaceType;
use App\Domain\Access\Models\Workspace;
use App\Domain\Organisation\Models\Department;
use App\Domain\Organisation\Services\DepartmentPermissionService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Events', 'Procurement', 'Finance', 'Media', 'Education', 'Partnerships']).' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'workspace_id' => fn () => Workspace::open(WorkspaceType::Department)->getKey(),
            'name_en' => $name,
            'name_ar' => null,
            'slug' => Str::slug($name),
            'is_public' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Department $department): void {
            app(DepartmentPermissionService::class)->syncAdminRole($department);
        });
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}
