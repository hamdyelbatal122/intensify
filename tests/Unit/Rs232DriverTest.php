<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Infrastructure\Drivers\Rs232Driver;
use Hamzi\PortFlow\Tests\TestCase;

final class Rs232DriverTest extends TestCase
{
    public function test_name_is_rs232(): void
    {
        $this->assertSame('rs232', (new Rs232Driver)->name());
    }

    public function test_parses_single_record(): void
    {
        $driver = new Rs232Driver;
        $driver->configure(['delimiter' => "\n"]);

        $frames = $driver->parseInbound("12.5;kg;SCALE1\n");

        $this->assertCount(1, $frames);
        $this->assertSame('12.5', $frames[0]->payload['weight']);
        $this->assertSame(['12.5', 'kg', 'SCALE1'], $frames[0]->payload['segments']);
    }

    public function test_parses_multiple_records(): void
    {
        $driver = new Rs232Driver;
        $driver->configure(['delimiter' => "\n"]);

        $frames = $driver->parseInbound("10.0;kg\n20.0;kg\n");

        $this->assertCount(2, $frames);
        $this->assertSame('10.0', $frames[0]->payload['weight']);
        $this->assertSame('20.0', $frames[1]->payload['weight']);
    }

    public function test_empty_chunk_returns_no_frames(): void
    {
        $frames = (new Rs232Driver)->parseInbound('');

        $this->assertSame([], $frames);
    }

    public function test_encodes_array_payload_as_csv(): void
    {
        $driver = new Rs232Driver;
        $driver->configure(['delimiter' => "\n"]);

        $result = $driver->encodeOutbound(['field1', 'field2', 'field3']);

        $this->assertSame("field1,field2,field3\n", $result);
    }

    public function test_encodes_string_payload_with_delimiter(): void
    {
        $driver = new Rs232Driver;
        $driver->configure(['delimiter' => "\r\n"]);

        $result = $driver->encodeOutbound('COMMAND');

        $this->assertSame("COMMAND\r\n", $result);
    }

    public function test_it_keeps_partial_payload_until_completed_with_context(): void
    {
        $driver = new Rs232Driver;
        $driver->configure(['delimiter' => "\n"]);
        $context = ['session_id' => 'rs232-test-session'];

        // First partial chunk
        $first = $driver->parseInbound('15.4;k', $context);
        $this->assertCount(0, $first);

        // Second completing chunk
        $second = $driver->parseInbound("g;SCALE2\n", $context);
        $this->assertCount(1, $second);
        $this->assertSame('15.4', $second[0]->payload['weight']);
        $this->assertSame(['15.4', 'kg', 'SCALE2'], $second[0]->payload['segments']);
    }
}
