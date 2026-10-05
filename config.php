<?php
const DB_PATH = __DIR__ . '/data/fly.sqlite';
const CLIENT_FILE = __DIR__ . '/downloads/fly-client-1.0.2.jar';
const SITE_NAME = 'Fly Client';
const ADMIN_1 = 'FlyOwner';
const ADMIN_2 = 'FlyDev';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}
function setup_db(): void {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', plan TEXT DEFAULT 'none', plan_expires INTEGER DEFAULT 0, created_at INTEGER NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS keys (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, days INTEGER NOT NULL, created_by INTEGER, used_by INTEGER, used_at INTEGER, created_at INTEGER NOT NULL, FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(used_by) REFERENCES users(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, type TEXT NOT NULL, name TEXT, age INTEGER, youtube TEXT, tiktok TEXT, views TEXT, subscribers INTEGER, experience TEXT, months INTEGER, discord TEXT, vk TEXT, reason TEXT, status TEXT DEFAULT 'pending', created_at INTEGER NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)");
    $admins = [
      [ADMIN_1, 'owner@flyclient.local', 'FlyOwner-ChangeMe!2026'],
      [ADMIN_2, 'dev@flyclient.local', 'FlyDev-ChangeMe!2026']
    ];
    foreach ($admins as [$u,$e,$p]) {
        $stmt=$pdo->prepare('SELECT id FROM users WHERE username=?'); $stmt->execute([$u]);
        if (!$stmt->fetch()) {
            $stmt=$pdo->prepare('INSERT INTO users(username,email,password_hash,role,created_at) VALUES(?,?,?,?,?)');
            $stmt->execute([$u,$e,password_hash($p,PASSWORD_DEFAULT),'admin',time()]);
        }
    }
}
setup_db();
session_start();
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function check_csrf(): void { if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) die('Invalid request'); }
function user(): ?array { if(empty($_SESSION['uid'])) return null; $s=db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); return $s->fetch() ?: null; }
function require_login(): array { $u=user(); if(!$u){header('Location: login.php'); exit;} return $u; }
function require_admin(): array { $u=require_login(); if($u['role']!=='admin'){http_response_code(403); exit('Forbidden');} return $u; }
function flash($msg): void { $_SESSION['flash']=$msg; }
function getflash(): ?string { $m=$_SESSION['flash']??null; unset($_SESSION['flash']); return $m; }
function prices(string $currency='RUB'): array {
  $base=['30'=>200,'180'=>400,'365'=>500,'forever'=>700];
  $rates=['RUB'=>1,'EUR'=>0.0105,'USD'=>0.0115,'GBP'=>0.0091];
  $symbols=['RUB'=>'₽','EUR'=>'€','USD'=>'$','GBP'=>'£'];
  $r=$rates[$currency]??1; $s=$symbols[$currency]??'₽';
  $out=[]; foreach($base as $k=>$v) $out[$k]=['value'=>round($v*$r,2),'symbol'=>$s]; return $out;
}
function plan_name(int $days): string { return $days===0?'Lifetime':($days===365?'1 year':$days.' days'); }
function active_plan(array $u): bool { return $u['plan']==='forever' || (int)$u['plan_expires']>time(); }
