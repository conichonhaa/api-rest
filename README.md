# Module API REST pour webtrees

Module webtrees permettant d'exposer les données généalogiques via une API REST JSON sécurisée.

## Fonctionnalités

- ✅ API REST JSON avec authentification par clé API
- ✅ Endpoints pour les individus et familles
- ✅ Pagination des résultats
- ✅ Logging optionnel des requêtes
- ✅ Interface d'administration intégrée
- ✅ Support CORS pour applications web
- ✅ Sécurité renforcée avec hash_equals()

## Installation

1. Téléchargez le module
2. Extraire dans `webtrees/modules_v4/api-rest/`
3. Structure des fichiers :
   ```
   modules_v4/api-rest/
   ├── module.php
   ├── ApiRestModule.php
   ├── resources/
   │   └── views/
   │       └── modules/
   │           └── api-rest/
   │               └── config.phtml
   └── README.md
   ```

## Configuration

1. Allez dans **Panneau de contrôle > Modules > API REST JSON Sécurisée**
2. Activez l'API
3. Générez une clé API
4. Optionnellement, activez le logging des requêtes

## Utilisation

### Authentification

Deux méthodes d'authentification :

**Method 1: Header Authorization**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     "https://your-site.com/api/individuals/1"
```

**Method 2: Query Parameter**
```bash
curl "https://your-site.com/api/individuals/1?api_key=YOUR_API_KEY"
```

### Endpoints disponibles

#### GET `/api/individuals/{tree_id}`
Récupère la liste des individus d'un arbre généalogique.

**Paramètres :**
- `limit` : Nombre d'éléments à retourner (défaut: 100, max: 1000)
- `offset` : Décalage pour la pagination (défaut: 0)

**Exemple de réponse :**
```json
{
    "data": [
        {
            "id": "I1",
            "name": "Jean DUPONT",
            "birth_date": "1950-01-15",
            "death_date": null,
            "birth_place": "Paris, France",
            "death_place": "",
            "sex": "M",
            "url": "https://your-site.com/individual/I1/Jean-DUPONT"
        }
    ],
    "meta": {
        "total": 1250,
        "limit": 100,
        "offset": 0,
        "returned": 100
    }
}
```

#### GET `/api/families/{tree_id}`
Récupère la liste des familles d'un arbre généalogique.

**Paramètres :**
- `limit` : Nombre d'éléments à retourner (défaut: 100, max: 1000)
- `offset` : Décalage pour la pagination (défaut: 0)

**Exemple de réponse :**
```json
{
    "data": [
        {
            "id": "F1",
            "husband": {
                "id": "I1",
                "name": "Jean DUPONT"
            },
            "wife": {
                "id": "I2",
                "name": "Marie MARTIN"
            },
            "marriage_date": "1975-06-20",
            "marriage_place": "Lyon, France",
            "children_count": 2,
            "url": "https://your-site.com/family/F1"
        }
    ],
    "meta": {
        "total": 450,
        "limit": 100,
        "offset": 0,
        "returned": 100
    }
}
```

### Codes de réponse HTTP

- `200` : Succès
- `401` : Clé API invalide ou manquante
- `404` : Arbre généalogique ou endpoint introuvable
- `500` : Erreur serveur
- `503` : API désactivée

## Exemples d'utilisation

### JavaScript/Fetch
```javascript
const apiKey = 'YOUR_API_KEY';
const treeId = 1;

fetch(`/api/individuals/${treeId}?limit=50`, {
    headers: {
        'Authorization': `Bearer ${apiKey}`
    }
})
.then(response => response.json())
.then(data => {
    console.log('Individus:', data.data);
    console.log('Total:', data.meta.total);
});
```

### PHP/cURL
```php
$apiKey = 'YOUR_API_KEY';
$treeId = 1;
$url = "https://your-site.com/api/individuals/{$treeId}?limit=100";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey}"
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

print_r($data);
```

### Python/Requests
```python
import requests

api_key = 'YOUR_API_KEY'
tree_id = 1
url = f'https://your-site.com/api/individuals/{tree_id}'

headers = {'Authorization': f'Bearer {api_key}'}
params = {'limit': 100, 'offset': 0}

response = requests.get(url, headers=headers, params=params)
data = response.json()

print(f"Total individuals: {data['meta']['total']}")
for individual in data['data']:
    print(f"- {individual['name']} ({individual['id']})")
```

## Sécurité

- Clés API générées avec `random_bytes(32)` (64 caractères hex)
- Comparaison sécurisée avec `hash_equals()`
- Support HTTPS recommandé
- Logging optionnel des requêtes avec IP et User-Agent
- Limitation du nombre d'éléments par requête (max 1000)

## Logs

Si activé, les requêtes sont loggées dans `data/logs/api-rest.log` :

```json
{"timestamp":"2024-01-15 14:30:25","ip":"192.168.1.100","method":"GET","uri":"https://site.com/api/individuals/1?limit=50","user_agent":"MyApp/1.0"}
```

## Compatibilité

- webtrees 2.1.x
- webtrees 2.2.x
- PHP 7.4+

## Développement

Pour étendre le module :

1. Ajoutez de nouveaux endpoints dans la méthode `boot()`
2. Implémentez les handlers dans la méthode `handle()`
3. Utilisez `createJsonResponse()` pour les réponses uniformes

## Licence

Compatible avec la licence GPL de webtrees.

## Support

Pour signaler des bugs ou demander des fonctionnalités, utilisez les issues GitHub du projet.