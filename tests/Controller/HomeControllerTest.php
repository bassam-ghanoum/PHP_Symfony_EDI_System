<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePageIsAvailableViaGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
        self::assertSelectorTextContains('h1', 'Welcome to the EDI system.');
    }
}
