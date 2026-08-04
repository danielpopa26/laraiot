<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Danpopa\LaraIoT\Models\MqttTopic;
use Danpopa\LaraIoT\Services\MqttCommandService;
use Illuminate\Console\Command;
use Throwable;

final class PublishMqttCommand extends Command
{
    protected $signature = 'laraiot:publish
                            {mqttTopicId : The ID of the MQTT command topic}
                            {payload : The payload to publish}
                            {--client-id= : Optional MQTT client identifier}';

    protected $description = 'Publish a command through a configured LaraIoT MQTT topic';

    public function handle(MqttCommandService $commandService): int
    {
        $mqttTopic = MqttTopic::query()->find(
            (int) $this->argument('mqttTopicId'),
        );

        if (! $mqttTopic instanceof MqttTopic) {
            $this->error('The specified MQTT topic was not found.');

            return self::FAILURE;
        }

        $clientIdOption = $this->option('client-id');

        $clientId = is_string($clientIdOption) && $clientIdOption !== ''
            ? $clientIdOption
            : null;

        $payload = $this->argument('payload');

        if (! is_string($payload)) {
            $this->error('The payload must be a string.');

            return self::FAILURE;
        }

        try {
            $commandService->send(
                mqttTopic: $mqttTopic,
                payload: $payload,
                clientId: $clientId,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'MQTT command published successfully to topic [%s].',
            $mqttTopic->topic,
        ));

        return self::SUCCESS;
    }
}
