<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

/*
 * La base de données de test (var/data_test.db avec SQLite) est recréée à chaque
 * lancement de PHPUnit en jouant les migrations du projet : les tests vérifient
 * donc aussi que les migrations fonctionnent.
 */
(static function (): void {
    $kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();

    $driver = (string) ($kernel->getContainer()->get('doctrine')->getConnection()->getParams()['driver'] ?? '');
    $isSqlite = str_contains($driver, 'sqlite');

    $commands = $isSqlite
        // Avec SQLite, "drop" supprime le fichier et la migration le recrée.
        ? [['command' => 'doctrine:database:drop', '--force' => true]]
        : [
            ['command' => 'doctrine:database:drop', '--force' => true, '--if-exists' => true],
            ['command' => 'doctrine:database:create'],
        ];
    $commands[] = ['command' => 'doctrine:migrations:migrate', '--no-interaction' => true];

    $application = new Application($kernel);
    $application->setAutoExit(false);

    foreach ($commands as $parameters) {
        $output = new BufferedOutput();

        if (0 !== $application->run(new ArrayInput($parameters), $output)) {
            throw new RuntimeException(sprintf("La commande \"%s\" a échoué :\n%s", $parameters['command'], $output->fetch()));
        }
    }

    $kernel->shutdown();
})();
