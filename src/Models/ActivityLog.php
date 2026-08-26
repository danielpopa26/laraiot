<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property int|null $logical_device_id
 * @property int|null $mqtt_topic_id
 * @property string|null $actor_type
 * @property int|string|null $actor_id
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed>|null $data
 * @property Carbon|null $happened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LogicalDevice|null $logicalDevice
 * @property-read MqttTopic|null $mqttTopic
 * @property-read Model|null $actor
 */
class ActivityLog extends Model
{
    protected $table = 'laraiot_activity_logs';

    protected $fillable = [
        'type',
        'logical_device_id',
        'mqtt_topic_id',
        'actor_type',
        'actor_id',
        'title',
        'description',
        'data',
        'happened_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'happened_at' => 'datetime',
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
     * @return BelongsTo<MqttTopic, $this>
     */
    public function mqttTopic(): BelongsTo
    {
        return $this->belongsTo(MqttTopic::class, 'mqtt_topic_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
