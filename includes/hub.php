<?php
// Super-app catalog: businesses (distribution and others) + shared tools.

function hub_name(): string
{
    $cfg = app_config();
    return (string)($cfg['hub']['name'] ?? 'Ki');
}

function hub_businesses(): array
{
    $cfg = app_config();
    $list = $cfg['hub']['businesses'] ?? null;
    if (is_array($list) && $list !== []) {
        return $list;
    }
    return [
        [
            'id'       => 'kd',
            'name'     => 'Ki Distributers',
            'subtitle' => 'Outlets · orders · van · stock',
            'href'     => '/index.php',
            'icon'     => 'distribution',
        ],
    ];
}

function hub_tools(): array
{
    $cfg = app_config();
    $list = $cfg['hub']['tools'] ?? null;
    if (is_array($list) && $list !== []) {
        return $list;
    }
    return [
        [
            'id'       => 'pdf-compress',
            'name'     => 'PDF compress',
            'subtitle' => 'Shrink a file for WhatsApp or email',
            'href'     => '/apps/tools/pdf-compress.php',
            'icon'     => 'pdf',
        ],
    ];
}

function hub_svg(string $icon): string
{
    $map = [
        'distribution' => '<rect x="3" y="10" width="13" height="8" rx="1"/><path d="M16 14h4l3 4H16"/><circle cx="7" cy="20" r="2"/><circle cx="18" cy="20" r="2"/>',
        'pdf'          => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'school'       => '<path d="M3 10 12 4l9 6v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M12 22V12"/>',
        'external'     => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'tool'         => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/>',
    ];
    return $map[$icon] ?? $map['external'];
}

function hub_find_gs(): ?string
{
    foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'] as $p) {
        if (is_executable($p)) return $p;
    }
    $which = trim((string)@shell_exec('command -v gs 2>/dev/null'));
    if ($which !== '' && is_executable($which)) return $which;
    return null;
}
