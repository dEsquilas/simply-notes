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
        $note = Note::find($noteId);
        if(!$note)
            return abort(404, 'Note not found');

        if(Gate::authorize('verifyOwnership', $note))
            return $next($request);
        else
            return abort(403, 'Note not found');

        return $next($request);
    }
}
