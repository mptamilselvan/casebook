<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignal
{
    public function enabled(): bool
    {
        return filled(config('services.onesignal.app_id'))
            && filled(config('services.onesignal.rest_api_key'));
    }

    public static function externalId(int $userId): string
    {
        return 'casebook-'.$userId;
    }

    public function notifyUser(int $userId, string $title, string $message, array $data = []): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Key '.config('services.onesignal.rest_api_key'),
            ])
            ->post('https://api.onesignal.com/notifications', [
                'app_id' => config('services.onesignal.app_id'),
                'target_channel' => 'push',
                'include_aliases' => [
                    'external_id' => [self::externalId($userId)],
                ],
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data' => $data,
            ]);

        if ($response->serverError()) {
            $response->throw();
        }

        if ($response->failed()) {
            Log::warning('OneSignal push was not delivered.', [
                'user_id' => $userId,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
