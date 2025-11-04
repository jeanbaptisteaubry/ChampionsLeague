<?php
declare(strict_types=1);

namespace App\Controller;

use App\Modele\CampagnePariModele;
use App\Modele\InscriptionPariModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\PhaseParieurVerrouModele;
use App\Service\Mailer;
use App\Service\PhaseMailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

final class AdminReminderController
{
    public function __construct(
        private Environment $twig,
        private CampagnePariModele $campagnes = new CampagnePariModele(),
        private PhaseCampagneModele $phases = new PhaseCampagneModele(),
        private InscriptionPariModele $inscriptions = new InscriptionPariModele(),
    ) {}

    public function sendReminderPhase(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $phase = $this->phases->findById($idPhase);
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);
        $campagne = $idCampagne > 0 ? $this->campagnes->findById($idCampagne) : null;

        $destinataires = $this->inscriptions->listUsersByCampagne($idCampagne);
        if (empty($destinataires)) {
            $_SESSION['flash_error'] = 'Aucun inscrit à notifier';
            return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
        }

        $locks = new PhaseParieurVerrouModele();
        $sent = 0;
        $failed = 0;

        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80,443]) ? ':' . $port : '');
        $link = $base . '/parieur/phases/' . $idPhase . '/parier';

        $limit = null;
        try {
            $dt = new \DateTimeImmutable((string)$phase['dateheureLimite']);
            $limit = $dt->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            $limit = (string)($phase['dateheureLimite'] ?? '');
        }

        $campLib = (string)($campagne['libelle'] ?? '');
        $phaseLib = (string)($phase['libelle'] ?? '');
        $subject = 'Rappel: ' . ($campLib !== '' ? ($campLib . ' - ') : '') . 'Phase "' . $phaseLib . '"';

        foreach ($destinataires as $u) {
            $uid = (int)($u['idUtilisateur'] ?? 0);
            if ($uid <= 0) { continue; }
            if ($locks->isLocked($uid, $idPhase)) { continue; }

            $name = (string)($u['pseudo'] ?? '');
            $email = (string)($u['mail'] ?? '');
            if ($email === '') { continue; }

            $html = '<p>Bonjour ' . htmlspecialchars($name) . ',</p>'
                  . '<p>Petit rappel: la phase "' . htmlspecialchars($phaseLib) . '"'
                  . ($campLib !== '' ? ' de la campagne "' . htmlspecialchars($campLib) . '"' : '')
                  . ' approche de sa date limite' . ($limit ? ' (' . htmlspecialchars($limit) . ')' : '') . '.</p>'
                  . '<p>Déposez ou mettez à jour vos paris ici: '
                  . '<a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>'
                  . '<p>Merci et bons pronostics !</p>';
            $text = 'Bonjour ' . $name . ",\n"
                  . 'Rappel: la phase "' . $phaseLib . '"' . ($campLib !== '' ? ' de la campagne "' . $campLib . '"' : '')
                  . ' approche de sa date limite' . ($limit ? ' (' . $limit . ')' : '') . ".\n"
                  . 'Déposez ou mettez à jour vos paris: ' . $link . "\n"
                  . 'Merci et bons pronostics !';

            if (Mailer::send($email, $name !== '' ? $name : $email, $subject, $html, $text)) { $sent++; }
            else { $failed++; }
        }

        if ($sent > 0) {
            $_SESSION['flash_ok'] = 'Email(s) de rappel envoyés à ' . $sent . ' destinataire(s)';
            if ($failed > 0) {
                $_SESSION['flash_ok'] .= ' (' . $failed . ' échec(s))';
            }
        } else {
            $_SESSION['flash_error'] = 'Aucun email envoyé (tout le monde est peut-être déjà verrouillé)';
        }

        return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
    }

    public function sendRecapPhase(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }

        $phase = $this->phases->findById($idPhase);
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase introuvable';
            return $response->withHeader('Location', '/admin/campagnes')->withStatus(302);
        }
        $idCampagne = (int)($phase['idCampagnePari'] ?? 0);

        [$sent, $failed] = PhaseMailer::sendPhaseSummaryToAll($request, $this->twig, $idPhase);
        if ($sent > 0) {
            $_SESSION['flash_ok'] = 'Récapitulatif envoyé à ' . $sent . ' destinataire(s)';
            if ($failed > 0) { $_SESSION['flash_ok'] .= ' (' . $failed . ' échec(s))'; }
        } else {
            $_SESSION['flash_error'] = 'Aucun email envoyé (aucun inscrit ?)';
        }

        return $response->withHeader('Location', "/admin/campagnes/$idCampagne/phases")->withStatus(302);
    }
}
