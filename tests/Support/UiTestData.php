<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Tests\Support;

use Danpopa\LaraIoT\Models\DeviceType;
use Danpopa\LaraIoT\Models\LogicalDevice;
use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Models\PhysicalDevice;

final class UiTestData
{
    /**
     * @return array{
     *     physicalDevice: PhysicalDevice,
     *     deviceType: DeviceType,
     *     logicalDevice: LogicalDevice
     * }
     */
    public static function deviceTree(
        string $suffix = '01',
    ): array {
        $deviceType = DeviceType::query()->create([
            'identifier' => 'relay-'.$suffix,
            'name' => 'Relay '.$suffix,
            'description' => 'Relay device type.',
            'is_enabled' => true,
        ]);

        $physicalDevice = PhysicalDevice::query()->create([
            'identifier' => 'controller-'.$suffix,
            'name' => 'Controller '.$suffix,
            'ip_address' => '192.168.2.100',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'description' => 'Test controller.',
            'is_enabled' => true,
        ]);

        $logicalDevice = LogicalDevice::query()->create([
            'physical_device_id' => $physicalDevice->getKey(),
            'device_type_id' => $deviceType->getKey(),
            'identifier' => 'logical-'.$suffix,
            'name' => 'Logical device '.$suffix,
            'unit' => null,
            'is_enabled' => true,
        ]);

        return [
            'physicalDevice' => $physicalDevice,
            'deviceType' => $deviceType,
            'logicalDevice' => $logicalDevice,
        ];
    }

    public static function stateTopic(
        LogicalDevice $logicalDevice,
        array $overrides = [],
    ): MqttTopic {
        return MqttTopic::query()->create([
            'logical_device_id' => $logicalDevice->getKey(),
            'purpose' => 'state',
            'topic' => 'test/'.$logicalDevice->identifier.'/state',
            'payload_mapping' => [
                'format' => 'raw',
                'value_map' => [
                    'ON' => 'on',
                    'OFF' => 'off',
                ],
            ],
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
            ...$overrides,
        ]);
    }

    public static function commandTopic(
        LogicalDevice $logicalDevice,
        array $overrides = [],
    ): MqttTopic {
        return MqttTopic::query()->create([
            'logical_device_id' => $logicalDevice->getKey(),
            'purpose' => 'command',
            'topic' => 'test/'.$logicalDevice->identifier.'/command',
            'payload_mapping' => [
                'on' => 'ON',
                'off' => 'OFF',
            ],
            'qos' => 0,
            'retain' => false,
            'is_enabled' => true,
            ...$overrides,
        ]);
    }
}
