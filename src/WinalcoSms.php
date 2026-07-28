<?php

namespace Winalco\Sms;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WinalcoSms
{
    public static function configured(): bool
    {
        return (bool) config('winalco-sms.key');
    }

    public function send(string $to, string $message, ?string $idempotencyKey = null): array
    {
        $request = $this->client();

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $request->post('/api/v1/sms/send', ['to' => $to, 'message' => $message])
            ->throw()
            ->json();
    }

    public function sendBulk(array $to, string $message): array
    {
        return $this->client()->post('/api/v1/sms/send-bulk', ['to' => $to, 'message' => $message])
            ->throw()
            ->json();
    }

    public function usage(): array
    {
        return $this->client()->get('/api/v1/sms/usage')->throw()->json();
    }

    public function status(string $id): array
    {
        return $this->client()->get('/api/v1/sms/'.$id)->throw()->json();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('winalco-sms.base_url'))
            ->withHeaders(['X-Api-Key' => (string) config('winalco-sms.key')])
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }
}
