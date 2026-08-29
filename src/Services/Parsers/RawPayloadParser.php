<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services\Parsers;

use Danpopa\LaraIoT\Contracts\PayloadParser;

final class RawPayloadParser implements PayloadParser
{
    public function supports(string $format): bool
    {
        return $format === 'raw';
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function parse(
        string $payload,
        array $configuration,
    ): string {
        return $payload;
    }
}
