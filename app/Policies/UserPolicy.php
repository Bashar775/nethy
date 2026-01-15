<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        
    }

    public function createUser(User $user): bool
    {
        Log::error('hi from policy - create user');
        return $user->isAdmin();
    }
    public function browseUsers(User $user): bool
    {
        Log::error('hi from policy - browse users');
        return $user->isAdmin();
    }
    public function createCategory(User $user): bool
    {
        Log::error('hi from policy - create category');
        return $user->isAdmin() || $user->isInventory();
    }
    public function updateCategory(User $user): bool
    {
        Log::error('hi from policy - update category');
        return $user->isAdmin() || $user->isInventory();
    }
    public function createProduct(User $user): bool
    {
        Log::error('hi from policy - create product');
        return $user->isAdmin() || $user->isInventory();
    }
    public function viewAnyOrder(User $user): bool
    {
        Log::error('hi from policy - viewAnyOrder');
        return $user->isAdmin() || $user->isInventory();
    }
    public function createOrder(User $user): bool
    {
        Log::error('hi from policy - createOrder');
        //what to do here?
        return true;
    }
    public function updateOrder(User $user): bool
    {
        Log::error('hi from policy - updateOrder');
        return $user->isAdmin() || $user->isInventory();
    }
    public function supplierOrder(User $user): bool
    {
        Log::error('hi from policy - supplierOrder');
        return $user->isAdmin() || $user->isInventory();
    }
    public function customerInvoice(User $user): bool
    {
        Log::error('hi from policy - customerInvoice');
        return $user->isAdmin() || $user->isInventory();
    }
    public function SupplierInvoice(User $user): bool
    {
        Log::error('hi from policy - SupplierInvoice');
        return $user->isAdmin() || $user->isInventory();
    }
    public function supplierInfo(User $user): bool
    {
        Log::error('hi from policy - supplierInfo');
        return $user->isAdmin() || $user->isInventory();
    }
    public function payment(User $user): bool
    {
        Log::error('hi from policy - payment');
        return $user->isAdmin() || $user->isInventory();
    }
}
