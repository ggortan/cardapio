<?php
// includes/functions.php

// Função para sanitizar entrada de dados
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para gerar hash de senha seguro
function passwordHash($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

// Função para verificar senha
function passwordVerify($password, $hash) {
    return password_verify($password, $hash);
}

// Função para redirecionar
function redirectTo($page) {
    header("Location: $page");
    exit();
}

// Função para exibir mensagens de erro/sucesso
function flashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Função para renderizar mensagens de flash
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };

        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";

        unset($_SESSION['flash_message']);
    }
}

// Função para formatar moeda
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para verificar se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

// Função para verificar permissão de usuário
function checkUserPermission($requiredRole) {
    if (!isLoggedIn() || $_SESSION['tipo_usuario'] !== $requiredRole) {
        flashMessage('Acesso não autorizado', 'error');
        redirectTo('login.php');
    }
}
// Adicione esta função ao arquivo includes/functions.php

/**
 * Faz upload de uma imagem e retorna o caminho relativo
 * 
 * @param array $file Array $_FILES['campo_upload']
 * @param string $directory Diretório destino (relativo à raiz do projeto)
 * @param string $oldImage Caminho da imagem antiga (opcional, para exclusão)
 * @return array Retorna array com status e mensagem/caminho
 */
function uploadImage($file, $directory = 'assets/uploads/produtos', $oldImage = null) {
    // Verificar se o arquivo foi enviado corretamente
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O upload do arquivo foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Uma extensão PHP interrompeu o upload do arquivo.'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Erro desconhecido no upload.';
            
        return ['status' => false, 'message' => $errorMessage];
    }
    
    // Verificar o tipo do arquivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return [
            'status' => false, 
            'message' => 'Tipo de arquivo não permitido. Apenas imagens JPG, PNG, GIF e WEBP são aceitas.'
        ];
    }
    
    // Verificar o tamanho do arquivo (5MB máximo)
    $maxSize = 5 * 1024 * 1024; // 5MB em bytes
    if ($file['size'] > $maxSize) {
        return [
            'status' => false, 
            'message' => 'O arquivo excede o tamanho máximo permitido de 5MB.'
        ];
    }
    
    // Criar diretório se não existir
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0775, true)) {
            return [
                'status' => false, 
                'message' => 'Não foi possível criar o diretório de destino.'
            ];
        }
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('produto_') . '.' . $extension;
    $destination = $directory . '/' . $filename;
    
    // Remover imagem antiga, se especificado
    if ($oldImage && file_exists($oldImage) && is_file($oldImage)) {
        unlink($oldImage);
    }
    
    // Mover o arquivo para o destino
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'status' => false, 
            'message' => 'Erro ao mover o arquivo para o destino final.'
        ];
    }
    
    return [
        'status' => true, 
        'path' => $destination
    ];
}