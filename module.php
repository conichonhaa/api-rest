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
        return '1.1.0'; // Version avec authentification login/password
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

// Headers pour API REST
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    exit(0);
}

// Inclure webtrees bootstrap
require_once __DIR__ . "/../../../../index.php";

// Fonction d\'authentification
function authenticateUser() {
    $username = null;
    $password = null;
    
    // Essayer HTTP Basic Auth
    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        $auth = $_SERVER["HTTP_AUTHORIZATION"];
        if (strpos($auth, "Basic ") === 0) {
            $credentials = base64_decode(substr($auth, 6));
            list($username, $password) = explode(":", $credentials, 2);
        }
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
}

// Fonction de réponse JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Vérifier l\'authentification
$user = authenticateUser();
if (!$user) {
    jsonResponse([
        "error" => "Authentication required",
        "message" => "Please provide valid username and password via Basic Auth or parameters"
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
    default:
        jsonResponse([
            "error" => "Unknown endpoint",
            "available_endpoints" => [
                "trees" => "List all family trees",
                "individuals" => "List individuals (requires tree parameter)",
                "individual" => "Get individual details (requires tree and xref parameters)",
                "user" => "Get current user info"
            ]
        ], 404);
}

function handleTreesRequest() {
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
}

function handleIndividualsRequest($user) {
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
        $query->where("i_gedcom", "LIKE", "%".addslashes($search)."%");
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
}

function handleIndividualRequest($user) {
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
}

function handleUserRequest($user) {
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

        // URL de base pour l\'API
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
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
                    </div>
                    
                    <h4>🔗 Endpoints disponibles :</h4>
                    <ul>
                        <li><code>GET {$api_url}?endpoint=trees</code> - Liste des arbres généalogiques</li>
                        <li><code>GET {$api_url}?endpoint=individuals&tree=TREE_NAME</code> - Liste des individus</li>
                        <li><code>GET {$api_url}?endpoint=individual&tree=TREE_NAME&xref=XREF</code> - Détails d'un individu</li>
                        <li><code>GET {$api_url}?endpoint=user</code> - Informations utilisateur</li>
                    </ul>
                    
                    <h4>🚀 Exemples d'utilisation :</h4>
                    
                    <h5>1. Avec HTTP Basic Auth (recommandé) :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=trees'</code></pre>
                    
                    <h5>2. Avec paramètres GET (pour tests) :</h5>
                    <pre><code>curl '{$api_url}?endpoint=trees&username=USERNAME&password=PASSWORD'</code></pre>
                    
                    <h5>3. Lister les individus d'un arbre :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individuals&tree=TREE_NAME&limit=10'</code></pre>
                    
                    <h5>4. Rechercher des individus :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individuals&tree=TREE_NAME&search=Martin'</code></pre>
                    
                    <h5>5. Obtenir les détails d'un individu :</h5>
                    <pre><code>curl -u 'username:password' '{$api_url}?endpoint=individual&tree=TREE_NAME&xref=I1'</code></pre>
                    
                    <div class='alert alert-warning mt-3'>
                        <strong>⚠️ Sécurité :</strong>
                        <ul>
                            <li>Utilisez HTTPS en production</li>
                            <li>Les permissions webtrees sont respectées</li>
                            <li>Seuls les arbres/individus visibles pour l'utilisateur sont accessibles</li>
                        </ul>
                    </div>
                    
                    <div class='alert alert-success mt-3'>
                        <strong>✅ Test rapide :</strong>
                        <p>Remplacez USERNAME et PASSWORD par vos identifiants webtrees :</p>
                        <pre><code>curl -u 'USERNAME:PASSWORD' '{$api_url}?endpoint=trees'</code></pre>
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
     * The module\'s schema version.
     */
    public function schemaVersion(): string
    {
        return '1';
    }
};
