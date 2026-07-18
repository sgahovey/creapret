<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PretControllerTest extends WebTestCase
{
    private const MARQUEUR = 'zz-test-demande';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    private function repo(KernelBrowser $client): PretRepository
    {
        return $client->getContainer()->get(PretRepository::class);
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\Entity\Pret p WHERE EXISTS (SELECT e2.id FROM App\Entity\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'demande.%')->execute();
    }

    /**
     * @return array{Materiel, Exemplaire}
     */
    private function materielAvecExemplaire(EntityManagerInterface $em, EtatExemplaire $etat = EtatExemplaire::DISPONIBLE): array
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat($etat)->setMateriel($mat);
        $em->persist($ex);
        $em->flush();

        return [$mat, $ex];
    }

    private function emprunteur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $suffixe = ''): Utilisateur
    {
        $u = new Utilisateur();
        $u->setEmail('demande.' . $suffixe . uniqid() . '@cnam-reunion.fr')
            ->setNom('Test')->setPrenom('Demande')
            ->setRole(Role::EMPRUNTEUR)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function pretValide(EntityManagerInterface $em, Exemplaire $ex, Utilisateur $u, string $debut, string $fin): void
    {
        $pret = (new Pret())
            ->setExemplaire($ex)->setEmprunteur($u)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setStatut(StatutPret::VALIDE);
        $em->persist($pret);
        $em->flush();
    }

    private function futur(string $offset): string
    {
        return (new \DateTimeImmutable($offset))->format('Y-m-d');
    }

    public function test_une_demande_valide_cree_un_pret_en_attente(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        [$mat, $ex] = $this->materielAvecExemplaire($em);
        $user = $this->emprunteur($em, $hasher);
        $client->loginUser($user);

        $debut = $this->futur('+10 days');
        $fin = $this->futur('+15 days');
        $client->request('GET', '/catalogue/' . $mat->getId() . '?debut=' . $debut . '&fin=' . $fin);
        self::assertResponseIsSuccessful();

        $client->submitForm('Demander un prêt');
        self::assertResponseRedirects('/catalogue/' . $mat->getId());

        $prets = $this->repo($client)->findBy(['exemplaire' => $ex->getId()]);
        self::assertCount(1, $prets);
        self::assertSame(StatutPret::DEMANDE, $prets[0]->getStatut());
        self::assertSame($user->getId(), $prets[0]->getEmprunteur()->getId());
    }

    public function test_une_periode_dans_le_passe_est_refusee_par_la_contrainte(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        [$mat, $ex] = $this->materielAvecExemplaire($em);
        $client->loginUser($this->emprunteur($em, $hasher));

        // Periode passee mais fin > debut : la fiche calcule la dispo et AFFICHE le formulaire
        // (pre-rempli avec la periode passee). On soumet ce vrai formulaire (jeton CSRF inclus).
        $debut = $this->futur('-10 days');
        $fin = $this->futur('-5 days');
        $client->request('GET', '/catalogue/' . $mat->getId() . '?debut=' . $debut . '&fin=' . $fin);
        self::assertResponseIsSuccessful();

        // La cause testee : la contrainte GreaterThanOrEqual(today) du DTO refuse la periode passee.
        $client->submitForm('Demander un prêt');
        self::assertResponseRedirects('/catalogue/' . $mat->getId());

        self::assertCount(0, $this->repo($client)->findBy(['exemplaire' => $ex->getId()]));
    }

    public function test_reverification_rg4_refuse_si_exemplaire_pris_entre_temps(): void
    {
        $client = static::createClient();
        // On garde le meme kernel/em entre les requetes pour simuler la concurrence sur la base.
        $client->disableReboot();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        [$mat, $ex] = $this->materielAvecExemplaire($em);
        $user = $this->emprunteur($em, $hasher);
        $client->loginUser($user);

        $debut = $this->futur('+10 days');
        $fin = $this->futur('+15 days');

        // Exemplaire libre au moment de l'affichage : le formulaire de demande apparait.
        $client->request('GET', '/catalogue/' . $mat->getId() . '?debut=' . $debut . '&fin=' . $fin);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action*="/pret/demander"]');

        // Concurrence : un autre emprunteur obtient un pret VALIDE sur cet exemplaire/periode
        // ENTRE l'affichage et la soumission.
        $autre = $this->emprunteur($em, $hasher, 'autre');
        $this->pretValide($em, $ex, $autre, $debut, $fin);

        // Soumission du formulaire : le controleur reverifie RG-4 (trouverUnLibreSurPeriode -> null)
        // et refuse. C'est la defense en profondeur contre la course affichage/soumission.
        $client->submitForm('Demander un prêt');
        self::assertResponseRedirects('/catalogue/' . $mat->getId());

        $em->clear();
        $repo = $this->repo($client);
        // Aucune DEMANDE creee : l'exemplaire n'etait plus libre a la soumission (cause testee).
        self::assertCount(0, $repo->findBy(['exemplaire' => $ex->getId(), 'statut' => StatutPret::DEMANDE]));
        // Le pret concurrent VALIDE existe toujours.
        self::assertCount(1, $repo->findBy(['exemplaire' => $ex->getId(), 'statut' => StatutPret::VALIDE]));
    }

    public function test_acces_anonyme_redirige_vers_login(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $this->purger($em);
        [$mat] = $this->materielAvecExemplaire($em);

        // Zone ^/pret : un anonyme est redirige vers la connexion (le firewall intercepte
        // avant le controleur, independamment du CSRF).
        $client->request('POST', '/pret/demander/' . $mat->getId(), [
            'demande_pret' => ['debut' => $this->futur('+10 days'), 'fin' => $this->futur('+15 days')],
        ]);
        self::assertResponseStatusCodeSame(302);
        self::assertStringContainsString('/connexion', (string) $client->getResponse()->headers->get('Location'));
    }

    public function test_methode_get_refusee(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);
        [$mat] = $this->materielAvecExemplaire($em);
        $client->loginUser($this->emprunteur($em, $hasher));

        // L'action est methods: ['POST'] : un GET renvoie 405.
        $client->request('GET', '/pret/demander/' . $mat->getId());
        self::assertResponseStatusCodeSame(405);
    }

    protected function tearDown(): void
    {
        // Reutilise le kernel deja boote par le test (createClient ne doit etre appele qu'une fois,
        // et test_reverification_rg4 utilise disableReboot()).
        $this->purger(static::getContainer()->get(EntityManagerInterface::class));
        parent::tearDown();
    }
}
