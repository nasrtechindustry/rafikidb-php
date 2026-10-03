# rafikidb/rafikidb

Typed PHP client for [RafikiDB](https://rafikidb.com) - data, auth, realtime,
storage, environment variables, secrets, webhooks, edge functions and
payments.

- PHP 8.1+, zero dependencies (curl + json)
- Laravel-native: auto-discovered service provider, facade, config and auth
  guard
- Same envelope and error model as the REST API

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
    baseUrl: 'https://api.rafikidb.com/api/v1', // optional
);

$rows = $db->from('profiles')
    ->select('id, nationality')
    ->eq('nationality', 'Tanzania')
    ->limit(25)
    ->execute();

$rows->success;   // true
$rows->data;      // array of rows
```

Failures throw `RafikiDBException` with `status`, `errorCode` and `errors`.

## Data

```php
// Filters: eq, neq, gt, gte, lt, lte, like, ilike, isNull, isNotNull, in
$db->from('profiles')->eq('nationality', 'Tanzania')->isNotNull('user_id');

// Ordering + pagination (keyset cursor for large tables)
$db->from('profiles')->order('created_at', ascending: false)->limit(50)->offset(100);
$db->from('logs')->select('id')->limit(100)->cursor($lastId);

// Joins (column-based, no SQL)
use RafikiDB\JoinSpec;
$rows = $db->from('messages')
    ->select('id as message_id, message, users.full_name as full_name')
    ->join(new JoinSpec(table: 'users', fromColumn: 'user_id', toColumn: 'id'))
    ->limit(20)
    ->execute();

// Row operations
$db->from('profiles')->get($id);
$db->from('profiles')->single();
$db->from('profiles')->head();                 // {count: n}
$db->from('profiles')->insert([...]);          // single or list
$db->from('profiles')->update(['nationality' => 'Kenya'])->eq('id', $id);
$db->from('profiles')->delete()->eq('id', $id);
```

## Auth

```php
$session = $db->auth->login('user@example.com', 'strong-pass1');
// session stored on the client; sent with every request (RLS sees the user)

$db->auth->signOut();

$db->auth->signup(email, password, fullName, phone);
$db->auth->otpRequest('+255712345678');
$db->auth->otpVerify('+255712345678', '123456', 'Asha Mwinyi');
$db->auth->resetPassword('user@example.com');
$db->auth->confirmResetPassword('user@example.com', '123456', 'brand-new-pass1');
```

## Realtime

```php
$sub = $db->realtime->subscribe(
    'messages',
    function (array $event): void {
        // $event['type'], $event['record'], ...
    },
    ['events' => ['INSERT', 'DELETE']],
);
$sub->run();    // blocks; call ->close() to stop (long-running worker)
```

## Modules

```php
$db->storage->createBucket(name: 'avatars', slug: 'avatars', isPublic: true);
$db->storage->signedUploadUrl(bucketId: $bucket['id'], objectName: 'user-1.png');

$db->env->set('STRIPE_KEY', 'sk_test_123', environment: 'production');
$db->env->bulkSet('development', ['DEBUG' => 'true']);

$db->secrets->set(key: 'API_SECRET', value: 's3cr3t');
$db->secrets->reveal($secretId);

$db->webhooks->create(name: 'order.created', url: 'https://myapp.com/hooks/orders', events: ['order.created']);
$db->webhooks->listDeliveries($webhookId);

$db->functions->create(name: 'hello', code: '...');
$db->functions->deploy($fnId);
$db->functions->invoke($fnId, body: '{"name":"Asha"}');

$db->payments->stkPush(phone: '+255712345678', amount: 5000, description: 'Order #123');
$db->payments->snippeStatus($reference);
$db->payments->listTransactions();
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
```

Use the facade:

```php
use RafikiDB\Facades\RafikiDB;

$rows = RafikiDB::from('messages')->select('id, message')->limit(10)->execute();
```

### Auth guard

When `RAFIKIDB_AUTH_GUARD=true` (default), a `rafikidb` user provider is
registered. After `RafikiDB::auth()->login(...)`, the project user is stored
in the SDK session and `auth()->user()` resolves it in the same request:

```php
// config/auth.php
'guards' => [
    'rafikidb' => ['driver' => 'session', 'provider' => 'rafikidb'],
    // ...existing guards
],
'providers' => [
    'rafikidb' => ['driver' => 'rafikidb'],
    // ...existing providers
],
```

```php
use RafikiDB\Facades\RafikiDB;

$result = RafikiDB::auth()->login($request->email, $request->password);
if ($result->success) {
    $request->session()->regenerate();
    // auth()->user() now returns the project user
    return redirect()->intended('/dashboard');
}

// Access the raw project user array anywhere:
$user = auth()->user(); // ->rafikidbUser() or ->full_name etc.
```

> Login itself always happens through `RafikiDB::auth()->login()`; the guard
> reads the session, it does not verify passwords itself.

## Development

```bash
composer install
composer test
```