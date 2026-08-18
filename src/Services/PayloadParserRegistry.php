<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Danpopa\LaraIoT\Contracts\PayloadParser;
use Danpopa\LaraIoT\Exceptions\InvalidMqttPayloadException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;

final class PayloadParserRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly ConfigRepository $config,
    ) {}

    public function for(string $format): PayloadParser
    {
        $parserClasses = $this->config->get(
            'laraiot.mqtt.payload_parsers',
            [],
        );

        if (! is_array($parserClasses)) {
            $parserClasses = [];
        }

        foreach ($parserClasses as $parserClass) {
            if (! is_string($parserClass)) {
                continue;
            }

            $parser = $this->container->make($parserClass);

            if (
                $parser instanceof PayloadParser
                && $parser->supports($format)
            ) {
                return $parser;
            }
        }

        throw new InvalidMqttPayloadException(
            sprintf(
                'The payload format "%s" is not supported.',
                $format,
            ),
        );
    }
}
