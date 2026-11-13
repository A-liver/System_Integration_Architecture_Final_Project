<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemSettings
{
    private const LEGACY_ONLINE = 'legacy_online';

    public function legacyOnline(): bool
    {
        return DB::table('settings')->where('key', self::LEGACY_ONLINE)->value('value') !== '0';
    }

    public function setLegacyOnline(bool $online): void
    {
        DB::table('settings')->updateOrInsert(['key' => self::LEGACY_ONLINE], ['value' => $online ? '1' : '0']);
    }
}
