<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_module_page = (strpos($_SERVER['PHP_SELF'], 'modules/') !== false);
?>
<aside class="sidebar">
    <div class="menu-item <?= ($current_page == 'index.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? '../index.php' : 'index.php' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'abrir_caixa.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'abrir_caixa.php' : 'modules/abrir_caixa.php' ?>">
            <i class="fas fa-door-open"></i> Abrir Caixa
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'registrar_venda.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'registrar_venda.php' : 'modules/registrar_venda.php' ?>">
            <i class="fas fa-shopping-cart"></i> Registrar Venda
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'contas_receber.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'contas_receber.php' : 'modules/contas_receber.php' ?>">
            <i class="fas fa-money-bill-wave"></i> Contas a Receber
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'retiradas.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'retiradas.php' : 'modules/retiradas.php' ?>">
            <i class="fas fa-money-bill-wave"></i> Retiradas
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'fechar_caixa.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'fechar_caixa.php' : 'modules/fechar_caixa.php' ?>">
            <i class="fas fa-door-closed"></i> Fechar Caixa
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'relatorios.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'relatorios.php' : 'modules/relatorios.php' ?>">
            <i class="fas fa-chart-bar"></i> Relatórios
        </a>
    </div>
    <div class="menu-item <?= ($current_page == 'relatorios.php') ? 'active' : '' ?>">
        <a href="<?= $is_module_page ? 'credito_cliente.php' : 'credito_cliente.php' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-credit-card-fill" viewBox="0 0 16 16">
                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0zm0 3v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7zm3 2h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1a1 1 0 0 1 1-1" />
            </svg></i> Credito Cliente
        </a>
    </div>
</aside>