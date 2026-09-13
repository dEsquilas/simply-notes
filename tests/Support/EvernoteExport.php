<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Builds Evernote HTML exports and zipped uploads for import tests.
 */
class EvernoteExport
{
    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @param array<string, string> $meta itemprop => content, e.g. ['created' => '2024-01-02 10:00:00'] */
    public static function html(string $title, string $body, array $meta = []): string
    {
        $metaTags = '';
        foreach ($meta as $name => $content) {
            $metaTags .= '<meta itemprop="'.$name.'" content="'.$content.'">';
        }

        return '<html><head>'.$metaTags.'</head><body><h1>'.$title.'</h1>'
            .'<en-note class="peso" style="white-space: inherit;">'.$body.'</en-note></body></html>';
    }

    public static function png(): string
    {
        return base64_decode(self::PNG);
    }

    /** @param array<string, string> $files path inside the zip => contents */
    public static function zip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'export.zip', 'application/zip', null, true);
    }
}
