<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Rebuild/Kernel.php';

(new App\Rebuild\Kernel())->run();
