<?php

use PHPUnit\Framework\TestCase;

final class LlmProviderTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['_options'] = array();
    }

    public function test_openai_compatible_providers_contains_chinese(): void {
        $providers = AI_Agent_LLM_Provider::get_openai_compatible_providers();

        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('deepseek', $providers);
        $this->assertArrayHasKey('kimi', $providers);
        $this->assertArrayHasKey('minimax', $providers);
    }

    public function test_base_urls_are_https(): void {
        foreach (AI_Agent_LLM_Provider::get_openai_compatible_providers() as $key => $cfg) {
            $this->assertStringStartsWith('https://', $cfg['base_url'], "Provider {$key} debe usar HTTPS");
            $this->assertNotEmpty($cfg['default_model'], "Provider {$key} debe tener default_model");
            $this->assertNotEmpty($cfg['models'], "Provider {$key} debe tener al menos un modelo");
        }
    }

    public function test_api_key_from_option(): void {
        update_option('ai_agent_deepseek_key', 'sk-test-deepseek');
        $this->assertSame('sk-test-deepseek', AI_Agent_LLM_Provider::get_api_key('deepseek'));
    }

    public function test_env_constant_overrides_option(): void {
        if (defined('AI_AGENT_DEEPSEEK_KEY')) {
            $this->markTestSkipped('AI_AGENT_DEEPSEEK_KEY ya definido en otro test');
        }
        define('AI_AGENT_DEEPSEEK_KEY', 'sk-from-env');
        update_option('ai_agent_deepseek_key', 'sk-from-db');

        $this->assertSame('sk-from-env', AI_Agent_LLM_Provider::get_api_key('deepseek'));
    }

    public function test_get_base_url_returns_correct_endpoint(): void {
        $this->assertSame('https://api.deepseek.com/v1', AI_Agent_LLM_Provider::get_base_url('deepseek'));
        $this->assertSame('https://api.moonshot.ai/v1', AI_Agent_LLM_Provider::get_base_url('kimi'));
        $this->assertSame('https://api.minimax.chat/v1', AI_Agent_LLM_Provider::get_base_url('minimax'));
        $this->assertSame('', AI_Agent_LLM_Provider::get_base_url('nonexistent'));
    }

    public function test_get_model_falls_back_to_default(): void {
        $GLOBALS['_options'] = array();
        $this->assertSame(
            AI_Agent_LLM_Provider::DEFAULT_DEEPSEEK_MODEL,
            AI_Agent_LLM_Provider::get_model('deepseek')
        );
    }

    public function test_get_model_uses_option_when_set(): void {
        update_option('ai_agent_kimi_model', 'kimi-k2-0905-preview');
        $this->assertSame('kimi-k2-0905-preview', AI_Agent_LLM_Provider::get_model('kimi'));
    }
}
