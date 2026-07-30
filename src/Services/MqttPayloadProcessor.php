<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use JsonException;

final class MqttPayloadProcessor
{
    public function __construct(
        private readonly PayloadParserRegistry $parsers,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     * @return array{
     *     configured_format: string,
     *     detected_format: string,
     *     extracted_value: mixed,
     *     normalized_value: mixed
     * }
     */
    public function process(
        string $payload,
        array $configuration,
    ): array {
        $format = $configuration['format'] ?? null;

        if (! is_string($format) || trim($format) === '') {
            throw new InvalidMqttPayloadException(
                'The payload format is not configured.',
            );
        }

        $format = strtolower(trim($format));
        $detectedFormat = $this->detectFormat($payload);

        if ($format === 'raw' && $detectedFormat === 'json') {
            throw new InvalidMqttPayloadException(
                'The received payload appears to be JSON, but the topic is configured as RAW.',
            );
        }

        $extractedValue = $this->parsers
            ->for($format)
            ->parse($payload, $configuration);

        return [
            'configured_format' => $format,
            'detected_format' => $detectedFormat,
            'extracted_value' => $extractedValue,
            'normalized_value' => $this->mapValue(
                $extractedValue,
                $configuration['value_map'] ?? [],
            ),
        ];
    }

    public function detectFormat(string $payload): string
    {
        try {
            $decoded = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return 'raw';
        }

        return is_array($decoded) ? 'json' : 'raw';
    }

    private function mapValue(
        mixed $value,
        mixed $valueMap,
    ): mixed {
        if (! is_array($valueMap)) {
            return $value;
        }

        $key = $this->valueMapKey($value);

        return array_key_exists($key, $valueMap)
            ? $valueMap[$key]
            : $value;
    }

    private function valueMapKey(mixed $value): string
    {
        return match (true) {
            $value === true => 'true',
            $value === false => 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,

            default => throw new InvalidMqttPayloadException(
                'The extracted value must be scalar or mapped from a scalar value.',
            ),
        };
    }
}
