<?php

/**
 * API REST Module for webtrees 2.2 - VERSION CORRIGÉE AVEC ROUTES DÉDIÉES
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
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API REST Module with dedicated routes
 */
return new class extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface {
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    private const API_KEYS_TABLE = 'api_rest_keys';

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
        return 'Module API REST avec gestion des clés API pour webtrees 2.2';
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
        return '1.0.2'; // Version avec routes dédiées
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
        // Créer la table des clés API si elle n'existe pas
        $this->createApiKeysTable();
        
        // Enregistrer les routes API
        $this->registerApiRoutes();
    }

    /**
     * Register API routes
     */
    private function registerApiRoutes(): void
    {
        // Créer un fichier de routes API dans le dossier du module
        $this->createApiRouteFile();
    }

    /**
     * Create API route file
     */
    private function createApiRouteFile(): void
    {
        $module_path = __DIR__;
        $routes_file = $module_path . '/api_routes.php';
        
        if (!file_exists($routes_file)) {
            $routes_content = $this->getApiRoutesContent();
            file_put_contents($routes_file, $routes_content);
        }
    }

    /**
     * Get API routes content
     */
    private function getApiRoutesContent(): string
    {
        return '<?php
/**
 * API Routes for REST API Module
 */

// Point d\'entrée unique pour toutes les requêtes API
if (isset($_GET[\'api_endpoint\'])) {
    // Inclure l\'autoloader de webtrees
    require_once __DIR__ . \'/../../../../index.php\';
    
    // Gérer la requête API
    handleApiRequest();
    exit;
}

function handleApiRequest() {
    $endpoint = $_GET[\'api_endpoint\'] ?? \'\';
    $api_key = $_SERVER[\'HTTP_X_API_KEY\'] ?? \'\';
    
    // Vérifier la clé API
    if (!validateApiKey($api_key)) {
        http_response_code(401);
        header(\'Content-Type: application/json\');
        echo json_encode([\'error\' => \'Invalid API key\']);
        return;
    }
    
    header(\'Content-Type: application/json\');
    
    switch ($endpoint) {
        case \'trees\':
            handleTreesRequest();
            break;
        case \'individuals\':
            handleIndividualsRequest();
            break;
        case \'individual\':
            handleIndividualRequest();
            break;
        default:
            http_response_code(404);
            echo json_encode([\'error\' => \'Unknown API endpoint\']);
    }
}

function validateApiKey($api_key) {
    if (empty($api_key)) {
        return false;
    }
    
    return \\Fisharebest\\Webtrees\\DB::table(\'api_rest_keys\')
        ->where(\'api_key\', \'=\', $api_key)
        ->exists();
}

function handleTreesRequest() {
    $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
    $trees = $tree_service->all();

    $result = [];
    foreach ($trees as $tree) {
        $result[] = [
            \'id\' => $tree->id(),
            \'name\' => $tree->name(),
            \'title\' => $tree->title(),
        ];
    }

    echo json_encode($result);
}

function handleIndividualsRequest() {
    $tree_name = $_GET[\'tree\'] ?? \'\';
    
    if (empty($tree_name)) {
        http_response_code(400);
        echo json_encode([\'error\' => \'Tree parameter required\']);
        return;
    }

    $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
    $tree = $tree_service->find($tree_name);

    if (!$tree) {
        http_response_code(404);
        echo json_encode([\'error\' => \'Tree not found\']);
        return;
    }

    $limit = min((int) ($_GET[\'limit\'] ?? 50), 100);
    $offset = max((int) ($_GET[\'offset\'] ?? 0), 0);

    $individuals = \\Fisharebest\\Webtrees\\DB::table(\'individuals\')
        ->where(\'i_file\', \'=\', $tree->id())
        ->limit($limit)
        ->offset($offset)
        ->get();

    $result = [];
    foreach ($individuals as $individual) {
        $result[] = [
            \'xref\' => $individual->i_id,
            \'gedcom\' => $individual->i_gedcom,
        ];
    }

    echo json_encode($result);
}

function handleIndividualRequest() {
    $tree_name = $_GET[\'tree\'] ?? \'\';
    $xref = $_GET[\'xref\'] ?? \'\';
    
    if (empty($tree_name) || empty($xref)) {
        http_response_code(400);
        echo json_encode([\'error\' => \'Tree and xref parameters required\']);
        return;
    }
    
    $tree_service = \\Fisharebest\\Webtrees\\Registry::container()->get(\\Fisharebest\\Webtrees\\Services\\TreeService::class);
    $tree = $tree_service->find($tree_name);

    if (!$tree) {
        http_response_code(404);
        echo json_encode([\'error\' => \'Tree not found\']);
        return;
    }

    $individual = \\Fisharebest\\Webtrees\\Registry::individualFactory()->make($xref, $tree);

    if (!$individual) {
        http_response_code(404);
        echo json_encode([\'error\' => \'Individual not found\']);
        return;
    }

    $result = [
        \'xref\' => $individual->xref(),
        \'name\' => $individual->fullName(),
        \'birth_date\' => $individual->getBirthDate()->display(),
        \'death_date\' => $individual->getDeathDate()->display(),
        \'gedcom\' => $individual->gedcom(),
    ];

    echo json_encode($result);
}
';
    }

    /**
     * Create API keys table
     */
    private function createApiKeysTable(): void
    {
        if (!DB::schema()->hasTable(self::API_KEYS_TABLE)) {
            DB::schema()->create(self::API_KEYS_TABLE, function ($table) {
                $table->increments('id');
                $table->string('name', 100);
                $table->string('api_key', 64)->unique();
                $table->timestamp('created_at')->useCurrent();
            });
        }
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
        $query_params = $request->getQueryParams();
        $subaction = $query_params['subaction'] ?? '';
        
        // Gérer la suppression
        if ($subaction === 'delete') {
            return $this->deleteApiKey($request);
        }
        
        // Afficher la configuration
        return $this->showConfig();
    }

    /**
     * Show configuration page
     */
    private function showConfig(): ResponseInterface
    {
        // Vérifier s'il faut générer une clé automatiquement
        $this->ensureApiKeyExists();
        
        $api_keys = DB::table(self::API_KEYS_TABLE)
            ->orderBy('created_at', 'desc')
            ->get();

        $html = $this->getConfigHtml($api_keys);
        
        return response($html);
    }

    /**
     * Ensure at least one API key exists
     */
    private function ensureApiKeyExists(): void
    {
        $count = DB::table(self::API_KEYS_TABLE)->count();
        
        if ($count === 0) {
            $api_key = bin2hex(random_bytes(32));
            
            DB::table(self::API_KEYS_TABLE)->insert([
                'name' => 'Clé API automatique - ' . date('Y-m-d H:i:s'),
                'api_key' => $api_key,
            ]);
        }
    }

    /**
     * Generate HTML for configuration page
     */
    private function getConfigHtml($api_keys): string
    {
        $title = I18N::translate('API Key Management');
        $key_label = I18N::translate('API Key');
        $created_label = I18N::translate('Created');
        $actions_label = I18N::translate('Actions');
        $delete_label = I18N::translate('Delete');

        $rows = '';
        $current_api_key = '';
        
        foreach ($api_keys as $key) {
            if (empty($current_api_key)) {
                $current_api_key = $key->api_key;
            }
            
            $delete_url = route('module', [
                'module' => $this->name(),
                'action' => 'Config',
                'subaction' => 'delete',
                'id' => $key->id
            ]);
            
            $rows .= "<tr>
                <td>" . e($key->name) . "</td>
                <td><code>" . e($key->api_key) . "</code></td>
                <td>" . e($key->created_at) . "</td>
                <td><a href='" . e($delete_url) . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Êtes-vous sûr ?\");'>" . e($delete_label) . "</a></td>
            </tr>";
        }

        // Obtenir le chemin du module pour les URLs API
        $module_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
        $api_base_url = "https://genealogie.vahinecestgonfle.com" . $module_path . "/api_routes.php";

        return "
        <div class='container-fluid'>
            <h2>" . e($title) . "</h2>

            <div class='card'>
                <div class='card-header'>
                    <h3>Clés API existantes</h3>
                </div>
                <div class='card-body'>
                    <table class='table table-striped'>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>" . e($key_label) . "</th>
                                <th>" . e($created_label) . "</th>
                                <th>" . e($actions_label) . "</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class='card mt-4'>
                <div class='card-header'>
                    <h3>Documentation API</h3>
                </div>
                <div class='card-body'>
                    <div class='alert alert-info'>
                        <h4>🔑 Votre clé API :</h4>
                        <p><strong><code>" . e($current_api_key) . "</code></strong></p>
                        <p><small>Utilisez cette clé dans l'en-tête <code>X-API-Key</code> de vos requêtes.</small></p>
                    </div>
                    
                    <h4>Endpoints disponibles :</h4>
                    <ul>
                        <li><code>GET {$api_base_url}?api_endpoint=trees</code> - Liste des arbres généalogiques</li>
                        <li><code>GET {$api_base_url}?api_endpoint=individuals&tree=TREE_NAME</code> - Liste des individus d'un arbre</li>
                        <li><code>GET {$api_base_url}?api_endpoint=individual&tree=TREE_NAME&xref=XREF</code> - Détails d'un individu</li>
                    </ul>
                    
                    <h4>Exemples d'utilisation :</h4>
                    <h5>1. Lister les arbres généalogiques :</h5>
                    <pre><code>curl -H 'X-API-Key: " . e($current_api_key) . "' \\
     '{$api_base_url}?api_endpoint=trees'</code></pre>
     
                    <h5>2. Lister les individus d'un arbre :</h5>
                    <pre><code>curl -H 'X-API-Key: " . e($current_api_key) . "' \\
     '{$api_base_url}?api_endpoint=individuals&tree=TREE_NAME'</code></pre>
     
                    <h5>3. Obtenir les détails d'un individu :</h5>
                    <pre><code>curl -H 'X-API-Key: " . e($current_api_key) . "' \\
     '{$api_base_url}?api_endpoint=individual&tree=TREE_NAME&xref=I1'</code></pre>
                    
                    <div class='alert alert-warning mt-3'>
                        <strong>⚠️ Sécurité :</strong> Gardez votre clé API secrète. Ne la partagez pas publiquement et ne l'incluez pas dans du code visible côté client.
                    </div>
                    
                    <div class='alert alert-success mt-3'>
                        <strong>✅ Test rapide :</strong> Vous pouvez tester immédiatement avec cette commande :
                        <pre><code>curl -H 'X-API-Key: " . e($current_api_key) . "' '{$api_base_url}?api_endpoint=trees'</code></pre>
                    </div>
                </div>
            </div>
        </div>";
    }

    /**
     * Delete API key
     */
    private function deleteApiKey(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? '';

        if ($id) {
            $result = DB::table(self::API_KEYS_TABLE)->where('id', '=', $id)->delete();
            if ($result) {
                FlashMessages::addMessage(I18N::translate('API key deleted successfully'), 'success');
            } else {
                FlashMessages::addMessage('Erreur lors de la suppression de la clé API', 'danger');
            }
        }

        return redirect($this->getConfigLink());
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
                    'API REST' => 'API REST',
                    'API Key Management' => 'Gestion des clés API',
                    'Generate API Key' => 'Générer une clé API',
                    'API Key Name' => 'Nom de la clé API',
                    'API Key' => 'Clé API',
                    'Created' => 'Créé',
                    'Actions' => 'Actions',
                    'Delete' => 'Supprimer',
                    'Generate' => 'Générer',
                    'API key generated successfully' => 'Clé API générée avec succès',
                    'API key deleted successfully' => 'Clé API supprimée avec succès',
                    'Invalid API key' => 'Clé API invalide',
                    'Access denied' => 'Accès refusé',
                ];
                break;
            case 'en':
            default:
                $translations = [
                    'API REST' => 'REST API',
                    'API Key Management' => 'API Key Management',
                    'Generate API Key' => 'Generate API Key',
                    'API Key Name' => 'API Key Name',
                    'API Key' => 'API Key',
                    'Created' => 'Created',
                    'Actions' => 'Actions',
                    'Delete' => 'Delete',
                    'Generate' => 'Generate',
                    'API key generated successfully' => 'API key generated successfully',
                    'API key deleted successfully' => 'API key deleted successfully',
                    'Invalid API key' => 'Invalid API key',
                    'Access denied' => 'Access denied',
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
