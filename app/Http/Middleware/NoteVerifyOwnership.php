<?php

namespace App\Http\Middleware;

use App\Models\Note;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class NoteVerifyOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $noteId = $request->route('noteId');
        // Restore and permanent delete operate on trashed notes too; other actions decide for themselves what to do with a trashed one
        $note = Note::withTrashed()->find($noteId);
        if(!$note)
            return abort(404, 'Note not found');

        // Throws a 403 AuthorizationException when the user does not own the note
        Gate::authorize('verifyOwnership', $note);

        return $next($request);
    }
}
