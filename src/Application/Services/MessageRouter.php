<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Application\Services;

use Hamzi\PortFlow\Application\Jobs\RouteSerialFrameJob;
use Hamzi\PortFlow\Domain\Contracts\SerialEvent;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MessageRouter
{
    public function __construct(private readonly Dispatcher $events) {}

    /**
     * Route a frame: dispatch to queue if enabled, otherwise route synchronously.
     */
    public function route(SerialFrame $frame): void
    {
        if ((bool) config('portflow.queue_routing', false)) {
            RouteSerialFrameJob::dispatch($frame);

            return;
        }

        $this->routeSync($frame);
    }

    /**
     * Execute routing synchronously — used directly and by RouteSerialFrameJob.
     */
    public function routeSync(SerialFrame $frame): void
    {
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = (array) config('portflow.mappings', []);

        foreach ($mappings as $mapping) {
            if (! $this->matches($mapping, $frame)) {
                continue;
            }

            try {
                $this->dispatchMappedEvent($mapping, $frame);
                $this->persistMappedModel($mapping, $frame);
            } catch (Throwable $e) {
                Log::error('[PortFlow] Frame routing failed', [
                    'driver' => $frame->driver,
                    'error' => $e->getMessage(),
                    'payload' => $frame->payload,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function matches(array $mapping, SerialFrame $frame): bool
    {
        $driver = $mapping['driver'] ?? null;
        if (is_string($driver) && $driver !== $frame->driver) {
            return false;
        }

        $payloadField = $mapping['payload_field'] ?? null;
        $matchValue = $mapping['equals'] ?? null;

        if (! is_string($payloadField) || $matchValue === null) {
            return true;
        }

        return ($frame->payload[$payloadField] ?? null) === $matchValue;
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function dispatchMappedEvent(array $mapping, SerialFrame $frame): void
    {
        $eventClass = $mapping['event'] ?? null;
        if (! is_string($eventClass) || ! class_exists($eventClass)) {
            return;
        }

        if (! is_a($eventClass, SerialEvent::class, true)) {
            Log::warning("[PortFlow] Event [{$eventClass}] does not implement SerialEvent interface. Routing skipped. Add 'implements SerialEvent' to your event class.");

            return;
        }

        $eventPayloadField = $mapping['event_payload_field'] ?? 'barcode';

        $value = is_string($eventPayloadField)
            ? ($frame->payload[$eventPayloadField] ?? null)
            : null;

        if (! is_string($value)) {
            $value = json_encode($frame->payload, JSON_THROW_ON_ERROR);
        }

        $this->events->dispatch(new $eventClass($value, $frame->context));
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function persistMappedModel(array $mapping, SerialFrame $frame): void
    {
        $modelClass = $mapping['model'] ?? null;
        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        /** @var Model $model */
        $model = new $modelClass;

        /** @var array<string, string> $fieldMap */
        $fieldMap = (array) ($mapping['field_map'] ?? []);

        $attributes = [];
        foreach ($fieldMap as $modelField => $payloadField) {
            $attributes[$modelField] = $frame->payload[$payloadField] ?? null;
        }

        if ($attributes !== []) {
            $model->fill($attributes);
            $model->save();
        }
    }
}
