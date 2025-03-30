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
        <a href="carrinho.php" class="btn btn-primary">
            <i class="bi bi-cart"></i> Ver meu carrinho
        </a>
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
                                <form action="adicionar-carrinho.php" method="POST">
                                    <input type="hidden" name="id_produto" value="<?php echo $produto['id_produto']; ?>">
                                    <div class="input-group">
                                        <input type="number" name="quantidade" 
                                               class="form-control" value="1" min="1" max="10">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-cart-plus"></i> Adicionar
                                        </button>
                                    </div>
                                </form>
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
    <a href="carrinho.php" class="btn btn-lg btn-primary rounded-circle shadow">
        <i class="bi bi-cart fs-4"></i>
        <?php if (isset($_SESSION['carrinho']) && count($_SESSION['carrinho']) > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo count($_SESSION['carrinho']); ?>
            </span>
        <?php endif; ?>
    </a>
</div>

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

<?php require_once '../includes/footer.php'; ?>