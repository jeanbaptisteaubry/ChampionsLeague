<?php
declare(strict_types=1);

namespace App\Service;

use App\Modele\AParierModele;
use App\Modele\CampagnePariModele;
use App\Modele\InscriptionPariModele;
use App\Modele\PariModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\PhaseCalculPointModele;
use App\Modele\ReponsePariModele;
use App\Service\Mailer;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

final class PhaseMailer
{
    /**
     * Compute phase summary arrays similar to ParieurController::resultatsPhase
     */
    private static function computeSummary(int $idPhase): array
    {
        $phases = new PhaseCampagneModele();
        $campagnes = new CampagnePariModele();
        $inscriptions = new InscriptionPariModele();
        $aParier = new AParierModele();
        $paris = new PariModele();
        $reponses = new ReponsePariModele();
        $phaseCalc = new PhaseCalculPointModele();

        $phase = $phases->findById($idPhase);
        if (!$phase) { return []; }
        $campagne = $campagnes->findById((int)$phase['idCampagnePari']);
        $participants = $inscriptions->listUsersByCampagne((int)$phase['idCampagnePari']);
        $items = $aParier->findByPhase($idPhase);

        // Cells: user bets per item (as string "v1 — v2")
        $cells = [];
        foreach ($participants as $u) {
            $uid = (int)$u['idUtilisateur'];
            $bets = $paris->findForUserAndPhase($uid, $idPhase);
            $byItem = [];
            foreach ($bets as $b) { $byItem[(int)$b['idAParier']] = ($b['valeurs'] ?? []); }
            foreach ($items as $it) {
                $idA = (int)$it['idAParier'];
                $vals = $byItem[$idA] ?? [];
                $ordered = [];
                for ($i=1; $i<=2; $i++) { if (isset($vals[$i])) { $ordered[] = (string)$vals[$i]; } }
                $cells[$idA][$uid] = implode(' — ', $ordered);
            }
        }

        // Official results
        $official = [];
        foreach ($items as $it) {
            $rid = (int)$it['idAParier'];
            $res = $reponses->findByAParier($rid);
            $rvals = [];
            foreach ($res as $r) { $rvals[(int)$r['numeroValeur']] = $r['valeurResultat']; }
            $official[$rid] = $rvals;
        }

        // Points per item per user and totals
        $calc = $phaseCalc->listByPhase($idPhase);
        $points = [];
        $totals = [];
        foreach ($items as $it) {
            $idA = (int)$it['idAParier'];
            foreach ($participants as $u) {
                $uid = (int)$u['idUtilisateur'];
                $betVals = [];
                if (isset($cells[$idA][$uid]) && $cells[$idA][$uid] !== '') {
                    $parts = explode(' — ', $cells[$idA][$uid]);
                    if (isset($parts[0]) && $parts[0] !== '') { $betVals[1] = (int)$parts[0]; }
                    if (isset($parts[1]) && $parts[1] !== '') { $betVals[2] = (int)$parts[1]; }
                }
                $resVals = $official[$idA] ?? [];
                $earned = 0;
                $b1 = isset($betVals[1]) ? (int)$betVals[1] : null;
                $b2 = isset($betVals[2]) ? (int)$betVals[2] : null;
                $r1 = isset($resVals[1]) ? (int)$resVals[1] : null;
                $r2 = isset($resVals[2]) ? (int)$resVals[2] : null;
                foreach ($calc as $c) {
                    $lib = (string)$c['libelle']; $nbp = (int)$c['nbPoint'];
                    if ($lib === '1N2') {
                        if ($b1 !== null && $b2 !== null && $r1 !== null && $r2 !== null) {
                            if (($b1 <=> $b2) === ($r1 <=> $r2)) { $earned += $nbp; }
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

        return [
            'campagne' => $campagne,
            'phase' => $phase,
            'participants' => $participants,
            'items' => $items,
            'cells' => $cells,
            'official' => $official,
            'points' => $points,
            'totals' => $totals,
        ];
    }

    /**
     * Send recap email (with HTML table) to all enrolled users of the phase's campagne.
     * Returns [sent, failed].
     */
    public static function sendPhaseSummaryToAll(Request $request, Environment $twig, int $idPhase): array
    {
        $phases = new PhaseCampagneModele();
        $inscriptions = new InscriptionPariModele();

        $phase = $phases->findById($idPhase);
        if (!$phase) { return [0, 0]; }
        $idCampagne = (int)$phase['idCampagnePari'];
        $destinataires = $inscriptions->listUsersByCampagne($idCampagne);
        if (empty($destinataires)) { return [0, 0]; }

        $data = self::computeSummary($idPhase);
        if (empty($data)) { return [0, 0]; }

        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80, 443]) ? ':' . $port : '');
        $link = $base . '/parieur/phases/' . $idPhase . '/resultats';

        $campLib = (string)($data['campagne']['libelle'] ?? '');
        $phaseLib = (string)($data['phase']['libelle'] ?? '');
        $subject = 'Tous les paris sont verrouillés — ' . ($campLib !== '' ? ($campLib . ' — ') : '') . 'Phase "' . $phaseLib . '"';

        $sent = 0; $failed = 0;
        foreach ($destinataires as $u) {
            $name = (string)($u['pseudo'] ?? '');
            $email = (string)($u['mail'] ?? '');
            if ($email === '') { continue; }

            $html = $twig->render('email/phase_locked.html.twig', $data + [
                'recipient' => $name,
                'resultLink' => $link,
            ]);
            $text = 'Bonjour ' . ($name !== '' ? $name : 'parieur') . ",\n"
                . 'Tous les paris sont verrouillés pour la phase "' . $phaseLib . '"'
                . ($campLib !== '' ? ' de la campagne "' . $campLib . '"' : '') . ".\n"
                . 'Consultez le récapitulatif ici: ' . $link . "\n";

            if (Mailer::send($email, $name !== '' ? $name : $email, $subject, $html, $text)) { $sent++; } else { $failed++; }
        }

        return [$sent, $failed];
    }
}

