<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalDevice extends Model
{
    protected $table = 'laraiot_physical_devices';

    protected $fillable = [
        'identifier',
        'name',
        'ip_address',
        'mac_address',
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
        return $this->hasMany(LogicalDevice::class, 'physical_device_id');
    }
}
