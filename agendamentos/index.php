<?php

require_once __DIR__ . "/../includes/proteger.php";
require_once __DIR__ . "/../config/conexao.php";

date_default_timezone_set("America/Sao_Paulo");

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = $_SESSION["erro_agendamentos"] ?? "";
unset($_SESSION["erro_agendamentos"]);
$clienteId = "";
$servicoId = "";
$profissionalId = "";
$dataHora = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string) ($_POST["csrf_token"] ?? "");

    $clienteId = filter_input(INPUT_POST, "cliente_id", FILTER_VALIDATE_INT);
    $servicoId = filter_input(INPUT_POST, "servico_id", FILTER_VALIDATE_INT);
    $profissionalId = filter_input(
        INPUT_POST,
        "profissional_id",
        FILTER_VALIDATE_INT
    );

    $dataHora = trim((string) ($_POST["inicio"] ?? ""));

    $inicio = DateTimeImmutable::createFromFormat(
        "!Y-m-d\TH:i",
        $dataHora
    );

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $erro = "Solicitação inválida. Recarregue a página.";
    } elseif (
        !$clienteId || $clienteId < 1 ||
        !$servicoId || $servicoId < 1 ||
        !$profissionalId || $profissionalId < 1
    ) {
        $erro = "Selecione cliente, serviço e profissional.";
    } elseif (
        !$inicio ||
        $inicio->format("Y-m-d\TH:i") !== $dataHora ||
        (int) $inicio->format("Y") > 9999
    ) {
        $erro = "Informe uma data e um horário válidos.";
    } elseif ($inicio <= new DateTimeImmutable()) {
        $erro = "Escolha uma data e um horário no futuro.";
    } else {
        try {
            $conexao->beginTransaction();

            // Organiza as reservas simultâneas do mesmo profissional.
            $stmt = $conexao->prepare(
                "SELECT id, ativo
                 FROM profissionais
                 WHERE id = :id
                 FOR UPDATE"
            );
            $stmt->execute([":id" => $profissionalId]);
            $profissional = $stmt->fetch();

            if (!$profissional || !(int) $profissional["ativo"]) {
                throw new DomainException(
                    "Selecione um profissional ativo."
                );
            }

            $stmt = $conexao->prepare(
                "SELECT id FROM clientes WHERE id = :id
                 LOCK IN SHARE MODE"
            );
            $stmt->execute([":id" => $clienteId]);

            if (!$stmt->fetch()) {
                throw new DomainException("Cliente não encontrado.");
            }

            $stmt = $conexao->prepare(
                "SELECT preco, duracao_minutos, ativo
                 FROM servicos
                 WHERE id = :id
                 LOCK IN SHARE MODE"
            );
            $stmt->execute([":id" => $servicoId]);
            $servico = $stmt->fetch();

            if (!$servico || !(int) $servico["ativo"]) {
                throw new DomainException("Selecione um serviço ativo.");
            }

            $duracao = (int) $servico["duracao_minutos"];

            if ($duracao < 1 || $duracao > 480) {
                throw new DomainException(
                    "Corrija a duração do serviço antes de agendar."
                );
            }

            $fim = $inicio->modify("+{$duracao} minutes");

            if ((int) $fim->format("Y") > 9999) {
                throw new DomainException("Data fora do limite permitido.");
            }

            // Existe conflito quando os períodos se sobrepõem.
            $stmt = $conexao->prepare(
                "SELECT id
                 FROM agendamentos
                 WHERE profissional_id = :profissional
                   AND status <> 'cancelado'
                   AND inicio < :novo_fim
                   AND fim > :novo_inicio
                 LIMIT 1
                 FOR UPDATE"
            );

            $stmt->execute([
                ":profissional" => $profissionalId,
                ":novo_fim" => $fim->format("Y-m-d H:i:s"),
                ":novo_inicio" => $inicio->format("Y-m-d H:i:s")
            ]);

            if ($stmt->fetch()) {
                throw new DomainException(
                    "Esse profissional já possui um atendimento nesse período."
                );
            }

            $stmt = $conexao->prepare(
                "INSERT INTO agendamentos
                    (cliente_id, servico_id, profissional_id, inicio, fim, valor)
                 VALUES
                    (:cliente, :servico, :profissional, :inicio, :fim, :valor)"
            );

            $stmt->execute([
                ":cliente" => $clienteId,
                ":servico" => $servicoId,
                ":profissional" => $profissionalId,
                ":inicio" => $inicio->format("Y-m-d H:i:s"),
                ":fim" => $fim->format("Y-m-d H:i:s"),
                ":valor" => $servico["preco"]
            ]);

            $conexao->commit();

            $_SESSION["mensagem_agendamentos"] =
                "Agendamento realizado com sucesso!";

            header("Location: index.php");
            exit;
        } catch (DomainException $excecao) {
            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            $erro = $excecao->getMessage();
        } catch (PDOException $excecao) {
            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            error_log($excecao->getMessage());
            $erro = "Não foi possível agendar. Tente novamente.";
        }
    }
}

$mensagem = $_SESSION["mensagem_agendamentos"] ?? "";
unset($_SESSION["mensagem_agendamentos"]);

$clientes = $conexao->query(
    "SELECT id, nome FROM clientes ORDER BY nome, id"
)->fetchAll();

$servicos = $conexao->query(
    "SELECT id, nome, preco, duracao_minutos
     FROM servicos WHERE ativo = 1 ORDER BY nome, id"
)->fetchAll();

$profissionais = $conexao->query(
    "SELECT id, nome FROM profissionais
     WHERE ativo = 1 ORDER BY nome, id"
)->fetchAll();

$podeAgendar = !empty($clientes)
    && !empty($servicos)
    && !empty($profissionais);

$dataFiltro = trim((string) ($_GET["data"] ?? ""));
$statusFiltro = (string) ($_GET["status"] ?? "");

$statusPermitidos = ["agendado", "concluido", "cancelado"];

if (
    $statusFiltro !== "" &&
    !in_array($statusFiltro, $statusPermitidos, true)
) {
    $erro = "Status de filtro inválido.";
    $statusFiltro = "";
}

$dataValida = null;

if ($dataFiltro !== "") {
    $dataValida = DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $dataFiltro
    );

    if (
        !$dataValida ||
        $dataValida->format("Y-m-d") !== $dataFiltro ||
        $dataFiltro < "1000-01-01" ||
        $dataFiltro >= "9999-12-31"
    ) {
        $erro = "Informe uma data válida para filtrar.";
        $dataFiltro = "";
        $dataValida = null;
    }
}

$sql = "SELECT a.id, a.inicio, a.fim, a.valor, a.status,
               c.nome AS cliente,
               s.nome AS servico,
               p.nome AS profissional
        FROM agendamentos a
        INNER JOIN clientes c ON c.id = a.cliente_id
        INNER JOIN servicos s ON s.id = a.servico_id
        INNER JOIN profissionais p ON p.id = a.profissional_id
        WHERE 1 = 1";

$parametros = [];

if ($dataValida !== null) {
    $sql .= " AND a.inicio >= :inicio_dia
              AND a.inicio < :fim_dia";

    $parametros[":inicio_dia"] = $dataValida->format("Y-m-d H:i:s");

    $parametros[":fim_dia"] = $dataValida
        ->modify("+1 day")
        ->format("Y-m-d H:i:s");
}

if ($statusFiltro !== "") {
    $sql .= " AND a.status = :status";
    $parametros[":status"] = $statusFiltro;
}

$sql .= " ORDER BY a.inicio DESC, a.id DESC LIMIT 50";

$stmt = $conexao->prepare($sql);
$stmt->execute($parametros);

$agendamentos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agendamentos | Barbearia Agenda</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main class="container">
        <a href="../index.php" class="link-voltar">← Voltar ao painel</a>

        <h1>Agendamentos</h1>
        <p class="subtitulo">
            Escolha o cliente, o serviço e o profissional.
            Horários de Brasília.
        </p>

        <?php if ($erro !== ""): ?>
            <div class="alerta" role="alert">
                <?= escapar($erro) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem !== ""): ?>
            <div class="sucesso" role="status">
                <?= escapar($mensagem) ?>
            </div>
        <?php endif; ?>

        <section class="bloco">
            <h2>Novo agendamento</h2>

            <?php if (!$podeAgendar): ?>
                <p>
                    Para agendar, cadastre pelo menos um cliente,
                    um serviço ativo e um profissional ativo.
                </p>
            <?php else: ?>
                <form method="POST">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapar($_SESSION["csrf_token"]) ?>"
                    >

                    <label for="cliente">Cliente</label>
                    <select id="cliente" name="cliente_id" required>
                        <option value="">Selecione um cliente</option>

                        <?php foreach ($clientes as $cliente): ?>
                            <option
                                value="<?= (int) $cliente["id"] ?>"
                                <?= (int) $clienteId === (int) $cliente["id"]
                                    ? "selected" : "" ?>
                            >
                                <?= escapar($cliente["nome"]) ?>
                                (#<?= (int) $cliente["id"] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="servico">Serviço</label>
                    <select id="servico" name="servico_id" required>
                        <option value="">Selecione um serviço</option>

                        <?php foreach ($servicos as $servico): ?>
                            <option
                                value="<?= (int) $servico["id"] ?>"
                                <?= (int) $servicoId === (int) $servico["id"]
                                    ? "selected" : "" ?>
                            >
                                <?= escapar($servico["nome"]) ?> —
                                R$ <?= number_format(
                                    (float) $servico["preco"], 2, ",", "."
                                ) ?> —
                                <?= (int) $servico["duracao_minutos"] ?> min
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="profissional">Profissional</label>
                    <select id="profissional" name="profissional_id" required>
                        <option value="">Selecione um profissional</option>

                        <?php foreach ($profissionais as $profissional): ?>
                            <option
                                value="<?= (int) $profissional["id"] ?>"
                                <?= (int) $profissionalId === (int) $profissional["id"]
                                    ? "selected" : "" ?>
                            >
                                <?= escapar($profissional["nome"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="inicio">Data e horário de início</label>
                    <input
                        type="datetime-local"
                        id="inicio"
                        name="inicio"
                        value="<?= escapar($dataHora) ?>"
                        min="<?= date("Y-m-d\TH:i") ?>"
                        required
                    >

                    <button type="submit">Agendar atendimento</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="bloco">
            <h2>Agendamentos — até 50, por data decrescente</h2>
            <form action="index.php" method="GET" class="filtros-agenda">
    <div>
        <label for="data-filtro">Data do atendimento</label>
        <input
            type="date"
            id="data-filtro"
            name="data"
            value="<?= escapar($dataFiltro) ?>"
        >
    </div>

    <div>
        <label for="status-filtro">Status</label>
        <select id="status-filtro" name="status">
            <option value="">Todos</option>

            <option
                value="agendado"
                <?= $statusFiltro === "agendado" ? "selected" : "" ?>
            >
                Agendado
            </option>

            <option
                value="concluido"
                <?= $statusFiltro === "concluido" ? "selected" : "" ?>
            >
                Concluído
            </option>

            <option
                value="cancelado"
                <?= $statusFiltro === "cancelado" ? "selected" : "" ?>
            >
                Cancelado
            </option>
        </select>
    </div>

    <button type="submit">Filtrar</button>

    <a href="index.php" class="limpar-filtros">
        Limpar filtros
    </a>
</form>
            <?php if (empty($agendamentos)): ?>
                <p>Nenhum agendamento encontrado para esta consulta.</p>
            <?php else: ?>
                <div class="tabela-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Início</th>
                                <th scope="col">Término</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Serviço</th>
                                <th scope="col">Profissional</th>
                                <th scope="col">Valor</th>
                                <th scope="col">Status</th>
                                <th scope="col">ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agendamentos as $agendamento): ?>
                                <tr>
                                    <td>
                                        <?= date(
                                            "d/m/Y H:i",
                                            strtotime($agendamento["inicio"])
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= date(
                                            "d/m/Y H:i",
                                            strtotime($agendamento["fim"])
                                        ) ?>
                                    </td>
                                    <td><?= escapar($agendamento["cliente"]) ?></td>
                                    <td><?= escapar($agendamento["servico"]) ?></td>
                                    <td><?= escapar($agendamento["profissional"]) ?></td>
                                    <td>
                                        R$ <?= number_format(
                                            (float) $agendamento["valor"],
                                            2, ",", "."
                                        ) ?>
                                    </td>
                                    <td>
    <?php
    $rotulos = [
        "agendado" => "Agendado",
        "concluido" => "Concluído",
        "cancelado" => "Cancelado"
    ];

    $statusAtual = $agendamento["status"];
    ?>

    <span class="status status-<?= escapar($statusAtual) ?>">
        <?= escapar($rotulos[$statusAtual] ?? $statusAtual) ?>
    </span>
</td>

<td>
    <?php if ($statusAtual === "agendado"): ?>
        <div class="acoes">
            <?php if (strtotime($agendamento["fim"]) <= time()): ?>
                <form
                    action="alterar_status.php"
                    method="POST"
                    onsubmit="return confirm('Confirmar que este atendimento foi realizado?');"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapar($_SESSION["csrf_token"]) ?>"
                    >
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $agendamento["id"] ?>"
                    >
                    <input type="hidden" name="status" value="concluido">

                    <button type="submit" class="acao-concluir">
                        Concluir
                    </button>
                </form>
            <?php else: ?>
                <span class="texto-discreto">
                    Conclusão após o término
                </span>
            <?php endif; ?>

            <form
                action="alterar_status.php"
                method="POST"
                onsubmit="return confirm('Cancelar este agendamento e liberar o horário?');"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar($_SESSION["csrf_token"]) ?>"
                >
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $agendamento["id"] ?>"
                >
                <input type="hidden" name="status" value="cancelado">

                <button type="submit" class="acao-excluir">
                    Cancelar
                </button>
            </form>
        </div>
    <?php else: ?>
        <span class="texto-discreto">Finalizado</span>
    <?php endif; ?>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>