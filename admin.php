<?php
// ============================================
// ADMIN.PHP - Interface d'administration
// ============================================

// Configuration de la base de données
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_NAME', 'if0_40645632_opportunites_db');
define('DB_USER', 'if0_40645632');
define('DB_PASS', '1UUJDXhPW3O');

// Connexion à MySQL
$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Création de la table si elle n'existe pas
$createTableSQL = "
CREATE TABLE IF NOT EXISTS opportunites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    description_extract TEXT,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    lien_postuler VARCHAR(500) NOT NULL,
    infos_supp TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
)";
$pdo->exec($createTableSQL);

// Variables pour les messages de retour
$message = '';
$messageType = '';
$extractedContent = '';

// Fonction pour extraire le contenu d'une URL - CORRIGÉE
function extractContentFromUrl($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "URL invalide";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return "Erreur HTTP $httpCode lors de la récupération";
    }
    
    // CORRECTION ICI : Utiliser strip_tags sans autoriser de balises
    // Supprimer tous les scripts et styles
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html); // Supprimer les commentaires HTML
    
    // CORRECTION ICI : Supprimer TOUTES les balises HTML, ne garder que le texte
    $text = strip_tags($html);
    
    // Nettoyer les espaces multiples et sauts de ligne
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Supprimer les caractères de contrôle
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    
    // Formater le texte pour une meilleure lisibilité
    $text = preg_replace('/(\. )/', ".\n\n", $text); // Nouvelle ligne après chaque phrase
    $text = preg_replace('/(\? )/', "?\n\n", $text);
    $text = preg_replace('/(\! )/', "!\n\n", $text);
    
    // Limiter à 5000 caractères mais garder les phrases complètes
    if (strlen($text) > 5000) {
        $text = substr($text, 0, 5000);
        $last_period = strrpos($text, '.');
        if ($last_period !== false) {
            $text = substr($text, 0, $last_period + 1);
        }
    }
    
    return $text;
}

// Traitement du formulaire d'extraction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extract_url'])) {
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);
    if (!empty($url)) {
        $extractedContent = extractContentFromUrl($url);
    } else {
        $extractedContent = "Veuillez entrer une URL valide";
    }
}

// Traitement du formulaire de création d'opportunité
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_opportunity'])) {
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $lien_postuler = filter_input(INPUT_POST, 'lien_postuler', FILTER_SANITIZE_URL);
    $infos_supp = filter_input(INPUT_POST, 'infos_supp', FILTER_SANITIZE_STRING);
    
    if ($nom && $date_debut && $date_fin && $lien_postuler) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO opportunites (nom, description_extract, date_debut, date_fin, lien_postuler, infos_supp) 
                VALUES (:nom, :description, :date_debut, :date_fin, :lien_postuler, :infos_supp)
            ");
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':date_debut' => $date_debut,
                ':date_fin' => $date_fin,
                ':lien_postuler' => $lien_postuler,
                ':infos_supp' => $infos_supp
            ]);
            
            $message = "Opportunité créée avec succès!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Erreur lors de la création : " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Veuillez remplir tous les champs obligatoires";
        $messageType = "error";
    }
}

// Récupération des opportunités existantes
$opportunites = [];
try {
    $stmt = $pdo->query("SELECT * FROM opportunites ORDER BY date_creation DESC");
    $opportunites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $message = "Erreur lors de la récupération des données : " . $e->getMessage();
    $messageType = "error";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestion des Opportunités</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #0094C6;
            --secondary-blue: #4DB3D3;
            --success-green: #4CAF50;
            --dark-green: #2E7D32;
        }
        
        * {
            font-family: 'Montserrat', sans-serif;
        }
        
        .bg-primary {
            background-color: var(--primary-blue);
        }
        
        .text-primary {
            color: var(--primary-blue);
        }
        
        .border-primary {
            border-color: var(--primary-blue);
        }
        
        .bg-secondary {
            background-color: var(--secondary-blue);
        }
        
        .bg-success {
            background-color: var(--success-green);
        }
        
        .text-success {
            color: var(--success-green);
        }
        
        .border-success {
            border-color: var(--success-green);
        }
        
        .h-screen-90 {
            height: 90vh;
        }
        
        .message-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .message-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .btn {
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .extracted-text {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .loader {
            display: none;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0094C6;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <div class="bg-primary w-10 h-10 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-xl">O</span>
                        </div>
                        <h1 class="ml-3 text-2xl font-bold text-gray-900">Admin Opportunités</h1>
                    </div>
                    <nav>
                        <a href="index.php" class="text-primary hover:text-secondary font-semibold">
                            <i class="fas fa-robot mr-1"></i>Voir le Chatbot →
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'message-success' : 'message-error'; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Section gauche : Extraction et création -->
                <div class="space-y-8">
                    <!-- Formulaire d'extraction -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-primary mb-4">
                            <i class="fas fa-download mr-2"></i>Extraction de contenu
                        </h2>
                        <form method="POST" id="extractForm" class="space-y-4">
                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-link mr-1"></i>URL à extraire
                                </label>
                                <input type="url" id="url" name="url" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="https://exemple.com/offre-emploi"
                                    value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>">
                                <p class="text-xs text-gray-500 mt-1">
                                    Entrez l'URL d'une page web contenant du texte à extraire
                                </p>
                            </div>
                            <button type="submit" name="extract_url" id="extractBtn"
                                class="w-full bg-primary text-white py-3 px-4 rounded-lg font-semibold hover:bg-secondary btn flex items-center justify-center">
                                <div class="loader" id="loader"></div>
                                <span id="btnText">
                                    <i class="fas fa-download mr-2"></i>Extraire le contenu
                                </span>
                            </button>
                        </form>

                        <?php if (isset($extractedContent) && $extractedContent !== ''): ?>
                            <div class="mt-6">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        <i class="fas fa-file-text mr-1"></i>Contenu extrait :
                                    </label>
                                    <span class="text-xs text-gray-500">
                                        <?php echo strlen($extractedContent); ?> caractères
                                    </span>
                                </div>
                                <div class="border border-gray-300 rounded-lg p-4 bg-gray-50 max-h-96 overflow-y-auto">
                                    <pre class="extracted-text"><?php echo htmlspecialchars($extractedContent); ?></pre>
                                </div>
                                
                                <!-- Zone pour copier dans le formulaire -->
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm text-gray-700 mb-2">
                                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                                        Vous pouvez copier ce texte dans le champ "Description" ci-dessous
                                    </p>
                                    <button type="button" onclick="copyToDescription()" 
                                            class="w-full bg-blue-100 text-blue-700 py-2 px-4 rounded-lg font-semibold hover:bg-blue-200 btn">
                                        <i class="fas fa-copy mr-2"></i>Copier vers le formulaire
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulaire de création -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-primary mb-4">
                            <i class="fas fa-plus-circle mr-2"></i>Créer une opportunité
                        </h2>
                        <form method="POST" id="createForm" class="space-y-4">
                            <div>
                                <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-tag mr-1"></i>Nom *
                                </label>
                                <input type="text" id="nom" name="nom" required maxlength="255"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="Ex: Développeur Full Stack"
                                    value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-calendar-alt mr-1"></i>Date début *
                                    </label>
                                    <input type="date" id="date_debut" name="date_debut" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        value="<?php 
                                            echo isset($_POST['date_debut']) ? htmlspecialchars($_POST['date_debut']) : 
                                            date('Y-m-d');
                                        ?>">
                                </div>
                                <div>
                                    <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-1">
                                        <i class="fas fa-calendar-times mr-1"></i>Date limite *
                                    </label>
                                    <input type="date" id="date_fin" name="date_fin" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                        value="<?php 
                                            echo isset($_POST['date_fin']) ? htmlspecialchars($_POST['date_fin']) : 
                                            date('Y-m-d', strtotime('+7 days'));
                                        ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="lien_postuler" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-external-link-alt mr-1"></i>Lien de postulation *
                                </label>
                                <input type="url" id="lien_postuler" name="lien_postuler" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="https://entreprise.com/postuler"
                                    value="<?php echo isset($_POST['lien_postuler']) ? htmlspecialchars($_POST['lien_postuler']) : ''; ?>">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-align-left mr-1"></i>Description
                                </label>
                                <textarea id="description" name="description" rows="5"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="Description détaillée de l'opportunité..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">
                                    Vous pouvez utiliser le texte extrait ci-dessus
                                </p>
                            </div>
                            
                            <div>
                                <label for="infos_supp" class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-info-circle mr-1"></i>Informations supplémentaires
                                </label>
                                <textarea id="infos_supp" name="infos_supp" rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="Salaire, avantages, localisation, conditions..."><?php echo isset($_POST['infos_supp']) ? htmlspecialchars($_POST['infos_supp']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" name="create_opportunity"
                                class="w-full bg-success text-white py-3 px-4 rounded-lg font-semibold hover:bg-dark-green btn">
                                <i class="fas fa-save mr-2"></i>Créer l'opportunité
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Section droite : Liste des opportunités -->
                <div class="bg-white rounded-xl shadow-lg p-6 h-screen-90 overflow-hidden flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-primary">
                            <i class="fas fa-list mr-2"></i>Opportunités existantes
                        </h2>
                        <span class="bg-primary text-white text-sm px-3 py-1 rounded-full">
                            <?php echo count($opportunites); ?> total
                        </span>
                    </div>
                    
                    <div class="overflow-y-auto flex-grow">
                        <?php if (empty($opportunites)): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox fa-3x mb-4 text-gray-300"></i>
                                <p class="text-lg mb-2">Aucune opportunité créée pour le moment.</p>
                                <p class="text-sm">Commencez par créer votre première opportunité !</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php 
                                $activeCount = 0;
                                $expiredCount = 0;
                                
                                foreach ($opportunites as $opp): 
                                    $isExpired = strtotime($opp['date_fin']) < time();
                                    if ($isExpired) {
                                        $expiredCount++;
                                    } else {
                                        $activeCount++;
                                    }
                                ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary transition-colors <?php echo $isExpired ? 'bg-gray-50' : ''; ?>">
                                        <div class="flex justify-between items-start">
                                            <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($opp['nom']); ?></h3>
                                            <div class="flex items-center space-x-2">
                                                <?php if ($isExpired): ?>
                                                    <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">
                                                        <i class="fas fa-clock mr-1"></i>Expirée
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                                                        <i class="fas fa-check-circle mr-1"></i>Active
                                                    </span>
                                                <?php endif; ?>
                                                <span class="bg-primary text-white text-xs px-2 py-1 rounded">
                                                    ID: <?php echo $opp['id']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 text-sm text-gray-600">
                                            <div class="flex items-center mb-2">
                                                <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                                <span class="font-semibold mr-2">Période :</span> 
                                                <span><?php echo date('d/m/Y', strtotime($opp['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($opp['date_fin'])); ?></span>
                                            </div>
                                            
                                            <?php if ($opp['description_extract']): ?>
                                                <div class="mt-3">
                                                    <div class="flex items-center mb-1">
                                                        <i class="fas fa-align-left text-primary mr-2"></i>
                                                        <span class="font-semibold">Description :</span>
                                                    </div>
                                                    <div class="bg-gray-50 p-3 rounded text-gray-700">
                                                        <?php 
                                                        $desc = htmlspecialchars($opp['description_extract']);
                                                        if (strlen($desc) > 150) {
                                                            echo substr($desc, 0, 150) . '...';
                                                        } else {
                                                            echo $desc;
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($opp['infos_supp']): ?>
                                                <div class="mt-3">
                                                    <div class="flex items-center mb-1">
                                                        <i class="fas fa-info-circle text-green-500 mr-2"></i>
                                                        <span class="font-semibold text-green-700">Informations supplémentaires :</span>
                                                    </div>
                                                    <div class="text-gray-600">
                                                        <?php 
                                                        $infos = htmlspecialchars($opp['infos_supp']);
                                                        if (strlen($infos) > 100) {
                                                            echo substr($infos, 0, 100) . '...';
                                                        } else {
                                                            echo $infos;
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-4 flex justify-between items-center pt-3 border-t border-gray-100">
                                            <a href="<?php echo htmlspecialchars($opp['lien_postuler']); ?>" 
                                               target="_blank"
                                               class="text-primary hover:text-secondary font-semibold text-sm flex items-center">
                                                <i class="fas fa-external-link-alt mr-2"></i>Postuler ici
                                            </a>
                                            <div class="text-right">
                                                <span class="text-xs text-gray-500">
                                                    <i class="far fa-clock mr-1"></i>
                                                    Créé le <?php echo date('d/m/Y H:i', strtotime($opp['date_creation'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-700"><?php echo $activeCount ?? 0; ?></div>
                                <div class="text-sm text-green-600">Actives</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-700"><?php echo $expiredCount ?? 0; ?></div>
                                <div class="text-sm text-red-600">Expirées</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            const nextWeekStr = nextWeek.toISOString().split('T')[0];
            
            // Ne pas écraser si des valeurs existent déjà
            if (!document.getElementById('date_debut').value) {
                document.getElementById('date_debut').value = today;
            }
            if (!document.getElementById('date_fin').value) {
                document.getElementById('date_fin').value = nextWeekStr;
            }
            
            // Gestion de l'extraction
            const extractForm = document.getElementById('extractForm');
            if (extractForm) {
                extractForm.addEventListener('submit', function() {
                    const btn = document.getElementById('extractBtn');
                    const loader = document.getElementById('loader');
                    const btnText = document.getElementById('btnText');
                    
                    if (btn && loader && btnText) {
                        btn.disabled = true;
                        loader.style.display = 'inline-block';
                        btnText.innerHTML = 'Extraction en cours...';
                    }
                });
            }
            
            // Validation des dates
            const createForm = document.getElementById('createForm');
            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    const dateDebut = new Date(document.getElementById('date_debut').value);
                    const dateFin = new Date(document.getElementById('date_fin').value);
                    
                    if (dateDebut > dateFin) {
                        e.preventDefault();
                        alert('La date de début ne peut pas être postérieure à la date limite.');
                        document.getElementById('date_debut').focus();
                        return false;
                    }
                    
                    // Validation de l'URL
                    const url = document.getElementById('lien_postuler').value;
                    if (url && !isValidUrl(url)) {
                        e.preventDefault();
                        alert('Veuillez entrer une URL valide pour le lien de postulation.');
                        document.getElementById('lien_postuler').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Auto-resize textarea
            const descriptionTextarea = document.getElementById('description');
            if (descriptionTextarea) {
                descriptionTextarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                // Trigger resize on load
                setTimeout(() => {
                    descriptionTextarea.dispatchEvent(new Event('input'));
                }, 100);
            }
        });
        
        function copyToDescription() {
            const extractedText = document.querySelector('.extracted-text');
            const descriptionField = document.getElementById('description');
            
            if (extractedText && descriptionField) {
                // Récupérer le texte du pre (qui peut contenir des sauts de ligne)
                const textToCopy = extractedText.textContent;
                
                // Ajouter au champ description (ne pas écraser le contenu existant)
                if (descriptionField.value.trim() !== '') {
                    descriptionField.value += '\n\n' + textToCopy;
                } else {
                    descriptionField.value = textToCopy;
                }
                
                // Ajuster la hauteur du textarea
                descriptionField.style.height = 'auto';
                descriptionField.style.height = (descriptionField.scrollHeight) + 'px';
                
                // Feedback visuel
                const originalPlaceholder = descriptionField.placeholder;
                descriptionField.placeholder = '✓ Texte copié avec succès !';
                
                setTimeout(() => {
                    descriptionField.placeholder = originalPlaceholder;
                }, 2000);
                
                // Faire défiler jusqu'au champ description
                descriptionField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                descriptionField.focus();
            }
        }
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // Réactiver le bouton d'extraction en cas d'erreur
        window.addEventListener('load', function() {
            const extractBtn = document.getElementById('extractBtn');
            if (extractBtn && extractBtn.disabled) {
                extractBtn.disabled = false;
                const loader = document.getElementById('loader');
                if (loader) loader.style.display = 'none';
                const btnText = document.getElementById('btnText');
                if (btnText) btnText.innerHTML = '<i class="fas fa-download mr-2"></i>Extraire le contenu';
            }
        });
    </script>
</body>
</html>
