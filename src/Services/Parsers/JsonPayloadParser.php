<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services\Parsers;

use Danpopa\LaraIoT\Contracts\PayloadParser;
use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use JsonException;
use stdClass;

final class JsonPayloadParser implements PayloadParser
{
    public function supports(string $format): bool
    {
        return $format === 'json';
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function parse(
        string $payload,
        array $configuration,
    ): mixed {
        try {
            $decoded = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidMqttPayloadException(
                'The received payload is not valid JSON.',
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new InvalidMqttPayloadException(
                'The received JSON payload must contain an object or array.',
            );
        }

        $valuePath = $configuration['value_path'] ?? null;

        if (! is_string($valuePath) || trim($valuePath) === '') {
            throw new InvalidMqttPayloadException(
                'A value path is required for a JSON payload.',
            );
        }

        $missing = new stdClass;
        $value = data_get($decoded, $valuePath, $missing);

        if ($value === $missing) {
            throw new InvalidMqttPayloadException(
                sprintf(
                    'The value path "%s" was not found in the received JSON payload.',
                    $valuePath,
                ),
            );
        }

        return $value;
    }
}
