<?php
/**
 * Configuration du Générateur de Produits IA - Beauty Clinic
 * 
 * IMPORTANT: Ce fichier doit être protégé et NE PAS être partagé publiquement
 * Ajoutez ce fichier à votre .gitignore si vous utilisez Git
 */

// ============================================================================
// CLÉ API GROQ - OBLIGATOIRE
// ============================================================================
// Remplacez 'gsk_votre_cle_api_ici' par votre vraie clé API Groq
// Obtenez une clé gratuite sur: https://console.groq.com/keys
$groqApiKey = getenv('GROQ_API_KEY'); // Essayer d'abord les variables d'environnement

if (!$groqApiKey || $groqApiKey === 'gsk_votre_cle_api_ici') {
    // Si pas de variable d'environnement, utiliser la valeur par défaut
    $groqApiKey = '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';
}

define('GROQ_API_KEY', $groqApiKey);

// Vérifier que la clé API est configurée
function isApiKeyConfigured() {
    return GROQ_API_KEY !== 'gsk_votre_cle_api_ici' && !empty(GROQ_API_KEY);
}

// ============================================================================
// CONFIGURATION DES UPLOADS
// ============================================================================
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 Mo
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', 'uploads/');

// ============================================================================
// CONFIGURATION DE L'API
// ============================================================================
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_TIMEOUT', 60); // Augmenté à 60 secondes

// ============================================================================
// CONFIGURATION DES IMAGES GÉNÉRÉES
// ============================================================================
define('IMAGE_GENERATION_URL', 'https://image.pollinations.ai/prompt/');
define('IMAGE_WIDTH', 1024);
define('IMAGE_HEIGHT', 1024);

// ============================================================================
// MODE DEBUG
// ============================================================================
// Mettre à true pour voir les erreurs détaillées (développement uniquement)
define('DEBUG_MODE', false);

// ============================================================================
// CONFIGURATION DES DOSSIERS
// ============================================================================
// Créer les dossiers s'ils n'existent pas
$directories = [
    'uploads/generator/',
    'uploads/generated/'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
