<?php

namespace App\Models;

use App\Support\Permission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'permissions',
    'user_type',
    'avatar',
    'plex_id',
    'jellyfin_id',
    'last_login_at',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'integer',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasPermission(Permission::ADMIN);
    }

    /**
     * @param  int|array<int>  $permissions
     */
    public function hasPermission(int|array $permissions, string $type = 'and'): bool
    {
        return Permission::check($permissions, (int) $this->permissions, $type);
    }

    public function toInertia(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'permissions' => (int) $this->permissions,
            'userType' => $this->user_type,
            'avatar' => $this->avatar,
            'isAdmin' => $this->isAdmin(),
            'isActive' => $this->is_active,
            'can' => [
                'manageSettings' => $this->hasPermission([Permission::ADMIN, Permission::MANAGE_SETTINGS], 'or'),
                'manageUsers' => $this->hasPermission([Permission::ADMIN, Permission::MANAGE_USERS], 'or'),
                'grabMovie' => $this->hasPermission([Permission::ADMIN, Permission::REQUEST, Permission::REQUEST_MOVIE], 'or'),
                'grabTv' => $this->hasPermission([Permission::ADMIN, Permission::REQUEST, Permission::REQUEST_TV], 'or'),
                'viewActivity' => $this->hasPermission([Permission::ADMIN, Permission::VIEW_ACTIVITY, Permission::MANAGE_REQUESTS], 'or'),
                'controlDownloads' => $this->hasPermission([Permission::ADMIN, Permission::MANAGE_REQUESTS], 'or'),
            ],
        ];
    }
}
