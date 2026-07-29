<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
