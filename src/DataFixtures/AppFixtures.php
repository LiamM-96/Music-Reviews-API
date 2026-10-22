<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Genre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setDisplayName('Admin User');
        $admin->setRoles(['ROLE_ADMIN']);

        $adminPassword = $this->passwordHasher->hashPassword($admin, 'password');
        $admin->setPassword($adminPassword);
        $manager->persist($admin);

        // Regular user
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setDisplayName('Regular User');
        $user->setRoles(['ROLE_USER']);

        $userPassword = $this->passwordHasher->hashPassword($user, 'password');
        $user->setPassword($userPassword);
        $manager->persist($user);

        // Genres
        $genres = [
            ['name' => 'Rock',       'slug' => 'rock'],
            ['name' => 'Pop',        'slug' => 'pop'],
            ['name' => 'Classical',  'slug' => 'classical'],
            ['name' => 'Jazz',       'slug' => 'jazz'],
            ['name' => 'Hip-Hop',    'slug' => 'hip-hop'],
            ['name' => 'Electronic', 'slug' => 'electronic'],
            ['name' => 'Metal',      'slug' => 'metal'],
        ];

        foreach ($genres as $g) {
            $genre = new Genre();
            $genre->setName($g['name']);
            $genre->setSlug($g['slug']);
            $manager->persist($genre);
        }

        $manager->flush();
    }
}

