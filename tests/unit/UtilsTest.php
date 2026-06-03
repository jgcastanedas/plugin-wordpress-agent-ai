<?php

use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase {

    // ─────────── detect_language ───────────

    public function test_detects_spanish(): void {
        $this->assertSame('es', AI_Agent_Utils::detect_language('Hola, ¿cuánto cuesta el producto?'));
    }

    public function test_detects_english(): void {
        $this->assertSame('en', AI_Agent_Utils::detect_language('Hello, how much is the product?'));
    }

    public function test_detects_portuguese(): void {
        $this->assertSame('pt', AI_Agent_Utils::detect_language('Olá, quanto custa o produto?'));
    }

    public function test_detects_french(): void {
        $this->assertSame('fr', AI_Agent_Utils::detect_language('Bonjour, combien coûte le produit ?'));
    }

    public function test_detects_german(): void {
        $this->assertSame('de', AI_Agent_Utils::detect_language('Hallo, wie viel kostet das Produkt?'));
    }

    public function test_detects_italian(): void {
        $this->assertSame('it', AI_Agent_Utils::detect_language('Ciao, quanto costa il prodotto?'));
    }

    public function test_detects_chinese_by_script(): void {
        $this->assertSame('zh', AI_Agent_Utils::detect_language('你好，这个产品多少钱？'));
    }

    public function test_detects_japanese_by_kana(): void {
        $this->assertSame('ja', AI_Agent_Utils::detect_language('こんにちは、この製品はいくらですか？'));
    }

    public function test_returns_null_for_gibberish(): void {
        $this->assertNull(AI_Agent_Utils::detect_language('xyzxyz qwerty 12345'));
    }

    public function test_returns_null_for_empty_string(): void {
        $this->assertNull(AI_Agent_Utils::detect_language(''));
    }

    public function test_respects_restrict_to_filter(): void {
        // Detectaría español pero solo se permite EN/FR → devuelve null para fallback
        $this->assertNull(
            AI_Agent_Utils::detect_language('Hola, gracias por tu ayuda', array('en', 'fr'))
        );

        // Detecta inglés y está permitido
        $this->assertSame(
            'en',
            AI_Agent_Utils::detect_language('Hello, thanks for the help', array('en', 'fr'))
        );
    }

    public function test_empty_restrict_list_means_no_restriction(): void {
        $this->assertSame('es', AI_Agent_Utils::detect_language('Hola gracias', array()));
        $this->assertSame('es', AI_Agent_Utils::detect_language('Hola gracias', null));
    }

    // ─────────── cosine_similarity ───────────

    public function test_cosine_identical_vectors(): void {
        $this->assertEqualsWithDelta(1.0, AI_Agent_Utils::cosine_similarity([1.0, 2.0, 3.0], [1.0, 2.0, 3.0]), 1e-10);
    }

    public function test_cosine_orthogonal_vectors(): void {
        $this->assertEqualsWithDelta(0.0, AI_Agent_Utils::cosine_similarity([1.0, 0.0], [0.0, 1.0]), 1e-10);
    }

    public function test_cosine_opposite_vectors(): void {
        $this->assertEqualsWithDelta(-1.0, AI_Agent_Utils::cosine_similarity([1.0, 1.0], [-1.0, -1.0]), 1e-10);
    }

    public function test_cosine_handles_zero_vector(): void {
        $this->assertSame(0.0, AI_Agent_Utils::cosine_similarity([0, 0, 0], [1, 2, 3]));
    }

    public function test_cosine_handles_mismatched_dimensions(): void {
        $this->assertSame(0.0, AI_Agent_Utils::cosine_similarity([1, 2], [1, 2, 3]));
    }

    public function test_cosine_handles_non_array_input(): void {
        $this->assertSame(0.0, AI_Agent_Utils::cosine_similarity(null, [1, 2, 3]));
        $this->assertSame(0.0, AI_Agent_Utils::cosine_similarity([1, 2, 3], 'not an array'));
    }

    // ─────────── supported languages ───────────

    public function test_supported_languages_includes_chinese_and_japanese(): void {
        $langs = AI_Agent_Utils::get_supported_languages();
        $this->assertArrayHasKey('zh', $langs);
        $this->assertArrayHasKey('ja', $langs);
        $this->assertArrayHasKey('es', $langs);
        $this->assertArrayHasKey('en', $langs);
    }

    public function test_get_language_name_returns_spanish_label(): void {
        $this->assertSame('chino simplificado', AI_Agent_Utils::get_language_name('zh'));
        $this->assertSame('inglés', AI_Agent_Utils::get_language_name('en'));
        $this->assertSame('xx', AI_Agent_Utils::get_language_name('xx'));
    }
}
