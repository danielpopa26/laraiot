<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Contracts;

interface PayloadParser
{
    public function supports(string $format): bool;

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function parse(
        string $payload,
        array $configuration,
    ): mixed;
}
