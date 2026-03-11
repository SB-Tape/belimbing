<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Livewire;

use App\Base\AI\Services\LlmClient;
use App\Modules\Core\AI\Services\AgenticRuntime;
use App\Modules\Core\AI\Services\ConfigResolver;
use App\Modules\Core\AI\Services\LaraOrchestrationService;
use App\Modules\Core\AI\Services\LaraPromptFactory;
use App\Modules\Core\AI\Services\MessageManager;
use App\Modules\Core\AI\Services\RuntimeCredentialResolver;
use App\Modules\Core\AI\Services\RuntimeMessageBuilder;
use App\Modules\Core\AI\Services\SessionManager;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Employee\Models\Employee;
use Livewire\Attributes\On;
use Livewire\Component;

class LaraChatOverlay extends Component
{
    public string $messageInput = '';

    public ?string $selectedSessionId = null;

    public bool $isLoading = false;

    /** @var array<string, mixed>|null */
    public ?array $lastRunMeta = null;

    public ?string $editingSessionId = null;

    public string $editingTitle = '';

    public function mount(): void
    {
        if (! $this->isLaraActivated()) {
            return;
        }

        $sessions = app(SessionManager::class)->list(Employee::LARA_ID);
        if (! empty($sessions)) {
            $this->selectedSessionId = $sessions[0]->id;
        }
    }

    #[On('lara-chat-opened')]
    public function onLaraChatOpened(): void
    {
        if (! $this->isLaraActivated()) {
            return;
        }

        if ($this->selectedSessionId === null) {
            $sessions = app(SessionManager::class)->list(Employee::LARA_ID);
            if (! empty($sessions)) {
                $this->selectedSessionId = $sessions[0]->id;
            }
        }

        $this->dispatch('lara-focus-composer');
    }

    public function createSession(): void
    {
        if (! $this->isLaraActivated()) {
            return;
        }

        $session = app(SessionManager::class)->create(Employee::LARA_ID);
        $this->selectedSessionId = $session->id;
        $this->lastRunMeta = null;
        $this->dispatch('lara-focus-composer');
    }

    public function selectSession(string $sessionId): void
    {
        $this->selectedSessionId = $sessionId;
        $this->lastRunMeta = null;
        $this->dispatch('lara-focus-composer');
    }

    public function deleteSession(string $sessionId): void
    {
        if (! $this->isLaraActivated()) {
            return;
        }

        app(SessionManager::class)->delete(Employee::LARA_ID, $sessionId);

        if ($this->selectedSessionId === $sessionId) {
            $sessions = app(SessionManager::class)->list(Employee::LARA_ID);
            $this->selectedSessionId = empty($sessions) ? null : $sessions[0]->id;
        }

        $this->lastRunMeta = null;
    }

    /**
     * Start inline-editing a session title.
     */
    public function startEditingTitle(string $sessionId): void
    {
        $session = app(SessionManager::class)->get(Employee::LARA_ID, $sessionId);
        $this->editingSessionId = $sessionId;
        $this->editingTitle = $session?->title ?? '';
    }

    /**
     * Save the edited session title and exit inline-editing mode.
     */
    public function saveTitle(): void
    {
        if ($this->editingSessionId === null) {
            return;
        }

        $title = trim($this->editingTitle);

        if ($title !== '') {
            app(SessionManager::class)->updateTitle(Employee::LARA_ID, $this->editingSessionId, $title);
        }

        $this->editingSessionId = null;
        $this->editingTitle = '';
    }

    /**
     * Cancel inline-editing without saving.
     */
    public function cancelEditingTitle(): void
    {
        $this->editingSessionId = null;
        $this->editingTitle = '';
    }

    /**
     * Ask Lara to generate a session title from the conversation history.
     */
    public function generateSessionTitle(string $sessionId): void
    {
        if (! $this->isLaraActivated()) {
            return;
        }

        $messageManager = app(MessageManager::class);
        $messages = $messageManager->read(Employee::LARA_ID, $sessionId);

        if (empty($messages)) {
            return;
        }

        $configResolver = app(ConfigResolver::class);
        $config = $configResolver->resolvePrimaryWithDefaultFallback(Employee::LARA_ID);

        if ($config === null) {
            return;
        }

        $credentialResolver = app(RuntimeCredentialResolver::class);
        $credentials = $credentialResolver->resolve($config);

        if (isset($credentials['error'])) {
            return;
        }

        $messageBuilder = app(RuntimeMessageBuilder::class);
        $apiMessages = $messageBuilder->build(
            $messages,
            'Generate a concise 3–6 word title summarizing this conversation. Reply with only the title, no quotes or punctuation.',
        );

        $llmClient = app(LlmClient::class);
        $response = $llmClient->chat(
            baseUrl: $credentials['base_url'],
            apiKey: $credentials['api_key'],
            model: $config['model'],
            messages: $apiMessages,
            maxTokens: 20,
            temperature: 0.5,
            timeout: 15,
            providerName: $config['provider_name'] ?? null,
        );

        $title = trim($response['content'] ?? '');

        if ($title === '') {
            return;
        }

        // Strip surrounding quotes if the LLM wrapped the title
        $title = trim($title, '"\'');

        app(SessionManager::class)->updateTitle(Employee::LARA_ID, $sessionId, $title);

        // If we're currently editing this session, update the editing state too
        if ($this->editingSessionId === $sessionId) {
            $this->editingTitle = $title;
        }
    }

    public function sendMessage(): void
    {
        if (! $this->isLaraActivated() || trim($this->messageInput) === '') {
            return;
        }

        $sessionManager = app(SessionManager::class);
        if ($this->selectedSessionId === null) {
            $session = $sessionManager->create(Employee::LARA_ID);
            $this->selectedSessionId = $session->id;
        }

        $this->isLoading = true;
        $content = trim($this->messageInput);
        $this->messageInput = '';

        $messageManager = app(MessageManager::class);
        $messageManager->appendUserMessage(Employee::LARA_ID, $this->selectedSessionId, $content);

        $messages = $messageManager->read(Employee::LARA_ID, $this->selectedSessionId);
        $orchestration = app(LaraOrchestrationService::class)->dispatchFromMessage($content);

        if ($orchestration !== null) {
            $result = [
                'content' => $orchestration['assistant_content'],
                'run_id' => $orchestration['run_id'],
                'meta' => $orchestration['meta'],
            ];
        } else {
            $runtime = app(AgenticRuntime::class);
            $systemPrompt = app(LaraPromptFactory::class)->buildForCurrentUser($content);
            $result = $runtime->run($messages, Employee::LARA_ID, $systemPrompt);

            // Extract <lara-action> JS blocks from response (may come from NavigateTool or direct LLM).
            $actionJs = $this->extractLaraAction($result['content']);
            if ($actionJs !== null) {
                $result['content'] = $actionJs['clean_content'];
                $result['meta']['orchestration'] = [
                    'status' => 'browser_action',
                    'js' => $actionJs['js'],
                ];
            }
        }

        $messageManager->appendAssistantMessage(
            Employee::LARA_ID,
            $this->selectedSessionId,
            $result['content'],
            $result['run_id'],
            $result['meta'],
        );

        $this->lastRunMeta = [
            'run_id' => $result['run_id'],
            ...$result['meta'],
        ];

        $navigationUrl = $result['meta']['orchestration']['navigation']['url'] ?? null;
        if (is_string($navigationUrl) && str_starts_with($navigationUrl, '/')) {
            $this->dispatch('lara-execute-js', js: "Livewire.navigate('".$navigationUrl."')");
        }

        $actionJs = $result['meta']['orchestration']['js'] ?? null;
        if (is_string($actionJs) && $actionJs !== '') {
            $this->dispatch('lara-execute-js', js: $actionJs);
        }

        $this->isLoading = false;
        $this->dispatch('lara-response-ready');
        $this->dispatch('lara-focus-composer');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $laraExists = Employee::query()->whereKey(Employee::LARA_ID)->exists();
        $laraActivated = $this->isLaraActivated();

        $sessions = [];
        $messages = [];

        if ($laraActivated) {
            $sessions = app(SessionManager::class)->list(Employee::LARA_ID);
        }

        if ($laraActivated && $this->selectedSessionId !== null) {
            $messages = app(MessageManager::class)->read(Employee::LARA_ID, $this->selectedSessionId);
        }

        return view('livewire.ai.lara-chat-overlay', [
            'laraExists' => $laraExists,
            'laraActivated' => $laraActivated,
            'sessions' => $sessions,
            'messages' => $messages,
        ]);
    }

    private function isLaraActivated(): bool
    {
        $isActivated = false;

        if (Employee::query()->whereKey(Employee::LARA_ID)->exists()) {
            $resolver = app(ConfigResolver::class);
            $configs = $resolver->resolve(Employee::LARA_ID);
            $isActivated = count($configs) > 0;

            if (! $isActivated && Company::query()->whereKey(Company::LICENSEE_ID)->exists()) {
                $isActivated = $resolver->resolveDefault(Company::LICENSEE_ID) !== null;
            }
        }

        return $isActivated;
    }

    /**
     * Extract `<lara-action>` JS block from LLM response content.
     *
     * @return array{js: string, clean_content: string}|null
     */
    private function extractLaraAction(string $content): ?array
    {
        if (preg_match('/<lara-action>(.*?)<\/lara-action>/s', $content, $matches) !== 1) {
            return null;
        }

        $js = trim($matches[1]);
        $clean = trim(str_replace($matches[0], '', $content));

        if ($js === '') {
            return null;
        }

        return ['js' => $js, 'clean_content' => $clean ?: $js];
    }
}
