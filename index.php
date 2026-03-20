<?php
session_start();

// ============================================================================
// CONFIGURATION
// ============================================================================
require_once 'config.php';

// Vérifier que la clé API est configurée
if (!isApiKeyConfigured()) {
    $apiKeyError = 'La clé API Groq n\'est pas configurée. Veuillez ajouter votre clé API dans config.php ou définir la variable d\'environnement GROQ_API_KEY.';
}

// ============================================================================
// FICHIER DE DONNÉES
// ============================================================================
$dataFile = 'data.json';

function loadData() {
    global $dataFile;
    if (file_exists($dataFile)) {
        $json = file_get_contents($dataFile);
        return json_decode($json, true);
    }
    return [];
}

$data = loadData();

// ============================================================================
// VARIABLES DE TRAITEMENT
// ============================================================================
$generatedResult = null;
$error = isset($apiKeyError) ? $apiKeyError : null;
$isGenerating = false;
$autoDownloadScript = '';

// ============================================================================
// TRAITEMENT DU FORMULAIRE DE GÉNÉRATION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generer'])) {
    
    // Vérifier que la clé API est configurée avant de continuer
    if (!isApiKeyConfigured()) {
        $error = 'La clé API Groq n\'est pas configurée. Veuillez configurer votre clé API.';
    } else {
        $isGenerating = true;
        
        $productName = trim($_POST['product_name'] ?? '');
        $category = $_POST['category'] ?? '';
        $brand = trim($_POST['brand'] ?? '');
        $style = $_POST['style'] ?? 'elegant';
        $characteristics = trim($_POST['characteristics'] ?? '');
        $targetAudience = trim($_POST['target_audience'] ?? '');
        $priceRange = trim($_POST['price_range'] ?? '');
        $originalImageUrl = $_POST['original_image_url'] ?? '';
        
        // Validation
        if (empty($productName) || empty($category)) {
            $error = 'Veuillez remplir au moins le nom du produit et la catégorie.';
        } else {
            // Appel à l'API Groq
            $promptData = [
                'model' => GROQ_MODEL,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en marketing de luxe et en photographie de produits pour une boutique premium "Beauty Clinic" spécialisée en mode et beauté au Togo.

Ta mission est de créer :
1. Une description de produit engageante, professionnelle et persuasive (3-4 phrases maximum)
2. Un prompt optimisé pour améliorer/transformer une photo de produit existante en image professionnelle de catalogue e-commerce

RÈGLES STRICTES :
- Style: Luxe, élégant, raffiné, professionnel
- Ton: Professionnel, engageant, sans vulgarité
- La description doit être en français
- Le prompt doit être en anglais (pour la génération d\'image)
- AUCUN élément inapproprié, vulgaire ou choquant
- Images adaptées à un catalogue de vente en ligne premium
- Mettre en valeur les caractéristiques du produit
- Créer un désir d\'achat subtil et sophistiqué
- Le prompt doit décrire comment transformer/améliorer la photo existante

FORMAT DE RÉPONSE (JSON uniquement):
{
  "description": "Description engageante du produit...",
  "imagePrompt": "Prompt détaillé en anglais pour transformer la photo en image professionnelle de catalogue...",
  "title": "Titre court et accrocheur du produit"
}'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Génère une description et un prompt d'amélioration d'image pour ce produit:

NOM: $productName
CATÉGORIE: $category
MARQUE: $brand
STYLE VISUEL: $style
CARACTÉRISTIQUES: $characteristics
PUBLIC CIBLE: $targetAudience
GAMME DE PRIX: $priceRange
IMAGE ORIGINALE: " . ($originalImageUrl ? 'Une photo existe et doit être améliorée/transformée' : 'Aucune photo fournie') . "

La boutique 'Beauty Clinic' propose des produits de luxe au Togo: parfums, sacs, chaussures, accessoires."
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ];
            
            $ch = curl_init(GROQ_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($promptData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . GROQ_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, GROQ_TIMEOUT);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $error = 'Erreur de connexion: ' . htmlspecialchars($curlError);
                if (DEBUG_MODE) {
                    error_log("CURL Error: $curlError");
                }
            } elseif ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                $content = $result['choices'][0]['message']['content'] ?? '';
                
                // Extraire le JSON de la réponse
                preg_match('/\{[\s\S]*\}/', $content, $matches);
                if ($matches) {
                    $parsedContent = json_decode($matches[0], true);
                    
                    if ($parsedContent && isset($parsedContent['description']) && isset($parsedContent['imagePrompt'])) {
                        $imagePrompt = urlencode($parsedContent['imagePrompt']);
                        
                        // Générer l'URL de l'image
                        $enhancedImageUrl = IMAGE_GENERATION_URL . $imagePrompt . "?width=" . IMAGE_WIDTH . "&height=" . IMAGE_HEIGHT . "&nologo=true&seed=" . time();
                        
                        // Télécharger l'image localement pour le téléchargement automatique
                        $localImagePath = downloadImage($enhancedImageUrl, $productName);
                        
                        if (!$localImagePath) {
                            // Si le téléchargement échoue, utiliser l'URL directe
                            $localImagePath = $enhancedImageUrl;
                        }
                        
                        $generatedResult = [
                            'title' => $parsedContent['title'] ?? $productName,
                            'description' => $parsedContent['description'],
                            'imageUrl' => $enhancedImageUrl,
                            'localImagePath' => $localImagePath,
                            'prompt' => $parsedContent['imagePrompt'],
                            'originalImage' => $originalImageUrl,
                            'productData' => [
                                'name' => $productName,
                                'category' => $category,
                                'brand' => $brand,
                                'price' => $_POST['price'] ?? '',
                            ]
                        ];
                        
                        // Sauvegarder dans la session
                        $_SESSION['last_generated'] = $generatedResult;
                        
                        // Générer le script de téléchargement automatique
                        $autoDownloadScript = generateAutoDownloadScript($localImagePath, $productName);
                        
                    } else {
                        $error = 'Erreur lors du parsing de la réponse IA. Structure JSON invalide.';
                        if (DEBUG_MODE) {
                            error_log("JSON Parse Error. Content: $content");
                        }
                    }
                } else {
                    $error = 'Format de réponse invalide. JSON non trouvé dans la réponse.';
                    if (DEBUG_MODE) {
                        error_log("JSON not found in response. Content: $content");
                    }
                }
            } elseif ($httpCode === 401) {
                $error = 'Erreur d\'authentification: Clé API Groq invalide. Veuillez vérifier votre clé API dans config.php.';
            } elseif ($httpCode === 429) {
                $error = 'Trop de requêtes. Veuillez patienter quelques instants avant de réessayer.';
            } else {
                $error = 'Erreur de connexion à l\'API (HTTP ' . $httpCode . '). Veuillez réessayer.';
                if (DEBUG_MODE && $response) {
                    $apiError = json_decode($response, true);
                    if (isset($apiError['error']['message'])) {
                        $error .= ' Détails: ' . $apiError['error']['message'];
                    }
                }
            }
        }
    }
    
    $isGenerating = false;
}

// ============================================================================
// FONCTIONS UTILITAIRES
// ============================================================================

/**
 * Télécharge une image depuis une URL et la sauvegarde localement
 * @param string $url URL de l'image
 * @param string $productName Nom du produit pour le nom de fichier
 * @return string|null Chemin local de l'image ou null en cas d'erreur
 */
function downloadImage($url, $productName) {
    $uploadDir = UPLOAD_DIR . 'generated/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            error_log("Impossible de créer le dossier: $uploadDir");
            return null;
        }
    }
    
    // Vérifier les permissions d'écriture
    if (!is_writable($uploadDir)) {
        error_log("Dossier non accessible en écriture: $uploadDir");
        return null;
    }
    
    // Générer un nom de fichier sécurisé
    $safeName = sanitizeFileName($productName);
    $fileName = $safeName . '_' . time() . '.jpg';
    $filePath = $uploadDir . $fileName;
    
    // Télécharger l'image avec cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Si cURL échoue, essayer file_get_contents
    if ($imageData === false || $httpCode !== 200) {
        $imageData = @file_get_contents($url);
    }
    
    if ($imageData && strlen($imageData) > 1000) {
        if (@file_put_contents($filePath, $imageData)) {
            return $filePath;
        }
    }
    
    error_log("Échec du téléchargement de l'image depuis: $url");
    return null;
}

/**
 * Génère le script JavaScript pour le téléchargement automatique
 * @param string $imagePath Chemin de l'image locale ou URL
 * @param string $productName Nom du produit
 * @return string Script JavaScript
 */
function generateAutoDownloadScript($imagePath, $productName) {
    $safeName = sanitizeFileName($productName);
    $isUrl = filter_var($imagePath, FILTER_VALIDATE_URL);
    
    if ($isUrl) {
        // Si c'est une URL externe, utiliser fetch pour télécharger
        return "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                fetch('" . htmlspecialchars($imagePath, ENT_QUOTES) . "')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = '" . $safeName . "_beauty_clinic.jpg';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(url);
                        showNotification('Image téléchargée automatiquement !', 'success');
                    })
                    .catch(error => {
                        console.error('Erreur de téléchargement:', error);
                        showNotification('Le téléchargement automatique a échoué. Utilisez le bouton manuel.', 'error');
                    });
            }, 2000);
        });
        </script>";
    } else {
        // Si c'est un fichier local
        return "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const link = document.createElement('a');
                link.href = '" . htmlspecialchars($imagePath, ENT_QUOTES) . "';
                link.download = '" . $safeName . "_beauty_clinic.jpg';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showNotification('Image téléchargée automatiquement !', 'success');
            }, 1500);
        });
        </script>";
    }
}

/**
 * Nettoie un nom de fichier pour le rendre sécurisé
 * @param string $name Nom à nettoyer
 * @return string Nom sécurisé
 */
function sanitizeFileName($name) {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9\-_]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_');
    return substr($name, 0, 50);
}

/**
 * Valide le type MIME réel d'un fichier
 * @param string $tmpPath Chemin temporaire du fichier
 * @return string|null Type MIME ou null
 */
function getRealMimeType($tmpPath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        return $mimeType;
    }
    return null;
}

// ============================================================================
// GESTION DE L'UPLOAD D'IMAGE
// ============================================================================
$uploadedImage = null;
$uploadError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $allowedTypes = UPLOAD_ALLOWED_TYPES;
    $maxSize = UPLOAD_MAX_SIZE;
    
    $file = $_FILES['product_image'];
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale du formulaire.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier.',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload.'
        ];
        $uploadError = $uploadErrors[$file['error']] ?? 'Erreur inconnue lors de l\'upload.';
    } else {
        // Vérification du type MIME réel
        $realMimeType = getRealMimeType($file['tmp_name']);
        
        if (!in_array($realMimeType, $allowedTypes)) {
            $uploadError = 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP.';
        } elseif ($file['size'] > $maxSize) {
            $uploadError = 'Le fichier est trop volumineux. Taille maximale: 5 Mo.';
        } else {
            $uploadDir = UPLOAD_DIR . 'generator/';
            
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0755, true)) {
                    $uploadError = 'Impossible de créer le dossier d\'upload.';
                }
            }
            
            if (!$uploadError) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $extension = strtolower(preg_replace('/[^a-z0-9]/', '', $extension));
                $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $uploadedImage = $targetPath;
                } else {
                    $uploadError = 'Erreur lors de la sauvegarde du fichier.';
                }
            }
        }
    }
    
    if ($uploadError) {
        $error = $uploadError;
    }
}

// ============================================================================
// DONNÉES POUR LE FORMULAIRE
// ============================================================================
$categories = $data['categories'] ?? [
    ['id' => 'parfums', 'name' => 'Parfums'],
    ['id' => 'sacs', 'name' => 'Sacs'],
    ['id' => 'chaussures', 'name' => 'Chaussures'],
    ['id' => 'accessoires', 'name' => 'Accessoires'],
];

$styles = [
    'elegant' => 'Élégant & Luxueux',
    'moderne' => 'Moderne & Tendance',
    'minimaliste' => 'Minimaliste & Épuré',
    'glamour' => 'Glamour & Chic',
    'naturel' => 'Naturel & Bio',
    'vintage' => 'Vintage & Classique',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Clinic - Générateur de Produits IA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ec4899;
            --primary-dark: #db2777;
            --secondary: #8b5cf6;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #faf5ff 50%, #eff6ff 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(236, 72, 153, 0.1);
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .logo-text h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo-text p { font-size: 0.7rem; color: var(--gray); }
        
        .badge-ai {
            padding: 0.4rem 0.875rem;
            background: linear-gradient(135deg, var(--secondary), #7c3aed);
            color: white;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .hero-section {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .hero-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.25rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }
        
        .hero-section h2 span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .hero-section p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid rgba(236, 72, 153, 0.1);
        }
        
        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.05), rgba(139, 92, 246, 0.05));
            border-bottom: 1px solid var(--border);
        }
        
        .card-header h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        .card-header h3 i { color: var(--primary); }
        
        .card-body { padding: 1.5rem; }
        
        .form-group { margin-bottom: 1.25rem; }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group label .required { color: var(--error); }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
        }
        
        .image-upload-area {
            border: 2px dashed var(--border);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(236, 72, 153, 0.02);
        }
        
        .image-upload-area:hover {
            border-color: var(--primary);
            background: rgba(236, 72, 153, 0.05);
        }
        
        .image-upload-area.has-image {
            border-style: solid;
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
        }
        
        .image-upload-area i {
            font-size: 2.5rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .image-upload-area p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .image-upload-area input[type="file"] { display: none; }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 12px;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--gray);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(236, 72, 153, 0.05);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }
        
        .result-section {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .result-image-container {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        }
        
        .result-image-container img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .result-image-container:hover img { transform: scale(1.02); }
        
        .image-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .result-image-container:hover .image-overlay {
            background: rgba(0,0,0,0.3);
            opacity: 1;
        }
        
        .description-box {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05), rgba(236, 72, 153, 0.05));
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .description-box h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }
        
        .description-box h4 i { color: var(--secondary); }
        
        .description-text {
            color: var(--gray);
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .comparison-item { text-align: center; }
        
        .comparison-item h5 {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .comparison-item img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--border);
        }
        
        .comparison-item.enhanced img { border-color: var(--success); }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .info-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .info-card {
            background: white;
            padding: 1.25rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        
        .info-card i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .info-card h4 {
            font-size: 0.85rem;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }
        
        .info-card p {
            font-size: 0.75rem;
            color: var(--gray);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .empty-state-icon i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto;
        }
        
        .prompt-info {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .prompt-info h5 {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .prompt-info code {
            font-size: 0.75rem;
            color: var(--gray);
            word-break: break-all;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .action-buttons .btn { flex: 1; }
        
        .footer {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .notification.success {
            background: linear-gradient(135deg, var(--success), #059669);
        }
        
        .notification.error {
            background: linear-gradient(135deg, var(--error), #dc2626);
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .auto-download-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));
            border: 1px solid var(--success);
            border-radius: 9999px;
            color: var(--success);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .auto-download-badge i {
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .api-key-warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            color: #92400e;
        }

        .api-key-warning i {
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }

        .api-key-warning strong {
            display: block;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-sparkles"></i>
                </div>
                <div class="logo-text">
                    <h1>Beauty Clinic</h1>
                    <p>Générateur de Produits IA</p>
                </div>
            </a>
            <div class="badge-ai">
                <i class="fas fa-magic"></i>
                Powered by Groq
            </div>
        </div>
    </header>

    <main class="main-container">
        <section class="hero-section">
            <h2>
                Transformez vos photos en
                <span>images professionnelles</span>
            </h2>
            <p>
                Importez une photo de votre produit et laissez l'IA la transformer 
                en visuel catalogue de luxe avec une description engageante.
            </p>
        </section>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-shopping-bag"></i>
                        Informations du produit
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (!isApiKeyConfigured()): ?>
                    <div class="api-key-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Configuration requise</strong>
                            La clé API Groq n'est pas configurée. Veuillez définir la variable d'environnement <code>GROQ_API_KEY</code> ou modifier le fichier <code>config.php</code>.
                            <br><br>
                            <a href="https://console.groq.com/keys" target="_blank" style="color: #92400e; text-decoration: underline;">Obtenir une clé API gratuite →</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" enctype="multipart/form-data" id="generatorForm">
                        <div class="form-group">
                            <label>Photo du produit</label>
                            <div class="image-upload-area <?php echo $uploadedImage ? 'has-image' : ''; ?>" onclick="document.getElementById('product_image').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Cliquez pour importer une photo</p>
                                <p style="font-size: 0.8rem; margin-top: 0.5rem;">JPG, PNG, GIF, WEBP (max 5MB)</p>
                                <input type="file" id="product_image" name="product_image" accept="image/*" onchange="previewImage(this)">
                                <?php if ($uploadedImage): ?>
                                <img src="<?php echo htmlspecialchars($uploadedImage); ?>" class="preview-image" id="preview">
                                <input type="hidden" name="original_image_url" value="<?php echo htmlspecialchars($uploadedImage); ?>">
                                <?php else: ?>
                                <img src="" class="preview-image" id="preview" style="display: none;">
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom du produit <span class="required">*</span></label>
                                <input type="text" name="product_name" class="form-control" placeholder="Ex: Oud Rose Intense" required
                                    value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Catégorie <span class="required">*</span></label>
                                <select name="category" class="form-control" required>
                                    <option value="">Choisir...</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($_POST['category'] ?? '') === $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Style visuel</label>
                                <select name="style" class="form-control">
                                    <?php foreach ($styles as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($_POST['style'] ?? 'elegant') === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Marque</label>
                                <input type="text" name="brand" class="form-control" placeholder="Ex: Al Haramain"
                                    value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Prix (FCFA)</label>
                                <input type="number" name="price" class="form-control" placeholder="Ex: 28500"
                                    value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Public cible</label>
                                <input type="text" name="target_audience" class="form-control" placeholder="Ex: Femmes élégantes"
                                    value="<?php echo htmlspecialchars($_POST['target_audience'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Caractéristiques principales</label>
                            <textarea name="characteristics" class="form-control" placeholder="Notes de rose et d'oud, flacon en cristal, 100ml..."><?php echo htmlspecialchars($_POST['characteristics'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Gamme de prix</label>
                            <input type="text" name="price_range" class="form-control" placeholder="Ex: Premium, Luxe, Accessible..."
                                value="<?php echo htmlspecialchars($_POST['price_range'] ?? ''); ?>">
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button type="submit" name="generer" class="btn btn-primary" <?php echo ($isGenerating || !isApiKeyConfigured()) ? 'disabled' : ''; ?>>
                                <?php if ($isGenerating): ?>
                                <span class="loading-spinner"></span>
                                Génération en cours...
                                <?php else: ?>
                                <i class="fas fa-magic"></i>
                                Générer le produit
                                <?php endif; ?>
                            </button>
                            <a href="index.php" class="btn btn-outline">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <?php if ($generatedResult): ?>
                <div class="result-section">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));">
                            <h3>
                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                Produit généré avec succès
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="auto-download-badge">
                                <i class="fas fa-download"></i>
                                Téléchargement automatique en cours...
                            </div>
                            
                            <?php if ($generatedResult['originalImage']): ?>
                            <div class="comparison-grid">
                                <div class="comparison-item">
                                    <h5>Photo originale</h5>
                                    <img src="<?php echo htmlspecialchars($generatedResult['originalImage']); ?>" alt="Original">
                                </div>
                                <div class="comparison-item enhanced">
                                    <h5>Version améliorée</h5>
                                    <img src="<?php echo htmlspecialchars($generatedResult['imageUrl']); ?>" alt="Améliorée">
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="result-image-container">
                                <img src="<?php echo htmlspecialchars($generatedResult['imageUrl']); ?>" alt="<?php echo htmlspecialchars($generatedResult['title']); ?>" id="generatedImage">
                                <div class="image-overlay">
                                    <button class="btn btn-success" onclick="downloadImageManual()">
                                        <i class="fas fa-download"></i>
                                        Télécharger
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="description-box">
                                <h4>
                                    <i class="fas fa-file-alt"></i>
                                    Description générée
                                </h4>
                                <p class="description-text"><?php echo nl2br(htmlspecialchars($generatedResult['description'])); ?></p>
                                <button class="btn btn-outline" style="margin-top: 1rem; width: auto; padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="copyText('<?php echo addslashes($generatedResult['description']); ?>')">
                                    <i class="fas fa-copy"></i>
                                    Copier
                                </button>
                            </div>

                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="downloadImageManual()">
                                    <i class="fas fa-download"></i>
                                    Télécharger l'image
                                </button>
                                <button class="btn btn-primary" onclick="addToCatalog('<?php echo addslashes($generatedResult['title']); ?>', '<?php echo addslashes($generatedResult['description']); ?>', '<?php echo htmlspecialchars($generatedResult['imageUrl']); ?>')">
                                    <i class="fas fa-plus"></i>
                                    Ajouter au catalogue
                                </button>
                            </div>

                            <div class="prompt-info">
                                <h5><i class="fas fa-code"></i> Prompt utilisé (référence)</h5>
                                <code><?php echo htmlspecialchars(substr($generatedResult['prompt'], 0, 200)) . '...'; ?></code>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-sparkles"></i>
                        </div>
                        <h3>Prêt à créer</h3>
                        <p>
                            Remplissez le formulaire et importez une photo de votre produit 
                            pour obtenir une version professionnelle et une description engageante.
                        </p>

                        <div class="info-cards">
                            <div class="info-card">
                                <i class="fas fa-image"></i>
                                <h4>Images HD</h4>
                                <p>Qualité professionnelle</p>
                            </div>
                            <div class="info-card">
                                <i class="fas fa-pen-fancy"></i>
                                <h4>Descriptions</h4>
                                <p>Textes engageants</p>
                            </div>
                            <div class="info-card">
                                <i class="fas fa-gem"></i>
                                <h4>Style Luxe</h4>
                                <p>Adapté Beauty Clinic</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer">
            <p>
                © 2026 Beauty Clinic - Générateur de Produits IA. 
                Toutes les images générées sont libres de droits pour votre boutique.
            </p>
        </footer>
    </main>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('preview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    preview.parentElement.classList.add('has-image');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Description copiée !', 'success');
            }).catch(() => {
                showNotification('Erreur lors de la copie', 'error');
            });
        }

        function showNotification(message, type = 'success') {
            const existing = document.querySelectorAll('.notification');
            existing.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = 'notification ' + type;
            notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        function addToCatalog(title, description, imageUrl) {
            const params = new URLSearchParams({
                page: 'products',
                action: 'add_generated',
                name: title,
                description: description,
                image: imageUrl
            });
            if (confirm('Voulez-vous ajouter ce produit au catalogue ?')) {
                window.location.href = 'admin.php?' + params.toString();
            }
        }

        function downloadImageManual() {
            const imageUrl = '<?php echo isset($generatedResult['localImagePath']) ? htmlspecialchars($generatedResult['localImagePath'], ENT_QUOTES) : ''; ?>';
            const productName = '<?php echo isset($generatedResult['title']) ? htmlspecialchars(sanitizeFileName($generatedResult['title']), ENT_QUOTES) : 'produit'; ?>';
            
            if (imageUrl) {
                const link = document.createElement('a');
                link.href = imageUrl;
                link.download = productName + '_beauty_clinic.jpg';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showNotification('Image téléchargée !', 'success');
            } else {
                showNotification('Erreur: image non disponible', 'error');
            }
        }
    </script>
    
    <?php echo $autoDownloadScript; ?>
</body>
</html>
