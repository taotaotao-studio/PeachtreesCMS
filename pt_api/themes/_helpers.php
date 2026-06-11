<?php

function themesBaseDir(): string {
    $dir = defined('THEME_DIR') ? THEME_DIR : (__DIR__ . '/../theme');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function isValidThemeSlug(string $slug): bool {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $slug) === 1;
}

function themePublicCssUrl(string $slug, string $entryCss): string {
    return '/theme/' . rawurlencode($slug) . '/' . ltrim($entryCss, '/');
}

function scanThemePackages(PDO $pdo): void {
    $baseDir = themesBaseDir();
    $themeDirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    if ($themeDirs === false) {
        $themeDirs = [];
    }

    foreach ($themeDirs as $themeDir) {
        $slug = basename($themeDir);
        if (!isValidThemeSlug($slug)) {
            continue;
        }

        $meta = [
            'description' => null,
            'version' => null,
            'author' => null,
            'entry_css' => 'style.css',
            'thumbnail' => 'thumbnail.svg',
        ];

        $metaFile = $themeDir . '/theme.json';
        if (is_file($metaFile)) {
            $decoded = json_decode(file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $meta['description'] = trim($decoded['description'] ?? '') ?: null;
                $meta['version'] = trim($decoded['version'] ?? '') ?: null;
                $meta['author'] = trim($decoded['author'] ?? '') ?: null;
                $meta['thumbnail'] = trim($decoded['thumbnail'] ?? 'thumbnail.svg') ?: 'thumbnail.svg';
                $entryCss = trim($decoded['entry_css'] ?? '');
                if ($entryCss !== '') {
                    $meta['entry_css'] = ltrim($entryCss, '/');
                }
            }
        }

        if (!is_file($themeDir . '/' . $meta['entry_css'])) {
            continue;
        }

        $upsert = $pdo->prepare("
            INSERT INTO pt_themes (slug, description, version, author, entry_css, thumbnail, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                version = VALUES(version),
                author = VALUES(author),
                entry_css = VALUES(entry_css),
                thumbnail = VALUES(thumbnail)
        ");
        $upsert->execute([
            $slug,
            $meta['description'],
            $meta['version'],
            $meta['author'],
            $meta['entry_css'],
            $meta['thumbnail']
        ]);
    }

    // Ensure exactly one active theme
    $activeStmt = $pdo->query("SELECT id, slug FROM pt_themes WHERE is_active = 1 ORDER BY id ASC");
    $activeRows = $activeStmt ? $activeStmt->fetchAll() : [];

    if (count($activeRows) === 0) {
        $fallbackStmt = $pdo->prepare("SELECT id FROM pt_themes WHERE slug = ? LIMIT 1");
        $fallbackStmt->execute(['default']);
        $fallback = $fallbackStmt->fetch();

        if (!$fallback) {
            $fallbackStmt = $pdo->query("SELECT id FROM pt_themes ORDER BY id ASC LIMIT 1");
            $fallback = $fallbackStmt ? $fallbackStmt->fetch() : null;
        }

        if ($fallback) {
            $setActive = $pdo->prepare("UPDATE pt_themes SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END");
            $setActive->execute([intval($fallback['id'])]);
        }
    } elseif (count($activeRows) > 1) {
        $keepId = intval($activeRows[0]['id']);
        $setOnlyOne = $pdo->prepare("UPDATE pt_themes SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END");
        $setOnlyOne->execute([$keepId]);
    }
}
