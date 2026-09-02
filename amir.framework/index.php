<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT', __DIR__);
define('PHP_PATH', ROOT . '/php');
define('HTML_PATH', ROOT . '/html');

require_once PHP_PATH . '/function/index.php';

$page = $_GET['page'] ?? 'index';

$page = trim($page, '/');

if ($page === '') {
    $page = 'index';
}

if (
    str_contains($page, '..') ||
    str_contains($page, '\\') ||
    str_contains($page, "\0")
) {
    http_response_code(400);
    exit('Bad Request');
}

$phpFile = PHP_PATH . '/' . $page . '.php';
$htmlFile = HTML_PATH . '/' . $page . '.html';

$data = [];

if (file_exists($phpFile)) {
    $data = framework_load_php($phpFile);
}

if (!file_exists($htmlFile)) {
    http_response_code(404);
    exit('404 - Page Not Found');
}

$html = file_get_contents($htmlFile);

$html = framework_render($html, $data);

header('Content-Type: text/html; charset=UTF-8');

echo $html;
