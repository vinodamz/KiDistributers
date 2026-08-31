<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/hub.php';

$user = require_login();
$hubMode = true;
$pageTitle = 'PDF compress — ' . hub_name();

$presets = [
    'screen'  => 'Smallest (screen / WhatsApp)',
    'ebook'   => 'Balanced (recommended)',
    'printer' => 'High quality (print)',
];
$gs = hub_find_gs();
if ($gs !== null && !function_exists('exec')) {
    $gs = null;
    $execBlocked = true;
} else {
    $execBlocked = false;
}
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($gs === null) {
        $err = 'Ghostscript (gs) is not installed on this server. Ask Hostgator to enable it, or run this tool on a machine that has gs.';
    } else {
        $preset = (string)($_POST['preset'] ?? 'ebook');
        if (!isset($presets[$preset])) $preset = 'ebook';

        if (empty($_FILES['pdf']['tmp_name']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
            $err = 'Choose a PDF file.';
        } elseif (($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = 'Upload failed (error ' . (int)$_FILES['pdf']['error'] . '). Try a smaller file.';
        } elseif ((int)$_FILES['pdf']['size'] > 15 * 1024 * 1024) {
            $err = 'File is over 15 MB.';
        } else {
            $tmp = $_FILES['pdf']['tmp_name'];
            $fh = fopen($tmp, 'rb');
            $magic = $fh ? (string)fread($fh, 5) : '';
            if ($fh) fclose($fh);
            if (!str_starts_with($magic, '%PDF')) {
                $err = 'That file is not a PDF.';
            } else {
                $in  = tempnam(sys_get_temp_dir(), 'kdpdfi');
                $out = tempnam(sys_get_temp_dir(), 'kdpdfo');
                $inPdf  = $in . '.pdf';
                $outPdf = $out . '.pdf';
                @unlink($in);
                @unlink($out);
                if (!move_uploaded_file($tmp, $inPdf)) {
                    $err = 'Could not store the upload.';
                } else {
                    $cmd = sprintf(
                        '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
                        escapeshellcmd($gs),
                        $preset,
                        escapeshellarg($outPdf),
                        escapeshellarg($inPdf)
                    );
                    $code = 0;
                    $unused = [];
                    exec($cmd . ' 2>&1', $unused, $code);
                    $ok = $code === 0 && is_file($outPdf) && filesize($outPdf) > 0;
                    if (!$ok) {
                        $err = 'Compression failed. Ghostscript exit code ' . $code . '.';
                        @unlink($inPdf);
                        @unlink($outPdf);
                    } else {
                        $origName = (string)($_FILES['pdf']['name'] ?? 'document.pdf');
                        $base = preg_replace('/[^\w.\-]+/', '_', pathinfo($origName, PATHINFO_FILENAME)) ?: 'document';
                        $download = $base . '-compressed.pdf';
                        $size = filesize($outPdf);
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . $download . '"');
                        header('Content-Length: ' . $size);
                        header('X-Original-Size: ' . (int)$_FILES['pdf']['size']);
                        readfile($outPdf);
                        @unlink($inPdf);
                        @unlink($outPdf);
                        exit;
                    }
                }
            }
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>PDF compress</h1>
        <p class="muted"><a href="/apps/index.php">← Apps</a> · Shrink a PDF with Ghostscript on this server. Files are not kept after download.</p>
    </div>
</div>

<?php if ($gs === null): ?>
    <div class="flash flash-error"><?= !empty($execBlocked)
        ? 'This host has disabled PHP exec(), so PDF compress cannot run.'
        : 'Ghostscript (gs) was not found. This tool cannot run until Hostgator provides it.' ?></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endif; ?>

<form method="post" class="card card-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
        <label>PDF file</label>
        <input type="file" name="pdf" accept="application/pdf,.pdf" required>
    </div>
    <div class="field">
        <label>Size vs quality</label>
        <select name="preset">
            <?php foreach ($presets as $k => $lab): ?>
                <option value="<?= e($k) ?>" <?= $k === 'ebook' ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button class="btn btn-primary" <?= $gs === null ? 'disabled' : '' ?>>Compress and download</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
