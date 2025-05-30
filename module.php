<?php

/**
 * API REST Module for webtrees 2.2
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
use Fisharebest\Webtrees\Http\Middleware\MiddlewareInterface;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * API REST Module with API Key management
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
        return '1.0.0';
    }

    /**
     * Where to get support for this module.
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/conichonhaa/api-rest';
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
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Créer la table des clés API si elle n'existe pas
        $this->createApiKeysTable();
        
        // Pour webtrees 2.2, nous devons gérer les routes différemment
        // Les routes API seront gérées via des actions du module
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
        // Gérer les appels API via les paramètres d'URL
        $query_params = $request->getQueryParams();
        $action = $query_params['action'] ?? 'show';
        $api_action = $query_params['api'] ?? '';
        
        // Si c'est un appel API
        if ($api_action) {
            return $this->handleApiRequest($request, $api_action);
        }
        
        // Sinon, gérer la configuration normale
        switch ($action) {
            case 'generate':
                return $this->generateApiKey($request);
            case 'delete':
                return $this->deleteApiKey($request);
            default:
                return $this->showConfig();
        }
    }

    /**
     * Show configuration page
     */
    private function showConfig(): ResponseInterface
    {
        $api_keys = DB::table(self::API_KEYS_TABLE)
            ->orderBy('created_at', 'desc')
            ->get();

        $html = $this->getConfigHtml($api_keys);
        
        return response($html);
    }

    /**
     * Generate HTML for configuration page
     */
    private function getConfigHtml($api_keys): string
    {
        $title = I18N::translate('API Key Management');
        $generate_label = I18N::translate('Generate API Key');
        $name_label = I18N::translate('API Key Name');
        $key_label = I18N::translate('API Key');
        $created_label = I18N::translate('Created');
        $actions_label = I18N::translate('Actions');
        $delete_label = I18N::translate('Delete');
        $generate_button = I18N::translate('Generate');

        $rows = '';
        foreach ($api_keys as $key) {
            $delete_url = route('module', [
                'module' => $this->name(),
                'action' => 'Config',
                'subaction' => 'delete',
                'id' => $key->id
            ]);
            
            $rows .= "<tr>
                <td>{$key->name}</td>
                <td><code>{$key->api_key}</code></td>
                <td>{$key->created_at}</td>
                <td><a href='{$delete_url}' class='btn btn-danger btn-sm' onclick='return confirm(\"Êtes-vous sûr ?\");'>{$delete_label}</a></td>
            </tr>";
        }

        $generate_url = route('module', [
            'module' => $this->name(),
            'action' => 'Config',
            'subaction' => 'generate'
        ]);

        return "
        <div class='container-fluid'>
            <h2>{$title}</h2>
            
            <div class='card mb-4'>
                <div class='card-header'>
                    <h3>{$generate_label}</h3>
                </div>
                <div class='card-body'>
                    <form method='post' action='{$generate_url}'>
                        <div class='form-group'>
                            <label for='key_name'>{$name_label}</label>
                            <input type='text' class='form-control' id='key_name' name='key_name' required>
                        </div>
                        <button type='submit' class='btn btn-primary'>{$generate_button}</button>
                    </form>
                </div>
            </div>

            <div class='card'>
                <div class='card-header'>
                    <h3>Clés API existantes</h3>
                </div>
                <div class='card-body'>
                    <table class='table table-striped'>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>{$key_label}</th>
                                <th>{$created_label}</th>
                                <th>{$actions_label}</th>
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
                    <h4>Endpoints disponibles :</h4>
                    <ul>
                        <li><code>GET /index.php?route=module&amp;module=api-rest&amp;action=Config&amp;api=trees</code> - Liste des arbres généalogiques</li>
                        <li><code>GET /index.php?route=module&amp;module=api-rest&amp;action=Config&amp;api=individuals&amp;tree=TREE_NAME</code> - Liste des individus d'un arbre</li>
                        <li><code>GET /index.php?route=module&amp;module=api-rest&amp;action=Config&amp;api=individual&amp;tree=TREE_NAME&amp;xref=XREF</code> - Détails d'un individu</li>
                    </ul>
                    <h4>Utilisation :</h4>
                    <p>Ajoutez l'en-tête <code>X-API-Key: votre_cle_api</code> à vos requêtes.</p>
                    <p>Exemple :</p>
                    <pre><code>curl -H 'X-API-Key: votre_cle_api' \
     'https://votre-domaine.com/index.php?route=module&amp;module=api-rest&amp;action=Config&amp;api=trees'</code></pre>
                </div>
            </div>
        </div>";
    }

    /**
     * Generate new API key
     */
    private function generateApiKey(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();
        $name = $params['key_name'] ?? '';

        if (empty($name)) {
            FlashMessages::addMessage('Le nom de la clé est requis', 'danger');
            return redirect($this->getConfigLink());
        }

        $api_key = bin2hex(random_bytes(32));

        DB::table(self::API_KEYS_TABLE)->insert([
            'name' => $name,
            'api_key' => $api_key,
        ]);

        FlashMessages::addMessage(I18N::translate('API key generated successfully'), 'success');
        return redirect($this->getConfigLink());
    }

    /**
     * Delete API key
     */
    private function deleteApiKey(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'] ?? '';

        if ($id) {
            DB::table(self::API_KEYS_TABLE)->where('id', '=', $id)->delete();
            FlashMessages::addMessage(I18N::translate('API key deleted successfully'), 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Handle API requests
     */
    private function handleApiRequest(ServerRequestInterface $request, string $api_action): ResponseInterface
    {
        // Vérifier la clé API
        if (!$this->checkApiKey($request)) {
            return response(['error' => I18N::translate('Invalid API key')], 401)
                ->withHeader('Content-Type', 'application/json');
        }

        $query_params = $request->getQueryParams();
        
        switch ($api_action) {
            case 'trees':
                return $this->getTrees($request);
            case 'individuals':
                $tree = $query_params['tree'] ?? '';
                return $this->getIndividuals($request, $tree);
            case 'individual':
                $tree = $query_params['tree'] ?? '';
                $xref = $query_params['xref'] ?? '';
                return $this->getIndividual($request, $tree, $xref);
            default:
                return response(['error' => 'Unknown API endpoint'], 404)
                    ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * API Middleware - Check API key
     */
    private function checkApiKey(ServerRequestInterface $request): bool
    {
        $api_key = $request->getHeaderLine('X-API-Key');
        
        if (empty($api_key)) {
            return false;
        }

        return $this->validateApiKey($api_key);
    }

    /**
     * Validate API key
     */
    private function validateApiKey(string $api_key): bool
    {
        return DB::table(self::API_KEYS_TABLE)
            ->where('api_key', '=', $api_key)
            ->exists();
    }

    /**
     * API: Get all trees
     */
    private function getTrees(ServerRequestInterface $request): ResponseInterface
    {
        $tree_service = Registry::container()->get(TreeService::class);
        $trees = $tree_service->all();

        $result = [];
        foreach ($trees as $tree) {
            $result[] = [
                'id' => $tree->id(),
                'name' => $tree->name(),
                'title' => $tree->title(),
            ];
        }

        return response($result)->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Get individuals from a tree
     */
    private function getIndividuals(ServerRequestInterface $request, string $tree_name): ResponseInterface
    {
        if (empty($tree_name)) {
            return response(['error' => 'Tree parameter required'], 400)
                ->withHeader('Content-Type', 'application/json');
        }

        $tree_service = Registry::container()->get(TreeService::class);
        $tree = $tree_service->find($tree_name);

        if (!$tree) {
            return response(['error' => 'Tree not found'], 404)
                ->withHeader('Content-Type', 'application/json');
        }

        $query_params = $request->getQueryParams();
        $limit = min((int) ($query_params['limit'] ?? 50), 100);
        $offset = max((int) ($query_params['offset'] ?? 0), 0);

        $individuals = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->limit($limit)
            ->offset($offset)
            ->get();

        $result = [];
        foreach ($individuals as $individual) {
            $result[] = [
                'xref' => $individual->i_id,
                'gedcom' => $individual->i_gedcom,
            ];
        }

        return response($result)->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Get specific individual
     */
    private function getIndividual(ServerRequestInterface $request, string $tree_name, string $xref): ResponseInterface
    {
        if (empty($tree_name) || empty($xref)) {
            return response(['error' => 'Tree and xref parameters required'], 400)
                ->withHeader('Content-Type', 'application/json');
        }
        
        $tree_service = Registry::container()->get(TreeService::class);
        $tree = $tree_service->find($tree_name);

        if (!$tree) {
            return response(['error' => 'Tree not found'], 404)
                ->withHeader('Content-Type', 'application/json');
        }

        $individual = Registry::individualFactory()->make($xref, $tree);

        if (!$individual) {
            return response(['error' => 'Individual not found'], 404)
                ->withHeader('Content-Type', 'application/json');
        }

        $result = [
            'xref' => $individual->xref(),
            'name' => $individual->fullName(),
            'birth_date' => $individual->getBirthDate()->display(),
            'death_date' => $individual->getDeathDate()->display(),
            'gedcom' => $individual->gedcom(),
        ];

        return response($result)->withHeader('Content-Type', 'application/json');
    }

    /**
     * The module's schema version.
     */
    public function schemaVersion(): string
    {
        return '1';
    }
};
