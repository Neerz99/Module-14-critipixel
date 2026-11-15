<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rating;

use App\Model\Entity\NumberOfRatingPerValue;
use App\Model\Entity\Review;
use App\Model\Entity\VideoGame;
use App\Rating\RatingHandler;
use PHPUnit\Framework\TestCase;

final class CountRatingsPerValueTest extends TestCase
{
    /**
     * @dataProvider provideVideoGames
     */
    public function testShouldCountRatingsPerValue(VideoGame $videoGame, NumberOfRatingPerValue $expected): void
    {
        $ratingHandler = new RatingHandler();

        $ratingHandler->countRatingsPerValue($videoGame);

        self::assertNumberOfRatingsPerValue($expected, $videoGame->getNumberOfRatingsPerValue());
    }

    public function testShouldNotAccumulateCountsWhenCalledSeveralTimes(): void
    {
        $videoGame = self::createVideoGame(1, 3, 3, 5);
        $ratingHandler = new RatingHandler();

        $ratingHandler->countRatingsPerValue($videoGame);
        $ratingHandler->countRatingsPerValue($videoGame);

        self::assertNumberOfRatingsPerValue(
            self::createExpected(one: 1, three: 2, five: 1),
            $videoGame->getNumberOfRatingsPerValue()
        );
    }

    public function testShouldResetCountsWhenVideoGameHasNoMoreReviews(): void
    {
        $videoGame = self::createVideoGame(2, 4);
        $ratingHandler = new RatingHandler();

        $ratingHandler->countRatingsPerValue($videoGame);
        $videoGame->getReviews()->clear();
        $ratingHandler->countRatingsPerValue($videoGame);

        self::assertNumberOfRatingsPerValue(self::createExpected(), $videoGame->getNumberOfRatingsPerValue());
    }

    /**
     * @return iterable<string, array{VideoGame, NumberOfRatingPerValue}>
     */
    public static function provideVideoGames(): iterable
    {
        yield 'no review' => [
            self::createVideoGame(),
            self::createExpected(),
        ];
        yield 'single review' => [
            self::createVideoGame(4),
            self::createExpected(four: 1),
        ];
        yield 'one review for each value' => [
            self::createVideoGame(1, 2, 3, 4, 5),
            self::createExpected(one: 1, two: 1, three: 1, four: 1, five: 1),
        ];
        yield 'identical ratings' => [
            self::createVideoGame(2, 2, 2),
            self::createExpected(two: 3),
        ];
        yield 'polarised ratings' => [
            self::createVideoGame(1, 5, 1, 5, 5),
            self::createExpected(one: 2, five: 3),
        ];
        yield 'many varied ratings' => [
            self::createVideoGame(5, 4, 4, 3, 5, 2, 4, 5, 1),
            self::createExpected(one: 1, two: 1, three: 1, four: 3, five: 3),
        ];
    }

    private static function assertNumberOfRatingsPerValue(
        NumberOfRatingPerValue $expected,
        NumberOfRatingPerValue $actual,
    ): void {
        self::assertSame($expected->getNumberOfOne(), $actual->getNumberOfOne(), 'Nombre de notes à 1');
        self::assertSame($expected->getNumberOfTwo(), $actual->getNumberOfTwo(), 'Nombre de notes à 2');
        self::assertSame($expected->getNumberOfThree(), $actual->getNumberOfThree(), 'Nombre de notes à 3');
        self::assertSame($expected->getNumberOfFour(), $actual->getNumberOfFour(), 'Nombre de notes à 4');
        self::assertSame($expected->getNumberOfFive(), $actual->getNumberOfFive(), 'Nombre de notes à 5');
    }

    private static function createVideoGame(int ...$ratings): VideoGame
    {
        $videoGame = new VideoGame();

        foreach ($ratings as $rating) {
            $videoGame->getReviews()->add((new Review())->setRating($rating));
        }

        return $videoGame;
    }

    private static function createExpected(
        int $one = 0,
        int $two = 0,
        int $three = 0,
        int $four = 0,
        int $five = 0,
    ): NumberOfRatingPerValue {
        $expected = new NumberOfRatingPerValue();

        foreach ([
            [$one, $expected->increaseOne(...)],
            [$two, $expected->increaseTwo(...)],
            [$three, $expected->increaseThree(...)],
            [$four, $expected->increaseFour(...)],
            [$five, $expected->increaseFive(...)],
        ] as [$count, $increase]) {
            for ($i = 0; $i < $count; ++$i) {
                $increase();
            }
        }

        return $expected;
    }
}
