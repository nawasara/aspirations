<?php

$prefix = 'nawasara-aspirations';

/*
| Sidebar menu for Lapor Bunda — configuration and monitoring only.
|
| Handling reports (dispatch, respond, Kabid verification) lives in the Next.js
| panel, not here. These pages exist so the policy numbers, categories and
| overall health can be seen and changed without leaving Nawasara.
|
| ⚠️ Workspace id `ponorogo-hub` is shared with nawasara/citizen. WorkspaceManager
| merges entries with the same id and takes label + icon from whichever loads
| FIRST alphabetically — and `nawasara-aspirations` sorts before
| `nawasara-citizen`. So this file decides the heading for the whole workspace:
| label and icon MUST stay identical to the ones in nawasara/citizen, or the
| sidebar heading silently changes.
|
| ⚠️ `group` must be one of WorkspaceManager::GROUP_ORDER. Anything else lands
| under "Lainnya" without warning.
*/

return [
    [
        'workspace' => 'ponorogo-hub',
        'label' => 'Ponorogo Hub',
        'icon' => 'lucide-landmark',
        'group' => 'Layanan',
        'url' => '',
        'permission' => 'aspirations.dashboard.view',
        'submenu' => [
            [
                'label' => 'Lapor Bunda',
                'icon' => 'lucide-megaphone',
                'url' => url($prefix.'/dashboard'),
                'permission' => 'aspirations.dashboard.view',
                'navigate' => true,
            ],
            [
                'label' => 'Laporan Masuk',
                'icon' => 'lucide-inbox',
                'url' => url($prefix.'/reports'),
                'permission' => 'aspirations.report.view',
                'navigate' => true,
            ],
            [
                'label' => 'Kategori Laporan',
                'icon' => 'lucide-tags',
                'url' => url($prefix.'/categories'),
                'permission' => 'aspirations.category.view',
                'navigate' => true,
            ],
            [
                'label' => 'Pengaturan Lapor',
                'icon' => 'lucide-sliders-horizontal',
                'url' => url($prefix.'/settings'),
                'permission' => 'aspirations.category.manage',
                'navigate' => true,
            ],
        ],
    ],
];
