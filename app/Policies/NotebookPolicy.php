<?php

namespace App\Policies;

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotebookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Notebook $notebook): bool
    {
        return $this->verifyOwnership($user, $notebook);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {

    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Notebook $notebook): bool
    {
        return $this->verifyOwnership($user, $notebook);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Notebook $notebook): bool
    {
        return $this->verifyOwnership($user, $notebook);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Notebook $notebook): bool
    {
        return $this->verifyOwnership($user, $notebook);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Notebook $notebook): bool
    {
        return $this->verifyOwnership($user, $notebook);
    }

    public function verifyOwnership($user, $notebook)
    {

        if($notebook->owner != $user->id)
            return false;
        else
            return true;

    }

}
