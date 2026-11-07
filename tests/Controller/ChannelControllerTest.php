<?php

namespace App\Tests\Controller;

use App\Entity\Channel;
use App\Repository\ChannelRepository;
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ChannelControllerTest extends AppWebTestCase
{
    public function testHomePageListsChannels(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'général', 'Discussions générales');
        $this->createMessage($channel, $alice, 'Bonjour');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.channel-card', 'général');
        self::assertSelectorTextContains('.channel-card', 'Discussions générales');
        self::assertSelectorTextContains('.channel-card', '1 message');
        self::assertSelectorTextContains('.channel-list', 'général');
    }

    public function testCreateChannel(): void
    {
        $this->client->loginUser($this->createUser('alice'));

        $this->client->request('GET', '/channels/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Créer le salon', [
            'channel[name]' => '  aide-symfony  ',
            'channel[description]' => 'Entraide sur Symfony',
        ]);

        $channel = static::getContainer()->get(ChannelRepository::class)->findOneBy(['name' => 'aide-symfony']);
        self::assertInstanceOf(Channel::class, $channel);
        self::assertSame('alice', $channel->getOwner()?->getUsername());
        self::assertSame('Entraide sur Symfony', $channel->getDescription());

        self::assertResponseRedirects(\sprintf('/channels/%d', $channel->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.main-header__title', 'aide-symfony');
        self::assertSelectorTextContains('.toast', 'Le salon #aide-symfony a été créé.');
        self::assertSelectorTextContains('.channel-list__item.is-active', 'aide-symfony');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidChannelProvider(): iterable
    {
        yield 'nom vide' => ['', '', 'Veuillez donner un nom au salon.'];
        yield 'nom composé d\'espaces' => ['   ', '', 'Veuillez donner un nom au salon.'];
        yield 'nom trop court' => ['a', '', 'Le nom du salon doit contenir au moins 2 caractères.'];
        yield 'nom trop long' => [str_repeat('a', 51), '', 'Le nom du salon ne peut pas dépasser 50 caractères.'];
        yield 'nom déjà utilisé' => ['existant', '', 'Un salon porte déjà ce nom.'];
        yield 'description trop longue' => ['nouveau', str_repeat('a', 256), 'La description ne peut pas dépasser 255 caractères.'];
    }

    #[DataProvider('invalidChannelProvider')]
    public function testCreateChannelValidation(string $name, string $description, string $expectedError): void
    {
        $alice = $this->createUser('alice');
        $this->createChannel($alice, 'existant');
        $this->client->loginUser($alice);

        $this->client->request('GET', '/channels/new');
        $this->client->submitForm('Créer le salon', [
            'channel[name]' => $name,
            'channel[description]' => $description,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field__errors', $expectedError);
        self::assertSame(1, static::getContainer()->get(ChannelRepository::class)->count());
    }

    public function testShowChannel(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'général', 'Le sujet du salon');
        $this->createMessage($channel, $alice, 'Premier message');

        $this->client->loginUser($this->createUser('bob'));
        $this->client->request('GET', \sprintf('/channels/%d', $channel->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.main-header__title', 'général');
        self::assertSelectorTextContains('.main-header__topic', 'Le sujet du salon');
        self::assertSelectorTextContains('.chat__welcome-title', 'Bienvenue dans #général !');
        self::assertSelectorTextContains('.message__author', 'alice');
        self::assertSelectorTextContains('.message__content', 'Premier message');
        self::assertSelectorExists('textarea[name="message[content]"]');
        // Bob n'est pas le créateur : pas de bouton de modification
        self::assertSelectorNotExists(\sprintf('a[href="/channels/%d/edit"]', $channel->getId()));
    }

    public function testUnknownChannelReturns404(): void
    {
        $this->client->loginUser($this->createUser('alice'));
        $this->client->request('GET', '/channels/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerCanEditChannel(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'ancien-nom');
        $this->client->loginUser($alice);

        $this->client->request('GET', \sprintf('/channels/%d/edit', $channel->getId()));
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('channel[name]', 'ancien-nom');

        $this->client->submitForm('Enregistrer les modifications', [
            'channel[name]' => 'nouveau-nom',
            'channel[description]' => 'Nouveau sujet',
        ]);

        self::assertResponseRedirects(\sprintf('/channels/%d', $channel->getId()));
        $channel = $this->reload(Channel::class, $channel->getId());
        self::assertSame('nouveau-nom', $channel?->getName());
        self::assertSame('Nouveau sujet', $channel?->getDescription());
    }

    public function testInvalidEditKeepsTheRealNameElsewhereOnThePage(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'mon-salon');
        $this->client->loginUser($alice);

        $this->client->request('GET', \sprintf('/channels/%d/edit', $channel->getId()));
        $this->client->submitForm('Enregistrer les modifications', ['channel[name]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.field__errors', 'Veuillez donner un nom au salon.');
        // La liste des salons et l'en-tête affichent toujours le nom enregistré
        self::assertSelectorTextContains('.channel-list__item.is-active', 'mon-salon');
        self::assertSelectorTextContains('.main-header__title', 'mon-salon');
        self::assertSame('mon-salon', $this->reload(Channel::class, $channel->getId())?->getName());
    }

    public function testOtherMembersCannotEditOrDeleteTheChannel(): void
    {
        $channel = $this->createChannel($this->createUser('alice'), 'salon-alice');
        $this->client->loginUser($this->createUser('bob'));

        $this->client->request('GET', \sprintf('/channels/%d/edit', $channel->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', \sprintf('/channels/%d/delete', $channel->getId()));
        self::assertResponseStatusCodeSame(403);

        self::assertNotNull($this->reload(Channel::class, $channel->getId()));
    }

    public function testAdminCanEditAnyChannel(): void
    {
        $channel = $this->createChannel($this->createUser('alice'), 'salon-alice');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->request('GET', \sprintf('/channels/%d/edit', $channel->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.panel--danger');
    }

    public function testOwnerCanDeleteChannelAndItsMessages(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'a-supprimer');
        $this->createMessage($channel, $alice, 'Message 1');
        $this->createMessage($channel, $alice, 'Message 2');
        $otherChannel = $this->createChannel($alice, 'a-garder');
        $this->createMessage($otherChannel, $alice, 'Message conservé');
        $channelId = $channel->getId();

        $this->client->loginUser($alice);
        $crawler = $this->client->request('GET', \sprintf('/channels/%d/edit', $channelId));
        $this->client->submit($crawler->selectButton('Supprimer le salon')->form());

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.toast', 'Le salon #a-supprimer et ses messages ont été supprimés.');

        self::assertNull($this->reload(Channel::class, $channelId));
        $connection = $this->entityManager()->getConnection();
        // Les messages du salon ont été supprimés en cascade, pas ceux des autres salons
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM message WHERE channel_id = ?', [$channelId]));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM message WHERE channel_id = ?', [$otherChannel->getId()]));
    }

    public function testDeleteRequiresAValidCsrfToken(): void
    {
        $alice = $this->createUser('alice');
        $channel = $this->createChannel($alice, 'protege');
        $this->client->loginUser($alice);

        $this->client->request('POST', \sprintf('/channels/%d/delete', $channel->getId()), ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects(\sprintf('/channels/%d/edit', $channel->getId()));
        self::assertNotNull($this->reload(Channel::class, $channel->getId()));
    }
}
