<?php

use Illuminate\Support\Facades\DB;
use Webkul\Installer\Helpers\DatabaseManager;

// ============================================================================
// Installed State
// ============================================================================

it('should detect installation when environment variables replace the env file', function () {
    $basePath = app()->basePath();

    $connection = Mockery::mock();
    $connection->shouldReceive('getPDO')->once();
    $connection->shouldReceive('getDatabaseName')->once()->andReturn('testing');

    $schema = Mockery::mock();
    $schema->shouldReceive('hasTable')->once()->with('admins')->andReturn(true);
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);

    $admins = Mockery::mock();
    $admins->shouldReceive('count')->once()->andReturn(1);

    DB::shouldReceive('connection')->andReturn($connection);
    DB::shouldReceive('table')->once()->with('admins')->andReturn($admins);

    try {
        app()->setBasePath(sys_get_temp_dir().'/bagisto-install-'.bin2hex(random_bytes(8)));

        expect(file_exists(base_path('.env')))->toBeFalse()
            ->and(app(DatabaseManager::class)->isInstalled())->toBeTrue();
    } finally {
        app()->setBasePath($basePath);
    }
});
