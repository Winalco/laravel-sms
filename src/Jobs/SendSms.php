<?php

namespace Winalco\Sms\Jobs;

use Winalco\Sms\Models\SmsMessage;
use Winalco\Sms\WinalcoSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // ponytail: releases (quota 429) peuvent durer ~25 h; les exceptions restent limitées à 5
    public int $tries = 100;

    public int $maxExceptions = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public SmsMessage $smsMessage,
        public ?string $idempotencyKey = null,
    ) {}

    public function handle(WinalcoSms $sms): void
    {
        if (! WinalcoSms::configured()) {
            Log::warning('SMS non envoyé: WINALCO_SMS_KEY absent', ['sms_message_id' => $this->smsMessage->id]);
            $this->smsMessage->update(['status' => SmsMessage::STATUS_FAILED, 'error_code' => 'not_configured']);

            return;
        }

        try {
            $response = $sms->send(
                $this->smsMessage->to,
                $this->smsMessage->message,
                $this->idempotencyKey ?? 'sms-'.$this->smsMessage->id,
            );
        } catch (RequestException $e) {
            $status = $e->response->status();

            if ($status === 429) {
                // ponytail: re-tentative fixe 15 min sur quota; affiner si des campagnes apparaissent
                $this->release(900);

                return;
            }

            if (in_array($status, [400, 401], true)) {
                if ($status === 401) {
                    Log::critical('Clé API Winalco SMS invalide ou révoquée', ['sms_message_id' => $this->smsMessage->id]);
                }
                $this->smsMessage->update(['status' => SmsMessage::STATUS_FAILED, 'error_code' => 'http_'.$status]);
                $this->fail($e);

                return;
            }

            throw $e; // 5xx / réseau: le backoff de la queue réessaie
        }

        // 201 = accepté; 200 = rejeu d'Idempotency-Key (message d'origine, rien de renvoyé)
        $providerId = $response['id'] ?? null;

        if ($providerId !== null && SmsMessage::where('provider_id', $providerId)->where('id', '!=', $this->smsMessage->id)->exists()) {
            // Rejeu d'Idempotency-Key: le SMS d'origine existe déjà sur une autre ligne.
            $this->smsMessage->update(['status' => SmsMessage::STATUS_FAILED, 'error_code' => 'duplicate']);

            return;
        }

        $this->smsMessage->update([
            'provider_id' => $providerId,
            'status' => $response['status'] ?? SmsMessage::STATUS_PENDING,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->smsMessage->update([
            'status' => SmsMessage::STATUS_FAILED,
            'error_code' => $this->smsMessage->error_code ?? 'send_failed',
        ]);
    }
}
