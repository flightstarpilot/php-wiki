<?php
declare(strict_types=1);

namespace Wiki\Services;

/**
 * Line-level diff using an LCS dynamic-programming table.
 * For very large inputs (n * m > 4,000,000) falls back to a
 * position-based coarse diff to avoid memory exhaustion.
 */
class DiffService
{
    private const MAX_DP_CELLS = 4000000;

    /**
     * @return array<int, array{type:string,ln:?int,rn:?int,text:string}>
     *         type is one of: equal, add, del
     */
    public static function diff(string $old, string $new): array
    {
        return self::lcsDiff(explode("\n", $old), explode("\n", $new));
    }

    private static function lcsDiff(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n * $m > self::MAX_DP_CELLS) {
            return self::coarseDiff($a, $b);
        }

        $dp = [];
        for ($i = 0; $i <= $n; $i++) {
            $dp[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 1; $i <= $n; $i++) {
            $ai = $a[$i - 1];
            for ($j = 1; $j <= $m; $j++) {
                if ($ai === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = $dp[$i - 1][$j] >= $dp[$i][$j - 1]
                        ? $dp[$i - 1][$j]
                        : $dp[$i][$j - 1];
                }
            }
        }

        $out = [];
        $i = $n;
        $j = $m;
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $out[] = ['type' => 'equal', 'ln' => $i, 'rn' => $j, 'text' => $a[$i - 1]];
                $i--; $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                $out[] = ['type' => 'add', 'ln' => null, 'rn' => $j, 'text' => $b[$j - 1]];
                $j--;
            } else {
                $out[] = ['type' => 'del', 'ln' => $i, 'rn' => null, 'text' => $a[$i - 1]];
                $i--;
            }
        }

        return array_reverse($out);
    }

    private static function coarseDiff(array $a, array $b): array
    {
        $out = [];
        $max = max(count($a), count($b));
        for ($i = 0; $i < $max; $i++) {
            $la = $a[$i] ?? null;
            $lb = $b[$i] ?? null;

            if ($la === $lb) {
                $out[] = ['type' => 'equal', 'ln' => $i + 1, 'rn' => $i + 1, 'text' => (string)$la];
                continue;
            }
            if ($la !== null) $out[] = ['type' => 'del', 'ln' => $i + 1, 'rn' => null, 'text' => $la];
            if ($lb !== null) $out[] = ['type' => 'add', 'ln' => null, 'rn' => $i + 1, 'text' => $lb];
        }
        return $out;
    }

    public static function stats(array $diff): array
    {
        $adds = 0;
        $dels = 0;
        foreach ($diff as $d) {
            if ($d['type'] === 'add') $adds++;
            elseif ($d['type'] === 'del') $dels++;
        }
        return ['adds' => $adds, 'dels' => $dels];
    }

    public static function renderSideBySide(array $diff): string
    {
        $html = '<div class="overflow-x-auto"><table class="w-full text-sm font-mono border-collapse"><tbody>';
        foreach ($diff as $d) {
            $l = $d['ln'] !== null ? (string)$d['ln'] : '';
            $r = $d['rn'] !== null ? (string)$d['rn'] : '';
            $text = e($d['text']);

            if ($d['type'] === 'equal') {
                $lc = $rc = 'bg-white';
                $lt = $rt = $text;
            } elseif ($d['type'] === 'del') {
                $lc = 'bg-red-50 text-red-800';
                $rc = 'bg-slate-50 text-slate-300';
                $lt = $text; $rt = '';
            } else {
                $lc = 'bg-slate-50 text-slate-300';
                $rc = 'bg-green-50 text-green-800';
                $lt = ''; $rt = $text;
            }

            $html .= '<tr class="border-t border-slate-100">';
            $html .= '<td class="w-12 px-2 py-0.5 text-right text-slate-400 select-none ' . $lc . '">' . $l . '</td>';
            $html .= '<td class="px-3 py-0.5 ' . $lc . ' whitespace-pre-wrap break-all">' . $lt . '</td>';
            $html .= '<td class="w-12 px-2 py-0.5 text-right text-slate-400 select-none ' . $rc . '">' . $r . '</td>';
            $html .= '<td class="px-3 py-0.5 ' . $rc . ' whitespace-pre-wrap break-all">' . $rt . '</td>';
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    public static function renderUnified(array $diff): string
    {
        $html = '<div class="overflow-x-auto"><table class="w-full text-sm font-mono border-collapse"><tbody>';
        foreach ($diff as $d) {
            $prefix = ' ';
            $cls = 'bg-white';
            $ln = '';

            if ($d['type'] === 'del') {
                $prefix = '-';
                $cls = 'bg-red-50 text-red-800';
                $ln = $d['ln'] !== null ? (string)$d['ln'] : '';
            } elseif ($d['type'] === 'add') {
                $prefix = '+';
                $cls = 'bg-green-50 text-green-800';
                $ln = $d['rn'] !== null ? (string)$d['rn'] : '';
            } else {
                $ln = $d['ln'] !== null ? (string)$d['ln'] : '';
            }

            $html .= '<tr class="border-t border-slate-100">';
            $html .= '<td class="w-12 px-2 py-0.5 text-right text-slate-400 select-none ' . $cls . '">' . $ln . '</td>';
            $html .= '<td class="w-6 px-1 py-0.5 select-none ' . $cls . '">' . $prefix . '</td>';
            $html .= '<td class="px-3 py-0.5 ' . $cls . ' whitespace-pre-wrap break-all">' . e($d['text']) . '</td>';
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }
}