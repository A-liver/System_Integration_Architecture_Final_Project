<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public const ROLE_STUDENT = 'student';

    public const ROLE_STAFF = 'staff';

    protected $fillable = ['name', 'login_id', 'role', 'student_number', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }
}
