<?php
// public/sincronizar-carrinho.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    // Redirecionar para login com retorno para o carrinho
    flashMessage('Você precisa fazer login para acessar o carrinho', 'error');
    redirectTo('../login.php?retorno=carrinho.php');
}

// Processar itens do carrinho enviados
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cart_items'])) {
    try {
        $cart_items = json_decode($_POST['cart_items'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao processar dados do carrinho");
        }
        
        if (!is_array($cart_items)) {
            throw new Exception("Formato de carrinho inválido");
        }
        
        // Inicializar carrinho
        $_SESSION['carrinho'] = [];
        
        // Verificar se não está vazio
        if (empty($cart_items)) {
            flashMessage('Seu carrinho está vazio', 'info');
            redirectTo('carrinho.php');
        }
        
        // Validar e adicionar itens ao carrinho da sessão
        $database = new Database();
        $conn = $database->getConnection();
        
        foreach ($cart_items as $item) {
            // Verificar se o produto existe
            $stmt = $conn->prepare("SELECT id_produto, nome, preco FROM Produto WHERE id_produto = :id");
            $stmt->bindParam(':id', $item['id_produto']);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($produto) {
                // Verificar se quantidade é válida
                $quantidade = max(1, min(10, (int)$item['quantidade']));
                
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
        
        // Adicionar script para limpar localStorage também
        echo "<script>
            if (typeof localStorage !== 'undefined') {
                // Não removemos aqui, pois queremos manter até que o pedido seja finalizado
                console.log('Carrinho sincronizado com sucesso!');
            }
        </script>";
        
        flashMessage('Carrinho sincronizado com sucesso!', 'success');
    } catch (Exception $e) {
        flashMessage('Erro ao sincronizar carrinho: ' . $e->getMessage(), 'error');
    }
}

// Redirecionar para o carrinho
redirectTo('carrinho.php');