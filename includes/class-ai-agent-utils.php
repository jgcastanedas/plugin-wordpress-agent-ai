<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utilidades puras (sin dependencias de WP runtime) reusables y testeables.
 */
class AI_Agent_Utils {

    /**
     * Catálogo de idiomas soportados por la detección y el agente.
     */
    public static function get_supported_languages() {
        return array(
            'es' => 'Español',
            'en' => 'English',
            'pt' => 'Português',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'zh' => '中文 (Chino simplificado)',
            'ja' => '日本語 (Japonés)',
        );
    }

    /**
     * Nombre legible del idioma (en español) para inyectar en el system prompt.
     */
    public static function get_language_name($code) {
        $names = array(
            'es' => 'español',
            'en' => 'inglés',
            'pt' => 'portugués',
            'fr' => 'francés',
            'de' => 'alemán',
            'it' => 'italiano',
            'zh' => 'chino simplificado',
            'ja' => 'japonés',
        );
        return $names[$code] ?? $code;
    }

    /**
     * Detecta el idioma del texto.
     *
     * @param string $text
     * @param array|null $restrict_to códigos permitidos (es/en/...). Si se pasa,
     *                               solo devuelve uno de esos códigos. Útil cuando
     *                               la conf restringe idiomas de respuesta.
     * @return string|null código ISO, o null si no se pudo detectar
     */
    public static function detect_language($text, $restrict_to = null) {
        $text = mb_strtolower((string) $text);

        // Detección de scripts CJK por bloques Unicode (muy preciso)
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u', $text)) {
            $lang = 'ja';
            return self::filter_lang($lang, $restrict_to);
        }
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text)) {
            // CJK Unified Ideographs sin kana → chino
            $lang = 'zh';
            return self::filter_lang($lang, $restrict_to);
        }

        $signals = array(
            'es' => array('hola', 'gracias', 'por favor', 'precio', 'producto', 'compra', 'pedido', 'cuánto', 'donde', 'cómo', 'qué', 'puedo', 'tengo', 'quiero', 'el ', 'la ', 'los ', 'las ', 'un ', 'una ', ' que ', ' de ', ' para ', ' con ', ' sin ', 'ñ'),
            'en' => array('hello', 'thanks', 'please', 'price', 'product', 'order', 'how much', 'where', 'how ', 'what ', 'i need', 'i want', 'the ', ' is ', ' are ', ' for ', ' with ', ' without ', ' how ', 'you '),
            'pt' => array('olá', 'obrigado', 'por favor', 'preço', 'produto', 'pedido', 'quanto', 'onde', 'como ', 'o que', 'eu quero', 'eu preciso', ' que ', ' de ', ' para ', ' com ', 'ção'),
            'fr' => array('bonjour', 'merci', 's\'il vous plaît', 'prix', 'produit', 'commande', 'combien', 'où', 'comment', 'quoi', 'je veux', 'je voudrais', ' le ', ' la ', ' les ', ' un ', ' une ', ' pour ', ' avec ', 'ç'),
            'de' => array('hallo', 'danke', 'bitte', 'preis', 'produkt', 'bestellung', 'wie viel', 'wo ', 'wie ', 'was ', 'ich möchte', 'ich brauche', ' der ', ' die ', ' das ', ' und ', ' für ', 'ß', 'ü', 'ö', 'ä'),
            'it' => array('ciao', 'grazie', 'per favore', 'prezzo', 'prodotto', 'ordine', 'quanto', 'dove', 'come ', 'cosa ', 'voglio', 'ho bisogno', ' il ', ' la ', ' i ', ' le ', ' un ', ' una ', ' per ', ' con '),
        );

        $scores = array();
        foreach ($signals as $lang => $words) {
            $count = 0;
            foreach ($words as $w) {
                $count += substr_count($text, $w);
            }
            $scores[$lang] = $count;
        }

        arsort($scores);
        $best = key($scores);

        if ($scores[$best] === 0) {
            return null;
        }

        return self::filter_lang($best, $restrict_to);
    }

    /**
     * Si el idioma detectado no está en la lista permitida, devuelve null
     * para que el caller pueda fallback al idioma por defecto.
     */
    private static function filter_lang($lang, $restrict_to) {
        if (empty($restrict_to) || !is_array($restrict_to)) {
            return $lang;
        }
        return in_array($lang, $restrict_to, true) ? $lang : null;
    }

    /**
     * Similitud coseno entre dos vectores numéricos.
     */
    public static function cosine_similarity($a, $b) {
        if (!is_array($a) || !is_array($b)) {
            return 0.0;
        }
        $n = count($a);
        if ($n === 0 || $n !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot    += $av * $bv;
            $norm_a += $av * $av;
            $norm_b += $bv * $bv;
        }

        $norm_a = sqrt($norm_a);
        $norm_b = sqrt($norm_b);

        if ($norm_a == 0.0 || $norm_b == 0.0) {
            return 0.0;
        }

        return $dot / ($norm_a * $norm_b);
    }
}
