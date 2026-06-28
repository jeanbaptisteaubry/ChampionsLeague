<?php
declare(strict_types=1);

namespace App\Service;

use App\Modele\AParierModele;
use App\Modele\CampagnePariModele;
use App\Modele\InscriptionPariModele;
use App\Modele\PariModele;
use App\Modele\PhaseCalculPointModele;
use App\Modele\PhaseCampagneModele;
use App\Modele\PhaseParieurVerrouModele;
use App\Modele\ReponsePariModele;
use App\Modele\TypePhaseModele;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

final class PhaseMailer
{
    public static function computeSummary(int $idPhase): array
    {
        $phases = new PhaseCampagneModele();
        $campagnes = new CampagnePariModele();
        $inscriptions = new InscriptionPariModele();
        $aParier = new AParierModele();
        $paris = new PariModele();
        $reponses = new ReponsePariModele();
        $phaseCalc = new PhaseCalculPointModele();
        $locks = new PhaseParieurVerrouModele();
        $typePhase = new TypePhaseModele();

        $phase = $phases->findById($idPhase);
        if (!$phase) {
            return [];
        }

        $campagne = $campagnes->findById((int)$phase['idCampagnePari']);
        $participants = $inscriptions->listUsersByCampagne((int)$phase['idCampagnePari']);
        $lockedUserIds = array_fill_keys($locks->listUserIdsByPhase($idPhase), true);
        $participants = array_values(array_filter(
            $participants,
            static fn(array $participant): bool =>
                isset($lockedUserIds[(int)$participant['idUtilisateur']])
        ));
        $items = $aParier->findByPhase($idPhase);
        $type = $typePhase->findById((int)$phase['idTypePhase']);
        $nbValues = max(1, (int)($type['nbValeurParPari'] ?? 2));
        $labels = [];
        foreach ($typePhase->labels((int)$phase['idTypePhase']) as $label) {
            $labels[(int)$label['numeroValeur']] = $label['libelle'];
        }

        $cells = [];
        $htmlCells = [];
        $betsByItemAndUser = [];
        foreach ($participants as $user) {
            $uid = (int)$user['idUtilisateur'];
            $bets = $paris->findForUserAndPhase($uid, $idPhase);
            $byItem = [];
            foreach ($bets as $bet) {
                $byItem[(int)$bet['idAParier']] = $bet['valeurs'] ?? [];
            }
            foreach ($items as $item) {
                $idA = (int)$item['idAParier'];
                $values = $byItem[$idA] ?? [];
                $betsByItemAndUser[$idA][$uid] = $values;
                $ordered = [];
                for ($i = 1; $i <= $nbValues; $i++) {
                    if (isset($values[$i]) && trim((string)$values[$i]) !== '') {
                        $ordered[] = (string)$values[$i];
                    }
                }
                $cells[$idA][$uid] = BetDisplayFormatter::plain($values, $labels, (string)$item['libellePari']);
                $htmlCells[$idA][$uid] = BetDisplayFormatter::html($values, $labels, (string)$item['libellePari']);
            }
        }

        $official = [];
        $officialHtml = [];
        foreach ($items as $item) {
            $idA = (int)$item['idAParier'];
            $official[$idA] = [];
            foreach ($reponses->findByAParier($idA) as $response) {
                $official[$idA][(int)$response['numeroValeur']] = $response['valeurResultat'];
            }
            $officialHtml[$idA] = BetDisplayFormatter::html($official[$idA], $labels, (string)$item['libellePari']);
        }

        $calc = $phaseCalc->listByPhase($idPhase);
        $points = [];
        $totals = [];
        foreach ($items as $item) {
            $idA = (int)$item['idAParier'];
            foreach ($participants as $user) {
                $uid = (int)$user['idUtilisateur'];
                $earned = PointCalculator::earned(
                    $betsByItemAndUser[$idA][$uid] ?? [],
                    $official[$idA] ?? [],
                    $calc
                );
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
            'htmlCells' => $htmlCells,
            'official' => $official,
            'officialHtml' => $officialHtml,
            'points' => $points,
            'totals' => $totals,
            'labels' => $labels,
            'nb' => $nbValues,
        ];
    }

    /**
     * Send recap email to all enrolled users of the phase campaign.
     * Returns [sent, failed].
     */
    public static function sendPhaseSummaryToAll(Request $request, Environment $twig, int $idPhase): array
    {
        $phases = new PhaseCampagneModele();
        $inscriptions = new InscriptionPariModele();

        $phase = $phases->findById($idPhase);
        if (!$phase) {
            return [0, 0];
        }

        $idCampagne = (int)$phase['idCampagnePari'];
        $destinataires = $inscriptions->listUsersByCampagne($idCampagne);
        if ($destinataires === []) {
            return [0, 0];
        }

        $data = self::computeSummary($idPhase);
        if ($data === []) {
            return [0, 0];
        }

        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80, 443], true) ? ':' . $port : '');
        $link = $base . '/parieur/phases/' . $idPhase . '/resultats';

        $campLib = (string)($data['campagne']['libelle'] ?? '');
        $phaseLib = (string)($data['phase']['libelle'] ?? '');
        $subject = 'Tous les paris sont verrouilles - '
            . ($campLib !== '' ? ($campLib . ' - ') : '')
            . 'Phase "' . $phaseLib . '"';

        $sent = 0;
        $failed = 0;
        foreach ($destinataires as $user) {
            $name = (string)($user['pseudo'] ?? '');
            $email = (string)($user['mail'] ?? '');
            if ($email === '') {
                continue;
            }

            $html = $twig->render('email/phase_locked.html.twig', $data + [
                'recipient' => $name,
                'resultLink' => $link,
            ]);
            $text = 'Bonjour ' . ($name !== '' ? $name : 'parieur') . ",\n"
                . 'Tous les paris sont verrouilles pour la phase "' . $phaseLib . '"'
                . ($campLib !== '' ? ' de la campagne "' . $campLib . '"' : '') . ".\n"
                . 'Consultez le recapitulatif ici: ' . $link . "\n";

            if (Mailer::send($email, $name !== '' ? $name : $email, $subject, $html, $text)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [$sent, $failed];
    }
}
