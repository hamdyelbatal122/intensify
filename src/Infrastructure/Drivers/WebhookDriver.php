<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers;

use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WebhookDriver implements SerialDriver
{
    private string $url = '';

    private string $method = 'POST';

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    public function name(): string
    {
        return 'webhook';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        $this->url = (string) ($options['url'] ?? '');
        $this->method = strtoupper((string) ($options['method'] ?? 'POST'));
        $this->headers = (array) ($options['headers'] ?? []);
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        return is_string($payload)
            ? $payload
            : json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        if ($this->url === '') {
            Log::warning('[PortFlow] WebhookDriver: Target URL is not configured.');

            return [
                SerialFrame::now($this->name(), [
                    'error' => 'URL not configured',
                    'raw_chunk' => $chunk,
                ], $context),
            ];
        }

        // Context payloads or JSON strings as data
        $decoded = json_decode($chunk, true);
        $data = is_array($decoded) ? $decoded : ['raw' => $chunk];

        try {
            $pendingRequest = Http::withHeaders($this->headers);

            $response = match ($this->method) {
                'GET' => $pendingRequest->get($this->url, $data),
                'PUT' => $pendingRequest->put($this->url, $data),
                'PATCH' => $pendingRequest->patch($this->url, $data),
                'DELETE' => $pendingRequest->delete($this->url, $data),
                default => $pendingRequest->post($this->url, $data),
            };

            $responseBody = $response->body();
            $jsonDecoded = json_decode($responseBody, true);

            $payload = [
                'webhook_url' => $this->url,
                'status_code' => $response->status(),
                'success' => $response->successful(),
                'response' => is_array($jsonDecoded) ? $jsonDecoded : $responseBody,
                'sent_data' => $data,
            ];

            return [
                SerialFrame::now($this->name(), $payload, $context),
            ];
        } catch (\Throwable $e) {
            Log::error('[PortFlow] Webhook dispatch failed', [
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);

            return [
                SerialFrame::now($this->name(), [
                    'webhook_url' => $this->url,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'sent_data' => $data,
                ], $context),
            ];
        }
    }
}
