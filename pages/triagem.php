<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

connectDB();
requireLogin();
requirePermission('admin');

$pageTitle = 'Triagem e Prioridade Social';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'calcular') {
	try {
		$pdo->beginTransaction();
		$beneficiariosParaCalculo = $pdo->query("SELECT id, renda_per_capita, situacao_trabalho, dependentes FROM beneficiarios WHERE ativo = 1")->fetchAll();
		$update = $pdo->prepare("UPDATE beneficiarios SET score_prioridade = ? WHERE id = ?");

		foreach ($beneficiariosParaCalculo as $beneficiario) {
			$update->execute([calculatePriorityBreakdown($beneficiario)['total'], $beneficiario['id']]);
		}

		recordAuditEvent('recalcular_prioridade', 'beneficiarios', 0, null, [
			'registros' => count($beneficiariosParaCalculo),
			'criterios' => ['renda_per_capita', 'situacao_trabalho', 'dependentes'],
			'versao_regra' => 1,
		]);
		$pdo->commit();
		$_SESSION['flash_message'] = 'Pontuações recalculadas. A classificação é um apoio à análise profissional.';
		$_SESSION['flash_type'] = 'success';
	} catch (Throwable $e) {
		if ($pdo->inTransaction()) {
			$pdo->rollBack();
		}
		$_SESSION['flash_message'] = 'Não foi possível recalcular as pontuações.';
		$_SESSION['flash_type'] = 'error';
	}

	header('Location: ' . PAGES_URL . 'triagem.php');
	exit();
}

$search = trim($_GET['search'] ?? '');
$situacao = $_GET['situacao'] ?? '';
$faixaRenda = $_GET['faixa_renda'] ?? '';
$ordenar = $_GET['ordenar'] ?? 'score';
$situacoesValidas = ['desempregado', 'empregado', 'autoempreendedor', 'aposentado', 'premium'];
$faixasValidas = ['baixa', 'media', 'alta'];
$ordenacoesValidas = ['score', 'renda', 'nome'];

if (!in_array($situacao, $situacoesValidas, true)) {
	$situacao = '';
}
if (!in_array($faixaRenda, $faixasValidas, true)) {
	$faixaRenda = '';
}
if (!in_array($ordenar, $ordenacoesValidas, true)) {
	$ordenar = 'score';
}

$conditions = ['ativo = 1'];
$params = [];
if ($search !== '') {
	$conditions[] = '(nome LIKE ? OR cpf LIKE ?)';
	$params[] = '%' . $search . '%';
	$params[] = '%' . $search . '%';
}
if ($situacao !== '') {
	$conditions[] = 'situacao_trabalho = ?';
	$params[] = $situacao;
}
if ($faixaRenda === 'baixa') {
	$conditions[] = 'renda_per_capita <= 500';
} elseif ($faixaRenda === 'media') {
	$conditions[] = 'renda_per_capita > 500 AND renda_per_capita <= 1000';
} elseif ($faixaRenda === 'alta') {
	$conditions[] = 'renda_per_capita > 1000';
}

$sql = 'SELECT id, nome, cpf, renda_per_capita, situacao_trabalho, dependentes, score_prioridade FROM beneficiarios WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$beneficiarios = $stmt->fetchAll();

foreach ($beneficiarios as &$beneficiario) {
	$beneficiario['pontuacao'] = calculatePriorityBreakdown($beneficiario);
}
unset($beneficiario);

usort($beneficiarios, function ($first, $second) use ($ordenar) {
	if ($ordenar === 'nome') {
		return strcasecmp($first['nome'], $second['nome']);
	}
	if ($ordenar === 'renda') {
		return (float)$first['renda_per_capita'] <=> (float)$second['renda_per_capita'];
	}
	return $second['pontuacao']['total'] <=> $first['pontuacao']['total'];
});

$totalPrioritarios = count(array_filter($beneficiarios, function ($beneficiario) {
	return $beneficiario['pontuacao']['total'] >= 70;
}));
?>

<div class="page-header">
	<h1><i class="fas fa-clipboard-check me-2"></i>Triagem e Prioridade Social</h1>
	<p class="subtitle">Classificação socioeconômica para apoiar a análise da equipe responsável</p>
</div>

<div class="alert alert-info" role="note">
	A pontuação é uma regra transparente baseada em renda, situação de trabalho e dependentes menores. Ela não substitui a avaliação dos profissionais nem decide o atendimento.
</div>

<div class="row g-3 mb-4">
	<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Cadastros no resultado</div><div class="h3 mb-0"><?php echo count($beneficiarios); ?></div></div></div></div>
	<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Pontuação a partir de 70</div><div class="h3 mb-0"><?php echo $totalPrioritarios; ?></div></div></div></div>
	<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Faixa de pontuação</div><div class="h3 mb-0">0 a 100</div></div></div></div>
</div>

<div class="card mb-4">
	<div class="card-header d-flex justify-content-between align-items-center">
		<h2 class="h5 mb-0">Filtros e cálculo</h2>
		<form method="POST">
			<input type="hidden" name="action" value="calcular">
			<button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-calculator me-1"></i>Recalcular e registrar</button>
		</form>
	</div>
	<div class="card-body">
		<form method="GET" class="row g-3">
			<div class="col-lg-4 col-md-6"><label for="search" class="form-label">Nome ou CPF</label><input class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"></div>
			<div class="col-lg-3 col-md-6"><label for="situacao" class="form-label">Situação de trabalho</label><select class="form-select" id="situacao" name="situacao"><option value="">Todas</option><?php foreach ($situacoesValidas as $opcao): ?><option value="<?php echo $opcao; ?>" <?php echo $situacao === $opcao ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($opcao), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
			<div class="col-lg-3 col-md-6"><label for="faixa_renda" class="form-label">Renda per capita</label><select class="form-select" id="faixa_renda" name="faixa_renda"><option value="">Todas</option><option value="baixa" <?php echo $faixaRenda === 'baixa' ? 'selected' : ''; ?>>Até R$ 500</option><option value="media" <?php echo $faixaRenda === 'media' ? 'selected' : ''; ?>>R$ 501 a R$ 1.000</option><option value="alta" <?php echo $faixaRenda === 'alta' ? 'selected' : ''; ?>>Acima de R$ 1.000</option></select></div>
			<div class="col-lg-2 col-md-6"><label for="ordenar" class="form-label">Ordenar</label><select class="form-select" id="ordenar" name="ordenar"><option value="score" <?php echo $ordenar === 'score' ? 'selected' : ''; ?>>Prioridade</option><option value="renda" <?php echo $ordenar === 'renda' ? 'selected' : ''; ?>>Menor renda</option><option value="nome" <?php echo $ordenar === 'nome' ? 'selected' : ''; ?>>Nome</option></select></div>
			<div class="col-12"><button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Aplicar filtros</button><a class="btn btn-outline-secondary" href="<?php echo PAGES_URL; ?>triagem.php">Limpar</a></div>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-header"><h2 class="h5 mb-0">Classificação de beneficiários</h2></div>
	<div class="card-body">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead><tr><th>Beneficiário</th><th>Renda per capita</th><th>Situação</th><th>Dependentes menores</th><th>Renda (40)</th><th>Trabalho (30)</th><th>Dependentes menores (30)</th><th>Total</th></tr></thead>
				<tbody>
					<?php foreach ($beneficiarios as $beneficiario): ?>
					<tr>
						<td><?php echo htmlspecialchars($beneficiario['nome'], ENT_QUOTES, 'UTF-8'); ?><br><small class="text-muted"><?php echo htmlspecialchars($beneficiario['cpf'], ENT_QUOTES, 'UTF-8'); ?></small></td>
						<td><?php echo formatCurrency($beneficiario['renda_per_capita']); ?></td>
						<td><?php echo htmlspecialchars(ucfirst($beneficiario['situacao_trabalho']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php echo (int)$beneficiario['dependentes']; ?></td>
						<td><?php echo $beneficiario['pontuacao']['renda']; ?></td>
						<td><?php echo $beneficiario['pontuacao']['trabalho']; ?></td>
						<td><?php echo $beneficiario['pontuacao']['dependentes']; ?></td>
						<td><span class="badge <?php echo $beneficiario['pontuacao']['total'] >= 70 ? 'bg-danger' : ($beneficiario['pontuacao']['total'] >= 40 ? 'bg-warning text-dark' : 'bg-secondary'); ?>"><?php echo $beneficiario['pontuacao']['total']; ?></span></td>
					</tr>
					<?php endforeach; ?>
					<?php if (!$beneficiarios): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhum beneficiário encontrado para esses filtros.</td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/header.php';
