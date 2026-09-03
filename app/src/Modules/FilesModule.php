<?php

declare(strict_types=1);

namespace Bbs\Modules;

use Bbs\Admin\AuditLog;
use Bbs\Bbs\Engine;
use Bbs\Bbs\Frame;
use Bbs\Bbs\Module;
use Bbs\Core\Db;
use Bbs\Core\Storage;

/**
 * File libraries: areas -> file lists -> details -> download, plus a caller
 * upload flow (held for SysOp approval) and the reference "library" of links.
 */
final class FilesModule extends Module
{
    private const MAX_UPLOAD = 16 * 1024 * 1024;

    public static function slugs(): array
    {
        return ['file.areas', 'file.list', 'file.find', 'file.upload', 'file.library'];
    }

    public function run(Engine $e, string $slug, array $in, array &$st): Frame
    {
        $key = strtoupper((string) ($in['key'] ?? ''));
        $cmd = (string) ($in['cmd'] ?? '');

        return match ($slug) {
            'file.areas'   => $this->areas($e, $key, 'files'),
            'file.library' => $this->areas($e, $key, 'library'),
            'file.list'    => $this->list($e, $key, $st),
            'file.find'    => $this->find($e, $in, $cmd, $key, $st),
            'file.upload'  => $this->upload($e, $in, $cmd, $key, $st),
            default        => $e->exitModule(),
        };
    }

    private function curArea(Engine $e): ?array
    {
        $id = (int) $e->session->get('file.area', 0);
        return $id ? Db::one('SELECT * FROM file_areas WHERE id = ?', [$id]) : null;
    }

    private function areas(Engine $e, string $key, string $kind): Frame
    {
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $rank = $e->rank();
        $areas = Db::all(
            'SELECT *, (SELECT COUNT(*) FROM files f WHERE f.area_id=file_areas.id AND f.deleted_at IS NULL AND f.is_approved=1) AS n
             FROM file_areas WHERE kind = ? AND min_read_rank <= ? ORDER BY sort, id',
            [$kind, $rank]
        );
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($areas[$idx])) {
                $e->session->put('file.area', (int) $areas[$idx]['id']);
                return $e->goModule('file.list');
            }
        }
        $title = $kind === 'library' ? 'Reference Library' : 'File Areas';
        $f = Frame::make('screen')->title($title)->mode('menu')->header($title)->blank();
        $choices = [];
        foreach ($areas as $i => $a) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => sprintf('%-24s  %s files', mb_substr($a['name'], 0, 24), (string) $a['n']),
                'desc'  => (string) $a['description'],
            ];
        }
        $this->picker($f, $choices);
        if (!$areas) {
            $f->pipe('|08   Nothing here yet.');
        }
        return $f->footer('↑↓ move  ·  ENTER open  ·  Q back');
    }

    private function list(Engine $e, string $key, array &$st): Frame
    {
        $area = $this->curArea($e);
        if (!$area) {
            return $this->areas($e, $key, 'files');
        }
        $files = Db::all(
            'SELECT * FROM files WHERE area_id = ? AND deleted_at IS NULL AND is_approved = 1
             ORDER BY created_at DESC LIMIT 200',
            [$area['id']]
        );
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        if ($key === 'U' && $e->can('file.upload')) {
            return $e->goModule('file.upload');
        }
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($files[$idx])) {
                $st['detail'] = (int) $files[$idx]['id'];
                return $this->detail($e, $files[$idx]);
            }
        }
        if (isset($st['detail']) && $key === 'D') {
            $fRow = Db::one('SELECT * FROM files WHERE id = ?', [(int) $st['detail']]);
            if ($fRow) {
                return Frame::make('redirect')->mode('redirect')
                    ->meta(['url' => '/api/file/' . $fRow['id'], 'download' => $fRow['filename']])
                    ->title('Downloading');
            }
        }

        $f = Frame::make('screen')->title($area['name'])->mode('menu')
            ->header($area['name'], count($files) . ' files')->blank();
        $choices = [];
        foreach ($files as $i => $fl) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => mb_substr($fl['filename'], 0, 40),
                'desc'  => sprintf(
                    '%s · %d downloads · %s',
                    $this->hsize((int) $fl['size_bytes']),
                    (int) $fl['downloads'],
                    date('Y-m-d', strtotime($fl['created_at']))
                ),
            ];
        }
        $this->picker($f, $choices);
        if (!$files) {
            $f->pipe('|08   Empty area.');
        }
        $hint = $e->can('file.upload')
            ? '↑↓ move  ·  ENTER details  ·  U upload · Q back'
            : '↑↓ move  ·  ENTER details  ·  Q back';
        return $f->footer($hint);
    }

    private function detail(Engine $e, array $fl): Frame
    {
        $f = Frame::make('screen')->title($fl['filename'])->mode('menu')->header('File: ' . $fl['filename'])->blank();
        $f->pipe('|07   Title ......: |15' . ($fl['title'] ?: $fl['filename']));
        $f->pipe('|07   Size .......: |14' . $this->hsize((int) $fl['size_bytes']) . ' |08(' . number_format((int) $fl['size_bytes']) . ' bytes)');
        $f->pipe('|07   SHA-256 ....: |08' . ($fl['sha256'] ?: 'n/a'));
        $f->pipe('|07   Uploader ...: |11' . ($fl['uploader_handle'] ?: 'sysop'));
        $f->pipe('|07   Downloads ..: |14' . (int) $fl['downloads']);
        $f->pipe('|07   Added ......: |07' . date('Y-m-d H:i', strtotime($fl['created_at'])));
        $f->blank()->rule();
        foreach (explode("\n", wordwrap((string) $fl['description'], 110, "\n", true)) as $l) {
            $f->pipe('|07   ' . $l);
        }
        $f->blank();
        $this->picker($f, [[
            'key'   => 'D',
            'label' => 'Download this file',
            'desc'  => (string) ($fl['title'] ?: $fl['filename']),
        ]]);
        return $f->footer('↑↓ move  ·  ENTER / D download  ·  Q back');
    }

    private function find(Engine $e, array $in, string $cmd, string $key, array &$st): Frame
    {
        if (($st['step'] ?? 'form') === 'form' && $cmd !== 'submit') {
            if ($key === "\x1B" || $key === 'Q') {
                return $e->exitModule();
            }
            return Frame::make('form')->title('Find File')->header('Search the File Catalogue')->blank()
                ->form([['name' => 'q', 'label' => 'Filename / keyword', 'type' => 'text', 'max' => 80]], 'ENTER searches · ESC cancels');
        }
        if ($cmd === 'submit') {
            $st['q'] = trim((string) ($in['data']['q'] ?? ''));
            $st['step'] = 'results';
        }
        if ($key === "\x1B" || $key === 'Q') {
            return $e->exitModule();
        }
        $q = (string) ($st['q'] ?? '');
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $rank = $e->rank();
        $rows = $q === '' ? [] : Db::all(
            'SELECT f.*, a.name AS area FROM files f JOIN file_areas a ON a.id = f.area_id
             WHERE f.deleted_at IS NULL AND f.is_approved = 1 AND a.min_read_rank <= ?
               AND (f.filename LIKE ? OR f.title LIKE ? OR f.description LIKE ?)
             ORDER BY f.downloads DESC LIMIT 40',
            [$rank, $like, $like, $like]
        );
        if (ctype_digit($key) && $key !== '0') {
            $idx = (int) $key - 1;
            if (isset($rows[$idx])) {
                $e->session->put('file.area', (int) $rows[$idx]['area_id']);
                $st['detail'] = (int) $rows[$idx]['id'];
                return $this->detail($e, $rows[$idx]);
            }
        }
        $f = Frame::make('screen')->title('Find: ' . $q)->mode('menu')
            ->header('File search "' . $q . '"', count($rows) . ' hits')->blank();
        $choices = [];
        foreach ($rows as $i => $r) {
            $choices[] = [
                'key'   => (string) ($i + 1),
                'label' => mb_substr($r['filename'], 0, 40),
                'desc'  => sprintf('%s · %s', mb_substr($r['area'], 0, 16), $this->hsize((int) $r['size_bytes'])),
            ];
        }
        $this->picker($f, $choices);
        if ($q !== '' && !$rows) {
            $f->pipe('|08   No matching files.');
        }
        return $f->footer('↑↓ move  ·  ENTER details  ·  Q back');
    }

    private function upload(Engine $e, array $in, string $cmd, string $key, array &$st): Frame
    {
        if (!$e->can('file.upload')) {
            return $this->denied($e, 'upload files');
        }
        $area = $this->curArea($e);
        $rank = $e->rank();
        $areas = Db::all('SELECT * FROM file_areas WHERE kind = "files" AND min_upload_rank <= ? ORDER BY sort', [$rank]);
        if (!$areas) {
            return $this->denied($e, 'upload to any area (need a higher access level)');
        }
        if ($cmd === 'cancel' || $key === "\x1B") {
            return $e->exitModule();
        }
        if ($cmd === 'submit') {
            $d = (array) ($in['data'] ?? []);
            $file = (array) ($d['file'] ?? []);
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($file['name'] ?? $d['filename'] ?? '')) ?: '';
            $b64  = (string) ($file['b64'] ?? '');
            $raw  = $b64 !== '' ? base64_decode($b64, true) : false;
            $areaId = (int) ($d['area'] ?? $areas[0]['id']);
            $desc = trim((string) ($d['description'] ?? ''));

            if ($name === '' || $raw === false || $raw === '') {
                return $this->uploadForm($e, $areas, 'Pick a file and give it a description.')->sound('error');
            }
            if (strlen($raw) > self::MAX_UPLOAD) {
                return $this->uploadForm($e, $areas, 'File is over 16 MB.')->sound('error');
            }
            $hash = hash('sha256', $raw);
            $rel  = date('Y/m') . '/' . substr($hash, 0, 2) . '/' . $hash . '_' . $name;
            Storage::put('files', $rel, $raw);
            $id = Db::insert('files', [
                'area_id'         => $areaId,
                'filename'        => mb_substr($name, 0, 158),
                'title'           => mb_substr(trim((string) ($d['title'] ?? $name)), 0, 158),
                'description'     => mb_substr($desc, 0, 4000),
                'size_bytes'      => strlen($raw),
                'sha256'          => $hash,
                'storage_path'    => $rel,
                'uploader_id'     => $e->session->userId,
                'uploader_handle' => $e->session->handle(),
                'is_approved'     => $e->rank() >= 80 ? 1 : 0,
                'created_at'      => date('Y-m-d H:i:s'),
                'approved_at'     => $e->rank() >= 80 ? date('Y-m-d H:i:s') : null,
            ]);
            if ($e->session->userId) {
                Db::q('UPDATE users SET uploads = uploads + 1 WHERE id = ?', [$e->session->userId]);
            }
            Db::q('UPDATE file_areas SET file_count = file_count + 1 WHERE id = ?', [$areaId]);
            AuditLog::record('file.upload', 'file', $id, $name);
            return $e->exitModule()->sound('beep');
        }
        return $this->uploadForm($e, $areas);
    }

    private function uploadForm(Engine $e, array $areas, string $err = ''): Frame
    {
        $opts = [];
        foreach ($areas as $a) {
            $opts[$a['id']] = $a['name'];
        }
        return Frame::make('form')->title('Upload')->header('Upload a file')->blank()
            ->pipe($err ? '|12   ' . $err : '|07   Uploads are held for SysOp approval unless you are staff. 16 MB max.')
            ->form([
                ['name' => 'file', 'label' => 'File', 'type' => 'file', 'max' => self::MAX_UPLOAD],
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'max' => 150],
                ['name' => 'area', 'label' => 'Area', 'type' => 'select', 'options' => $opts],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 3000],
            ], 'ENTER uploads · ESC cancels');
    }

    private function hsize(int $b): string
    {
        $u = ['B', 'K', 'M', 'G'];
        $i = 0;
        $n = (float) $b;
        while ($n >= 1024 && $i < 3) {
            $n /= 1024;
            $i++;
        }
        return ($i === 0 ? (string) $b : number_format($n, 1)) . $u[$i];
    }
}
