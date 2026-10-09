<?php
function renderGuiaDocumento(array $guia, $mostrarAcoes = true) {
    $escapar = function ($valor) {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    };
    $dataCriacao = date('d/m/Y H:i', strtotime($guia['data_criacao']));
    $numeroGuia = $escapar($guia['numero'] ?? $guia['id']);
    $beneficiario = $escapar($guia['beneficiario_nome'] ?? '');
    $cpf = $escapar($guia['beneficiario_cpf'] ?? '');
    $item = $escapar($guia['item_nome'] ?? '');
    $usuario = $escapar($guia['usuario_nome'] ?? '');
    $status = $escapar(ucfirst($guia['status'] ?? ''));
    $quantidade = (int)$guia['quantidade'];
    $justificativa = nl2br($escapar($guia['justificativa'] ?? ''));

    ob_start();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guia <?php echo $numeroGuia; ?> - Fundo Social</title>
    <style>
        @page { size: A4 portrait; margin: 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; line-height: 1.5; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin: 0 auto 18px; max-width: 760px; }
        .toolbar button, .toolbar a { border: 1px solid #b8c2d1; border-radius: 4px; background: #fff; color: #172033; padding: 9px 13px; font-size: 10pt; text-decoration: none; cursor: pointer; }
        .document { max-width: 760px; margin: 0 auto; }
        .masthead { border-bottom: 2px solid #174b7a; padding-bottom: 16px; margin-bottom: 22px; }
        .organization { color: #174b7a; font-size: 11pt; font-weight: 700; text-transform: uppercase; }
        h1 { margin: 7px 0 0; font-size: 22pt; }
        .guide-number { margin-top: 5px; color: #475569; font-size: 11pt; }
        .section { margin: 22px 0; }
        h2 { margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1px solid #cbd5e1; font-size: 13pt; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
        .field-label { display: block; color: #475569; font-size: 9pt; font-weight: 700; text-transform: uppercase; }
        .field-value { display: block; min-height: 22px; font-size: 12pt; }
        .justification { min-height: 65px; white-space: normal; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 70px; text-align: center; }
        .signature-line { border-top: 1px solid #64748b; padding-top: 7px; font-size: 10pt; }
        .document-footer { margin-top: 32px; border-top: 1px solid #cbd5e1; padding-top: 8px; color: #64748b; font-size: 8pt; text-align: center; }
        @media print { .toolbar { display: none; } }
        @media screen { body { padding: 24px; background: #eef2f7; } .document { padding: 40px; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .12); } }
    </style>
</head>
<body>
    <?php if ($mostrarAcoes): ?>
    <div class="toolbar">
        <a href="<?php echo PAGES_URL; ?>guia_pdf.php?id=<?php echo (int)$guia['id']; ?>">Baixar PDF</a>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
    <?php endif; ?>
    <main class="document">
        <header class="masthead">
            <div class="organization">Fundo Social de Caçapava</div>
            <h1>Guia de autorização</h1>
            <div class="guide-number">Documento nº <?php echo $numeroGuia; ?></div>
        </header>

        <section class="section">
            <h2>Beneficiário</h2>
            <div class="details">
                <div><span class="field-label">Nome</span><span class="field-value"><?php echo $beneficiario; ?></span></div>
                <div><span class="field-label">CPF</span><span class="field-value"><?php echo $cpf; ?></span></div>
            </div>
        </section>

        <section class="section">
            <h2>Item autorizado</h2>
            <div class="details">
                <div><span class="field-label">Produto</span><span class="field-value"><?php echo $item; ?></span></div>
                <div><span class="field-label">Quantidade</span><span class="field-value"><?php echo $quantidade; ?></span></div>
                <div><span class="field-label">Data de emissão</span><span class="field-value"><?php echo $escapar($dataCriacao); ?></span></div>
                <div><span class="field-label">Status</span><span class="field-value"><?php echo $status; ?></span></div>
            </div>
        </section>

        <section class="section">
            <h2>Justificativa</h2>
            <div class="field-value justification"><?php echo $justificativa; ?></div>
        </section>

        <section class="signatures">
            <div class="signature-line">Responsável pela autorização<br><?php echo $usuario; ?></div>
            <div class="signature-line">Recebido pelo beneficiário<br><?php echo $beneficiario; ?></div>
        </section>

        <footer class="document-footer">Documento gerado em <?php echo $escapar(date('d/m/Y H:i')); ?> pelo Sistema de Gestão do Fundo Social.</footer>
    </main>
</body>
</html>
    <?php
    return ob_get_clean();
}
