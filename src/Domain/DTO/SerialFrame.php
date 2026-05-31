<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Domain\DTO;

use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final class SerialFrame implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $driver,
        public readonly array $payload,
        public readonly DateTimeImmutable $receivedAt,
        public readonly array $context = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public static function now(string $driver, array $payload, array $context = []): self
    {
        return new self(
            driver: $driver,
            payload: $payload,
            receivedAt: new DateTimeImmutable,
            context: $context,
        );
    }

    /**
     * Convert the frame instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'payload' => $this->payload,
            'received_at' => $this->receivedAt->format(DateTimeImmutable::ATOM),
            'context' => $this->context,
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
