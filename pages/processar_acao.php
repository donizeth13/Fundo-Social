<?php
/**
 * Processador de ações administrativas em JSON ou formulário
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();

$headers = function_exists('getallheaders') ? getallheaders() : [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($headers['Content-Type'] ?? '');
if (strpos($contentType, 'application/json') === 0) {
    $input = json_decode(file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
} else {
    $input = $_POST;
}

$action = $input['action'] ?? $_GET['acao'] ?? '';
$id = (int)($input['id'] ?? $_GET['id'] ?? 0);
$response = ['status' => 'error', 'message' => 'Ação desconhecida.'];

if (!userCan('admin') && !userCan('gerente')) {
    http_response_code(403);
    $response = ['status' => 'error', 'message' => 'Acesso negado.'];
} else {
    switch ($action) {
        case 'excluir_item':
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT nome, quantidade FROM itens WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) {
                    throw new RuntimeException('Item não encontrado.');
                }
                $stmt = $pdo->prepare("DELETE FROM itens WHERE id = ?");
                $stmt->execute([$id]);
                recordAuditEvent('excluir_item', 'itens', $id, null, ['quantidade_final' => (int)$item['quantidade']]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Item excluído com sucesso.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code($e instanceof PDOException ? 409 : 404);
                $response = ['status' => 'error', 'message' => $e instanceof PDOException ? 'Não é possível excluir um item com movimentações ou guias vinculadas.' : 'Item não encontrado.'];
            }
            break;

        case 'fechar_guia':
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE guias SET status = 'encerrada' WHERE id = ? AND status = 'ativo'");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('Guia não encontrada ou já encerrada.');
                }
                recordAuditEvent('encerrar_guia', 'guias', $id);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Guia encerrada com sucesso.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(404);
                $response = ['status' => 'error', 'message' => $e->getMessage()];
            }
            break;

        case 'excluir_beneficiario':
            try {
                $stmt = $pdo->prepare("DELETE FROM beneficiarios WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    recordAuditEvent('excluir_beneficiario', 'beneficiarios', $id);
                    $response = ['status' => 'success', 'message' => 'Beneficiário excluído com sucesso.'];
                } else {
                    http_response_code(404);
                    $response = ['status' => 'error', 'message' => 'Beneficiário não encontrado.'];
                }
            } catch (PDOException $e) {
                http_response_code(409);
                $response = ['status' => 'error', 'message' => 'Não é possível excluir um beneficiário com guias vinculadas.'];
            }
            break;

        case 'atualizar_item':
            $quantidade = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_INT);
            if ($id <= 0 || $quantidade === false || $quantidade < 0) {
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Informe uma quantidade válida.'];
                break;
            }
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item) {
                    throw new RuntimeException('Item não encontrado.');
                }
                $quantidadeAnterior = (int)$item['quantidade'];
                if ($quantidade < $quantidadeAnterior) {
                    $pdo->rollBack();
                    http_response_code(409);
                    $response = ['status' => 'error', 'message' => 'Reduções de estoque devem ser registradas por uma guia de retirada.'];
                    break;
                }
                $stmt = $pdo->prepare("UPDATE itens SET quantidade = ? WHERE id = ?");
                $stmt->execute([$quantidade, $id]);
                if ($quantidade > $quantidadeAnterior) {
                    recordStockMovement($id, $quantidadeAnterior, $quantidade, 'entrada');
                }
                recordAuditEvent('ajuste_estoque', 'itens', $id, ['quantidade' => $quantidadeAnterior], ['quantidade' => $quantidade]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Entrada de estoque registrada.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Não foi possível atualizar o estoque.'];
            }
            break;

        case 'atualizar_guia':
            $quantidadeNova = filter_var($input['quantidade'] ?? null, FILTER_VALIDATE_INT);
            if ($id <= 0 || $quantidadeNova === false || $quantidadeNova <= 0) {
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Informe uma quantidade válida.'];
                break;
            }
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT item_id, quantidade, status FROM guias WHERE id = ? FOR UPDATE");
                $stmt->execute([$id]);
                $guia = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$guia || $guia['status'] !== 'ativo') {
                    throw new RuntimeException('Somente guias ativas podem ser ajustadas.');
                }
                $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
                $stmt->execute([$guia['item_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                $quantidadeAnterior = (int)$guia['quantidade'];
                $saldoAtual = (int)$item['quantidade'];
                $diferenca = $quantidadeNova - $quantidadeAnterior;
                if ($diferenca > 0 && $saldoAtual < $diferenca) {
                    throw new RuntimeException('Estoque insuficiente para aumentar a quantidade da guia.');
                }
                if ($diferenca !== 0) {
                    $saldoNovo = $saldoAtual - $diferenca;
                    $stmt = $pdo->prepare("UPDATE itens SET quantidade = ? WHERE id = ?");
                    $stmt->execute([$saldoNovo, $guia['item_id']]);
                    $tipo = $diferenca > 0 ? 'saida' : 'entrada';
                    recordStockMovement($guia['item_id'], $saldoAtual, $saldoNovo, $tipo, $id);
                }
                $stmt = $pdo->prepare("UPDATE guias SET quantidade = ? WHERE id = ?");
                $stmt->execute([$quantidadeNova, $id]);
                recordAuditEvent('ajustar_quantidade_guia', 'guias', $id, ['quantidade' => $quantidadeAnterior], ['quantidade' => $quantidadeNova]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Guia e movimentação de estoque atualizadas.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Não foi possível ajustar a guia.'];
            }
            break;

        case 'atualizar_beneficiario':
            $nome = trim($input['nome'] ?? '');
            $cpf = formatCPF($input['cpf'] ?? '');
            $telefone = formatPhone($input['telefone'] ?? '');
            $email = trim($input['email'] ?? '');
            $renda = (float)($input['renda'] ?? 0);
            $situacao = $input['situacao'] ?? 'desempregado';
            $dependentes = max(0, (int)($input['dependentes'] ?? 0));
            if ($id > 0 && $nome !== '' && $cpf !== false && $telefone !== false && $renda >= 0) {
                $stmt = $pdo->prepare("UPDATE beneficiarios SET nome = ?, cpf = ?, telefone = ?, email = ?, renda_per_capita = ?, situacao_trabalho = ?, dependentes = ? WHERE id = ?");
                if ($stmt->execute([$nome, $cpf, $telefone, $email, $renda, $situacao, $dependentes, $id])) {
                    recordAuditEvent('atualizar_beneficiario', 'beneficiarios', $id);
                    $response = ['status' => 'success', 'message' => 'Beneficiário atualizado.'];
                }
            } else {
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Confira os dados do beneficiário.'];
            }
            break;

        case 'criar_guia':
            $itemId = (int)($input['item_id'] ?? 0);
            $beneficiarioId = (int)($input['beneficiario_id'] ?? 0);
            $quantidade = (int)($input['quantidade'] ?? 0);
            $justificativa = trim($input['justificativa'] ?? '');
            if ($itemId <= 0 || $beneficiarioId <= 0 || $quantidade <= 0 || $justificativa === '') {
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Informe item, beneficiário, quantidade e justificativa.'];
                break;
            }
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$item || (int)$item['quantidade'] < $quantidade) {
                    throw new RuntimeException($item ? 'Estoque insuficiente.' : 'Item não encontrado.');
                }
                $stmt = $pdo->prepare("INSERT INTO guias (item_id, beneficiario_id, usuario_id, quantidade, justificativa, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                $stmt->execute([$itemId, $beneficiarioId, $_SESSION['user_id'], $quantidade, $justificativa]);
                $guiaId = (int)$pdo->lastInsertId();
                $quantidadeInicial = (int)$item['quantidade'];
                $quantidadeFinal = $quantidadeInicial - $quantidade;
                $stmt = $pdo->prepare("UPDATE itens SET quantidade = ? WHERE id = ?");
                $stmt->execute([$quantidadeFinal, $itemId]);
                recordStockMovement($itemId, $quantidadeInicial, $quantidadeFinal, 'saida', $guiaId);
                recordAuditEvent('emitir_guia', 'guias', $guiaId, null, ['item_id' => $itemId, 'beneficiario_id' => $beneficiarioId, 'quantidade' => $quantidade]);
                $pdo->commit();
                $response = ['status' => 'success', 'message' => 'Guia criada e saída de estoque registrada.', 'guia_id' => $guiaId];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Não foi possível criar a guia. Confira o estoque e os dados.'];
            }
            break;

        case 'criar_beneficiario':
            $nome = trim($input['nome'] ?? '');
            $cpf = formatCPF($input['cpf'] ?? '');
            $telefone = formatPhone($input['telefone'] ?? '');
            $email = trim($input['email'] ?? '');
            $renda = (float)($input['renda'] ?? 0);
            $situacao = $input['situacao'] ?? 'desempregado';
            $dependentes = max(0, (int)($input['dependentes'] ?? 0));
            $endereco = trim($input['endereco'] ?? '');
            $bairro = trim($input['bairro'] ?? '');
            $cidade = trim($input['cidade'] ?? 'Caçapava');
            $estado = strtoupper(substr(trim($input['estado'] ?? 'SP'), 0, 2));
            if ($nome !== '' && $cpf !== false && $telefone !== false && $renda >= 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO beneficiarios (nome, cpf, telefone, email, renda_per_capita, situacao_trabalho, dependentes, endereco, bairro, cidade, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $cpf, $telefone, $email, $renda, $situacao, $dependentes, $endereco, $bairro, $cidade, $estado]);
                    recordAuditEvent('cadastrar_beneficiario', 'beneficiarios', (int)$pdo->lastInsertId());
                    $response = ['status' => 'success', 'message' => 'Beneficiário cadastrado com sucesso.'];
                } catch (PDOException $e) {
                    http_response_code(409);
                    $response = ['status' => 'error', 'message' => 'Não foi possível cadastrar. Confira se o CPF já está registrado.'];
                }
            } else {
                http_response_code(422);
                $response = ['status' => 'error', 'message' => 'Confira os dados do beneficiário.'];
            }
            break;
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
