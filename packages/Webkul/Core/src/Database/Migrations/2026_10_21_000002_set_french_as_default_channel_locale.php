<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set French as the default locale for existing channels.
     */
    public function up(): void
    {
        $frenchLocaleId = DB::table('locales')->where('code', 'fr')->value('id');

        if (! $frenchLocaleId) {
            return;
        }

        Schema::create('drall_channel_locale_backup', function (Blueprint $table) {
            $table->unsignedInteger('channel_id')->primary();
            $table->unsignedInteger('locale_id');
        });

        $channels = DB::table('channels')->get(['id', 'default_locale_id']);

        foreach ($channels as $channel) {
            DB::table('drall_channel_locale_backup')->insert([
                'channel_id' => $channel->id,
                'locale_id' => $channel->default_locale_id,
            ]);
        }

        DB::table('channels')->update(['default_locale_id' => $frenchLocaleId]);
    }

    /**
     * Restore each channel's previous default locale.
     */
    public function down(): void
    {
        if (! Schema::hasTable('drall_channel_locale_backup')) {
            return;
        }

        $channels = DB::table('drall_channel_locale_backup')->get();

        foreach ($channels as $channel) {
            DB::table('channels')
                ->where('id', $channel->channel_id)
                ->update(['default_locale_id' => $channel->locale_id]);
        }

        Schema::dropIfExists('drall_channel_locale_backup');
    }
};
