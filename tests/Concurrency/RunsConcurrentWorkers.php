<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait RunsConcurrentWorkers
{
    private string $concurrencyConnection = 'concurrency';

    private int $workerTimeoutSeconds = 60;

    private ?string $originalConnection = null;

    private ?string $sqlitePath = null;

    private ?string $startSignal = null;

    protected function tearDown(): void
    {
        $this->cleanUpWorkers();

        parent::tearDown();
    }

    protected function prepareConcurrencyDatabase(): array
    {
        $mysqlDatabase = env('CONCURRENCY_MYSQL_DATABASE');

        if ($mysqlDatabase) {
            $connection = ['database' => $mysqlDatabase] + config('database.connections.mysql');
            $workerEnv = ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $mysqlDatabase];
        } else {
            $this->sqlitePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'apfspn-concurrency-'.bin2hex(random_bytes(6)).'.sqlite';
            touch($this->sqlitePath);
            $connection = ['database' => $this->sqlitePath] + config('database.connections.sqlite');
            $workerEnv = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->sqlitePath];
        }

        config(['database.connections.'.$this->concurrencyConnection => $connection]);
        $this->originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($this->concurrencyConnection);

        Artisan::call('migrate:fresh', ['--database' => $this->concurrencyConnection, '--force' => true]);

        return $workerEnv + [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
    }

    protected function runWorkers(string $script, array $argumentSets, array $workerEnv): array
    {
        $this->startSignal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'apfspn-concurrency-start-'.bin2hex(random_bytes(6));
        $env = $workerEnv + getenv();
        $workers = [];

        foreach ($argumentSets as $arguments) {
            $process = proc_open(
                [PHP_BINARY, base_path($script), $this->startSignal, ...$arguments],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $env,
            );

            $this->assertIsResource($process, 'Could not start a worker process.');
            $workers[] = [$process, $pipes];
        }

        $deadline = microtime(true) + $this->workerTimeoutSeconds;

        while (count(glob($this->startSignal.'.ready.*')) < count($workers) && microtime(true) < $deadline) {
            usleep(10_000);
        }

        touch($this->startSignal);

        return array_map(function (array $worker) {
            [$process, $pipes] = $worker;
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return json_decode($output, true) ?? ['ok' => false, 'error' => trim($output."\n".$errors)];
        }, $workers);
    }

    protected function cleanUpWorkers(): void
    {
        if ($this->originalConnection !== null) {
            if (env('CONCURRENCY_MYSQL_DATABASE')) {
                rescue(fn () => Artisan::call('db:wipe', ['--database' => $this->concurrencyConnection, '--force' => true]), report: false);
            }

            DB::purge($this->concurrencyConnection);
            DB::setDefaultConnection($this->originalConnection);
            $this->originalConnection = null;
        }

        if ($this->startSignal !== null) {
            foreach ([$this->startSignal, ...glob($this->startSignal.'.ready.*')] as $file) {
                @unlink($file);
            }

            $this->startSignal = null;
        }

        if ($this->sqlitePath !== null) {
            foreach (['', '-journal', '-wal', '-shm'] as $suffix) {
                @unlink($this->sqlitePath.$suffix);
            }

            $this->sqlitePath = null;
        }
    }
}
