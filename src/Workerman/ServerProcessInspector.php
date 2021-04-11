<?php

namespace Laravel\Octane\Workerman;

use Laravel\Octane\PosixExtension;
use Laravel\Octane\SymfonyProcessFactory;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ServerProcessInspector
{
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SymfonyProcessFactory $processFactory,
        protected PosixExtension $posix
    ) {
    }

    /**
     * Determine if the Workerman server process is running.
     *
     * @return bool
     */
    public function serverIsRunning(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return $masterProcessId && $this->posix->kill($masterProcessId, 0);
    }

    /**
     * Reload the Workerman workers.
     *
     * @return void
     */
    public function reloadServer(): void
    {
        $this->processFactory->createProcess([
            (new PhpExecutableFinder)->find(), 'workerman-server', 'reload',
        ], realpath(__DIR__.'/../../bin'), ['APP_BASE_PATH' => base_path(), 'LARAVEL_OCTANE' => 1], null, null)->run();
    }

    /**
     * Stop the Workerman server.
     *
     * @return bool
     */
    public function stopServer(): bool
    {
        [
            'masterProcessId' => $masterProcessId,
        ] = $this->serverStateFile->read();

        return (bool) $this->posix->kill($masterProcessId, SIGTERM);
    }
}
