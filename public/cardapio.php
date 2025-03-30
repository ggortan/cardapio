<?php
// public/cardapio.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    flashMessage('Você precisa fazer login para acessar esta página', 'error');
    redirectTo('../login.php');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Buscar categorias com produtos
    $stmt = $conn->prepare("
        SELECT c.id_categoria, c.nome AS categoria_nome, 
               p.id_produto, p.nome, p.descricao, p.preco, p.imagem_url
        FROM Categoria c
        LEFT JOIN Produto p ON c.id_categoria = p.id_categoria
        WHERE p.id_produto IS NOT NULL
        ORDER BY c.nome, p.nome
    ");
    $stmt->execute();
    
    // Agrupar produtos por categoria
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categorias = [];
    
    foreach ($resultados as $row) {
        $id_categoria = $row['id_categoria'];
        $categoria_nome = $row['categoria_nome'];
        
        if (!isset($categorias[$id_categoria])) {
            $categorias[$id_categoria] = [
                'nome' => $categoria_nome,
                'produtos' => []
            ];
        }
        
        $categorias[$id_categoria]['produtos'][] = [
            'id_produto' => $row['id_produto'],
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'preco' => $row['preco'],
            'imagem_url' => $row['imagem_url']
        ];
    }
} catch (Exception $e) {
    flashMessage('Erro ao carregar cardápio: ' . $e->getMessage(), 'error');
    $categorias = [];
}

// Contador de itens no cardápio
$total_produtos = 0;
foreach ($categorias as $categoria) {
    $total_produtos += count($categoria['produtos']);
}

// Banner promocional (opcional)
$promocoes = [
    [
        'titulo' => 'Promoção de Terça',
        'descricao' => 'Hamburgueres com 15% de desconto',
        'imagem' => '../assets/images/promo1.jpg'
    ],
    [
        'titulo' => 'Happy Hour',
        'descricao' => 'De Segunda a Sexta das 17h às 19h',
        'imagem' => '../assets/images/promo2.jpg'
    ],
    [
        'titulo' => 'Combo Família',
        'descricao' => 'Peça para 4 pessoas e ganhe uma sobremesa',
        'imagem' => '../assets/images/promo3.jpg'
    ]
];

// Selecionar uma promoção aleatória
$promo_index = array_rand($promocoes);
$promocao = $promocoes[$promo_index];

// Definir o CSRF token para o JavaScript
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

require_once '../includes/header.php';
?>

<style>
    .category-heading {
        position: sticky;
        top: 70px;
        background-color: #f8f9fa;
        padding: 10px 15px;
        margin: 0 -15px 15px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        z-index: 10;
    }
    
    .product-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .product-image {
        height: 180px;
        object-fit: cover;
        border-top-left-radius: calc(0.25rem - 1px);
        border-top-right-radius: calc(0.25rem - 1px);
    }
    
    .banner {
        background-size: cover;
        background-position: center;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 30px;
        position: relative;
    }
    
    .banner-content {
        background-color: rgba(0,0,0,0.6);
        color: white;
        padding: 30px;
    }
    
    .category-nav {
        position: sticky;
        top: 56px;
        background-color: #fff;
        z-index: 1020;
        padding: 10px 0;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }
    
    .cart-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .toast-container {
        z-index: 1090;
    }
    
    .cart-item {
        margin-bottom: 10px;
    }
    
    .cart-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        font-size: 0.7rem;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #dc3545;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .quantity-input {
        width: 55px !important;
    }
    
    @media (max-width: 768px) {
        .category-heading {
            top: 56px;
        }
    }
</style>

<!-- Banner Promocional -->
<div class="banner" style="background-image: url('<?php echo $promocao['imagem']; ?>');">
    <div class="banner-content">
        <h2><?php echo htmlspecialchars($promocao['titulo']); ?></h2>
        <p class="lead"><?php echo htmlspecialchars($promocao['descricao']); ?></p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cartModal">
            <i class="bi bi-cart"></i> Ver meu carrinho
        </button>
    </div>
</div>

<!-- Navegação das Categorias -->
<div class="category-nav">
    <div class="d-flex flex-nowrap overflow-auto">
        <?php foreach ($categorias as $id_categoria => $categoria): ?>
            <a href="#categoria-<?php echo $id_categoria; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <?php echo htmlspecialchars($categoria['nome']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Conteúdo Principal -->
<h1>Nosso Cardápio</h1>
<p class="lead">Escolha seus produtos favoritos e adicione ao carrinho para fazer seu pedido.</p>

<?php if (empty($categorias)): ?>
    <div class="alert alert-info">
        Estamos atualizando nosso cardápio. Por favor, volte em breve!
    </div>
<?php else: ?>
    <!-- Contador de produtos -->
    <div class="alert alert-light border">
        <i class="bi bi-info-circle"></i> Temos <?php echo $total_produtos; ?> produtos disponíveis em nosso cardápio!
    </div>
    
    <?php foreach ($categorias as $id_categoria => $categoria): ?>
        <div id="categoria-<?php echo $id_categoria; ?>" class="mb-5">
            <h2 class="category-heading"><?php echo htmlspecialchars($categoria['nome']); ?></h2>
            
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($categoria['produtos'] as $produto): ?>
                    <div class="col">
                        <div class="card product-card h-100">
                            <?php if (!empty($produto['imagem_url'])): ?>
                                <img src="<?php echo htmlspecialchars($produto['imagem_url']); ?>" 
                                     class="product-image" 
                                     alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($produto['nome']); ?></h5>
                                <?php if (!empty($produto['descricao'])): ?>
                                    <p class="card-text"><?php echo htmlspecialchars($produto['descricao']); ?></p>
                                <?php endif; ?>
                                <p class="card-text fw-bold text-success fs-5">
                                    <?php echo formatCurrency($produto['preco']); ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="input-group">
                                    <input type="number" class="form-control quantity-input" value="1" min="1" max="10">
                                    <button type="button" class="btn btn-primary add-to-cart-btn"
                                            data-product-id="<?php echo $produto['id_produto']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($produto['nome']); ?>"
                                            data-product-price="<?php echo $produto['preco']; ?>"
                                            data-product-image="<?php echo htmlspecialchars($produto['imagem_url'] ?? ''); ?>">
                                        <i class="bi bi-cart-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Flutuante de carrinho -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <button type="button" class="btn btn-lg btn-primary cart-btn shadow" data-bs-toggle="modal" data-bs-target="#cartModal">
        <i class="bi bi-cart fs-4"></i>
        <span class="cart-badge d-none" id="cart-badge">0</span>
    </button>
</div>

<!-- Modal de Carrinho -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="bi bi-cart"></i> Meu Carrinho 
                    <span class="badge bg-primary rounded-pill" id="cart-modal-badge">0</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Mensagem de carrinho vazio -->
                <div id="empty-cart-message" class="text-center py-4">
                    <i class="bi bi-cart-x" style="font-size: 3rem; color: #dee2e6;"></i>
                    <p class="mt-3 mb-0">Seu carrinho está vazio</p>
                    <p class="text-muted">Adicione itens do nosso cardápio para começar seu pedido</p>
                </div>
                
                <!-- Lista de itens do carrinho -->
                <div id="cart-items-container">
                    <div id="cart-items-list" class="mb-4">
                        <!-- JavaScript preencherá esta área -->
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <h5 class="mb-0">Total</h5>
                        <h4 class="mb-0 text-success" id="cart-total">R$ 0,00</h4>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <span class="text-muted me-3">Total de itens: <span id="cart-item-count">0</span></span>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="clear-cart-btn">
                        <i class="bi bi-trash"></i> Limpar Carrinho
                    </button>
                </div>
                <div id="cart-action-buttons" class="d-none">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continuar Comprando</button>
                    <button type="button" class="btn btn-primary" id="sync-cart-btn">
                        <i class="bi bi-check-circle"></i> Finalizar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Área de notificações -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<!-- Seção de Informações -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-clock fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Horário de Funcionamento</h5>
                <p class="card-text">
                    Segunda a Sexta: 11h - 22h<br>
                    Sábados e Domingos: 11h - 23h
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-geo-alt fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Nossa Localização</h5>
                <p class="card-text">
                    Av. Principal, 1234<br>
                    Centro - Sua Cidade/UF
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-telephone fs-1 text-primary mb-3"></i>
                <h5 class="card-title">Contato</h5>
                <p class="card-text">
                    (00) 00000-0000<br>
                    contato@cardapiodigital.com
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Script do carrinho dinâmico -->
<script>
    // Variável para o token CSRF
    const csrfToken = '<?php echo $csrf_token; ?>';
</script>
<script src="../assets/js/dynamic-cart.js"></script>
<script>
    // Sincronizar carrinho quando clicar no botão de finalizar
    document.addEventListener('DOMContentLoaded', function() {
        const syncCartBtn = document.getElementById('sync-cart-btn');
        if (syncCartBtn) {
            syncCartBtn.addEventListener('click', function() {
                DynamicCart.syncCart();
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>