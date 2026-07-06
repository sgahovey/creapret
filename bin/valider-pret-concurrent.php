<?php

declare(strict_types=1);

// Worker autonome de validation concurrente (US-3.4, BOUT B2). Lance dans un process separe par
// le test de concurrence : il boote le kernel, charge un pret, attend un top-depart commun, puis
// tente de le valider via PretService. Les exceptions de concurrence (deadlock, lock timeout)
// sont capturees et rapportees comme une issue normale : l'invariant RG-1 est verifie par le
// test parent, pas par la reussite de chaque worker.

use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Kernel;
use App\Service\PretService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Script autonome : charger l'environnement (DATABASE_URL, APP_ENV) comme tests/bootstrap.php.
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$idPret = (int) ($argv[1] ?? 0);
$idValidateur = (int) ($argv[2] ?? 0);
$departMicrotime = (float) ($argv[3] ?? 0.0);

$kernel = new Kernel((string) ($_SERVER['APP_ENV'] ?? 'test'), (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();
$container = $kernel->getContainer();

// EM via un service PUBLIC (le vrai conteneur supprime les services prives inutilises).
$em = $container->get('doctrine.orm.default_entity_manager');
assert($em instanceof EntityManagerInterface);
// PretService n'est pas encore consomme par un controleur : on l'assemble a la main.
$service = new PretService($em, $em->getRepository(Pret::class));

$pret = $em->find(Pret::class, $idPret);
$validateur = $em->find(Utilisateur::class, $idValidateur);

if (!$pret instanceof Pret || !$validateur instanceof Utilisateur) {
    fwrite(STDOUT, 'INTROUVABLE');
    exit(2);
}

// Rendez-vous : attendre le top-depart commun pour maximiser la concurrence reelle.
$attente = $departMicrotime - microtime(true);
if ($attente > 0) {
    usleep((int) ($attente * 1_000_000));
}

try {
    $resultat = $service->valider($pret, $validateur);
    fwrite(STDOUT, $resultat->name);

    exit(0);
} catch (\Throwable $e) {
    // Deadlock / lock wait timeout : issue de concurrence acceptable.
    fwrite(STDOUT, 'EXCEPTION:' . $e::class);

    exit(0);
}
