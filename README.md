# winalco/laravel-sms

Envoyez des SMS depuis votre application Laravel via le relay Winalco : envoi
transactionnel, suivi en base, webhook de statut signé.

## 1. Créer votre compte et votre clé API

Le code de ce package est libre (MIT), mais l'envoi passe par la plateforme
Winalco : il vous faut un compte et une clé API.

> ### 👉 [Créer un compte / ouvrir la console](https://sms-relay.winalco.dz/app/)
> Plan gratuit, une adresse e-mail suffit, sans carte bancaire.
> Présentation du service et tarifs : <https://sms-relay.winalco.dz>

Une fois connecté, allez dans **console → API Keys** et créez une clé : vous
obtenez un jeton `wak_...` **affiché une seule fois**, copiez-le tout de suite.

Le champ **status webhook** de cette même page sert à l'étape 4 — laissez-le vide
pour l'instant, tant que votre application n'est pas déployée.

## 2. Installer le package

```bash
composer require winalco/laravel-sms
php artisan migrate   # crée la table sms_messages (no-op si elle existe déjà)
```

Dans votre `.env` :

```
WINALCO_SMS_KEY=wak_...              # la clé de l'étape 1
WINALCO_SMS_URL=https://sms-relay.winalco.dz
WINALCO_SMS_WEBHOOK_SECRET=          # rempli à l'étape 4
```

Pour ajuster la configuration : `php artisan vendor:publish --tag=winalco-sms-config`.

## 3. Envoyer un SMS

```php
use Winalco\Sms\Models\SmsMessage;

// Point d'entrée unique : valide le numéro algérien (05/06/07, +213, 00213),
// trace la ligne, envoie via la queue. Retourne null (loggé) si le numéro est
// invalide ou en cas d'échec interne — ne casse jamais l'appelant.
SmsMessage::queue(
    to: $user->phone,
    message: __('Winalco : paiement confirmé. Réf :ref.', ['ref' => $ref]),
    notable: $order,                          // morph optionnel
    context: 'payment_confirmation',          // libre, affiché dans vos UIs
    idempotencyKey: 'order-paid-'.$order->id, // anti double-envoi côté relay
);
```

Les envois passent par le job `Winalco\Sms\Jobs\SendSms` : un worker de queue
doit tourner. Sur hébergement mutualisé, un cron par minute suffit :

```
* * * * * cd /chemin/app && php artisan queue:work --stop-when-empty --max-time=50
```

Besoin de plus bas niveau ? `Winalco\Sms\WinalcoSms` expose `send()`,
`sendBulk()` (max 500 numéros), `usage()` (quotas) et `status($id)`.

**Longueur des messages :** restez ≤ 160 caractères et évitez `ê â î ô û ç`
(bascule silencieuse en UCS-2, soit 70 caractères par segment).

## 4. Recevoir les statuts (webhook)

Le package expose déjà la route `POST /api/webhooks/winalco-sms` : signature
HMAC-SHA256 vérifiée, doublons ignorés, statuts finaux (`sent`, `failed`,
`canceled`) écrits sur la ligne `sms_messages`. Vous n'avez rien à coder.

Faites-le dans cet ordre, sinon le relay enverra des statuts dans le vide :

1. Déployez le code et lancez `php artisan migrate` en production.
2. Dans [console → API Keys](https://sms-relay.winalco.dz/app/), renseignez le
   **status webhook** avec `https://votre-domaine.tld/api/webhooks/winalco-sms`
   (HTTPS et publiquement joignable — pas de `localhost`).
3. Copiez le secret `whs_...` **affiché une seule fois** dans
   `WINALCO_SMS_WEBHOOK_SECRET`.
4. Videz le cache : `php artisan config:clear`.

Tant que `WINALCO_SMS_WEBHOOK_SECRET` est vide, toute requête entrante est
rejetée en 403 — c'est voulu. Si vous désactivez puis réactivez le webhook dans
la console, un **nouveau** secret est émis : pensez à mettre `.env` à jour.

À noter : le statut `sent` signifie « remis au réseau mobile », pas « reçu par le
destinataire ».

## 5. Rétention des données

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

## Compatibilité

Laravel 10, 11, 12 et 13 · PHP 8.1+.

## Aide

- Détail de l'API HTTP : [`docs/winalco-sms-api-reference.md`](docs/winalco-sms-api-reference.md)
  ou <https://sms-relay.winalco.dz/api.html>
- Compte, quotas, facturation : <https://sms-relay.winalco.dz/app/>
- Support : contact@winalco.dz

Code sous [licence MIT](LICENSE). Le service d'envoi (relay, SIM, quotas) est
fourni par la plateforme Winalco et nécessite un compte.
