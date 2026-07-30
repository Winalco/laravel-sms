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
(middleware `throttle:600,1`, nom `webhooks.winalco-sms`). Signature HMAC-SHA256
vérifiée (tolérance 300 s, comparaison constante) ; sans
`WINALCO_SMS_WEBHOOK_SECRET` configuré, tout est rejeté (403). Seuls les statuts
finaux (`sent`, `failed`, `canceled`) sont acceptés ; toute autre valeur est
ignorée avec un 200 (pas de retry côté relay).

La limite est explicite et non le groupe `api` : Laravel 11 a retiré
`throttle:api` de ce groupe par défaut, le webhook serait donc sans limite sur
Laravel 11+. 600/min laisse passer les rafales d'un `sendBulk` de 500.

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

## Rétention des données

Chaque ligne `sms_messages` conserve le numéro du destinataire et le corps du
message — souvent un OTP ou une référence de paiement. Rien n'est supprimé par
défaut. Pour purger, fixez une durée et planifiez `model:prune` :

```
WINALCO_SMS_PRUNE_AFTER_DAYS=90
```

```php
// routes/console.php (ou app/Console/Kernel.php)
Schedule::command('model:prune', ['--model' => [\Winalco\Sms\Models\SmsMessage::class]])->daily();
```

Les numéros n'apparaissent jamais en clair dans les logs du package (seuls les
3 derniers chiffres sont conservés).

## Tests

```bash
composer install
vendor/bin/pest
```

Compatibilité : Laravel 10, 11, 12 et 13 (PHP 8.1+). La CI teste les quatre.
`composer update --with orchestra/testbench:^9.0` (8/9/10/11 = Laravel
10/11/12/13) force une version en local.

Note : Laravel 10 et 11 ont atteint la fin de support sécurité amont ; ils
restent installables, mais migrez vers 12 ou 13 dès que possible.

## Licence

Code sous [licence MIT](LICENSE). Le service d'envoi lui-même (relay, SIM,
quotas) est fourni par la plateforme Winalco et nécessite un compte et une
clé API — voir `contact@winalco.dz`.
