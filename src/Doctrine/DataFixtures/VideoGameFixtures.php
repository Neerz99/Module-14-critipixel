<?php

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

use function array_fill_callback;

final class VideoGameFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Écarts appliqués à la note de la rédaction pour obtenir des notes d'utilisateurs
     * plausibles : la plupart proches de l'avis de la rédaction, quelques avis plus tranchés.
     */
    private const RATING_OFFSETS = [0, 1, -1, 0, 2, -2, 1, 0, -1];

    /**
     * Nombre maximum d'avis par jeu : un par utilisateur, à l'exception de user+0.
     */
    private const MAX_REVIEWS_PER_VIDEO_GAME = 9;

    /**
     * Commentaires de joueurs dont le ton correspond à la note attribuée,
     * pour qu'un avis à 1/5 ne soit jamais accompagné d'un commentaire élogieux.
     */
    private const COMMENTS_BY_TONE = [
        'negative' => [
            'Très déçu. Le jeu plante régulièrement et les temps de chargement sont interminables.',
            'Une histoire sans intérêt et une maniabilité rigide. Je n\'ai pas dépassé les premières heures.',
            'Beaucoup trop cher pour ce que c\'est : contenu répétitif et fin bâclée.',
            'Les promesses de la bande-annonce ne sont pas tenues. Graphismes datés et IA à la ramasse.',
            'Des bugs à chaque partie, des sauvegardes corrompues… À éviter tant qu\'il n\'y a pas de patch.',
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
        private readonly CountRatingsPerValue $countRatingsPerValue
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Indexation par e-mail : l'ordre retourné par findAll() n'est pas garanti.
        $users = [];
        foreach ($manager->getRepository(User::class)->findAll() as $user) {
            $users[$user->getEmail()] = $user;
        }

        $videoGames = array_fill_callback(0, 50, fn (int $index): VideoGame => (new VideoGame)
            ->setTitle(sprintf('Jeu vidéo %d', $index))
            ->setDescription($this->faker->paragraphs(10, true))
            ->setReleaseDate(new DateTimeImmutable())
            ->setTest($this->faker->paragraphs(6, true))
            ->setRating(($index % 5) + 1)
            ->setImageName(sprintf('video_game_%d.png', $index))
            ->setImageSize(2_098_872)
        );

        array_walk($videoGames, $this->addTags(...));

        array_walk($videoGames, [$manager, 'persist']);

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

    /**
     * Associe entre 1 et 4 tags distincts à chaque jeu. La répartition est déterministe
     * pour que les tests fonctionnels de filtrage par tag restent reproductibles.
     */
    private function addTags(VideoGame $videoGame, int $index): void
    {
        $numberOfTags = ($index % 4) + 1;
        $numberOfAvailableTags = count(TagFixtures::TAGS);

        for ($k = 0; $k < $numberOfTags; ++$k) {
            // Le pas de 5 étant premier avec le nombre de tags (14), les tags obtenus sont distincts.
            $tagIndex = ($index + $k * 5) % $numberOfAvailableTags;

            $videoGame->getTags()->add(
                $this->getReference(TagFixtures::getReferenceName($tagIndex), Tag::class)
            );
        }
    }

    /**
     * Crée entre 0 et 9 avis pour un jeu, chacun écrit par un utilisateur différent
     * (un utilisateur ne peut noter qu'une fois un même jeu, cf. VideoGameVoter).
     *
     * L'utilisateur user+0 ne rédige aucun avis afin de pouvoir être utilisé dans les
     * tests fonctionnels de dépôt d'avis sur n'importe quel jeu.
     *
     * @param array<string, User> $users
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
                // Un avis sur trois est une note seule, sans commentaire.
                ->setComment($k % 3 === 2 ? null : $this->createComment($rating));

            // La collection inverse doit être alimentée pour que le calcul des notes en tienne compte.
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
