<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Console\Commands;

use Danpopa\LaraIoT\Services\MqttListenerService;
use Illuminate\Console\Command;
use Throwable;

final class ListenMqttCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laraiot:mqtt-listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for MQTT messages and persist LaraIoT topic state.';

    public function handle(
        MqttListenerService $listener,
    ): int {
        $this->registerSignalHandlers($listener);

        $this->components->info(
            'Starting LaraIoT MQTT listener.',
        );

        try {
            $listener->listen();
        } catch (Throwable $exception) {
            $this->components->error(
                $exception->getMessage(),
            );

            return self::FAILURE;
        }

        $this->components->info(
            'LaraIoT MQTT listener stopped.',
        );

        return self::SUCCESS;
    }

    private function registerSignalHandlers(
        MqttListenerService $listener,
    ): void {
        if (! extension_loaded('pcntl')) {
            return;
        }

        $signals = [];

        foreach (['SIGINT', 'SIGTERM'] as $signalName) {
            if (! defined($signalName)) {
                continue;
            }

            $signals[] = (int) constant($signalName);
        }

        if ($signals === []) {
            return;
        }

        $this->trap(
            $signals,
            function (int $_signal) use ($listener): void {
                $this->newLine();

                $this->components->warn(
                    'Stopping LaraIoT MQTT listener...',
                );

                $listener->stop();
            },
        );
    }
}
