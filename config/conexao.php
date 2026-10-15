
<?php

$config = require __DIR__ . "/config.local.php";

$servidor = $config["servidor"];
$banco = $config["banco"];
$usuario = $config["usuario"];
$senha = $config["senha"];

try {
    $conexao = new PDO(
        "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $erro) {
    error_log($erro->getMessage());

    http_response_code(500);
    exit("Não foi possível conectar ao banco de dados.");
}
