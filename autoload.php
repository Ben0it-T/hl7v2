<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'HL7v2\\';

    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    }
});
