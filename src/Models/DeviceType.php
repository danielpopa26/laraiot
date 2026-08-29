<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $identifier
 * @property string $name
 * @property string|null $description
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LogicalDevice> $logicalDevices
 */
class DeviceType extends Model
{
    protected $table = 'laraiot_device_types';

    protected $fillable = [
        'identifier',
        'name',
        'description',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LogicalDevice, $this>
     */
    public function logicalDevices(): HasMany
    {
        return $this->hasMany(LogicalDevice::class, 'device_type_id');
    }
}
