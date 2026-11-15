<?php

declare(strict_types=1);

namespace App\Doctrine\DataFixtures;

use App\Model\Entity\Review;
use App\Model\Entity\Tag;
use App\Model\Entity\User;
use App\Model\Entity\VideoGame;
use App\Rating\CalculateAverageRating;
use App\Rating\CountRatingsPerValue;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Generator;

final class VideoGameFixtures extends Fixture implements DependentFixtureInterface
{
    // Écart entre la note de l'utilisateur et celle de la rédaction
    private const RATING_OFFSETS = [0, 1, -1, 0, 2, -2, 1, 0, -1];

    // 1 avis max par utilisateur, user+0 n'en écrit aucun
    private const MAX_REVIEWS_PER_VIDEO_GAME = 9;

    private const COMMENTS_BY_TONE = [
        'negative' => [
            'Très déçu. Le jeu plante régulièrement et les temps de chargement sont interminables.',
            'Une histoire sans intérêt et une maniabilité rigide. Je n\'ai pas dépassé les premières heures.',
            'Beaucoup trop cher pour ce que c\'est : contenu répétitif et fin bâclée.',
            'Les promesses de la bande-annonce ne sont pas tenues. Graphismes datés et IA à la ramasse.',
            'Des bugs à chaque partie, des sauvegardes corrompues... À éviter tant qu\'il n\'y a pas de patch.',
        ],
        'mixed' => [
            'Pas mauvais, mais sans surprise. Les fans du genre y trouveront leur compte.',
            'Bonne direction artistique, mais le rythme s\'essouffle sérieusement en milieu de partie.',
            'Sympa à plusieurs, beaucoup moins intéressant en solo.',
            'Quelques bonnes idées gâchées par une difficulté mal dosée.',
            'Correct pour passer le temps, à prendre en promotion plutôt qu\'au prix fort.',
        ],
        'positive' => [
            'Excellent ! Une ambiance incroyable et une bande-son qui reste en tête.',
            'Gameplay nerveux et très bien pensé, je n\'ai pas lâché la manette de la soirée.',
            'Une vraie claque visuelle, et l\'histoire m\'a tenu en haleine jusqu\'au bout.',
            'Une durée de vie énorme et du contenu de qualité. Il vaut largement son prix.',
            'Facile à prendre en main, difficile à maîtriser : exactement ce que j\'attendais.',
            'Mon coup de cœur de l\'année, je le recommande les yeux fermés.',
        ],
    ];

    public function __construct(
        private readonly Generator $faker,
        private readonly CalculateAverageRating $calculateAverageRating,
        private readonly CountRatingsPerValue $countRatingsPerValue,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        foreach ($manager->getRepository(User::class)->findAll() as $user) {
            $users[$user->getEmail()] = $user;
        }

        $videoGames = \array_fill_callback(0, 50, fn (int $index): VideoGame => (new VideoGame())
            ->setTitle(sprintf('Jeu vidéo %d', $index))
            ->setDescription($this->faker->paragraphs(10, true))
            ->setReleaseDate(new DateTimeImmutable())
            ->setTest($this->faker->paragraphs(6, true))
            ->setRating(($index % 5) + 1)
            ->setImageName(sprintf('video_game_%d.png', $index))
            ->setImageSize(2_098_872)
        );

        foreach ($videoGames as $index => $videoGame) {
            $this->addTags($videoGame, $index);
            $manager->persist($videoGame);
        }

        $manager->flush();

        foreach ($videoGames as $index => $videoGame) {
            foreach ($this->createReviews($videoGame, $index, $users) as $review) {
                $manager->persist($review);
            }

            $this->calculateAverageRating->calculateAverage($videoGame);
            $this->countRatingsPerValue->countRatingsPerValue($videoGame);
        }

        $manager->flush();
    }

    // Répartition fixe (1 à 4 tags par jeu) pour garder des tests de filtrage reproductibles
    private function addTags(VideoGame $videoGame, int $index): void
    {
        $numberOfTags = ($index % 4) + 1;
        $numberOfAvailableTags = count(TagFixtures::TAGS);

        for ($k = 0; $k < $numberOfTags; ++$k) {
            $tagIndex = ($index + $k * 5) % $numberOfAvailableTags;

            $videoGame->getTags()->add(
                $this->getReference(TagFixtures::getReferenceName($tagIndex), Tag::class)
            );
        }
    }

    /**
     * @param array<string, User> $users
     *
     * @return Review[]
     */
    private function createReviews(VideoGame $videoGame, int $index, array $users): array
    {
        $numberOfReviews = $index % (self::MAX_REVIEWS_PER_VIDEO_GAME + 1);
        $reviews = [];

        for ($k = 0; $k < $numberOfReviews; ++$k) {
            $userIndex = (($index + $k) % self::MAX_REVIEWS_PER_VIDEO_GAME) + 1;
            $rating = max(1, min(5, $videoGame->getRating() + self::RATING_OFFSETS[$k % count(self::RATING_OFFSETS)]));

            $review = (new Review())
                ->setVideoGame($videoGame)
                ->setUser($users[sprintf('user+%d@email.com', $userIndex)])
                ->setRating($rating)
                ->setComment($k % 3 === 2 ? null : $this->createComment($rating));

            $videoGame->getReviews()->add($review);
            $reviews[] = $review;
        }

        return $reviews;
    }

    private function createComment(int $rating): string
    {
        $tone = match (true) {
            $rating <= 2 => 'negative',
            $rating === 3 => 'mixed',
            default => 'positive',
        };

        return $this->faker->randomElement(self::COMMENTS_BY_TONE[$tone]);
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, TagFixtures::class];
    }
}
