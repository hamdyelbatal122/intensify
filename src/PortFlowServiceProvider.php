<?php

declare(strict_types=1);

namespace Hamzi\PortFlow;

use Hamzi\PortFlow\Application\Services\DriverRegistry;
use Hamzi\PortFlow\Application\Services\HardwareMessageService;
use Hamzi\PortFlow\Application\Services\MessageRouter;
use Hamzi\PortFlow\Console\Commands\DiagnoseCommand;
use Hamzi\PortFlow\Console\Commands\ListenSerialCommand;
use Hamzi\PortFlow\Console\Commands\MakeDriverCommand;
use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Exceptions\PortFlowException;
use Hamzi\PortFlow\Infrastructure\Livewire\PortFlowConnector;
use Hamzi\PortFlow\Infrastructure\Livewire\PortFlowStatus;
use Hamzi\PortFlow\Infrastructure\Printing\BladeEscPosRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class PortFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/portflow.php', 'portflow');

        $this->app->singleton(DriverRegistry::class, fn ($app) => new DriverRegistry($app));
        $this->app->singleton(MessageRouter::class, fn ($app) => new MessageRouter($app['events']));
        $this->app->singleton(HardwareMessageService::class, fn ($app) => new HardwareMessageService(
            $app->make(DriverRegistry::class),
            $app->make(MessageRouter::class),
        ));
        $this->app->singleton(BladeEscPosRenderer::class, fn ($app) => new BladeEscPosRenderer($app['view']));
        $this->app->singleton('portflow', fn ($app) => new PortFlowManager(
            $app->make(DriverRegistry::class),
            $app->make(HardwareMessageService::class),
        ));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'portflow');

        $this->validateConfiguration();
        $this->bootRateLimiter();

        $this->publishes([
            __DIR__.'/../config/portflow.php' => config_path('portflow.php'),
        ], 'portflow-config');

        $this->publishes([
            __DIR__.'/../resources/js/portflow-serial.js' => public_path('vendor/portflow/portflow-serial.js'),
        ], 'portflow-assets');

        $this->publishes([
            __DIR__.'/../stubs/serial-driver.stub' => base_path('stubs/portflow/serial-driver.stub'),
        ], 'portflow-stubs');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'portflow');

        if (class_exists(Livewire::class)) {
            Livewire::component('portflow-connector', PortFlowConnector::class);
            Livewire::component('portflow-status', PortFlowStatus::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDriverCommand::class,
                ListenSerialCommand::class,
                DiagnoseCommand::class,
            ]);
        }
    }

    private function validateConfiguration(): void
    {
        /** @var array<string, mixed> $drivers */
        $drivers = (array) config('portflow.drivers', []);

        foreach ($drivers as $name => $class) {
            if (! is_string($class)) {
                throw PortFlowException::invalidDriver((string) $name, 'driver class must be a string');
            }

            if (! class_exists($class)) {
                throw PortFlowException::invalidDriver((string) $name, "class [{$class}] does not exist");
            }

            if (! is_a($class, SerialDriver::class, true)) {
                throw PortFlowException::invalidDriver((string) $name, "class [{$class}] must implement SerialDriver");
            }
        }

        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = (array) config('portflow.mappings', []);

        foreach ($mappings as $index => $mapping) {
            /** @phpstan-ignore function.alreadyNarrowedType */
            if (! is_array($mapping)) {
                throw PortFlowException::invalidConfiguration("mappings[{$index}] must be an array");
            }

            if (isset($mapping['event'])) {
                $eventClass = $mapping['event'];
                if (! is_string($eventClass) || ! class_exists($eventClass)) {
                    throw PortFlowException::invalidConfiguration("mappings[{$index}].event class [{$eventClass}] does not exist");
                }
            }

            if (isset($mapping['model'])) {
                $modelClass = $mapping['model'];
                if (! is_string($modelClass) || ! class_exists($modelClass)) {
                    throw PortFlowException::invalidConfiguration("mappings[{$index}].model class [{$modelClass}] does not exist");
                }
            }
        }
    }

    private function bootRateLimiter(): void
    {
        RateLimiter::for('portflow', function (Request $request) {
            $by = $request->ip();

            if ($request->user() !== null) {
                $by .= '-'.$request->user()->getAuthIdentifier();
            }

            return Limit::perMinute((int) config('portflow.ingest_rate_limit', 60))
                ->by($by);
        });
    }
}
