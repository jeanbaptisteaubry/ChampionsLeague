<?php
declare(strict_types=1);

namespace App\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigFactory
{
    /**
     * Create a minimal Twig Environment.
     *
     * @param string $templatesPath Path to templates directory
     * @param string|null $cachePath Path to cache directory (null disables cache)
     * @param bool $debug Enable Twig debug mode
     */
    public static function create(
        string $templatesPath,
        ?string $cachePath = null,
        bool $debug = false
    ): Environment {
        $loader = new FilesystemLoader($templatesPath);

        $options = [];
        if ($cachePath !== null) {
            $options['cache'] = $cachePath;
        }
        if ($debug) {
            $options['debug'] = true;
        }
        $twig = new Environment($loader, $options);

        // Global 'app' with current user if session active
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
            $twig->addGlobal('app', ['user' => $_SESSION['user']]);
        } else {
            $twig->addGlobal('app', ['user' => null]);
        }

        return $twig;
    }
}
