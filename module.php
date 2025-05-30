<?php

/**
 * API REST Module for webtrees 2.2 - VERSION AVEC AUTHENTIFICATION LOGIN/PASSWORD
 */

declare(strict_types=1);

namespace MyCustomModules\ApiRest;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST Module with login/password authentication
 */
return new class extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface {
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     */
    public function title(): string
    {
        return 'API REST';
    }

    /**
     * A sentence describing what this module does.
     */
    public function description(): string
    {
        return 'Module API REST avec authentification login/password pour webtrees 2.2';
    }

    /**
     * The person or organisation who created this module.
     */
    public function customModuleAuthorName(): string
    {
        return 'Votre Nom';
    }

    /**
     * The version of this module.
     */
    public function customModuleVersion(): string
    {
        return '1.2.0'; // Version corrigée avec gestion HTTPS
    }

    /**
     * Where to get support for this module.
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/conichonhaa/api-rest';
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Créer le fichier API endpoint
        $this->createApiEndpoint();
    }

    /**
     * Create standalone API endpoint file
     */
    private function createApiEndpoint(): void
    {
        $module_path = __DIR__;
        $api_file = $module_path . '/api.php';
        
        if (!file_exists($api_file)) {
            $api_content = $this->getApiContent();
            file_put_contents($api_file, $api_content);
        }
    }

    /**
     * Get API endpoint content
     */
    private function getApiContent(): string
    {
        return '<?php
/**
 * Standalone API endpoint with login/password authentication
 */

// Configuration et gestion des erreurs
error_reporting(E_ALL);
ini_set("display_errors", 0); // Ne pas afficher les erreurs directement
ini_set("log_errors", 1);

// Headers pour API REST
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    exit(0);
}

// Fonction de réponse JSON avec gestion d\'erreurs
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Fonction de log des erreurs
function logError($message, $context = []) {
    $log = date("Y-m-d H:i:s") . " - " . $message;
    if (!empty($context)) {
        $log .= " - Context: " . json_encode($context);
    }
    error_log($log);
}

// Gestionnaire d\'erreurs global
set_error_handler(function($severity, $message, $file, $line) {
    logError("PHP Error: $message in $file:$line", ["severity" => $severity]);
    jsonResponse([
        "error" => "Internal server error",
        "message" => "An error occurred while processing your request"
    ], 500);
});

// Gestionnaire d\'exceptions global
set_exception_handler(function($exception) {
    logError("PHP Exception: " . $exception->getMessage(), [
        "file" => $exception->getFile(),
        "line" => $exception->getLine(),
        "trace" => $exception->getTraceAsString()
    ]);
    jsonResponse([
        "error" => "Internal server error",
        "message" => "An exception occurred while processing your request"
    ], 500);
});

try {
    // Chercher le fichier webtrees index.php avec plusieurs chemins possibles
    $possible_paths = [
        __DIR__ . "/../../../../index.php",           // modules_v4/[vendor]/[module]/
        __DIR__ . "/../../../index.php",              // modules/[module]/
        __DIR__ . "/../../index.php",                 // [webtrees_root]/modules/
        realpath(__DIR__ . "/../../../../index.php"), // Chemin absolu
        $_SERVER["DOCUMENT_ROOT"] . "/index.php"      // Racine du serveur
    ];
    
    $webtrees_found = false;
    foreach ($possible_paths as $path) {
        if ($path && file_exists($path)) {
            require_once $path;
            $webtrees_found = true;
            break;
        }
    }
    
    if (!$webtrees_found) {
        jsonResponse([
            "error" => "Webtrees bootstrap not found",
            "debug_paths" => $possible_paths,
            "current_dir" => __DIR__
        ], 500);
    }

    // Vérifier que webtrees est bien chargé
    if (!class_exists("\\Fisharebest\\Webtrees\\Registry")) {
        jsonResponse([
            "error" => "Webtrees not properly loaded"
        ], 500);
    }

} catch (Exception $e) {
    jsonResponse([
        "error" => "Failed to load webtrees",
        "message" => $e->getMessage()
    ], 500);
}

// Fonction d\'authentification
function authenticateUser() {
    try {
        $username = null;
        $password = null;
        
        // Essayer HTTP Basic Auth
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            $auth = $_SERVER["HTTP_AUTHORIZATION"];
            if (strpos($auth, "Basic ") === 0) {
                $credentials = base64_decode(substr($auth, 6));
                if ($credentials && strpos($credentials, ":") !== false) {
                    list($username, $password) = explode(":", $credentials, 2);
                }
            }
        }
        
        // Essayer PHP_AUTH_USER/PHP_AUTH_PW (alternative à HTTP_AUTHORIZATION)
        if (empty($username) && isset($_SERVER["PHP_AUTH_USER"])) {
            $username = $_SERVER["PHP_AUTH_USER"];
            $password = $_SERVER["PHP_AUTH_PW"] ?? "";
        }
        
        // Essayer les paramètres GET/POST
        if (empty($username)) {
            $username = $_GET["username"] ?? $_POST["username"] ?? "";
            $password = $_GET["password"] ?? $_POST["password"] ?? "";
        }
        
        if (empty($username) || empty($password)) {
            return null;
        }
        
        // Vérifier les credentials dans la base webtrees
        $user_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\UserService::class);
        $user = $user_service->findByUserName($username);
        
        if ($user && password_verify($password, $user->getPreference("password"))) {
            return $user;
        }
        
        return null;
        
    } catch (Exception $e) {
        logError("Authentication error: " . $e->getMessage());
        return null;
    }
}

// Vérifier l\'authentification
$user = authenticateUser();
if (!$user) {
    jsonResponse([
        "error" => "Authentication required",
        "message" => "Please provide valid username and password via Basic Auth or parameters",
        "auth_methods" => [
            "basic_auth" => "Authorization: Basic base64(username:password)",
            "parameters" => "?username=USER&password=PASS"
        ]
    ], 401);
}

// Router les requêtes API
$endpoint = $_GET["endpoint"] ?? "";

switch ($endpoint) {
    case "trees":
        handleTreesRequest();
        break;
    case "individuals":
        handleIndividualsRequest($user);
        break;
    case "individual":
        handleIndividualRequest($user);
        break;
    case "user":
        handleUserRequest($user);
        break;
    case "debug":
        handleDebugRequest($user);
        break;
    default:
        jsonResponse([
            "error" => "Unknown endpoint",
            "available_endpoints" => [
                "trees" => "List all family trees",
                "individuals" => "List individuals (requires tree parameter)",
                "individual" => "Get individual details (requires tree and xref parameters)",
                "user" => "Get current user info",
                "debug" => "Debug information"
            ]
        ], 404);
}

function handleDebugRequest($user) {
    jsonResponse([
        "success" => true,
        "debug" => [
            "user_authenticated" => true,
            "user_id" => $user->id(),
            "username" => $user->userName(),
            "php_version" => PHP_VERSION,
            "webtrees_loaded" => class_exists("\\Fisharebest\\Webtrees\\Registry"),
            "server_info" => [
                "https" => isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on",
                "host" => $_SERVER["HTTP_HOST"] ?? "unknown",
                "request_uri" => $_SERVER["REQUEST_URI"] ?? "unknown"
            ]
        ]
    ]);
}

function handleTreesRequest() {
    try {
        $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
        $trees = $tree_service->all();

        $result = [];
        foreach ($trees as $tree) {
            $result[] = [
                "id" => $tree->id(),
                "name" => $tree->name(),
                "title" => $tree->title(),
                "individuals_count" => \\Fisharebest\\Webtrees\\DB::table("individuals")
                    ->where("i_file", "=", $tree->id())
                    ->count()
            ];
        }

        jsonResponse([
            "success" => true,
            "data" => $result,
            "count" => count($result)
        ]);
        
    } catch (Exception $e) {
        logError("Trees request error: " . $e->getMessage());
        jsonResponse([
            "error" => "Failed to retrieve trees",
            "message" => $e->getMessage()
        ], 500);
    }
}

function handleIndividualsRequest($user) {
    try {
        $tree_name = $_GET["tree"] ?? "";
        
        if (empty($tree_name)) {
            jsonResponse([
                "error" => "Tree parameter required",
                "example" => "?endpoint=individuals&tree=tree_name"
            ], 400);
        }

        $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
        $tree = $tree_service->find($tree_name);

        if (!$tree) {
            jsonResponse(["error" => "Tree not found"], 404);
        }

        // Vérifier les permissions
        if (!$tree->canShow($user)) {
            jsonResponse(["error" => "Access denied to this tree"], 403);
        }

        $limit = min((int) ($_GET["limit"] ?? 50), 500);
        $offset = max((int) ($_GET["offset"] ?? 0), 0);
        $search = $_GET["search"] ?? "";

        $query = \\Fisharebest\\Webtrees\\DB::table("individuals")
            ->where("i_file", "=", $tree->id());
        
        if (!empty($search)) {
            $query->where("i_gedcom", "LIKE", "%".addcslashes($search, "%_\\\\")."%");
        }
        
        $total = $query->count();
        $individuals = $query->limit($limit)->offset($offset)->get();

        $result = [];
        foreach ($individuals as $individual_record) {
            $individual = \\Fisharebest\\Webtrees\\Registry::individualFactory()->make($individual_record->i_id, $tree);
            if ($individual && $individual->canShow($user)) {
                $result[] = [
                    "xref" => $individual->xref(),
                    "name" => $individual->fullName(),
                    "birth_date" => $individual->getBirthDate()->display(),
                    "death_date" => $individual->getDeathDate()->display(),
                    "sex" => $individual->sex()
                ];
            }
        }

        jsonResponse([
            "success" => true,
            "data" => $result,
            "pagination" => [
                "total" => $total,
                "limit" => $limit,
                "offset" => $offset,
                "count" => count($result)
            ]
        ]);
        
    } catch (Exception $e) {
        logError("Individuals request error: " . $e->getMessage());
        jsonResponse([
            "error" => "Failed to retrieve individuals",
            "message" => $e->getMessage()
        ], 500);
    }
}

function handleIndividualRequest($user) {
    try {
        $tree_name = $_GET["tree"] ?? "";
        $xref = $_GET["xref"] ?? "";
        
        if (empty($tree_name) || empty($xref)) {
            jsonResponse([
                "error" => "Tree and xref parameters required",
                "example" => "?endpoint=individual&tree=tree_name&xref=I1"
            ], 400);
        }
        
        $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
        $tree = $tree_service->find($tree_name);

        if (!$tree) {
            jsonResponse(["error" => "Tree not found"], 404);
        }

        if (!$tree->canShow($user)) {
            jsonResponse(["error" => "Access denied to this tree"], 403);
        }

        $individual = \\Fisharebest\\Webtrees\\Registry::individualFactory()->make($xref, $tree);

        if (!$individual) {
            jsonResponse(["error" => "Individual not found"], 404);
        }

        if (!$individual->canShow($user)) {
            jsonResponse(["error" => "Access denied to this individual"], 403);
        }

        $result = [
            "xref" => $individual->xref(),
            "name" => $individual->fullName(),
            "birth_date" => $individual->getBirthDate()->display(),
            "death_date" => $individual->getDeathDate()->display(),
            "birth_place" => $individual->getBirthPlace()->gedcomName(),
            "death_place" => $individual->getDeathPlace()->gedcomName(),
            "sex" => $individual->sex(),
            "gedcom" => $individual->gedcom()
        ];

        // Ajouter les parents
        $families = $individual->childFamilies();
        $parents = [];
        foreach ($families as $family) {
            if ($family->husband()) {
                $parents["father"] = [
                    "xref" => $family->husband()->xref(),
                    "name" => $family->husband()->fullName()
                ];
            }
            if ($family->wife()) {
                $parents["mother"] = [
                    "xref" => $family->wife()->xref(),
                    "name" => $family->wife()->fullName()
                ];
            }
        }
        if (!empty($parents)) {
            $result["parents"] = $parents;
        }

        // Ajouter les conjoints
        $spouses = [];
        foreach ($individual->spouseFamilies() as $family) {
            $spouse = $family->spouse($individual);
            if ($spouse) {
                $spouses[] = [
                    "xref" => $spouse->xref(),
                    "name" => $spouse->fullName()
                ];
            }
        }
        if (!empty($spouses)) {
            $result["spouses"] = $spouses;
        }

        jsonResponse([
            "success" => true,
            "data" => $result
        ]);
        
    } catch (Exception $e) {
        logError("Individual request error: " . $e->getMessage());
        jsonResponse([
            "error" => "Failed to retrieve individual",
            "message" => $e->getMessage()
        ], 500);
    }
}

function handleUserRequest($user) {
    try {
        jsonResponse([
            "success" => true,
            "data" => [
                "id" => $user->id(),
                "username" => $user->userName(),
                "real_name" => $user->realName(),
                "email" => $user->email(),
                "preferences" => [
                    "language" => $user->getPreference("language"),
                    "theme" => $user->getPreference("theme")
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        logError("User request error: " . $e->getMessage());
        jsonResponse([
            "error" => "Failed to retrieve user info",
            "message" => $e->getMessage()
        ], 500);
    }
}
';
    }

    /**
     * Configuration page
     */
    public function getConfigLink(): string
    {
        return route('module', [
            'module' => $this->name(),
            'action' => 'Config',
        ]);
    }

    /**
     * Handle configuration requests
     */
    public function getConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->showConfig();
    }

    /**
     * Show configuration page
     */
    private function showConfig(): ResponseInterface
    {
        $html = $this->getConfigHtml();
        return response($html);
    }

    /**
     * Generate HTML for configuration page
     */
    private function getConfigHtml(): string
    {
        $title = I18N::translate('API REST Configuration');

        // URL de base pour l'API - Toujours utiliser HTTPS si disponible
        $base_url = 'https://' . $_SERVER['HTTP_HOST'];
        if (isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], [80, 443])) {
            $base_url .= ':' . $_SERVER['SERVER_PORT'];
        }
        
        $module_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
        $api_url = $base_url . $module_path . '/api.php';

        return "
        <div class='container-fluid'>
            <h2>" . e($title) . "</h2>

            <div class='card'>
                <div class='card-header'>
                    <h3>🔐 Authentification Login/Password</h3>
                </div>
                <div class='card-body'>
                    <div class='alert alert-info'>
                        <h4>📝 Comment utiliser l'API :</h4>
                        <p>Cette API utilise vos identifiants webtrees existants (login/mot de passe).</p>
                        <p><strong>URL de l'API :</strong> <code>{$api_url}</code></p>
                        <p><strong>⚠️ HTTPS obligatoire :</strong> Votre site redirige automatiquement vers HTTPS, c'est parfait pour la sécurité !</p>
                    </div>
                    
                    <h4>🔗 Endpoints disponibles :</h4>
                    <ul>
                        <li><code>GET {$api_url}?endpoint=trees</code> - Liste des arbres généalogiques</li>
                        <li><code>GET {$api_url}?endpoint=individuals&tree=TREE_NAME</code> - Liste des individus</li>
                        <li><code>GET {$api_url}?endpoint=individual&tree=TREE_NAME&xref=XREF</code> - Détails d'un individu</li>
                        <li><code>GET {$api_url}?endpoint=user</code> - Informations utilisateur</li>
                        <li><code>GET {$api_url}?endpoint=debug</code> - Informations de debug</li>
                    </ul>
                    
                    <h4>🚀 Exemples d'utilisation :</h4>
                    
                    <h5>1. Test de debug (première étape) :</h5>
                    <pre><code>curl -k -u 'username:password' '{$api_url}?endpoint=debug'</code></pre>
                    <p><small>Le -k ignore les certificats SSL auto-signés si nécessaire</small></p>
                    
                    <h5>2. Avec HTTP Basic Auth (recommandé) :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=trees'</code></pre>
                    
                    <h5>3. Avec paramètres GET (pour tests) :</h5>
                    <pre><code>curl '{$api_url}?endpoint=trees&username=USERNAME&password=PASSWORD'</code></pre>
                    
                    <h5>4. Lister les individus d'un arbre :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individuals&tree=TREE_NAME&limit=10'</code></pre>
                    
                    <h5>5. Rechercher des individus :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individuals&tree=TREE_NAME&search=Martin'</code></pre>
                    
                    <h5>6. Obtenir les détails d'un individu :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individual&tree=TREE_NAME&xref=I1'</code></pre>
                    
                    <div class='alert alert-success mt-3'>
                        <strong>🔧 Dépannage :</strong>
                        <ul>
                            <li><strong>Erreur 500 :</strong> Vérifiez les logs PHP du serveur</li>
                            <li><strong>HTTPS :</strong> La redirection HTTPS est normale et sécurisée</li>
                            <li><strong>Certificat :</strong> Utilisez -k avec curl si certificat auto-signé</li>
                            <li><strong>Debug :</strong> Utilisez l'endpoint debug pour diagnostiquer</li>
                        </ul>
                    </div>
                    
                    <div class='alert alert-warning mt-3'>
                        <strong>⚠️ Sécurité :</strong>
                        <ul>
                            <li>HTTPS automatique : ✅ Excellent !</li>
                            <li>Les permissions webtrees sont respectées</li>
                            <li>Seuls les arbres/individus visibles pour l'utilisateur sont accessibles</li>
                            <li>Gestion des erreurs améliorée</li>
                        </ul>
                    </div>
                    
                    <div class='alert alert-success mt-3'>
                        <strong>✅ Test rapide :</strong>
                        <p>Remplacez USERNAME et PASSWORD par vos identifiants webtrees :</p>
                        <pre><code>curl -u 'USERNAME:PASSWORD' '{$api_url}?endpoint=debug'</code></pre>
                    </div>
                    
                    <h4>📊 Réponse JSON type :</h4>
                    <pre><code>{
  \"success\": true,
  \"data\": [
    {
      \"id\": 1,
      \"name\": \"tree1\",
      \"title\": \"Mon arbre généalogique\",
      \"individuals_count\": 1234
    }
  ],
  \"count\": 1
}</code></pre>
                </div>
            </div>
        </div>";
    }

    /**
     * Additional/updated translations.
     */
    public function customTranslations(string $language): array
    {
        $translations = [];
        
        switch ($language) {
            case 'fr':
                $translations = [
                    'API REST Configuration' => 'Configuration API REST',
                ];
                break;
            case 'en':
            default:
                $translations = [
                    'API REST Configuration' => 'REST API Configuration',
                ];
                break;
        }

        return $translations;
    }

    /**
     * The module's schema version.
     */
    public function schemaVersion(): string
    {
        return '1';
    }
};
