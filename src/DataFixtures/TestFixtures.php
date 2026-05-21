<?php

/**
 * Fixtures de test: crea los usuarios base del sistema (superadmin, admin+audit, user).
 */

namespace App\DataFixtures;

use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new Usuario();
        $user->setUsername('testuser');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'testpass'));
        $user->setRoles(['ROLE_ADMIN', 'ROLE_AUDIT']);
        $user->setNombre('Test');
        $user->setApellido('User');
        $user->setEmail('test@test.com');
        $user->setFechaAlta();
        $manager->persist($user);

        $superAdmin = new Usuario();
        $superAdmin->setUsername('superadmin');
        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'superpass'));
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);
        $superAdmin->setNombre('Super');
        $superAdmin->setApellido('Admin');
        $superAdmin->setEmail('superadmin@test.com');
        $superAdmin->setFechaAlta();
        $manager->persist($superAdmin);

        $targetUser = new Usuario();
        $targetUser->setUsername('targetuser');
        $targetUser->setPassword($this->passwordHasher->hashPassword($targetUser, 'targetpass'));
        $targetUser->setRoles(['ROLE_USER']);
        $targetUser->setNombre('Target');
        $targetUser->setApellido('User');
        $targetUser->setEmail('target@test.com');
        $targetUser->setFechaAlta();
        $manager->persist($targetUser);

        $manager->flush();
    }
}
