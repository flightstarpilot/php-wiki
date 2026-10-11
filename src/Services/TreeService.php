<?php
declare(strict_types=1);

namespace Wiki\Services;

use Wiki\Models\TranslationGroup;
use Wiki\Models\Page;

class TreeService
{
    /** 构建父子树，附带默认语言 title */
    public static function build(): array
    {
        $groups = TranslationGroup::all();
        $titles = Page::titlesByGroup();

        $byId = [];
        foreach ($groups as $g) {
            $g['title'] = $titles[$g['id']][$g['default_lang']]
                       ?? $titles[$g['id']][array_key_first($titles[$g['id']] ?? [])]
                       ?? $g['slug'];
            $g['children'] = [];
            $byId[$g['id']] = $g;
        }

        $root = [];
        foreach ($byId as $id => &$g) {
            if ($g['parent_id'] && isset($byId[$g['parent_id']])) {
                $byId[$g['parent_id']]['children'][] = &$g;
            } else {
                $root[] = &$g;
            }
        }
        unset($g);
        return $root;
    }

    public static function render(array $nodes, string $currentSlug = '', int $depth = 0): string
    {
        if (!$nodes) return '';
        $cls = $depth === 0
            ? 'space-y-0.5'
            : 'ml-3 border-l border-slate-200 pl-2 space-y-0.5';

        $html = "<ul class=\"$cls\">";
        foreach ($nodes as $n) {
            $active = $n['slug'] === $currentSlug;
            $html .= '<li>';
            $html .= '<a href="' . e(url('page/' . $n['slug'])) . '" class="block px-2 py-1 rounded text-sm '
                   . ($active ? 'bg-blue-100 text-blue-700 font-medium' : 'hover:bg-slate-100 text-slate-700')
                   . '">' . e($n['title']) . '</a>';
            if (!empty($n['children'])) {
                $html .= self::render($n['children'], $currentSlug, $depth + 1);
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }
}