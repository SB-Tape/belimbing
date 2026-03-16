<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Database\Livewire\Queries;

use App\Base\AI\DTO\ChatRequest;
use App\Base\AI\Services\LlmClient;
use App\Base\Database\Exceptions\BlbQueryException;
use App\Base\Database\Models\TableRegistry;
use App\Base\Database\Services\QueryExecutor;
use App\Modules\Core\AI\Models\AiProvider;
use App\Modules\Core\AI\Models\AiProviderModel;
use App\Modules\Core\AI\Services\RuntimeCredentialResolver;
use App\Modules\Core\User\Models\Query;
use App\Modules\Core\User\Models\UserPin;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Show page for a single database query.
 *
 * The natural-language prompt is the primary input. AI generates
 * title, description, and SQL from it. Users explicitly save via a
 * button; inline edits for title/description/SQL are available as
 * overrides. Supports sharing (copy-on-share) with auto-pinning.
 */
class Show extends Component
{
    use WithPagination;

    public Query $query;

    public string $error = '';

    public string $editName = '';

    public string $editSql = '';

    public string $editDescription = '';

    public string $editPrompt = '';

    public string $selectedModelId = '';

    public bool $isGenerating = false;

    public string $aiError = '';

    /**
     * Initialize the component by loading the query for the authenticated user.
     *
     * Sets the default AI model to the first active model of the highest-priority
     * provider for the user's company.
     *
     * @param  string  $slug  The URL slug identifying the query
     */
    public function mount(string $slug): void
    {
        $this->query = Query::query()
            ->where('user_id', auth()->id())
            ->where('slug', $slug)
            ->firstOrFail();

        $this->editName = $this->query->name;
        $this->editSql = $this->query->sql_query;
        $this->editDescription = $this->query->description ?? '';
        $this->editPrompt = $this->query->prompt ?? '';

        $this->selectedModelId = $this->resolveDefaultModelId();
    }

    /**
     * Persist all editable fields to the database.
     */
    public function save(): void
    {
        $validated = validator([
            'name' => $this->editName,
            'prompt' => $this->editPrompt ?: null,
            'description' => $this->editDescription ?: null,
            'sql_query' => $this->editSql,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'prompt' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sql_query' => ['required', 'string'],
        ])->validate();

        $this->query->name = $validated['name'];
        $this->query->slug = Query::generateSlug($validated['name'], $this->query->user_id);
        $this->query->prompt = $validated['prompt'];
        $this->query->description = $validated['description'];
        $this->query->sql_query = $validated['sql_query'];
        $this->query->save();
    }

    /**
     * Run the current SQL query — persists first, then re-renders results.
     */
    public function runQuery(): void
    {
        $this->save();
        $this->error = '';
        $this->resetPage();
    }

    /**
     * Generate title, description, and SQL from the natural-language prompt.
     *
     * Resolves provider credentials, builds a schema-aware prompt, calls the
     * LLM, and populates all three fields. Does not auto-run or auto-save.
     */
    public function generateSql(): void
    {
        $prompt = trim($this->editPrompt);

        if ($prompt === '') {
            $this->aiError = __('Please enter a prompt first.');

            return;
        }

        if ($this->selectedModelId === '') {
            $this->aiError = __('Please select an AI model.');

            return;
        }

        $this->isGenerating = true;
        $this->aiError = '';

        try {
            $config = $this->resolveModelConfig();

            if ($config === null) {
                return;
            }

            $schemaContext = $this->buildSchemaContext();

            $result = app(LlmClient::class)->chat(new ChatRequest(
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'],
                model: $config['model'],
                messages: [
                    [
                        'role' => 'system',
                        'content' => <<<SYSTEM
                        You are a database query assistant. Given a database schema and a natural language request, generate:
                        1. A short page title (max 60 chars)
                        2. A one-sentence description of what the query shows
                        3. A single SELECT SQL query

                        Respond in EXACTLY this format (three lines, no extra text):
                        TITLE: <title>
                        DESCRIPTION: <description>
                        SQL: <sql>

                        Rules:
                        - Only SELECT statements are allowed.
                        - Use the table and column names exactly as provided in the schema.
                        - The SQL must be a single statement, no semicolons.
                        - Respond in the same language as the user's request.

                        Database schema:
                        {$schemaContext}
                        SYSTEM,
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                maxTokens: 1024,
                temperature: 0.1,
                timeout: 30,
                providerName: $config['provider_name'],
            ));

            if (isset($result['error'])) {
                $this->aiError = $result['error'];

                return;
            }

            $content = trim($result['content'] ?? '');

            if ($content === '') {
                $this->aiError = __('The AI model returned an empty response. Please try again.');

                return;
            }

            $parsed = $this->parseGeneratedOutput($content);

            if ($parsed['title'] === '' && $parsed['description'] === '' && $parsed['sql'] === '') {
                $this->aiError = __('Could not parse the AI response. Raw output: :output', [
                    'output' => \Illuminate\Support\Str::limit($content, 200),
                ]);

                return;
            }

            if ($parsed['title'] !== '') {
                $this->editName = $parsed['title'];
            }
            if ($parsed['description'] !== '') {
                $this->editDescription = $parsed['description'];
            }
            if ($parsed['sql'] !== '') {
                $this->editSql = $parsed['sql'];
            }
        } catch (\Throwable $e) {
            $this->aiError = $e->getMessage() ?: __('An unexpected error occurred while generating the query.');
        } finally {
            $this->isGenerating = false;
        }
    }

    /**
     * Share this query with another user by creating an independent copy.
     *
     * The copy is owned by the target user, includes attribution in the
     * description, and a UserPin is auto-created for quick sidebar access.
     *
     * @param  int  $userId  The target user's ID
     */
    public function shareWith(int $userId): void
    {
        $newQuery = Query::query()->create([
            'user_id' => $userId,
            'name' => $this->query->name,
            'slug' => Query::generateSlug($this->query->name, $userId),
            'prompt' => $this->query->prompt,
            'sql_query' => $this->query->sql_query,
            'description' => 'Shared by '.auth()->user()->name
                .($this->query->description ? "\n\n".$this->query->description : ''),
            'icon' => $this->query->icon,
        ]);

        $pinUrl = route('admin.system.database-queries.show', $newQuery->slug);

        UserPin::query()->create([
            'user_id' => $userId,
            'label' => $newQuery->name,
            'url' => $pinUrl,
            'url_hash' => UserPin::hashUrl($pinUrl),
            'icon' => $newQuery->icon ?? 'heroicon-o-circle-stack',
            'sort_order' => (UserPin::query()->where('user_id', $userId)->max('sort_order') ?? -1) + 1,
        ]);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $columns = [];
        $rows = [];
        $total = 0;
        $perPage = 25;
        $currentPage = 1;
        $lastPage = 1;

        try {
            $executor = app(QueryExecutor::class);
            $result = $executor->execute($this->query->sql_query, $this->getPage());

            $columns = $result['columns'];
            $rows = $result['rows'];
            $total = $result['total'];
            $perPage = $result['per_page'];
            $currentPage = $result['current_page'];
            $lastPage = $result['last_page'];
            $this->error = '';
        } catch (BlbQueryException $e) {
            $this->error = $e->getMessage();
        }

        return view('livewire.admin.system.database-queries.show', [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'error' => $this->error,
            'availableModels' => $this->getAvailableModels(),
        ]);
    }

    /**
     * Check whether any editable field differs from the persisted model.
     */
    public function getIsDirtyProperty(): bool
    {
        return $this->editName !== $this->query->name
            || $this->editSql !== $this->query->sql_query
            || $this->editDescription !== ($this->query->description ?? '')
            || $this->editPrompt !== ($this->query->prompt ?? '');
    }

    /**
     * Get active AI models grouped by provider for the current user's company.
     *
     * @return list<array{id: string, label: string, provider: string, providerId: int}>
     */
    private function getAvailableModels(): array
    {
        $companyId = auth()->user()?->getCompanyId();

        if ($companyId === null) {
            return [];
        }

        $providers = AiProvider::query()
            ->forCompany($companyId)
            ->active()
            ->orderBy('priority')
            ->orderBy('display_name')
            ->get();

        $models = [];
        foreach ($providers as $provider) {
            $providerModels = AiProviderModel::query()
                ->where('ai_provider_id', $provider->id)
                ->active()
                ->orderBy('model_id')
                ->get();

            foreach ($providerModels as $model) {
                $models[] = [
                    'id' => $provider->id.':::'.$model->model_id,
                    'label' => $model->model_id,
                    'provider' => $provider->display_name,
                    'providerId' => (int) $provider->id,
                ];
            }
        }

        return $models;
    }

    /**
     * Resolve the default model ID from the highest-priority active provider.
     */
    private function resolveDefaultModelId(): string
    {
        $companyId = auth()->user()?->getCompanyId();

        if ($companyId === null) {
            return '';
        }

        $provider = AiProvider::query()
            ->forCompany($companyId)
            ->active()
            ->prioritized()
            ->first();

        if ($provider === null) {
            $provider = AiProvider::query()
                ->forCompany($companyId)
                ->active()
                ->orderBy('display_name')
                ->first();
        }

        if ($provider === null) {
            return '';
        }

        $model = AiProviderModel::query()
            ->where('ai_provider_id', $provider->id)
            ->active()
            ->default()
            ->first();

        if ($model === null) {
            $model = AiProviderModel::query()
                ->where('ai_provider_id', $provider->id)
                ->active()
                ->orderBy('model_id')
                ->first();
        }

        return $model !== null
            ? $provider->id.':::'.$model->model_id
            : '';
    }

    /**
     * Resolve provider credentials and model for the selected model ID.
     *
     * @return array{api_key: string, base_url: string, model: string, provider_name: string|null}|null
     */
    private function resolveModelConfig(): ?array
    {
        $parts = explode(':::', $this->selectedModelId, 2);

        if (count($parts) !== 2) {
            $this->aiError = __('Invalid model selection. Please choose a model and try again.');

            return null;
        }

        [$providerId, $modelId] = $parts;

        $provider = AiProvider::query()
            ->where('id', $providerId)
            ->active()
            ->first();

        if ($provider === null) {
            $this->aiError = __('The selected AI provider is no longer available. Please choose another model.');

            return null;
        }

        $credentials = app(RuntimeCredentialResolver::class)->resolve([
            'api_key' => $provider->api_key,
            'base_url' => $provider->base_url,
            'provider_name' => $provider->name,
        ]);

        if (isset($credentials['error'])) {
            $this->aiError = $credentials['error'];

            return null;
        }

        return [
            'api_key' => $credentials['api_key'],
            'base_url' => $credentials['base_url'],
            'model' => $modelId,
            'provider_name' => $provider->name,
        ];
    }

    /**
     * Build a compact schema description for the LLM prompt.
     *
     * Lists registered tables with their column names and types.
     */
    private function buildSchemaContext(): string
    {
        $tables = TableRegistry::query()
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $lines = [];
        foreach ($tables as $table) {
            try {
                $columns = Schema::getColumns($table);
                $colDefs = array_map(
                    fn (array $col): string => $col['name'].' '.$col['type_name'],
                    $columns,
                );
                $lines[] = $table.'('.implode(', ', $colDefs).')';
            } catch (\Throwable) {
                continue;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parse the structured LLM output into title, description, and SQL.
     *
     * Expected format:
     *   TITLE: <title>
     *   DESCRIPTION: <description>
     *   SQL: <sql>
     *
     * @return array{title: string, description: string, sql: string}
     */
    private function parseGeneratedOutput(string $content): array
    {
        $title = '';
        $description = '';
        $sql = '';

        if (preg_match('/^TITLE:\s*(.+)$/mi', $content, $m)) {
            $title = trim($m[1]);
        }

        if (preg_match('/^DESCRIPTION:\s*(.+)$/mi', $content, $m)) {
            $description = trim($m[1]);
        }

        if (preg_match('/^SQL:\s*(.+)/si', $content, $m)) {
            $sql = trim($m[1]);
            $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql);
            $sql = preg_replace('/\s*```\s*$/', '', $sql);
            $sql = rtrim(trim($sql), ';');
        }

        return ['title' => $title, 'description' => $description, 'sql' => $sql];
    }
}
