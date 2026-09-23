<?php

namespace App\Models;

use App\Traits\HasCode;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasCode, HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->password)) {
                $user->password = Hash::make('Mmg2026!');
            }
        });

        static::updating(function (self $user): void {
            if ($user->isDirty('department_id')) {
                $newDepartmentId = $user->department_id;
                $rolesToDetach = $user->roles()
                    ->whereNotNull('department_id')
                    ->where('department_id', '!=', $newDepartmentId)
                    ->pluck('id');

                if ($rolesToDetach->isNotEmpty()) {
                    $user->roles()->detach($rolesToDetach);
                }
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'position_id',
        'territory_id',
        'manager_id',
        'sales_target',
        'target_metadata',
        'code',
        'is_active',
    ];

    protected $codeColumn = 'code';

    protected $codePrefix = 'USR';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'sales_target' => 'decimal:2',
            'target_metadata' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Memoized department check. Policies evaluate this per row, so avoid
     * reloading the relation for every record in a table.
     */
    private ?bool $managementDepartment = null;

    private ?bool $managementDirector = null;

    /**
     * Whether the user belongs to the Management department.
     */
    public function isManagementDepartment(): bool
    {
        return $this->managementDepartment ??= $this->department?->name === Department::MANAGEMENT;
    }

    /**
     * Whether the user is a Director within the Management department.
     *
     * Matched on the role name containing "Director" rather than on an exact
     * role name: this role has already been renamed between
     * "Director - Management" and "Management Director", and the admin UI can
     * rewrite it again.
     */
    public function isManagementDirector(): bool
    {
        return $this->managementDirector ??= $this->isManagementDepartment()
            && $this->roles->contains(fn ($role): bool => str_contains($role->name, 'Director'));
    }

    /**
     * Whether every record is visible to this user, ignoring ownership,
     * hierarchy and territory.
     *
     * Super Admin is checked first so a Super Admin who happens to sit in the
     * Management department keeps write access.
     */
    public function hasGlobalVisibility(): bool
    {
        return $this->isSuperAdmin() || $this->isManagementDirector();
    }

    /**
     * Whether this user may never write, even to records they can see.
     */
    public function isReadOnlyGlobalViewer(): bool
    {
        return $this->isManagementDirector() && ! $this->isSuperAdmin();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }
}
