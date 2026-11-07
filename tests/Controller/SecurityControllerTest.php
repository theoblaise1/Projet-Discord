<?php

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;

final class SecurityControllerTest extends AppWebTestCase
{
    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        foreach (['/', '/channels/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login', message: $url);
        }
    }

    public function testLoginWithEmail(): void
    {
        $this->createUser('alice');

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Se connecter', [
            '_username' => 'ALICE@example.com',
            '_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.user-panel__name', 'alice');
    }

    public function testLoginWithUsername(): void
    {
        $this->createUser('alice');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'Alice',
            '_password' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/');
    }

    public function testLoginWithWrongPasswordShowsAnError(): void
    {
        $this->createUser('alice');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'alice@example.com',
            '_password' => 'mauvais-mot-de-passe',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert--error', 'Identifiants invalides.');
        // Le dernier identifiant saisi est conservé
        self::assertInputValueSame('_username', 'alice@example.com');
    }

    public function testLoginIsProtectedAgainstCsrf(): void
    {
        $this->createUser('alice');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => 'alice@example.com',
            '_password' => self::PASSWORD,
            '_csrf_token' => 'jeton-invalide',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert--error');
    }

    public function testLogout(): void
    {
        $this->client->loginUser($this->createUser('alice'));

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.toast', 'Vous êtes bien déconnecté');

        // Une fois déconnecté, l'application n'est plus accessible
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testLoggedInUserIsRedirectedAwayFromLoginPage(): void
    {
        $this->client->loginUser($this->createUser('alice'));
        $this->client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }
}
