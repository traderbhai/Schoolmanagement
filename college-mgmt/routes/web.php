<?php

foreach ([
    'public',
    'applicant',
    'admission',
    'admin',
    'academics',
    'teacher',
    'student',
    'accounts',
    'approvals',
    'cmc',
] as $routeFile) {
    require __DIR__."/{$routeFile}.php";
}

require __DIR__.'/auth.php';
