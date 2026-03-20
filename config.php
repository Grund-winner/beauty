<?php
/**
 * Configuration du Générateur de Produits IA - Beauty Clinic
 * 
 * IMPORTANT: Ce fichier doit être protégé et NE PAS être partagé publiquement
 * Ajoutez ce fichier à votre .gitignore si vous utilisez Git
 */

// Clé API Groq - REMPLACEZ PAR VOTRE CLÉ RÉELLE
define('GROQ_API_KEY', 'gsk_votre_cle_api_ici');

// Configuration des uploads
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 Mo
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', 'uploads/');

// Configuration de l'API
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_TIMEOUT', 30);

// Configuration des images générées
define('IMAGE_GENERATION_URL', 'https://image.pollinations.ai/prompt/');
define('IMAGE_WIDTH', 1024);
define('IMAGE_HEIGHT', 1024);

// Mode debug (à mettre à false en production)
define('DEBUG_MODE', false);
