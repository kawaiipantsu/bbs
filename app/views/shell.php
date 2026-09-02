<?php
/** @var array $meta @var string $origin @var string $site @var string $buildver @var string $asset_v */
use Bbs\Core\View;
$e = fn ($v) => View::e($v);
$title  = $meta['title'] ?? $site;
$desc   = $meta['description'] ?? '';
$ogdesc = $meta['og_description'] ?? $desc;
$canon  = $meta['canonical'] ?? ($origin . '/');
$ogimg  = $meta['og_image'] ?? ($origin . '/og/default.png');
$ogtype = $meta['og_type'] ?? 'website';
$ogalt  = $meta['og_image_alt'] ?? $title;
$goto   = $meta['goto'] ?? '';
$jsonld = $meta['jsonld'] ?? null;
?><!doctype html>
<html lang="en" data-goto="<?= $e($goto) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($title) ?></title>
<meta name="description" content="<?= $e($desc) ?>">
<link rel="canonical" href="<?= $e($canon) ?>">
<meta name="theme-color" content="#0e1013">
<meta name="color-scheme" content="dark">
<meta name="robots" content="index,follow,max-image-preview:large">
<meta name="generator" content="THUGS(red) BBS Engine v<?= $e($buildver) ?>">

<meta property="og:site_name" content="<?= $e($site) ?>">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($ogdesc) ?>">
<meta property="og:type" content="<?= $e($ogtype) ?>">
<meta property="og:url" content="<?= $e($canon) ?>">
<meta property="og:locale" content="en_US">
<meta property="og:image" content="<?= $e($ogimg) ?>">
<meta property="og:image:secure_url" content="<?= $e($ogimg) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= $e($ogalt) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($ogdesc) ?>">
<meta name="twitter:image" content="<?= $e($ogimg) ?>">
<meta name="twitter:image:alt" content="<?= $e($ogalt) ?>">

<link rel="icon" href="/media/images/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/media/images/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/media/images/favicon-180.png">
<link rel="manifest" href="/manifest.webmanifest">

<link rel="preload" as="font" type="font/woff2" href="/media/fonts/bbsterm-regular.woff2" crossorigin>
<link rel="stylesheet" href="/css/bbs.css?v=<?= $e($asset_v) ?>">
<link rel="stylesheet" href="/css/crt.css?v=<?= $e($asset_v) ?>">
<?php if ($jsonld): ?>
<script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
</head>
<body>
<noscript>
  <div class="noscript">
    <h1><?= $e($site) ?></h1>
    <p>This board is a terminal. It needs JavaScript to render ANSI, play the modem
       and read the keyboard. Turn it on and dial back in.</p>
    <p><?= $e($desc) ?></p>
  </div>
</noscript>

<div id="crt" aria-hidden="false">
  <img id="monitor" src="/media/images/monitor.png" alt="" draggable="false">
  <div id="glass">
    <div id="screen" role="application" aria-label="<?= $e($site) ?> terminal" tabindex="0"></div>
    <div id="fx-scanlines"></div>
    <div id="fx-vignette"></div>
    <div id="fx-glow"></div>
    <div id="fx-flicker"></div>
    <div id="fx-roll"></div>
  </div>
  <div id="panel">
    <button id="knob-bright"   class="knob" type="button" aria-label="Brightness" title="Brightness — drag up/down or scroll"></button>
    <button id="knob-contrast" class="knob" type="button" aria-label="Contrast (colour)" title="Contrast — colour intensity, drag up/down or scroll"></button>
    <button id="power-btn"      type="button" aria-label="Power"      title="Power"><span class="led"></span></button>
  </div>
</div>

<div id="hud">
  <button id="btn-sound" type="button" aria-pressed="false" title="Toggle sound (Ctrl+S)">SOUND: OFF</button>
  <button id="btn-crt" type="button" aria-pressed="true" title="Toggle CRT FX">CRT: ON</button>
  <button id="btn-full" type="button" title="Fullscreen (F11)">[ ]</button>
  <span id="hud-node"></span>
</div>

<div id="ticker"><div id="ticker-track"></div></div>

<script type="module" src="/js/app.js?v=<?= $e($asset_v) ?>"></script>
</body>
</html>
