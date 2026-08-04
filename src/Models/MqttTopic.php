<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $purpose
 * @property string $topic
 * @property int $qos
 * @property bool $retain
 * @property bool $is_enabled
 */
final class MqttTopic extends Model
{
    protected $table = 'laraiot_mqtt_topics';

    protected $fillable = [
        'physical_device_id',
        'logical_device_id',
        'purpose',
        'topic',
        'payload_mapping',
        'qos',
        'retain',
        'is_enabled',
        'last_payload',
        'last_value',
        'last_received_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_mapping' => 'array',
            'qos' => 'integer',
            'retain' => 'boolean',
            'is_enabled' => 'boolean',
            'last_value' => 'array',
            'last_received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PhysicalDevice, $this>
     */
    public function physicalDevice(): BelongsTo
    {
        return $this->belongsTo(
            PhysicalDevice::class,
            'physical_device_id',
        );
    }

    /**
     * @return BelongsTo<LogicalDevice, $this>
     */
    public function logicalDevice(): BelongsTo
    {
        return $this->belongsTo(
            LogicalDevice::class,
            'logical_device_id',
        );
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'mqtt_topic_id');
    }
}
