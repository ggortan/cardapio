<?php
// public/atualizar-carrinho.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar o carrinho', 'error');
    redirectTo('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_produto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT) ?: 1;

    if ($id_produto && $quantidade > 0) {
        if (isset($_SESSION['carrinho'])) {
            foreach ($_SESSION['carrinho'] as &$item) {
                if ($item['id_produto'] == $id_produto) {
                    $item['quantidade'] = $quantidade;
                    flashMessage('Quantidade atualizada com sucesso', 'success');
                    break;
                }
            }
        }
    } else {
        flashMessage('Dados inválidos', 'error');
    }
}

redirectTo('carrinho.php');