<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_employee',
        'phone',
        'address',
    ];

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
        ];
    }
    public function orders(){
        return $this->hasMany(Order::class);
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
    public function isAdmin(){
        return $this->is_employee && $this->roles()->where('type','admin')->exists();
    }
    public function isEmployee(){
        return $this->is_employee;
    }
    public function isSales(){
        return $this->is_employee && $this->roles()->where('type', 'sales')->exists();
    }
    public function isAccounting(){
        return $this->is_employee && $this->roles()->where('type', 'accounting')->exists();
    }
    public function isInventory(){
        return $this->is_employee && $this->roles()->where('type', 'inventory')->exists();
    }
    public function isReporting(){
        return $this->is_employee && $this->roles()->where('type', 'reporting')->exists();
    }
    public function assignRoles(array $roles)
    {
        $roleIds = Role::whereIn('type', $roles)->pluck('id')->toArray();
        $this->roles()->sync($roleIds);
    }
}
