<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const HOME_ROUTE_PATH = '/';
    private const HOME_ROUTE_NAME = 'app_home';
    private const HOME_TEMPLATE = 'home/index.html.twig';
    private const GET_METHOD = 'GET';

    #[Route(path: self::HOME_ROUTE_PATH, name: self::HOME_ROUTE_NAME, methods: [self::GET_METHOD])]
    public function __invoke(): Response
    {
        return $this->render(self::HOME_TEMPLATE);
    }
}
