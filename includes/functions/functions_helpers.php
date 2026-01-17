<?php
// includes/functions/functions_helpers.php

/**
 * Funções auxiliares (helpers)
 */

// Função para formatar moeda
function formatarMoeda($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para calcular dias em atraso
function calcularDiasAtraso($data_venda)
{
    $data_venda = new DateTime($data_venda);
    $hoje = new DateTime();
    $diferenca = $hoje->diff($data_venda);
    return $diferenca->days;
}

// Validar CPF
function validarCPF($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Verifica se os dígitos calculados conferem com os informados
    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

// Função para sanitizar inputs
function sanitizarInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Função para converter valor brasileiro para float
function converterValorBrasileiro($valor) {
    $valor = str_replace(['R$', '.', ' '], '', $valor);
    $valor = str_replace(',', '.', $valor);
    return floatval($valor);
}