<?php
// public/carrinho.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar o carrinho', 'error');
    redirectTo('../login.php');
}

// Processar remoção de item
if (isset($_GET['remover']) && is_numeric($_GET['remover'])) {
    $idRemover = (int)$_GET['remover'];
    
    if (isset($_SESSION['carrinho'])) {
        foreach ($_SESSION['carrinho'] as $indice => $item) {
            if ($item['id_produto'] == $idRemover) {
                unset($_SESSION['carrinho'][$indice]);
                // Reindexar o array
                $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
                flashMessage('Produto removido do carrinho', 'success');
                break;
            }
        }
    }
    redirectTo('carrinho.php');
}

// Calcular total do carrinho
$total = 0;
$carrinho = $_SESSION['carrinho'] ?? [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Carrinho de Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Seu Carrinho</h1>
        
        <?php displayFlashMessage(); ?>

        <?php if (empty($carrinho)): ?>
            <div class="alert alert-info">
                Seu carrinho está vazio. <a href="cardapio.php">Veja nosso cardápio</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço Unitário</th>
                                <th>Quantidade</th>
                                <th>Subtotal</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($carrinho as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                    <td><?php echo formatCurrency($item['preco']); ?></td>
                                    <td>
                                        <form action="atualizar-carrinho.php" method="POST" class="d-inline">
                                            <input type="hidden" name="id_produto" value="<?php echo $item['id_produto']; ?>">
                                            <div class="input-group input-group-sm" style="width: 130px;">
                                                <input type="number" name="quantidade" 
                                                       class="form-control" 
                                                       value="<?php echo $item['quantidade']; ?>" 
                                                       min="1" max="10">
                                                <button type="submit" class="btn btn-outline-secondary">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <?php 
                                        $subtotal = $item['preco'] * $item['quantidade'];
                                        $total += $subtotal;
                                        echo formatCurrency($subtotal); 
                                        ?>
                                    </td>
                                    <td>
                                        <a href="carrinho.php?remover=<?php echo $item['id_produto']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Remover este item?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <td colspan="3" class="text-end fw-bold">Total:</td>
                                <td colspan="2" class="fw-bold text-success">
                                    <?php echo formatCurrency($total); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="d-flex justify-content-between">
                        <a href="cardapio.php" class="btn btn-outline-secondary">
                            Continuar Comprando
                        </a>
                        <a href="finalizar-pedido.php" class="btn btn-primary">
                            Finalizar Pedido
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</body>
</html>