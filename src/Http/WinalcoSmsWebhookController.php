<?php

namespace Winalco\Sms\Http;

use Winalco\Sms\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WinalcoSmsWebhookController
{
    /**
     * Reçoit les états finaux (sent/failed/canceled) poussés par le relay.
     * Règles (cf. docs/winalco-sms-api-reference): vérifier la signature,
     * répondre 200 vite, dédupliquer, ne jamais retraiter un état final.
     */
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('winalco-sms.webhook_secret');

        if (! self::signatureValid((string) $request->header('X-Winalco-Signature'), $request->getContent(), $secret)) {
            abort(403);
        }

        $payload = $request->json()->all();
        $providerId = $payload['id'] ?? null;

        // Guard: don't query if id is missing/empty (prevents matching unrelated null rows).
        if (! is_string($providerId) || $providerId === '') {
            Log::notice('Webhook SMS: id inconnu', ['payload_id' => $providerId]);

            return response()->noContent(200);
        }

        $sms = SmsMessage::where('provider_id', $providerId)->first();

        if (! $sms) {
            // 200 quand même: un retry ne nous fera pas connaître cet id.
            Log::notice('Webhook SMS: id inconnu', ['payload_id' => $providerId]);
        } elseif (! $sms->isFinal()) {
            $sms->update([
                'status' => $payload['status'] ?? $sms->status,
                'error_code' => $payload['errorCode'] ?? null,
                'webhook_delivery_id' => (string) $request->header('X-Winalco-Delivery'),
            ]);
        }
        // Déjà final = retry d'une livraison déjà traitée -> 200 sans retraiter.

        return response()->noContent(200);
    }

    /**
     * HMAC-SHA256 sur "t.<corps brut>", tolérance 300 s, comparaison constante.
     * Statique et pure ($now injectable) pour être testable sans conteneur.
     */
    public static function signatureValid(string $header, string $rawBody, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $matches) !== 1) {
            return false;
        }

        [, $timestamp, $signature] = $matches;

        if (abs(($now ?? time()) - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
