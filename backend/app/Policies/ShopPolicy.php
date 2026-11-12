<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function update(User $user, Shop $shop): bool
    {
        return $user->account_status === 'active' && $shop->owner_id === $user->id;
    }

    public function review(User $user, Shop $shop): bool
    {
        return $user->role === 'admin' && $user->account_status === 'active';
    }
}
