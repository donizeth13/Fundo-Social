<?php
/**
 * Consultas do relatório de movimentações de estoque por produto.
 *
 * Cada produto é apresentado com quatro indicadores do período consultado:
 * saldo anterior, entradas, saídas e saldo atual.
 */

/** Limites usados quando o filtro de data é deixado em branco. */
const RELATORIO_DATA_INICIO_SEM_LIMITE = '1970-01-01 00:00:00';
const RELATORIO_DATA_FIM_SEM_LIMITE = '9999-12-31 23:59:59';

/**
 * Normaliza o período informado no formulário.
 * Datas em branco significam "sem limite" (todo o período).
 *
 * @return array{data_inicio: string, data_fim: string, inicio_sql: string, fim_sql: string, tem_limite_inicial: bool, rotulo: string, aviso: string}
 */
function normalizarPeriodoRelatorio($dataInicio, $dataFim) {
    $dataInicio = is_string($dataInicio) ? trim($dataInicio) : '';
    $dataFim = is_string($dataFim) ? trim($dataFim) : '';
    $avisos = [];

    if ($dataInicio !== '' && !isValidDateMySql($dataInicio)) {
        $dataInicio = '';
        $avisos[] = 'Data inicial inválida: o relatório foi gerado sem limite inicial.';
    }
    if ($dataFim !== '' && !isValidDateMySql($dataFim)) {
        $dataFim = '';
        $avisos[] = 'Data final inválida: o relatório foi gerado sem limite final.';
    }

    if ($dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim) {
        $dataTrocada = $dataInicio;
        $dataInicio = $dataFim;
        $dataFim = $dataTrocada;
        $avisos[] = 'As datas estavam invertidas e foram ajustadas para ' . formatDateBr($dataInicio) . ' a ' . formatDateBr($dataFim) . '.';
    }

    return [
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'inicio_sql' => $dataInicio === '' ? RELATORIO_DATA_INICIO_SEM_LIMITE : $dataInicio . ' 00:00:00',
        'fim_sql' => $dataFim === '' ? RELATORIO_DATA_FIM_SEM_LIMITE : date('Y-m-d H:i:s', strtotime($dataFim . ' +1 day')),
        'tem_limite_inicial' => $dataInicio !== '',
        'rotulo' => montarRotuloPeriodoRelatorio($dataInicio, $dataFim),
        'aviso' => implode(' ', $avisos),
    ];
}

/**
 * Texto do período usado nos títulos, na impressão e na exportação.
 */
function montarRotuloPeriodoRelatorio($dataInicio, $dataFim) {
    if ($dataInicio === '' && $dataFim === '') {
        return 'Todo o período';
    }
    if ($dataInicio === '') {
        return 'Até ' . formatDateBr($dataFim);
    }
    if ($dataFim === '') {
        return 'A partir de ' . formatDateBr($dataInicio);
    }
    return formatDateBr($dataInicio) . ' a ' . formatDateBr($dataFim);
}

/**
 * Primeiro dia do mês corrente, usado como filtro inicial padrão.
 */
function getDataInicioPadraoRelatorio() {
    return date('Y-m-01');
}

/**
 * Resumo por produto no período informado.
 *
 * O saldo anterior é o saldo gravado na última movimentação anterior ao período;
 * produtos sem histórico anterior começam em zero. O saldo atual é o saldo no fim
 * do período consultado (saldo anterior + entradas - saídas).
 */
function getResumoMovimentacoesPorProduto($inicioSql, $fimSql, $itemId = 0) {
    global $pdo;

    $sql = "SELECT i.id,
                   i.codigo,
                   i.nome,
                   i.quantidade AS saldo_cadastrado,
                   COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'entrada' THEN m.quantidade_movimentada ELSE 0 END), 0) AS entradas,
                   COALESCE(SUM(CASE WHEN m.tipo_movimentacao = 'saida' THEN m.quantidade_movimentada ELSE 0 END), 0) AS saidas,
                   COUNT(m.id) AS movimentos,
                   COALESCE((
                       SELECT m2.quantidade_final
                       FROM itens_guias_estoque m2
                       WHERE m2.item_id = i.id
                         AND m2.data_movimentacao < :inicio_saldo
                       ORDER BY m2.data_movimentacao DESC, m2.id DESC
                       LIMIT 1
                   ), 0) AS saldo_anterior
            FROM itens i
            LEFT JOIN itens_guias_estoque m
                   ON m.item_id = i.id
                  AND m.data_movimentacao >= :inicio_periodo
                  AND m.data_movimentacao < :fim_periodo";

    $parametros = [
        'inicio_saldo' => $inicioSql,
        'inicio_periodo' => $inicioSql,
        'fim_periodo' => $fimSql,
    ];

    if ((int)$itemId > 0) {
        $sql .= " WHERE i.id = :item_id";
        $parametros['item_id'] = (int)$itemId;
    }

    $sql .= " GROUP BY i.id, i.codigo, i.nome, i.quantidade";

    if ((int)$itemId <= 0) {
        $sql .= " HAVING entradas > 0 OR saidas > 0 OR saldo_anterior <> 0 OR saldo_cadastrado <> 0";
    }

    $sql .= " ORDER BY i.nome, i.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);

    return prepararLinhasResumo($stmt->fetchAll());
}

/**
 * Converte os valores vindos do banco e calcula o saldo atual de cada produto.
 */
function prepararLinhasResumo(array $linhas) {
    $preparadas = [];

    foreach ($linhas as $linha) {
        $saldoAnterior = (int)$linha['saldo_anterior'];
        $entradas = (int)$linha['entradas'];
        $saidas = (int)$linha['saidas'];
        $saldoCadastrado = (int)$linha['saldo_cadastrado'];
        $saldoAtual = $saldoAnterior + $entradas - $saidas;

        $preparadas[] = [
            'id' => (int)$linha['id'],
            'codigo' => (string)$linha['codigo'],
            'nome' => (string)$linha['nome'],
            'saldo_anterior' => $saldoAnterior,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo_atual' => $saldoAtual,
            'saldo_cadastrado' => $saldoCadastrado,
            'divergencia' => $saldoAtual - $saldoCadastrado,
            'movimentos' => (int)$linha['movimentos'],
        ];
    }

    return $preparadas;
}

/**
 * Soma as colunas do resumo para a linha de totais.
 *
 * @return array{saldo_anterior: int, entradas: int, saidas: int, saldo_atual: int, movimentos: int, produtos: int}
 */
function somarResumoMovimentacoes(array $linhas) {
    $totais = [
        'saldo_anterior' => 0,
        'entradas' => 0,
        'saidas' => 0,
        'saldo_atual' => 0,
        'movimentos' => 0,
        'produtos' => count($linhas),
    ];

    foreach ($linhas as $linha) {
        $totais['saldo_anterior'] += $linha['saldo_anterior'];
        $totais['entradas'] += $linha['entradas'];
        $totais['saidas'] += $linha['saidas'];
        $totais['saldo_atual'] += $linha['saldo_atual'];
        $totais['movimentos'] += $linha['movimentos'];
    }

    return $totais;
}

/**
 * Produtos cujo histórico de movimentações não fecha com o saldo cadastrado.
 */
function filtrarDivergenciasResumo(array $linhas) {
    $divergentes = [];

    foreach ($linhas as $linha) {
        if ($linha['divergencia'] !== 0) {
            $divergentes[] = $linha;
        }
    }

    return $divergentes;
}

/**
 * Movimentações de um produto dentro do período, em ordem cronológica.
 */
function getMovimentacoesItemPeriodo($itemId, $inicioSql, $fimSql, $limite = 200) {
    global $pdo;

    $limite = max(1, (int)$limite);
    $sql = "SELECT m.id,
                   m.data_movimentacao,
                   m.tipo_movimentacao,
                   m.quantidade_inicial,
                   m.quantidade_movimentada,
                   m.quantidade_final,
                   g.numero AS guia_numero,
                   b.nome AS beneficiario_nome
            FROM itens_guias_estoque m
            LEFT JOIN guias g ON g.id = m.guia_id
            LEFT JOIN beneficiarios b ON b.id = g.beneficiario_id
            WHERE m.item_id = :item_id
              AND m.data_movimentacao >= :inicio_periodo
              AND m.data_movimentacao < :fim_periodo
            ORDER BY m.data_movimentacao, m.id
            LIMIT $limite";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'item_id' => (int)$itemId,
        'inicio_periodo' => $inicioSql,
        'fim_periodo' => $fimSql,
    ]);

    return $stmt->fetchAll();
}

/**
 * Linhas do resumo prontas para exportação em CSV.
 */
function montarLinhasCsvResumo(array $linhas) {
    $registros = [];

    foreach ($linhas as $linha) {
        $registros[] = [
            'Código' => $linha['codigo'],
            'Produto' => $linha['nome'],
            'Saldo anterior' => $linha['saldo_anterior'],
            'Entradas' => $linha['entradas'],
            'Saídas' => $linha['saidas'],
            'Saldo atual' => $linha['saldo_atual'],
            'Movimentos' => $linha['movimentos'],
        ];
    }

    return $registros;
}
