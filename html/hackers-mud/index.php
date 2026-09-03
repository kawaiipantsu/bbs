<?php

/**
 * Entry document for the standalone graphical Hackers-MUD client.
 * Apache's DirectoryIndex serves this for /hackers-mud/ ; everything else in
 * this folder is a static asset. The shell HTML + headers come from
 * HackersMudController so there's one source of truth.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use Bbs\Core\Config;
use Bbs\Core\Request;
use Bbs\Http\Controllers\HackersMudController;

Config::loadSettings();

(new HackersMudController())->shell(Request::capture())->send();
