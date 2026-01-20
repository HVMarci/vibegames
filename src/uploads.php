<?php
declare(strict_types=1);

function upload_error_message(int $code): string
{
    $uploadMax = ini_get('upload_max_filesize') ?: '';
    $postMax = ini_get('post_max_size') ?: '';
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Upload failed: file exceeds server limit (upload_max_filesize=' . $uploadMax . ', post_max_size=' . $postMax . ').';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Upload failed: file exceeds form limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'Upload failed: file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'Upload failed: no file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Upload failed: missing temporary folder on server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Upload failed: failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload failed: upload stopped by a PHP extension.';
        case UPLOAD_ERR_OK:
            return 'Upload failed.';
        default:
            return 'Upload failed: unknown error.';
    }
}

function ini_size_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '') {
        return 0;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (int)$val;
    switch ($last) {
        case 'g':
            return $num * 1024 * 1024 * 1024;
        case 'm':
            return $num * 1024 * 1024;
        case 'k':
            return $num * 1024;
        default:
            return (int)$val;
    }
}

function post_too_large(): bool
{
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxBytes = ini_size_bytes((string)(ini_get('post_max_size') ?: ''));
    return $postMaxBytes > 0 && $contentLength > $postMaxBytes;
}

function delete_uploaded_path(string $webPath): void
{
    if (!str_starts_with($webPath, '/uploads/')) {
        return;
    }
    $rel = substr($webPath, strlen('/uploads/'));
    if ($rel === '' || str_contains($rel, '/') || str_contains($rel, '\\') || str_contains($rel, '..')) {
        return;
    }
    $full = __DIR__ . '/../public/uploads/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

function save_uploaded_image(array $file): string
{
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($err));
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('Screenshot is too large.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('Server misconfiguration: fileinfo extension is required for uploads.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    if (!str_starts_with($mime, 'image/')) {
        throw new RuntimeException('Screenshot must be an image (detected: ' . $mime . ').');
    }

    $origName = (string)($file['name'] ?? 'image');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]+/i', '', $ext) ?: 'img';

    $dir = __DIR__ . '/../public/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Failed to create upload directory.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Upload failed: could not save the file (check server permissions).');
    }
    return '/uploads/' . $name;
}
