<?php

use App\Services\NoteVersionService;

beforeEach(function () {
    // Matches config/versions.php's defaults, without booting the app just to read them
    $this->service = new NoteVersionService(percentLoss: 20, minCharsLoss: 500, minCharsForPercent: 100);
});

it('hashes identical title and content the same way', function () {
    expect(NoteVersionService::hash('Title', '<p>Body</p>'))
        ->toBe(NoteVersionService::hash('Title', '<p>Body</p>'));
});

it('hashes different title or content differently', function () {
    $base = NoteVersionService::hash('Title', '<p>Body</p>');

    expect(NoteVersionService::hash('Other', '<p>Body</p>'))->not->toBe($base)
        ->and(NoteVersionService::hash('Title', '<p>Other</p>'))->not->toBe($base)
        ->and(NoteVersionService::hash(null, null))->toBe(NoteVersionService::hash(null, null));
});

it('does not flag identical content as a substantial change', function () {
    expect($this->service->isSubstantialChange('<p>Same text</p>', '<p>Same text</p>'))->toBeFalse();
});

it('does not flag growing content as a substantial change', function () {
    expect($this->service->isSubstantialChange('<p>Short</p>', '<p>Short and now much longer</p>'))->toBeFalse();
});

// percent_loss = 20, min_chars_for_percent = 100 (matches config/versions.php's defaults)
// Old length is large enough here that the lost characters clear min_chars_for_percent on their own
it('does not flag a loss of exactly the percent threshold', function () {
    $old = '<p>'.str_repeat('a', 1_000).'</p>';
    $new = '<p>'.str_repeat('a', 800).'</p>'; // loses exactly 200 chars = 20%

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('flags a loss of more than the percent threshold', function () {
    $old = '<p>'.str_repeat('a', 1_000).'</p>';
    $new = '<p>'.str_repeat('a', 799).'</p>'; // loses 201 chars = 20.1%

    expect($this->service->isSubstantialChange($old, $new))->toBeTrue();
});

// min_chars_for_percent = 100: a huge percentage loss on a short note never counts on its own
it('does not flag a large percentage loss when too few characters are actually lost', function () {
    $old = '<p>'.str_repeat('a', 40).'</p>';
    $new = '<p>'.str_repeat('a', 10).'</p>'; // loses 30 chars = 75%, but 30 < min_chars_for_percent

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('does not flag a percentage loss of one character short of min_chars_for_percent', function () {
    $old = '<p>'.str_repeat('a', 200).'</p>';
    $new = '<p>'.str_repeat('a', 101).'</p>'; // loses 99 chars = 49.5%, but 99 < 100

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('flags a percentage loss of exactly min_chars_for_percent characters', function () {
    $old = '<p>'.str_repeat('a', 200).'</p>';
    $new = '<p>'.str_repeat('a', 100).'</p>'; // loses exactly 100 chars = 50%

    expect($this->service->isSubstantialChange($old, $new))->toBeTrue();
});

// min_chars_loss = 500 (matches config/versions.php's default)
it('does not flag a loss of exactly the character threshold', function () {
    $old = '<p>'.str_repeat('a', 10_000).'</p>';
    $new = '<p>'.str_repeat('a', 10_000 - 500).'</p>';

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('flags a loss of more than the character threshold', function () {
    $old = '<p>'.str_repeat('a', 10_000).'</p>';
    $new = '<p>'.str_repeat('a', 10_000 - 501).'</p>';

    expect($this->service->isSubstantialChange($old, $new))->toBeTrue();
});

it('flags a decrease in the number of images even without much text loss', function () {
    $old = '<p>text</p><img src="a"><img src="b">';
    $new = '<p>text</p><img src="a">';

    expect($this->service->isSubstantialChange($old, $new))->toBeTrue();
});

it('does not flag images that stay the same or increase', function () {
    $old = '<p>text</p><img src="a">';
    $new = '<p>text</p><img src="a"><img src="b">';

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('flags a decrease in the number of tables even without much text loss', function () {
    $old = '<table></table><table></table>';
    $new = '<table></table>';

    expect($this->service->isSubstantialChange($old, $new))->toBeTrue();
});

it('normalizes whitespace and strips tags before comparing lengths', function () {
    $old = "<p>Some   <strong>bold</strong>\n\n text here that is long enough to matter for the percentage math</p>";
    $new = '<p>Some bold text here that is long enough to matter for the percentage math</p>';

    expect($this->service->isSubstantialChange($old, $new))->toBeFalse();
});

it('treats null content as empty text', function () {
    expect($this->service->isSubstantialChange(null, null))->toBeFalse();
});
