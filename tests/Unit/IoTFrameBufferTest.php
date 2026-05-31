<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Domain\Services\IoTFrameBuffer;
use PHPUnit\Framework\TestCase;

final class IoTFrameBufferTest extends TestCase
{
    public function test_it_emits_frames_when_delimiter_is_found(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $frames = $buffer->push("a\nb\n");

        $this->assertSame(['a', 'b'], $frames);
    }

    public function test_it_keeps_partial_payload_until_completed(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $first = $buffer->push('abc');
        $second = $buffer->push("123\n");

        $this->assertSame([], $first);
        $this->assertSame(['abc123'], $second);
    }

    public function test_it_handles_multi_character_delimiters(): void
    {
        $buffer = new IoTFrameBuffer("\r\n");

        $first = $buffer->push("hello\r");
        $second = $buffer->push("\nworld\r\n");

        $this->assertSame([], $first);
        $this->assertSame(['hello', 'world'], $second);
    }

    public function test_it_truncates_when_max_bytes_exceeded(): void
    {
        // Buffer with small max size
        $buffer = new IoTFrameBuffer("\n", 10);

        // Pushing large chunk exceeding max bytes
        $frames = $buffer->push("abcdefghijklmnop\n");

        // The chunk should be processed and empty because of truncation or strict limits
        $this->assertSame([], $frames);
        $this->assertSame('', $buffer->push(''));
    }

    public function test_it_handles_empty_delimiters_safely(): void
    {
        $buffer = new IoTFrameBuffer('');
        
        $frames = $buffer->push("abc\n");
        $this->assertSame(['abc\n'], $frames);
    }
}
