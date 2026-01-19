<?php
declare(strict_types=1);

$user = require_auth();
$pdo = db();

$aspectsStmt = $pdo->query('SELECT id, label, weight FROM aspects WHERE active = 1 ORDER BY sort_order ASC, id ASC');
$aspects = $aspectsStmt->fetchAll();
if (!$aspects) {
    render('Vote', '<h1>Vote</h1><p>No aspects configured yet. Insert rows into the <code>aspects</code> table.</p>');
}

function vote_is_ajax(): bool
{
    $xrw = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if (strtolower($xrw) === 'fetch') {
        return true;
    }
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    return stripos($accept, 'application/json') !== false;
}

function vote_json(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function vote_fail(string $message, ?int $entryId = null): void
{
    if (vote_is_ajax()) {
        vote_json(400, ['ok' => false, 'error' => $message, 'entryId' => $entryId]);
    }
    flash_set('err', $message);
    redirect($entryId ? ('/vote#entry-' . $entryId) : '/vote');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $postedVotes = $_POST['vote'] ?? null;
    if (!is_array($postedVotes)) {
        vote_fail('Invalid vote submission.');
    }
    $touched = $_POST['touched'] ?? null;
    if (!is_array($touched)) {
        vote_fail('Invalid vote submission.');
    }

    $entriesStmt = $pdo->query('SELECT id, user_id FROM entries');
    $allEntries = $entriesStmt->fetchAll();
    $entryOwnerById = [];
    foreach ($allEntries as $e) {
        $entryOwnerById[(int)$e['id']] = (int)$e['user_id'];
    }

    $aspectIds = array_map(fn($a) => (int)$a['id'], $aspects);
    $toSave = [];  // entry_id => [aspect_id => score]
    $toClearAll = []; // entry_id => true

    foreach ($touched as $entryIdStr => $isTouched) {
        if ((string)$isTouched !== '1' || !ctype_digit((string)$entryIdStr)) {
            continue;
        }
        $entryId = (int)$entryIdStr;
        $ownerId = $entryOwnerById[$entryId] ?? null;
        if ($ownerId === null) {
            continue;
        }
        if ($ownerId === (int)$user['id']) {
            vote_fail('You cannot vote for your own entry.', $entryId);
        }

        $aspectMap = $postedVotes[$entryId] ?? null;
        if (!is_array($aspectMap)) {
            $aspectMap = [];
        }

        $clean = []; // numeric scores only; missing means "no vote"
        foreach ($aspectIds as $aid) {
            $v = (string)($aspectMap[$aid] ?? '-');
            if ($v === '' || $v === '-') {
                continue;
            }
            if (!ctype_digit($v)) {
                vote_fail('Invalid score value.', $entryId);
            }
            $iv = (int)$v;
            if ($iv < 1 || $iv > 10) {
                vote_fail('Scores must be between 1 and 10.', $entryId);
            }
            $clean[$aid] = $iv;
        }
        if (!$clean) {
            $toClearAll[$entryId] = true;
        } else {
            $toSave[$entryId] = $clean;
        }
    }

    if (!$toSave && !$toClearAll) {
        vote_fail('Nothing to save yet. Change at least one entry.');
    }

    $pdo->beginTransaction();
    try {
        $selectVote = $pdo->prepare('SELECT id FROM votes WHERE user_id = ? AND entry_id = ?');
        $touchVote = $pdo->prepare('UPDATE votes SET updated_at = NOW() WHERE id = ?');
        $insertVote = $pdo->prepare('INSERT INTO votes (user_id, entry_id) VALUES (?, ?)');
        $deleteItems = $pdo->prepare('DELETE FROM vote_items WHERE vote_id = ?');
        $insertItem = $pdo->prepare('INSERT INTO vote_items (vote_id, aspect_id, score) VALUES (?, ?, ?)');
        $deleteVote = $pdo->prepare('DELETE FROM votes WHERE user_id = ? AND entry_id = ?');

        foreach (array_keys($toClearAll) as $entryId) {
            $deleteVote->execute([(int)$user['id'], (int)$entryId]);
        }
        foreach ($toSave as $entryId => $scoresByAspect) {
            $selectVote->execute([(int)$user['id'], $entryId]);
            $vote = $selectVote->fetch();
            if ($vote) {
                $voteId = (int)$vote['id'];
                $touchVote->execute([$voteId]);
            } else {
                $insertVote->execute([(int)$user['id'], $entryId]);
                $voteId = (int)$pdo->lastInsertId();
            }
            $deleteItems->execute([$voteId]);
            foreach ($scoresByAspect as $aid => $score) {
                $insertItem->execute([$voteId, (int)$aid, (int)$score]);
            }
        }

        $pdo->commit();
        if (vote_is_ajax()) {
            vote_json(200, ['ok' => true, 'message' => 'Votes saved.']);
        }
        flash_set('ok', 'Votes saved.');
        redirect('/vote');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (vote_is_ajax()) {
            vote_json(500, ['ok' => false, 'error' => $e->getMessage()]);
        }
        throw $e;
    }
}

$entriesStmt = $pdo->query('
  SELECT e.*, u.display_name, u.email
  FROM entries e
  JOIN users u ON u.id = e.user_id
  ORDER BY e.created_at ASC, e.id ASC
');
$entries = $entriesStmt->fetchAll();

if (!$entries) {
    render('Vote', '<h1>Vote</h1><p>No entries yet. Once someone registers an entry, it will appear here.</p>');
}

// Prefill current user's saved scores
$entryIds = array_map(fn($e) => (int)$e['id'], $entries);
$prefill = [];
if ($entryIds) {
    $in = implode(',', array_fill(0, count($entryIds), '?'));
    $stmt = $pdo->prepare("
      SELECT v.entry_id, vi.aspect_id, vi.score
      FROM votes v
      JOIN vote_items vi ON vi.vote_id = v.id
      WHERE v.user_id = ? AND v.entry_id IN ($in)
    ");
    $stmt->execute(array_merge([(int)$user['id']], $entryIds));
    foreach ($stmt->fetchAll() as $row) {
        $prefill[(int)$row['entry_id']][(int)$row['aspect_id']] = (int)$row['score'];
    }
}

$content = '<h1>Vote</h1>';
$content .= '<p class="hint">Pick scores 1–10. Use “-” to skip a vote. Saving overwrites your previous votes.</p>';
$content .= '<form method="post" id="vote-form">';
$content .= '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '" />';

foreach ($entries as $entry) {
    $isOwn = (int)$entry['user_id'] === (int)$user['id'];
    $content .= '<input type="hidden" class="touched" name="touched[' . (int)$entry['id'] . ']" value="0" />';
    $content .= '<div class="card" id="entry-' . (int)$entry['id'] . '">';
    $content .= '<div class="entry-head">';
    $content .= '<img class="thumb" src="' . h($entry['screenshot_path']) . '" alt="Screenshot" />';
    $content .= '<div>';
    $content .= '<h2 style="margin-top:0;">' . h($entry['title']) . '</h2>';
    $content .= '<div class="hint">Creator: ' . h($entry['creator_name']) . ' (account: ' . h($entry['display_name']) . ')</div>';
    $content .= '<p>' . nl2br(h($entry['description'])) . '</p>';
    if ($isOwn) {
        $content .= '<div class="msg err">You cannot vote for your own entry.</div>';
    }
    $content .= '</div></div>';

    if (!$isOwn) {
        $content .= '<div class="aspect-list">';
        foreach ($aspects as $aspect) {
            $aid = (int)$aspect['id'];
            $saved = $prefill[(int)$entry['id']][$aid] ?? null;
            $content .= '<div class="aspect-row">';
            $content .= '<div class="aspect-meta">';
            $content .= '<div style="font-weight:800;">' . h($aspect['label']) . '</div>';
            $pct = (int)round(((float)$aspect['weight']) * 100);
            $content .= '<div class="hint">Weight: ' . h((string)$pct) . '%</div>';
            $content .= '</div>';
            $content .= '<div class="aspect-input">';
            $content .= '<label class="small" style="margin:0 0 6px 0;">Score</label>';
            $content .= '<select class="vote-score" data-entry-id="' . (int)$entry['id'] . '" name="vote[' . (int)$entry['id'] . '][' . $aid . ']">';
            $content .= '<option value="-"' . ($saved === null ? ' selected' : '') . '>-</option>';
            for ($i = 1; $i <= 10; $i++) {
                $sel = ($saved === $i) ? 'selected' : '';
                $content .= '<option value="' . $i . '" ' . $sel . '>' . $i . '</option>';
            }
            $content .= '</select>';
            $content .= '</div>';
            $content .= '</div>';
        }
        $content .= '</div>';
    }
    $content .= '</div>';
}

$content .= '<div class="savebar"><button class="btn" type="submit">Save all votes</button></div>';
$content .= '</form>';
$content .= '<div id="vote-toast" class="toast" role="status" aria-live="polite" style="display:none;"></div>';
$content .= '<script>
(function(){
  var form = document.getElementById("vote-form");
  var toast = document.getElementById("vote-toast");
  if (!form || !toast) return;
  function showToast(kind, msg){
    toast.className = "toast " + kind;
    toast.textContent = msg;
    toast.style.display = "block";
    clearTimeout(toast._t);
    toast._t = setTimeout(function(){ toast.style.display = "none"; }, 6000);
  }
  form.addEventListener("submit", async function(ev){
    ev.preventDefault();
    var btn = form.querySelector("button[type=submit]");
    if (btn) { btn.disabled = true; btn.textContent = "Saving…"; }
    try{
      var res = await fetch("/vote", { method:"POST", body: new FormData(form), headers: { "X-Requested-With": "fetch", "Accept": "application/json" } });
      var data = null;
      try { data = await res.json(); } catch (e) {}
      if (res.ok && data && data.ok){
        showToast("ok", data.message || "Saved.");
      } else {
        var msg = (data && data.error) ? data.error : ("Save failed (HTTP " + res.status + ").");
        showToast("err", msg);
        if (data && data.entryId){
          var el = document.getElementById("entry-" + data.entryId);
          if (el) el.scrollIntoView({ behavior:"smooth", block:"start" });
        }
      }
    } catch (e){
      showToast("err", "Save failed: " + (e && e.message ? e.message : "Network error"));
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = "Save all votes"; }
    }
  });
  function markTouched(entryId){
    var inp = form.querySelector("input.touched[name=\'touched[" + entryId + "]\']");
    if (inp) inp.value = "1";
  }
  form.addEventListener("change", function(ev){
    var t = ev.target;
    if (t && t.classList && t.classList.contains("vote-score")){
      markTouched(t.getAttribute("data-entry-id"));
    }
  });
})();
</script>';

render('Vote', $content);
