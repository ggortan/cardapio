<?php
// admin/produtos.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Verificar permissão de administrador
checkUserPermission('administrador');

$database = new Database();
$conn = $database->getConnection();

// Definir caminhos importantes para upload e acesso às imagens
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/eep/cardapio/';  // Caminho absoluto para a raiz do projeto
$uploads_dir = 'assets/uploads/produtos';  // Diretório relativo para uploads (como armazenado no BD)
$physical_uploads_dir = $root_path . $uploads_dir;  // Caminho físico completo para a pasta de uploads

// Criar o diretório de uploads se não existir
if (!file_exists($physical_uploads_dir)) {
    mkdir($physical_uploads_dir, 0755, true);
}

// Função para manipular uploads de imagem
function handleImageUpload($file, $physical_dir, $db_path, $old_image = null) {
    // Verificar erros no upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Erro no upload: código ' . $file['error']];
    }
    
    // Verificar tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['status' => false, 'message' => 'Tipo de arquivo não permitido. Use apenas JPG, PNG, GIF ou WEBP.'];
    }
    
    // Verificar tamanho (5MB máximo)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['status' => false, 'message' => 'O arquivo excede o tamanho máximo de 5MB.'];
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('produto_') . '.' . $extension;
    $full_path = $physical_dir . '/' . $filename;
    
    // Remover imagem antiga se existir
    if ($old_image && file_exists($old_image) && is_file($old_image)) {
        @unlink($old_image);
    }
    
    // Mover o arquivo para o destino
    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        return [
            'status' => false, 
            'message' => 'Falha ao salvar o arquivo. Verifique as permissões do diretório.'
        ];
    }
    
    // Retornar o caminho para o banco de dados (relativo) e o caminho físico
    return [
        'status' => true,
        'db_path' => $db_path . '/' . $filename,  // Caminho para armazenar no BD
        'full_path' => $full_path                 // Caminho físico no sistema
    ];
}

// Processar exclusão de produto
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id_produto = (int)$_GET['excluir'];
    
    try {
        // Verificar se o produto tem imagem para excluir
        $stmt = $conn->prepare("SELECT imagem_url FROM Produto WHERE id_produto = :id");
        $stmt->bindParam(':id', $id_produto);
        $stmt->execute();
        $imagem_url = $stmt->fetchColumn();
        
        // Se houver imagem e ela estiver no diretório de uploads, excluir
        if ($imagem_url && strpos($imagem_url, 'assets/uploads/') === 0) {
            $full_image_path = $root_path . $imagem_url;
            if (file_exists($full_image_path)) {
                @unlink($full_image_path);
            }
        }
        
        // Excluir o produto do banco de dados
        $stmt = $conn->prepare("DELETE FROM Produto WHERE id_produto = :id");
        $stmt->bindParam(':id', $id_produto);
        $stmt->execute();
        
        flashMessage('Produto excluído com sucesso', 'success');
    } catch (Exception $e) {
        flashMessage('Erro ao excluir produto: ' . $e->getMessage(), 'error');
    }
    
    redirectTo('produtos.php');
}

// Processar adição/edição de produto
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_produto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
    $nome = sanitizeInput($_POST['nome']);
    $descricao = sanitizeInput($_POST['descricao']);
    $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
    $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
    $imagem_url = sanitizeInput($_POST['imagem_url'] ?? '');
    
    // Validações básicas
    $errors = [];
    if (empty($nome)) $errors[] = "Nome é obrigatório";
    if ($preco === false || $preco <= 0) $errors[] = "Preço deve ser um número positivo";
    if ($id_categoria === false) $errors[] = "Categoria válida é obrigatória";
    
    // Verificar upload de imagem
    $upload_result = ['status' => false];
    $isImageUploaded = isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['size'] > 0;
    
    if ($isImageUploaded) {
        // Se editando, buscar imagem atual para possível exclusão
        $old_image_path = null;
        if ($id_produto) {
            $stmt = $conn->prepare("SELECT imagem_url FROM Produto WHERE id_produto = :id");
            $stmt->bindParam(':id', $id_produto);
            $stmt->execute();
            $current_image = $stmt->fetchColumn();
            
            if ($current_image && strpos($current_image, 'assets/uploads/') === 0) {
                $old_image_path = $root_path . $current_image;
            }
        }
        
        // Processar upload
        $upload_result = handleImageUpload(
            $_FILES['imagem_upload'],
            $physical_uploads_dir,
            $uploads_dir,
            $old_image_path
        );
        
        if (!$upload_result['status']) {
            $errors[] = $upload_result['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            // Se fez upload de imagem, usar o caminho retornado
            if ($isImageUploaded && $upload_result['status']) {
                $imagem_url = $upload_result['db_path'];
            }
            
            // Preparar query (atualizar ou inserir)
            if ($id_produto) {
                $stmt = $conn->prepare("
                    UPDATE Produto SET 
                        nome = :nome, 
                        descricao = :descricao, 
                        preco = :preco, 
                        id_categoria = :id_categoria, 
                        imagem_url = :imagem_url
                    WHERE id_produto = :id_produto
                ");
                $stmt->bindParam(':id_produto', $id_produto);
                $mensagem = 'Produto atualizado com sucesso';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO Produto (
                        nome, descricao, preco, id_categoria, imagem_url
                    ) VALUES (
                        :nome, :descricao, :preco, :id_categoria, :imagem_url
                    )
                ");
                $mensagem = 'Produto adicionado com sucesso';
            }
            
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':preco', $preco);
            $stmt->bindParam(':id_categoria', $id_categoria);
            $stmt->bindParam(':imagem_url', $imagem_url);
            
            $stmt->execute();
            flashMessage($mensagem, 'success');
            redirectTo('produtos.php');
        } catch (Exception $e) {
            flashMessage('Erro ao salvar produto: ' . $e->getMessage(), 'error');
        }
    } else {
        // Se houver erros, exibi-los
        foreach ($errors as $error) {
            flashMessage($error, 'error');
        }
    }
}

// Carregar dados para edição
$produto = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_produto = (int)$_GET['editar'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM Produto WHERE id_produto = :id");
        $stmt->bindParam(':id', $id_produto);
        $stmt->execute();
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$produto) {
            flashMessage('Produto não encontrado', 'error');
            redirectTo('produtos.php');
        }
    } catch (Exception $e) {
        flashMessage('Erro ao carregar produto: ' . $e->getMessage(), 'error');
    }
}

// Carregar categorias para o formulário
try {
    $stmt = $conn->query("SELECT id_categoria, nome FROM Categoria ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao carregar categorias: ' . $e->getMessage(), 'error');
    $categorias = [];
}

// Listar todos os produtos
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.nome AS categoria_nome
        FROM Produto p
        JOIN Categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nome
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    flashMessage('Erro ao listar produtos: ' . $e->getMessage(), 'error');
    $produtos = [];
}

// Função para gerar URL para imagens (útil para gerar URLs corretas)
function getImageUrl($relativePath) {
    // Caminho base da aplicação
    $base = '/eep/cardapio/';
    
    // Se for URL externa (começando com http ou https), retorna como está
    if (preg_match('/^https?:\/\//', $relativePath)) {
        return $relativePath;
    }
    
    // Senão, constrói URL a partir do caminho relativo
    return $base . $relativePath;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gerenciar Produtos</h1>
    <button type="button" class="btn btn-primary" id="btnNovoProduto">
        <i class="bi bi-plus-lg"></i> Novo Produto
    </button>
</div>

<?php displayFlashMessage(); ?>

<!-- Produtos Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Produtos</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagem</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto_item): ?>
                        <tr>
                            <td><?php echo $produto_item['id_produto']; ?></td>
                            <td>
                                <?php if (!empty($produto_item['imagem_url'])): ?>
                                    <img src="<?php echo getImageUrl($produto_item['imagem_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($produto_item['nome']); ?>" 
                                         class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                <?php else: ?>
                                    <span class="text-muted">Sem imagem</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($produto_item['nome']); ?></td>
                            <td><?php echo htmlspecialchars($produto_item['categoria_nome']); ?></td>
                            <td><?php echo formatCurrency($produto_item['preco']); ?></td>
                            <td>
                                <a href="produtos.php?editar=<?php echo $produto_item['id_produto']; ?>" 
                                   class="btn btn-sm btn-primary btn-editar">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="produtos.php?excluir=<?php echo $produto_item['id_produto']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Tem certeza que deseja excluir este produto?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($produtos)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nenhum produto cadastrado</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Produto -->
<div class="modal fade" id="produtoModal" tabindex="-1" aria-labelledby="produtoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="produtoModalLabel">
                    <?php echo $produto ? 'Editar Produto' : 'Novo Produto'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="produtos.php" enctype="multipart/form-data" id="formProduto">
                    <?php if ($produto): ?>
                        <input type="hidden" name="id_produto" value="<?php echo $produto['id_produto']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Produto</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?php echo $produto ? htmlspecialchars($produto['nome']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php 
                            echo $produto ? htmlspecialchars($produto['descricao']) : ''; 
                        ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="preco" class="form-label">Preço</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" class="form-control" id="preco" name="preco" 
                                       step="0.01" min="0.01"
                                       value="<?php echo $produto ? htmlspecialchars($produto['preco']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="id_categoria" class="form-label">Categoria</label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">Selecione uma categoria</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>"
                                        <?php echo $produto && $produto['id_categoria'] == $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Imagem do Produto</label>
                        
                        <div class="row align-items-center">
                            <!-- Prévia da imagem atual -->
                            <?php if ($produto && !empty($produto['imagem_url'])): ?>
                                <div class="col-md-3 mb-3">
                                    <img src="<?php echo getImageUrl($produto['imagem_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($produto['nome']); ?>" 
                                         class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                            
                            <!-- Opções de imagem -->
                            <div class="col-md-<?php echo ($produto && !empty($produto['imagem_url'])) ? '9' : '12'; ?>">
                                <div class="card">
                                    <div class="card-header">
                                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" 
                                                        data-bs-target="#upload-pane" type="button" role="tab" 
                                                        aria-controls="upload-pane" aria-selected="true">
                                                    Upload de Arquivo
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="url-tab" data-bs-toggle="tab" 
                                                        data-bs-target="#url-pane" type="button" role="tab" 
                                                        aria-controls="url-pane" aria-selected="false">
                                                    URL Externa
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-body">
                                        <div class="tab-content">
                                            <!-- Tab Upload -->
                                            <div class="tab-pane fade show active" id="upload-pane" role="tabpanel" aria-labelledby="upload-tab">
                                                <div class="mb-3">
                                                    <label for="imagem_upload" class="form-label">Selecione uma imagem</label>
                                                    <input type="file" class="form-control" id="imagem_upload" name="imagem_upload" 
                                                           accept="image/jpeg, image/png, image/gif, image/webp">
                                                    <div id="uploadHelp" class="form-text">
                                                        Formatos suportados: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Tab URL -->
                                            <div class="tab-pane fade" id="url-pane" role="tabpanel" aria-labelledby="url-tab">
                                                <div class="mb-3">
                                                    <label for="imagem_url" class="form-label">URL da Imagem</label>
                                                    <input type="url" class="form-control" id="imagem_url" name="imagem_url" 
                                                           placeholder="https://exemplo.com/imagem.jpg"
                                                           value="<?php echo $produto ? htmlspecialchars($produto['imagem_url']) : ''; ?>">
                                                    <div class="form-text">Insira a URL completa da imagem (incluindo https://)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar o modal
        let produtoModal = new bootstrap.Modal(document.getElementById('produtoModal'));
        
        // Mostrar o modal ao clicar no botão Novo Produto
        document.getElementById('btnNovoProduto').addEventListener('click', function() {
            // Limpar o formulário
            document.getElementById('formProduto').reset();
            
            // Se há campos hidden para id_produto, remover ou limpar
            const idField = document.querySelector('input[name="id_produto"]');
            if (idField) idField.remove();
            
            // Atualizar o título do modal
            document.getElementById('produtoModalLabel').textContent = 'Novo Produto';
            
            // Abrir o modal
            produtoModal.show();
        });
        
        // Abrir modal para edição quando clicar no botão editar
        document.querySelectorAll('.btn-editar').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Redirecionar para a URL com o parâmetro editar
                window.location.href = this.getAttribute('href');
            });
        });
        
        // Se estiver no modo de edição (produto está definido), abrir o modal
        <?php if ($produto): ?>
        produtoModal.show();
        <?php endif; ?>
        
        // Configurar o comportamento dos tabs da imagem
        const uploadTab = document.getElementById('upload-tab');
        const urlTab = document.getElementById('url-tab');
        const uploadInput = document.getElementById('imagem_upload');
        const urlInput = document.getElementById('imagem_url');
        
        if (uploadTab && urlTab) {
            // Limpar URL ao selecionar a tab de upload
            uploadTab.addEventListener('click', function() {
                if (urlInput) urlInput.value = '';
            });
            
            // Limpar upload ao selecionar a tab de URL
            urlTab.addEventListener('click', function() {
                if (uploadInput) uploadInput.value = '';
            });
        }
        
        // Mostrar prévia da imagem selecionada
        if (uploadInput) {
            uploadInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Procurar por uma prévia existente ou criar uma nova
                        let preview = document.querySelector('.preview-image');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.className = 'preview-image img-thumbnail mt-2';
                            preview.style.maxHeight = '150px';
                            uploadInput.parentNode.appendChild(preview);
                        }
                        preview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>