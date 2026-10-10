<?php

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();

$stats = getStatistics();

$lowStockThreshold = 10;
$stmt = $pdo->prepare("SELECT id, nome, quantidade FROM itens WHERE quantidade <= ? ORDER BY quantidade, nome LIMIT 6");
$stmt->execute([$lowStockThreshold]);
$itensAtencao = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE quantidade <= ?");
$stmt->execute([$lowStockThreshold]);
$totalItensAtencao = (int)$stmt->fetchColumn();
$ultimasGuias = $pdo->query("SELECT g.id, g.numero, g.quantidade, g.status, g.data_criacao, i.nome AS item_nome, b.nome AS beneficiario_nome FROM guias g LEFT JOIN itens i ON g.item_id = i.id LEFT JOIN beneficiarios b ON g.beneficiario_id = b.id ORDER BY g.data_criacao DESC LIMIT 5")->fetchAll();
$movimentacoes = $pdo->query("SELECT m.*, i.nome AS item_nome, g.numero AS guia_numero, b.nome AS beneficiario_nome FROM itens_guias_estoque m LEFT JOIN itens i ON m.item_id = i.id LEFT JOIN guias g ON m.guia_id = g.id LEFT JOIN beneficiarios b ON g.beneficiario_id = b.id ORDER BY m.data_movimentacao DESC LIMIT 6")->fetchAll();

$pageTitle = 'Painel de Controle';
?>
<div class="dashboard-heading">
    <div>
        <h1><i class="fas fa-gauge-high me-2"></i>Painel de Controle</h1>
        <p>Resumo operacional do Fundo Social de Caçapava</p>
    </div>
    <div class="dashboard-actions">
        <a class="btn btn-primary" href="<?php echo PAGES_URL; ?>guias.php"><i class="fas fa-plus me-2"></i>Nova guia</a>
        <a class="btn btn-outline-primary" href="<?php echo PAGES_URL; ?>beneficiarios.php"><i class="fas fa-user-plus me-2"></i>Novo beneficiário</a>
    </div>
</div>

<section class="dashboard-metrics" aria-label="Indicadores do sistema">
    <article class="dashboard-metric metric-stock">
        <div class="metric-label"><span>Itens cadastrados</span><i class="fas fa-boxes-stacked"></i></div>
        <div class="metric-value"><?php echo (int)$stats['total_itens']; ?></div>
        <div class="metric-context"><?php echo (int)$stats['estoque_disponivel']; ?> unidades disponíveis</div>
    </article>
    <article class="dashboard-metric metric-guides">
        <div class="metric-label"><span>Guias ativas</span><i class="fas fa-file-signature"></i></div>
        <div class="metric-value"><?php echo (int)($stats['guias_ativas'] ?? 0); ?></div>
        <div class="metric-context"><?php echo (int)$stats['total_guias']; ?> guias no total</div>
    </article>
    <article class="dashboard-metric metric-people">
        <div class="metric-label"><span>Beneficiários</span><i class="fas fa-users"></i></div>
        <div class="metric-value"><?php echo (int)$stats['total_beneficiarios']; ?></div>
        <div class="metric-context">Cadastros totais</div>
    </article>
    <article class="dashboard-metric <?php echo $totalItensAtencao > 0 ? 'metric-warning' : 'metric-ok'; ?>">
        <div class="metric-label"><span>Estoque em atenção</span><i class="fas fa-triangle-exclamation"></i></div>
        <div class="metric-value"><?php echo $totalItensAtencao; ?></div>
        <div class="metric-context">Itens com até <?php echo $lowStockThreshold; ?> unidades</div>
    </article>
</section>

<div class="dashboard-panels">
    <section class="dashboard-panel" aria-labelledby="stock-attention-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-kicker">ESTOQUE</p>
                <h2 id="stock-attention-title">Itens em atenção</h2>
            </div>
            <a class="panel-link" href="<?php echo PAGES_URL; ?>estoque.php">Ver estoque <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <?php if ($itensAtencao): ?>
        <div class="attention-list">
            <?php foreach ($itensAtencao as $item): ?>
            <?php $percentualEstoque = min(100, (int)$item['quantidade'] * 100 / $lowStockThreshold); ?>
            <div class="attention-item">
                <div class="attention-item-heading">
                    <span><?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong class="<?php echo (int)$item['quantidade'] === 0 ? 'text-danger' : 'text-warning'; ?>"><?php echo (int)$item['quantidade']; ?> un.</strong>
                </div>
                <div class="stock-meter" role="progressbar" aria-label="Nível de estoque" aria-valuemin="0" aria-valuemax="<?php echo $lowStockThreshold; ?>" aria-valuenow="<?php echo (int)$item['quantidade']; ?>">
                    <span class="<?php echo (int)$item['quantidade'] === 0 ? 'meter-empty' : 'meter-low'; ?>" style="width: <?php echo $percentualEstoque; ?>%"></span>
                </div>
                <div class="attention-status"><?php echo (int)$item['quantidade'] === 0 ? 'Esgotado' : 'Abaixo do limite de segurança'; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="dashboard-empty"><i class="fas fa-circle-check"></i><span>Nenhum item precisa de reposição agora.</span></div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="recent-guides-title">
        <div class="dashboard-panel-heading">
            <div>
                <p class="dashboard-kicker">ATENDIMENTO</p>
                <h2 id="recent-guides-title">Guias recentes</h2>
            </div>
            <a class="panel-link" href="<?php echo PAGES_URL; ?>guias.php">Todas as guias <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <?php if ($ultimasGuias): ?>
        <div class="table-responsive">
            <table class="table dashboard-table align-middle">
                <thead><tr><th>Guia</th><th>Beneficiário</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                    <?php foreach ($ultimasGuias as $guia): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($guia['numero'], ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars($guia['item_nome'] ?? 'Item removido', ENT_QUOTES, 'UTF-8'); ?></small></td>
                        <td><?php echo htmlspecialchars($guia['beneficiario_nome'] ?? 'Cadastro indisponível', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge <?php echo $guia['status'] === 'ativo' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars(ucfirst($guia['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo formatDate($guia['data_criacao']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="dashboard-empty"><i class="fas fa-file-circle-plus"></i><span>Nenhuma guia emitida.</span><a href="<?php echo PAGES_URL; ?>guias.php">Emitir a primeira guia</a></div>
        <?php endif; ?>
    </section>
</div>

<section class="dashboard-panel dashboard-movements" aria-labelledby="recent-movements-title">
    <div class="dashboard-panel-heading">
        <div>
            <p class="dashboard-kicker">RASTREABILIDADE</p>
            <h2 id="recent-movements-title">Movimentações recentes</h2>
        </div>
        <a class="panel-link" href="<?php echo PAGES_URL; ?>estoque.php">Histórico do estoque <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table dashboard-table align-middle">
            <thead><tr><th>Data</th><th>Item</th><th>Movimento</th><th>Quantidade</th><th>Saldo final</th><th>Beneficiário</th><th>Referência</th></tr></thead>
            <tbody>
                <?php foreach ($movimentacoes as $movimentacao): ?>
                <tr>
                    <td><?php echo formatDateTimeBr($movimentacao['data_movimentacao']); ?></td>
                    <td><?php echo htmlspecialchars($movimentacao['item_nome'] ?? 'Item removido', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="movement-type <?php echo $movimentacao['tipo_movimentacao'] === 'entrada' ? 'movement-in' : 'movement-out'; ?>"><?php echo $movimentacao['tipo_movimentacao'] === 'entrada' ? 'Entrada' : 'Saída'; ?></span></td>
                    <td><?php echo (int)$movimentacao['quantidade_movimentada']; ?></td>
                    <td><?php echo (int)$movimentacao['quantidade_final']; ?></td>
                    <td><?php echo htmlspecialchars($movimentacao['beneficiario_nome'] ?? 'Movimento manual', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($movimentacao['guia_numero'] ?? 'Ajuste manual', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$movimentacoes): ?><tr><td colspan="7" class="text-muted py-4">Ainda não há movimentações registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';

