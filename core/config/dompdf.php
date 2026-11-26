<?php

$appPublicPath = base_path('public');
$deployedPublicPath = base_path('../');

return [
    'public_path' => is_dir($appPublicPath) ? $appPublicPath : $deployedPublicPath,
];
