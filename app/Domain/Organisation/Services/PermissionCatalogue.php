<?php

namespace App\Domain\Organisation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads config/permissions.php and answers which permissions are delegable,
 * admin-only or reserved (LLD 02 §2).
 */
final class PermissionCatalogue
{
    /**
     * Every console permission name, including admin-only and reserved ones.
     *
     * @return Collection<int, string>
     */
    public function all(): Collection
    {
        return $this->areaPermissions()
            ->merge(config('permissions.department_admin_only', []))
            ->merge($this->reserved())
            ->unique()
            ->values();
    }

    /**
     * <area>.<action> for every area and action.
     *
     * @return Collection<int, string>
     */
    public function areaPermissions(): Collection
    {
        return collect(config('permissions.areas', []))
            ->flatMap(fn (array $actions, string $area): array => array_map(fn (string $action): string => $area.'.'.$action, $actions))
            ->values();
    }

    /**
     * Permissions a ceiling may contain and an admin may grant to members.
     *
     * @return Collection<int, string>
     */
    public function delegable(): Collection
    {
        return $this->areaPermissions()->reject(fn (string $name): bool => $this->isApprove($name))->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function reserved(): Collection
    {
        return collect(config('permissions.reserved', []));
    }

    public function isReserved(string $name): bool
    {
        return $this->reserved()->contains($name);
    }

    public function isApprove(string $name): bool
    {
        return Str::endsWith($name, '.approve');
    }

    public function isDelegable(string $name): bool
    {
        return $this->delegable()->contains($name);
    }

    public function area(string $name): string
    {
        return Str::before($name, '.');
    }

    /**
     * The approve permissions implied for admins by a ceiling.
     *
     * @param  iterable<string>  $ceiling
     * @return Collection<int, string>
     */
    public function approvalsFor(iterable $ceiling): Collection
    {
        $areas = collect($ceiling)->map(fn (string $name): string => $this->area($name))->unique();

        return $this->areaPermissions()
            ->filter(fn (string $name): bool => $this->isApprove($name) && $areas->contains($this->area($name)))
            ->values();
    }

    /**
     * Group delegable permissions by area for the matrix UI.
     *
     * @return list<array{area: string, actions: list<string>}>
     */
    public function matrix(): array
    {
        return collect(config('permissions.areas', []))
            ->map(fn (array $actions, string $area): array => [
                'area' => $area,
                'actions' => array_values(array_filter($actions, fn (string $action): bool => $action !== 'approve')),
            ])
            ->values()
            ->all();
    }
}
