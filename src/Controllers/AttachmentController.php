<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Auth\Auth;
use Wiki\Models\Settings;
use Wiki\Services\AttachmentService;
use Wiki\Services\I18nService;

class AttachmentController
{
    /**
     * Upload endpoint. Accepts:
     *   - multipart/form-data with 'file'
     *   - JSON with base64 'data' and 'filename' (for editor paste)
     */
    public function upload(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Settings::getBool('feature.attachments')) {
            http_response_code(403);
            echo json_encode(['error' => I18nService::t('error.forbidden')]);
            return;
        }
        if (!Auth::isEditor()) {
            http_response_code(403);
            echo json_encode(['error' => I18nService::t('error.forbidden')]);
            return;
        }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            http_response_code(400);
            echo json_encode(['error' => 'CSRF check failed']);
            return;
        }

        $file = null;

        if (!empty($_FILES['file'])) {
            $file = $_FILES['file'];
        } elseif (!empty($_POST['data']) && !empty($_POST['filename'])) {
            // Decode base64 payload from editor paste
            $raw = (string)$_POST['data'];
            if (strpos($raw, 'base64,') !== false) {
                $raw = substr($raw, strpos($raw, 'base64,') + 7);
            }
            $bin = base64_decode($raw, true);
            if ($bin === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid base64 payload']);
                return;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'wiki_up_');
            file_put_contents($tmp, $bin);
            $file = [
                'name'     => (string)$_POST['filename'],
                'type'     => '',
                'tmp_name' => $tmp,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($bin),
            ];
        }

        if (!$file) {
            http_response_code(400);
            echo json_encode(['error' => 'No file provided']);
            return;
        }

        $errors = AttachmentService::validate($file);
        if ($errors) {
            http_response_code(400);
            echo json_encode(['error' => implode('; ', $errors)]);
            return;
        }

        try {
            $result = AttachmentService::store($file, (int)(Auth::user()['id'] ?? 0));
            echo json_encode([
                'url'      => $result['url'],
                'filename' => $result['filename'],
                'id'       => $result['id'],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(array $params): void
    {
        if (!Auth::isAdmin()) { http_response_code(403); exit('Forbidden'); }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        AttachmentService::delete($id);
        redirect('admin/attachments');
    }

    public function testS3(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::isAdmin()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); echo json_encode(['error' => 'CSRF check failed']); return; }

        echo json_encode(AttachmentService::testS3());
    }
}