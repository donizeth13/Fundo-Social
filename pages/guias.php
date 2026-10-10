<?php

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Guias de Autorização';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'criar') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $beneficiarioId = (int)($_POST['beneficiario_id'] ?? 0);
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $justificativa = trim(cleanInput($_POST['justificativa'] ?? ''));

        if ($itemId <= 0 || $beneficiarioId <= 0 || $quantidade <= 0 || $justificativa === '') {
            $_SESSION['flash_message'] = 'Informe o item, beneficiário, quantidade e justificativa.';
            $_SESSION['flash_type'] = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT quantidade FROM itens WHERE id = ? FOR UPDATE");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item || $item['quantidade'] < $quantidade) {
                    $pdo->rollBack();
                    $_SESSION['flash_message'] = $item ? 'Estoque insuficiente para este item.' : 'Item não encontrado.';
                    $_SESSION['flash_type'] = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO guias (item_id, beneficiario_id, usuario_id, quantidade, justificativa, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                    $stmt->execute([$itemId, $beneficiarioId, $_SESSION['user_id'], $quantidade, $justificativa]);
                    $guiaId = (int)$pdo->lastInsertId();
                    $quantidadeInicial = (int)$item['quantidade'];
                    $quantidadeFinal = $quantidadeInicial - $quantidade;
                    $stmt = $pdo->prepare("UPDATE itens SET quantidade = quantidade - ? WHERE id = ?");
                    $stmt->execute([$quantidade, $itemId]);
                    recordStockMovement($itemId, $quantidadeInicial, $quantidadeFinal, 'saida', $guiaId);
                    recordAuditEvent('emitir_guia', 'guias', $guiaId, null, [
                        'item_id' => $itemId,
                        'beneficiario_id' => $beneficiarioId,
                        'quantidade' => $quantidade,
                    ]);
                    $pdo->commit();
                    $_SESSION['flash_message'] = 'Guia criada e estoque atualizado.';
                    $_SESSION['flash_type'] = 'success';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash_message'] = 'Não foi possível criar a guia. Confira os dados e tente novamente.';
                $_SESSION['flash_type'] = 'error';
            }
        }
    } elseif ($action === 'fechar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE guias SET status = 'encerrada' WHERE id = ? AND status = 'ativo'");
            $stmt->execute([$id]);
            $encerrada = $stmt->rowCount() > 0;
            if ($encerrada) {
                recordAuditEvent('encerrar_guia', 'guias', $id);
            }
            $_SESSION['flash_message'] = $encerrada ? 'Guia encerrada.' : 'A guia não foi encontrada ou já está encerrada.';
            $_SESSION['flash_type'] = $encerrada ? 'success' : 'error';
        }
    }

    header('Location: ' . PAGES_URL . 'guias.php');
    exit();
}

$statusFilter = $_GET['status'] ?? null;
if (!in_array($statusFilter, ['ativo', 'encerrada', 'cancelado', 'pendente'], true)) {
    $statusFilter = null;
}
$guias = getGuias(100, 0, $statusFilter);
$itens = getItems(1000, 0);
$beneficiarios = getBeneficiarios(1000, 0);
?>

<div class="page-header">
    <h1><i class="fas fa-file-signature me-2"></i>Guias de Autorização</h1>
    <p class="subtitle">Emita autorizações e acompanhe a distribuição de itens</p>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nova guia</h5></div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="criar">
            <div class="col-md-4">
                <label class="form-label" for="item_id">Item *</label>
                <select class="form-select" id="item_id" name="item_id" required>
                    <option value="">Selecione um item</option>
                    <?php foreach ($itens as $item): ?>
                    <option value="<?php echo (int)$item['id']; ?>" <?php echo (int)$item['quantidade'] < 1 ? 'disabled' : ''; ?>><?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?> (estoque: <?php echo (int)$item['quantidade']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="beneficiario_id">Beneficiário *</label>
                <select class="form-select" id="beneficiario_id" name="beneficiario_id" required>
                    <option value="">Selecione um beneficiário</option>
                    <?php foreach ($beneficiarios as $beneficiario): ?>
                    <option value="<?php echo (int)$beneficiario['id']; ?>"><?php echo htmlspecialchars($beneficiario['nome'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="quantidade">Quantidade *</label>
                <input class="form-control" id="quantidade" name="quantidade" type="number" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="justificativa">Justificativa *</label>
                <input class="form-control" id="justificativa" name="justificativa" required>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit"><i class="fas fa-file-signature me-2"></i>Emitir guia</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Guias cadastradas</h5>
        <form method="GET" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="status" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                <option value="ativo" <?php echo $statusFilter === 'ativo' ? 'selected' : ''; ?>>Ativas</option>
                <option value="encerrada" <?php echo $statusFilter === 'encerrada' ? 'selected' : ''; ?>>Encerradas</option>
                <option value="cancelado" <?php echo $statusFilter === 'cancelado' ? 'selected' : ''; ?>>Canceladas</option>
                <option value="pendente" <?php echo $statusFilter === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
            </select>
            <button class="btn btn-sm btn-outline-primary" type="submit" aria-label="Filtrar"><i class="fas fa-filter"></i></button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Número</th><th>Item</th><th>Beneficiário</th><th>Quantidade</th><th>Justificativa</th><th>Status</th><th>Criada por</th><th>Data</th><th>Documentos e ação</th></tr></thead>
                <tbody>
                    <?php foreach ($guias as $guia): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($guia['numero'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($guia['item_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($guia['beneficiario_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$guia['quantidade']; ?></td>
                        <td><?php echo htmlspecialchars($guia['justificativa'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($guia['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($guia['usuario_nome'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo formatDate($guia['data_criacao']); ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo PAGES_URL; ?>guia_imprimir.php?id=<?php echo (int)$guia['id']; ?>" target="_blank" rel="noopener" title="Imprimir guia" aria-label="Imprimir guia">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-danger" href="<?php echo PAGES_URL; ?>guia_pdf.php?id=<?php echo (int)$guia['id']; ?>" title="Baixar PDF" aria-label="Baixar PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            <?php if ($guia['status'] === 'ativo'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Encerrar esta guia?')">
                                <input type="hidden" name="action" value="fechar">
                                <input type="hidden" name="id" value="<?php echo (int)$guia['id']; ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit" aria-label="Encerrar guia"><i class="fas fa-check"></i></button>
                            </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$guias): ?><tr><td colspan="9" class="text-center text-muted py-4">Nenhuma guia encontrada.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
