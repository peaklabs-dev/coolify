<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class OpenApi extends Command
{
    protected $signature = 'openapi';

    protected $description = 'Generate OpenApi json and yaml files.';

    public function handle()
    {
        echo "Generating OpenAPI YAML.\n";
        $process = Process::run([
            '/var/www/html/vendor/bin/openapi',
            'app',
            '-o',
            'openapi.yaml',
            '--version',
            '3.1.0', // https://github.com/OAI/OpenAPI-Specification/releases
        ]);
        echo $process->errorOutput();
        echo $process->output();

        echo "\nGenerating OpenAPI JSON.\n";
        $process = Process::run([
            '/var/www/html/vendor/bin/openapi',
            'app',
            '-o',
            'openapi.json',
            '--version',
            '3.1.0', // https://github.com/OAI/OpenAPI-Specification/releases
        ]);
        echo $process->errorOutput();
        echo $process->output();
    }
}
