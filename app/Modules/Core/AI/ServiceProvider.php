<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI;

use App\Modules\Core\AI\Services\ConfigResolver;
use App\Modules\Core\AI\Services\DigitalWorkerRuntime;
use App\Modules\Core\AI\Services\MessageManager;
use App\Modules\Core\AI\Services\ModelDiscoveryService;
use App\Modules\Core\AI\Services\SessionManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     *
     * Merges AI config and wires workspace-based services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/ai.php', 'ai');

        $this->app->singleton(ConfigResolver::class);
        $this->app->singleton(ModelDiscoveryService::class);
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(MessageManager::class);
        $this->app->singleton(DigitalWorkerRuntime::class);
    }
}
