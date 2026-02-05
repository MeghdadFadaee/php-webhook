<?php

enum Messenger
{
    case TELEGRAM;
    case BALE;

    public static function tryFromIp(string $ip): ?self
    {
        return match (true) {
            isIpInRanges($ip, [
                '149.154.160.0/20',
                '91.108.4.0/22',
            ]) => self::TELEGRAM,
            isIpInRanges($ip, [
                '2.189.68.0/24',
            ]) => self::BALE,
            default => null,
        };
    }

    public function getApiBaseurl(): string
    {
        return match ($this) {
            self::TELEGRAM => 'https://api.telegram.org',
            self::BALE => 'https://tapi.bale.ai',
        };
    }
}
