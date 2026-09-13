<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Symfony\Component\Process\Process;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseTruncation;

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }

        static::startAppServer();
    }

    /**
     * Serves the app for the browser. `php artisan dusk` has already swapped .env for .env.dusk.local.
     */
    protected static function startAppServer(): void
    {
        $url = parse_url($_ENV['APP_URL'] ?? 'http://127.0.0.1:8123');
        $host = $url['host'];
        $port = (string) $url['port'];

        // PHP's built-in server started directly (not `artisan serve`, which leaves an orphan child process).
        // It runs from public/, so the SQLite path is made absolute, and PHP 8.4 deprecations from
        // Laravel 10 are kept out of the rendered pages.
        $root = dirname(__DIR__);
        $server = new Process(
            [PHP_BINARY, '-d', 'display_errors=0', '-S', "{$host}:{$port}", $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],
            $root.'/public',
            ['DB_DATABASE' => $root.'/database/dusk.sqlite'],
        );
        $server->disableOutput();
        $server->start();

        static::afterClass(fn () => $server->stop());

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (@fsockopen($host, (int) $port)) {
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException("The app server did not start on {$host}:{$port}");
    }

    /**
     * Dusk hardcodes tests/Browser for its debugging output; our test directories are lowercase.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Browser::$storeScreenshotsAt = base_path('tests/browser/screenshots');
        Browser::$storeConsoleLogAt = base_path('tests/browser/console');
        Browser::$storeSourceAt = base_path('tests/browser/source');
    }

    /**
     * Refuses to truncate anything but the dedicated browser test database.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || ! str_ends_with($database, 'dusk.sqlite')) {
            throw new RuntimeException("Browser tests must use database/dusk.sqlite, got {$connection}:{$database}");
        }

        if (! file_exists($database)) {
            touch($database);
        }

        return parent::setUpTraits();
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
