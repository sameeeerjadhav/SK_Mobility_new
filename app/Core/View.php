<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data);
        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
        if (is_file($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function partial(string $partial, array $data = []): void
    {
        extract($data);
        require BASE_PATH . '/app/Views/' . str_replace('.', '/', $partial) . '.php';
    }
}
