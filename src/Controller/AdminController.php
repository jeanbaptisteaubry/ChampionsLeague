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
use App\Service\PhaseMailer;
use App\Service\ApiFootballClient;
use App\Service\TheSportsDbClient;
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
        private UtilisateurTokenModele $userTokens = new UtilisateurTokenModele(),
        private PariModele $paris = new PariModele()
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
            'betCounts' => $this->computeBetCounts($campagnes),
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

    public function deleteCampagne(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $campagne = $idCampagne > 0 ? $this->campagnes->findById($idCampagne) : null;
        if (!$campagne) {
            $_SESSION['flash_error'] = 'Campagne introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $betCount = $this->paris->countByCampagne($idCampagne);
        if ($betCount > 0) {
            $_SESSION['flash_error'] = "Impossible de supprimer cette campagne : $betCount pari(s) enregistre(s).";
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $this->campagnes->delete($idCampagne);
        $_SESSION['flash_ok'] = 'Campagne supprimee';
        return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
    }

    public function campagneInvites(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $campagne = $idCampagne > 0 ? $this->campagnes->findById($idCampagne) : null;
        if (!$campagne) {
            $_SESSION['flash_error'] = 'Campagne introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $html = $this->twig->render('admin/campagne_invites.html.twig', [
            'title' => 'Inviter des parieurs',
            'campagne' => $campagne,
            'participants' => $this->inscriptions->listUsersByCampagne($idCampagne),
            'available' => $this->inscriptions->listParieursNotInCampagne($idCampagne),
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function inviteParieursToCampagne(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/invitations")->withStatus(302);
        }

        $campagne = $idCampagne > 0 ? $this->campagnes->findById($idCampagne) : null;
        if (!$campagne) {
            $_SESSION['flash_error'] = 'Campagne introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $ids = $data['parieurs'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            $_SESSION['flash_error'] = 'Selectionnez au moins un parieur';
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/invitations")->withStatus(302);
        }

        $allowed = [];
        foreach ($this->inscriptions->listParieursNotInCampagne($idCampagne) as $u) {
            $allowed[(int)$u['idUtilisateur']] = $u;
        }

        $invited = 0;
        $sent = 0;
        $failed = 0;
        foreach ($ids as $rawId) {
            $idUser = (int)$rawId;
            if (!isset($allowed[$idUser])) { continue; }
            $user = $allowed[$idUser];
            $this->inscriptions->inscrire($idUser, $idCampagne);
            $invited++;

            $accessToken = $this->userTokens->create($idUser, 'campagne_' . $idCampagne, 24 * 365 * 100);
            if ($this->sendCampaignInviteEmail($request, $user, $campagne, $accessToken)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($invited === 0) {
            $_SESSION['flash_error'] = 'Aucun nouveau parieur invite';
        } else {
            $_SESSION['flash_ok'] = $invited . ' parieur(s) invite(s), ' . $sent . ' email(s) envoye(s)';
            if ($failed > 0) { $_SESSION['flash_ok'] .= ' (' . $failed . ' echec(s))'; }
        }

        return $response->withHeader('Location', "/admin/campagnes/$idCampagne/invitations")->withStatus(302);
    }

    private function sendCampaignInviteEmail(Request $request, array $user, array $campagne, string $accessToken): bool
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80,443]) ? ':' . $port : '');
        $campaignLink = $base . '/invitation/campagne/' . $accessToken;

        $name = (string)($user['pseudo'] ?? '');
        $email = (string)($user['mail'] ?? '');
        if ($email === '') { return false; }

        $campaignLabel = (string)($campagne['libelle'] ?? '');
        $subject = 'Invitation campagne: ' . $campaignLabel;
        $html = '<p>Bonjour ' . htmlspecialchars($name) . ',</p>'
              . '<p>Vous avez ete invite a participer a la campagne "' . htmlspecialchars($campaignLabel) . '".</p>'
              . '<p>Acceder a la campagne sans connexion: <a href="' . htmlspecialchars($campaignLink) . '">' . htmlspecialchars($campaignLink) . '</a></p>'
              . '<p>Ce lien est personnel.</p>';
        $text = "Bonjour $name,\n"
              . "Vous avez ete invite a participer a la campagne \"$campaignLabel\".\n"
              . "Acceder a la campagne sans connexion: $campaignLink\n"
              . "Ce lien est personnel.\n";

        return Mailer::send($email, $name !== '' ? $name : $email, $subject, $html, $text);
    }

    private function computeInscriptionCounts(array $campagnes): array
    {
        $counts = [];
        foreach ($campagnes as $c) {
            $counts[(int)$c['idCampagnePari']] = $this->inscriptions->countByCampagne((int)$c['idCampagnePari']);
        }
        return $counts;
    }

    private function computeBetCounts(array $campagnes): array
    {
        $counts = [];
        foreach ($campagnes as $c) {
            $counts[(int)$c['idCampagnePari']] = $this->paris->countByCampagne((int)$c['idCampagnePari']);
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
        $phaseBetCounts = [];
        $betStatuses = [];
        $pointStatuses = [];
        foreach ($phases as $p) {
            $idPhase = (int)$p['idPhaseCampagne'];
            $itemCount = $this->aParier->countByPhase($idPhase);
            $expectedValues = $itemCount * (int)$p['nbValeurParPari'];
            $participants = $this->paris->listStatusByPhase($idCampagne, $idPhase, $expectedValues);
            $counts = [
                'locked' => 0,
                'complete' => 0,
                'in_progress' => 0,
                'not_started' => 0,
            ];
            foreach ($participants as $participant) {
                $counts[$participant['status']]++;
            }

            $apCounts[$idPhase] = $itemCount;
            $phaseBetCounts[$idPhase] = $this->paris->countByPhase($idPhase);
            $betStatuses[$idPhase] = [
                'participants' => $participants,
                'counts' => $counts,
                'expectedValues' => $expectedValues,
            ];

            $summary = PhaseMailer::computeSummary($idPhase);
            $ranking = [];
            foreach (($summary['participants'] ?? []) as $participant) {
                $idUser = (int)$participant['idUtilisateur'];
                $ranking[] = [
                    'idUtilisateur' => $idUser,
                    'pseudo' => $participant['pseudo'],
                    'total' => (int)($summary['totals'][$idUser] ?? 0),
                ];
            }
            usort($ranking, static function (array $left, array $right): int {
                $byPoints = $right['total'] <=> $left['total'];
                return $byPoints !== 0
                    ? $byPoints
                    : strcasecmp((string)$left['pseudo'], (string)$right['pseudo']);
            });

            $officialCount = 0;
            foreach (($summary['official'] ?? []) as $values) {
                if (isset($values[1], $values[2])
                    && trim((string)$values[1]) !== ''
                    && trim((string)$values[2]) !== '') {
                    $officialCount++;
                }
            }
            $pointStatuses[$idPhase] = [
                'ranking' => $ranking,
                'items' => $summary['items'] ?? [],
                'participants' => $summary['participants'] ?? [],
                'cells' => $summary['cells'] ?? [],
                'htmlCells' => $summary['htmlCells'] ?? [],
                'official' => $summary['official'] ?? [],
                'officialHtml' => $summary['officialHtml'] ?? [],
                'points' => $summary['points'] ?? [],
                'officialCount' => $officialCount,
                'itemCount' => count($summary['items'] ?? []),
                'calculationCount' => count($this->phaseCalc->listByPhase($idPhase)),
            ];
        }
        $html = $this->twig->render('admin/phases.html.twig', [
            'title' => 'Phases de campagne',
            'campagne' => $campagne,
            'phases' => $phases,
            'types' => $types,
            'apCounts' => $apCounts,
            'phaseBetCounts' => $phaseBetCounts,
            'betStatuses' => $betStatuses,
            'pointStatuses' => $pointStatuses,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function startBetAssistance(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $idUser = (int)($args['idUser'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);
        $returnUrl = $idCampagne > 0
            ? "/admin/campagnes/$idCampagne/phases"
            : '/admin/campagnes';
        $data = (array)($request->getParsedBody() ?? []);

        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }
        if (!$phase || $idUser <= 0) {
            $_SESSION['flash_error'] = 'Phase ou participant invalide';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }
        if (!$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Ce participant n est pas inscrit a la campagne';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        $user = $this->users->findById($idUser);
        $type = $user ? $this->types->findById((int)$user['idTypeUtilisateur']) : null;
        if (!$user || ($type['libelle'] ?? null) !== 'parieur') {
            $_SESSION['flash_error'] = 'Compte parieur introuvable';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        $_SESSION['impersonator'] = [
            'user' => $_SESSION['user'],
            'returnUrl' => $returnUrl,
            'phaseId' => $idPhase,
        ];
        $_SESSION['user'] = [
            'id' => (int)$user['idUtilisateur'],
            'pseudo' => $user['pseudo'],
            'mail' => $user['mail'],
            'idTypeUtilisateur' => (int)$user['idTypeUtilisateur'],
            'typeLibelle' => 'parieur',
        ];
        session_regenerate_id(true);
        $_SESSION['flash_ok'] = 'Mode assistance actif pour ' . $user['pseudo'];

        return $response
            ->withHeader('Location', "/parieur/phases/$idPhase/parier")
            ->withStatus(302);
    }

    public function unlockParticipantBets(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $idUser = (int)($args['idUser'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);
        $returnUrl = $idCampagne > 0
            ? "/admin/campagnes/$idCampagne/phases"
            : '/admin/campagnes';
        $data = (array)($request->getParsedBody() ?? []);

        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }
        if (!$phase || $idUser <= 0 || !$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Phase ou participant invalide';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        (new PhaseParieurVerrouModele())->unlock($idUser, $idPhase);
        $_SESSION['flash_ok'] = 'Les paris du participant ont ete deverrouilles';

        return $response->withHeader('Location', $returnUrl)->withStatus(302);
    }

    public function lockParticipantBets(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $idUser = (int)($args['idUser'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);
        $returnUrl = $idCampagne > 0
            ? "/admin/campagnes/$idCampagne/phases"
            : '/admin/campagnes';
        $data = (array)($request->getParsedBody() ?? []);

        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }
        if (!$phase || $idUser <= 0 || !$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Phase ou participant invalide';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        [$itemCount, $missingValues] = $this->participantMissingBetValues($idUser, $idPhase, $phase);
        if ($itemCount === 0 || $missingValues > 0) {
            $_SESSION['flash_error'] = $itemCount === 0
                ? 'Aucun element a parier n est configure pour cette phase'
                : "Verrouillage impossible : $missingValues valeur(s) de pari sont manquante(s)";
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        (new PhaseParieurVerrouModele())->lock($idUser, $idPhase);
        $_SESSION['flash_ok'] = 'Les paris du participant ont ete verrouilles et valides';

        return $response->withHeader('Location', $returnUrl)->withStatus(302);
    }

    private function participantMissingBetValues(int $idUser, int $idPhase, array $phase): array
    {
        $items = $this->aParier->findByPhase($idPhase);
        $type = $this->typePhase->findById((int)$phase['idTypePhase']);
        $expectedPerItem = max(1, (int)($type['nbValeurParPari'] ?? 1));
        $betsByItem = [];
        foreach ($this->paris->findForUserAndPhase($idUser, $idPhase) as $bet) {
            $betsByItem[(int)$bet['idAParier']] = $bet['valeurs'] ?? [];
        }

        $missingValues = 0;
        foreach ($items as $item) {
            $values = $betsByItem[(int)$item['idAParier']] ?? [];
            for ($number = 1; $number <= $expectedPerItem; $number++) {
                if (!isset($values[$number]) || trim((string)$values[$number]) === '') {
                    $missingValues++;
                }
            }
        }

        return [count($items), $missingValues];
    }

    public function extendPhaseDeadline(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);
        $returnUrl = $idCampagne > 0
            ? "/admin/campagnes/$idCampagne/phases"
            : '/admin/campagnes';
        $data = (array)($request->getParsedBody() ?? []);

        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        $hours = (int)($data['hours'] ?? 0);
        if (!$phase || $hours < 1 || $hours > 168) {
            $_SESSION['flash_error'] = 'Indiquez une prolongation comprise entre 1 et 168 heures';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        try {
            $currentDeadline = new \DateTimeImmutable((string)$phase['dateheureLimite']);
            $now = new \DateTimeImmutable('now');
            $base = $currentDeadline > $now ? $currentDeadline : $now;
            $newDeadline = $base->modify("+$hours hours");
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Date limite invalide';
            return $response->withHeader('Location', $returnUrl)->withStatus(302);
        }

        $this->phases->updateDeadline($idPhase, $newDeadline->format('Y-m-d H:i:s'));
        $_SESSION['flash_ok'] = "Phase prolongee de $hours heure(s), jusqu au "
            . $newDeadline->format('d/m/Y H:i');

        return $response->withHeader('Location', $returnUrl)->withStatus(302);
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
        $betCount = $this->paris->countByPhase($idPhase);
        if ($betCount > 0) {
            $_SESSION['flash_error'] = "Impossible de supprimer cette phase : $betCount pari(s) enregistre(s).";
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
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

    public function apiFootballResults(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $campagne = $this->campagnes->findById((int)$phase['idCampagnePari']);
        $items = $this->aParier->findByPhase($idPhase);
        [$defaultFrom, $defaultTo, $defaultSeason] = $this->apiFootballDefaults($phase);
        $query = $request->getQueryParams();
        $from = $this->validDate((string)($query['from'] ?? '')) ?? $defaultFrom;
        $to = $this->validDate((string)($query['to'] ?? '')) ?? $defaultTo;
        $league = max(1, (int)($query['league'] ?? 2));
        $season = max(2000, (int)($query['season'] ?? $defaultSeason));

        $fixtures = [];
        $matches = [];
        $apiStatus = [];
        $apiError = null;
        try {
            $client = new ApiFootballClient();
            $statusPayload = $client->status();
            $apiStatus = is_array($statusPayload['response'] ?? null)
                ? $statusPayload['response']
                : [];
            $fixtures = $client->fixtures($from, $to, $league, $season);
            $matches = $client->matchItems($items, $fixtures);
        } catch (\Throwable $e) {
            $apiError = $e->getMessage();
        }

        $html = $this->twig->render('admin/api_football.html.twig', [
            'title' => 'Verification API-Football',
            'phase' => $phase,
            'campagne' => $campagne,
            'items' => $items,
            'fixtures' => $fixtures,
            'matches' => $matches,
            'apiStatus' => $apiStatus,
            'apiError' => $apiError,
            'from' => $from,
            'to' => $to,
            'league' => $league,
            'season' => $season,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function importApiFootballResults(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response
                ->withHeader('Location', "/admin/phases/$idPhase/api-football")
                ->withStatus(302);
        }

        [$defaultFrom, $defaultTo, $defaultSeason] = $this->apiFootballDefaults($phase);
        $from = $this->validDate((string)($data['from'] ?? '')) ?? $defaultFrom;
        $to = $this->validDate((string)($data['to'] ?? '')) ?? $defaultTo;
        $league = max(1, (int)($data['league'] ?? 2));
        $season = max(2000, (int)($data['season'] ?? $defaultSeason));
        $selected = is_array($data['fixtures'] ?? null) ? $data['fixtures'] : [];

        try {
            $client = new ApiFootballClient();
            $fixtures = $client->fixtures($from, $to, $league, $season);
            $fixturesById = [];
            foreach ($fixtures as $fixture) {
                $fixtureId = (int)($fixture['fixture']['id'] ?? 0);
                if ($fixtureId > 0) {
                    $fixturesById[$fixtureId] = $fixture;
                }
            }

            $allowedItems = [];
            foreach ($this->aParier->findByPhase($idPhase) as $item) {
                $allowedItems[(int)$item['idAParier']] = true;
            }

            $imported = 0;
            $skipped = 0;
            foreach ($selected as $rawItemId => $rawFixtureId) {
                $itemId = (int)$rawItemId;
                $fixtureId = (int)$rawFixtureId;
                $fixture = $fixturesById[$fixtureId] ?? null;
                if (!isset($allowedItems[$itemId]) || !$fixture || !ApiFootballClient::isFinished($fixture)) {
                    if ($fixtureId > 0) { $skipped++; }
                    continue;
                }

                $this->reponses->setResult($itemId, 1, (string)$fixture['goals']['home']);
                $this->reponses->setResult($itemId, 2, (string)$fixture['goals']['away']);
                $imported++;
            }

            $_SESSION['flash_ok'] = "$imported resultat(s) importe(s) depuis API-Football";
            if ($skipped > 0) {
                $_SESSION['flash_ok'] .= " ($skipped ignore(s): non termine(s) ou invalide(s))";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        $query = http_build_query([
            'from' => $from,
            'to' => $to,
            'league' => $league,
            'season' => $season,
        ]);
        return $response
            ->withHeader('Location', "/admin/phases/$idPhase/api-football?$query")
            ->withStatus(302);
    }

    private function apiFootballDefaults(array $phase): array
    {
        try {
            $deadline = new \DateTimeImmutable((string)$phase['dateheureLimite']);
        } catch (\Throwable $e) {
            $deadline = new \DateTimeImmutable('now');
        }
        $season = (int)$deadline->format('Y');
        if ((int)$deadline->format('n') < 7) {
            $season--;
        }

        return [
            $deadline->format('Y-m-d'),
            $deadline->modify('+3 days')->format('Y-m-d'),
            $season,
        ];
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public function theSportsDbResults(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $campagne = $this->campagnes->findById((int)$phase['idCampagnePari']);
        $items = $this->aParier->findByPhase($idPhase);
        [$defaultFrom, $defaultTo] = $this->apiFootballDefaults($phase);
        $defaultSeason = substr($defaultFrom, 0, 4);
        $query = $request->getQueryParams();
        $from = $this->validDate((string)($query['from'] ?? '')) ?? $defaultFrom;
        $to = $this->validDate((string)($query['to'] ?? '')) ?? $defaultTo;
        $league = max(1, (int)($query['league'] ?? 4429));
        $season = trim((string)($query['season'] ?? $defaultSeason));

        $events = [];
        $matches = [];
        $apiError = null;
        try {
            $client = new TheSportsDbClient();
            $events = $client->seasonEvents($league, $season, $from, $to);
            $matches = $client->matchItems($items, $events);
        } catch (\Throwable $e) {
            $apiError = $e->getMessage();
        }

        $html = $this->twig->render('admin/thesportsdb.html.twig', [
            'title' => 'Verification TheSportsDB',
            'phase' => $phase,
            'campagne' => $campagne,
            'items' => $items,
            'events' => $events,
            'matches' => $matches,
            'apiError' => $apiError,
            'from' => $from,
            'to' => $to,
            'league' => $league,
            'season' => $season,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function importTheSportsDbResults(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $idPhase > 0 ? $this->phases->findById($idPhase) : null;
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expiree';
            return $response
                ->withHeader('Location', "/admin/phases/$idPhase/thesportsdb")
                ->withStatus(302);
        }

        [$defaultFrom, $defaultTo] = $this->apiFootballDefaults($phase);
        $defaultSeason = substr($defaultFrom, 0, 4);
        $from = $this->validDate((string)($data['from'] ?? '')) ?? $defaultFrom;
        $to = $this->validDate((string)($data['to'] ?? '')) ?? $defaultTo;
        $league = max(1, (int)($data['league'] ?? 4429));
        $season = trim((string)($data['season'] ?? $defaultSeason));
        $selected = is_array($data['events'] ?? null) ? $data['events'] : [];

        try {
            $events = (new TheSportsDbClient())->seasonEvents($league, $season, $from, $to);
            $eventsById = [];
            foreach ($events as $event) {
                $eventId = (int)($event['idEvent'] ?? 0);
                if ($eventId > 0) {
                    $eventsById[$eventId] = $event;
                }
            }

            $allowedItems = [];
            foreach ($this->aParier->findByPhase($idPhase) as $item) {
                $allowedItems[(int)$item['idAParier']] = true;
            }

            $imported = 0;
            $skipped = 0;
            foreach ($selected as $rawItemId => $rawEventId) {
                $itemId = (int)$rawItemId;
                $eventId = (int)$rawEventId;
                $event = $eventsById[$eventId] ?? null;
                if (!isset($allowedItems[$itemId]) || !$event || !TheSportsDbClient::hasScore($event)) {
                    if ($eventId > 0) { $skipped++; }
                    continue;
                }

                $this->reponses->setResult($itemId, 1, (string)$event['intHomeScore']);
                $this->reponses->setResult($itemId, 2, (string)$event['intAwayScore']);
                $imported++;
            }

            $_SESSION['flash_ok'] = "$imported resultat(s) importe(s) depuis TheSportsDB";
            if ($skipped > 0) {
                $_SESSION['flash_ok'] .= " ($skipped ignore(s): score absent ou selection invalide)";
            }
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        $query = http_build_query([
            'from' => $from,
            'to' => $to,
            'league' => $league,
            'season' => $season,
        ]);
        return $response
            ->withHeader('Location', "/admin/phases/$idPhase/thesportsdb?$query")
            ->withStatus(302);
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





