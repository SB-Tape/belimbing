<?php

use App\Modules\Core\AI\Services\LaraCapabilityMatcher;
use App\Modules\Core\AI\Tools\AgentListTool;
use Tests\Support\AssertsToolBehavior;
use Tests\TestCase;

uses(TestCase::class, AssertsToolBehavior::class);

const AGENT_LIST_DATA_ANALYST = 'Data Analyst';
const AGENT_LIST_CODE_REVIEWER = 'Code Reviewer';

beforeEach(function () {
    $this->matcher = Mockery::mock(LaraCapabilityMatcher::class);
    $this->tool = new AgentListTool($this->matcher);
});

describe('tool metadata', function () {
    it('has the expected metadata', function () {
        $this->assertToolMetadata(
            $this->tool,
            'agent_list',
            'ai.tool_agent_list.execute',
            ['capability_filter'],
            [],
        );
    });
});

describe('agent discovery', function () {
    it('returns message when no agents available', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([]);

        $result = $this->tool->execute([]);

        expect((string) $result)->toContain('No Agents available');
    });

    it('lists available agents', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => AGENT_LIST_DATA_ANALYST, 'capability_summary' => 'Analyzes data and generates reports'],
                ['employee_id' => 2, 'name' => AGENT_LIST_CODE_REVIEWER, 'capability_summary' => 'Reviews code for quality'],
            ]);

        $result = $this->tool->execute([]);

        expect((string) $result)->toContain('2 Agents available')
            ->and((string) $result)->toContain(AGENT_LIST_DATA_ANALYST)
            ->and((string) $result)->toContain('ID: 1')
            ->and((string) $result)->toContain(AGENT_LIST_CODE_REVIEWER)
            ->and((string) $result)->toContain('ID: 2')
            ->and((string) $result)->toContain('Analyzes data');
    });

    it('shows singular form for one agent', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 5, 'name' => 'Solo Agent', 'capability_summary' => 'General tasks'],
            ]);

        $result = $this->tool->execute([]);

        expect((string) $result)->toContain('1 Agent available')
            ->and((string) $result)->not->toContain('Agents available');
    });
});

describe('capability filtering', function () {
    it('filters agents by capability keyword', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => AGENT_LIST_DATA_ANALYST, 'capability_summary' => 'Analyzes data and generates reports'],
                ['employee_id' => 2, 'name' => AGENT_LIST_CODE_REVIEWER, 'capability_summary' => 'Reviews code for quality'],
            ]);

        $result = $this->tool->execute(['capability_filter' => 'data']);

        expect((string) $result)->toContain(AGENT_LIST_DATA_ANALYST)
            ->and((string) $result)->not->toContain(AGENT_LIST_CODE_REVIEWER);
    });

    it('performs case-insensitive filtering', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => AGENT_LIST_DATA_ANALYST, 'capability_summary' => 'Analyzes DATA reports'],
            ]);

        $result = $this->tool->execute(['capability_filter' => 'data']);

        expect((string) $result)->toContain(AGENT_LIST_DATA_ANALYST);
    });

    it('returns no match message when filter excludes all agents', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => AGENT_LIST_DATA_ANALYST, 'capability_summary' => 'Analyzes data'],
            ]);

        $result = $this->tool->execute(['capability_filter' => 'nonexistent']);

        expect((string) $result)->toContain('No Agents match the filter');
    });

    it('ignores empty capability filter', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => 'Agent', 'capability_summary' => 'General'],
            ]);

        $result = $this->tool->execute(['capability_filter' => '']);

        expect((string) $result)->toContain('Agent');
    });

    it('ignores non-string capability filter', function () {
        $this->matcher->shouldReceive('discoverDelegableAgentsForCurrentUser')
            ->once()
            ->andReturn([
                ['employee_id' => 1, 'name' => 'Agent', 'capability_summary' => 'General'],
            ]);

        $result = $this->tool->execute(['capability_filter' => 123]);

        expect((string) $result)->toContain('Agent');
    });
});
