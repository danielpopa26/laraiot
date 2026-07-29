<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Contracts;

use PhpMqtt\Client\Contracts\MqttClient;

interface MqttClientFactory
{
    public function create(
        string $host,
        int $port,
        ?string $clientId = null,
    ): MqttClient;
}