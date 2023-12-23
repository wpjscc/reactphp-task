<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;

$event = Task::addTask(function ($uuid) {
    return [
        'code' => 0,
        'data' => [
            'name' => 'once process',
        ]
    ];
}, true);
$event->on('data', function ($data) {
    echo ($data) . "\n";
});

$event->once('fail', function ($data) {
    echo ($data) . "\n";
});
