<?php
// public/adicionar-carrinho.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para adicionar itens ao carrinho', 'error');
    redirectTo('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_produto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT) ?: 1;

    if ($id_produto) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Buscar detalhes do produto
            $stmt = $conn->prepare("SELECT id_produto, nome, preco FROM Produto WHERE id_produto = :id");
            $stmt->bindParam(':id', $id_produto);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($produto) {
                // Inicializar carrinho na sessão se não existir
                if (!isset($_SESSION['carrinho'])) {
                    $_SESSION['carrinho'] = [];
                }

                // Verificar se produto já está no carrinho
                $produtoExistente = false;
                foreach ($_SESSION['carrinho'] as &$item) {
                    if ($item['id_produto'] == $id_produto) {
                        $item['quantidade'] += $quantidade;
                        $produtoExistente = true;
                        break;
                    }
                }

                // Se não existir, adicionar novo item
                if (!$produtoExistente) {
                    $_SESSION['carrinho'][] = [
                        'id_produto' => $produto['id_produto'],
                        'nome' => $produto['nome'],
                        'preco' => $produto['preco'],
                        'quantidade' => $quantidade
                    ];
                }

                flashMessage('Produto adicionado ao carrinho!', 'success');
                redirectTo('carrinho.php');
            } else {
                flashMessage('Produto não encontrado', 'error');
            }
        } catch (Exception $e) {
            flashMessage('Erro ao adicionar produto: ' . $e->getMessage(), 'error');
        }
    } else {
        flashMessage('ID de produto inválido', 'error');
    }
}

redirectTo('cardapio.php');