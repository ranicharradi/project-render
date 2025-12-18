<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($path === '/healthz') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ok\n";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PHP on Render</title>
    <style>
      body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 40px; line-height: 1.4; }
      code { background: #f3f4f6; padding: 2px 6px; border-radius: 6px; }
      .box { padding: 16px; border: 1px solid #e5e7eb; border-radius: 12px; max-width: 720px; }
    </style>
  </head>
  <body>
    <h1>Deployed PHP app</h1>
    <div class="box">
      <p>If you can see this page, your service is running.</p>
      <p>Try: <code>/healthz</code></p>
      <p>UTC time: <code><?= htmlspecialchars($now, ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>
  </body>
</html>
