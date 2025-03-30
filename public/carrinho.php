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

require_once '../includes/header.php';
?>

<style>
    .cart-item-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 5px;
    }
    
    .cart-summary {
        position: sticky;
        top: 85px;
    }
    
    .quantity-input {
        width: 70px;
    }
    
    .empty-cart-icon {
        font-size: 5rem;
        color: #dee2e6;
    }
    
    @media (max-width: 768px) {
        .cart-item-img {
            width: 60px;
            height: 60px;
        }
    }
</style>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cart"></i> Meu Carrinho 
                    <?php if (!empty($carrinho)): ?>
                        <span class="badge bg-primary rounded-pill"><?php echo count($carrinho); ?></span>
                    <?php endif; ?>
                </h5>
                <a href="cardapio.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Continuar Comprando
                </a>
            </div>
            
            <div class="card-body">
                <?php displayFlashMessage(); ?>
                
                <?php if (empty($carrinho)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cart-x empty-cart-icon"></i>
                        <h4 class="mt-3">Seu carrinho está vazio</h4>
                        <p class="text-muted">Adicione itens do nosso cardápio para começar seu pedido</p>
                        <a href="cardapio.php" class="btn btn-primary mt-3">
                            <i class="bi bi-arrow-left"></i> Ver Cardápio
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Preço</th>
                                    <th>Quantidade</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carrinho as $item): ?>
                                    <?php 
                                        $subtotal = $item['preco'] * $item['quantidade'];
                                        $total += $subtotal;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['imagem_url'] ?? '')): ?>
                                                    <img src="<?php echo htmlspecialchars($item['imagem_url']); ?>" 
                                                         class="cart-item-img me-3" 
                                                         alt="<?php echo htmlspecialchars($item['nome']); ?>">
                                                <?php else: ?>
                                                    <div class="cart-item-img me-3 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['nome']); ?></h6>
                                                    <small class="text-muted">Código: #<?php echo $item['id_produto']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo formatCurrency($item['preco']); ?></td>
                                        <td>
                                            <form action="atualizar-carrinho.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id_produto" value="<?php echo $item['id_produto']; ?>">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" name="quantidade" 
                                                           class="form-control quantity-input" 
                                                           value="<?php echo $item['quantidade']; ?>" 
                                                           min="1" max="10">
                                                    <button type="submit" class="btn btn-outline-secondary">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="fw-bold"><?php echo formatCurrency($subtotal); ?></span>
                                        </td>
                                        <td>
                                            <a href="carrinho.php?remover=<?php echo $item['id_produto']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Tem certeza que deseja remover este item?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <?php if (!empty($carrinho)): ?>
            <div class="card shadow-sm cart-summary">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <span><?php echo formatCurrency($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Taxa de entrega</span>
                        <span>Grátis</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold text-success fs-4"><?php echo formatCurrency($total); ?></span>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="finalizar-pedido.php" class="btn btn-primary">
                            <i class="bi bi-check2-circle"></i> Finalizar Pedido
                        </a>
                        <a href="cardapio.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Continuar Comprando
                        </a>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="small text-muted">
                        <i class="bi bi-shield-check"></i> Pagamento seguro
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-truck"></i> Entrega rápida e segura
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>