<?php

namespace App\Policies;

class NotePolicy
{

    public function verifyOwnership($user, $note)
    {
        $notebook = $note->notebook()->first();

        if(!$notebook || $notebook->owner != $user->id)
            return false;
        else
            return true;

    }

}
