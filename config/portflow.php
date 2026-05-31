<?php

declare(strict_types=1);

use Hamzi\PortFlow\Domain\Events\ProductScanned;
use Hamzi\PortFlow\Infrastructure\Drivers\BarcodeLineDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\EscPosDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\FingerprintPacketDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\RawJsonDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\RfidAsciiDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\Rs232Driver;
use Hamzi\PortFlow\Infrastructure\Drivers\WebhookDriver;

return [
    'default_driver' => env('PORTFLOW_DEFAULT_DRIVER', 'raw-json'),

    'serial' => [
        'baud_rate' => (int) env('PORTFLOW_BAUD_RATE', 9600),
        'baud_rate_options' => [9600, 19200, 38400, 57600, 74880, 115200],
        'remember_baud_rate' => filter_var(env('PORTFLOW_REMEMBER_BAUD_RATE', true), FILTER_VALIDATE_BOOL),
        'auto_connect_on_load' => filter_var(env('PORTFLOW_AUTO_CONNECT_ON_LOAD', true), FILTER_VALIDATE_BOOL),
        'port_filters' => [],
        'browser_chunk_event' => 'portflow-frame-received',
        'livewire_chunk_event' => 'portflow-frame-received',
        'livewire_status_event' => 'portflow-status-updated',
        'livewire_error_event' => 'portflow-error',
    ],

    'backend' => [
        'allowed_devices' => [],
        'default_chunk_bytes' => 256,
        'default_read_sleep_us' => 20000,
    ],

    'ingest_path' => env('PORTFLOW_INGEST_PATH', '/portflow/ingest'),

    /*
     | Middleware applied to the ingest route.
     | NOTE: Do NOT include TrimStrings or ConvertEmptyStringsToNull here —
     | they would corrupt binary delimiters (\n, \r\n) in serial chunks.
     | The 'web' group is used by default to keep CSRF protection. If you
     | prefer a fully stateless endpoint, switch to ['api'].
     */
    'ingest_middleware' => ['web'],

    /*
     | Maximum allowed byte-length for an inbound chunk.
     | Requests exceeding this limit receive a 422 Unprocessable Entity.
     */
    'max_chunk_bytes' => env('PORTFLOW_MAX_CHUNK_BYTES', 16384),

    /*
     | Per-minute rate limit for the ingest endpoint (per IP).
     | Set to 0 to disable throttling.
     */
    'ingest_rate_limit' => env('PORTFLOW_RATE_LIMIT', 60),

    /*
     | When true, SerialFrames are routed via the queue instead of synchronously.
     | Requires a working queue worker (php artisan queue:work).
     */
    'queue_routing' => env('PORTFLOW_QUEUE_ROUTING', false),

    'drivers' => [
        'raw-json' => RawJsonDriver::class,
        'barcode-line' => BarcodeLineDriver::class,
        'rfid-ascii' => RfidAsciiDriver::class,
        'fingerprint-packet' => FingerprintPacketDriver::class,
        'escpos' => EscPosDriver::class,
        'rs232' => Rs232Driver::class,
        'webhook' => WebhookDriver::class,
    ],

    'driver_options' => [
        'raw-json' => [
            'delimiter' => "\n",
            'max_bytes' => 16384,
        ],
        'barcode-line' => [
            'delimiter' => "\n",
            'strip_prefix' => [],
            'strip_suffix' => ["\r", "\n", "\t"],
        ],
        'rfid-ascii' => [
            'stx' => "\x02",
            'etx' => "\x03",
            'uppercase' => true,
        ],
        'fingerprint-packet' => [
            'start_code_hex' => 'EF01',
        ],
        'rs232' => [
            'delimiter' => "\n",
        ],
        'escpos' => [],
        'webhook' => [
            'url' => env('PORTFLOW_WEBHOOK_URL', ''),
            'method' => 'POST',
            'headers' => [],
        ],
    ],

    'mappings' => [
        [
            'driver' => 'raw-json',
            'payload_field' => 'type',
            'equals' => 'barcode.scan',
            'event' => ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
        [
            'driver' => 'escpos',
            'event' => ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
        [
            'driver' => 'barcode-line',
            'event' => ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
        [
            'driver' => 'rfid-ascii',
            'payload_field' => 'tag',
            'event' => ProductScanned::class,
            'event_payload_field' => 'tag',
        ],
    ],
];
