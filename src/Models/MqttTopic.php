<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $logical_device_id
 * @property string $purpose
 * @property string $topic
 * @property array<string, mixed>|null $payload_mapping
 * @property int $qos
 * @property bool $retain
 * @property bool $is_enabled
 * @property string|null $last_payload
 * @property mixed $last_value
 * @property Carbon|null $last_received_at
 * @property string|null $last_error
 */
final class MqttTopic extends Model
{
    protected $table = 'laraiot_mqtt_topics';

    protected $fillable = [
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
            'last_value' => 'json:unicode',
            'last_received_at' => 'datetime',
        ];
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
        return $this->hasMany(
            ActivityLog::class,
            'mqtt_topic_id',
        );
    }
}
