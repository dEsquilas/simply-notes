<?php

use Symfony\Component\Finder\Finder;

/**
 * Names of the it()/test() calls in a file whose closure never calls visit() the way the browser plugin detects it:
 * a plain visit( preceded by whitespace. Helpers that call visit() for the test are not enough.
 *
 * @return list<string>
 */
function browserTestsWithoutVisit(string $file): array
{
    $tokens = token_get_all(file_get_contents($file));
    $missing = [];

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $isTestCall = is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && in_array($tokens[$i][1], ['it', 'test'], true)
            && ($tokens[$i + 1] ?? null) === '(';

        if (! $isTestCall) {
            continue;
        }

        $name = trim($tokens[$i + 2][1] ?? '', '\'"');

        // Walk to the closure body and through its matching braces
        for ($j = $i; $j < $count && ! (is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION); $j++);
        for (; $j < $count && $tokens[$j] !== '{'; $j++);

        $depth = 0;
        $callsVisit = false;
        for (; $j < $count; $j++) {
            $token = $tokens[$j];
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($token === '}' && --$depth === 0) {
                break;
            }

            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'visit'
                && ($tokens[$j + 1] ?? null) === '(' && is_array($tokens[$j - 1]) && $tokens[$j - 1][0] === T_WHITESPACE) {
                $callsVisit = true;
            }
        }

        if (! $callsVisit) {
            $missing[] = $name;
        }

        $i = $j;
    }

    return $missing;
}

it('makes every browser test call visit() in its own body', function () {
    $files = Finder::create()->files()->in(dirname(__DIR__).'/browser')->name('*Test.php');

    $missing = [];
    foreach ($files as $file) {
        foreach (browserTestsWithoutVisit($file->getPathname()) as $test) {
            $missing[] = $file->getFilename().': '.$test;
        }
    }

    expect($files)->not->toBeEmpty()
        ->and($missing)->toBe([], 'These browser tests would not start the browser when they run first');
});
