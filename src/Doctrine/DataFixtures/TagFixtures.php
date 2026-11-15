<?php

declare(strict_types=1);

namespace App\Doctrine\DataFixtures;

use App\Model\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class TagFixtures extends Fixture
{
    /**
     * Genres et modes de jeu réalistes, tous inférieurs à 30 caractères (contrainte de Tag::$name).
     * L'ordre est important : VideoGameFixtures s'appuie sur les références "tag_<index>".
     */
    public const TAGS = [
        'Action',
        'Aventure',
        'RPG',
        'Stratégie',
        'Simulation',
        'Sport',
        'Course',
        'Plateforme',
        'FPS',
        'Horreur',
        'Puzzle',
        'Multijoueur',
        'Indépendant',
        'Monde ouvert',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::TAGS as $index => $name) {
            $tag = (new Tag())->setName($name);

            $manager->persist($tag);
            $this->addReference(self::getReferenceName($index), $tag);
        }

        $manager->flush();
    }

    public static function getReferenceName(int $index): string
    {
        return sprintf('tag_%d', $index);
    }
}
