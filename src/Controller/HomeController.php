<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    #[Route(path: '/', name: 'app_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(
            content: '<!DOCTYPE html><html lang="en"><body><h1>Welcome to the EDI system.</h1></body></html>',
            headers: [
                'Content-Type' => 'text/html; charset=UTF-8',
            ],
        );
    }
}
