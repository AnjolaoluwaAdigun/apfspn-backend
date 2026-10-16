<?php

namespace Tests\Feature\AdminMembers;

use App\Models\MemberIdSequence;
use App\Models\Membership;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Auth\InteractsWithAuth;
use Tests\TestCase;

#[Group('concurrency')]
class MemberIdConcurrencyTest extends TestCase
{
    use InteractsWithAdminMembers, InteractsWithAuth;

    private const CONNECTION = 'concurrency';

    private const WORKER = 'tests/Concurrency/approve-member.php';

    private const WORKER_TIMEOUT_SECONDS = 60;

    private ?string $originalConnection = null;

    private ?string $sqlitePath = null;

    private ?string $startSignal = null;

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function test_concurrent_approvals_issue_unique_sequential_member_ids(): void
    {
        try {
            $workerEnv = $this->prepareDatabase();

            $lagos = array_map(fn () => $this->pendingMember(['state_code' => 'LA'])->uuid, range(1, 4));
            $kano = array_map(fn () => $this->pendingMember(['state_code' => 'KN'])->uuid, range(1, 2));
            MemberIdSequence::where('state_code', 'KN')->delete();

            $results = $this->runWorkers([...$lagos, ...$kano, $lagos[0], $lagos[0]], $workerEnv);

            foreach ($results as $result) {
                $this->assertTrue($result['ok'] ?? false, 'Worker failed: '.json_encode($result));
            }

            $issued = Membership::whereNotNull('member_id')->pluck('member_id', 'user_uuid');

            $lagosIds = array_map(fn (string $uuid) => $issued[$uuid], $lagos);
            sort($lagosIds);
            $kanoIds = array_map(fn (string $uuid) => $issued[$uuid], $kano);
            sort($kanoIds);

            $this->assertSame(['APFSPNLA001', 'APFSPNLA002', 'APFSPNLA003', 'APFSPNLA004'], $lagosIds);
            $this->assertSame(['APFSPNKN001', 'APFSPNKN002'], $kanoIds);
            $this->assertCount(6, $issued->unique());

            foreach (array_filter($results, fn (array $result) => $result['member_uuid'] === $lagos[0]) as $result) {
                $this->assertSame($issued[$lagos[0]], $result['member_id']);
            }

            $this->assertSame(4, MemberIdSequence::where('state_code', 'LA')->value('last_sequence'));
            $this->assertSame(2, MemberIdSequence::where('state_code', 'KN')->value('last_sequence'));
        } finally {
            $this->cleanUp();
        }
    }

    private function prepareDatabase(): array
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

        config(['database.connections.'.self::CONNECTION => $connection]);
        $this->originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);

        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);

        return $workerEnv + [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];
    }

    private function runWorkers(array $memberUuids, array $workerEnv): array
    {
        $this->startSignal = sys_get_temp_dir().DIRECTORY_SEPARATOR.'apfspn-concurrency-start-'.bin2hex(random_bytes(6));
        $env = $workerEnv + getenv();
        $workers = [];

        foreach ($memberUuids as $uuid) {
            $process = proc_open(
                [PHP_BINARY, base_path(self::WORKER), $uuid, $this->startSignal],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $env,
            );

            $this->assertIsResource($process, 'Could not start a worker process.');
            $workers[] = [$process, $pipes];
        }

        $deadline = microtime(true) + self::WORKER_TIMEOUT_SECONDS;

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

            return json_decode($output, true) ?? ['ok' => false, 'member_uuid' => null, 'error' => trim($output."\n".$errors)];
        }, $workers);
    }

    private function cleanUp(): void
    {
        if ($this->originalConnection !== null) {
            if (env('CONCURRENCY_MYSQL_DATABASE')) {
                rescue(fn () => Artisan::call('db:wipe', ['--database' => self::CONNECTION, '--force' => true]), report: false);
            }

            DB::purge(self::CONNECTION);
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
