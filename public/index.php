<?php

require dirname(__DIR__) . '/app/Config/bootstrap.php';

$router = require BASE_PATH . '/app/Config/routes.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$router->dispatch($method, $uri);
