<?php
declare(strict_types=1);

// Debug: afficher les erreurs en prod (temporaire)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require __DIR__ . '/../vendor/autoload.php';
// Fallback au cas où l'autoload Composer des "files" n'est pas à jour
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/../src/Fonctions/CSRF.php';
}

use App\View\TwigFactory;
use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Controller\AdminController;
use App\Controller\AdminReminderController;
use App\Controller\ParieurController;
use App\Modele\UtilisateurModele;
use App\Modele\TypeUtilisateurModele;
use App\Modele\UtilisateurTokenModele;
use App\Modele\CampagnePariModele;
use App\Modele\TypePhaseModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\AParierModele;
use App\Modele\ReponsePariModele;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;

// Sessions
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Fix application timezone (server may be UTC)
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Paris');
}

// Initialize Twig
$twig = TwigFactory::create(
    __DIR__ . '/../templates',
    null, // set a path in production, e.g. __DIR__.'/../var/cache/twig'
    true
);

// Create Slim app
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();
$responseFactory = $app->getResponseFactory();

// Models
$userModel = new UtilisateurModele();
$typeModel = new TypeUtilisateurModele();
$campagneModel = new CampagnePariModele();
$typePhaseModel = new TypePhaseModele();
$phaseModel = new PhaseCampagneModele();
$aParierModel = new AParierModele();
$reponseModel = new ReponsePariModele();

// Controllers
$home = new HomeController($twig);
$auth = new AuthController($twig, $userModel, $typeModel, new UtilisateurTokenModele());
$admin = new AdminController($twig, $userModel, $typeModel, $campagneModel, $typePhaseModel, $phaseModel, $aParierModel, $reponseModel);
$adminReminder = new AdminReminderController($twig, $campagneModel, $phaseModel);
$parieur = new ParieurController($twig, $userModel);

// Routes (groupées par contrôleur / use case)
$app->get('/', [$home, 'index']);
$app->get('/hello/{name}', [$home, 'hello']);

// Auth
$app->get('/login', [$auth, 'showLogin']);
$app->post('/login', [$auth, 'login']);
$app->get('/logout', [$auth, 'logout']);
// Activation / définition de mot de passe
$app->get('/account/activate/{token}', [$auth, 'activateForm']);
$app->post('/account/activate/{token}', [$auth, 'activateSubmit']);
// Réinitialisation mot de passe
$app->get('/account/reset', [$auth, 'resetRequestForm']);
$app->post('/account/reset', [$auth, 'resetRequest']);
$app->get('/account/reset/{token}', [$auth, 'resetForm']);
$app->post('/account/reset/{token}', [$auth, 'resetSubmit']);

// Middlewares (PSR-15)
$setTwigGlobals = function (Request $request, RequestHandler $handler) use ($twig) : Response {
    $twig->addGlobal('app', [
        'user' => $_SESSION['user'] ?? null,
        'csrf' => csrf_token(),
    ]);
    return $handler->handle($request);
};

$requireAuth = function (Request $request, RequestHandler $handler) use ($responseFactory): Response {
    if (!isset($_SESSION['user'])) {
        $res = $responseFactory->createResponse(302);
        return $res->withHeader('Location', '/login');
    }
    return $handler->handle($request);
};

$requireRole = function (string $role) use ($responseFactory) {
    return function (Request $request, RequestHandler $handler) use ($role, $responseFactory): Response {
        if (!isset($_SESSION['user']) || ($_SESSION['user']['typeLibelle'] ?? null) !== $role) {
            $res = $responseFactory->createResponse(302);
            return $res->withHeader('Location', '/login');
        }
        return $handler->handle($request);
    };
};

// Global Twig globals middleware
$app->add($setTwigGlobals);

// Ensure UTF-8 content type for HTML responses
$app->add(function (Request $request, RequestHandler $handler) use ($responseFactory): Response {
    $response = $handler->handle($request);
    $ct = $response->getHeaderLine('Content-Type');
    if ($ct === '') {
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
    $ctLower = strtolower($ct);
    if (substr($ctLower, 0, 9) === 'text/html' && stripos($ct, 'charset=') === false) {
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }
    return $response;
});

// Admin area
$app->group('/admin', function ($group) use ($admin, $aParierModel) {
    $group->get('', [$admin, 'home']);
    $group->get('/home-text', [$admin, 'showHomeText']);
    $group->post('/home-text', [$admin, 'saveHomeText']);
    // Campagnes
    $group->get('/campagnes', [$admin, 'listCampagnes']);
    $group->post('/campagnes', [$admin, 'createCampagne']);
    $group->post('/campagnes/{idCampagne}/gain', [$admin, 'setCampagneGain']);
    // Fallback GET -> redirect to listing to avoid 405 if user clicks URL
    $group->get('/campagnes/{idCampagne}/gain', function ($request, $response) {
        return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
    });
    // Types de phase
    $group->get('/types', [$admin, 'listTypes']);
    $group->post('/types', [$admin, 'createType']);
    $group->post('/types/{idType}/delete', [$admin, 'deleteType']);
    $group->get('/types/{idType}/delete', function ($request, $response) {
        return $response->withHeader('Location', '/admin/types')->withStatus(302);
    });
    // Phases d'une campagne
    $group->get('/campagnes/{idCampagne}/phases', [$admin, 'listPhases']);
    $group->post('/campagnes/{idCampagne}/phases', [$admin, 'createPhase']);
    $group->post('/phases/{idPhase}/delete', [$admin, 'deletePhase']);
    // Config calcul des points
    $group->get('/phases/{idPhase}/calculs', [$admin, 'listCalculs']);
    $group->post('/phases/{idPhase}/calculs', [$admin, 'addCalcul']);
    $group->post('/phases/{idPhase}/calculs/{idPc}/delete', [$admin, 'deleteCalcul']);
    $group->get('/phases/{idPhase}/calculs/{idPc}/delete', function ($request, $response, $args) {
        $idPhase = $args['idPhase'] ?? '';
        return $response->withHeader('Location', '/admin/phases/' . $idPhase . '/calculs')->withStatus(302);
    });
    // AParier
    $group->get('/phases/{idPhase}/a-parier', [$admin, 'listAParier']);
    $group->post('/phases/{idPhase}/a-parier', [$admin, 'createAParier']);
    $group->post('/phases/{idPhase}/a-parier/resultats', [$admin, 'setResultatsBatch']);
    // Rappels par email (manuel)
    $group->post('/phases/{idPhase}/reminder', [$adminReminder, 'sendReminderPhase']);
    $group->get('/phases/{idPhase}/reminder', function ($request, $response, $args) use ($phaseModel) {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $ph = $idPhase > 0 ? $phaseModel->findById($idPhase) : null;
        $idCampagne = (int)($ph['idCampagnePari'] ?? 0);
        $target = $idCampagne > 0 ? '/admin/campagnes/' . $idCampagne . '/phases' : '/admin/campagnes';
        return $response->withHeader('Location', $target)->withStatus(302);
    });
    // Résultats
    $group->post('/a-parier/{idAParier}/resultats', [$admin, 'setResultats']);
    $group->get('/a-parier/{idAParier}/resultats', function ($request, $response, $args) use ($aParierModel) {
        $idA = (int)($args['idAParier'] ?? 0);
        $it = $aParierModel->findById($idA);
        $idPhase = (int)($it['idPhaseCampagne'] ?? 0);
        $target = $idPhase > 0 ? '/admin/phases/' . $idPhase . '/a-parier' : '/admin/campagnes';
        return $response->withHeader('Location', $target)->withStatus(302);
    });
    $group->get('/users', [$admin, 'listUsers']);
    $group->get('/users/new', [$admin, 'newUserForm']);
    $group->post('/users', [$admin, 'createUser']);
    $group->post('/users/{id}/resend-invite', [$admin, 'resendInvite']);
})->add($requireRole('administrateur'))->add($requireAuth);

// Parieur area
$app->group('/parieur', function ($group) use ($parieur) {
    // Redirige la racine parieur vers la page campagnes
    $group->get('', function ($request, $response) {
        return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
    });
    $group->get('/password', [$parieur, 'showPassword']);
    $group->post('/password', [$parieur, 'updatePassword']);
    // Pseudo
    $group->get('/pseudo', [$parieur, 'showPseudo']);
    $group->post('/pseudo', [$parieur, 'updatePseudo']);
    // Campagnes
    $group->get('/campagnes', [$parieur, 'campagnesPage']);
    $group->get('/campagnes/{idCampagne}', [$parieur, 'campagneDetail']);
    $group->get('/campagnes/{idCampagne}/classement', [$parieur, 'classementCampagne']);
    $group->get('/campagnes/{idCampagne}/synthese', [$parieur, 'syntheseCampagne']);
    $group->post('/campagnes', [$parieur, 'inscrire']);
    $group->post('/campagnes/{idCampagne}/desinscrire', [$parieur, 'desinscrire']);
    $group->get('/campagnes/{idCampagne}/desinscrire', function ($request, $response) {
        return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
    });
    $group->get('/phases/{idPhase}/parier', [$parieur, 'parier']);
    $group->post('/phases/{idPhase}/parier', [$parieur, 'placerTabulaire']);
    $group->post('/phases/{idPhase}/verrouiller', [$parieur, 'verrouiller']);
    $group->get('/phases/{idPhase}/resultats', [$parieur, 'resultatsPhase']);
    $group->get('/phases/{idPhase}/resultats.csv', [$parieur, 'resultatsPhaseCsv']);
})->add($requireRole('parieur'))->add($requireAuth);

$app->run();
