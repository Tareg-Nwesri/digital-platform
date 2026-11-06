<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\WorkspaceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared scope key for teams and departments. Spatie permission's team key points here.
 *
 * @property int $id
 * @property WorkspaceType $type
 */
#[Fillable(['type'])]
class Workspace extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
        ];
    }

    public static function open(WorkspaceType $type): self
    {
        return self::query()->create(['type' => $type]);
    }

    /**
     * The single platform workspace that scopes global roles such as super-admin.
     */
    public static function platform(): self
    {
        return once(fn (): self => self::query()->firstOrCreate(['type' => WorkspaceType::Platform]));
    }
}
