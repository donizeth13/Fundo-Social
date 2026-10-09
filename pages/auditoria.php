<?php
/**
 * Consulta de auditoria do sistema
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Auditoria';
$acao = trim($_GET['acao'] ?? '');
$tabela = trim($_GET['tabela'] ?? '');
$conditions = [];
$params = [];

if ($acao !== '') {
    $conditions[] = 'a.acao LIKE ?';
    $params[] = '%' . $acao . '%';
}
if ($tabela !== '') {
    $conditions[] = 'a.tabela = ?';
    $params[] = $tabela;
}

$sql = "SELECT a.id, a.acao, a.tabela, a.registro_id, a.ip_address, a.data_hora, u.nome AS usuario_nome FROM audit_log a LEFT JOIN usuarios u ON a.usuario_id = u.id";
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY a.data_hora DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eventos = $stmt->fetchAll();
$tabelas = $pdo->query("SELECT DISTINCT tabela FROM audit_log ORDER BY tabela")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-list me-2"></i>Auditoria</h1>
    <p class="subtitle">Registro das operações administrativas e movimentações realizadas</p>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Eventos recentes</h2>
        <span class="badge bg-secondary"><?php echo count($eventos); ?> registros</span>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4"><label class="form-label" for="acao">Ação</label><input class="form-control" id="acao" name="acao" value="<?php echo htmlspecialchars($acao, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex.: emitir_guia"></div>
            <div class="col-md-4"><label class="form-label" for="tabela">Área</label><select class="form-select" id="tabela" name="tabela"><option value="">Todas</option><?php foreach ($tabelas as $nomeTabela): ?><option value="<?php echo htmlspecialchars($nomeTabela, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $tabela === $nomeTabela ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($nomeTabela), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Filtrar</button><a class="btn btn-outline-secondary" href="<?php echo PAGES_URL; ?>auditoria.php">Limpar</a></div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>Data e hora</th><th>Usuário</th><th>Ação</th><th>Área</th><th>Registro</th><th>Origem</th></tr></thead>
                <tbody>
                    <?php foreach ($eventos as $evento): ?>
                    <tr>
                        <td><?php echo formatDateTimeBr($evento['data_hora']); ?></td>
                        <td><?php echo htmlspecialchars($evento['usuario_nome'] ?? 'Usuário removido', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><code><?php echo htmlspecialchars($evento['acao'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars($evento['tabela'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$evento['registro_id'] ?: 'Geral'; ?></td>
                        <td><?php echo htmlspecialchars($evento['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$eventos): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum evento encontrado.</td><?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">Exibindo até 200 eventos. Os dados pessoais detalhados não são apresentados nesta consulta.</p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
