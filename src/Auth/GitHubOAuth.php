<?php
declare(strict_types=1);

namespace Wiki\Auth;

use Wiki\Models\Settings;

class GitHubOAuth
{
    public static function enabled(): bool
    {
        return Settings::getBool('auth.github_oauth')
            && (string)Settings::get('auth.github_client_id', '') !== ''
            && (string)Settings::get('auth.github_client_secret', '') !== '';
    }

    public static function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'    => (string)Settings::get('auth.github_client_id', ''),
            'redirect_uri' => self::redirectUri(),
            'scope'        => 'read:user user:email',
            'state'        => $state,
            'allow_signup' => 'true',
        ]);
        return 'https://github.com/login/oauth/authorize?' . $params;
    }

    public static function redirectUri(): string
    {
        return url('auth/github/callback');
    }

    /** Exchange the "code" for an access token. */
    public static function exchangeCode(string $code): ?string
    {
        $resp = self::post('https://github.com/login/oauth/access_token', [
            'client_id'     => (string)Settings::get('auth.github_client_id', ''),
            'client_secret' => (string)Settings::get('auth.github_client_secret', ''),
            'code'          => $code,
            'redirect_uri'  => self::redirectUri(),
        ], ['Accept: application/json']);
        if (!$resp) return null;
        $json = json_decode($resp, true);
        return $json['access_token'] ?? null;
    }

    public static function fetchUser(string $token): ?array
    {
        $resp = self::get('https://api.github.com/user', $token);
        if (!$resp) return null;
        $user = json_decode($resp, true);
        if (!is_array($user) || empty($user['id'])) return null;

        if (empty($user['email'])) {
            $emailsResp = self::get('https://api.github.com/user/emails', $token);
            $emails = json_decode((string)$emailsResp, true);
            if (is_array($emails)) {
                foreach ($emails as $e) {
                    if (!empty($e['primary']) && !empty($e['email'])) {
                        $user['email'] = $e['email'];
                        break;
                    }
                }
            }
        }

        return $user;
    }

    private static function get(string $url, string $token): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'wiki-oauth',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/vnd.github+json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($resp !== false && $code >= 200 && $code < 300) ? (string)$resp : null;
    }

    private static function post(string $url, array $data, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_USERAGENT      => 'wiki-oauth',
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers),
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($resp !== false && $code >= 200 && $code < 300) ? (string)$resp : null;
    }
}