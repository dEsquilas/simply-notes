<?php

namespace App\Policies;

use App\Models\Notebook;
use App\Models\User;

class NotebookPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Notebook $notebook): bool
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
