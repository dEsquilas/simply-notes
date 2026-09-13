<?php

namespace App\Policies;

class NotePolicy
{

    public function verifyOwnership($user, $note)
    {
        // The notebook may be trashed while the note is not: ownership still applies
        $notebook = $note->notebook()->withTrashed()->first();

        if(!$notebook || $notebook->owner != $user->id)
            return false;
        else
            return true;

    }

}
