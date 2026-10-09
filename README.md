# Fundo Social

Sistema local de gestão de beneficiários, estoque e guias, executado com PHP e MySQL/MariaDB no XAMPP.

## Executar no XAMPP

1. Inicie Apache e MySQL no painel do XAMPP.
2. Abra `http://localhost/phpmyadmin` e importe `fundo_social.sql`. O script cria o banco `fundo_social`, as tabelas e os dados de demonstração; os cadastros existentes não são apagados.
3. Instale o Composer, abra o terminal na pasta do projeto e execute `composer install` para instalar a geração de PDF das guias.
4. Acesse `http://localhost/Fundo%20Social/pages/login.php`.
5. Entre com `admin@fundo.com` e `admin123`.

A conexão padrão está em `config/database.php` (usuário `root`, senha vazia, padrão do XAMPP). Para outro ambiente, ajuste esses valores localmente.

## Módulos

- **Beneficiários:** cadastro, pesquisa por nome/CPF, edição e exclusão.
- **Estoque:** entradas e ajustes de saldo com histórico de movimentações.
- **Guias:** emissão transacional; a saída só é concluída quando há saldo suficiente, e fica vinculada ao beneficiário, ao item e ao usuário. Cada guia pode ser impressa ou baixada individualmente em PDF.
- **Triagem:** classificação transparente de 0 a 100, filtrável e ordenável. A regra distribui até 40 pontos por renda per capita, até 30 por situação de trabalho e até 30 por dependentes menores. A classificação apoia a análise profissional e não decide o atendimento.
- **Importar CSV:** carga de planilhas históricas, com validação e rejeição de CPFs duplicados. Use o modelo disponível na própria tela; limite de 5 MB.
- **Auditoria:** consulta de operações administrativas sem exibir dados pessoais detalhados.

## Triagem e dados

A triagem atual é uma regra explicável implementada em PHP, não um modelo de *Machine Learning* treinado. O projeto ainda precisa de uma base histórica rotulada e validada pelos profissionais responsáveis para treinar e avaliar um modelo com métricas adequadas. Os dados importados devem ser autorizados pela instituição e tratados conforme a LGPD.

O CSV deve conter pelo menos `nome`, `cpf` e `telefone`. Também pode conter `email`, `renda_per_capita`, `situacao_trabalho`, `dependentes_menores`, `endereco`, `bairro`, `cidade` e `estado`; são aceitos separadores `;` e `,`.
