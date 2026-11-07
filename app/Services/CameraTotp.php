<?php

namespace App\Services;

/** RFC 6238, SHA-1, 30-second step; replay prevention is persisted by the controller. */
class CameraTotp
{
    public function generateSecret(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        foreach (str_split($bits, 5) as $part) {
            $secret .= $alphabet[bindec($part)];
        }

        return $secret;
    }

    public function code(string $secret, int $counter, int $digits = 6): string
    {
        if (! preg_match('/^[A-Z2-7]+$/D', $secret)) {
            throw new \InvalidArgumentException('Invalid authenticator secret');
        }
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }
        $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter & 0xFFFFFFFF), $key, true);
        $offset = ord($hash[19]) & 15;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, ?int $lastCounter): ?int
    {
        if (! preg_match('/^[0-9]{6}$/D', $code)) {
            return null;
        }
        $counter = intdiv(now()->timestamp, 30);
        for ($step = $counter - 1; $step <= $counter + 1; $step++) {
            if ($step > ($lastCounter ?? -1) && hash_equals($this->code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }
}
