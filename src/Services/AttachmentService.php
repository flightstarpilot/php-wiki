<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Database\DatabaseInterface;
use Wiki\Models\Settings;

/**
 * Attachment storage abstraction.
 *
 * Two drivers:
 *   - local : files stored under /uploads/YYYY/MM/, served directly.
 *   - s3    : S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, Aliyun OSS).
 *             Implemented with AWS Signature Version 4, no external SDK.
 */
class AttachmentService
{
    private static ?DatabaseInterface $db = null;
    public static function init(DatabaseInterface $db): void { self::$db = $db; }

    public static function driver(): string
    {
        return (string)Settings::get('attachment.driver', 'local');
    }

    public static function allowedTypes(): array
    {
        $raw = (string)Settings::get('attachment.allowed_types', '');
        $list = array_filter(array_map('trim', explode(',', strtolower($raw))));
        return $list ?: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip', 'md', 'txt'];
    }

    public static function maxSize(): int
    {
        return (int)Settings::get('attachment.max_size', 10485760);
    }

    public static function validate(array $file): array
    {
        $errors = [];

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['Invalid upload'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['No file uploaded'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['File exceeds server upload limit'];
            default:
                return ['Upload failed (code ' . $file['error'] . ')'];
        }

        if ($file['size'] > self::maxSize()) {
            $errors[] = 'File too large (limit ' . self::maxSize() . ' bytes)';
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::allowedTypes(), true)) {
            $errors[] = 'File type not allowed';
        }

        return $errors;
    }

    /**
     * Store an uploaded file.
     *
     * @return array{id:int,url:string,filename:string,path:string}
     */
    public static function store(array $file, int $userId = 0): array
    {
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $mime = self::detectMime((string)$file['tmp_name'], $ext);
        $size = (int)$file['size'];

        if (self::driver() === 's3') {
            $key = self::s3Key($name);
            self::s3Put($key, (string)$file['tmp_name'], $mime);
            $url  = self::s3PublicUrl($key);
            $path = $key;
            $storage = 's3';
        } else {
            $rel  = self::localKey($name);
            $dest = WIKI_ROOT . '/uploads/' . $rel;
            $dir  = dirname($dest);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }
            @chmod($dest, 0644);
            $path = $rel;
            $url  = url('uploads/' . $rel);
            $storage = 'local';
        }

        $now = gmdate('Y-m-d H:i:s');
        self::$db->execute(
            'INSERT INTO attachments (filename, original_name, mime_type, size, storage, path, url, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$name, (string)$file['name'], $mime, $size, $storage, $path, $url, $userId ?: null, $now]
        );

        return [
            'id'       => self::$db->lastInsertId(),
            'url'      => $url,
            'filename' => (string)$file['name'],
            'path'     => $path,
        ];
    }

    public static function find(int $id): ?array
    {
        return self::$db->fetchOne('SELECT * FROM attachments WHERE id = ?', [$id]);
    }

    public static function listAll(int $limit = 200, int $offset = 0): array
    {
        return self::$db->query(
            'SELECT a.*, u.username AS uploader
             FROM attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function delete(int $id): bool
    {
        $a = self::find($id);
        if (!$a) return false;

        if ($a['storage'] === 's3') {
            try { self::s3Delete((string)$a['path']); } catch (\Throwable $e) { /* keep going */ }
        } else {
            $file = WIKI_ROOT . '/uploads/' . $a['path'];
            if (is_file($file)) @unlink($file);
        }

        self::$db->execute('DELETE FROM attachments WHERE id = ?', [$id]);
        return true;
    }

    private static function detectMime(string $path, string $ext): string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if (is_string($m) && $m !== '') return $m;
        }
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'zip' => 'application/zip',
            'md' => 'text/markdown', 'txt' => 'text/plain',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private static function localKey(string $name): string
    {
        return gmdate('Y/m/') . $name;
    }

    private static function s3Key(string $name): string
    {
        return 'uploads/' . gmdate('Y/m/') . $name;
    }

    // ------------------------------------------------------------------
    // S3 (AWS Signature Version 4)
    // ------------------------------------------------------------------

    private static function s3Config(): array
    {
        return [
            'endpoint'   => rtrim((string)Settings::get('attachment.s3_endpoint', ''), '/'),
            'bucket'     => (string)Settings::get('attachment.s3_bucket', ''),
            'region'     => (string)Settings::get('attachment.s3_region', 'auto'),
            'access'     => (string)Settings::get('attachment.s3_access_key', ''),
            'secret'     => (string)Settings::get('attachment.s3_secret_key', ''),
            'public_url' => rtrim((string)Settings::get('attachment.s3_public_url', ''), '/'),
        ];
    }

    private static function s3PublicUrl(string $key): string
    {
        $cfg = self::s3Config();
        $base = $cfg['public_url'] !== ''
            ? $cfg['public_url']
            : $cfg['endpoint'] . '/' . $cfg['bucket'];
        return $base . '/' . ltrim($key, '/');
    }

    private static function s3Put(string $key, string $filePath, string $mime): void
    {
        $body = file_get_contents($filePath);
        if ($body === false) throw new \RuntimeException('Cannot read file body');
        self::s3Request('PUT', $key, $body, $mime);
    }

    private static function s3Delete(string $key): void
    {
        self::s3Request('DELETE', $key, '', '');
    }

    private static function s3Request(string $method, string $key, string $body, string $contentType): void
    {
        $cfg = self::s3Config();
        if ($cfg['endpoint'] === '' || $cfg['bucket'] === '' || $cfg['access'] === '' || $cfg['secret'] === '') {
            throw new \RuntimeException('S3 is not fully configured');
        }

        // Virtual-hosted vs path style: use path style universally for compatibility
        $host = parse_url($cfg['endpoint'], PHP_URL_HOST) ?? '';
        $scheme = parse_url($cfg['endpoint'], PHP_URL_SCHEME) ?: 'https';

        $uri = '/' . rawurlencode($cfg['bucket']) . '/' . str_replace('%2F', '/', rawurlencode($key));

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $canonicalHeaders = "host:$host\n"
                          . "x-amz-content-sha256:$payloadHash\n"
                          . "x-amz-date:$amzDate\n";
        if ($contentType !== '') {
            $canonicalHeaders = "content-type:$contentType\n" . $canonicalHeaders;
        }

        $signedHeaders = $contentType !== ''
            ? 'content-type;host;x-amz-content-sha256;x-amz-date'
            : 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = $method . "\n"
                          . $uri . "\n"
                          . '' . "\n" // canonical query string
                          . $canonicalHeaders . "\n"
                          . $signedHeaders . "\n"
                          . $payloadHash;

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$dateStamp/{$cfg['region']}/s3/aws4_request";
        $stringToSign = $algorithm . "\n"
                      . $amzDate . "\n"
                      . $credentialScope . "\n"
                      . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $cfg['secret'], true);
        $kRegion  = hash_hmac('sha256', $cfg['region'], $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = $algorithm . ' '
                    . 'Credential=' . $cfg['access'] . '/' . $credentialScope . ', '
                    . 'SignedHeaders=' . $signedHeaders . ', '
                    . 'Signature=' . $signature;

        $headers = [
            'Authorization: ' . $authHeader,
            'x-amz-date: ' . $amzDate,
            'x-amz-content-sha256: ' . $payloadHash,
        ];
        if ($contentType !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $url = $scheme . '://' . $host . $uri;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($method !== 'DELETE' && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new \RuntimeException('S3 request failed: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("S3 request returned HTTP $code: " . substr((string)$resp, 0, 300));
        }
    }

    /** Test S3 credentials by issuing a HEAD on the bucket. */
    public static function testS3(): array
    {
        try {
            $cfg = self::s3Config();
            if ($cfg['endpoint'] === '' || $cfg['bucket'] === '') {
                return ['ok' => false, 'message' => 'Endpoint or bucket is empty'];
            }
            self::s3Request('PUT', '.wiki-test-' . bin2hex(random_bytes(4)) . '.txt', 'ok', 'text/plain');
            return ['ok' => true, 'message' => 'Connection OK (test object written)'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}