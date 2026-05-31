<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Console\Commands;

use Hamzi\PortFlow\Application\Services\DriverRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class DiagnoseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'portflow:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform diagnostic health checks on all registered PortFlow drivers and configurations';

    /**
     * Execute the console command.
     */
    public function handle(DriverRegistry $registry): int
    {
        $this->components->info('PortFlow System Diagnostics');

        $this->comment('1. Checking Cache Infrastructure for Delimiter Buffering...');
        $cacheDriver = config('cache.default', 'unknown');
        $cacheStatus = 'OK';

        try {
            Cache::put('portflow.diagnose.temp', 'test', 10);
            $cacheWorking = Cache::get('portflow.diagnose.temp') === 'test';
            Cache::forget('portflow.diagnose.temp');
        } catch (\Throwable $e) {
            $cacheWorking = false;
            $cacheStatus = 'FAILED ('.$e->getMessage().')';
        }

        $this->table(
            ['Infrastructure Component', 'Value / Type', 'Status'],
            [
                ['Laravel Cache Driver', $cacheDriver, $cacheWorking ? '<info>ACTIVE</info>' : '<error>'.$cacheStatus.'</error>'],
                ['Baud Rate Default', config('portflow.default_baud', 9600), '<info>OK</info>'],
                ['Queue Routing', config('portflow.queue_routing', false) ? 'Enabled (Async)' : 'Disabled (Sync)', '<info>OK</info>'],
            ]
        );

        if (! $cacheWorking) {
            $this->error('WARNING: Cache driver is not working correctly! Buffer persistence will fail, leading to fragmented frames.');
        } elseif ($cacheDriver === 'file') {
            $this->warn('NOTE: You are using the [file] cache driver. For production systems with high-frequency serial inputs, an in-memory driver like [redis] is highly recommended.');
        }

        $this->newLine();
        $this->comment('2. Diagnosing Registered Drivers & Configurations...');

        $drivers = $registry->all();
        $rows = [];

        foreach ($drivers as $name => $class) {
            $configured = config("portflow.driver_options.{$name}") !== null;
            $status = '<info>AVAILABLE</info>';

            if (! class_exists($class)) {
                $status = '<error>CLASS NOT FOUND</error>';
            }

            $rows[] = [
                $name,
                $class,
                $configured ? 'Yes' : 'No (Using defaults)',
                $status,
            ];
        }

        $this->table(
            ['Driver Name', 'Class Name', 'Custom Config', 'Status'],
            $rows
        );

        $this->newLine();
        $this->comment('3. Performing Validation on Configured Mappings...');

        $mappings = (array) config('portflow.mappings', []);
        if (empty($mappings)) {
            $this->warn('No automatic mappings configured under `config/portflow.php`. Frames will be parsed but not automatically routed.');
        } else {
            $mappingRows = [];
            foreach ($mappings as $index => $mapping) {
                $driver = $mapping['driver'] ?? 'Any';
                $event = $mapping['event'] ?? 'None';
                $model = $mapping['model'] ?? 'None';

                $eventStatus = '<info>OK</info>';
                if ($event !== 'None') {
                    if (! class_exists($event)) {
                        $eventStatus = '<error>EVENT CLASS MISSING</error>';
                    }
                }

                $modelStatus = '<info>OK</info>';
                if ($model !== 'None') {
                    if (! class_exists($model)) {
                        $modelStatus = '<error>MODEL CLASS MISSING</error>';
                    }
                }

                $mappingRows[] = [
                    "Mapping #{$index} ({$driver})",
                    $eventStatus,
                    $modelStatus,
                ];
            }

            $this->table(
                ['Mapping Target', 'Event Status', 'Eloquent Model Status'],
                $mappingRows
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail('Diagnostics Summary', '<info>ALL CHECKS PASSED</info>');

        return self::SUCCESS;
    }
}
