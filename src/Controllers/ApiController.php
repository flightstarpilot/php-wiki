<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Models\Page;
use Wiki\Models\Settings;
use Wiki\Models\TranslationGroup;

/**
 * Read-only JSON API. Enabled by feature.api_read.
 *
 * GET /api/page/{slug}
 * GET /api/page/{slug}/{lang}
 * GET /api/pages
 */
class ApiController
{
    private function guard(): bool
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Settings::getBool('feature.api_read')) {
            http_response_code(404);
            echo json_encode(['error' => 'API disabled']);
            return false;
        }
        return true;
    }

    public function page(array $params): void
    {
        if (!$this->guard()) return;

        $slug = (string)$params['slug'];
        $lang = isset($params['lang']) ? (string)$params['lang'] : null;

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        if ($lang === null) {
            $lang = (string)Settings::get('site.default_lang', $group['default_lang']);
        }

        $page = Page::findByGroupLang((int)$group['id'], $lang);
        $fallback = false;
        if (!$page) {
            $page = Page::findByGroupLang((int)$group['id'], $group['default_lang']);
            $fallback = $page !== null;
        }

        if (!$page) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        echo json_encode([
            'slug'     => $group['slug'],
            'lang'     => $page['lang'],
            'fallback' => $fallback,
            'title'    => $page['title'],
            'content'  => $page['content'],
            'version'  => (int)$page['current_version'],
            'updated_at' => $page['updated_at'],
        ], JSON_UNESCAPED_UNICODE);
    }

    public function pages(): void
    {
        if (!$this->guard()) return;

        $groups = TranslationGroup::all();
        $out = [];
        foreach ($groups as $g) {
            $out[] = [
                'slug'         => $g['slug'],
                'default_lang' => $g['default_lang'],
                'parent_id'    => $g['parent_id'] !== null ? (int)$g['parent_id'] : null,
                'languages'    => array_map(fn($p) => [
                    'lang'  => $p['lang'],
                    'title' => $p['title'],
                ], Page::listByGroup((int)$g['id'])),
            ];
        }
        echo json_encode($groups ? $out : [], JSON_UNESCAPED_UNICODE);
    }
}
