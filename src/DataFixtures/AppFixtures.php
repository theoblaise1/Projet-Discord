<?php

namespace App\DataFixtures;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration, chargées avec :
 *
 *     php bin/console doctrine:fixtures:load
 *
 * Comptes créés (même mot de passe pour tous, voir self::PASSWORD) :
 *   - admin@example.com (administrateur : peut modifier/supprimer tous les salons et messages)
 *   - alice@example.com, bob@example.com, chloe@example.com (membres)
 */
final class AppFixtures extends Fixture
{
    public const PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        foreach ([
            ['admin', 'admin@example.com', ['ROLE_ADMIN']],
            ['alice', 'alice@example.com', []],
            ['bob', 'bob@example.com', []],
            ['chloe', 'chloe@example.com', []],
        ] as [$username, $email, $roles]) {
            $user = (new User())
                ->setUsername($username)
                ->setEmail($email)
                ->setRoles($roles)
                ->setCreatedAt(new \DateTimeImmutable('-7 days'));
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

            $manager->persist($user);
            $users[$username] = $user;
        }

        $channels = [];
        foreach ([
            ['général', 'Discussions générales : présentez-vous ici !', 'admin'],
            ['aide-symfony', 'Questions et entraide sur Symfony, Doctrine et Twig.', 'alice'],
            ['détente', 'Musique, jeux vidéo et bonne humeur.', 'bob'],
        ] as [$name, $description, $owner]) {
            $channel = (new Channel())
                ->setName($name)
                ->setDescription($description)
                ->setOwner($users[$owner])
                ->setCreatedAt(new \DateTimeImmutable('-3 days'));

            $manager->persist($channel);
            $channels[$name] = $channel;
        }

        // [salon, auteur, il y a combien de minutes, contenu]
        $conversation = [
            ['général', 'admin', 2 * 1440 + 180, 'Bienvenue sur Symcord ! 🎉 Ce serveur sert de démonstration au projet Symfony « Discord ».'],
            ['général', 'admin', 2 * 1440 + 179, 'Pour une question technique, direction #aide-symfony. Pour le reste, #détente est là pour ça.'],
            ['aide-symfony', 'alice', 1440 + 300, 'Comment réserver une page aux membres connectés ?'],
            ['aide-symfony', 'bob', 1440 + 298, 'Le plus simple : l\'attribut #[IsGranted(\'ROLE_USER\')] sur le contrôleur, ou une règle access_control dans security.yaml.'],
            ['aide-symfony', 'alice', 1440 + 295, 'Merci ! Et pour que seul l\'auteur d\'un salon puisse le modifier ?'],
            ['aide-symfony', 'admin', 1440 + 290, 'Il faut un Voter : regarde src/Security/Voter/ChannelVoter.php dans ce projet.'],
            ['général', 'alice', 1440 + 120, 'Salut tout le monde ! Moi c\'est Alice, je découvre Symfony depuis quelques semaines.'],
            ['général', 'bob', 1440 + 117, 'Bienvenue Alice 👋'],
            ['général', 'bob', 1440 + 116, "Tu verras, une fois qu'on a compris les contrôleurs, les formulaires et Doctrine,\ntout s'enchaîne très vite."],
            ['détente', 'bob', 1440 + 60, 'Vous écoutez quoi en codant ? 🎧'],
            ['détente', 'chloe', 1440 + 58, 'Du lo-fi, ça aide à rester concentrée.'],
            ['détente', 'alice', 1440 + 57, 'Pareil, ou des musiques de jeux vidéo.'],
            ['général', 'chloe', 62, 'Coucou ! Quand on supprime un salon, ses messages sont supprimés aussi ?'],
            ['général', 'admin', 60, 'Oui : la base de données les supprime en cascade (ON DELETE CASCADE sur la clé étrangère).'],
            ['aide-symfony', 'chloe', 30, 'Astuce : php bin/console debug:router affiche toutes les routes de l\'application.'],
            ['détente', 'bob', 10, 'Pause café ☕ avant de reprendre la migration Doctrine !'],
        ];

        // Les messages sont enregistrés dans l'ordre chronologique : leur identifiant
        // (qui sert à les trier) suit ainsi l'ordre d'envoi.
        usort($conversation, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        foreach ($conversation as [$channel, $author, $minutesAgo, $content]) {
            $message = (new Message())
                ->setChannel($channels[$channel])
                ->setAuthor($users[$author])
                ->setContent($content)
                ->setCreatedAt(new \DateTimeImmutable(\sprintf('-%d minutes', $minutesAgo)));

            $manager->persist($message);
        }

        $manager->flush();
    }
}
