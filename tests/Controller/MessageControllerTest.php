<?php

namespace App\Tests\Controller;

use App\Controller\ChannelController;
use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Tests\AppWebTestCase;

final class MessageControllerTest extends AppWebTestCase
{
    private User $alice;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->createUser('alice');
        $this->channel = $this->createChannel($this->alice, 'général');
    }

    private function channelUrl(): string
    {
        return \sprintf('/channels/%d', $this->channel->getId());
    }

    public function testSendMessage(): void
    {
        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());

        $this->client->submitForm('Envoyer', [
            'message[content]' => "  Bonjour à tous !\nComment ça va ?  ",
        ]);

        self::assertResponseRedirects($this->channelUrl());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.message__author', 'alice');
        self::assertSelectorTextContains('.message__content', 'Bonjour à tous !');
        // Les retours à la ligne sont conservés à l'affichage
        self::assertStringContainsString('Bonjour à tous !<br />', (string) $this->client->getResponse()->getContent());

        $messages = static::getContainer()->get(MessageRepository::class)->findAll();
        self::assertCount(1, $messages);
        // Les espaces superflus en début et fin de message sont retirés
        self::assertSame("Bonjour à tous !\nComment ça va ?", $messages[0]->getContent());
        self::assertSame('alice', $messages[0]->getAuthor()?->getUsername());
    }

    public function testBlankMessageIsRejected(): void
    {
        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());
        $this->client->submitForm('Envoyer', ['message[content]' => "   \n   "]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.composer__errors', 'Votre message ne peut pas être vide.');
        self::assertSame(0, static::getContainer()->get(MessageRepository::class)->count());
    }

    public function testTooLongMessageIsRejected(): void
    {
        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());
        $this->client->submitForm('Envoyer', ['message[content]' => str_repeat('é', Message::MAX_LENGTH + 1)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.composer__errors', 'Votre message ne peut pas dépasser 2000 caractères (il en contient 2001).');
        self::assertSame(0, static::getContainer()->get(MessageRepository::class)->count());
    }

    public function testMessageOfMaximumLengthIsAccepted(): void
    {
        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());
        $this->client->submitForm('Envoyer', ['message[content]' => str_repeat('é', Message::MAX_LENGTH)]);

        self::assertResponseRedirects($this->channelUrl());
    }

    public function testMessageContentIsEscaped(): void
    {
        $this->createMessage($this->channel, $this->alice, '<script>alert("xss")</script>');

        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
    }

    public function testAjaxSendReturnsJson(): void
    {
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', $this->channelUrl());
        $token = $crawler->filter('input[name="message[_token]"]')->attr('value');
        $headers = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

        // Message valide : 201 + identifiant du message
        $this->client->request('POST', $this->channelUrl(), ['message' => ['content' => 'Envoyé en AJAX', '_token' => $token]], [], $headers);
        self::assertResponseStatusCodeSame(201);
        $data = $this->jsonResponse();
        self::assertIsInt($data['id']);
        self::assertSame('Envoyé en AJAX', $this->reload(Message::class, $data['id'])?->getContent());

        // Message vide : 422 + erreurs de validation
        $this->client->request('POST', $this->channelUrl(), ['message' => ['content' => '', '_token' => $token]], [], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertContains('Votre message ne peut pas être vide.', $this->jsonResponse()['errors']);

        // Jeton CSRF invalide : refusé
        $this->client->request('POST', $this->channelUrl(), ['message' => ['content' => 'Pirate', '_token' => 'invalide']], [], $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertNotEmpty($this->jsonResponse()['errors']);

        self::assertSame(1, static::getContainer()->get(MessageRepository::class)->count());
    }

    public function testPollingReturnsOnlyNewMessages(): void
    {
        $first = $this->createMessage($this->channel, $this->alice, 'Premier');
        $this->createMessage($this->channel, $this->alice, 'Deuxième');
        $last = $this->createMessage($this->channel, $this->alice, 'Troisième');

        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl().'/messages?after='.$first->getId(), server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $data = $this->jsonResponse();
        self::assertStringNotContainsString('Premier', $data['html']);
        self::assertStringContainsString('Deuxième', $data['html']);
        self::assertStringContainsString('Troisième', $data['html']);
        self::assertSame($last->getId(), $data['lastId']);

        // Rien de nouveau
        $this->client->request('GET', $this->channelUrl().'/messages?after='.$last->getId());
        self::assertSame(['html' => '', 'lastId' => $last->getId()], $this->jsonResponse());
    }

    public function testConsecutiveMessagesFromTheSameAuthorAreGrouped(): void
    {
        $bob = $this->createUser('bob');
        $first = $this->createMessage($this->channel, $this->alice, 'Salut', new \DateTimeImmutable('-3 minutes'));
        $second = $this->createMessage($this->channel, $this->alice, 'Ça va ?', new \DateTimeImmutable('-2 minutes'));
        $third = $this->createMessage($this->channel, $bob, 'Oui et toi ?', new \DateTimeImmutable('-1 minute'));

        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());

        self::assertSelectorNotExists(\sprintf('#message-%d.message--grouped', $first->getId()));
        self::assertSelectorExists(\sprintf('#message-%d.message--grouped', $second->getId()));
        self::assertSelectorNotExists(\sprintf('#message-%d.message--grouped', $third->getId()));

        // Le regroupement fonctionne aussi pour les messages chargés en AJAX
        $this->client->request('GET', $this->channelUrl().'/messages?after='.$first->getId());
        $html = $this->jsonResponse()['html'];
        self::assertMatchesRegularExpression(\sprintf('/class="message message--grouped" id="message-%d"/', $second->getId()), $html);
        self::assertMatchesRegularExpression(\sprintf('/class="message" id="message-%d"/', $third->getId()), $html);
    }

    public function testOlderMessagesAreLoadedOnDemand(): void
    {
        $total = ChannelController::MESSAGES_PER_PAGE + 5;
        $messages = [];
        for ($i = 1; $i <= $total; ++$i) {
            $messages[$i] = $this->createMessage($this->channel, $this->alice, 'Message n°'.$i);
        }

        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', $this->channelUrl());

        // Seuls les 50 derniers messages sont affichés, avec un bouton pour charger les précédents
        self::assertCount(ChannelController::MESSAGES_PER_PAGE, $crawler->filter('.messages .message'));
        self::assertSelectorExists('[data-chat-load-more]');
        self::assertSelectorNotExists(\sprintf('#message-%d', $messages[5]->getId()));
        self::assertSelectorExists(\sprintf('#message-%d', $messages[6]->getId()));

        $this->client->request('GET', $this->channelUrl().'/messages?before='.$messages[6]->getId());
        $data = $this->jsonResponse();
        self::assertFalse($data['hasMore']);
        self::assertSame(5, substr_count($data['html'], 'data-message-id='));
        self::assertStringContainsString('Message n°1<', $data['html']);
    }

    public function testAuthorCanDeleteTheirMessage(): void
    {
        $message = $this->createMessage($this->channel, $this->alice, 'À supprimer');
        $messageId = $message->getId();

        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', $this->channelUrl());
        $this->client->submit($crawler->filter(\sprintf('#message-%d form', $messageId))->form());

        self::assertResponseRedirects($this->channelUrl());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.toast', 'Le message a été supprimé.');
        self::assertNull($this->reload(Message::class, $messageId));
    }

    public function testAjaxDeleteReturnsJson(): void
    {
        $message = $this->createMessage($this->channel, $this->alice, 'À supprimer');
        $messageId = $message->getId();

        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', $this->channelUrl());
        $token = $crawler->filter(\sprintf('#message-%d input[name="_token"]', $messageId))->attr('value');

        $this->client->request('POST', \sprintf('/messages/%d/delete', $messageId), ['_token' => $token], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $messageId], $this->jsonResponse());
        self::assertNull($this->reload(Message::class, $messageId));
    }

    public function testChannelOwnerCanModerateMessages(): void
    {
        $bob = $this->createUser('bob');
        $message = $this->createMessage($this->channel, $bob, 'Message de Bob');

        // Alice a créé le salon : elle peut supprimer le message de Bob
        $this->client->loginUser($this->alice);
        $this->client->request('GET', $this->channelUrl());
        self::assertSelectorExists(\sprintf('#message-%d form', $message->getId()));
    }

    public function testOtherMembersCannotDeleteAMessage(): void
    {
        $message = $this->createMessage($this->channel, $this->alice, 'Message d\'Alice');
        $this->client->loginUser($this->createUser('bob'));

        $this->client->request('GET', $this->channelUrl());
        self::assertSelectorNotExists(\sprintf('#message-%d form', $message->getId()));

        $this->client->request('POST', \sprintf('/messages/%d/delete', $message->getId()), ['_token' => 'peu-importe']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->reload(Message::class, $message->getId()));
    }

    public function testDeleteRequiresAValidCsrfToken(): void
    {
        $message = $this->createMessage($this->channel, $this->alice, 'Protégé');
        $this->client->loginUser($this->alice);

        $this->client->request('POST', \sprintf('/messages/%d/delete', $message->getId()), ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects($this->channelUrl());
        self::assertNotNull($this->reload(Message::class, $message->getId()));
    }

    public function testPollingAnUnknownChannelReturns404(): void
    {
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/channels/999999/messages?after=0');

        self::assertResponseStatusCodeSame(404);
    }
}
