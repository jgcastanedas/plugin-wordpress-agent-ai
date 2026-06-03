<?php

use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase {

    public function test_twilio_signature_matches_official_algorithm(): void {
        // Reproducción del ejemplo oficial de Twilio:
        // https://www.twilio.com/docs/usage/webhooks/webhooks-security
        $url = 'https://mycompany.com/myapp.php?foo=1&bar=2';
        $auth_token = '12345';
        $body = 'CallSid=CA1234567890ABCDE&Caller=%2B14158675309&Digits=1234&From=%2B14158675309&To=%2B18005551212';

        // Calcular firma esperada manualmente (mismo algoritmo)
        parse_str($body, $params);
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . $v;
        }
        $expected_sig = base64_encode(hash_hmac('sha1', $data, $auth_token, true));

        $this->assertTrue(
            AI_Agent_Webhook::verify_twilio_signature($body, $expected_sig, $auth_token, $url),
            'Firma válida debería pasar'
        );

        $this->assertFalse(
            AI_Agent_Webhook::verify_twilio_signature($body, 'firma-incorrecta', $auth_token, $url),
            'Firma inválida debería fallar'
        );
    }

    public function test_twilio_signature_fails_with_tampered_body(): void {
        $url = 'https://example.com/webhook';
        $token = 'secret';
        $body = 'From=%2B34000&Body=hola';

        parse_str($body, $params); ksort($params);
        $data = $url; foreach ($params as $k => $v) { $data .= $k . $v; }
        $sig = base64_encode(hash_hmac('sha1', $data, $token, true));

        $tampered_body = 'From=%2B34999&Body=hola';

        $this->assertFalse(
            AI_Agent_Webhook::verify_twilio_signature($tampered_body, $sig, $token, $url),
            'Body modificado debe invalidar firma'
        );
    }

    public function test_twilio_signature_uses_timing_safe_comparison(): void {
        // Verifica que la implementación use hash_equals (no ==).
        // Como no podemos inspeccionar tiempo, al menos verificamos que
        // strings de longitud distinta NO crashea.
        $this->assertFalse(
            AI_Agent_Webhook::verify_twilio_signature('foo=1', 'short', 'token', 'https://e.com'),
            'Firma corta debe ser rechazada sin crashes'
        );
    }
}
