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

    public function test_it_truncates_buffer_when_max_bytes_exceeded(): void
    {
        $buffer = new IoTFrameBuffer("\n", 10);

        // Push 16 bytes without delimiter — buffer truncates to last 10
        $frames = $buffer->push('abcdefghijklmnop');
        $this->assertSame([], $frames);

        // The buffer should now contain 'ghijklmnop' (last 10 bytes)
        $remainder = $buffer->flushRemainder();
        $this->assertSame('ghijklmnop', $remainder);
    }

    public function test_flush_remainder_returns_pending_bytes(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $buffer->push('partial');
        $remainder = $buffer->flushRemainder();

        $this->assertSame('partial', $remainder);
    }

    public function test_flush_remainder_returns_null_on_empty(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $remainder = $buffer->flushRemainder();

        $this->assertNull($remainder);
    }

    public function test_get_state_and_set_state(): void
    {
        $buffer = new IoTFrameBuffer("\n");
        $buffer->push('hello');

        $state = $buffer->getState();

        $buffer2 = new IoTFrameBuffer("\n");
        $buffer2->setState($state);

        $frames = $buffer2->push(" world\n");
        $this->assertSame(['hello world'], $frames);
    }
}
