<?php

namespace Laravel\Octane\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Workerman\Protocols\Http;
use Workerman\Psr7\Response;
use Workerman\Psr7\ServerRequest;
use Workerman\Worker;

class RunWorkermanCommand extends Command
{

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:workerman-run
                    {--host=127.0.0.1 : The IP address the server should bind to}
                    {--port=8000 : The port the server should be available on}
                    {--workers=4 : The number of workers that should be available to handle requests}
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
     * @return int
     */
    public function handle()
    {
        $workers =  $this->option('workers');
        $host = 'http://' . $this->option('host') . ': ' . $this->option('port');

        $this->info('Starting Workerman on ' . $host . ' with ' . $workers . ' workers');

        $worker = new Worker($host);
        $worker->count = $workers;

        Http::requestClass(Workerman\Psr7\ServerRequest::class);
        $worker->onMessage = function($connection, ServerRequest $request)
        {
            $response = new Response(200, [], 'hello world');
            $connection->send($response);
        };

        Worker::runAll();

        $this->warn('Finished');
    }
}
