<?php


define('DB_HOST', 'localhost');
define('DB_USER', 'root');      
define('DB_PASS', '');          
define('DB_NAME', 'sitewatcher');

define('BASE_URL', 'http://localhost/sitewatcher');   
define('BASE_PATH', __DIR__);                        
define('LOG_DIR',   BASE_PATH . '/logs');
define('SS_DIR',    BASE_PATH . '/screenshots');
define('LOG_RETENTION_DAYS', 30);


define('ANOMALY_KEYWORDS', [

    'casino', 'poker','gamble', 'jackpot',
    'payday loan', 'fast cash', 'quick loan', 'bad credit loan', 'instant loan',
    'bitcoin doubler', 'crypto giveaway', 'elon musk bitcoin',
    'click here to win', 'you have been selected', 'free iphone', 'free stuff', 'free car'
]);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('<div class="alert error">Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>');
        }
    }
    return $pdo;
}

function getSetting(string $key, string $default = ''): string {
    try {
        $st = db()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting(string $key, string $value): void {
    db()->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?')
        ->execute([$key, $value, $value]);
}

session_start();

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
