<?php
// functions.php (na RAIZ do projeto)

// Verificar se o diretório de funções existe
$functionsDir = __DIR__ . '/includes/functions/';

// Incluir todas as funções especializadas
if (file_exists($functionsDir . 'functions_gerais.php')) {
    require_once $functionsDir . 'functions_gerais.php';
}
if (file_exists($functionsDir . 'functions_helpers.php')) {
    require_once $functionsDir . 'functions_helpers.php';
}
if (file_exists($functionsDir . 'functions_caixa.php')) {
    require_once $functionsDir . 'functions_caixa.php';
}
if (file_exists($functionsDir . 'functions_vendas.php')) {
    require_once $functionsDir . 'functions_vendas.php';
}
if (file_exists($functionsDir . 'functions_clientes.php')) {
    require_once $functionsDir . 'functions_clientes.php';
}
if (file_exists($functionsDir . 'functions_contas_receber.php')) {
    require_once $functionsDir . 'functions_contas_receber.php';
}
if (file_exists($functionsDir . 'functions_relatorios.php')) {
    require_once $functionsDir . 'functions_relatorios.php';
}

// Incluir também funções de retiradas se existirem
$retiradasFile = __DIR__ . '/includes/functions/functions_retiradas.php';
if (file_exists($retiradasFile)) {
    require_once $retiradasFile;
}