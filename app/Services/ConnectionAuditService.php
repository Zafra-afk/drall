<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\ConnectionResolverInterface as DatabaseConnectionResolverInterface;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConnectionAuditService
{
    /**
     * Database connection resolver.
     */
    protected DatabaseConnectionResolverInterface $database;

    /**
     * Redis manager.
     */
    protected RedisManager $redis;

    /**
     * Queue factory.
     */
    protected QueueFactory $queue;

    /**
     * Cache repository.
     */
    protected CacheRepository $cache;

    /**
     * Filesystem factory.
     */
    protected FilesystemFactory $filesystem;

    /**
     * Mailer instance.
     */
    protected Mailer $mailer;

    /**
     * Audited connection results.
     */
    protected array $results = [];

    /**
     * Create a new connection audit service instance.
     */
    public function __construct(
        DatabaseConnectionResolverInterface $database,
        RedisManager $redis,
        QueueFactory $queue,
        CacheRepository $cache,
        FilesystemFactory $filesystem,
        Mailer $mailer
    ) {
        $this->database = $database;
        $this->redis = $redis;
        $this->queue = $queue;
        $this->cache = $cache;
        $this->filesystem = $filesystem;
        $this->mailer = $mailer;
    }

    /**
     * Audit every connection node and return results.
     */
    public function audit(): array
    {
        $this->results = [];

        $this->auditDatabases();
        $this->auditRedis();
        $this->auditCache();
        $this->auditQueue();
        $this->auditMail();
        $this->auditFilesystem();

        $healthy = true;

        foreach ($this->results as $result) {
            if (
                $result['status'] === 'unhealthy'
            ) {
                $healthy = false;
                break;
            }
        }

        return [
            'connections' => $this->results,
            'healthy' => $healthy,
            'audited_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Audit all database connections.
     */
    private function auditDatabases(): void
    {
        $connections = config('database.connections', []);

        foreach ($connections as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';

            $this->auditConnection(
                $name,
                'database',
                $driver,
                function () use ($name) {
                    return $this->database->connection($name);
                },
                function ($connection) {
                    $connection->select('SELECT 1');
                }
            );
        }
    }

    /**
     * Audit all redis connections.
     */
    private function auditRedis(): void
    {
        $connections = config('database.redis', []);

        foreach ($connections as $name => $config) {
            if (
                $name === 'client'
                || $name === 'options'
            ) {
                continue;
            }

            $driver = $config['driver'] ?? config('database.redis.client', 'phpredis');

            $this->auditConnection(
                $name,
                'redis',
                $driver,
                function () use ($name) {
                    return $this->redis->connection($name);
                },
                function ($redis) {
                    $redis->ping();
                }
            );
        }
    }

    /**
     * Audit all cache stores.
     */
    private function auditCache(): void
    {
        $stores = config('cache.stores', []);

        foreach ($stores as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';

            if (
                $driver !== 'database'
                && $driver !== 'redis'
                && $driver !== 'memcached'
            ) {
                continue;
            }

            $this->auditConnection(
                $name,
                'cache',
                $driver,
                function () use ($name) {
                    return $this->cache->store($name);
                },
                function ($store) {
                    $store->get('connection_audit_probe');
                }
            );
        }
    }

    /**
     * Audit all queue connections.
     */
    private function auditQueue(): void
    {
        $connections = config('queue.connections', []);

        foreach ($connections as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';

            if (
                $driver !== 'database'
                && $driver !== 'redis'
                && $driver !== 'beanstalkd'
                && $driver !== 'sqs'
            ) {
                continue;
            }

            $this->auditConnection(
                $name,
                'queue',
                $driver,
                function () use ($name) {
                    return $this->queue->connection($name);
                },
                function ($connection) {
                    $connection->size('connection_audit_probe');
                }
            );
        }
    }

    /**
     * Audit mail connection.
     */
    private function auditMail(): void
    {
        $name = config('mail.default');

        if (! $name) {
            return;
        }

        $driver = config('mail.default') ?? 'unknown';

        $this->auditConnection(
            $name,
            'mail',
            $driver,
            function () {
                return $this->mailer;
            },
            function ($mailer) {
                $mailer->getSwiftMailer();
            }
        );
    }

    /**
     * Audit all filesystem disks.
     */
    private function auditFilesystem(): void
    {
        $disks = config('filesystems.disks', []);

        foreach ($disks as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';

            if (
                $driver !== 'local'
                && $driver !== 's3'
                && $driver !== 'ftp'
                && $driver !== 'sftp'
                && $driver !== 'rackspace'
            ) {
                continue;
            }

            $this->auditConnection(
                $name,
                'filesystem',
                $driver,
                function () use ($name) {
                    return $this->filesystem->disk($name);
                },
                function ($disk) {
                    $this->testFilesystemWritable($disk);
                }
            );
        }
    }

    /**
     * Test if filesystem disk is writable.
     */
    private function testFilesystemWritable($disk): void
    {
        $disk->directories('');

        $testFile = 'connection_audit_probe_'.uniqid().'.tmp';

        $disk->put($testFile, 'audit');

        $disk->get($testFile);

        $disk->delete($testFile);
    }

    /**
     * Audit a single connection with timing.
     */
    private function auditConnection(string $name, string $type, string $driver, callable $connect, callable $check): void
    {
        $start = microtime(true);

        try {
            $connection = $connect();
            $check($connection);

            $latency = (int) round((microtime(true) - $start) * 1000);

            $this->results[] = [
                'name' => $name,
                'type' => $type,
                'status' => 'healthy',
                'latency_ms' => $latency,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);

            Log::warning('Connection audit failed.', [
                'name' => $name,
                'type' => $type,
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);

            $this->results[] = [
                'name' => $name,
                'type' => $type,
                'status' => 'unhealthy',
                'latency_ms' => $latency,
                'error' => $e->getMessage(),
            ];
        }
    }
}
