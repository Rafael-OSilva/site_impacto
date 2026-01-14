<?php
// Inicializar variáveis se não existirem
$status_caixa = $status_caixa ?? false;
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Operador';
?>
<header>
    <div class="logo">
        <i class="fas fa-cash-register"></i> Caixa Impacto
    </div>
    <div class="user-info">
        <span>Operador: <?= htmlspecialchars($nome_usuario) ?></span>
        <span class="caixa-status <?= $status_caixa ? 'status-aberto' : 'status-fechado' ?>">
            <?= $status_caixa ? 'Caixa Aberto' : 'Caixa Fechado' ?>
        </span>
        <a href="../logout.php" class="btn-danger">Sair</a>
    </div>
</header>