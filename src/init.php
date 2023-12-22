<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {

    require __DIR__ . '/../../../autoload.php';
}

use Wpjscc\Task\Task;
use MessagePack\BufferUnpacker;
use MessagePack\MessagePack;

$unpacker = new BufferUnpacker();

$stream = new \React\Stream\ReadableResourceStream(STDIN);

$stream->on('data', function ($chunk)  use ($unpacker) {
    $pid = getmypid();
    Task::replay("Task {$pid} receive data\n"); 
    $unpacker->append($chunk);
    if ($messages = $unpacker->tryUnpack()) {
        $unpacker->release();
        foreach ($messages as $message) {
            // log 
            // $writeStream->write(MessagePack::pack($message));
            Task::handleTask($message);
        }
        
        return $messages;
    } else {
        Task::replay("Task {$pid} tryPack fail\n");
    }
});

$stream->on('end', function () {

});

// only test in one process
// $stream->emit('data', [MessagePack::pack([
//     'cmd' => 'task',
//     'uuid' => 'hello',
//     'data' => [
//         'serialized' => Task::getSeralized(function () {
//             return 'james';
//         })
//     ]
// ])]);

Task::replayInit();