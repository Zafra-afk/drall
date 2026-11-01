<?php

namespace App\Console\Commands;

use App\Services\ConnectionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditConnections extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'audit:connections';

    /**
     * The console command description.
     */
    protected $description = 'Audit every application connection node and log the results';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $audit = app(ConnectionAuditService::class);
        $result = $audit->audit();

        $rows = [];

        foreach ($result['connections'] as $connection) {
            $rows[] = [
                $connection['name'],
                $connection['type'],
                $connection['status'],
                $connection['latency_ms'] ?? 'n/a',
                $connection['error'] ? mb_substr($connection['error'], 0, 80) : null,
            ];
        }

        $this->table(
            ['Name', 'Type', 'Status', 'Latency (ms)', 'Error'],
            $rows
        );

        $summary = $result['healthy']
            ? 'All connections are healthy.'
            : 'Some connections are unhealthy.';

        $this->info($summary);

        Log::info('Connection audit completed.', [
            'healthy' => $result['healthy'],
            'audited_at' => $result['audited_at'],
            'count' => count($result['connections']),
        ]);

        if (! $result['healthy']) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
