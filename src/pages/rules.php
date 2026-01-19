<?php
declare(strict_types=1);

$content = '<h1>Rules</h1>';
$content .= '<p class="hint">1st vibecoding competition</p>';
$content .= '<div class="grid">';
$content .= '<div>';
$content .= '<h2>Voting</h2>';
$content .= '<ul>';
$content .= '<li>Scores are 1–10 per aspect. Use “-” to leave an aspect unrated.</li>';
$content .= '<li>You cannot vote for your own entry.</li>';
$content .= '<li>Saving again overwrites your previous votes for the entries you changed.</li>';
$content .= '</ul>';
$content .= '</div>';
$content .= '<div>';
$content .= '<h2>Entries</h2>';
$content .= '<ul>';
$content .= '<li>Each user can register exactly one entry.</li>';
$content .= '<li>Provide a title, description, creator name, and a screenshot.</li>';
$content .= '</ul>';
$content .= '</div>';
$content .= '<div>';
$content .= '<h2>Results</h2>';
$content .= '<ul>';
$content .= '<li>Per aspect: average of all submitted scores (unrated “-” values are ignored).</li>';
$content .= '<li>Total score: sum of (average × weight) across aspects.</li>';
$content .= '<li>Ties share the same rank (e.g. 1., 2., 2., 4.).</li>';
$content .= '</ul>';
$content .= '</div>';
$content .= '</div>';

render('Rules', $content);

