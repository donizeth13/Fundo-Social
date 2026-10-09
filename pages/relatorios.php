<?php
/**
 * Página de Relatórios
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Relatórios';

// Processar exportação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] == 'exportar') {
        $tipo = $_POST['tipo'] ?? 'estoque';
        
        switch ($tipo) {
            case 'estoque':
                $dados = $pdo->query("SELECT id, codigo, nome, quantidade, descricao, observacao, created_at, updated_at FROM itens ORDER BY id")->fetchAll();
                exportToCSV($dados, 'relatorio-estoque-' . date('Y-m-d'));
                break;
            case 'guias':
                $dados = getGuias(1000, 0);
                exportToCSV($dados, 'relatorio-guias-' . date('Y-m-d'));
                break;
            case 'beneficiarios':
                $dados = getBeneficiarios(1000, 0);
                exportToCSV($dados, 'relatorio-beneficiarios-' . date('Y-m-d'));
                break;
        }
    }
}
?>

<!-- Opções de Relatório -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Geração de Relatórios</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-box me-2"></i>Estoque</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">Relatório de itens, quantidades e descrições.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="exportar">
                            <input type="hidden" name="tipo" value="estoque">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-download me-2"></i>Exportar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-file-signature me-2"></i>Guias de Autorização</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">Relatório com todas as guias emitidas e seus status.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="exportar">
                            <input type="hidden" name="tipo" value="guias">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fas fa-download me-2"></i>Exportar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-user-injured me-2"></i>Beneficiários</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">Relatório de cadastro e indicadores socioeconômicos.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="exportar">
                            <input type="hidden" name="tipo" value="beneficiarios">
                            <button type="submit" class="btn btn-outline-info w-100">
                                <i class="fas fa-download me-2"></i>Exportar
                            </button>
                        </form>

<!-- Cronograma de Relatórios -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Cronograma de Relatórios Sugerido</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Frequência</th>
                        <th>Relatório</th>
                        <th>Responsável</th>
                        <th>Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Diário</td>
                        <td>Movimentações de estoque</td>
                        <td>Assistente Social</td>
                        <td>08h00</td>
                    </tr>
                    <tr>
                        <td>Semanal</td>
                        <td>Relatório de guias emitidas</td>
                        <td>Gestor de Operações</td>
                        <td>Sábado</td>
                    </tr>
                    <tr>
                        <td>Mensal</td>
                        <td>Relatório de Prioridade Social</td>
                        <td>Equipe de Avaliação</td>
                        <td>5º dia útil</td>
                    </tr>
                    <tr>
                        <td>Trimestral</td>
                        <td>Relatório Executivo Governança</td>
                        <td>Administrador do Fundo</td>
                        <td>15 dias do mês</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-warning text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Resumo Geral</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">Visão geral com indicadores principais do sistema.</p>
                        <?php
                        $resumo = '<table class="table table-sm mb-0">';
                        $resumo .= '<tr><td class="fw-bold">Total de Itens:</td><td>' . $stats['total_itens'] . '</td></tr>';
                        $resumo .= '<tr><td class="fw-bold">Total de Guias:</td><td>' . $stats['total_guias'] . '</td></tr>';
                        $resumo .= '<tr><td class="fw-bold">Guias Ativas:</td><td>' . ($stats['guias_ativas'] ?? 0) . '</td></tr>';
                        $resumo .= '<tr><td class="fw-bold">Beneficiários:</td><td>' . $stats['total_beneficiarios'] . '</td></tr>';
                        $resumo .= '<tr><td class="fw-bold">Estoque Disponível:</td><td>' . $stats['estoque_disponivel'] . '</td></tr>';
                        $resumo .= '</table>';
                        echo $resumo;
                        ?>
                        <a href="<?php echo PAGES_URL; ?>relatorios.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-refresh me-2"></i>Atualizar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';

