<?php

namespace Winalco\Sms\Models;

use Winalco\Sms\Jobs\SendSms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;

class SmsMessage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const FINAL_STATUSES = [self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_CANCELED];

    protected $fillable = [
        'provider_id', 'to', 'message', 'status', 'error_code',
        'notable_type', 'notable_id', 'context', 'webhook_delivery_id',
    ];

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'success',
            self::STATUS_FAILED => 'error',
            self::STATUS_CANCELED => 'info',
            default => 'warning',
        };
    }

    /**
     * Normalise un mobile algérien (05/06/07, +213, 00213), null si invalide.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[\s.\-\/()]+/', '', (string) $phone);

        return preg_match('/^(\+213|00213|0)[567]\d{8}$/', $phone) === 1 ? $phone : null;
    }

    /**
     * Point d'entrée unique: valide le numéro, trace l'envoi, dispatch le job.
     * Retourne null (déjà loggé) si le numéro est invalide ou si la mise en file
     * échoue - l'appelant (paiement, lead...) ne doit jamais casser à cause d'un SMS.
     */
    public static function queue(?string $to, string $message, ?Model $notable = null, ?string $context = null, ?string $idempotencyKey = null): ?self
    {
        $normalized = self::normalizePhone($to);

        if ($normalized === null) {
            Log::warning('SMS ignoré: numéro invalide', ['to' => $to, 'context' => $context]);

            return null;
        }

        $sms = null;

        try {
            $sms = self::create([
                'to' => $normalized,
                'message' => $message,
                'status' => self::STATUS_QUEUED,
                'context' => $context,
                'notable_type' => $notable?->getMorphClass(),
                'notable_id' => $notable?->getKey(),
            ]);

            SendSms::dispatch($sms, $idempotencyKey);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('SMS non mis en file: erreur interne', ['to' => $normalized, 'context' => $context]);

            if ($sms !== null) {
                $sms->update(['status' => self::STATUS_FAILED, 'error_code' => 'dispatch_failed']);
            }

            return null;
        }

        return $sms;
    }
}
