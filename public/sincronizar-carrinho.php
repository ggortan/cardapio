<?php
// public/sincronizar-carrinho.php - versão corrigida
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Definir cabeçalho de resposta como JSON se for uma requisição AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
}

// Verificar se usuário está logado
if (!isLoggedIn()) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode([
            'success' => false,
            'message' => 'Você precisa fazer login para acessar o carrinho',
            'redirect' => '../login.php'
        ]);
        exit;
    } else {
        flashMessage('Você precisa fazer login para acessar o carrinho', 'error');
        redirectTo('../login.php');
    }
}

// Processar itens do carrinho enviados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cart_items'])) {
    try {
        $cart_items = json_decode($_POST['cart_items'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao processar dados do carrinho");
        }
        
        if (!is_array($cart_items) || empty($cart_items)) {
            throw new Exception("Dados do carrinho inválidos ou vazios");
        }
        
        // Inicializar carrinho na sessão
        $_SESSION['carrinho'] = [];
        
        // Validar e adicionar itens ao carrinho da sessão
        $database = new Database();
        $conn = $database->getConnection();
        
        foreach ($cart_items as $item) {
            // Ignorar itens sem id_produto
            if (!isset($item['id_produto'])) continue;
            
            // Verificar se o produto existe
            $stmt = $conn->prepare("SELECT id_produto, nome, preco FROM Produto WHERE id_produto = :id");
            $stmt->bindParam(':id', $item['id_produto']);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($produto) {
                // Verificar se quantidade é válida
                $quantidade = max(1, min(10, (int)($item['quantidade'] ?? 1)));
                
                // Adicionar ao carrinho
                $_SESSION['carrinho'][] = [
                    'id_produto' => $produto['id_produto'],
                    'nome' => $produto['nome'],
                    'preco' => $produto['preco'],
                    'quantidade' => $quantidade,
                    'imagem_url' => $item['imagem_url'] ?? null
                ];
            }
        }
        
        flashMessage('Carrinho sincronizado com sucesso!', 'success');
        
        // Se for Ajax, retornar sucesso
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode([
                'success' => true,
                'message' => 'Carrinho sincronizado com sucesso!',
                'item_count' => count($_SESSION['carrinho'])
            ]);
            exit;
        }
    } catch (Exception $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao sincronizar carrinho: ' . $e->getMessage()
            ]);
            exit;
        } else {
            flashMessage('Erro ao sincronizar carrinho: ' . $e->getMessage(), 'error');
        }
    }
}

// Redirecionar para o carrinho (para requisições não-Ajax)
redirectTo('carrinho.php');