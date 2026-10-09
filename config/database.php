<?php
/**
 * Configuração da conexão com o banco de dados MySQL
 * Para uso com XAMPP
 * 
 * Configurações padrão do XAMPP:
 * - Host: localhost
 * - Usuário: root
 * - Senha: (vazio)
 * - Banco: fundo_social
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fundo_social');

/**
 * Configurações da aplicação
 */
define('APP_NAME', 'Fundo Social - Gestão de Guias e Estoque');
define('APP_VERSION', '1.0.0');
define('APP_DATE', '2026-10-10');

/**
 * Limites de controle de estoque (em unidades)
 * - MÁXIMO: capacidade de cada item; ao atingir, o sistema exibe um aviso.
 * - ATENÇÃO: limite de segurança; itens com quantidade menor ou igual entram em atenção.
 */
define('ESTOQUE_LIMITE_MAXIMO', 100);
define('ESTOQUE_LIMITE_ATENCAO', 10);

/**
 * Caminhos de navegação
 */
define('BASE_URL', '/Fundo Social/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('PAGES_URL', BASE_URL . 'pages/');
define('UPLOADS_URL', BASE_URL . 'uploads/');

/**
 * Códigos de status HTTP
 */
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_NO_CONTENT', 204);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_INTERNAL_ERROR', 500);
