<?php

declare(strict_types=1);

use Danpopa\LaraIoT\Services\Parsers\JsonPayloadParser;
use Danpopa\LaraIoT\Services\Parsers\RawPayloadParser;

return [

    /*
    |--------------------------------------------------------------------------
    | Communication Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes: "polling" and "websocket".
    |
    */

    'mode' => env('LARAIOT_MODE', 'polling'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | When null, LaraIoT will use the host application's timezone.
    |
    */

    'timezone' => env('LARAIOT_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Optional User Interface
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'enabled' => env('LARAIOT_UI_ENABLED', false),
        'prefix' => env('LARAIOT_UI_PREFIX', 'laraiot'),

        'middleware' => [
            'web',
        ],

        'guard' => env(
            'LARAIOT_UI_GUARD',
            'laraiot',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'enabled' => env('LARAIOT_API_ENABLED', true),
        'prefix' => env(
            'LARAIOT_API_PREFIX',
            'api/laraiot',
        ),
        'middleware' => [
            'api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    */

    'polling' => [
        'interval' => env(
            'LARAIOT_POLLING_INTERVAL',
            10,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | MQTT Broker
    |--------------------------------------------------------------------------
    */

    'mqtt' => [
        'host' => env(
            'LARAIOT_MQTT_HOST',
            '127.0.0.1',
        ),

        'port' => env(
            'LARAIOT_MQTT_PORT',
            1883,
        ),

        'client_id' => env(
            'LARAIOT_MQTT_CLIENT_ID',
        ),

        'username' => env(
            'LARAIOT_MQTT_USERNAME',
        ),

        'password' => env(
            'LARAIOT_MQTT_PASSWORD',
        ),

        'clean_session' => env(
            'LARAIOT_MQTT_CLEAN_SESSION',
            true,
        ),

        'keep_alive' => env(
            'LARAIOT_MQTT_KEEP_ALIVE',
            10,
        ),

        'connection_timeout' => env(
            'LARAIOT_MQTT_CONNECTION_TIMEOUT',
            5,
        ),

        /*
        |--------------------------------------------------------------------------
        | MQTT TLS
        |--------------------------------------------------------------------------
        */

        'tls' => [
            'enabled' => env(
                'LARAIOT_MQTT_TLS_ENABLED',
                false,
            ),

            'verify_peer' => env(
                'LARAIOT_MQTT_TLS_VERIFY_PEER',
                true,
            ),

            'verify_peer_name' => env(
                'LARAIOT_MQTT_TLS_VERIFY_PEER_NAME',
                true,
            ),

            'allow_self_signed' => env(
                'LARAIOT_MQTT_TLS_ALLOW_SELF_SIGNED',
                false,
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | MQTT Listener
        |--------------------------------------------------------------------------
        */

        'listener' => [
            'client_id' => env(
                'LARAIOT_MQTT_LISTENER_CLIENT_ID',
                'laraiot-listener',
            ),

            'sync_interval' => env(
                'LARAIOT_MQTT_LISTENER_SYNC_INTERVAL',
                5,
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | MQTT Topic Testing
        |--------------------------------------------------------------------------
        */

        'testing' => [
            'timeout' => env(
                'LARAIOT_MQTT_TEST_TIMEOUT',
                10,
            ),

            'token_ttl' => env(
                'LARAIOT_MQTT_TEST_TOKEN_TTL',
                10,
            ),

            'client_id_prefix' => env(
                'LARAIOT_MQTT_TEST_CLIENT_ID_PREFIX',
                'laraiot-test',
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | MQTT Payload Parsers
        |--------------------------------------------------------------------------
        */

        'payload_parsers' => [
            RawPayloadParser::class,
            JsonPayloadParser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WebSocket
    |--------------------------------------------------------------------------
    */

    'websocket' => [
        'connection' => env(
            'LARAIOT_BROADCAST_CONNECTION',
        ),

        'channel_prefix' => env(
            'LARAIOT_WEBSOCKET_CHANNEL_PREFIX',
            'laraiot',
        ),
    ],

];
