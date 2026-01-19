<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h($title) ?></title>
    <link rel="icon" href="/assets/telekom-logo.svg" type="image/svg+xml" />
    <link rel="stylesheet" href="/assets/style.css" />
  </head>
  <body>
    <div class="wrap">
      <div class="topbar">
        <div class="brand">
          <a href="/vote" class="brand">
            <img class="logo-img" src="/assets/telekom-logo.svg" alt="Telekom" />
            <span class="brand-stack">
              <span class="brand-line">
                <span class="wordmark">Telekom</span>
                <span class="brand-title">Vibegames Competition</span>
              </span>
              <span class="brand-subtitle">1st vibecoding competition</span>
            </span>
          </a>
        </div>
        <div class="nav">
          <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="nav-menu">
            <span class="nav-toggle-lines" aria-hidden="true"></span>
            <span class="sr-only">Menu</span>
          </button>
          <div id="nav-menu" class="nav-menu">
          <?php if ($user): ?>
            <a href="/entry">My entry</a>
            <a href="/vote">Vote</a>
            <a href="/rules">Rules</a>
            <?php if ((int)($user['is_results_viewer'] ?? 0) === 1): ?>
              <a href="/results">Results</a>
            <?php endif; ?>
            <a href="/logout">Logout</a>
          <?php else: ?>
            <a href="/register">Register</a>
            <a href="/login">Login</a>
            <a href="/rules">Rules</a>
          <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($ok): ?><div class="card msg ok"><?= h($ok) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="card msg err"><?= h($err) ?></div><?php endif; ?>

      <div class="card">
        <?= $contentHtml ?>
      </div>
      <div class="small" style="margin-top:10px;">
        <?= h($user ? ("Logged in as {$user['display_name']} ({$user['email']})") : 'Not logged in') ?>
      </div>
    </div>
    <script>
      (function(){
        var btn = document.querySelector(".nav-toggle");
        var menu = document.getElementById("nav-menu");
        if (!btn || !menu) return;
        function setOpen(open){
          btn.setAttribute("aria-expanded", open ? "true" : "false");
          menu.classList.toggle("open", !!open);
        }
        btn.addEventListener("click", function(){
          var open = btn.getAttribute("aria-expanded") === "true";
          setOpen(!open);
        });
        document.addEventListener("click", function(e){
          if (!menu.classList.contains("open")) return;
          if (menu.contains(e.target) || btn.contains(e.target)) return;
          setOpen(false);
        });
        window.addEventListener("resize", function(){
          if (window.matchMedia("(min-width: 801px)").matches) setOpen(false);
        });
      })();
    </script>
  </body>
</html>
