<?php
declare(strict_types=1);

/**
 * Checks the documentation of one version branch:
 *
 *  - every sidebar link in documentation.md points to an existing page
 *  - every page is listed in the sidebar
 *  - every /docs/{{version}}/page and #anchor link points to an existing page and heading
 *  - code fences are closed
 *
 * Heading anchors follow the website's renderer (league/commonmark heading
 * permalinks, levels 2-4, duplicate slugs get -1, -2, ...).
 *
 * Usage: php .github/scripts/check-docs.php [directory]
 */

$root = rtrim($argv[1] ?? getcwd(), '/');
$skip = ['documentation.md', 'README.md', 'LICENSE.md', 'CONTRIBUTING.md'];

$pages = [];
foreach (glob($root . '/*.md') ?: [] as $file) {
    $name = basename($file);
    if (!in_array($name, $skip, true)) {
        $pages[basename($file, '.md')] = (string) file_get_contents($file);
    }
}

/** Markdown with fenced code blocks removed; also reports unclosed fences. */
function withoutCode(string $markdown, string $page, array &$errors): string
{
    $out = [];
    $fence = null;
    foreach (preg_split('/\R/', $markdown) as $number => $line) {
        $plain = preg_replace('/^(\s*>\s?)+/', '', $line);
        if (preg_match('/^\s*(`{3,}|~{3,})/', $plain, $m)) {
            if ($fence === null) {
                $fence = [$m[1][0], strlen($m[1]), $number + 1];
            } elseif ($m[1][0] === $fence[0] && strlen($m[1]) >= $fence[1]) {
                $fence = null;
            }
            $out[] = '';
            continue;
        }
        $out[] = $fence === null ? $line : '';
    }
    if ($fence !== null) {
        $errors[] = "$page.md:{$fence[2]}: code fence is never closed";
    }

    return implode("\n", $out);
}

function slug(string $heading): string
{
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $heading);   // [text](url) -> text
    $text = str_replace(['`', '*'], '', (string) $text);
    $text = preg_replace('/(^|\s)_+|_+(\s|$)/', '$1$2', $text);         // _emphasis_
    $text = mb_strtolower(trim((string) $text), 'UTF-8');
    $text = (string) preg_replace('/\s+/u', '-', $text);

    return (string) preg_replace('/[^\p{L}\p{Nd}\p{Nl}\p{M}-]+/u', '', $text);
}

$errors = [];
$anchors = [];
$bodies = [];
foreach ($pages as $page => $markdown) {
    $body = withoutCode($markdown, $page, $errors);
    $bodies[$page] = $body;
    $seen = [];
    preg_match_all('/^#{2,4}\s+(.+?)\s*#*\s*$/m', $body, $m);
    foreach ($m[1] as $heading) {
        $slug = slug($heading);
        $unique = $slug;
        for ($i = 1; isset($seen[$unique]); $i++) {
            $unique = $slug . '-' . $i;
        }
        $seen[$unique] = true;
    }
    $anchors[$page] = $seen;
}

// Sidebar
$sidebarFile = $root . '/documentation.md';
if (!is_file($sidebarFile)) {
    $errors[] = 'documentation.md is missing';
} else {
    preg_match_all('#\]\(/docs/\{\{version\}\}/([^)\#]+)\)#', (string) file_get_contents($sidebarFile), $m);
    $listed = array_flip($m[1]);
    foreach (array_keys($listed) as $page) {
        if (!isset($pages[$page])) {
            $errors[] = "documentation.md: sidebar links to missing page \"$page\"";
        }
    }
    foreach (array_keys($pages) as $page) {
        if (!isset($listed[$page])) {
            $errors[] = "$page.md: page is not listed in documentation.md";
        }
    }
}

// Links inside pages
foreach ($bodies as $page => $body) {
    foreach (preg_split('/\n/', $body) as $number => $line) {
        $line = preg_replace('/`[^`]*`/', '', $line);   // ignore inline code
        preg_match_all('#\]\((/docs/\{\{version\}\}/([^)\#\s]*)|)(\#[^)\s]+)?\)#', (string) $line, $links, PREG_SET_ORDER);
        foreach ($links as $link) {
            if ($link[1] === '' && !isset($link[3])) {
                continue;
            }
            $target = $link[1] === '' ? $page : $link[2];
            $where = "$page.md:" . ($number + 1);
            if (!isset($pages[$target])) {
                $errors[] = "$where: link to missing page \"$target\"";
                continue;
            }
            $anchor = isset($link[3]) ? substr($link[3], 1) : '';
            if ($anchor !== '' && !isset($anchors[$target][$anchor])) {
                $errors[] = "$where: link to missing heading \"$target#$anchor\"";
            }
        }
        if (preg_match('#\]\(/docs/(?!\{\{version\}\})#', (string) $line)) {
            $errors[] = "$page.md:" . ($number + 1) . ': /docs link without {{version}}';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n\n" . count($errors) . " problem(s) found.\n");
    exit(1);
}

echo count($pages) . " pages checked: sidebar, links and anchors are fine.\n";
