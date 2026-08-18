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
        {topicId : ID of the configured MQTT command topic}
        {command : Logical command key from the topic command map}
        {--client-id= : Optional MQTT client ID}';

    protected $description =
        'Publish a configured MQTT command through LaraIoT.';

    public function handle(
        MqttCommandService $commandService,
    ): int {
        $topicId = $this->argument('topicId');
        $command = $this->argument('command');
        $clientId = $this->option('client-id');

        if (
            ! is_string($topicId)
            || ! ctype_digit($topicId)
        ) {
            $this->components->error(
                'The MQTT topic ID must be a positive integer.',
            );

            return self::FAILURE;
        }

        if (
            ! is_string($command)
            || trim($command) === ''
        ) {
            $this->components->error(
                'The MQTT command must not be empty.',
            );

            return self::FAILURE;
        }

        $mqttTopic = MqttTopic::query()->find(
            (int) $topicId,
        );

        if ($mqttTopic === null) {
            $this->components->error(
                sprintf(
                    'MQTT topic with ID %s was not found.',
                    $topicId,
                ),
            );

            return self::FAILURE;
        }

        $resolvedClientId = is_string($clientId)
            && trim($clientId) !== ''
                ? trim($clientId)
                : null;

        try {
            $commandService->send(
                mqttTopic: $mqttTopic,
                command: trim($command),
                clientId: $resolvedClientId,
            );
        } catch (Throwable $exception) {
            $this->components->error(
                $exception->getMessage(),
            );

            return self::FAILURE;
        }

        $this->components->info(
            sprintf(
                'MQTT command "%s" published to "%s".',
                trim($command),
                $mqttTopic->topic,
            ),
        );

        return self::SUCCESS;
    }
}
