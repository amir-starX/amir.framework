
<?php

function framework_load_php($file)
{
    $code = file_get_contents($file);

    $code = preg_replace(
        '/database\.([a-zA-Z_][a-zA-Z0-9_]*)\.([0-9]+)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+?);/m',
        'framework_database_set("$1", $2, "$3", $4);',
        $code
    );

    $code = preg_replace_callback(
        '/database\.([a-zA-Z_][a-zA-Z0-9_]*)\.([0-9]+)\.([a-zA-Z_][a-zA-Z0-9_]*)/',
        function ($m) {
            return 'framework_database_get("' . $m[1] . '", ' . $m[2] . ', "' . $m[3] . '")';
        },
        $code
    );

    $temp = tempnam(sys_get_temp_dir(), 'amir_') . '.php';

    file_put_contents($temp, $code);

    $loader = function ($temp) {

        include $temp;

        $vars = get_defined_vars();

        unset(
            $vars['temp'],
            $vars['vars']
        );

        return $vars;
    };

    $result = $loader($temp);

    unlink($temp);

    return $result;
}

function framework_render($html, &$data)
{
    $html = preg_replace_callback(
        '/\{\+([a-zA-Z0-9_\-\/]+)\+\}/',
        function ($match) use (&$data) {

            $name = $match[1];

            $phpFile = PHP_PATH . '/' . $name . '.php';
            $htmlFile = HTML_PATH . '/' . $name . '.html';

            if (file_exists($phpFile)) {
                $newData = framework_load_php($phpFile);
                $data = array_merge($data, $newData);
            }

            if (file_exists($htmlFile)) {
                $content = file_get_contents($htmlFile);
                return framework_render($content, $data);
            }

            return '';
        },
        $html
    );

    $html = preg_replace_callback(
        '/\{\=([a-zA-Z_][a-zA-Z0-9_]*)=\}/',
        function ($match) use (&$data) {

            $name = $match[1];

            if (!array_key_exists($name, $data)) {
                return '';
            }

            return htmlspecialchars(
                (string)$data[$name],
                ENT_QUOTES,
                'UTF-8'
            );
        },
        $html
    );

    $html = preg_replace_callback(
        '/(<a\b[^>]*href=["\'])\{\+\+([a-zA-Z0-9_\-\/]+)\+\+\}(["\'])/i',
        function ($match) {

            return $match[1]
                . '?page='
                . $match[2]
                . $match[3];
        },
        $html
    );

    return $html;
}

function framework_db()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    require PHP_PATH . '/config/config.php';

    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

    $pdo = new PDO(
        $dsn,
        $db_user,
        $db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    return $pdo;
}

function framework_database_get($table, $id, $column)
{
    if (
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)
    ) {
        return null;
    }

    $pdo = framework_db();

    $sql = "SELECT `$column`
            FROM `$table`
            WHERE id = :id
            LIMIT 1";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id' => $id
    ]);

    return $stmt->fetchColumn();
}

function framework_database_set($table, $id, $column, $value)
{
    if (
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)
    ) {
        return false;
    }

    $pdo = framework_db();

    $sql = "UPDATE `$table`
            SET `$column` = :value
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    return $stmt->execute([
        ':id' => $id,
        ':value' => $value
    ]);
}

function input($name, $default = '')
{
    return $_POST[$name] ?? $default;
}