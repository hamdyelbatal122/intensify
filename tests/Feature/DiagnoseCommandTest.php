<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Feature;

use Hamzi\PortFlow\Tests\TestCase;

final class DiagnoseCommandTest extends TestCase
{
    public function test_diagnose_command_runs_successfully(): void
    {
        $this->artisan('portflow:diagnose')
            ->expectsOutputToContain('PortFlow System Diagnostics')
            ->expectsOutputToContain('Checking Cache Infrastructure')
            ->expectsOutputToContain('Diagnosing Registered Drivers')
            ->expectsOutputToContain('Performing Validation on Configured Mappings')
            ->assertExitCode(0);
    }
}
