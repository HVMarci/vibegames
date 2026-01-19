<?php
declare(strict_types=1);

$user = require_auth();
require_results_viewer($user);

$pdo = db();

$aspectsStmt = $pdo->query('SELECT id, label, weight FROM aspects WHERE active = 1 ORDER BY sort_order ASC, id ASC');
$aspects = $aspectsStmt->fetchAll();
if (!$aspects) {
    render('Results', '<h1>Results</h1><p>No aspects configured.</p>');
}

function fmt_score(float $value, int $maxDecimals = 4): string
{
    $s = number_format($value, $maxDecimals, '.', '');
    $s = rtrim($s, '0');
    $s = rtrim($s, '.');
    return $s === '' ? '0' : $s;
}

$rows = $pdo->query('
  SELECT
    e.id AS entry_id,
    e.title,
    e.creator_name,
    e.screenshot_path,
    a.id AS aspect_id,
    a.label AS aspect_label,
    a.weight AS aspect_weight,
    AVG(vi.score) AS avg_score
  FROM entries e
  JOIN aspects a ON a.active = 1
  LEFT JOIN votes v ON v.entry_id = e.id
  LEFT JOIN vote_items vi ON vi.vote_id = v.id AND vi.aspect_id = a.id
  GROUP BY e.id, a.id
  ORDER BY e.created_at ASC, e.id ASC, a.sort_order ASC, a.id ASC
')->fetchAll();

if (!$rows) {
    render('Results', '<h1>Results</h1><p>No entries yet.</p>');
}

$entries = [];
foreach ($rows as $r) {
    $eid = (int)$r['entry_id'];
    if (!isset($entries[$eid])) {
        $entries[$eid] = [
            'id' => $eid,
            'title' => $r['title'],
            'creator_name' => $r['creator_name'],
            'screenshot_path' => $r['screenshot_path'],
            'aspects' => [],
            'total' => 0.0,
        ];
    }
    $avg = $r['avg_score'] === null ? null : (float)$r['avg_score'];
    $w = (float)$r['aspect_weight'];
    $entries[$eid]['aspects'][(int)$r['aspect_id']] = [
        'label' => $r['aspect_label'],
        'weight' => $w,
        'avg' => $avg,
        'weighted' => $avg === null ? 0.0 : ($avg * $w),
    ];
}

foreach ($entries as &$e) {
    $sum = 0.0;
    foreach ($e['aspects'] as $a) {
        $sum += (float)$a['weighted'];
    }
    $e['total'] = $sum;
}
unset($e);

usort($entries, fn($a, $b) => $b['total'] <=> $a['total']);

$content = '<h1>Results</h1>';
$content .= '<p class="hint">Per entry: average per aspect across voters, then sum(avg × weight).</p>';

$prevScoreKey = null;
$rank = 0;
foreach ($entries as $i => $e) {
    $scoreKey = number_format((float)$e['total'], 6, '.', '');
    if ($prevScoreKey === null) {
        $rank = 1;
        $prevScoreKey = $scoreKey;
    } elseif ($scoreKey !== $prevScoreKey) {
        $rank = $i + 1; // competition ranking: 1,2,2,4
        $prevScoreKey = $scoreKey;
    }
    $content .= '<div class="card">';
    $content .= '<div class="entry-head">';
    $content .= '<div class="rank-col">';
    $content .= '<div class="rank-badge">' . (int)$rank . '.</div>';
    $content .= '<div class="msg ok pill rank-total">Total: ' . h(fmt_score((float)$e['total'], 4)) . '</div>';
    $content .= '</div>';
    $content .= '<img class="thumb" src="' . h($e['screenshot_path']) . '" alt="Screenshot" />';
    $content .= '<div>';
    $content .= '<h2 style="margin-top:0;">' . h($e['title']) . '</h2>';
    $content .= '<div class="hint">Creator: ' . h($e['creator_name']) . '</div>';
    $content .= '</div></div>';

    $content .= '<div class="table-wrap" style="margin-top:10px;">';
    $content .= '<table><thead><tr><th>Aspect</th><th>Weight</th><th>Avg</th><th>Avg×Weight</th></tr></thead><tbody>';
    foreach ($aspects as $aspect) {
        $aid = (int)$aspect['id'];
        $a = $e['aspects'][$aid] ?? null;
        $avg = $a ? $a['avg'] : null;
        $weighted = $a ? $a['weighted'] : 0.0;
        $pct = (int)round(((float)$aspect['weight']) * 100);
        $content .= '<tr>';
        $content .= '<td>' . h((string)$aspect['label']) . '</td>';
        $content .= '<td>' . h((string)$pct) . '%</td>';
        $content .= '<td>' . ($avg === null ? '<span class="hint">—</span>' : h(fmt_score($avg, 4))) . '</td>';
        $content .= '<td>' . h(fmt_score((float)$weighted, 4)) . '</td>';
        $content .= '</tr>';
    }
    $content .= '</tbody></table>';
    $content .= '</div>';
    $content .= '</div>';
}

render('Results', $content);
