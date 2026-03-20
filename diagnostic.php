<?php
/**
 * Diagnostic - Beauty Clinic Générateur
 * Ce fichier permet de vérifier que tout est correctement configuré
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic - Beauty Clinic</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
            line-height: 1.6;
        }
        h1 { color: #ec4899; }
        .test {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .test h3 { margin-top: 0; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        code {
            background: #e5e7eb;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
        }
        pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔧 Diagnostic Beauty Clinic</h1>
    
    <div class="test">
        <h3>1. Configuration Clé API Groq</h3>
        <?php if (isApiKeyConfigured()): ?>
            <p class="success">✅ Clé API configurée correctement</p>
            <p>Clé (masquée): <code><?php echo substr(GROQ_API_KEY, 0, 10); ?>...</code></p>
        <?php else: ?>
            <p class="error">❌ Clé API non configurée</p>
            <p>Vous devez :</p>
            <ol>
                <li>Créer un compte sur <a href="https://console.groq.com/keys" target="_blank">console.groq.com</a></li>
                <li>Générer une clé API</li>
                <li>Définir la variable d'environnement <code>GROQ_API_KEY</code> sur Render</li>
                <li>Ou modifier <code>config.php</code> avec votre clé</li>
            </ol>
        <?php endif; ?>
    </div>

    <div class="test">
        <h3>2. Extensions PHP Requises</h3>
        <?php
        $extensions = ['curl', 'fileinfo', 'json', 'session', 'pdo', 'pdo_mysql'];
        foreach ($extensions as $ext):
            $loaded = extension_loaded($ext);
        ?>
            <p class="<?php echo $loaded ? 'success' : 'error'; ?>">
                <?php echo $loaded ? '✅' : '❌'; ?> <?php echo $ext; ?>
            </p>
        <?php endforeach; ?>
    </div>

    <div class="test">
        <h3>3. Permissions des Dossiers</h3>
        <?php
        $directories = [
            'uploads/' => UPLOAD_DIR,
            'uploads/generator/' => UPLOAD_DIR . 'generator/',
            'uploads/generated/' => UPLOAD_DIR . 'generated/'
        ];
        foreach ($directories as $name => $dir):
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);
        ?>
            <p class="<?php echo $writable ? 'success' : ($exists ? 'warning' : 'error'); ?>">
                <?php echo $writable ? '✅' : ($exists ? '⚠️' : '❌'); ?> 
                <?php echo $name; ?> 
                <?php echo $writable ? '(accessible en écriture)' : ($exists ? '(existe mais pas accessible en écriture)' : '(n\'existe pas)'); ?>
            </p>
        <?php endforeach; ?>
    </div>

    <div class="test">
        <h3>4. Test Connexion API Groq</h3>
        <?php
        if (isApiKeyConfigured()):
            $ch = curl_init('https://api.groq.com/openai/v1/models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . GROQ_API_KEY
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200):
        ?>
                <p class="success">✅ Connexion à l'API Groq réussie</p>
        <?php else: ?>
                <p class="error">❌ Échec de la connexion à l'API Groq (HTTP <?php echo $httpCode; ?>)</p>
                <p>La clé API est peut-être invalide.</p>
        <?php 
            endif;
        else: 
        ?>
            <p class="warning">⚠️ Impossible de tester - Clé API non configurée</p>
        <?php endif; ?>
    </div>

    <div class="test">
        <h3>5. Variables d'Environnement</h3>
        <pre><?php 
            $envVars = ['GROQ_API_KEY', 'RENDER', 'DATABASE_URL'];
            foreach ($envVars as $var) {
                $value = getenv($var);
                echo "$var: " . ($value ? ($var === 'GROQ_API_KEY' ? substr($value, 0, 10) . '...' : $value) : 'non définie') . "\n";
            }
        ?></pre>
    </div>

    <div class="test">
        <h3>6. Informations PHP</h3>
        <p>Version PHP: <code><?php echo PHP_VERSION; ?></code></p>
        <p>Max upload size: <code><?php echo ini_get('upload_max_filesize'); ?></code></p>
        <p>Max execution time: <code><?php echo ini_get('max_execution_time'); ?>s</code></p>
        <p>Memory limit: <code><?php echo ini_get('memory_limit'); ?></code></p>
    </div>

    <p style="margin-top: 2rem;">
        <a href="index.php">← Retour au générateur</a>
    </p>
</body>
</html>
