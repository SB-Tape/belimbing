<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use Illuminate\Support\Facades\DB;

/**
 * System information reporting tool for Digital Workers.
 *
 * Provides structured JSON about the BLB instance including framework
 * details, active modules, configured AI providers, and health checks.
 * The LLM reads this to answer system-related questions.
 *
 * Gated by `ai.tool_system_info.execute` authz capability.
 */
class SystemInfoTool implements DigitalWorkerTool
{
    /**
     * Valid section names that can be requested.
     *
     * @var list<string>
     */
    private const SECTIONS = [
        'all',
        'framework',
        'modules',
        'providers',
        'health',
    ];

    public function name(): string
    {
        return 'system_info';
    }

    public function description(): string
    {
        return 'Report BLB system state including framework version, active modules, '
            .'configured AI providers, and health checks. '
            .'Use this to answer questions like "What version of Laravel is running?", '
            .'"Which modules are active?", or "Is the database healthy?".';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'section' => [
                    'type' => 'string',
                    'enum' => self::SECTIONS,
                    'description' => 'Which section to return. '
                        .'Options: "all" (default), "framework", "modules", "providers", "health". '
                        .'Use "all" to get a complete system overview.',
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_system_info.execute';
    }

    public function execute(array $arguments): string
    {
        $section = $arguments['section'] ?? 'all';

        if (! is_string($section) || ! in_array($section, self::SECTIONS, true)) {
            $section = 'all';
        }

        $data = $section === 'all'
            ? $this->gatherAll()
            : [$section => $this->gatherSection($section)];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Gather all sections into a single response.
     *
     * @return array<string, mixed>
     */
    private function gatherAll(): array
    {
        return [
            'framework' => $this->gatherFramework(),
            'modules' => $this->gatherModules(),
            'providers' => $this->gatherProviders(),
            'health' => $this->gatherHealth(),
        ];
    }

    /**
     * Gather a single section by name.
     *
     * @param  string  $section  Section name
     */
    private function gatherSection(string $section): mixed
    {
        return match ($section) {
            'framework' => $this->gatherFramework(),
            'modules' => $this->gatherModules(),
            'providers' => $this->gatherProviders(),
            'health' => $this->gatherHealth(),
            default => [],
        };
    }

    /**
     * Framework metadata: Laravel version, PHP version, environment, etc.
     *
     * @return array<string, mixed>
     */
    private function gatherFramework(): array
    {
        return [
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'php_sapi' => php_sapi_name(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];
    }

    /**
     * Active BLB modules by scanning `app/Modules/Core/` for directories
     * that contain a `ServiceProvider.php`.
     *
     * @return list<string>
     */
    private function gatherModules(): array
    {
        $modules = [];
        $modulesPath = base_path('app/Modules/Core');

        try {
            if (! is_dir($modulesPath)) {
                return [];
            }

            $directories = new \DirectoryIterator($modulesPath);

            foreach ($directories as $entry) {
                if (! $entry->isDir() || $entry->isDot()) {
                    continue;
                }

                $serviceProviderPath = $entry->getPathname().'/ServiceProvider.php';

                if (file_exists($serviceProviderPath)) {
                    $modules[] = $entry->getFilename();
                }
            }

            sort($modules);
        } catch (\Throwable) {
            // Filesystem error — return what we have
        }

        return $modules;
    }

    /**
     * Configured AI providers from the `ai_providers` table.
     *
     * Uses `DB::table()` to avoid model dependency. API keys are never
     * returned. Returns `[]` if the table does not exist (fresh install).
     *
     * @return list<array<string, mixed>>
     */
    private function gatherProviders(): array
    {
        try {
            $providers = DB::table('ai_providers')
                ->select(['id', 'name', 'is_active'])
                ->get();

            $modelCounts = DB::table('ai_provider_models')
                ->selectRaw('ai_provider_id, count(*) as model_count')
                ->groupBy('ai_provider_id')
                ->pluck('model_count', 'ai_provider_id');

            return $providers->map(function (object $provider) use ($modelCounts): array {
                return [
                    'name' => $provider->name,
                    'is_active' => (bool) $provider->is_active,
                    'model_count' => (int) ($modelCounts[$provider->id] ?? 0),
                ];
            })->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Health checks: queue, cache, session, database connectivity, storage.
     *
     * @return array<string, mixed>
     */
    private function gatherHealth(): array
    {
        return [
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'database' => $this->checkDatabase(),
            'storage_writable' => is_writable(storage_path('app')),
        ];
    }

    /**
     * Test database connectivity with a simple SELECT 1.
     */
    private function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1');

            return 'connected';
        } catch (\Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }
}
