<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Exemplaire;
use App\Entity\Materiel;
use App\Entity\Pret;
use App\Entity\Utilisateur;
use App\Enum\EtatExemplaire;
use App\Enum\Role;
use App\Enum\StatutPret;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * Exerce les VRAIES methodes de notification de rappel/retard (US-4.3), la ou le test de commande
 * les mocke. Verifie notamment le respect de l'opt-out emailRappel (base legale RGPD art. 6.1.b).
 */
final class NotificationRappelRetardTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private const MARQUEUR = 'zz-test-notif-rr';
    private EntityManagerInterface $em;
    private NotificationService $notifications;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->notifications = static::getContainer()->get(NotificationService::class);
        $this->purger();
    }

    private function purger(): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\Pret p WHERE EXISTS (SELECT e2.id FROM App\\Entity\\Exemplaire e2 WHERE e2 = p.exemplaire AND e2.numeroInventaire LIKE :p)')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Exemplaire e WHERE e.numeroInventaire LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Materiel m WHERE m.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Categorie c WHERE c.nom LIKE :p')->setParameter('p', self::MARQUEUR . '%')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\Utilisateur u WHERE u.email LIKE :p')->setParameter('p', 'notifrr.%')->execute();
    }

    private function pret(bool $emailRappel): Pret
    {
        $u = (new Utilisateur())->setEmail('notifrr.' . uniqid() . '@creapret.local')
            ->setNom('T')->setPrenom('E')->setRole(Role::EMPRUNTEUR)->setEstActif(true)->setMotDePasseHash('x')
            ->setEmailRappel($emailRappel);
        $this->em->persist($u);
        $cat = (new Categorie())->setNom(self::MARQUEUR . '-c');
        $this->em->persist($cat);
        $mat = (new Materiel())->setNom(self::MARQUEUR . '-m')->setCategorie($cat);
        $this->em->persist($mat);
        $ex = (new Exemplaire())->setNumeroInventaire(self::MARQUEUR . '-' . uniqid())->setEtat(EtatExemplaire::DISPONIBLE)->setMateriel($mat);
        $this->em->persist($ex);
        $pret = (new Pret())->setExemplaire($ex)->setEmprunteur($u)
            ->setDateDebut(new \DateTimeImmutable('2026-09-10'))->setDateFin(new \DateTimeImmutable('2026-09-15'))
            ->setStatut(StatutPret::VALIDE);
        $this->em->persist($pret);
        $this->em->flush();

        return $pret;
    }

    public function test_rappel_echeance_envoye_si_preference_active(): void
    {
        $pret = $this->pret(emailRappel: true);

        $resultat = $this->notifications->notifierRappelEcheance($pret);

        self::assertTrue($resultat);
        self::assertQueuedEmailCount(1);
    }

    public function test_rappel_echeance_supprime_si_preference_desactivee(): void
    {
        $pret = $this->pret(emailRappel: false);

        $resultat = $this->notifications->notifierRappelEcheance($pret);

        // Opt-out : aucun email, retour false (garde RGPD art. 6.1.b).
        self::assertFalse($resultat);
        self::assertQueuedEmailCount(0);
    }

    public function test_retard_toujours_envoye_meme_si_rappel_desactive(): void
    {
        // Meme avec emailRappel = false, le retard part (interet legitime art. 6.1.f).
        $pret = $this->pret(emailRappel: false);

        $this->notifications->notifierRetard($pret);

        self::assertQueuedEmailCount(1);
    }

    protected function tearDown(): void
    {
        $this->purger();
        parent::tearDown();
    }
}
