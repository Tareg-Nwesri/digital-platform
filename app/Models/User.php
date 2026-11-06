<?php

namespace App\Models;

use App\Domain\Access\Models\Workspace;
use App\Domain\Access\Services\WorkspaceContext;
use App\Support\Concerns\HasLocalizedFields;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name_en
 * @property string|null $name_ar
 * @property-read string $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $phone
 * @property Carbon|null $birthdate
 * @property string $locale
 * @property bool $is_active
 * @property bool $can_login
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name_en', 'name_ar', 'email', 'password', 'phone', 'birthdate', 'locale', 'is_active', 'can_login'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['name'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasLocalizedFields, HasRoles, Impersonate, LogsActivity, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    public const SUPER_ADMIN = 'super-admin';

    private ?bool $superAdminCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'birthdate' => 'date',
            'is_active' => 'boolean',
            'can_login' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The display name in the current locale.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->localized('name'));
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdminCache ??= app(WorkspaceContext::class)->within(
            Workspace::platform(),
            fn (): bool => $this->unsetRelation('roles')->hasRole(self::SUPER_ADMIN),
        );
    }

    /**
     * Super admins and department admins must use two-factor authentication.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->isSuperAdmin() || DB::table('department_members')
            ->where('user_id', $this->getKey())
            ->where('is_admin', true)
            ->exists();
    }

    public function forgetSuperAdminCache(): void
    {
        $this->superAdminCache = null;
    }

    /**
     * Whole years of age on the given date, or null without a birthdate.
     */
    public function ageOn(CarbonInterface $date): ?int
    {
        return $this->birthdate === null ? null : (int) floor($this->birthdate->diffInYears($date));
    }

    public function canImpersonate(): bool
    {
        return $this->is_active && $this->isSuperAdmin();
    }

    public function canBeImpersonated(): bool
    {
        return $this->is_active && $this->can_login && ! $this->isSuperAdmin();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('access')
            ->logOnly(['name_en', 'name_ar', 'email', 'phone', 'locale', 'is_active', 'can_login'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
