<?php
// Vercel entry point: every request is routed here (see vercel.json),
// including /assets/*, which public/index.php serves itself.
define('VIA_API_ENTRY', true);
require __DIR__ . '/../public/index.php';
