<?php

namespace Dominservice\MediaKit\Support\Url;

use InvalidArgumentException;

/**
 * Prosty podpisywacz URLi CloudFront (canned policy).
 * Wymaga:
 * - CLOUDFRONT_KEY_PAIR_ID
 * - CLOUDFRONT_PRIVATE_KEY_PATH (plik .pem)
 * - CLOUDFRONT_URL_EXPIRES (sekundy)
 */
class CloudFrontSigner
{
    public static function sign(string $url): string
    {
        $keyPairId = (string) config('media-kit.cdn.cloudfront.key_pair_id', '');
        $pkPath    = (string) config('media-kit.cdn.cloudfront.private_key_path', '');
        $expires   = time() + (int) config('media-kit.cdn.cloudfront.expires', 3600);

        if ($keyPairId === '' || $pkPath === '' || !is_file($pkPath)) {
            // Brak konfiguracji — zwróć URL niepodpisany
            return $url;
        }

        $policy = json_encode([
            'Statement' => [[
                'Resource'  => $url,
                'Condition' => ['DateLessThan' => ['AWS:EpochTime' => $expires]],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        $privateKey = @file_get_contents($pkPath);
        if ($privateKey === false) {
            throw new InvalidArgumentException('Cannot read CloudFront private key at: ' . $pkPath);
        }

        $pkey = openssl_pkey_get_private($privateKey);
        if (!$pkey) {
            throw new InvalidArgumentException('Invalid CloudFront private key');
        }

        $signature = '';
        openssl_sign($policy, $signature, $pkey, OPENSSL_ALGO_SHA1);
        openssl_free_key($pkey);

        $policyB64 = self::urlsafeBase64($policy);
        $sigB64    = self::urlsafeBase64($signature);

        $sep = (parse_url($url, PHP_URL_QUERY) ? '&' : '?');

        return $url
            . $sep . 'Policy=' . $policyB64
            . '&Signature=' . $sigB64
            . '&Key-Pair-Id=' . rawurlencode($keyPairId);
    }

    protected static function urlsafeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
