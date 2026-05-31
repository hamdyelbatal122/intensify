<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers\Traits;

use Illuminate\Support\Facades\Cache;

trait HasBufferPersistence
{
    /**
     * Load the current buffer for a given context and driver.
     *
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: ?string}
     */
    protected function loadBuffer(array $context, string $driverName, bool $binarySafe = false): array
    {
        $sessionId = isset($context['session_id']) ? (string) $context['session_id'] : null;
        if ($sessionId === null || $sessionId === '') {
            return ['', null];
        }

        $cacheKey = "portflow.{$driverName}.buf.".hash('sha256', $sessionId);
        $state = (string) Cache::get($cacheKey, '');

        if ($binarySafe && $state !== '') {
            $buffer = base64_decode($state, true) ?: '';
        } else {
            $buffer = $state;
        }

        return [$buffer, $cacheKey];
    }

    /**
     * Store the buffer to cache.
     */
    protected function storeBuffer(?string $cacheKey, string $buffer, bool $binarySafe = false, int $ttl = 300): void
    {
        if ($cacheKey !== null) {
            $value = $binarySafe ? base64_encode($buffer) : $buffer;
            Cache::put($cacheKey, $value, $ttl);
        }
    }
}
