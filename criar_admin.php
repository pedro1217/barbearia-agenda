<?php

// Permite executar este arquivo somente pelo terminal.
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Execute este arquivo pelo terminal.");
}

require_once __DIR__ . "/config/conexao.php";

// Impede cadastrar outro administrador por este script.
$total = $conexao->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

if ((int) $total > 0) {
    exit("Já existe um administrador cadastrado." . PHP_EOL);
}

echo "Seu nome: ";
$nome = trim(fgets(STDIN));

echo "Seu e-mail: ";
$email = trim(fgets(STDIN));

echo "Crie uma senha com pelo menos 8 caracteres: ";
$senha = rtrim(fgets(STDIN), "\r\n");

if ($nome === "" || mb_strlen($nome) > 100) {
    exit("Informe um nome entre 1 e 100 caracteres." . PHP_EOL);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    exit("Informe um e-mail válido com até 150 caracteres." . PHP_EOL);
}

if (strlen($senha) < 8 || strlen($senha) > 72) {
    exit("A senha precisa ter entre 8 e 72 bytes." . PHP_EOL);
}

// Gera o hash da senha para armazenar no banco.
$senhaProtegida = password_hash($senha, PASSWORD_DEFAULT);

$sql = "INSERT INTO usuarios (nome, email, senha)
        VALUES (:nome, :email, :senha)";

$stmt = $conexao->prepare($sql);

$stmt->execute([
    ":nome" => $nome,
    ":email" => $email,
    ":senha" => $senhaProtegida
]);

echo "Administrador cadastrado com sucesso!" . PHP_EOL;