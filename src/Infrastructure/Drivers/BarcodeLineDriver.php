<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers;

use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Support\Facades\Cache;

use Hamzi\PortFlow\Infrastructure\Drivers\Traits\HasBufferPersistence;

final class BarcodeLineDriver implements SerialDriver
{
    use HasBufferPersistence;

    private string $delimiter = "\n";

    /**
     * @var array<int, string>
     */
    private array $stripPrefix = [];

    /**
     * @var array<int, string>
     */
    private array $stripSuffix = ["\r", "\n", "\t"];

    public function name(): string
    {
        return 'barcode-line';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        $delimiter = (string) ($options['delimiter'] ?? "\n");
        $this->delimiter = $delimiter !== '' ? $delimiter : "\n";

        $this->stripPrefix = array_values(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) ? $value : null, (array) ($options['strip_prefix'] ?? [])),
            static fn (?string $value): bool => $value !== null && $value !== '',
        ));

        $this->stripSuffix = array_values(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) ? $value : null, (array) ($options['strip_suffix'] ?? ["\r", "\n", "\t"])),
            static fn (?string $value): bool => $value !== null && $value !== '',
        ));
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $payload = (string) ($payload['barcode'] ?? $payload['raw'] ?? '');
        }

        return $payload.$this->delimiter;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        [$buffer, $cacheKey] = $this->loadBuffer($context, 'barcode');
        $buffer .= $chunk;

        $frames = [];
        $delimiter = $this->delimiter !== '' ? $this->delimiter : "\n";

        while (($position = strpos($buffer, $delimiter)) !== false) {
            $raw = substr($buffer, 0, $position);
            $buffer = substr($buffer, $position + strlen($delimiter));

            $barcode = $this->normalizeBarcode($raw);

            if ($barcode === '') {
                continue;
            }

            $frames[] = SerialFrame::now($this->name(), [
                'barcode' => $barcode,
                'raw' => $raw,
                'length' => strlen($barcode),
            ], $context);
        }

        $this->storeBuffer($cacheKey, $buffer);

        return $frames;
    }

    private function normalizeBarcode(string $value): string
    {
        foreach ($this->stripPrefix as $prefix) {
            if (str_starts_with($value, $prefix)) {
                $value = substr($value, strlen($prefix));
                break;
            }
        }

        foreach ($this->stripSuffix as $suffix) {
            if (str_ends_with($value, $suffix)) {
                $value = substr($value, 0, -strlen($suffix));
            }
        }

        return trim($value);
    }
}
