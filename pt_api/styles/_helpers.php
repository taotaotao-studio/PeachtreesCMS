<?php

function stylesBaseDir(): string {
    $dir = defined('STYLE_DIR') ? STYLE_DIR : (__DIR__ . '/../../pt_frontend/public/pattern');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function isValidStyleSlug(string $slug): bool {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $slug) === 1;
}

function stylePublicCssUrl(string $slug, string $entryCss = 'style.css'): string {
    return '/pattern/' . rawurlencode($slug) . '/' . ltrim($entryCss, '/');
}

function scanStylePackages(PDO $pdo): void {
    $baseDir = stylesBaseDir();
    $styleDirs = glob($baseDir . '/*', GLOB_ONLYDIR);
    if ($styleDirs === false) {
        $styleDirs = [];
    }

    foreach ($styleDirs as $styleDir) {
        $slug = basename($styleDir);
        if (!isValidStyleSlug($slug)) {
            continue;
        }

        $meta = [
            'description' => null,
            'version' => null,
            'author' => null,
            'entry_css' => 'style.css',
            'thumbnail' => null,
        ];

        $metaFile = $styleDir . '/description.json';
        if (is_file($metaFile)) {
            $decoded = json_decode(file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $meta['description'] = trim($decoded['description'] ?? '') ?: null;
                $meta['version'] = trim($decoded['version'] ?? '') ?: null;
                $meta['author'] = trim($decoded['author'] ?? '') ?: null;
                $entryCss = trim($decoded['entry_css'] ?? '');
                if ($entryCss !== '') {
                    $meta['entry_css'] = ltrim($entryCss, '/');
                }
            }
        }

        $files = scandir($styleDir);
        foreach ($files as $file) {
            if (preg_match('/^bg\.(svg|png|jpg|jpeg|gif|webp)$/i', $file)) {
                $meta['thumbnail'] = '/pattern/' . $slug . '/' . $file;
                break;
            }
        }

        if (!is_file($styleDir . '/' . $meta['entry_css'])) {
            continue;
        }

        $upsert = $pdo->prepare("
            INSERT INTO pt_page_style (name, description, version, author, entry_css, thumbnail)
            VALUES (?, ?, ?, ?, ?, ?)
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
}
