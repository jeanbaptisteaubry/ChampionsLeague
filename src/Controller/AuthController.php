<?php
declare(strict_types=1);

namespace App\Controller;

use App\Modele\TypeUtilisateurModele;
use App\Modele\UtilisateurModele;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;
use App\Modele\UtilisateurTokenModele;
use App\Service\Mailer;

final class AuthController
{
    public function __construct(
        private Environment $twig,
        private UtilisateurModele $users,
        private TypeUtilisateurModele $types,
        private UtilisateurTokenModele $tokens = new UtilisateurTokenModele()
    ) {}

    public function showLogin(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth/login.html.twig', [
            'title' => 'Connexion',
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        $mail = trim($data['mail'] ?? '');
        $password = (string)($data['password'] ?? '');

        // CSRF
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Veuillez réessayer.';
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $user = $this->users->findByMail($mail);
        if (!$user || !password_verify($password, $user['motDePasseHasch'])) {
            $_SESSION['flash_error'] = 'Identifiants invalides';
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $type = $this->types->findById((int)$user['idTypeUtilisateur']);
        $_SESSION['user'] = [
            'id' => (int)$user['idUtilisateur'],
            'pseudo' => $user['pseudo'],
            'mail' => $user['mail'],
            'idTypeUtilisateur' => (int)$user['idTypeUtilisateur'],
            'typeLibelle' => $type['libelle'] ?? null,
        ];

        // Redirection selon rôle
        $target = ($_SESSION['user']['typeLibelle'] === 'administrateur') ? '/admin' : '/parieur';
        return $response->withHeader('Location', $target)->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    // ===== Reset mot de passe =====
    public function resetRequestForm(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth/reset_request.html.twig', [
            'title' => 'Réinitialiser votre mot de passe',
            'ok' => $_SESSION['flash_ok'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ]);
        unset($_SESSION['flash_ok'], $_SESSION['flash_error']);
        $response->getBody()->write($html);
        return $response;
    }

    public function resetRequest(Request $request, Response $response): Response
    {
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Veuillez réessayer.';
            return $response->withHeader('Location', '/account/reset')->withStatus(302);
        }
        $mail = trim((string)($data['mail'] ?? ''));
        if ($mail === '') {
            $_SESSION['flash_error'] = 'Veuillez renseigner votre email';
            return $response->withHeader('Location', '/account/reset')->withStatus(302);
        }
        $user = $this->users->findByMail($mail);
        if ($user) {
            try {
                $token = $this->tokens->create((int)$user['idUtilisateur'], 'reset', 2*24); // 48h
                $this->sendEmail($request, $mail, $user['pseudo'] ?? $mail, 'Réinitialisation de votre mot de passe',
                    'Cliquez sur le lien suivant pour réinitialiser votre mot de passe:',
                    '/account/reset/' . $token
                );
            } catch (\Throwable $e) {
                // On ignore les erreurs d'envoi pour éviter la fuite d'info
            }
        }
        $_SESSION['flash_ok'] = 'Si un compte correspond à cet email, un lien a été envoyé.';
        return $response->withHeader('Location', '/account/reset')->withStatus(302);
    }

    public function resetForm(Request $request, Response $response, array $args): Response
    {
        $token = (string)($args['token'] ?? '');
        $row = $this->tokens->findValid($token, 'reset');
        $html = $this->twig->render('auth/set_password.html.twig', [
            'title' => 'Réinitialiser votre mot de passe',
            'token' => $token,
            'valid' => (bool)$row,
            'error' => $_SESSION['flash_error'] ?? null,
            'ok' => $_SESSION['flash_ok'] ?? null,
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_ok']);
        $response->getBody()->write($html);
        return $response;
    }

    public function resetSubmit(Request $request, Response $response, array $args): Response
    {
        $token = (string)($args['token'] ?? '');
        $row = $this->tokens->findValid($token, 'reset');
        if (!$row) { $_SESSION['flash_error'] = 'Lien invalide ou expiré'; return $response->withHeader('Location', '/login')->withStatus(302); }
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Veuillez réessayer.';
            return $response->withHeader('Location', '/account/reset/' . urlencode($token))->withStatus(302);
        }
        $p1 = (string)($data['password'] ?? '');
        $p2 = (string)($data['password_confirm'] ?? '');
        if ($p1 === '' || $p1 !== $p2) {
            $_SESSION['flash_error'] = 'Les mots de passe sont vides ou différents';
            return $response->withHeader('Location', '/account/reset/' . urlencode($token))->withStatus(302);
        }
        $complexErr = $this->checkPasswordComplexity($p1);
        if ($complexErr !== null) {
            $_SESSION['flash_error'] = $complexErr;
            return $response->withHeader('Location', '/account/reset/' . urlencode($token))->withStatus(302);
        }
        $this->users->updatePassword((int)$row['idUtilisateur'], $p1);
        $this->tokens->markUsed((int)$row['idToken']);
        $_SESSION['flash_ok'] = 'Mot de passe mis à jour. Vous pouvez vous connecter.';
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    private function sendEmail(Request $request, string $toEmail, string $name, string $subject, string $intro, string $path): void
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $scheme = $uri->getScheme() ?: 'http';
        $port = $uri->getPort();
        $base = $scheme . '://' . $host . ($port && !in_array($port, [80,443]) ? ':' . $port : '');
        $link = $base . $path;

        $html = '<p>Bonjour ' . htmlspecialchars($name) . ',</p>' .
            '<p>' . htmlspecialchars($intro) . '</p>' .
            '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>' .
            '<p>Ce lien expire dans 48h.</p>';
        $text = "Bonjour $name,\n$intro\n$link\n(Lien valable 48h).";
        if (!Mailer::send($toEmail, $name, $subject, $html, $text)) {
            error_log('[AuthController] Echec envoi email à ' . $toEmail);
        }
    }
    public function activateForm(Request $request, Response $response, array $args): Response
    {
        $token = (string)($args['token'] ?? '');
        $row = $this->tokens->findValid($token, 'activation');
        $html = $this->twig->render('auth/set_password.html.twig', [
            'title' => 'Définir votre mot de passe',
            'token' => $token,
            'valid' => (bool)$row,
            'error' => $_SESSION['flash_error'] ?? null,
            'ok' => $_SESSION['flash_ok'] ?? null,
        ]);
        unset($_SESSION['flash_error'], $_SESSION['flash_ok']);
        $response->getBody()->write($html);
        return $response;
    }

    public function activateSubmit(Request $request, Response $response, array $args): Response
    {
        $token = (string)($args['token'] ?? '');
        $row = $this->tokens->findValid($token, 'activation');
        if (!$row) { $_SESSION['flash_error'] = 'Lien invalide ou expiré'; return $response->withHeader('Location', '/login')->withStatus(302); }
        $data = (array)($request->getParsedBody() ?? []);
        if (!csrf_validate($data['_csrf'] ?? null)) {
            $_SESSION['flash_error'] = 'Session expirée. Veuillez réessayer.';
            return $response->withHeader('Location', '/account/activate/' . urlencode($token))->withStatus(302);
        }
        $p1 = (string)($data['password'] ?? '');
        $p2 = (string)($data['password_confirm'] ?? '');
        if ($p1 === '' || $p1 !== $p2) {
            $_SESSION['flash_error'] = 'Les mots de passe sont vides ou différents';
            return $response->withHeader('Location', '/account/activate/' . urlencode($token))->withStatus(302);
        }
        $complexErr = $this->checkPasswordComplexity($p1);
        if ($complexErr !== null) {
            $_SESSION['flash_error'] = $complexErr;
            return $response->withHeader('Location', '/account/activate/' . urlencode($token))->withStatus(302);
        }
        $this->users->updatePassword((int)$row['idUtilisateur'], $p1);
        $this->tokens->markUsed((int)$row['idToken']);
        $_SESSION['flash_ok'] = 'Mot de passe défini. Vous pouvez vous connecter.';
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    private function checkPasswordComplexity(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Le mot de passe doit contenir au moins 8 caractères';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Le mot de passe doit contenir au moins une lettre et un chiffre';
        }
        return null;
    }
}
