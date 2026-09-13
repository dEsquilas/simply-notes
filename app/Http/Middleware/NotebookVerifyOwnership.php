<?php

namespace App\Http\Middleware;

use App\Models\Notebook;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class NotebookVerifyOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $notebookId = $request->route('notebookId');
        // Restore and permanent delete operate on trashed notebooks too; other actions decide for themselves what to do with a trashed one
        $notebook = Notebook::withTrashed()->find($notebookId);
        if(!$notebook)
            return abort(404, 'Notebook not found');

        if(Gate::allows('verifyOwnership', $notebook))
            return $next($request);
        else
            return abort(403, 'Notebook not found');
    }
}
