<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI;

use App\Modules\Core\AI\Services\AgenticRuntime;
use App\Modules\Core\AI\Services\ConfigResolver;
use App\Modules\Core\AI\Services\DigitalWorkerRuntime;
use App\Modules\Core\AI\Services\DigitalWorkerToolRegistry;
use App\Modules\Core\AI\Services\LaraCapabilityMatcher;
use App\Modules\Core\AI\Services\LaraContextProvider;
use App\Modules\Core\AI\Services\LaraKnowledgeNavigator;
use App\Modules\Core\AI\Services\LaraModelCatalogQueryService;
use App\Modules\Core\AI\Services\LaraNavigationRouter;
use App\Modules\Core\AI\Services\LaraOrchestrationService;
use App\Modules\Core\AI\Services\LaraPromptFactory;
use App\Modules\Core\AI\Services\LaraTaskDispatcher;
use App\Modules\Core\AI\Services\MessageManager;
use App\Modules\Core\AI\Services\Messaging\Adapters\EmailAdapter;
use App\Modules\Core\AI\Services\Messaging\Adapters\SlackAdapter;
use App\Modules\Core\AI\Services\Messaging\Adapters\TelegramAdapter;
use App\Modules\Core\AI\Services\Messaging\Adapters\WhatsAppAdapter;
use App\Modules\Core\AI\Services\Messaging\ChannelAdapterRegistry;
use App\Modules\Core\AI\Services\ModelDiscoveryService;
use App\Modules\Core\AI\Services\ProviderAuthFlowService;
use App\Modules\Core\AI\Services\SessionManager;
use App\Modules\Core\AI\Tools\ArtisanTool;
use App\Modules\Core\AI\Tools\BashTool;
use App\Modules\Core\AI\Tools\BrowserTool;
use App\Modules\Core\AI\Tools\DelegateTaskTool;
use App\Modules\Core\AI\Tools\DelegationStatusTool;
use App\Modules\Core\AI\Tools\DocumentAnalysisTool;
use App\Modules\Core\AI\Tools\GuideTool;
use App\Modules\Core\AI\Tools\ImageAnalysisTool;
use App\Modules\Core\AI\Tools\MemoryGetTool;
use App\Modules\Core\AI\Tools\MemorySearchTool;
use App\Modules\Core\AI\Tools\MessageTool;
use App\Modules\Core\AI\Tools\NavigateTool;
use App\Modules\Core\AI\Tools\NotificationTool;
use App\Modules\Core\AI\Tools\QueryDataTool;
use App\Modules\Core\AI\Tools\ScheduleTaskTool;
use App\Modules\Core\AI\Tools\SystemInfoTool;
use App\Modules\Core\AI\Tools\WebFetchTool;
use App\Modules\Core\AI\Tools\WebSearchTool;
use App\Modules\Core\AI\Tools\WorkerListTool;
use App\Modules\Core\AI\Tools\WriteJsTool;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register Core AI services.
     *
     * Config is provided by Base AI (config key 'ai'). Core registers
     * governance services that depend on company/employee context.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigResolver::class);
        $this->app->singleton(ModelDiscoveryService::class);
        $this->app->singleton(SessionManager::class);
        $this->app->singleton(MessageManager::class);
        $this->app->singleton(DigitalWorkerRuntime::class);
        $this->app->singleton(ProviderAuthFlowService::class);
        $this->app->singleton(LaraContextProvider::class);
        $this->app->singleton(LaraKnowledgeNavigator::class);
        $this->app->singleton(LaraModelCatalogQueryService::class);
        $this->app->singleton(LaraCapabilityMatcher::class);
        $this->app->singleton(LaraTaskDispatcher::class);
        $this->app->singleton(LaraNavigationRouter::class);
        $this->app->singleton(LaraOrchestrationService::class);
        $this->app->singleton(LaraPromptFactory::class);

        $this->app->singleton(ChannelAdapterRegistry::class, function () {
            $registry = new ChannelAdapterRegistry;

            $registry->register(new WhatsAppAdapter);
            $registry->register(new TelegramAdapter);
            $registry->register(new SlackAdapter);
            $registry->register(new EmailAdapter);

            return $registry;
        });

        $this->app->singleton(DigitalWorkerToolRegistry::class, function ($app) {
            $registry = new DigitalWorkerToolRegistry(
                $app->make(\App\Base\Authz\Contracts\AuthorizationService::class),
            );

            $registry->register(new ArtisanTool);
            $registry->register(new BashTool);
            $registry->register($app->make(BrowserTool::class));
            $registry->register($app->make(DelegateTaskTool::class));
            $registry->register(new DelegationStatusTool);
            $registry->register(new DocumentAnalysisTool);
            $registry->register($app->make(GuideTool::class));
            $registry->register(new ImageAnalysisTool);
            $registry->register(new MemoryGetTool);
            $registry->register($app->make(MessageTool::class));
            $registry->register(new NavigateTool);
            $registry->register(new NotificationTool);
            $registry->register(new QueryDataTool);
            $registry->register(new ScheduleTaskTool);
            $registry->register(new SystemInfoTool);
            $registry->register(new WebFetchTool);
            $registry->register($app->make(WorkerListTool::class));
            $registry->register(new WriteJsTool);

            $memorySearchTool = MemorySearchTool::createIfAvailable();
            if ($memorySearchTool !== null) {
                $registry->register($memorySearchTool);
            }

            $webSearchTool = WebSearchTool::createIfConfigured();
            if ($webSearchTool !== null) {
                $registry->register($webSearchTool);
            }

            return $registry;
        });

        $this->app->singleton(AgenticRuntime::class);
    }
}
