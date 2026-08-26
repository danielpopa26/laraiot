<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $application_mode
 * @property int $polling_interval
 * @property string $timezone
 * @property string $date_format
 * @property string $time_format
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApplicationSetting extends Model
{
    public const int SINGLETON_ID = 1;

    public const string MODE_POLLING = 'polling';

    public const string MODE_WEBSOCKET = 'websocket';

    public const int MIN_POLLING_INTERVAL = 1;

    public const int MAX_POLLING_INTERVAL = 3600;

    /**
     * @var array<string, string>
     */
    public const array DATE_FORMATS = [
        'd M Y' => '28 Jul 2026',
        'd/m/Y' => '28/07/2026',
        'm/d/Y' => '07/28/2026',
        'Y-m-d' => '2026-07-28',
        'j F Y' => '28 July 2026',
    ];

    /**
     * @var array<string, string>
     */
    public const array TIME_FORMATS = [
        'H:i:s' => '14:35:20',
        'H:i' => '14:35',
        'h:i:s A' => '02:35:20 PM',
        'h:i A' => '02:35 PM',
    ];

    protected $table = 'laraiot_settings';

    protected $fillable = [
        'application_mode',
        'polling_interval',
        'timezone',
        'date_format',
        'time_format',
    ];

    public static function current(): self
    {
        return static::query()->findOrFail(
            self::SINGLETON_ID,
        );
    }

    public function usesWebsocket(): bool
    {
        return $this->application_mode === self::MODE_WEBSOCKET;
    }

    /**
     * @return array<string, int|string>
     */
    public static function defaults(): array
    {
        return [
            'application_mode' => self::MODE_POLLING,
            'polling_interval' => 10,
            'timezone' => 'UTC',
            'date_format' => 'd M Y',
            'time_format' => 'H:i:s',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polling_interval' => 'integer',
        ];
    }
}
