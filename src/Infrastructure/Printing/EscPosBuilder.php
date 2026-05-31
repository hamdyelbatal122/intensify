<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Printing;

/**
 * Fluent builder for ESC/POS byte sequences (thermal printers).
 *
 * Usage:
 *   $bytes = (new EscPosBuilder)
 *       ->align('center')->bold()->text('RECEIPT')->bold(false)
 *       ->align('left')->text('Item 1 ... $5.00')
 *       ->divider()->feed(3)->cut()
 *       ->bytes();
 */
final class EscPosBuilder
{
    /** ESC @ — Initialize printer */
    private string $buffer = "\x1B\x40";

    /** Print text followed by a newline. */
    public function text(string $value): self
    {
        $this->buffer .= $value."\n";

        return $this;
    }

    /** Print a horizontal divider line (default 48 chars). */
    public function divider(int $width = 48): self
    {
        $this->buffer .= str_repeat('-', $width)."\n";

        return $this;
    }

    /** Feed blank lines. */
    public function feed(int $lines = 1): self
    {
        $this->buffer .= str_repeat("\n", max(1, $lines));

        return $this;
    }

    /** Enable/disable bold mode. ESC E n */
    public function bold(bool $on = true): self
    {
        $this->buffer .= "\x1B\x45".($on ? "\x01" : "\x00");

        return $this;
    }

    /** Enable/disable underline mode. ESC - n */
    public function underline(bool $on = true): self
    {
        $this->buffer .= "\x1B\x2D".($on ? "\x01" : "\x00");

        return $this;
    }

    /**
     * Set text alignment.
     *
     * @param  'left'|'center'|'right'  $alignment
     */
    public function align(string $alignment): self
    {
        $byte = match ($alignment) {
            'center' => "\x01",
            'right' => "\x02",
            default => "\x00",
        };

        $this->buffer .= "\x1B\x61".$byte;

        return $this;
    }

    /** Cut paper (full cut by default, partial when $partial = true). GS V */
    public function cut(bool $partial = false): self
    {
        $this->buffer .= $partial ? "\x1D\x56\x01" : "\x1D\x56\x00";

        return $this;
    }

    /** Reset the builder buffer. */
    public function reset(): self
    {
        $this->buffer = "\x1B\x40";

        return $this;
    }

    /** Return the accumulated ESC/POS byte string. */
    public function bytes(): string
    {
        return $this->buffer;
    }
}
