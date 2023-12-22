<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;


Task::run();


$event = Task::addTask(function ($uuid) {
    Task::replayData($uuid, 'hello world');
    return 'success';
});

$event->on('data', function ($data) {
    echo ($data) . "\n";
});

$event->on('success', function ($data) {
    echo ($data) . "\n";
});
