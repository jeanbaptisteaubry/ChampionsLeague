<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;
use App\Modele\CampagnePariModele;
use App\Modele\InscriptionPariModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\AParierModele;
use App\Modele\PariModele;
use App\Modele\ReponsePariModele;
use App\Modele\PhaseCalculPointModele;

final class ParieurController
{
    public function __construct(
        private Environment $twig,
        private \App\Modele\UtilisateurModele $users,
        private CampagnePariModele $campagnes = new CampagnePariModele(),
        private InscriptionPariModele $inscriptions = new InscriptionPariModele(),
        private PhaseCampagneModele $phases = new PhaseCampagneModele(),
        private AParierModele $aParier = new AParierModele(),
        private PariModele $paris = new PariModele(),
        private ReponsePariModele $reponses = new ReponsePariModele(),
        private PhaseCalculPointModele $phaseCalc = new PhaseCalculPointModele()
    ) {}

    public function showPassword(Request $request, Response $response): Response
    {
        $html = $this->twig->render('parieur/password.html.twig', [
            'title' => 'Changer le mot de passe',
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function updatePassword(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $p1 = (string)($data['password'] ?? '');
        $p2 = (string)($data['password_confirm'] ?? '');

        // CSRF
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Veuillez réessayer.';
            return $response->withHeader('Location', '/parieur/password')->withStatus(302);
        }

        if ($p1 === '' || $p2 === '') {
            $_SESSION['flash_error'] = 'Veuillez remplir les deux champs';
            return $response->withHeader('Location', '/parieur/password')->withStatus(302);
        }

        if ($p1 !== $p2) {
            $_SESSION['flash_error'] = 'Les mots de passe ne correspondent pas';
            return $response->withHeader('Location', '/parieur/password')->withStatus(302);
        }

        $id = (int)($_SESSION['user']['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Non authentifié';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $this->users->updatePassword($id, $p1);
        $_SESSION['flash_ok'] = 'Mot de passe mis à jour';
        return $response->withHeader('Location', '/parieur/password')->withStatus(302);
    }
    public function inscription(Request $request, Response $response): Response
    {
        return $this->campagnesPage($request, $response);
    }

    public function inscrire(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        $idCampagne = (int)($data['idCampagnePari'] ?? 0);
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if ($idCampagne <= 0 || $idUser <= 0) {
            $_SESSION['flash_error'] = 'Champs invalides';
        } else {
            $this->inscriptions->inscrire($idUser, $idCampagne);
            $_SESSION['flash_ok'] = 'Inscription enregistrée';
        }
        return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
    }

    public function desinscrire(Request $request, Response $response, array $args): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if ($idCampagne <= 0 || $idUser <= 0) {
            $_SESSION['flash_error'] = 'Champs invalides';
        } else {
            // Bloquer la désinscription si des paris existent sur cette campagne
            $countBets = $this->paris->countByUserAndCampagne($idUser, $idCampagne);
            if ($countBets > 0) {
                $_SESSION['flash_error'] = 'Impossible de se désinscrire: des paris sont enregistrés sur cette campagne';
                return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
            }
            $this->inscriptions->desinscrire($idUser, $idCampagne);
            $_SESSION['flash_ok'] = 'Désinscription effectuée';
        }
        return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
    }

    public function campagnesPage(Request $request, Response $response): Response
    {
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        $enrolled = $this->inscriptions->listByUser($idUser);
        $available = $this->inscriptions->listNotEnrolled($idUser);
        $html = $this->twig->render('parieur/campagnes.html.twig', [
            'title' => 'Mes campagnes',
            'enrolled' => $enrolled,
            'available' => $available,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function campagneDetail(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if ($idCampagne <= 0 || $idUser <= 0) {
            $_SESSION['flash_error'] = 'Campagne invalide';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        if (!$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Vous devez être inscrit à cette campagne';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        $campagne = $this->campagnes->findById($idCampagne);
        $phases = $this->phases->findByCampagne($idCampagne);
        $participants = $this->inscriptions->listUsersByCampagne($idCampagne);
        $html = $this->twig->render('parieur/campagne.html.twig', [
            'title' => 'Campagne — ' . ($campagne['libelle'] ?? ''),
            'campagne' => $campagne,
            'phases' => $phases,
            'participants' => $participants,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function syntheseCampagne(Request $request, Response $response, array $args): Response
    {
        $idCampagne = (int)($args['idCampagne'] ?? 0);
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if ($idCampagne <= 0 || $idUser <= 0) {
            $_SESSION['flash_error'] = 'Campagne invalide';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        if (!$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Vous devez être inscrit à cette campagne';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }

        $campagne = $this->campagnes->findById($idCampagne);
        $phases = $this->phases->findByCampagne($idCampagne);
        $participants = $this->inscriptions->listUsersByCampagne($idCampagne);

        // Calcul des points par phase et par participant
        $matrix = []; // [idPhase][idUser] => points
        $totalsByUser = []; // total across phases

        foreach ($phases as $p) {
            $idPhase = (int)$p['idPhaseCampagne'];
            $items = $this->aParier->findByPhase($idPhase);
            // Résultats officiels et config calcul
            $official = [];
            foreach ($items as $it) {
                $rid = (int)$it['idAParier'];
                $res = $this->reponses->findByAParier($rid);
                $rvals = [];
                foreach ($res as $r) { $rvals[(int)$r['numeroValeur']] = $r['valeurResultat']; }
                $official[$rid] = $rvals;
            }
            $calc = $this->phaseCalc->listByPhase($idPhase);

            // Nb valeurs pour ce type
            $type = (new \App\Modele\TypePhaseModele())->findById((int)$p['idTypePhase']);
            $nb = max(1, (int)($type['nbValeurParPari'] ?? 1));

            foreach ($participants as $u) {
                $uid = (int)$u['idUtilisateur'];
                $earnedPhase = 0;
                $bets = $this->paris->findForUserAndPhase($uid, $idPhase);
                $byItem = [];
                foreach ($bets as $b) { $byItem[(int)$b['idAParier']] = $b['valeurs'] ?? []; }
                foreach ($items as $it) {
                    $idA = (int)$it['idAParier'];
                    $vals = $byItem[$idA] ?? [];
                    $rvals = $official[$idA] ?? [];
                    $b1 = isset($vals[1]) ? (int)$vals[1] : null;
                    $b2 = isset($vals[2]) ? (int)$vals[2] : null;
                    $r1 = isset($rvals[1]) ? (int)$rvals[1] : null;
                    $r2 = isset($rvals[2]) ? (int)$rvals[2] : null;
                    foreach ($calc as $c) {
                        $lib = (string)$c['libelle']; $nbp=(int)$c['nbPoint'];
                        if ($lib==='1N2') { if ($b1!==null && $b2!==null && $r1!==null && $r2!==null) { if (($b1<=>$b2)===($r1<=>$r2)) $earnedPhase += $nbp; } }
                        elseif ($lib==='scoreExact') { if ($b1!==null && $b2!==null && $r1!==null && $r2!==null) { if ($b1===$r1 && $b2===$r2) $earnedPhase += $nbp; } }
                    }
                }
                $matrix[$idPhase][$uid] = $earnedPhase;
                $totalsByUser[$uid] = ($totalsByUser[$uid] ?? 0) + $earnedPhase;
            }
        }

        $html = $this->twig->render('parieur/synthese_campagne.html.twig', [
            'title' => 'Synthèse — ' . ($campagne['libelle'] ?? ''),
            'campagne' => $campagne,
            'phases' => $phases,
            'participants' => $participants,
            'matrix' => $matrix,
            'totals' => $totalsByUser,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function parier(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $items = $this->aParier->findByPhase($idPhase);
        $phase = $this->phases->findById($idPhase);
        $type = $phase ? (new \App\Modele\TypePhaseModele())->findById((int)$phase['idTypePhase']) : null;
        $labels = [];
        $nb = 1;
        if ($type) {
            $nb = max(1, (int)($type['nbValeurParPari'] ?? 1));
            foreach ((new \App\Modele\TypePhaseModele())->labels((int)$phase['idTypePhase']) as $row) {
                $labels[(int)$row['numeroValeur']] = $row['libelle'];
            }
        }
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        $existing = [];
        foreach ($this->paris->findForUserAndPhase($idUser, $idPhase) as $p) {
            $existing[(int)$p['idAParier']] = $p['valeurs'] ?? [];
        }
        $closed = false;
        if (!empty($phase['dateheureLimite'])) {
            try { $closed = (new \DateTimeImmutable($phase['dateheureLimite'])) < (new \DateTimeImmutable('now')); } catch (\Throwable $e) { $closed = false; }
        }
        $html = $this->twig->render('parieur/parier.html.twig', [
            'title' => 'Placer des paris',
            'idPhase' => $idPhase,
            'idCampagne' => (int)($phase['idCampagnePari'] ?? 0),
            'items' => $items,
            'nb' => $nb,
            'labels' => $labels,
            'existing' => $existing,
            'closed' => $closed,
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function placer(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)$args['idPhase'];
        $phase = $this->phases->findById($idPhase);
        if ($phase && !empty($phase['dateheureLimite'])) {
            try {
                if ((new \DateTimeImmutable($phase['dateheureLimite'])) < (new \DateTimeImmutable('now'))) {
                    $_SESSION['flash_error'] = 'Phase clôturée: les paris ne sont plus acceptés';
                    return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
                }
            } catch (\Throwable $e) {}
        }
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
        }
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        $valuesByItem = [];
        foreach ($data as $k => $v) {
            if (preg_match('/^aparier_(\d+)_val_(\d+)$/', (string)$k, $m)) {
                $idA = (int)$m[1];
                $num = (int)$m[2];
                $val = trim((string)$v);
                if ($val === '') { continue; }
                $valuesByItem[$idA][$num] = $val;
            }
        }
        foreach ($valuesByItem as $idA => $vals) {
            $this->paris->placerValeurs($idUser, (int)$idA, $vals);
        }
        $_SESSION['flash_ok'] = 'Paris enregistrés';
        return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
    }

    public function resultatsPhase(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $this->phases->findById($idPhase);
        if (!$phase) {
            $_SESSION['flash_error'] = 'Phase inconnue';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        $idCampagne = (int)$phase['idCampagnePari'];
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if (!$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            $_SESSION['flash_error'] = 'Vous devez être inscrit à cette campagne';
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        // Interdire la consultation avant la limite
        $beforeLimit = false;
        if (!empty($phase['dateheureLimite'])) {
            try { $beforeLimit = (new \DateTimeImmutable($phase['dateheureLimite'])) > (new \DateTimeImmutable('now')); } catch (\Throwable $e) { $beforeLimit = false; }
        }
        if ($beforeLimit) {
            $_SESSION['flash_error'] = 'Les résultats seront visibles après la date limite.';
            return $response->withHeader('Location', '/parieur/campagnes/' . $idCampagne)->withStatus(302);
        }
        $campagne = $this->campagnes->findById($idCampagne);
        $items = $this->aParier->findByPhase($idPhase);
        $participants = $this->inscriptions->listUsersByCampagne($idCampagne);

        // Nombre de valeurs pour la phase
        $type = (new \App\Modele\TypePhaseModele())->findById((int)$phase['idTypePhase']);
        $nb = max(1, (int)($type['nbValeurParPari'] ?? 1));

        // Préparer cellule [idAParier][idUtilisateur] => "val1 | val2 | ..." et points
        $cells = [];
        $points = [];
        $totals = [];

        // Résultats officiels par item
        $official = [];
        foreach ($items as $it) {
            $rid = (int)$it['idAParier'];
            $res = $this->reponses->findByAParier($rid);
            $rvals = [];
            foreach ($res as $r) { $rvals[(int)$r['numeroValeur']] = $r['valeurResultat']; }
            $official[$rid] = $rvals;
        }

        // Config points
        $calc = $this->phaseCalc->listByPhase($idPhase); // each: idTypeResultat, libelle, nbPoint
        foreach ($participants as $u) {
            $uid = (int)$u['idUtilisateur'];
            $bets = $this->paris->findForUserAndPhase($uid, $idPhase);
            $byItem = [];
            foreach ($bets as $b) {
                $vals = $b['valeurs'] ?? [];
                $ordered = [];
                for ($i=1; $i<=$nb; $i++) { if (isset($vals[$i])) { $ordered[] = (string)$vals[$i]; } }
                $byItem[(int)$b['idAParier']] = [ 'text' => implode(' | ', $ordered), 'vals' => $vals ];
            }
            foreach ($items as $it) {
                $idA = (int)$it['idAParier'];
                $cells[$idA][$uid] = $byItem[$idA]['text'] ?? '';
                // Calcul des points
                $earned = 0;
                $betVals = $byItem[$idA]['vals'] ?? [];
                $resVals = $official[$idA] ?? [];
                // On essaie de parser en int
                $b1 = isset($betVals[1]) ? (int)$betVals[1] : null;
                $b2 = isset($betVals[2]) ? (int)$betVals[2] : null;
                $r1 = isset($resVals[1]) ? (int)$resVals[1] : null;
                $r2 = isset($resVals[2]) ? (int)$resVals[2] : null;
                foreach ($calc as $c) {
                    $lib = (string)$c['libelle'];
                    $nbp = (int)$c['nbPoint'];
                    if ($lib === '1N2') {
                        if ($b1 !== null && $b2 !== null && $r1 !== null && $r2 !== null) {
                            $ps = $b1 <=> $b2; // -1,0,1
                            $rs = $r1 <=> $r2;
                            if ($ps === $rs) { $earned += $nbp; }
                        }
                    } elseif ($lib === 'scoreExact') {
                        if ($b1 !== null && $b2 !== null && $r1 !== null && $r2 !== null) {
                            if ($b1 === $r1 && $b2 === $r2) { $earned += $nbp; }
                        }
                    }
                }
                $points[$idA][$uid] = $earned;
                $totals[$uid] = ($totals[$uid] ?? 0) + $earned;
            }
        }

        $html = $this->twig->render('parieur/resultats_phase.html.twig', [
            'title' => 'Résultats des paris — ' . ($campagne['libelle'] ?? ''),
            'campagne' => $campagne,
            'phase' => $phase,
            'participants' => $participants,
            'items' => $items,
            'cells' => $cells,
            'official' => $official,
            'points' => $points,
            'totals' => $totals,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function resultatsPhaseCsv(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $this->phases->findById($idPhase);
        if (!$phase) { return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302); }
        $idCampagne = (int)$phase['idCampagnePari'];
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        if (!$this->inscriptions->estInscrit($idUser, $idCampagne)) {
            return $response->withHeader('Location', '/parieur/campagnes')->withStatus(302);
        }
        // Interdire l'export avant la limite
        $beforeLimit = false;
        if (!empty($phase['dateheureLimite'])) {
            try { $beforeLimit = (new \DateTimeImmutable($phase['dateheureLimite'])) > (new \DateTimeImmutable('now')); } catch (\Throwable $e) { $beforeLimit = false; }
        }
        if ($beforeLimit) {
            return $response->withHeader('Location', '/parieur/campagnes/' . $idCampagne)->withStatus(302);
        }
        $campagne = $this->campagnes->findById($idCampagne);
        $items = $this->aParier->findByPhase($idPhase);
        $participants = $this->inscriptions->listUsersByCampagne($idCampagne);
        $type = (new \App\Modele\TypePhaseModele())->findById((int)$phase['idTypePhase']);
        $nb = max(1, (int)($type['nbValeurParPari'] ?? 1));

        // Results & points reuse
        $official = [];
        foreach ($items as $it) {
            $rid = (int)$it['idAParier'];
            $res = $this->reponses->findByAParier($rid);
            $rvals = [];
            foreach ($res as $r) { $rvals[(int)$r['numeroValeur']] = $r['valeurResultat']; }
            $official[$rid] = $rvals;
        }
        $calc = $this->phaseCalc->listByPhase($idPhase);

        // CSV header
        $rows = [];
        $header = ['AParier', 'Resultat1', 'Resultat2'];
        foreach ($participants as $u) { $header[] = $u['pseudo']; }
        $rows[] = $header;

        foreach ($items as $it) {
            $idA = (int)$it['idAParier'];
            $row = [$it['libellePari'], (string)($official[$idA][1] ?? ''), (string)($official[$idA][2] ?? '')];
            foreach ($participants as $u) {
                $uid = (int)$u['idUtilisateur'];
                $bets = $this->paris->findForUserAndPhase($uid, $idPhase);
                $byItem = [];
                foreach ($bets as $b) { $byItem[(int)$b['idAParier']] = $b['valeurs'] ?? []; }
                $vals = $byItem[$idA] ?? [];
                $ordered = [];
                for ($i=1; $i<=$nb; $i++) { if (isset($vals[$i])) { $ordered[] = (string)$vals[$i]; } }

                // Points
                $earned = 0;
                $b1 = isset($vals[1]) ? (int)$vals[1] : null;
                $b2 = isset($vals[2]) ? (int)$vals[2] : null;
                $r1 = isset($official[$idA][1]) ? (int)$official[$idA][1] : null;
                $r2 = isset($official[$idA][2]) ? (int)$official[$idA][2] : null;
                foreach ($calc as $c) {
                    $lib = (string)$c['libelle']; $nbp=(int)$c['nbPoint'];
                    if ($lib==='1N2') { if ($b1!==null && $b2!==null && $r1!==null && $r2!==null) { if (($b1<=>$b2)===($r1<=>$r2)) $earned += $nbp; } }
                    elseif ($lib==='scoreExact') { if ($b1!==null && $b2!==null && $r1!==null && $r2!==null) { if ($b1===$r1 && $b2===$r2) $earned += $nbp; } }
                }

                $row[] = implode(' | ', $ordered) . ($ordered ? " (".$earned."pt)" : '');
            }
            $rows[] = $row;
        }

        // Build CSV
        $csv = '';
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(function($v){
                $v = str_replace(["\r","\n"], ' ', (string)$v);
                $v = str_replace('"', '""', $v);
                return '"' . $v . '"';
            }, $r)) . "\r\n";
        }

        $filename = 'resultats_phase_' . $idPhase . '.csv';
        $response = $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
                             ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->getBody()->write($csv);
        return $response;
    }

    // Nouveau gestionnaire pour la saisie tabulaire des paris
    public function placerTabulaire(Request $request, Response $response, array $args): Response
    {
        $idPhase = (int)($args['idPhase'] ?? 0);
        $phase = $this->phases->findById($idPhase);
        if ($phase && !empty($phase['dateheureLimite'])) {
            try {
                if ((new \DateTimeImmutable($phase['dateheureLimite'])) < (new \DateTimeImmutable('now'))) {
                    $_SESSION['flash_error'] = 'Phase clôturée: les paris ne sont plus acceptés';
                    return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
                }
            } catch (\Throwable $e) {}
        }
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée';
            return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
        }
        $idUser = (int)($_SESSION['user']['id'] ?? 0);
        $valuesByItem = [];
        if (isset($data['paris']) && is_array($data['paris'])) {
            foreach ($data['paris'] as $idA => $vals) {
                if (!is_array($vals)) continue;
                foreach ($vals as $num => $val) {
                    $val = trim((string)$val);
                    if ($val === '') continue;
                    $valuesByItem[(int)$idA][(int)$num] = $val;
                }
            }
        } else {
            // Compat: ancien format aparier_<id>_val_<i>
            foreach ($data as $k => $v) {
                if (preg_match('/^aparier_(\d+)_val_(\d+)$/', (string)$k, $m)) {
                    $idA = (int)$m[1];
                    $num = (int)$m[2];
                    $val = trim((string)$v);
                    if ($val === '') continue;
                    $valuesByItem[$idA][$num] = $val;
                }
            }
        }
        if (empty($valuesByItem)) {
            $_SESSION['flash_error'] = 'Aucun pari saisi';
            return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
        }
        foreach ($valuesByItem as $idA => $vals) {
            $this->paris->placerValeurs($idUser, (int)$idA, $vals);
        }
        $_SESSION['flash_ok'] = 'Paris enregistrés';
        return $response->withHeader('Location', "/parieur/phases/$idPhase/parier")->withStatus(302);
    }
}
