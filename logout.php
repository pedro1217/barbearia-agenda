<?php

require_once __DIR__ . "/includes/proteger.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$token = (string) ($_POST["csrf_token"] ?? "");
$tokenSessao = (string) ($_SESSION["csrf_token"] ?? "");

if (
    $token === "" ||
    $tokenSessao === "" ||
    !hash_equals($tokenSessao, $token)
) {
    http_response_code(403);
    exit("Solicitação inválida.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;
