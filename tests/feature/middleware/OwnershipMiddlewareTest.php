<?php

use App\Http\Middleware\NotebookVerifyOwnership;
use App\Http\Middleware\NoteVerifyOwnership;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

function requestWithRoute(array $parameters): Request
{
    $request = Request::create('/test');
    $route = (new Route('GET', '/test', []))->bind($request);
    foreach ($parameters as $name => $value) {
        $route->setParameter($name, $value);
    }
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function statusOf(callable $callback): ?int
{
    try {
        $callback();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return null;
}

it('lets the owner through to the notebook', function () {
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $this->actingAs($user);

    $response = (new NotebookVerifyOwnership())->handle(
        requestWithRoute(['notebookId' => $notebook->id]),
        fn () => response('passed')
    );

    expect($response->getContent())->toBe('passed');
});

it('lets the owner through to the note', function () {
    $user = User::factory()->create();
    $note = Note::factory()->for(Notebook::factory()->ownedBy($user))->create();
    $this->actingAs($user);

    $response = (new NoteVerifyOwnership())->handle(
        requestWithRoute(['noteId' => $note->id]),
        fn () => response('passed')
    );

    expect($response->getContent())->toBe('passed');
});

it('stops requests without a notebook id', function () {
    $this->actingAs(User::factory()->create());

    expect(statusOf(fn () => (new NotebookVerifyOwnership())->handle(requestWithRoute([]), fn () => response('passed'))))
        ->toBe(403);
});

it('stops requests without a note id', function () {
    $this->actingAs(User::factory()->create());

    expect(statusOf(fn () => (new NoteVerifyOwnership())->handle(requestWithRoute([]), fn () => response('passed'))))
        ->toBe(404);
});
