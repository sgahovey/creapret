<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\TypeActionJournal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de l'administration des comptes (US-6.2, BF-13). Reservee au super-administrateur.
 * Couvre l'acces, la recherche, la creation (sans consentement), l'unicite de la trace de journal, et
 * les gardes anti-verrouillage / anti-soi de la bascule d'activation.
 */
final class CompteControllerTest extends WebTestCase
{
    private const EMAIL_MARQUEUR = 'comptetest.';
    private const NOM_MARQUEUR = 'ZZCompteTest';
    private const MDP = 'Motdepasse1!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    /** @var list<int> super-admins neutralises pour l'anti-verrouillage, a reactiver en tearDown */
    private array $aReactiver = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purger();
    }

    protected function tearDown(): void
    {
        if ([] !== $this->aReactiver) {
            $this->em->createQuery('UPDATE App\Entity\Utilisateur u SET u.estActif = true WHERE u.id IN (:ids)')
                ->setParameter('ids', $this->aReactiver)->execute();
            $this->aReactiver = [];
        }
        $this->purger();
        parent::tearDown();
    }

    private function purger(): void
    {
        // Journal : append-only sans marqueur -> on borne par le libelle acteur/cible (contient le nom marqueur).
        $this->em->getConnection()->executeStatement(
            'DELETE FROM journal_admin WHERE acteur_libelle LIKE :m OR cible_libelle LIKE :m',
            ['m' => '%' . self::NOM_MARQUEUR . '%'],
        );
        $this->em->createQuery('DELETE FROM App\Entity\Utilisateur u WHERE u.email LIKE :p')
            ->setParameter('p', self::EMAIL_MARQUEUR . '%')->execute();
    }

    private function creer(Role $role, bool $actif = true, string $prenom = 'Cible'): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $u = (new Utilisateur())
            ->setEmail(self::EMAIL_MARQUEUR . uniqid() . '@creapret.local')
            ->setNom(self::NOM_MARQUEUR)
            ->setPrenom($prenom)
            ->setRole($role)
            ->setEstActif($actif);
        $u->setMotDePasseHash($hasher->hashPassword($u, self::MDP));
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function connecterSuperAdmin(): Utilisateur
    {
        $admin = $this->creer(Role::SUPER_ADMIN, true, 'Admin');
        $this->client->loginUser($admin);

        return $admin;
    }

    /**
     * POST de bascule d'activation avec un jeton CSRF valide. Le bouton etant masque sur les lignes
     * « self » (anti-soi), on injecte le jeton dans la session (mock_file en test), ce qui reproduit
     * exactement ce que soumettrait un formulaire legitime.
     */
    private function basculerActivation(int $id, string $jeton = 'jeton-test-activation'): void
    {
        $this->client->request('GET', '/admin/comptes');
        $session = $this->client->getRequest()->getSession();
        $session->set('_csrf/activation' . $id, $jeton);
        $session->save();
        $this->client->request('POST', '/admin/comptes/' . $id . '/activation', ['_token' => $jeton]);
    }

    private function rafraichir(int $id): Utilisateur
    {
        $this->em->clear();
        $frais = $this->em->getRepository(Utilisateur::class)->find($id);
        self::assertInstanceOf(Utilisateur::class, $frais);

        return $frais;
    }

    private function compterJournal(TypeActionJournal $type, int $cibleId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM journal_admin WHERE type_action = :t AND cible_id = :c',
            ['t' => $type->value, 'c' => $cibleId],
        );
    }

    public function test_gestionnaire_recoit_403_sur_la_liste(): void
    {
        $this->client->loginUser($this->creer(Role::GESTIONNAIRE, true, 'Gest'));

        $this->client->request('GET', '/admin/comptes');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_liste_et_recherche_filtre(): void
    {
        $this->connecterSuperAdmin();
        $alpha = $this->creer(Role::EMPRUNTEUR, true, 'Rechercablealpha');
        $beta = $this->creer(Role::EMPRUNTEUR, true, 'Rechercablebeta');

        $this->client->request('GET', '/admin/comptes?recherche=Rechercablealpha');

        self::assertResponseIsSuccessful();
        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($alpha->getEmail(), $contenu);
        self::assertStringNotContainsString($beta->getEmail(), $contenu);
    }

    public function test_creation_compte_sans_consentement_et_trace(): void
    {
        $this->connecterSuperAdmin();
        $email = self::EMAIL_MARQUEUR . uniqid() . '@creapret.local';

        $crawler = $this->client->request('GET', '/admin/comptes/nouveau');
        $form = $crawler->selectButton('Créer le compte')->form();
        $form['utilisateur_admin[email]'] = $email;
        $form['utilisateur_admin[prenom]'] = 'Nouveau';
        $form['utilisateur_admin[nom]'] = self::NOM_MARQUEUR;
        $form['utilisateur_admin[role]'] = Role::EMPRUNTEUR->value;
        $form['utilisateur_admin[plainPassword][first]'] = self::MDP;
        $form['utilisateur_admin[plainPassword][second]'] = self::MDP;
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/comptes');

        $this->em->clear();
        $compte = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Utilisateur::class, $compte);

        // Mot de passe utilisable (hash non vide + verification), consentement NUL (compte cree par un tiers).
        self::assertNotSame('', $compte->getPassword());
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($compte, self::MDP));
        self::assertNull($compte->getDateConsentement());
        self::assertNull($compte->getVersionCgu());

        self::assertSame(1, $this->compterJournal(TypeActionJournal::COMPTE_CREATION, (int) $compte->getId()));
    }

    public function test_modification_simple_une_seule_trace(): void
    {
        $this->connecterSuperAdmin();
        $compte = $this->creer(Role::EMPRUNTEUR, true, 'AvantModif');
        $id = (int) $compte->getId();

        $crawler = $this->client->request('GET', '/admin/comptes/' . $id . '/modifier');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['utilisateur_admin[prenom]'] = 'ApresModif';
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/comptes');
        self::assertSame(1, $this->compterJournal(TypeActionJournal::COMPTE_MODIFICATION, $id));
        self::assertSame(0, $this->compterJournal(TypeActionJournal::COMPTE_CHANGEMENT_ROLE, $id));
    }

    public function test_changement_de_role_une_seule_trace(): void
    {
        $this->connecterSuperAdmin();
        $compte = $this->creer(Role::EMPRUNTEUR, true, 'AvantRole');
        $id = (int) $compte->getId();

        $crawler = $this->client->request('GET', '/admin/comptes/' . $id . '/modifier');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['utilisateur_admin[role]'] = Role::GESTIONNAIRE->value;
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/comptes');
        self::assertSame(1, $this->compterJournal(TypeActionJournal::COMPTE_CHANGEMENT_ROLE, $id));
        self::assertSame(0, $this->compterJournal(TypeActionJournal::COMPTE_MODIFICATION, $id));
        self::assertSame(Role::GESTIONNAIRE, $this->rafraichir($id)->getRole());
    }

    public function test_anti_verrouillage_dernier_super_admin_actif(): void
    {
        $admin = $this->connecterSuperAdmin();
        $id = (int) $admin->getId();

        // Neutraliser tout autre super-admin actif pour que $admin soit le dernier (etat restaure en tearDown).
        /** @var list<int> $autres */
        $autres = array_map('intval', $this->em->createQuery(
            'SELECT u.id FROM App\Entity\Utilisateur u WHERE u.role = :r AND u.estActif = true AND u.id != :id',
        )->setParameter('r', Role::SUPER_ADMIN->value)->setParameter('id', $id)->getSingleColumnResult());
        if ([] !== $autres) {
            $this->em->createQuery('UPDATE App\Entity\Utilisateur u SET u.estActif = false WHERE u.id IN (:ids)')
                ->setParameter('ids', $autres)->execute();
            $this->aReactiver = $autres;
        }

        $this->basculerActivation($id);

        // Refus par l'invariant global : le compte reste actif, message metier affiche.
        self::assertTrue($this->rafraichir($id)->isEstActif());
        $this->client->followRedirect();
        self::assertStringContainsString(
            'dernier super-administrateur actif',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function test_anti_soi_desactivation_refusee(): void
    {
        $admin = $this->connecterSuperAdmin();
        // Un 2e super-admin actif : l'invariant global passe, seule la garde anti-soi (Voter) doit jouer.
        $this->creer(Role::SUPER_ADMIN, true, 'Autre');
        $id = (int) $admin->getId();

        $this->basculerActivation($id);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->rafraichir($id)->isEstActif());
    }

    public function test_activation_compte_inactif(): void
    {
        $this->connecterSuperAdmin();
        $compte = $this->creer(Role::EMPRUNTEUR, false, 'Inactif');
        $id = (int) $compte->getId();

        $this->basculerActivation($id);

        self::assertResponseRedirects('/admin/comptes');
        self::assertTrue($this->rafraichir($id)->isEstActif());
        self::assertSame(1, $this->compterJournal(TypeActionJournal::COMPTE_ACTIVATION, $id));
    }

    public function test_csrf_invalide_ne_change_rien(): void
    {
        $this->connecterSuperAdmin();
        $compte = $this->creer(Role::EMPRUNTEUR, true, 'CsrfCible');
        $id = (int) $compte->getId();

        $this->client->request('POST', '/admin/comptes/' . $id . '/activation', ['_token' => 'faux']);

        // Jeton invalide : aucune bascule, le compte reste actif, aucune trace de desactivation.
        self::assertTrue($this->rafraichir($id)->isEstActif());
        self::assertSame(0, $this->compterJournal(TypeActionJournal::COMPTE_DESACTIVATION, $id));
    }
}
