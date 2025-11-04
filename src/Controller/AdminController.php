<?php
declare(strict_types=1);

namespace App\Controller;

use App\Modele\TypeUtilisateurModele;
use App\Modele\UtilisateurModele;
use App\Modele\CampagnePariModele;
use App\Modele\TypePhaseModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\AParierModele;
use App\Modele\ReponsePariModele;
use App\Modele\TypeResultatModele;
use App\Modele\PhaseCalculPointModele;
use App\Modele\InscriptionPariModele;
use App\Modele\UtilisateurTokenModele;
use App\Modele\PhaseParieurVerrouModele;
use App\Modele\PariModele;
use App\Service\Mailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

final class AdminController
{
    public function __construct(
        private Environment $twig,
        private UtilisateurModele $users,
        private TypeUtilisateurModele $types,
        private CampagnePariModele $campagnes = new CampagnePariModele(),
        private TypePhaseModele $typePhase = new TypePhaseModele(),
        private PhaseCampagneModele $phases = new PhaseCampagneModele(),
        private AParierModele $aParier = new AParierModele(),
        private ReponsePariModele $reponses = new ReponsePariModele(),
        private InscriptionPariModele $inscriptions = new InscriptionPariModele(),
        private TypeResultatModele $typeResultat = new TypeResultatModele(),
        private PhaseCalculPointModele $phaseCalc = new PhaseCalculPointModele(),
        private UtilisateurTokenModele $userTokens = new UtilisateurTokenModele()
    ) {}

    public function home(Request $request, Response $response): Response
    {
        $param = new \App\Modele\ParametreModele();
        $homeText = $param->get('home_text') ?? '';
        $html = $this->twig->render('admin/home.html.twig', [
            'title' => 'Espace administrateur',
            'homeText' => $homeText,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function showHomeText(Request $request, Response $response): Response
    {
        $param = new \App\Modele\ParametreModele();
        $homeText = $param->get('home_text') ?? '';
        $html = $this->twig->render('admin/home_text.html.twig', [
            'title' => 'Texte d\'accueil',
            'homeText' => $homeText,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

        public function saveHomeText(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', '/admin/home-text')->withStatus(302);
        }
        $text = (string)($data['home_text'] ?? '');
        (new \App\Modele\ParametreModele())->set('home_text', sanitize_html($text));
        $_SESSION['flash_ok'] = 'Texte d\'accueil mis à jour';
        return $response->withHeader('Location', '/admin/home-text')->withStatus(302);
    }
    public function listUsers(Request $request, Response $response): Response
    {
        $users = $this->users->findAll();
        $html = $this->twig->render('admin/users.html.twig', [
            'title' => 'Utilisateurs',
            'users' => $users,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function newUserForm(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/new_user.html.twig', [
            'title' => 'CrÃ©er un parieur',
            'error' => $_SESSION['flash_error'] ?? null,
            'ok' => $_SESSION['flash_ok'] ?? null,
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_ok']);
        $response->getBody()->write($html);
        return $response;
    }

    public function createUser(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $pseudo = trim($data['pseudo'] ?? '');
        $mail = trim($data['mail'] ?? '');

        // CSRF
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e. Veuillez rÃ©essayer.';
            return $response->withHeader('Location', '/admin/users/new')->withStatus(302);
        }

        if ($pseudo === '' || $mail === '') {
            $_SESSION['flash_error'] = 'Tous les champs sont requis';
            return $response->withHeader('Location', '/admin/users/new')->withStatus(302);
        }

        $parieurType = $this->types->findByLibelle('parieur');
        $idType = (int)($parieurType['idTypeUtilisateur'] ?? 1);

        try {
            $userId = $this->users->createWithoutPassword($pseudo, $mail, $idType);
            $token = $this->userTokens->create($userId, 'activation', 48);
            $this->sendActivationEmail($request, $mail, $pseudo, $token);
            $_SESSION['flash_ok'] = 'Utilisateur crÃ©Ã©. Un email d\'activation a Ã©tÃ© envoyÃ©.';
        } catch (\PDOException $e) {
            $_SESSION['flash_error'] = 'Erreur: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/admin/users/new')->withStatus(302);
    }

    public function resendInvite(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e. Veuillez rÃ©essayer.';
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) { $_SESSION['flash_error'] = 'Utilisateur invalide'; return $response->withHeader('Location', '/admin/users')->withStatus(302); }
        $user = $this->users->findById($id);
        if (!$user) { $_SESSION['flash_error'] = 'Utilisateur introuvable'; return $response->withHeader('Location', '/admin/users')->withStatus(302); }
        if (!empty($user['motDePasseHasch'])) {
            $_SESSION['flash_error'] = 'Utilisateur dÃ©jÃ  activÃ©';
            return $response->withHeader('Location', '/admin/users')->withStatus(302);
        }
        try {
            $token = $this->userTokens->create($id, 'activation', 48);
            $this->sendActivationEmail($request, $user['mail'], $user['pseudo'], $token);
            $_SESSION['flash_ok'] = 'Invitation renvoyÃ©e';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Ã‰chec de l\'envoi de l\'invitation';
        }
        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    private function sendActivationEmail(Request $request, string $toEmail, string $pseudo, string $token): void
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80,443]) ? ':' . $port : '');
        $link = $base . '/account/activate/' . $token;

        $html = '<p>Bonjour ' . htmlspecialchars($pseudo) . ',</p>' .
            '<p>Votre compte a Ã©tÃ© crÃ©Ã©. Cliquez sur le lien suivant pour dÃ©finir votre mot de passe:</p>' .
            '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>' .
            '<p>Ce lien expire dans 48h.</p>';
        $text = "Bonjour $pseudo,\nActivez votre compte: $link\n(Lien valable 48h).";
        if (!Mailer::send($toEmail, $pseudo, 'Activation de votre compte', $html, $text)) {
            error_log('[AdminController] Echec envoi email activation Ã  ' . $toEmail);
        }
    }

    // Campagnes
    public function listCampagnes(Request $request, Response $response): Response
    {
        $campagnes = $this->campagnes->findAll();
        $html = $this->twig->render('admin/campagnes.html.twig', [
            'title' => 'Campagnes',
            'campagnes' => $campagnes,
            'inscCounts' => $this->computeInscriptionCounts($campagnes),
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function createCampagne(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $lib = trim($data['libelle'] ?? '');
        $desc = trim($data['description'] ?? '');
        if ($lib === '') { $_SESSION['flash_error'] = 'LibellÃ© requis'; }
        else {
            $this->campagnes->create($lib, $desc ?: null);
            $_SESSION['flash_ok'] = 'Campagne crÃ©Ã©e';
        }
        return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
    }

    public function setCampagneGain(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $count = $this->inscriptions->countByCampagne($idCampagne);
        if ($count > 0) {
            $_SESSION['flash_error'] = 'Des utilisateurs sont inscrits: gain non modifiable.';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $gainText = trim((string)($data['gain'] ?? ''));
        if ($gainText === '') { $gainText = null; }
        if ($gainText !== null && mb_strlen($gainText) > 2000) {
            $_SESSION['flash_error'] = 'Le gain ne doit pas dÃ©passer 2000 caractÃ¨res';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $this->campagnes->setGain($idCampagne, $gainText);
        $_SESSION['flash_ok'] = 'Gain mis Ã  jour';
        return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
    }

    private function computeInscriptionCounts(array $campagnes): array
    {
        $counts = [];
        foreach ($campagnes as $c) {
            $counts[(int)$c['idCampagnePari']] = $this->inscriptions->countByCampagne((int)$c['idCampagnePari']);
        }
        return $counts;
    }

    // Types de phase
    public function listTypes(Request $request, Response $response): Response
    {
        $types = $this->typePhase->findAll();
        $usage = [];
        foreach ($types as $t) {
            $usage[(int)$t['idTypePhase']] = $this->typePhase->countUsage((int)$t['idTypePhase']);
        }
        $html = $this->twig->render('admin/types_phase.html.twig', [
            'title' => 'Types de phase',
            'types' => $types,
            'usage' => $usage,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function createType(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', '/admin/types')->withStatus(302);
        }
        $lib = trim($data['libelle'] ?? '');
        $nb = (int)($data['nbValeurParPari'] ?? 1);
        $labels = array_filter(array_map('trim', explode(',', (string)($data['labels'] ?? ''))));
        if ($lib === '' || $nb <= 0) { $_SESSION['flash_error'] = 'LibellÃ© et nb requis'; }
        else {
            $this->typePhase->create($lib, $nb, $labels);
            $_SESSION['flash_ok'] = 'Type de phase crÃ©Ã©';
        }
        return $response->withHeader('Location', '/admin/types')->withStatus(302);
    }

    public function deleteType(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', '/admin/types')->withStatus(302);
        }
        $id = (int)($args['idType'] ?? 0);
        if ($id <= 0) { $_SESSION['flash_error'] = 'ID invalide'; return $response->withHeader('Location', '/admin/types')->withStatus(302); }
        $count = $this->typePhase->countUsage($id);
        if ($count > 0) {
            $_SESSION['flash_error'] = 'Impossible de supprimer: type utilisÃ© par au moins une phase';
        } else {
            $this->typePhase->delete($id);
            $_SESSION['flash_ok'] = 'Type supprimÃ©';
        }
        return $response->withHeader('Location', '/admin/types')->withStatus(302);
    }

    // Phases d'une campagne
    public function listPhases(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)$args['idCampagne'];
        $phases = $this->phases->findByCampagne($idCampagne);
        $types = $this->typePhase->findAll();
        $campagne = $this->campagnes->findById($idCampagne);
        $apCounts = [];
        foreach ($phases as $p) { $apCounts[(int)$p['idPhaseCampagne']] = $this->aParier->countByPhase((int)$p['idPhaseCampagne']); }
        $html = $this->twig->render('admin/phases.html.twig', [
            'title' => 'Phases de campagne',
            'campagne' => $campagne,
            'phases' => $phases,
            'types' => $types,
            'apCounts' => $apCounts,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function createPhase(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $idCampagne = (int)$args['idCampagne'];
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
        }
        $idType = (int)($data['idTypePhase'] ?? 0);
        $ordre = (int)($data['ordre'] ?? 1);
        $lib = trim($data['libelle'] ?? '');
        $limRaw = trim($data['dateheureLimite'] ?? '');
        // Normaliser datetime-local (YYYY-MM-DDTHH:MM[:SS]) vers DATETIME MySQL (YYYY-MM-DD HH:MM:SS)
        $lim = str_replace('T', ' ', $limRaw);
        if ($lim !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $lim)) {
            $lim .= ':00';
        }
        if ($idType <= 0 || $lib === '' || $lim === '') { $_SESSION['flash_error'] = 'Champs requis'; }
        else {
            $this->phases->create($idCampagne, $idType, $ordre, $lib, $lim);
            $_SESSION['flash_ok'] = 'Phase crÃ©Ã©e';
        }
        return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
    }

    // Calcul des points pour une phase
    public function listCalculs(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $phase = $this->phases->findById($idPhase);
        if (!$phase) { $_SESSION['flash_error'] = 'Phase introuvable'; return $response->withHeader('Location', '/admin/campagnes')->withStatus(302); }
        $campagne = $this->campagnes->findById((int)$phase['idCampagnePari']);
        $current = $this->phaseCalc->listByPhase($idPhase);
        $allTypes = $this->typeResultat->findAll();
        $used = array_column($current, 'idTypeResultat');
        $available = array_filter($allTypes, fn($t) => !in_array((int)$t['idTypeResultat'], $used, true));

        $html = $this->twig->render('admin/calculs_points.html.twig', [
            'title' => 'Calcul des points',
            'phase' => $phase,
            'campagne' => $campagne,
            'current' => $current,
            'available' => array_values($available),
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function addCalcul(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/phases/$idPhase/calculs")->withStatus(302);
        }
        $idType = (int)($data['idTypeResultat'] ?? 0);
        $nb = (int)($data['nbPoint'] ?? 0);
        if ($idType <= 0 || $nb <= 0) {
            $_SESSION['flash_error'] = 'Type et nb de points requis';
        } else {
            $this->phaseCalc->upsert($idPhase, $idType, $nb);
            $_SESSION['flash_ok'] = 'Calcul ajoutÃ©/mis Ã  jour';
        }
        return $response->withHeader('Location', "/admin/phases/$idPhase/calculs")->withStatus(302);
    }

    public function deleteCalcul(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $idPc = (int)$args['idPc'];
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/phases/$idPhase/calculs")->withStatus(302);
        }
        $this->phaseCalc->delete($idPc);
        $_SESSION['flash_ok'] = 'Calcul supprimÃ©';
        return $response->withHeader('Location', "/admin/phases/$idPhase/calculs")->withStatus(302);
    }

    public function deletePhase(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $idPhase = (int)($args['idPhase'] ?? 0);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            // We need campagne ID to redirect; try to fetch phase
            $ph = $this->phases->findById($idPhase);
            $idCampagne = (int)($ph['idCampagnePari'] ?? 0);
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
        }
        $ph = $this->phases->findById($idPhase);
        if (!$ph) { $_SESSION['flash_error'] = 'Phase introuvable'; return $response->withHeader('Location', '/admin/campagnes')->withStatus(302); }
        $idCampagne = (int)$ph['idCampagnePari'];
        $count = $this->aParier->countByPhase($idPhase);
        if ($count > 0) {
            $_SESSION['flash_error'] = 'Impossible de supprimer: des Ã©lÃ©ments Ã  parier existent';
        } else {
            $this->phases->delete($idPhase);
            $_SESSION['flash_ok'] = 'Phase supprimÃ©e';
        }
        return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
    }

    // Ã‰lÃ©ments Ã  parier
    public function listAParier(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $phase = $this->phases->findById($idPhase);
        $campagne = $phase ? $this->campagnes->findById((int)$phase['idCampagnePari']) : null;
        $type = $phase ? $this->typePhase->findById((int)$phase['idTypePhase']) : null;
        $items = $this->aParier->findByPhase($idPhase);
        // PrÃ©parer labels et rÃ©sultats existants
        $labels = [];
        $nb = 1;
        if ($type) {
            $nb = max(1, (int)($type['nbValeurParPari'] ?? 1));
            foreach ($this->typePhase->labels((int)$phase['idTypePhase']) as $row) {
                $labels[(int)$row['numeroValeur']] = $row['libelle'];
            }
        }
        $results = [];
        foreach ($items as $it) {
            $idA = (int)$it['idAParier'];
            $results[$idA] = [];
            foreach ($this->reponses->findByAParier($idA) as $r) {
                $results[$idA][(int)$r['numeroValeur']] = $r['valeurResultat'];
            }
        }
        $html = $this->twig->render('admin/a_parier.html.twig', [
            'title' => 'Ã‰lÃ©ments Ã  parier',
            'phase' => $phase,
            'campagne' => $campagne,
            'type' => $type,
            'nb' => $nb,
            'labels' => $labels,
            'results' => $results,
            'items' => $items,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function createAParier(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/phases/$idPhase/a-parier")->withStatus(302);
        }
        $lib = trim((string)($data['libellePari'] ?? ''));
        $batch = (string)($data['batch'] ?? '');

        $count = 0;
        if ($batch !== '') {
            $lines = preg_split("/(\r\n|\n|\r)/", $batch) ?: [];
            foreach ($lines as $line) {
                $label = trim((string)$line);
                if ($label === '') { continue; }
                try {
                    $this->aParier->create($idPhase, $label);
                    $count++;
                } catch (\PDOException $e) {
                    // Ignorer les doublons ou erreurs ponctuelles pour continuer le lot
                }
            }
        }

        if ($lib !== '') {
            try {
                $this->aParier->create($idPhase, $lib);
                $count++;
            } catch (\PDOException $e) {
                $_SESSION['flash_error'] = 'Erreur lors de l\'ajout: ' . $e->getMessage();
            }
        }

        if ($count > 0) {
            $_SESSION['flash_ok'] = $count . ' Ã©lÃ©ment(s) ajoutÃ©(s)';
        } elseif (empty($_SESSION['flash_error'])) {
            $_SESSION['flash_error'] = 'Veuillez saisir un libellÃ© ou des lignes.';
        }
        return $response->withHeader('Location', "/admin/phases/$idPhase/a-parier")->withStatus(302);
    }

    // RÃ©sultats
    public function setResultats(Request $request, Response $response, array $args): Response
    {
        $idAParier = (int)$args['idAParier'];
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/a-parier/$idAParier/resultats")->withStatus(302);
        }
        foreach ($data as $key => $value) {
            if (preg_match('/^valeur_(\d+)$/', (string)$key, $m)) {
                $num = (int)$m[1];
                $this->reponses->setResult($idAParier, $num, (string)$value);
            }
        }
        $_SESSION['flash_ok'] = 'RÃ©sultats enregistrÃ©s';
        return $response->withHeader('Location', "/admin/a-parier/$idAParier/resultats")->withStatus(302);
    }

    public function setResultatsBatch(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirÃ©e';
            return $response->withHeader('Location', "/admin/phases/$idPhase/a-parier")->withStatus(302);
        }
        $res = $data['res'] ?? [];
        if (is_array($res)) {
            foreach ($res as $idAParier => $vals) {
                $idAParier = (int)$idAParier;
                if (!is_array($vals)) continue;
                foreach ($vals as $num => $val) {
                    $num = (int)$num;
                    $val = trim((string)$val);
                    if ($val === '') continue; // ne pas enregistrer vides
                    $this->reponses->setResult($idAParier, $num, $val);
                }
            }
        }
        $_SESSION['flash_ok'] = 'RÃ©sultats enregistrÃ©s';
        return $response->withHeader('Location', "/admin/phases/$idPhase/a-parier")->withStatus(302);
    }
}





