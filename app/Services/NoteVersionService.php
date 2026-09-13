<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteVersion;

/**
 * Snapshots the state of a note BEFORE it changes, so past states can be listed and restored.
 */
class NoteVersionService
{
    /**
     * Thresholds default to config/versions.php's own defaults, but the app always resolves this
     * service through the binding in AppServiceProvider, which reads the live config values -
     * these constructor defaults only matter when the service is instantiated directly, e.g. in tests.
     */
    public function __construct(
        private readonly int $percentLoss = 20,
        private readonly int $minCharsLoss = 500,
        private readonly int $minCharsForPercent = 100,
    ) {
    }

    /**
     * Snapshots the note's current stored title/content, unless it is identical to the
     * note's latest version (never create back-to-back duplicate versions).
     */
    public function snapshot(Note $note, string $reason, ?string $label = null, bool $pinned = false): ?NoteVersion
    {
        $hash = self::hash($note->title, $note->content);

        $latest = $note->versions()->latest('id')->first();
        if ($latest && $latest->content_hash === $hash) {
            return null;
        }

        return $note->versions()->create([
            'title' => $note->title,
            'content' => $note->content,
            'content_hash' => $hash,
            'reason' => $reason,
            'label' => $label,
            'pinned' => $pinned,
        ]);
    }

    /**
     * Identity hash used to dedupe versions: not a security hash, just "is this the same content".
     */
    public static function hash(?string $title, ?string $content): string
    {
        return hash('sha256', ($title ?? '')."\0".($content ?? ''));
    }

    /**
     * Whether replacing $oldContent with $newContent is a "substantial" change: the plain text
     * (tags stripped, whitespace normalized) shrinks by more than the configured percentage or
     * character count, or the note loses images or tables.
     */
    public function isSubstantialChange(?string $oldContent, ?string $newContent): bool
    {
        $oldLength = mb_strlen(self::plainText($oldContent));
        $newLength = mb_strlen(self::plainText($newContent));
        $lost = max(0, $oldLength - $newLength);

        // A tiny edit to a short note can lose a huge percentage of it without losing much text
        // (e.g. one word out of forty characters): the percent rule only kicks in once enough
        // characters are actually gone, so it does not pin a version on every small edit.
        if ($lost >= $this->minCharsForPercent && $lost > $oldLength * ($this->percentLoss / 100)) {
            return true;
        }

        if ($lost > $this->minCharsLoss) {
            return true;
        }

        if (self::countTag($newContent, 'img') < self::countTag($oldContent, 'img')) {
            return true;
        }

        if (self::countTag($newContent, 'table') < self::countTag($oldContent, 'table')) {
            return true;
        }

        return false;
    }

    private static function plainText(?string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
    }

    private static function countTag(?string $html, string $tag): int
    {
        return preg_match_all('/<'.preg_quote($tag, '/').'[\s\/>]/i', (string) $html);
    }
}
