# Laravel FCM HTTP v1 🚀✨🔥

A simple package that helps you send **Firebase notifications using HTTP v1** with your Laravel applications. 🎯📬💡

---

### Installation 📦⚡️💻

You can install the package via composer: 🎉📥🛠️

```bash
composer require warith313/laravel-fcm-httpv1 "^0.0.1"
```

---

### Laravel Setup 🛠️📣🚀

Register the service provider: 🏗️📢💡

```php
// config/app.php

'providers' => [
    // ...
    Warith\Fcm\FcmServiceProvider::class,
],
```

Optional: add the Facade for easy access: 🪄📘✨

```php
// config/app.php

'aliases' => [
    // ...
    'Fcm' => Warith\Fcm\FcmFacade::class,
],
```

Publish the config file: 📂📣💼

```bash
php artisan vendor:publish --provider="Warith\Fcm\FcmServiceProvider"
```

Published file content (`config/laravel-fcm.php`): 📝📌🔧

```php
return [

    /**
     * Path to your Firebase service account JSON file
     */
    'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH', storage_path('app/service-account.json')),

];
```

Set the path in your `.env`: 🌟📁📝

```
FCM_SERVICE_ACCOUNT_PATH=/storage/app/service-account.json
```

---

### Lumen Setup 🔧📡⚡️

Add the service provider in `bootstrap/app.php`: 🏗️💡🚀

```php
$app->register(Warith\Fcm\FcmServiceProvider::class);
```

Copy the config file to `config/laravel-fcm.php` and configure before registering the provider: 📝⚙️📂

```php
$app->configure('laravel-fcm');
$app->register(Warith\Fcm\FcmServiceProvider::class);
```

---

### Methods Reference 📜⚙️📝

* `->to($recipients)` – Send to a single token (string) or array of tokens
* `->toTopic($topic)` – Send to a topic
* `->data($array)` – Custom data payload
* `->notification($array)` – Notification payload
* `->priority('high'|'normal')` – Notification priority
* `->timeToLive(seconds)` – Time to live in seconds (0 to 2419200)
* `->enableResponseLog()` – Log raw Firebase response
* `->send()` – Send the message

---

### Usage Examples 📬🔥🎯

#### 1. Send only data payload: 📊🖋️⚡️

```php
$recipients = [
    'token1...',
    'token2...',
];

fcm()
    ->to($recipients)
    ->priority('high')
    ->timeToLive(0)
    ->data([
        'title' => 'Test FCM',
        'body' => 'This is a test FCM',
    ])
    ->send();
```

#### 2. Send to a topic: 📢📰🎯

```php
$topic = 'news';

fcm()
    ->toTopic($topic)
    ->priority('normal')
    ->timeToLive(0)
    ->notification([
        'title' => 'Topic FCM',
        'body' => 'This is a topic test',
    ])
    ->send();
```

#### 3. Send only notification payload: 🔔📝✨

```php
fcm()
    ->to($recipients)
    ->priority('high')
    ->timeToLive(0)
    ->notification([
        'title' => 'Test Notification',
        'body' => 'Notification only payload',
    ])
    ->send();
```

#### 4. Send both data & notification payloads with image 🖼️📱🌟

```php
fcm()
    ->to($recipients)
    ->priority('high')
    ->timeToLive(0)
    ->data([
        'title' => 'Test FCM',
        'body' => 'This is a data payload',
        'type' => 'all',
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        'image' => $image_link
    ])
    ->notification([
        'title' => 'Test FCM',
        'body' => 'This is a notification payload',
        'image' => $image_link
    ])
    ->send();
```

> **Note:** When sending images, the package automatically sets `mutable-content` for APNs, adds image to Android notification, and includes Webpush headers. 📷📲🎨

---

### Logging 📝🔍📂

To see the raw Firebase response: 🖥️📋✨

```php
fcm()
    ->to($recipients)
    ->enableResponseLog()
    ->send();
```

Check logs at `storage/logs/laravel.log`. 🗂️📜🔥

---

### Notes for HTTP v1 ⚡️🚀💡

* `token` must be a single string per message. For multiple tokens, pass an array to `->to($tokens)` and the package sends each individually.
* APNs overrides (`badge`, `mutable-content`) are automatically added if `image` is provided.
* Android overrides (`click_action`, `image`) are automatically handled.
* Webpush image headers are automatically added if `image` is present.
* `timeToLive` must be between `0` and `2419200` (28 days). 🎯📬📌

---

### License 🔒

This package is open-sourced software licensed under the [MIT license](LICENSE).
