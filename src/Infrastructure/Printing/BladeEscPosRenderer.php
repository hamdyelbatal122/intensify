<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Printing;

use Illuminate\Contracts\View\Factory;

final class BladeEscPosRenderer
{
    public function __construct(private readonly Factory $views) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string
    {
        $content = trim($this->views->make($view, $data)->render());

        $builder = new EscPosBuilder;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $builder->text(rtrim((string) $line));
        }

        return $builder->feed(2)->cut()->bytes();
    }
}
