# rafikidb/rafikidb

Typed PHP client for [RafikiDB](https://rafikidb.com), a backend-as-a-service for East African applications. Covers data, auth, realtime, storage, environment variables, secrets, webhooks, edge functions and payments.

- PHP 8.1+, zero runtime dependencies (curl + json only)
- Laravel-native: auto-discovered service provider, facade, config and auth guard
- Same envelope and error model as the REST API
- Keyset cursor pagination for large tables
- Column-based joins with no raw SQL

## Install

```bash
composer require rafikidb/rafikidb
```

## Quick start

```php
use RafikiDB\RafikiDB;

$db = RafikiDB::create(
    projectId: '5c73dab0-...',
    apiKey: 'raf_live_...',
    baseUrl: 'https://api.rafikidb.com/api/v1', // optional, defaults to the RafikiDB API
);

$rows = $db->from('profiles')
    ->select('id, nationality')
    ->eq('nationality', 'Tanzania')
    ->limit(25)
    ->execute();

$rows->success;   // true
$rows->data;      // array of rows
$rows->message;   // "ok" or human readable message
```

Every call returns an `Envelope` with `success`, `data`, `message`, `code` and `errors`. On failure a `RafikiDBException` is thrown with `status`, `errorCode` and `errors`, so failures are easy to catch:

```php
use RafikiDB\RafikiDBException;

try {
    $result = $db->from('profiles')->insert([
        ['full_name' => 'Asha Mwinyi', 'nationality' => 'Tanzania'],
    ]);
} catch (RafikiDBException $e) {
    $e->status;      // 400
    $e->errorCode;   // "invalid_input"
    $e->errors;      // ["email failed email", "password failed min"]
}
```

## Data

### Read rows

```php
// All rows
$db->from('profiles')->execute();

// Select specific columns (aliases supported)
$db->from('profiles')->select('id, full_name, created_at')->execute();

// Single row by primary key
$db->from('profiles')->get('9f8a7b6c-...');

// First matching row, null when none
$db->from('profiles')->eq('phone', '+255712345678')->single();

// Row count only
$db->from('profiles')->head(); // data: {count: 42}
```

### Filters

| Method | SQL equivalent |
| --- | --- |
| `eq($col, $v)` | `= $v` |
| `neq($col, $v)` | `<> $v` |
| `gt($col, $v)` | `> $v` |
| `gte($col, $v)` | `>= $v` |
| `lt($col, $v)` | `< $v` |
| `lte($col, $v)` | `<= $v` |
| `like($col, $pattern)` | `LIKE $pattern` |
| `ilike($col, $pattern)` | `ILIKE $pattern` (case-insensitive) |
| `isNull($col)` | `IS NULL` |
| `isNotNull($col)` | `IS NOT NULL` |
| `in($col, $values)` | `IN ($values)` |

```php
$db->from('profiles')
    ->eq('nationality', 'Tanzania')
    ->neq('status', 'blocked')
    ->gt('balance', 10000)
    ->like('full_name', 'Asha%')
    ->in('region', ['dar-es-salaam', 'mbeya'])
    ->execute();
```

### Ordering and pagination

```php
// Sort
$db->from('profiles')->order('created_at', ascending: false)->execute();

// Offset pagination
$db->from('profiles')->order('created_at')->limit(50)->offset(100)->execute();

// Keyset cursor pagination for large tables (faster, stable across writes)
$page1 = $db->from('logs')->select('id')->order('id')->limit(100)->execute();
$lastId = end($page1->data)['id'];
$page2 = $db->from('logs')->select('id')->order('id')->limit(100)->cursor($lastId)->execute();
```

### Joins

```php
use RafikiDB\JoinSpec;

$rows = $db->from('messages')
    ->select('id as message_id, message, users.full_name as sender')
    ->join(new JoinSpec(
        table: 'users',
        fromColumn: 'user_id',
        toColumn: 'id',
        type: 'LEFT JOIN',   // optional: LEFT JOIN (default), INNER JOIN, RIGHT JOIN
    ))
    ->limit(20)
    ->execute();

foreach ($rows->data as $row) {
    echo $row['sender'];
}
```

### Write rows

```php
// Insert one row
$db->from('profiles')->insert([
    'full_name' => 'Asha Mwinyi',
    'nationality' => 'Tanzania',
]);

// Insert many rows
$db->from('profiles')->insert([
    ['full_name' => 'Asha', 'nationality' => 'Tanzania'],
    ['full_name' => 'Juma', 'nationality' => 'Kenya'],
]);

// Update matching rows
$db->from('profiles')
    ->update(['nationality' => 'Kenya'])
    ->eq('id', '9f8a7b6c-...');

// Delete matching rows
$db->from('profiles')->delete()->eq('id', '9f8a7b6c-...');
```

### Raw request

For anything not covered by the builder, use the raw client:

```php
$db->request('/projects/' . $db->projectId() . '/data/profiles', 'GET', null, ['limit' => 5]);
// or the shortcuts
$db->get($path, $query);
$db->post($path, $body);
$db->patch($path, $body);
$db->put($path, $body);
$db->delete($path);
```

## Auth

The user session is stored on the client and sent with every request, so Row Level Security policies see the authenticated user.

```php
// Sign up with email and password
$db->auth->signup('asha@example.com', 'strong-pass1', 'Asha Mwinyi', '+255712345678');

// Sign in (alias: ->signin())
$session = $db->auth->login('asha@example.com', 'strong-pass1');
// $session->data contains the user + access/refresh tokens

// Restore a session from storage (see persistence below)
$db->auth->setSession(json_decode($storedJson, true));

// Read the signed-in user
$user = $db->auth->session(); // array or null

// Sign out (clears the in-memory session)
$db->auth->signOut();
```

### Phone and OTP

```php
// Request an OTP
$db->auth->otpRequest('+255712345678');

// Verify it (signs the user in)
$db->auth->otpVerify('+255712345678', '123456', 'Asha Mwinyi');
```

### Password reset

```php
// Step 1: request a reset code
$db->auth->resetPassword('asha@example.com');

// Step 2: confirm with the code
$db->auth->confirmResetPassword('asha@example.com', '123456', 'brand-new-pass1');
```

### Token refresh and logout

```php
$fresh = $db->auth->refresh($refreshToken);
$db->auth->logout($refreshToken);
```

### Persisting the session

The client keeps the session in memory only. To keep users signed in across requests, store it yourself:

```php
// After login
file_put_contents('/tmp/session.json', json_encode($db->auth->session()));

// On the next request
$db->auth->setSession(json_decode(file_get_contents('/tmp/session.json'), true));
```

## Realtime

Subscribe to database changes over a persistent connection. The callback receives each event and the subscription is closed with `->close()`.

```php
$sub = $db->realtime->subscribe(
    'messages',
    function (array $event): void {
        // $event['type']   => 'INSERT' | 'UPDATE' | 'DELETE'
        // $event['record'] => the changed row
        echo $event['type'] . ': ' . json_encode($event['record']);
    },
    ['events' => ['INSERT', 'DELETE']], // optional filter
);

$sub->run();  // blocks; run this in a worker process
```

## Storage

```php
// Buckets
$bucket = $db->storage->createBucket(
    name: 'Avatars',
    slug: 'avatars',
    isPublic: true,
    fileSizeLimit: 5 * 1024 * 1024,       // optional, in bytes
    allowedMimeTypes: ['image/png', 'image/jpeg'], // optional
);
$db->storage->listBuckets();
$db->storage->getBucket($bucket['id']);
$db->storage->deleteBucket($bucket['id']);

// Objects and folders
$db->storage->listObjects($bucket['id']);
$db->storage->createFolder($bucket['id'], '2026');
$db->storage->deleteObject($objectId);

// Signed upload (client uploads directly to storage)
$upload = $db->storage->signedUploadUrl($bucket['id'], 'user-1.png', 2 * 1024 * 1024);
// PUT the file body to $upload['url'] with the returned headers

// Download and public URLs
$download = $db->storage->signedDownloadUrl($objectId);
$url = $db->storage->publicUrl($bucket['id'], $objectId);
```

## Environment variables

```php
$db->env->list();

$db->env->set('STRIPE_KEY', 'sk_test_123', environment: 'production');

$db->env->update($varId, value: 'sk_live_999');

$db->env->bulkSet('development', [
    'DEBUG' => 'true',
    'BASE_URL' => 'http://localhost:3001',
]);

$db->env->remove($varId);
```

## Secrets

Secrets are write-only. Values are never returned after creation except through `reveal`, which requires the secret id.

```php
$db->secrets->set(key: 'API_SECRET', value: 's3cr3t', description: 'Third-party API');

$db->secrets->list();

$db->secrets->reveal($secretId);

$db->secrets->update($secretId, value: 'new-value', description: 'Rotated');

$db->secrets->remove($secretId);
```

## Webhooks

```php
// Create
$webhook = $db->webhooks->create(
    name: 'Order Created',
    url: 'https://myapp.com/hooks/orders',
    events: ['order.created'],
    secret: 'whsec_...', // optional, used to sign deliveries
);

// Manage
$db->webhooks->list();
$db->webhooks->update($webhook['id'], enabled: false);
$db->webhooks->send($webhook['id']);           // test ping
$db->webhooks->remove($webhook['id']);

// Deliveries
$db->webhooks->listDeliveries($webhook['id']);
$db->webhooks->retryDelivery($webhook['id'], $deliveryId);
```

## Edge functions

```php
// Create with inline code
$fn = $db->functions->create(
    name: 'hello',
    runtime: 'javascript',
    code: 'export default async function (ctx) { return { ok: true }; }',
);

// Lifecycle
$db->functions->list();
$db->functions->get($fn['id']);
$db->functions->update($fn['id'], name: 'hello-v2');
$db->functions->deploy($fn['id']);
$db->functions->invoke($fn['id'], method: 'POST', body: '{"name":"Asha"}');
$db->functions->remove($fn['id']);
```

## Payments

Snippe (Tanzania mobile money) and M-Pesa are supported as providers.

```php
// Settings
$db->payments->getSettings();
$db->payments->updateSettings(snippeApiKey: 'sk_live_...');

// Mobile money STK push
$result = $db->payments->stkPush(
    phone: '+255712345678',
    amount: 5000,
    description: 'Order #123',
);

// Snippe checkout
$db->payments->snippeInitiate(
    amount: 15000,
    phone: '+255712345678',
    firstName: 'Asha',
    lastName: 'Mwinyi',
    email: 'asha@example.com',
    orderId: 'order_123',
);

// Snippe hosted session (redirect based)
$db->payments->snippeSession(
    amount: 15000,
    redirectUrl: 'https://myapp.com/payments/callback',
    orderId: 'order_123',
);

// Track payments
$db->payments->snippeStatus($reference);
$db->payments->listTransactions();
$db->payments->getTransaction($transactionId);
```

## Laravel

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=rafikidb-config
```

Set `.env`:

```bash
RAFIKIDB_PROJECT_ID=5c73dab0-...
RAFIKIDB_API_KEY=raf_live_...
RAFIKIDB_URL=http://localhost:8080/api/v1
RAFIKIDB_AUTH_GUARD=true
```

Use the facade:

```php
use RafikiDB\Facades\RafikiDB;

$rows = RafikiDB::from('messages')
    ->select('id, message')
    ->limit(10)
    ->execute();
```

### Auth guard

When `RAFIKIDB_AUTH_GUARD=true` (default), a `rafikidb` user provider is registered. Register the guard in `config/auth.php`:

```php
'guards' => [
    'rafikidb' => ['driver' => 'session', 'provider' => 'rafikidb'],
    // ...existing guards
],
'providers' => [
    'rafikidb' => ['driver' => 'rafikidb'],
    // ...existing providers
],
```

Then sign users in through the SDK and let the guard resolve them:

```php
use RafikiDB\Facades\RafikiDB;

$result = RafikiDB::auth()->login($request->email, $request->password);
if ($result->success) {
    $request->session()->regenerate();
    return redirect()->intended('/dashboard');
}

// auth()->user() now returns the project user
$user = auth()->user();
$user->full_name;   // via magic accessors
$user->rafikidbUser(); // the raw user array
```

Login always happens through `RafikiDB::auth()->login()`. The guard reads the SDK session, it does not verify passwords itself.

## Development

```bash
composer install
composer test
```