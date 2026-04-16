<?php

use App\Providers\AppServiceProvider;

return [
    'name' => 'Git Worktree',
    'version' => app('git.version'),
    'env' => 'development',
    'providers' => [
        AppServiceProvider::class,
    ],
];
