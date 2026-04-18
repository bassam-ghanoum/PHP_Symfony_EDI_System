<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    private const HOME_ROUTE_PATH = '/';
    private const GET_METHOD = 'GET';
    private const CONTENT_TYPE_HEADER = 'content-type';
    private const HTML_CONTENT_TYPE = 'text/html; charset=UTF-8';
    private const MAIN_HEADING_SELECTOR = 'h1';
    private const WELCOME_HEADING = 'Welcome to the EDI system.';

    public function testHomePageIsAvailableViaGet(): void
    {
        $client = static::createClient();
        $client->request(self::GET_METHOD, self::HOME_ROUTE_PATH);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(self::CONTENT_TYPE_HEADER, self::HTML_CONTENT_TYPE);
        self::assertSelectorTextContains(self::MAIN_HEADING_SELECTOR, self::WELCOME_HEADING);
    }
}
