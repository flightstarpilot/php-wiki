<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Models\Settings;
use Wiki\Models\Tag;
use Wiki\Models\TranslationGroup;
use Wiki\Services\I18nService;
use Wiki\Services\SearchService;

class SearchController
{
    public function search(): void
    {
        if (!Settings::getBool('feature.search')) { $this->forbidden(); return; }

        $q    = trim((string)($_GET['q'] ?? ''));
        $lang = trim((string)($_GET['lang'] ?? ''));

        $results = $q !== '' ? SearchService::search($q, $lang, 50) : [];

        $pageTitle = I18nService::t('search.title');
        require WIKI_ROOT . '/templates/search.php';
    }

    public function tags(): void
    {
        $tags = Tag::listAll();
        $pageTitle = I18nService::t('tag.title');
        require WIKI_ROOT . '/templates/tags.php';
    }

    public function tag(array $params): void
    {
        $slug = $params['slug'];
        $tag  = Tag::findBySlug($slug);
        if (!$tag) { http_response_code(404); exit('Tag not found'); }

        $pages = Tag::pagesByTag((int)$tag['id']);
        $pageTitle = $tag['name'];
        require WIKI_ROOT . '/templates/tag.php';
    }

    public function sitemap(): void
    {
        if (!Settings::getBool('seo.sitemap_enabled')) { http_response_code(404); exit(); }

        header('Content-Type: application/xml; charset=utf-8');

        $base = rtrim((string)Settings::get('site.base_url', url('')), '/');
        $groups = TranslationGroup::all();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $now = gmdate('c');
        echo "  <url><loc>" . htmlspecialchars($base . '/', ENT_XML1) . "</loc>"
           . "<changefreq>daily</changefreq><priority>1.0</priority></url>\n";

        foreach ($groups as $g) {
            $loc = $base . '/page/' . $g['slug'];
            $lastmod = date('c', strtotime((string)$g['updated_at']));
            echo "  <url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>"
               . "<lastmod>" . htmlspecialchars($lastmod, ENT_XML1) . "</lastmod>"
               . "<changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
        }

        echo '</urlset>';
        exit;
    }

    public function rss(): void
    {
        if (!Settings::getBool('feature.rss')) { http_response_code(404); exit(); }

        header('Content-Type: application/rss+xml; charset=utf-8');

        $base = rtrim((string)Settings::get('site.base_url', url('')), '/');
        $siteName = (string)Settings::get('site.name');
        $desc     = (string)Settings::get('site.description');

        $recent = \Wiki\Models\Page::recentUpdates(30);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"><channel>' . "\n";
        echo '  <title>' . htmlspecialchars($siteName, ENT_XML1) . "</title>\n";
        echo '  <link>' . htmlspecialchars($base, ENT_XML1) . "</link>\n";
        echo '  <description>' . htmlspecialchars($desc, ENT_XML1) . "</description>\n";
        echo '  <language>' . htmlspecialchars(I18nService::current(), ENT_XML1) . "</language>\n";

        foreach ($recent as $p) {
            $link = $base . '/page/' . $p['group_slug']
                  . ($p['lang'] === Settings::get('site.default_lang') ? '' : '/' . $p['lang']);
            echo "  <item>\n";
            echo '    <title>' . htmlspecialchars((string)$p['title'], ENT_XML1) . "</title>\n";
            echo '    <link>' . htmlspecialchars($link, ENT_XML1) . "</link>\n";
            echo '    <guid>' . htmlspecialchars($link, ENT_XML1) . "</guid>\n";
            echo '    <pubDate>' . date(DATE_RSS, strtotime((string)$p['updated_at'])) . "</pubDate>\n";
            echo "  </item>\n";
        }

        echo '</channel></rss>';
        exit;
    }

    private function forbidden(): void
    {
        http_response_code(403);
        exit('Search is disabled');
    }
}