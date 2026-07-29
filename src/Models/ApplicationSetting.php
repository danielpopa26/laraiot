<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    protected $table = 'laraiot_settings';

    protected $fillable = [
        'application_mode',
        'polling_interval',
        'timezone',
        'date_format',
        'time_format',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polling_interval' => 'integer',
        ];
    }

    public static function current(): self
    {
        $mode = config('laraiot.mode', 'polling');

        if (
            ! is_string($mode)
            || ! in_array($mode, ['polling', 'websocket'], true)
        ) {
            $mode = 'polling';
        }

        $timezone = config('laraiot.timezone')
            ?? config('app.timezone', 'UTC');

        if (! is_string($timezone) || $timezone === '') {
            $timezone = 'UTC';
        }

        $dateFormat = config('laraiot.date_format', 'd M Y');

        if (! is_string($dateFormat) || $dateFormat === '') {
            $dateFormat = 'd M Y';
        }

        $timeFormat = config('laraiot.time_format', 'H:i:s');

        if (! is_string($timeFormat) || $timeFormat === '') {
            $timeFormat = 'H:i:s';
        }

        return self::query()->firstOrCreate([], [
            'application_mode' => $mode,
            'polling_interval' => max(
                1,
                (int) config('laraiot.polling.interval', 10),
            ),
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
        ]);
    }
}
