<?php
declare(strict_types=1);

namespace Wiki\Database;

/**
 * HTTP-backed database driver.
 *
 * The wiki does not speak SQL to a remote SQL server directly. Instead, the
 * operator runs their own endpoint (any language) that accepts a small JSON
 * protocol and translates the SQL onto whatever storage they like.
 *
 * Request body (POST, JSON):
 *   {
 *     "action": "query" | "fetchOne" | "fetchValue" | "execute"
 *             | "lastInsertId" | "begin" | "commit" | "rollback"
 *             | "tableExists",
 *     "sql":    "SELECT ...",         // for sql-bearing actions
 *     "params": [ ... ],              // positional bind values
 *     "table":  "pages"               // for tableExists
 *   }
 *
 * Response body (JSON):
 *   { "ok": true,  "data": <any> }
 *   { "ok": false, "error": "..." }
 *
 * Auth modes: none | bearer | basic | header | query
 */
class HttpDatabase implements DatabaseInterface
{
    private array $cfg;
    private string $endpoint;
    private string $method;
    private string $authType;
    private string $authToken;
    private string $authHeader;
    private int $timeout;

    public function __construct(array $cfg)
    {
        $this->cfg        = $cfg;
        $this->endpoint   = (string)($cfg['endpoint'] ?? '');
        $this->method     = strtoupper((string)($cfg['method'] ?? 'POST'));
        $this->authType   = (string)($cfg['auth_type'] ?? 'none');
        $this->authToken  = (string)($cfg['auth_token'] ?? '');
        $this->authHeader = (string)($cfg['auth_header'] ?? 'X-API-Key');
        $this->timeout    = (int)($cfg['timeout'] ?? 15);

        if ($this->endpoint === '') {
            throw new \RuntimeException('HTTP database endpoint is empty');
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $data = $this->call(['action' => 'query', 'sql' => $sql, 'params' => $params]);
        return is_array($data) ? $data : [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $data = $this->call(['action' => 'fetchOne', 'sql' => $sql, 'params' => $params]);
        return is_array($data) ? $data : null;
    }

    public function fetchValue(string $sql, array $params = [], $default = null)
    {
        $data = $this->call(['action' => 'fetchValue', 'sql' => $sql, 'params' => $params]);
        return $data === null ? $default : $data;
    }

    public function execute(string $sql, array $params = []): int
    {
        $data = $this->call(['action' => 'execute', 'sql' => $sql, 'params' => $params]);
        return (int)($data ?? 0);
    }

    public function lastInsertId(): int
    {
        return (int)($this->call(['action' => 'lastInsertId']) ?? 0);
    }

    public function beginTransaction(): bool
    {
        return (bool)($this->call(['action' => 'begin']) ?? false);
    }

    public function commit(): bool
    {
        return (bool)($this->call(['action' => 'commit']) ?? false);
    }

    public function rollBack(): bool
    {
        return (bool)($this->call(['action' => 'rollback']) ?? false);
    }

    public function driver(): string
    {
        return 'http';
    }

    public function tableExists(string $table): bool
    {
        return (bool)($this->call(['action' => 'tableExists', 'table' => $table]) ?? false);
    }

    public function pdo(): \PDO
    {
        throw new \RuntimeException('HTTP driver does not expose a PDO handle');
    }

    private function call(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode HTTP database request');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Wiki-Action: ' . ($payload['action'] ?? ''),
        ];

        switch ($this->authType) {
            case 'bearer':
                $headers[] = 'Authorization: Bearer ' . $this->authToken;
                break;
            case 'basic':
                $headers[] = 'Authorization: Basic ' . base64_encode($this->authToken);
                break;
            case 'header':
                $headers[] = $this->authHeader . ': ' . $this->authToken;
                break;
            case 'query':
            case 'none':
            default:
                break;
        }

        $url = $this->endpoint;
        if ($this->authType === 'query' && $this->authToken !== '') {
            $sep = strpos($url, '?') === false ? '?' : '&';
            $url .= $sep . 'token=' . urlencode($this->authToken);
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $this->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($this->method !== 'GET') {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new \RuntimeException('HTTP database request failed: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("HTTP database returned status $code: " . substr((string)$resp, 0, 300));
        }

        $json = json_decode((string)$resp, true);
        if (!is_array($json)) {
            throw new \RuntimeException('HTTP database response is not valid JSON');
        }
        if (empty($json['ok'])) {
            throw new \RuntimeException('HTTP database error: ' . (string)($json['error'] ?? 'unknown'));
        }

        return $json['data'] ?? null;
    }

    public static function testConnection(array $cfg): array
    {
        try {
            $driver = new self($cfg);
            $driver->call(['action' => 'tableExists', 'table' => 'pages']);
            return ['ok' => true, 'message' => 'Connection OK'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
