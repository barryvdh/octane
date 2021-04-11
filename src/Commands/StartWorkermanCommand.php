<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Workerman\ServerProcessInspector;
use Laravel\Octane\Workerman\ServerStateFile;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class StartWorkermanCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InstallsWorkermanDependencies, Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:workerman
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port=8000 : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane Workerman server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @param  \Laravel\Octane\RoadRunner\ServerProcessInspector  $inspector
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return int
     */
    public function handle(ServerProcessInspector $inspector, ServerStateFile $serverStateFile)
    {
        if (! $this->ensureWorkermanPackageIsInstalled()) {
            return 1;
        }

        if ($inspector->serverIsRunning()) {
            $this->error('Workerman server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile);

        $server = tap(new Process([
            (new PhpExecutableFinder)->find(), 'workerman-server', 'start', $serverStateFile->path(),
        ], realpath(__DIR__.'/../../bin'), ['APP_BASE_PATH' => base_path(), 'LARAVEL_OCTANE' => 1], null, null))->start();

        return $this->runServer($server, $inspector, 'workerman');
    }

    /**
     * Write the RoadRunner server state file.
     *
     * @param  \Laravel\Octane\RoadRunner\ServerStateFile  $serverStateFile
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->option('host'),
            'port' => $this->option('port'),
            'workers' => $this->workerCount(),
            'maxRequests' => $this->option('max-requests'),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the number of workers that should be started.
     *
     * @return int
     */
    protected function workerCount()
    {
        return $this->option('workers') == 'auto'
                            ? 0
                            : $this->option('workers', 0);
    }

    /**
     * Get the maximum number of seconds that workers should be allowed to execute a single request.
     *
     * @return string
     */
    protected function maxExecutionTime()
    {
        return config('octane.max_execution_time', '30').'s';
    }

    /**
     * Write the server process output to the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        Str::of($server->getIncrementalOutput())
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! is_array($debug = json_decode($output, true))) {
                    return $this->info($output);
                }

                if (is_array($stream = json_decode($debug['msg'], true))) {
                    return $this->handleStream($stream);
                }

                if ($debug['level'] == 'debug' && isset($debug['remote'])) {
                    [$statusCode, $method, $url] = explode(' ', $debug['msg']);

                    $elapsed = Str::endsWith($debug['elapsed'], 'ms')
                        ? substr($debug['elapsed'], 0, -2)
                        : substr($debug['elapsed'], 0, -1) * 1000;

                    return $this->requestInfo([
                        'method' => $method,
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'duration' => (float) $elapsed,
                    ]);
                }
            });

        Str::of($server->getIncrementalErrorOutput())
            ->explode("\n")
            ->filter()
            ->each(function ($output) {
                if (! Str::contains($output, ['DEBUG', 'INFO', 'WARN'])) {
                    $this->error($output);
                }
            });
    }
}
