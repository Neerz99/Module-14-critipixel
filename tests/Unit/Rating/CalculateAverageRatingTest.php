<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rating;

use App\Model\Entity\Review;
use App\Model\Entity\VideoGame;
use App\Rating\RatingHandler;
use PHPUnit\Framework\TestCase;

/**
 * Moyenne arrondie à l'entier supérieur, null sans avis.
 */
final class CalculateAverageRatingTest extends TestCase
{
    /**
     * @dataProvider provideVideoGames
     */
    public function testShouldCalculateAverageRating(VideoGame $videoGame, ?int $expectedAverageRating): void
    {
        $ratingHandler = new RatingHandler();

        $ratingHandler->calculateAverage($videoGame);

        self::assertSame($expectedAverageRating, $videoGame->getAverageRating());
    }

    public function testShouldResetAverageRatingWhenVideoGameHasNoMoreReviews(): void
    {
        $videoGame = (new VideoGame())->setAverageRating(4);

        (new RatingHandler())->calculateAverage($videoGame);

        self::assertNull($videoGame->getAverageRating());
    }

    /**
     * @return iterable<string, array{VideoGame, ?int}>
     */
    public static function provideVideoGames(): iterable
    {
        yield 'no review' => [self::createVideoGame(), null];
        yield 'single review' => [self::createVideoGame(3), 3];
        yield 'identical ratings' => [self::createVideoGame(4, 4, 4), 4];
        yield 'exact integer average' => [self::createVideoGame(1, 2, 3, 4, 5), 3];
        yield 'decimal average rounded up' => [self::createVideoGame(1, 2), 2];
        yield 'low decimal still rounded up' => [self::createVideoGame(3, 3, 4), 4];
        yield 'all minimum ratings' => [self::createVideoGame(1, 1, 1), 1];
        yield 'all maximum ratings' => [self::createVideoGame(5, 5, 5, 5), 5];
        yield 'polarised ratings' => [self::createVideoGame(1, 5, 1, 5), 3];
        yield 'many varied ratings' => [self::createVideoGame(5, 4, 4, 3, 5, 2, 4, 5, 1), 4];
    }

    private static function createVideoGame(int ...$ratings): VideoGame
    {
        $videoGame = new VideoGame();

        foreach ($ratings as $rating) {
            $videoGame->getReviews()->add((new Review())->setRating($rating));
        }

        return $videoGame;
    }
}
