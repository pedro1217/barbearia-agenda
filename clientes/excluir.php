<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

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

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Cliente inválido.");
}

try {
    $stmt = $conexao->prepare(
        "DELETE FROM clientes WHERE id = :id"
    );

    $stmt->execute([":id" => $id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION["mensagem"] = "Cliente excluído com sucesso!";
    } else {
        $_SESSION["erro_clientes"] = "Cliente não encontrado.";
    }
} catch (PDOException $erroBanco) {
    // MySQL/MariaDB: erro 1451 significa que existem registros vinculados.
    if ((int) ($erroBanco->errorInfo[1] ?? 0) === 1451) {
        $_SESSION["erro_clientes"] =
            "Este cliente possui agendamentos e não pode ser excluído.";
    } else {
        error_log($erroBanco->getMessage());

        $_SESSION["erro_clientes"] =
            "Não foi possível excluir o cliente. Tente novamente.";
    }
}

header("Location: index.php");
exit;