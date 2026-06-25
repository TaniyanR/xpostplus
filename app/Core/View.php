<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/Views/' . $template . '.php';
        $content = ob_get_clean();
        if ($layout === null) return $content;
        ob_start();
        require dirname(__DIR__) . '/Views/' . $layout . '.php';
        return ob_get_clean();
    }
}
