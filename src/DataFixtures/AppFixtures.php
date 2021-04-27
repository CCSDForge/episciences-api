<?php

namespace App\DataFixtures;

use App\Entity\Main\PaperRatingGrid;
use App\Entity\Main\Papers;
use App\Entity\Main\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder){
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        // $product = new Product();
        // $manager->persist($product);




        $user = new User();

        $user
            ->setLangueid('en')
            ->setScreenName('djamel')
            ->setUsername('djamel')
            ->setEmail('djamel@episciences.org')
            ->setLastName('djamel')
            ->setValid(1)
            ->setPassword($this->encoder->encodePassword($user, 'admin'));

        $paper = new Papers();

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
