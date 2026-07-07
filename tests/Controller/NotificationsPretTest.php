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
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifie que chaque evenement du cycle de vie du pret met en file l'email attendu (US-4.2).
 * Aligne sur le pattern CreaSlot : le transport async garde les messages en FILE (jamais envoyes
 * en test), on assert via assertQueuedEmailCount, et on calcule le destinataire attendu en tenant
 * compte de la redirection dev (assumee, non neutralisee).
 */
final class NotificationsPretTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private const MARQUEUR = 'zz-test-notif-pret';

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
    }

    /** Destinataire reellement cible : l'adresse de redirection si definie, sinon l'adresse reelle. */
    private function destinataireAttendu(string $emailReel): string
    {
        $redirection = $_ENV['APP_MAILER_REDIRECT_TO'] ?? '';

        return '' !== $redirection ? $redirection : $emailReel;
    }

    private function purger(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM App\\Entity\\Pret p WHERE EXISTS (SELECT e2.id FROM App\\Entity\\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Exemplaire e WHERE e.numeroInventaire LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Materiel m WHERE m.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Categorie c WHERE c.nom LIKE :p')
            ->setParameter('p', self::MARQUEUR . '%')->execute();
        $em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', 'notifpret.%')->execute();
    }

    private function utilisateur(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Role $role, string $suffixe): Utilisateur
    {
        $u = (new Utilisateur())
            ->setEmail('notifpret.' . $suffixe . '.' . uniqid() . '@creapret.local')
            ->setNom('T')->setPrenom($suffixe)
            ->setRole($role)->setEstActif(true);
        $u->setMotDePasseHash($hasher->hashPassword($u, 'motdepasse'));
        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function materielAvecExemplaire(EntityManagerInterface $em): Exemplaire
    {
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-cat');
        $em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-mat')->setCategorie($cat);
        $em->persist($mat);
        $ex = (new Exemplaire())
            ->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())
            ->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $em->persist($ex);
        $em->flush();

        return $ex;
    }

    private function pret(EntityManagerInterface $em, Utilisateur $emprunteur, Exemplaire $ex, StatutPret $statut = StatutPret::DEMANDE): Pret
    {
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($emprunteur)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10 00:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-09-15 00:00:00'))
            ->setStatut($statut);
        $em->persist($pret);
        $em->flush();

        return $pret;
    }

    public function test_valider_met_en_file_un_email_pour_l_emprunteur(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $gestionnaire = $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest');
        $ex = $this->materielAvecExemplaire($em);
        $this->pret($em, $emprunteur, $ex);
        $attendu = $this->destinataireAttendu($emprunteur->getEmail());

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets');
        $client->submitForm('Valider');

        self::assertQueuedEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertNotNull($message);
        self::assertEmailAddressContains($message, 'To', $attendu);
    }

    public function test_refuser_met_en_file_un_email_avec_le_motif(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $gestionnaire = $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest');
        $ex = $this->materielAvecExemplaire($em);
        $pret = $this->pret($em, $emprunteur, $ex);
        $idPret = $pret->getId();
        $attendu = $this->destinataireAttendu($emprunteur->getEmail());

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets/' . $idPret . '/refuser');
        $client->submitForm('Confirmer le refus', [
            'refus_pret[motif]' => 'Materiel indisponible pour cette periode.',
        ]);

        self::assertQueuedEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertNotNull($message);
        self::assertEmailAddressContains($message, 'To', $attendu);
        self::assertEmailHtmlBodyContains($message, 'Materiel indisponible pour cette periode.');
    }

    public function test_retour_met_en_file_un_email_pour_l_emprunteur(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        $gestionnaire = $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest');
        $ex = $this->materielAvecExemplaire($em);
        $this->pret($em, $emprunteur, $ex, StatutPret::VALIDE);
        $attendu = $this->destinataireAttendu($emprunteur->getEmail());

        $client->loginUser($gestionnaire);
        $client->request('GET', '/gestion/prets/retours');
        $client->submitForm('Enregistrer le retour');

        self::assertQueuedEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertNotNull($message);
        self::assertEmailAddressContains($message, 'To', $attendu);
    }

    public function test_demander_met_en_file_un_email_par_gestionnaire_actif(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $this->purger($em);

        $emprunteur = $this->utilisateur($em, $hasher, Role::EMPRUNTEUR, 'emp');
        // Deux gestionnaires actifs (destinataires) + un inactif (ne doit PAS recevoir).
        $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest1');
        $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'gest2');
        $inactif = $this->utilisateur($em, $hasher, Role::GESTIONNAIRE, 'inactif');
        $inactif->setEstActif(false);
        $em->flush();

        $ex = $this->materielAvecExemplaire($em);
        $materielId = $ex->getMateriel()->getId();

        // N = nombre reel de gestionnaires actifs en base (robuste a l'etat global).
        $repo = $client->getContainer()->get(UtilisateurRepository::class);
        $nbActifs = count($repo->findGestionnaires());
        self::assertGreaterThanOrEqual(2, $nbActifs, 'Au moins nos 2 gestionnaires actifs doivent compter.');

        $client->loginUser($emprunteur);
        $client->request('GET', '/catalogue/' . $materielId . '?debut=2026-09-10&fin=2026-09-15');
        $client->submitForm('Demander un pret', [
            'demande_pret[debut]' => '2026-09-10',
            'demande_pret[fin]'   => '2026-09-15',
        ]);

        // Un email en file par gestionnaire actif ; l'inactif n'est pas compte (filtre estActif).
        self::assertQueuedEmailCount($nbActifs);
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger($em);
        parent::tearDown();
    }
}
