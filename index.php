<?php

require_once __DIR__ . "/includes/proteger.php";
require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Sao_Paulo");

function escapar($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$hoje = new DateTimeImmutable("today");
$amanha = $hoje->modify("+1 day");

$stmt = $conexao->prepare(
    "SELECT a.id, a.inicio, a.fim, a.valor, a.status,
            c.nome AS cliente,
            s.nome AS servico,
            p.nome AS profissional
     FROM agendamentos a
     INNER JOIN clientes c ON c.id = a.cliente_id
     INNER JOIN servicos s ON s.id = a.servico_id
     INNER JOIN profissionais p ON p.id = a.profissional_id
     WHERE a.inicio >= :inicio_dia
       AND a.inicio < :fim_dia
     ORDER BY a.inicio, a.id"
);

$stmt->execute([
    ":inicio_dia" => $hoje->format("Y-m-d H:i:s"),
    ":fim_dia" => $amanha->format("Y-m-d H:i:s")
]);

$agendamentos = $stmt->fetchAll();

$totais = [
    "agendado" => 0,
    "concluido" => 0,
    "cancelado" => 0
];

// Soma em centavos para evitar imprecisão com valores monetários.
$valorConcluidoCentavos = 0;

foreach ($agendamentos as $agendamento) {
    $status = $agendamento["status"];

    if (isset($totais[$status])) {
        $totais[$status]++;
    }

    if ($status === "concluido") {
        $partes = explode(".", (string) $agendamento["valor"]);

        $reais = (int) $partes[0];
        $centavos = (int) str_pad($partes[1] ?? "", 2, "0");

        $valorConcluidoCentavos += ($reais * 100) + $centavos;
    }
}

$valorFormatado = number_format(
    intdiv($valorConcluidoCentavos, 100),
    0,
    ",",
    "."
) . "," . str_pad(
    (string) ($valorConcluidoCentavos % 100),
    2,
    "0",
    STR_PAD_LEFT
);

$rotulos = [
    "agendado" => "Agendado",
    "concluido" => "Concluído",
    "cancelado" => "Cancelado"
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel | Barbearia Agenda</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
    <main class="container painel">
        <header class="cabecalho-painel">
            <div>
                <span class="marca">BARBEARIA AGENDA</span>

                <h1>
                    Olá, <?= escapar($_SESSION["usuario_nome"]) ?>!
                </h1>

                <p class="subtitulo">
                    Acompanhe os atendimentos de
                    <?= $hoje->format("d/m/Y") ?>.
                    Horário de Brasília.
                </p>
            </div>

            <form action="logout.php" method="POST" class="form-sair">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar($_SESSION["csrf_token"]) ?>"
                >

                <button type="submit" class="botao-sair">
                    Sair do sistema
                </button>
            </form>
        </header>

        <nav class="menu-painel" aria-label="Menu principal">
            <a href="agendamentos/index.php" class="botao-link">
                Agendamentos
            </a>

            <a href="clientes/index.php" class="botao-link">
                Clientes
            </a>

            <a href="servicos/index.php" class="botao-link">
                Serviços
            </a>

            <a href="profissionais/index.php" class="botao-link">
                Profissionais
            </a>
        </nav>

        <section class="resumo-painel" aria-label="Resumo do dia">
            <article class="cartao-resumo">
                <h2>Total do dia</h2>
                <strong><?= count($agendamentos) ?></strong>
                <p>Inclui todos os status</p>
            </article>

            <article class="cartao-resumo">
                <h2>Agendados</h2>
                <strong><?= $totais["agendado"] ?></strong>
                <p>Ainda não finalizados</p>
            </article>

            <article class="cartao-resumo">
                <h2>Concluídos</h2>
                <strong><?= $totais["concluido"] ?></strong>
                <p>Atendimentos realizados</p>
            </article>

            <article class="cartao-resumo">
                <h2>Cancelados</h2>
                <strong><?= $totais["cancelado"] ?></strong>
                <p>Cancelamentos da agenda do dia</p>
            </article>
        </section>

        <section class="bloco valor-dia">
            <div>
                <h2>Valor dos serviços concluídos</h2>
                <p>
                    Considera atendimentos iniciados hoje.
                    Não representa confirmação de pagamento.
                </p>
            </div>

            <strong>R$ <?= escapar($valorFormatado) ?></strong>
        </section>

        <section class="bloco">
            <div class="titulo-agenda">
                <h2>Agenda de hoje</h2>

                <a href="agendamentos/index.php" class="link-voltar">
                    Gerenciar agendamentos →
                </a>
            </div>

            <?php if (empty($agendamentos)): ?>
                <div class="estado-vazio">
                    <p>Nenhum atendimento marcado para hoje.</p>

                    <a href="agendamentos/index.php" class="botao-link">
                        Agendar atendimento
                    </a>
                </div>
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
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($agendamentos as $agendamento): ?>
                                <tr>
                                    <td>
                                        <?= date(
                                            "H:i",
                                            strtotime($agendamento["inicio"])
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= date(
                                            "d/m H:i",
                                            strtotime($agendamento["fim"])
                                        ) ?>
                                    </td>

                                    <td><?= escapar($agendamento["cliente"]) ?></td>
                                    <td><?= escapar($agendamento["servico"]) ?></td>
                                    <td><?= escapar($agendamento["profissional"]) ?></td>

                                    <td>
                                        R$ <?= number_format(
                                            (float) $agendamento["valor"],
                                            2,
                                            ",",
                                            "."
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status status-<?= escapar($agendamento["status"]) ?>">
                                            <?= escapar(
                                                $rotulos[$agendamento["status"]]
                                                ?? $agendamento["status"]
                                            ) ?>
                                        </span>
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