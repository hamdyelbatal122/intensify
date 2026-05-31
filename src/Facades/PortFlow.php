<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Facades;

use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Hamzi\PortFlow\PortFlowManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<int, SerialFrame> ingest(string $driver, string $chunk, array<string, mixed> $context = [])
 * @method static string encode(string $driver, array<int|string, mixed>|string $payload)
 * @method static string print(string $view, array<string, mixed> $data = [])
 * @method static void registerDriver(string $name, string $driverClass)
 * @method static array{default_driver: string, registered_drivers: list<string>} health()
 *
 * @see PortFlowManager
 */
final class PortFlow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'portflow';
    }
}
