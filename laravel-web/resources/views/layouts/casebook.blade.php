<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Case Book' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script>
        document.documentElement.classList.remove('dark');
        try { localStorage.setItem('casebook-theme', 'light'); } catch (e) {}
    </script>
    @if (config('services.onesignal.app_id') && auth()->check())
        @php
            $pushWorker = parse_url(asset('OneSignalSDKWorker.js'), PHP_URL_PATH) ?: '/OneSignalSDKWorker.js';
            $pushScope = rtrim(str_replace('\\', '/', dirname($pushWorker)), '/').'/';
        @endphp
        <script>
            window.casebookPush = {
                appId: @json(config('services.onesignal.app_id')),
                userId: @json((string) auth()->id()),
                workerPath: @json($pushWorker),
                workerScope: @json($pushScope),
            };
            window.OneSignalDeferred = window.OneSignalDeferred || [];
            window.OneSignalDeferred.push(async function (OneSignal) {
                await OneSignal.init({
                    appId: window.casebookPush.appId,
                    serviceWorkerPath: window.casebookPush.workerPath,
                    serviceWorkerParam: { scope: window.casebookPush.workerScope },
                    allowLocalhostAsSecureOrigin: true,
                });
                await OneSignal.login('casebook-' + String(window.casebookPush.userId));
                if (!OneSignal.User.PushSubscription.optedIn) {
                    try {
                        await OneSignal.Notifications.requestPermission();
                    } catch (error) {
                        if (!window.Notification || Notification.permission === 'default') {
                            try { await OneSignal.Slidedown.promptPush(); } catch (e) {}
                        }
                    }
                }
            });
            window.casebookEnablePush = function () {
                window.OneSignalDeferred.push(async function (OneSignal) {
                    await OneSignal.Notifications.requestPermission();
                });
            };
        </script>
        <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased">
    @yield('content')
</body>
</html>
