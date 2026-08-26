<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $physical_device_id
 * @property int $device_type_id
 * @property string $identifier
 * @property string $name
 * @property mixed $last_value
 * @property string|null $unit
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PhysicalDevice|null $physicalDevice
 * @property-read DeviceType|null $deviceType
 * @property-read Collection<int, MqttTopic> $mqttTopics
 * @property-read Collection<int, ActivityLog> $activityLogs
 */
class LogicalDevice extends Model
{
    protected $table = 'laraiot_logical_devices';

    protected $fillable = [
        'physical_device_id',
        'device_type_id',
        'identifier',
        'name',
        'last_value',
        'unit',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_value' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PhysicalDevice, $this>
     */
    public function physicalDevice(): BelongsTo
    {
        return $this->belongsTo(PhysicalDevice::class, 'physical_device_id');
    }

    /**
     * @return BelongsTo<DeviceType, $this>
     */
    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class, 'device_type_id');
    }

    /**
     * @return HasMany<MqttTopic, $this>
     */
    public function mqttTopics(): HasMany
    {
        return $this->hasMany(MqttTopic::class, 'logical_device_id');
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'logical_device_id');
    }
}
