<?php
// index.php - Arquivo principal de entrada
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirecionar para a página adequada com base no status de login e tipo de usuário
if (isLoggedIn()) {
    // Se estiver logado, redireciona com base no tipo de usuário
    switch ($_SESSION['tipo_usuario']) {
        case 'administrador':
            redirectTo('admin/dashboard.php');
            break;
        case 'operador':
            redirectTo('admin/pedidos.php');
            break;
        default:
            redirectTo('public/cardapio.php');
    }
} else {
    // Se não estiver logado, redireciona para a página de login
    redirectTo('login.php');
}