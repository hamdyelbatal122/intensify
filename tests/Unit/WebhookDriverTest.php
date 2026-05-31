<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Infrastructure\Drivers\WebhookDriver;
use Hamzi\PortFlow\Tests\TestCase;
use Illuminate\Support\Facades\Http;

final class WebhookDriverTest extends TestCase
{
    public function test_name_is_webhook(): void
    {
        $this->assertSame('webhook', (new WebhookDriver)->name());
    }

    public function test_dispatches_webhook_successfully(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['status' => 'received'], 200),
        ]);

        $driver = new WebhookDriver;
        $driver->configure([
            'url' => 'https://example.com/api/ingest',
            'method' => 'POST',
            'headers' => ['Authorization' => 'Bearer token123'],
        ]);

        $frames = $driver->parseInbound(json_encode(['sensor' => 'temp', 'value' => 24.5]));

        $this->assertCount(1, $frames);
        $this->assertTrue($frames[0]->payload['success']);
        $this->assertSame(200, $frames[0]->payload['status_code']);
        $this->assertSame(['status' => 'received'], $frames[0]->payload['response']);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/api/ingest'
                && $request->hasHeader('Authorization', 'Bearer token123')
                && $request['sensor'] === 'temp';
        });
    }

    public function test_handles_webhook_failure_gracefully(): void
    {
        Http::fake([
            'example.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $driver = new WebhookDriver;
        $driver->configure([
            'url' => 'https://example.com/api/ingest',
        ]);

        $frames = $driver->parseInbound('raw-data-payload');

        $this->assertCount(1, $frames);
        $this->assertFalse($frames[0]->payload['success']);
        $this->assertSame(500, $frames[0]->payload['status_code']);
        $this->assertSame('Internal Server Error', $frames[0]->payload['response']);
    }
}
