# winalco/laravel-sms

Client Laravel pour le relay SMS Winalco (`https://sms-relay.winalco.dz`) : envoi
transactionnel, suivi en base, webhook de statut signé HMAC.

Référence API complète : [`docs/winalco-sms-api-reference.md`](docs/winalco-sms-api-reference.md).

> **Prérequis :** une clé API du relay. Le code de ce package est libre (MIT),
> mais l'envoi de SMS nécessite un compte sur la plateforme Winalco —
> inscrivez-vous puis créez votre clé dans console → API Keys.

## Installation

```bash
composer require winalco/laravel-sms
php artisan migrate   # crée sms_messages (no-op si elle existe déjà)
```

Si le package n'est pas encore disponible sur Packagist, ajoutez d'abord le
dépôt Git dans votre `composer.json` :

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/winalco/laravel-sms" }
]
```

`.env` :

```
WINALCO_SMS_KEY=wak_...              # console -> API Keys
WINALCO_SMS_URL=https://sms-relay.winalco.dz
WINALCO_SMS_WEBHOOK_SECRET=whs_...   # après enregistrement du webhook
```

Config publiable si besoin : `php artisan vendor:publish --tag=winalco-sms-config`.

## Usage

```php
use Winalco\Sms\Models\SmsMessage;

// Point d'entrée unique: valide le numéro algérien (05/06/07, +213, 00213),
// trace la ligne, envoie via la queue. Retourne null (loggé) si numéro
// invalide ou échec interne - ne casse jamais l'appelant.
SmsMessage::queue(
    to: $user->phone,
    message: __('Winalco: paiement confirmé. Réf :ref.', ['ref' => $ref]),
    notable: $order,                   // morph optionnel
    context: 'payment_confirmation',   // libre, affiché dans vos UIs
    idempotencyKey: 'order-paid-'.$order->id, // anti double-envoi côté relay
);
```

Client bas niveau : `Winalco\Sms\WinalcoSms` — `send()`, `sendBulk()` (max 500),
`usage()` (quotas), `status($id)`. Statuts finaux: `sent`, `failed`, `canceled`
(`sent` = remis au réseau, pas une preuve de réception).

## Webhook de statut

La route `POST /api/webhooks/winalco-sms` est enregistrée automatiquement
(middleware `api`, nom `webhooks.winalco-sms`). Signature HMAC-SHA256 vérifiée
(tolérance 300 s, comparaison constante) ; sans `WINALCO_SMS_WEBHOOK_SECRET`
configuré, tout est rejeté (403).

Ordre de mise en service (impératif) : déployer le code -> `php artisan migrate`
-> enregistrer l'URL HTTPS publique dans console -> API Keys -> copier le secret
`whs_` (affiché une seule fois) dans `.env` -> vider le cache config.

## Queue

Les envois passent par le job `Winalco\Sms\Jobs\SendSms` (connexion queue par
défaut). Sur hébergement mutualisé, un cron par minute suffit :

```
* * * * * cd /chemin/app && php artisan queue:work --stop-when-empty --max-time=50
```

Messages GSM-7 : rester ≤ 160 caractères et éviter `ê â î ô û ç`
(bascule silencieuse en UCS-2 à 70 caractères par segment).

## Tests

```bash
composer install
vendor/bin/pest
```

## Licence

Code sous [licence MIT](LICENSE). Le service d'envoi lui-même (relay, SIM,
quotas) est fourni par la plateforme Winalco et nécessite un compte et une
clé API — voir `contact@winalco.dz`.
