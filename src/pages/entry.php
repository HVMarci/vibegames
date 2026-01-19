<?php
declare(strict_types=1);

$user = require_auth();
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM entries WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$entry = $stmt->fetch() ?: null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    if (post_too_large()) {
        $postMax = ini_get('post_max_size') ?: '';
        flash_set('err', 'Upload failed: request exceeds server limit (post_max_size=' . $postMax . ').');
        redirect('/entry');
    }
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $creatorName = trim((string)($_POST['creator_name'] ?? ''));

    if ($title === '' || $description === '' || $creatorName === '') {
        flash_set('err', 'Please fill all fields.');
        redirect('/entry');
    }

    $screenshotPath = $entry['screenshot_path'] ?? '';
    $hasUpload = isset($_FILES['screenshot']) && (int)($_FILES['screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    try {
        if (!$entry && !$hasUpload) {
            flash_set('err', 'Screenshot is required.');
            redirect('/entry');
        }
        if ($hasUpload) {
            $screenshotPath = save_uploaded_image($_FILES['screenshot']);
        }
    } catch (RuntimeException $e) {
        flash_set('err', $e->getMessage());
        redirect('/entry');
    }

    if ($entry) {
        $upd = $pdo->prepare('UPDATE entries SET title = ?, description = ?, creator_name = ?, screenshot_path = ? WHERE id = ? AND user_id = ?');
        $upd->execute([$title, $description, $creatorName, $screenshotPath, (int)$entry['id'], (int)$user['id']]);
        flash_set('ok', 'Entry updated.');
        redirect('/entry');
    }

    $ins = $pdo->prepare('INSERT INTO entries (user_id, title, description, creator_name, screenshot_path) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([(int)$user['id'], $title, $description, $creatorName, $screenshotPath]);
    flash_set('ok', 'Entry registered.');
    redirect('/entry');
}

$content = '<h1>My entry</h1>';
$content .= '<p class="hint">You can register exactly one entry. Submitting again updates your existing entry.</p>';
$uploadMax = ini_get('upload_max_filesize') ?: '';
$postMax = ini_get('post_max_size') ?: '';
if ($uploadMax !== '' || $postMax !== '') {
    $content .= '<p class="hint">Server upload limits: upload_max_filesize=' . h($uploadMax) . ', post_max_size=' . h($postMax) . '.</p>';
}
$content .= '<form method="post" enctype="multipart/form-data" class="grid">';
$content .= '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '" />';
$content .= '<div><label>Title</label><input type="text" name="title" value="' . h($entry['title'] ?? '') . '" required /></div>';
$content .= '<div><label>Creator name</label><input type="text" name="creator_name" value="' . h($entry['creator_name'] ?? '') . '" required /></div>';
$content .= '<div><label>Description</label><textarea name="description" required>' . h($entry['description'] ?? '') . '</textarea></div>';
$content .= '<div><label>Screenshot</label><input type="file" name="screenshot" accept="image/*" ' . ($entry ? '' : 'required') . ' /></div>';
$content .= '<div><button class="btn" type="submit">' . ($entry ? 'Update entry' : 'Register entry') . '</button></div>';
$content .= '</form>';
if ($entry) {
    $content .= '<p class="hint">Current screenshot:</p>';
    $content .= '<p><img class="thumb" src="' . h($entry['screenshot_path']) . '" alt="Screenshot" /></p>';
}

render('My entry', $content);
