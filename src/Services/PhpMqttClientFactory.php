<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Contracts\MqttClientFactory;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\MqttClient;

final class PhpMqttClientFactory implements MqttClientFactory
{
    public function create(
        string $host,
        int $port,
        ?string $clientId = null,
    ): MqttClientContract {
        return new MqttClient(
            $host,
            $port,
            $clientId,
        );
    }
}