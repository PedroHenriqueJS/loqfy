<?php

namespace App\Ai;

use Illuminate\Http\Client\RequestException;
use Throwable;

class ProviderRetry
{
    public const ATTEMPTS = 4;

    private const RETRYABLE = [429, 500, 502, 503, 529];

    public static function shouldRetry(Throwable $e): bool
    {
        return $e instanceof RequestException && in_array($e->response->status(), self::RETRYABLE, true);
    }

    public static function delayMs(int $attempt, Throwable $e): int
    {
        $retryAfter = $e instanceof RequestException ? (float) $e->response->header('retry-after') : 0.0;
        $backoff = 2 ** $attempt;

        return (int) ((max($retryAfter, $backoff) + 1) * 1000);
    }
}
