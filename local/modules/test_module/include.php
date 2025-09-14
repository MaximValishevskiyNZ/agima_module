<?php

Bitrix\Main\Loader::registerAutoloadClasses(
    'test_module',
    array(
        'Test\\Main' => 'lib/Main.php',
    )
);
 $logFile = __DIR__ . '/../log/event_handler.log';
        $logData = date('Y-m-d H:i:s') . " | Вызван include\n";
file_put_contents($logFile, $logData, FILE_APPEND);