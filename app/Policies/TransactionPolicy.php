<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        // Untuk sekarang: siapa pun yang bisa login boleh view/print.
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if ($user->role === 'ADMIN') {
            return true;
        }

        return $transaction->status === 'DRAFT';
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        // ADMIN cuma boleh delete kalau masih DRAFT.
        return $user->role === 'ADMIN' && $transaction->status === 'DRAFT';
    }

    public function restore(User $user, Transaction $transaction): bool
    {
        return false;
    }

    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
