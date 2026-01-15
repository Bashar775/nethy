<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class RolePloicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {

    }

    public function createUser(User $user): bool
    {
        Log::error($user);
        return $user->isAdmin();
    }
}
