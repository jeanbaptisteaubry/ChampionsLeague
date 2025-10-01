<?php
declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

final class HomeController
{
    public function __construct(private Environment $twig)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $param = new \App\Modele\ParametreModele();
        $homeText = $param->get('home_text') ?? '';
        $html = $this->twig->render('home.html.twig', [
            'title' => 'Accueil',
            'homeText' => $homeText,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function hello(Request $request, Response $response, array $args): Response
    {
        $name = $args['name'] ?? 'World';
        $html = $this->twig->render('hello.html.twig', [
            'title' => 'Hello',
            'name'  => $name,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}
