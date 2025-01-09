<?php

namespace App\DataFixtures;

use App\Entity\Paper;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher){
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);




        $user = new User();

        $plaintextPassword = 'plaintextPassword';

        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plaintextPassword
        );



        $user
            ->setLangueid('en')
            ->setScreenName('djamel')
            ->setUsername('djamel')
            ->setEmail('djamel@episciences.org')
            ->setLastName('djamel')
            ->setValid(1);


        $user->setPassword($hashedPassword);




        $paper = new Paper();

        $paper
            ->setRvid(2)
            ->setUid(2)
            ->setStatus(7)
            ->setIdentifier('hal-00001858')
            ->setRepoid(1)
            ->setRecord('record')->setWhen(new \DateTime())->setSubmissionDate(new \DateTime());

        $manager->persist($user);
        //$manager->persist($paper);


        $manager->flush();
    }
}
