<?php

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Estoque de Itens';

// Processar ação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'adicionar':
                $nome = cleanInput($_POST['nome'] ?? '');
                $quantidade = (int)($_POST['quantidade'] ?? 0);
                $descricao = cleanInput($_POST['descricao'] ?? '');
                
                if (empty($nome) || $quantidade <= 0) {
                    $_SESSION['flash_message'] = 'Preencha todos os campos corretamente.';
                    $_SESSION['flash_type'] = 'error';
                } else {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("INSERT INTO itens (nome, quantidade, descricao) VALUES (?, ?, ?)");
                        $stmt->execute([$nome, $quantidade, $descricao]);
                        $itemId = (int)$pdo->lastInsertId();
                        recordStockMovement($itemId, 0, $quantidade, 'entrada');
                        recordAuditEvent('entrada_estoque', 'itens', $itemId, null, ['quantidade' => $quantidade]);
                        $pdo->commit();
                        $_SESSION['flash_message'] = 'Item adicionado com sucesso!';
                        $_SESSION['flash_type'] = 'success';
                        header('Location: estoque.php');
                        exit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $_SESSION['flash_message'] = 'Não foi possível adicionar o item.';
                        $_SESSION['flash_type'] = 'error';
                    }
                }
                break;
                
            case 'atualizar':
                $id = (int)($_POST['id'] ?? 0);
                $nome = cleanInput($_POST['nome'] ?? '');
                $descricao = cleanInput($_POST['descricao'] ?? '');
                if ($id > 0 && $nome !== '') {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("SELECT nome FROM itens WHERE id = ? FOR UPDATE");
                        $stmt->execute([$id]);
                        $item = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$item) {
                            throw new RuntimeException('Item não encontrado.');
                        }

                        $stmt = $pdo->prepare("UPDATE itens SET nome = ?, descricao = ? WHERE id = ?");
                        $stmt->execute([$nome, $descricao, $id]);
                        recordAuditEvent('editar_dados_item', 'itens', $id, ['nome' => $item['nome']], ['nome' => $nome]);
                        $pdo->commit();
                        $_SESSION['flash_message'] = 'Dados do item atualizados. O saldo só muda por entrada ou guia.';
                        $_SESSION['flash_type'] = 'success';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $_SESSION['flash_message'] = 'Erro ao atualizar quantidade.';
                        $_SESSION['flash_type'] = 'error';
                    }
                }
                break;

            case 'entrada':
                $id = (int)($_POST['id'] ?? 0);
                $quantidadeEntrada = filter_var($_POST['quantidade'] ?? null, FILTER_VALIDATE_INT);
                if ($id > 0 && $quantidadeEntrada !== false && $quantidadeEntrada > 0) {
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
                        $stmt->execute([$id]);
                        $item = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$item) {
                            throw new RuntimeException('Item não encontrado.');
                        }

                        $saldoAnterior = (int)$item['quantidade'];
                        $saldoFinal = $saldoAnterior + $quantidadeEntrada;
                        $stmt = $pdo->prepare("UPDATE itens SET quantidade = ? WHERE id = ?");
                        $stmt->execute([$saldoFinal, $id]);
                        recordStockMovement($id, $saldoAnterior, $saldoFinal, 'entrada');
                        recordAuditEvent('entrada_estoque', 'itens', $id, ['quantidade' => $saldoAnterior], ['quantidade' => $saldoFinal]);
                        $pdo->commit();
                        $_SESSION['flash_message'] = 'Entrada registrada e saldo atualizado.';
                        $_SESSION['flash_type'] = 'success';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $_SESSION['flash_message'] = 'Não foi possível registrar a entrada.';
                        $_SESSION['flash_type'] = 'error';
                    }
                } else {
                    $_SESSION['flash_message'] = 'Informe uma quantidade de entrada maior que zero.';
                    $_SESSION['flash_type'] = 'error';
                }
                break;
                
            case 'excluir':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM itens WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $_SESSION['flash_message'] = 'Item excluído!';
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Erro ao excluir item.';
                        $_SESSION['flash_type'] = 'error';
                    }
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Buscar itens
$itens = getItems(50, 0);
$movimentacoes = $pdo->query("SELECT m.*, i.nome AS item_nome, g.numero AS guia_numero, b.nome AS beneficiario_nome FROM itens_guias_estoque m LEFT JOIN itens i ON m.item_id = i.id LEFT JOIN guias g ON m.guia_id = g.id LEFT JOIN beneficiarios b ON g.beneficiario_id = b.id ORDER BY m.data_movimentacao DESC LIMIT 100")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-box me-2"></i>Estoque de Itens</h1>
    <p class="subtitle">Gerencie os itens disponíveis para doação e distribuição</p>
</div>

<div class="card mt-4">
    <div class="card-header"><h2 class="h5 mb-0"><i class="fas fa-clock-rotate-left me-2"></i>Movimentações recentes</h2></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Data</th><th>Item</th><th>Tipo</th><th>Saldo anterior</th><th>Quantidade</th><th>Saldo final</th><th>Beneficiário</th><th>Guia</th></tr></thead>
                <tbody>
                    <?php foreach ($movimentacoes as $movimentacao): ?>
                    <tr>
                        <td><?php echo formatDateTimeBr($movimentacao['data_movimentacao']); ?></td>
                        <td><?php echo htmlspecialchars($movimentacao['item_nome'] ?? 'Item removido', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $movimentacao['tipo_movimentacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?></td>
                        <td><?php echo (int)$movimentacao['quantidade_inicial']; ?></td>
                        <td><?php echo (int)$movimentacao['quantidade_movimentada']; ?></td>
                        <td><?php echo (int)$movimentacao['quantidade_final']; ?></td>
                        <td><?php echo htmlspecialchars($movimentacao['beneficiario_nome'] ?? 'Movimento manual', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($movimentacao['guia_numero'] ?? 'Ajuste manual', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$movimentacoes): ?><tr><td colspan="8" class="text-center text-muted">Sem movimentações registradas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Formulário de Adição -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Novo Item no Estoque</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="adicionar">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="nome" class="form-label">Nome do Item *</label>
                    <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Leite, Arroz, Feijão..." required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="quantidade" class="form-label">Quantidade *</label>
                    <input type="number" class="form-control" id="quantidade" name="quantidade" min="0" value="1" required>
                </div>
                <div class="col-12 mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="2" placeholder="Observações sobre o item..."></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Adicionar Item
                </button>
                <a href="<?php echo PAGES_URL; ?>estoque.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Itens -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Itens Cadastrados</h5>
        <span class="badge bg-info"><?php echo count($itens); ?> itens</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Quantidade</th>
                        <th>Descrição</th>
                        <th>Data Cad.</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($itens) > 0): ?>
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td class="fw-bold"><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['nome']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $item['quantidade'] > 0 ? 'success' : 'danger'; ?>">
                                    <?php echo $item['quantidade']; ?>
                                </span>
                            </td>
                            <td class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($item['descricao']); ?>">
                                <?php echo htmlspecialchars($item['descricao'] ?: '-'); ?>
                            </td>
                            <td><?php echo formatDate($item['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editItemModal"
                                            data-id="<?php echo $item['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($item['nome']); ?>"
                                            data-descricao="<?php echo htmlspecialchars($item['descricao']); ?>"
                                            aria-label="Editar cadastro de <?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar cadastro">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#entradaEstoqueModal"
                                            data-id="<?php echo $item['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-quantidade="<?php echo (int)$item['quantidade']; ?>"
                                            aria-label="Registrar entrada para <?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?>" title="Registrar entrada">
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-action" onclick="confirmDelete('processar_acao.php?acao=excluir_item&id=<?php echo $item['id']; ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-box-open text-muted mb-2"></i>
                                <p class="mb-0">Não há itens cadastrados. Clique em "Novo Item" para adicionar.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar dados do item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editItemForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="atualizar">
                    <input type="hidden" name="id" id="editId">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" class="form-control" id="editNome" name="nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editDescricao">Descrição</label>
                        <textarea class="form-control" id="editDescricao" name="descricao" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="entradaEstoqueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h2 class="modal-title h5">Registrar entrada no estoque</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="entrada">
                    <input type="hidden" name="id" id="entradaItemId">
                    <div class="mb-3"><label class="form-label" for="entradaItemNome">Produto</label><input class="form-control" id="entradaItemNome" readonly></div>
                    <div class="mb-3"><label class="form-label" for="entradaSaldoAtual">Saldo atual</label><input class="form-control" id="entradaSaldoAtual" readonly></div>
                    <div class="mb-3"><label class="form-label" for="entradaQuantidade">Quantidade recebida</label><input class="form-control" id="entradaQuantidade" name="quantidade" type="number" min="1" step="1" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success"><i class="fas fa-arrow-down me-2"></i>Registrar entrada</button></div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editItemModal');
    editModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        if (!button) {
            return;
        }
        document.getElementById('editId').value = button.dataset.id;
        document.getElementById('editNome').value = button.dataset.nome;
        document.getElementById('editDescricao').value = button.dataset.descricao;
    });

    const entradaModal = document.getElementById('entradaEstoqueModal');
    entradaModal.addEventListener('show.bs.modal', function(e) {
        const button = e.relatedTarget;
        if (!button) {
            return;
        }
        document.getElementById('entradaItemId').value = button.dataset.id;
        document.getElementById('entradaItemNome').value = button.dataset.nome;
        document.getElementById('entradaSaldoAtual').value = button.dataset.quantidade;
        document.getElementById('entradaQuantidade').value = '';
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
