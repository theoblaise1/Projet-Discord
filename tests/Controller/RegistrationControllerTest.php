<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\AppWebTestCase;

final class RegistrationControllerTest extends AppWebTestCase
{
    public function testRegisterPageIsDisplayed(): void
    {
        $this->client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un compte');
        self::assertSelectorExists('input[name="registration_form[email]"]');
        self::assertSelectorExists('input[name="registration_form[username]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][first]"]');
        self::assertSelectorExists('input[name="registration_form[plainPassword][second]"]');
    }

    public function testSuccessfulRegistrationLogsTheUserIn(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'Nouveau.Membre@Example.com',
            'registration_form[username]' => 'nouveau_membre',
            'registration_form[plainPassword][first]' => 'unMotDePasseSolide',
            'registration_form[plainPassword][second]' => 'unMotDePasseSolide',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero__title', 'Bienvenue, nouveau_membre !');
        self::assertSelectorTextContains('.toast', 'Bienvenue sur Symcord, nouveau_membre !');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'nouveau_membre']);
        self::assertInstanceOf(User::class, $user);
        // L'e-mail est enregistré en minuscules et le mot de passe est haché.
        self::assertSame('nouveau.membre@example.com', $user->getEmail());
        self::assertNotSame('unMotDePasseSolide', $user->getPassword());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testInvalidDataShowsValidationErrors(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'pas-un-email',
            'registration_form[username]' => 'x',
            'registration_form[plainPassword][first]' => 'court',
            'registration_form[plainPassword][second]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.field__errors', 'n\'est pas valide');
        self::assertAnySelectorTextContains('.field__errors', 'au moins 2 caractères');
        self::assertAnySelectorTextContains('.field__errors', 'au moins 8 caractères');
        self::assertSame(0, static::getContainer()->get(UserRepository::class)->count());
    }

    public function testBlankFormShowsRequiredErrors(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte');

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.field__errors', 'Veuillez saisir une adresse e-mail.');
        self::assertAnySelectorTextContains('.field__errors', 'Veuillez choisir un nom d\'utilisateur.');
        self::assertAnySelectorTextContains('.field__errors', 'Veuillez choisir un mot de passe.');
    }

    public function testPasswordsMustMatch(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'membre@example.com',
            'registration_form[username]' => 'membre',
            'registration_form[plainPassword][first]' => 'motdepasse1',
            'registration_form[plainPassword][second]' => 'motdepasse2',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field__errors', 'Les deux mots de passe ne correspondent pas.');
    }

    public function testEmailAndUsernameMustBeUnique(): void
    {
        $this->createUser('alice');

        $this->client->request('GET', '/register');
        $this->client->submitForm('Créer mon compte', [
            'registration_form[email]' => 'ALICE@example.com',
            'registration_form[username]' => 'alice',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('.field__errors', 'Un compte existe déjà avec cette adresse e-mail.');
        self::assertAnySelectorTextContains('.field__errors', 'Ce nom d\'utilisateur est déjà pris.');
    }

    public function testLoggedInUserIsRedirectedAwayFromRegisterPage(): void
    {
        $this->client->loginUser($this->createUser('alice'));
        $this->client->request('GET', '/register');

        self::assertResponseRedirects('/');
    }
}
