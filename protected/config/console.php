<?php

$extraConsoleConfig = array(
    'components' => array('log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning',
                    'maxFileSize' => 16384, // 16 MB
                ),
            ),
        ),
    )
);

$config = include('main.php');

foreach ($extraConsoleConfig as $k => $v) {
    if (isset($config[$k])) {
        $config[$k] = array_merge($config[$k], $v);
    } else {
        $config[$k] = $v;
    }
}

unset($config['controllerMap']);
return $config;