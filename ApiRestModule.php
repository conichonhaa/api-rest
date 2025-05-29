<?php

declare(strict_types=1);

namespace WebtreesModules\ApiRest;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Date;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiRestModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    private const SETTING_API_KEY = 'API_KEY';
    private const SETTING_ENABLED = 'API_ENABLED';
    private const SETTING_LOG_REQUESTS = 'LOG_REQUESTS';
    private const SETTING_RATE_LIMIT = 'RATE_LIMIT_PER_HOUR';

    /**
     * Module constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Module name - must match directory name
     */
    public function name(): string
    {
        return 'api-rest';
    }

    /**
     * Module title
     */
    public function title(): string
    {
        return 'API REST JSON Sécurisée';
    }

    /**
     * Module description
     */
    public function description(): string
    {
        return 'API REST JSON sécurisée avec authentification par clé API pour accéder aux données généalogiques';
    }

    /**
     * Module version
     */
    public function customModuleVersion(): string
    {
        return '1.2.0';
    }

    /**
     * Module author name
     */
    public function customModuleAuthorName(): string
    {
        return 'Votre Nom';
    }

    /**
     * Module support URL
     */
    public function customModuleSupportUrl(): string
    {
        return '';
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Enregistrer les routes de l'API uniquement si activée
        if ($this->getPreference(self::SETTING_ENABLED, '0') === '1') {
            $routeMap = Registry::routeFactory()->routeMap();
            
            // Routes principales
            $routeMap->get('/api/individuals/{tree}', $this)->tokens(['tree' => '\d+']);
            $routeMap->get('/api/families/{tree}', $this)->tokens(['tree' => '\d+']);
            $routeMap->get('/api/trees', $this);
            
            // Routes détaillées
            $routeMap->get('/api/individual/{tree}/{xref}', $this)->tokens(['tree' => '\d+', 'xref' => '[A-Z0-9_-]+']);
            $routeMap->get('/api/family/{tree}/{xref}', $this)->tokens(['tree' => '\d+', 'xref' => '[A-Z0-9_-]+']);
            
            // Routes de recherche
            $routeMap->get('/api/search/{tree}', $this)->tokens(['tree' => '\d+']);
            
            // Routes de statistiques
            $routeMap->get('/api/stats/{tree}', $this)->tokens(['tree' => '\d+']);
            
            // Routes d'anniversaires (pour les rappels)
            $routeMap->get('/api/anniversaries/{tree}', $this)->tokens(['tree' => '\d+']);
            $routeMap->get('/api/anniversaries/{tree}/{date}', $this)->tokens(['tree' => '\d+', 'date' => '\d{4}-\d{2}-\d{2}']);
        }
    }

    /**
     * Configuration page
     */
    public function getConfigLink(): string
    {
        return route('module', [
            'module' => $this->name(),
            'action' => 'Config'
        ]);
    }

    /**
     * Handle configuration requests
     */
    public function getConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $api_key = $this->getPreference(self::SETTING_API_KEY, '');
        $api_enabled = $this->getPreference(self::SETTING_ENABLED, '0');
        $log_requests = $this->getPreference(self::SETTING_LOG_REQUESTS, '0');
        $rate_limit = $this->getPreference(self::SETTING_RATE_LIMIT, '1000');

        return $this->viewResponse('modules/api-rest/config', [
            'title' => $this->title(),
            'module' => $this->name(),
            'api_key' => $api_key,
            'api_enabled' => $api_enabled,
            'log_requests' => $log_requests,
            'rate_limit' => $rate_limit,
        ]);
    }

    /**
     * Save configuration
     */
    public function postConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if (isset($params['generate_key'])) {
            // Générer une nouvelle clé API
            $new_key = $this->generateApiKey();
            $this->setPreference(self::SETTING_API_KEY, $new_key);
            FlashMessages::addMessage('Nouvelle clé API générée avec succès');
        } else {
            // Sauvegarder les paramètres
            $this->setPreference(self::SETTING_ENABLED, $params['api_enabled'] ?? '0');
            $this->setPreference(self::SETTING_LOG_REQUESTS, $params['log_requests'] ?? '0');
            $this->setPreference(self::SETTING_RATE_LIMIT, $params['rate_limit'] ?? '1000');
            FlashMessages::addMessage('Configuration sauvegardée');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Handle HTTP requests
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Vérifier si l'API est activée
        if ($this->getPreference(self::SETTING_ENABLED, '0') !== '1') {
            return $this->createJsonResponse(['error' => 'API désactivée'], 503);
        }

        // Vérifier l'authentification
        if (!$this->isAuthenticated($request)) {
            return $this->createJsonResponse(['error' => 'Clé API invalide ou manquante'], 401);
        }

        // Vérifier la limite de débit
        if (!$this->checkRateLimit($request)) {
            return $this->createJsonResponse(['error' => 'Limite de débit dépassée'], 429);
        }

        $path = $request->getUri()->getPath();
        $route = $request->getAttribute('route');

        // Logger la requête si activé
        if ($this->getPreference(self::SETTING_LOG_REQUESTS, '0') === '1') {
            $this->logRequest($request);
        }

        try {
            // Route pour la liste des arbres
            if ($path === '/api/trees') {
                return $this->getTrees($request);
            }

            // Routes nécessitant un arbre
            $tree_id = $route->getArgument('tree');
            $tree = Registry::treeService()->find((int) $tree_id);
            
            if (!$tree instanceof Tree) {
                return $this->createJsonResponse(['error' => 'Arbre généalogique introuvable'], 404);
            }

            // Router vers les bonnes méthodes
            if (preg_match('#^/api/individuals/(\d+)$#', $path)) {
                return $this->getIndividuals($request, $tree);
            } elseif (preg_match('#^/api/families/(\d+)$#', $path)) {
                return $this->getFamilies($request, $tree);
            } elseif (preg_match('#^/api/individual/(\d+)/([A-Z0-9_-]+)$#', $path, $matches)) {
                return $this->getIndividual($request, $tree, $matches[2]);
            } elseif (preg_match('#^/api/family/(\d+)/([A-Z0-9_-]+)$#', $path, $matches)) {
                return $this->getFamily($request, $tree, $matches[2]);
            } elseif (preg_match('#^/api/search/(\d+)$#', $path)) {
                return $this->searchInTree($request, $tree);
            } elseif (preg_match('#^/api/stats/(\d+)$#', $path)) {
                return $this->getTreeStats($request, $tree);
            } elseif (preg_match('#^/api/anniversaries/(\d+)$#', $path)) {
                return $this->getAnniversaries($request, $tree);
            } elseif (preg_match('#^/api/anniversaries/(\d+)/(\d{4}-\d{2}-\d{2})$#', $path, $matches)) {
                return $this->getAnniversariesByDate($request, $tree, $matches[2]);
            }

            return $this->createJsonResponse(['error' => 'Endpoint non trouvé'], 404);

        } catch (\Exception $e) {
            return $this->createJsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available trees
     */
    private function getTrees(ServerRequestInterface $request): ResponseInterface
    {
        $trees = [];
        
        foreach (Registry::treeService()->all() as $tree) {
            $trees[] = [
                'id' => $tree->id(),
                'name' => $tree->name(),
                'title' => $tree->title(),
                'individuals_count' => $tree->individuals()->count(),
                'families_count' => $tree->families()->count(),
            ];
        }

        return $this->createJsonResponse([
            'data' => $trees,
            'meta' => [
                'total' => count($trees)
            ]
        ]);
    }

    /**
     * Get individual by XREF
     */
    private function getIndividual(ServerRequestInterface $request, Tree $tree, string $xref): ResponseInterface
    {
        $individual = Registry::individualFactory()->make($xref, $tree);
        
        if (!$individual instanceof Individual) {
            return $this->createJsonResponse(['error' => 'Individu introuvable'], 404);
        }

        $birth_date = $individual->getBirthDate();
        $death_date = $individual->getDeathDate();
        
        // Récupérer les familles
        $families = [];
        foreach ($individual->spouseFamilies() as $family) {
            $spouse = $family->spouse($individual);