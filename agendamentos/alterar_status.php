<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

date_default_timezone_set("America/Sao_Paulo");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$token = (string) ($_POST["csrf_token"] ?? "");

if (!hash_equals($_SESSION["csrf_token"], $token)) {
    http_response_code(403);
    exit("Solicitação inválida.");
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
$status = (string) ($_POST["status"] ?? "");

if (!$id || $id < 1) {
    http_response_code(400);
    exit("Agendamento inválido.");
}

if (!in_array($status, ["concluido", "cancelado"], true)) {
    http_response_code(400);
    exit("Status inválido.");
}

try {
    // Só permite mudar atendimentos que ainda estão agendados.
    $sql = "UPDATE agendamentos
            SET status = :status
            WHERE id = :id
              AND status = 'agendado'";

    $parametros = [
        ":status" => $status,
        ":id" => $id
    ];

    // Só conclui quando o horário de término já passou.
    if ($status === "concluido") {
        $sql .= " AND fim <= :agora";
        $parametros[":agora"] = date("Y-m-d H:i:s");
    }

    $stmt = $conexao->prepare($sql);
    $stmt->execute($parametros);

    if ($stmt->rowCount() === 1) {
        $_SESSION["mensagem_agendamentos"] =
            $status === "concluido"
                ? "Atendimento concluído com sucesso!"
                : "Agendamento cancelado. O horário foi liberado.";
    } else {
        $_SESSION["erro_agendamentos"] =
            "Não foi possível alterar: o registro pode não existir, "
            . "já estar finalizado ou, para concluir, ainda não ter "
            . "atingido o horário de término.";
    }
} catch (PDOException $excecao) {
    error_log($excecao->getMessage());

    $_SESSION["erro_agendamentos"] =
        "Não foi possível alterar o status. Tente novamente.";
}

header("Location: index.php");
exit;