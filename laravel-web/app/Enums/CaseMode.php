<?php

namespace App\Enums;

enum CaseMode: string
{
    case Personal = 'personal';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Office => 'Office',
        };
    }

    public static function current(): ?self
    {
        $request = request();
        $header = self::tryFrom(strtolower(trim((string) $request->header('X-Case-Mode'))));
        if ($header !== null) {
            return $header;
        }

        if (! $request->hasSession()) {
            return null;
        }

        return self::tryFrom((string) $request->session()->get('case_mode'));
    }
}
