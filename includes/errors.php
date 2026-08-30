<?php
declare(strict_types=1);

ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
              . ' in ' . $e->getFile() . ':' . $e->getLine());
    kd_friendly_error_page();
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err !== null
        && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        kd_friendly_error_page();
    }
});

function kd_friendly_error_page(): void
{
    if (PHP_SAPI === 'cli') return;

    static $shown = false;
    if ($shown) return;
    $shown = true;

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Something went wrong</title></head>'
       . '<body style="margin:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;'
       . 'background:#f4f7f8;color:#2b2b2b;display:flex;min-height:100vh;align-items:center;justify-content:center;">'
       . '<div style="text-align:center;padding:2rem;max-width:420px;">'
       . '<div style="font-size:3rem;">📦</div>'
       . '<h1 style="color:#115e59;font-size:1.3rem;margin:.5rem 0;">Something went wrong</h1>'
       . '<p style="color:#666;font-size:.95rem;">Sorry — this page hit a snag. It\'s been logged. '
       . 'Please try again in a minute.</p>'
       . '<a href="javascript:location.reload()" style="display:inline-block;margin-top:.8rem;padding:.6rem 1.2rem;'
       . 'background:#0f766e;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Try again</a>'
       . '</div></body></html>';
}
