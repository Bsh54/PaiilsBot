<?php
// ============================================
// INDEX.PHP - Chatbot public avec API Oreus
// ============================================

// Configuration de la base de données
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_NAME', 'if0_40645632_opportunites_db');
define('DB_USER', 'if0_40645632');
define('DB_PASS', '1UUJDXhPW3O');

// Configuration API Oreus/Alogo
define('OREUS_API_KEY', 'or_f6e6e03b37835072fce65c55ce845ae89a10dd09b35726be785fc423');
define('OREUS_API_URL', 'https://oreus-staging.dev2.dev-id.fr/api/v1/sdk/chat/completions');

session_start();

// Connexion à MySQL
$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Ne pas afficher l'erreur en production
    error_log("Erreur de connexion DB: " . $e->getMessage());
}

// Récupération des opportunités valides
$opportunites = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM opportunites 
            WHERE date_fin >= CURDATE() 
            ORDER BY date_fin ASC
        ");
        $opportunites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Erreur de requête DB: " . $e->getMessage());
    }
}

// Traitement AJAX pour le chatbot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_opportunites') {
        echo json_encode($opportunites);
        exit;
    }
    
    if ($_POST['action'] === 'chat' && isset($_POST['message'], $_POST['opportunite_id'])) {
        $userMessage = trim($_POST['message']);
        $opportuniteId = (int)$_POST['opportunite_id'];
        
        // Trouver l'opportunité sélectionnée
        $selectedOpp = null;
        foreach ($opportunites as $opp) {
            if ($opp['id'] == $opportuniteId) {
                $selectedOpp = $opp;
                break;
            }
        }
        
        if ($selectedOpp) {
            try {
                // Appel à l'API Oreus/Alogo
                $response = callOreusAPI($selectedOpp, $userMessage);
                echo json_encode([
                    'success' => true, 
                    'response' => $response,
                    'opportunite' => [
                        'id' => $selectedOpp['id'],
                        'nom' => $selectedOpp['nom']
                    ]
                ]);
            } catch (Exception $e) {
                error_log("Erreur API Oreus: " . $e->getMessage());
                // Fallback en cas d'erreur
                $fallbackResponse = generateFallbackResponse($selectedOpp, $userMessage);
                echo json_encode([
                    'success' => true,
                    'response' => $fallbackResponse,
                    'from_fallback' => true,
                    'opportunite' => [
                        'id' => $selectedOpp['id'],
                        'nom' => $selectedOpp['nom']
                    ]
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Opportunité non trouvée']);
        }
        exit;
    }
}

// Fonction pour appeler l'API Oreus
function callOreusAPI($opportunite, $userMessage) {
    $apiKey = OREUS_API_KEY;
    $apiUrl = OREUS_API_URL;
    
    // Construction du prompt contextuel
    $contextPrompt = buildContextPrompt($opportunite, $userMessage);
    
    // Préparation de la requête
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $data = [
        'model' => 'PaiilsBot',
        'messages' => [
            [
                'role' => 'system',
                'content' => "Tu es un assistant expert en opportunités professionnelles et formations. Tu réponds aux questions des utilisateurs de manière précise et utile."
            ],
            [
                'role' => 'user',
                'content' => $contextPrompt
            ]
        ],
        
       
        'stream' => false
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erreur cURL: " . $error);
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erreur HTTP {$httpCode}: " . $response);
    }
    
    $responseData = json_decode($response, true);
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        throw new Exception("Format de réponse API invalide");
    }
    
    return formatAPIResponse($responseData['choices'][0]['message']['content']);
}

// Construction du prompt contextuel
function buildContextPrompt($opportunite, $userMessage) {
    $dateFin = date('d/m/Y', strtotime($opportunite['date_fin']));
    
    $prompt = "INFORMATIONS SUR L'OPPORTUNITÉ:\n";
    $prompt .= "Nom: {$opportunite['nom']}\n";
    if (!empty($opportunite['type'])) {
        $prompt .= "Type: {$opportunite['type']}\n";
    }
    $prompt .= "Date limite: {$dateFin}\n";
    
    if (!empty($opportunite['description'])) {
        $prompt .= "Description: {$opportunite['description']}\n";
    }
    
    if (!empty($opportunite['details'])) {
        $prompt .= "Détails supplémentaires: {$opportunite['details']}\n";
    }
    
    $prompt .= "\nQUESTION DE L'UTILISATEUR:\n";
    $prompt .= $userMessage . "\n\n";
    
    $prompt .= "INSTRUCTIONS POUR TA RÉPONSE:\n";
    $prompt .= "1. Réponds de manière précise et utile\n";
    $prompt .= "2. Mets en avant les dates limites importantes\n";
    $prompt .= "3. Si la question concerne les prérequis, sois spécifique\n";
    $prompt .= "4. Pour les questions sur la candidature, explique le processus\n";
    $prompt .= "5. Utilise un ton professionnel mais accessible\n";
    $prompt .= "6. Si tu manques d'informations, propose de consulter le site officiel\n";
    
    return $prompt;
}

// Formatage de la réponse API
function formatAPIResponse($response) {
    // Nettoyage basique
    $response = trim($response);
    
    // Formatage Markdown simple
    $response = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $response);
    $response = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $response);
    
    // Conversion des listes
    $response = preg_replace('/^\s*[-*]\s+(.*)$/m', '<li>$1</li>', $response);
    $response = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $response);
    
    // Conversion des liens
    $response = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" class="text-blue-600 hover:underline">$1</a>', $response);
    
    return nl2br($response);
}

// Génération de réponse de secours
function generateFallbackResponse($opportunite, $userMessage) {
    $dateFin = date('d/m/Y', strtotime($opportunite['date_fin']));
    $messageLower = strtolower($userMessage);
    
    $responses = [];
    
    // Questions sur la date
    if (strpos($messageLower, 'date') !== false || 
        strpos($messageLower, 'limite') !== false || 
        strpos($messageLower, 'jusqu') !== false) {
        $responses = [
            "La date limite pour l'opportunité <strong>{$opportunite['nom']}</strong> est fixée au <strong>{$dateFin}</strong>. Je vous recommande de postuler au moins une semaine avant cette date.",
            "Cette opportunité se termine le <strong>{$dateFin}</strong>. Il est conseillé de soumettre votre candidature plusieurs jours avant pour éviter tout problème technique.",
            "Pour <strong>{$opportunite['nom']}</strong>, vous avez jusqu'au <strong>{$dateFin}</strong> pour postuler. Les candidatures reçues après cette date ne seront pas considérées."
        ];
    }
    // Questions sur les prérequis
    elseif (strpos($messageLower, 'prérequis') !== false || 
            strpos($messageLower, 'qualification') !== false || 
            strpos($messageLower, 'requis') !== false) {
        $responses = [
            "Pour <strong>{$opportunite['nom']}</strong>, les qualifications requises dépendent généralement du niveau du poste. Consultez l'offre officielle pour les détails précis.",
            "Les prérequis pour cette opportunité incluent généralement un diplôme pertinent et une expérience professionnelle. Je vous conseille de vérifier les exigences spécifiques sur le site de recrutement.",
            "Concernant les qualifications pour <strong>{$opportunite['nom']}</strong>, il est important de lire attentivement le descriptif de poste qui liste tous les critères d'éligibilité."
        ];
    }
    // Questions sur la candidature
    elseif (strpos($messageLower, 'postuler') !== false || 
            strpos($messageLower, 'candidature') !== false || 
            strpos($messageLower, 'appliquer') !== false) {
        $responses = [
            "Pour postuler à <strong>{$opportunite['nom']}</strong>, vous devez généralement soumettre un CV à jour, une lettre de motivation et les documents requis via la plateforme officielle.",
            "Le processus de candidature pour cette opportunité se fait en ligne. Assurez-vous d'avoir tous vos documents prêts avant de commencer le formulaire.",
            "Pour soumettre votre candidature à <strong>{$opportunite['nom']}</strong>, suivez les instructions sur le site officiel. Prévoyez suffisamment de temps avant la date limite du {$dateFin}."
        ];
    }
    // Questions générales
    else {
        $responses = [
            "Je comprends votre question concernant <strong>{$opportunite['nom']}</strong>. Pour une réponse précise, je vous recommande de consulter le site officiel de cette opportunité.",
            "Concernant <strong>{$opportunite['nom']}</strong> (valable jusqu'au {$dateFin}), pourriez-vous préciser votre question ? Je peux vous aider avec les dates limites, les prérequis ou le processus de candidature.",
            "Pour <strong>{$opportunite['nom']}</strong>, je peux vous informer sur la date limite ({$dateFin}), les conditions de participation et la procédure de candidature. Sur quel aspect souhaitez-vous plus d'informations ?"
        ];
    }
    
    return $responses[array_rand($responses)];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Opportunités</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Garder exactement le même CSS que dans votre code */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        * {
            font-family: 'Montserrat', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .gradient-bg {
            background: var(--primary-gradient);
        }
        
        .chat-bubble-user {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px 20px 5px 20px;
            max-width: 85%;
            margin-left: auto;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        
        .chat-bubble-bot {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #333;
            border-radius: 20px 20px 20px 5px;
            max-width: 85%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .floating-card {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(102, 126, 234, 0); }
            100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .typing-indicator {
            display: flex;
            padding: 10px;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-gradient);
            margin: 0 3px;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 5px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 10px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
        
        .opportunity-chip {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .opportunity-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.2);
        }
        
        .opportunity-chip.selected {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn {
            background: var(--primary-gradient);
            transition: all 0.3s ease;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn:active {
            transform: translateY(0);
        }
        
        /* Style pour les liens dans les réponses du bot */
        .chat-bubble-bot a {
            color: #3b82f6;
            text-decoration: underline;
            font-weight: 500;
        }
        
        .chat-bubble-bot a:hover {
            color: #1d4ed8;
        }
        
        /* Style pour les listes dans les réponses */
        .chat-bubble-bot ul {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .chat-bubble-bot li {
            list-style-type: disc;
            margin-bottom: 0.5rem;
        }
        
        /* Badge pour les réponses de fallback */
        .fallback-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
            vertical-align: middle;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 md:p-8">
    <!-- Chat Container Centré -->
    <div class="w-full max-w-4xl mx-auto">
        <!-- Header du Chat -->
        <div class="glass-effect rounded-3xl overflow-hidden mb-6 fade-in">
            <div class="gradient-bg p-6 md:p-8 text-white">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-robot text-3xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold">Assistant Opportunités</h1>
                            <p class="text-white/90 mt-1">Chatbot intelligent pour vos recherches</p>
                        </div>
                    </div>
                    <div class="bg-white/20 px-4 py-2 rounded-xl">
                        <p class="font-semibold">
                            <i class="fas fa-briefcase mr-2"></i>
                            <?php echo count($opportunites); ?> opportunités
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panel du Chat Principal -->
        <div class="glass-effect rounded-3xl overflow-hidden shadow-2xl fade-in">
            <!-- Zone de messages -->
            <div id="chat-messages" class="h-[500px] overflow-y-auto p-6 scrollbar-thin bg-gradient-to-b from-white to-gray-50">
                <!-- Message d'accueil -->
                <div class="chat-bubble-bot p-6 mb-6 fade-in">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center mr-4">
                            <i class="fas fa-robot text-white text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">🤖 Assistant IA</h3>
                            <p class="text-gray-600 text-sm">Prêt à vous aider</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <p class="text-gray-700">Bonjour ! Je suis votre assistant dédié aux opportunités professionnelles et formations.</p>
                        
                        <?php if (!empty($opportunites)): ?>
                            <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                <p class="font-semibold text-blue-800 mb-3 flex items-center">
                                    <i class="fas fa-list-ul mr-2"></i>
                                    Opportunités disponibles :
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($opportunites as $index => $opp): ?>
                                        <div class="opportunity-chip bg-white px-4 py-2 rounded-full border border-blue-200 hover:border-blue-400 transition-all duration-300"
                                             onclick="selectOpportunity(<?php echo $opp['id']; ?>, '<?php echo htmlspecialchars($opp['nom']); ?>')"
                                             data-id="<?php echo $opp['id']; ?>">
                                            <span class="font-semibold text-blue-600">#<?php echo $index + 1; ?></span>
                                            <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($opp['nom']); ?></span>
                                            <span class="ml-3 text-xs text-gray-500">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date('d/m/Y', strtotime($opp['date_fin'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="bg-gradient-to-r from-blue-50 to-purple-50 p-5 rounded-xl border border-purple-100">
                                <p class="font-semibold text-purple-800 mb-2 flex items-center">
                                    <i class="fas fa-lightbulb mr-2"></i>
                                    Comment utiliser le chatbot :
                                </p>
                                <ul class="space-y-2 text-gray-700">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span>Cliquez sur une opportunité pour la sélectionner</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span>Posez vos questions sur l'opportunité sélectionnée</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span>Obtenez des réponses détaillées et personnalisées</span>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-200">
                                <p class="text-yellow-800 font-semibold">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Aucune opportunité disponible pour le moment.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Indicateur de sélection -->
            <div id="selected-opp-info" class="hidden mx-6 mt-4 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-emerald-200 slide-in">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-check text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-emerald-800 text-sm">Opportunité sélectionnée</p>
                            <p class="text-gray-800 font-bold" id="selected-opp-name"></p>
                        </div>
                    </div>
                    <button onclick="clearSelection()" 
                            class="text-gray-400 hover:text-red-500 transition-colors duration-300 p-2 hover:bg-white rounded-lg">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Zone de saisie -->
            <div class="border-t border-gray-200 p-6 bg-white/80">
                <div id="chat-input-container" class="<?php echo empty($opportunites) ? 'opacity-50 pointer-events-none' : ''; ?>">
                    <div class="relative">
                        <div class="flex items-center space-x-4">
                            <div class="relative flex-1">
                                <input type="text" 
                                       id="chat-input" 
                                       placeholder="<?php echo empty($opportunites) ? 'Aucune opportunité disponible' : 'Tapez votre message ici...' ?>" 
                                       class="w-full px-6 py-4 pl-14 bg-gray-50 border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition-all duration-300 text-gray-700 placeholder-gray-400"
                                       <?php echo empty($opportunites) ? 'disabled' : ''; ?>>
                                <div class="absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-400">
                                    <i class="fas fa-comment-alt text-lg"></i>
                                </div>
                            </div>
                            <button id="send-button"
                                    class="send-btn text-white px-8 py-4 rounded-2xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-3"
                                    <?php echo empty($opportunites) ? 'disabled' : ''; ?>>
                                <span>Envoyer</span>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let selectedOpportunityId = null;
        let selectedOpportunityName = null;
        let isProcessing = false;
        
        // Données PHP converties en JavaScript
        const opportunitiesData = <?php echo json_encode($opportunites); ?>;
        const apiConfig = {
            key: '<?php echo OREUS_API_KEY; ?>',
            url: '<?php echo OREUS_API_URL; ?>'
        };
        
        // Sélection d'une opportunité
        function selectOpportunity(id, name) {
            selectedOpportunityId = id;
            selectedOpportunityName = name;
            
            // Mettre en évidence la chip sélectionnée
            document.querySelectorAll('.opportunity-chip').forEach(chip => {
                chip.classList.remove('selected', 'bg-gradient-to-r', 'from-blue-500', 'to-purple-500', 'text-white');
                if (parseInt(chip.dataset.id) === id) {
                    chip.classList.add('selected', 'bg-gradient-to-r', 'from-blue-500', 'to-purple-500', 'text-white');
                }
            });
            
            // Mettre à jour l'indicateur
            document.getElementById('selected-opp-name').textContent = name;
            document.getElementById('selected-opp-info').classList.remove('hidden');
            
            // Ajouter un message au chat
            addMessage(`J'ai sélectionné l'opportunité <strong>"${name}"</strong>. Que souhaitez-vous savoir à ce sujet ?`, 'bot');
            
            // Focus sur le champ de saisie
            document.getElementById('chat-input').focus();
        }
        
        // Effacer la sélection
        function clearSelection() {
            selectedOpportunityId = null;
            selectedOpportunityName = null;
            
            document.querySelectorAll('.opportunity-chip').forEach(chip => {
                chip.classList.remove('selected', 'bg-gradient-to-r', 'from-blue-500', 'to-purple-500', 'text-white');
            });
            
            document.getElementById('selected-opp-info').classList.add('hidden');
            addMessage('Aucune opportunité sélectionnée. Sélectionnez-en une pour continuer.', 'bot');
        }
        
        // Ajouter un message au chat
        function addMessage(text, sender, isFallback = false) {
            const messagesContainer = document.getElementById('chat-messages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `mb-6 fade-in ${sender === 'user' ? 'chat-bubble-user' : 'chat-bubble-bot'}`;
            
            const timestamp = new Date().toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            const senderName = sender === 'user' ? 'Vous' : 'Assistant IA';
            const senderIcon = sender === 'user' ? 'fa-user' : 'fa-robot';
            const iconColor = sender === 'user' ? 'from-blue-400 to-blue-600' : 'from-purple-400 to-pink-400';
            
            // Ajouter un badge pour les réponses de fallback
            const fallbackBadge = isFallback ? '<span class="fallback-badge">Mode local</span>' : '';
            
            messageDiv.innerHTML = `
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r ${iconColor} flex items-center justify-center mr-3">
                        <i class="fas ${senderIcon} text-white"></i>
                    </div>
                    <div class="flex items-center">
                        <h3 class="font-bold ${sender === 'user' ? 'text-white' : 'text-gray-800'}">${senderName}</h3>
                        ${fallbackBadge}
                    </div>
                    <p class="text-xs ${sender === 'user' ? 'text-white/70' : 'text-gray-500'} ml-auto">${timestamp}</p>
                </div>
                <div class="${sender === 'user' ? 'text-white' : 'text-gray-700'}">${text}</div>
            `;
            
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Afficher l'indicateur de frappe
        function showTypingIndicator() {
            const messagesContainer = document.getElementById('chat-messages');
            const typingDiv = document.createElement('div');
            typingDiv.className = 'chat-bubble-bot p-6 mb-6 fade-in';
            typingDiv.id = 'typing-indicator';
            typingDiv.innerHTML = `
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-400 to-pink-400 flex items-center justify-center mr-3">
                        <i class="fas fa-robot text-white"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">Assistant IA</h3>
                        <p class="text-xs text-gray-500">En train d'écrire...</p>
                    </div>
                </div>
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;
            
            messagesContainer.appendChild(typingDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Cacher l'indicateur de frappe
        function hideTypingIndicator() {
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }
        
        // Construire le prompt contextuel
        function buildContextPrompt(opportunite, userMessage) {
            const dateFin = new Date(opportunite.date_fin).toLocaleDateString('fr-FR');
            
            let prompt = "INFORMATIONS SUR L'OPPORTUNITÉ:\n";
            prompt += `Nom: ${opportunite.nom}\n`;
            
            if (opportunite.type) {
                prompt += `Type: ${opportunite.type}\n`;
            }
            
            prompt += `Date limite: ${dateFin}\n`;
            
            if (opportunite.description) {
                prompt += `Description: ${opportunite.description}\n`;
            }
            
            if (opportunite.details) {
                prompt += `Détails supplémentaires: ${opportunite.details}\n`;
            }
            
            prompt += "\nQUESTION DE L'UTILISATEUR:\n";
            prompt += `${userMessage}\n\n`;
            
            prompt += "INSTRUCTIONS POUR TA RÉPONSE:\n";
            prompt += "1. Réponds de manière précise et utile\n";
            prompt += "2. Mets en avant les dates limites importantes\n";
            prompt += "3. Si la question concerne les prérequis, sois spécifique\n";
            prompt += "4. Pour les questions sur la candidature, explique le processus\n";
            prompt += "5. Utilise un ton professionnel mais accessible\n";
            prompt += "6. Si tu manques d'informations, propose de consulter le site officiel\n";
            
            return prompt;
        }
        
        // Formater la réponse de l'API
        function formatAPIResponse(response) {
            // Nettoyage basique
            response = response.trim();
            
            // Formatage Markdown simple
            response = response.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            response = response.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Conversion des listes
            response = response.replace(/^\s*[-*]\s+(.*)$/gm, '<li>$1</li>');
            
            // Grouper les éléments de liste contigus
            const lines = response.split('\n');
            let formattedResponse = '';
            let inList = false;
            
            for (let i = 0; i < lines.length; i++) {
                if (lines[i].match(/^\s*<li>.*<\/li>\s*$/)) {
                    if (!inList) {
                        formattedResponse += '<ul>';
                        inList = true;
                    }
                    formattedResponse += lines[i];
                } else {
                    if (inList) {
                        formattedResponse += '</ul>';
                        inList = false;
                    }
                    formattedResponse += lines[i];
                    
                    if (i < lines.length - 1) {
                        formattedResponse += '\n';
                    }
                }
            }
            
            if (inList) {
                formattedResponse += '</ul>';
            }
            
            // Conversion des liens
            formattedResponse = formattedResponse.replace(
                /\[(.*?)\]\((.*?)\)/g, 
                '<a href="$2" target="_blank" class="text-blue-600 hover:underline">$1</a>'
            );
            
            // Convertir les retours à la ligne en <br>
            return formattedResponse.replace(/\n/g, '<br>');
        }
        
        // Générer une réponse de secours
        function generateFallbackResponse(opportunite, userMessage) {
            const dateFin = new Date(opportunite.date_fin).toLocaleDateString('fr-FR');
            const messageLower = userMessage.toLowerCase();
            
            let responses = [];
            
            if (messageLower.includes('date') || messageLower.includes('limite') || messageLower.includes('jusqu')) {
                responses = [
                    `La date limite pour l'opportunité <strong>${opportunite.nom}</strong> est fixée au <strong>${dateFin}</strong>. Je vous recommande de postuler au moins une semaine avant cette date.`,
                    `Cette opportunité se termine le <strong>${dateFin}</strong>. Il est conseillé de soumettre votre candidature plusieurs jours avant pour éviter tout problème technique.`,
                    `Pour <strong>${opportunite.nom}</strong>, vous avez jusqu'au <strong>${dateFin}</strong> pour postuler. Les candidatures reçues après cette date ne seront pas considérées.`
                ];
            } else if (messageLower.includes('prérequis') || messageLower.includes('qualification') || messageLower.includes('requis')) {
                responses = [
                    `Pour <strong>${opportunite.nom}</strong>, les qualifications requises dépendent généralement du niveau du poste. Consultez l'offre officielle pour les détails précis.`,
                    `Les prérequis pour cette opportunité incluent généralement un diplôme pertinent et une expérience professionnelle. Je vous conseille de vérifier les exigences spécifiques sur le site de recrutement.`,
                    `Concernant les qualifications pour <strong>${opportunite.nom}</strong>, il est important de lire attentivement le descriptif de poste qui liste tous les critères d'éligibilité.`
                ];
            } else if (messageLower.includes('postuler') || messageLower.includes('candidature') || messageLower.includes('appliquer')) {
                responses = [
                    `Pour postuler à <strong>${opportunite.nom}</strong>, vous devez généralement soumettre un CV à jour, une lettre de motivation et les documents requis via la plateforme officielle.`,
                    `Le processus de candidature pour cette opportunité se fait en ligne. Assurez-vous d'avoir tous vos documents prêts avant de commencer le formulaire.`,
                    `Pour soumettre votre candidature à <strong>${opportunite.nom}</strong>, suivez les instructions sur le site officiel. Prévoyez suffisamment de temps avant la date limite du ${dateFin}.`
                ];
            } else {
                responses = [
                    `Je comprends votre question concernant <strong>${opportunite.nom}</strong>. Pour une réponse précise, je vous recommande de consulter le site officiel de cette opportunité.`,
                    `Concernant <strong>${opportunite.nom}</strong> (valable jusqu'au ${dateFin}), pourriez-vous préciser votre question ? Je peux vous aider avec les dates limites, les prérequis ou le processus de candidature.`,
                    `Pour <strong>${opportunite.nom}</strong>, je peux vous informer sur la date limite (${dateFin}), les conditions de participation et la procédure de candidature. Sur quel aspect souhaitez-vous plus d'informations ?`
                ];
            }
            
            return responses[Math.floor(Math.random() * responses.length)];
        }
        
        // Appeler directement l'API Oreus depuis JavaScript
        async function callOreusAPI(opportunite, userMessage) {
            const prompt = buildContextPrompt(opportunite, userMessage);
            
            const requestBody = {
                model: 'Alogo',
                messages: [
                    {
                        role: 'system',
                        content: "Vous êtes PAIILS Bot, lassistant conversationnel expert du Pan-African Institute for International Language (PAIILS). Votre mission principale est danalyser et dexpliquer en détail les textes dopportunités (offres demploi, appels doffres, projets de collaboration, etc.) que vous envoient les utilisateurs.Instructions de fonctionnement (Style & Comportement) :Accueil & Identité : Présentez-vous brièvement comme le bot de PAIILS, en rappelant la conviction centrale : Dans un marché mondialisé, la maîtrise de langlais nest pas une option, cest le véhicule essentiel de linnovation, du commerce et de lintégration.Analyse Structurée & Conversationnelle :Pour chaque texte reçu, fournissez une explication claire, cohérente et détaillée. Décortiquez le vocabulaire technique, les implicites et les attendus.Maintenez un dialogue fluide. Utilisez des phrases de transition comme : Parlons maintenant des compétences requises..., Un autre point crucial de cette offre est..., Cela mamène à souligner que....Évitez les réponses sèches. Même si une information semble répéter un point précédent, reformulez-la pour approfondir ou illustrer le propos. Exemple : Comme je le mentionnais plus tôt sur les compétences en négociation, ceci est absolument vital parce que....Intégration Stratégique de PAIILS (Lien avec lopportunité) :Faites systématiquement le lien entre les exigences de lopportunité analysée et la nécessité de maîtriser un anglais professionnel et opérationnel. Soulignez que la simple compréhension passive ne suffit pas ; il faut pouvoir négocier, présenter, convaincre et diriger avec aisance.Valorisez loffre PAIILS en montrant comment elle comble exactement ce gap. Insistez sur :La pédagogie unique : Au-delà des manuels, basée sur limmersion réelle et les compétences professionnelles pratiques.Le résultat exceptionnel : Accéder à un niveau de confiance et de maîtrise permettant de saisir et de dominer ce type dopportunités internationales.La mission : Bâtir une Afrique unie et compétente. Lutilisateur, en se formant, devient acteur de cette vision.Appel à lAction Naturel & Convaincant :Concluez votre analyse en orientant naturellement la conversation vers une solution concrète. Par exemple :Pour saisir pleinement une opportunité comme celle-ci, une formation adaptée est clé. Cest précisément ce que PAIILS offre.Les compétences linguistiques exigées ici sont typiques de celles que nous développons chez PAIILS pour vous placer à lavant-garde.Incitez à payer une formation en présentant cela comme linvestissement logique et décisif pour transformer lopportunité analysée en succès. Proposez de les orienter vers une offre de formation pertinente (ex: Anglais des Affaires & Négociation, Présentation Professionnelle dImpact).Restez toujours encourageant et orienté solution.Informations Pratiques (À glisser si pertinent) :Si le contexte sy prête (ex : lutilisateur est au Bénin ou demande des infos), vous pouvez mentionner : Notre institut est situé à Calavi, Bénin (sur la voie de lAcadémie Photo, en direction du carrefour Arconville)."
                    },
                    {
                        role: 'user',
                        content: prompt
                    }
                ],
                temperature: 0.7,
                
                stream: false
            };
            
            const response = await fetch(apiConfig.url, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiConfig.key}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestBody)
            });
            
            if (!response.ok) {
                throw new Error(`Erreur HTTP ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.choices || !data.choices[0] || !data.choices[0].message || !data.choices[0].message.content) {
                throw new Error('Format de réponse API invalide');
            }
            
            return formatAPIResponse(data.choices[0].message.content);
        }
        
        // Envoyer un message (version JavaScript directe vers l'API)
        async function sendMessage() {
            if (isProcessing) return;
            
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Vérifier qu'une opportunité est sélectionnée
            if (!selectedOpportunityId) {
                addMessage('Veuillez d\'abord sélectionner une opportunité dans la liste.', 'bot');
                return;
            }
            
            // Trouver l'opportunité sélectionnée
            const selectedOpportunity = opportunitiesData.find(opp => opp.id == selectedOpportunityId);
            if (!selectedOpportunity) {
                addMessage('Opportunité non trouvée. Veuillez en sélectionner une autre.', 'bot');
                return;
            }
            
            // Ajouter le message de l'utilisateur
            addMessage(message, 'user');
            input.value = '';
            
            // Désactiver temporairement l'input
            input.disabled = true;
            document.getElementById('send-button').disabled = true;
            isProcessing = true;
            
            // Afficher l'indicateur de frappe
            showTypingIndicator();
            
            try {
                // Appeler directement l'API Oreus depuis JavaScript
                const apiResponse = await callOreusAPI(selectedOpportunity, message);
                
                // Cacher l'indicateur de frappe
                hideTypingIndicator();
                
                // Ajouter la réponse du bot
                addMessage(apiResponse, 'bot', false);
                
            } catch (error) {
                // En cas d'erreur, utiliser le fallback
                hideTypingIndicator();
                
                const fallbackResponse = generateFallbackResponse(selectedOpportunity, message);
                addMessage(fallbackResponse, 'bot', true);
                
                console.error('Erreur API:', error);
            } finally {
                // Réactiver l'input
                input.disabled = false;
                document.getElementById('send-button').disabled = false;
                isProcessing = false;
                input.focus();
            }
        }
        
        // Questions rapides prédéfinies (optionnel)
        function quickQuestion(question) {
            if (!selectedOpportunityId) {
                addMessage('Veuillez d\'abord sélectionner une opportunité.', 'bot');
                return;
            }
            
            const input = document.getElementById('chat-input');
            input.value = question;
            sendMessage();
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('chat-input');
            const sendBtn = document.getElementById('send-button');
            
            // Envoyer avec le bouton
            sendBtn.addEventListener('click', sendMessage);
            
            // Envoyer avec Entrée
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey && !isProcessing) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Focus automatique
            if (input && !input.disabled) {
                setTimeout(() => {
                    input.focus();
                }, 300);
            }
            
            // Animation d'entrée
            setTimeout(() => {
                document.querySelectorAll('.fade-in').forEach(el => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
            }, 100);
            
            
            
            
    </script>
</body>
</html>
