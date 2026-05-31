<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers;

use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Hamzi\PortFlow\Infrastructure\Drivers\Traits\HasBufferPersistence;

final class Rs232Driver implements SerialDriver
{
    use HasBufferPersistence;

    private string $delimiter = "\n";

    public function name(): string
    {
        return 'rs232';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        $delimiter = (string) ($options['delimiter'] ?? "\n");
        $this->delimiter = $delimiter !== '' ? $delimiter : "\n";
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $payload = implode(',', array_map(static fn (mixed $value): string => (string) $value, $payload));
        }

        return $payload.$this->delimiter;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        [$buffer, $cacheKey] = $this->loadBuffer($context, 'rs232');
        $buffer .= $chunk;

        $frames = [];
        $delimiter = $this->delimiter !== '' ? $this->delimiter : "\n";

        while (($position = strpos($buffer, $delimiter)) !== false) {
            $record = substr($buffer, 0, $position);
            $buffer = substr($buffer, $position + strlen($delimiter));

            $record = trim($record);
            if ($record === '') {
                continue;
            }

            $segments = array_map('trim', explode(';', $record));
            $payload = [
                'raw' => $record,
                'segments' => $segments,
                'weight' => $segments[0] ?? '',
            ];

            $frames[] = SerialFrame::now($this->name(), $payload, $context);
        }

        $this->storeBuffer($cacheKey, $buffer);

        return $frames;
    }
}
