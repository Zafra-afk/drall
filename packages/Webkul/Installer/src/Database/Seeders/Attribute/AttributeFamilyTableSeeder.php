<?php

namespace Webkul\Installer\Database\Seeders\Attribute;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Concerns\SyncsPostgresSequences;

class AttributeFamilyTableSeeder extends Seeder
{
    use SyncsPostgresSequences;

    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::statement($this->foreignKeySql(false));

        DB::table('attribute_families')->delete();

        $defaultLocale = $parameters['default_locale'] ?? config('app.locale');

        DB::table('attribute_families')->insert([
            [
                'id' => 1,
                'code' => 'default',
                'name' => trans('installer::app.seeders.attribute.attribute-families.default', [], $defaultLocale),
                'status' => 0,
                'is_user_defined' => 1,
            ],
        ]);

        DB::statement($this->foreignKeySql(true));

        $this->syncPostgresSequences(['attribute_families']);
    }

    /**
     * Get the database-specific SQL for enabling or disabling foreign key checks.
     */
    protected function foreignKeySql(bool $enable): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return 'SET FOREIGN_KEY_CHECKS='.($enable ? 1 : 0);
        }

        if ($driver === 'pgsql') {
            return 'SET session_replication_role = '.($enable ? "'DEFAULT'" : "'replica'");
        }

        if ($driver === 'sqlite') {
            return 'PRAGMA foreign_keys = '.($enable ? 'ON' : 'OFF');
        }

        return 'SELECT 1';
    }
}
