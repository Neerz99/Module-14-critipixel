<?php

declare(strict_types=1);

namespace App\Tests\Functional\VideoGame;

use App\Model\Entity\Review;
use App\Model\Entity\User;
use App\Model\Entity\VideoGame;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;

final class ShowTest extends FunctionalTestCase
{
    // Une seule note dans les fixtures : 2/5 par user+2
    private const VIDEO_GAME_SLUG = 'jeu-video-1';

    public function testShouldShowVideoGame(): void
    {
        $this->get('/jeu-video-0');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Jeu vidéo 0');
    }

    /**
     * @dataProvider provideValidReviews
     */
    public function testShouldPostReview(int $rating, ?string $comment): void
    {
        $this->login();

        $this->get('/'.self::VIDEO_GAME_SLUG);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="review"]');

        $this->client->submitForm('Poster', [
            'review[rating]' => (string) $rating,
            'review[comment]' => $comment ?? '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertResponseRedirects('/'.self::VIDEO_GAME_SLUG);

        $review = $this->findReview('user+0@email.com');
        self::assertNotNull($review);
        self::assertSame($rating, $review->getRating());
        self::assertSame($comment, $review->getComment());

        $videoGame = $review->getVideoGame();
        self::assertCount(2, $videoGame->getReviews());
        self::assertSame((int) ceil((2 + $rating) / 2), $videoGame->getAverageRating());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="review"]');
    }

    /**
     * @return iterable<string, array{int, ?string}>
     */
    public static function provideValidReviews(): iterable
    {
        yield 'note et commentaire' => [4, 'Très bon jeu, je recommande.'];
        yield 'note seule' => [1, null];
        yield 'commentaire de longueur maximale' => [5, str_repeat('a', Review::COMMENT_MAX_LENGTH)];
    }

    public function testShouldRejectReviewWithoutRating(): void
    {
        $this->login();

        $crawler = $this->get('/'.self::VIDEO_GAME_SLUG);
        $form = $crawler->selectButton('Poster')->form();
        $this->getRatingField($form)->disableValidation()->setValue('');
        $form['review[comment]']->setValue('Un commentaire sans note.');

        $this->client->submit($form);

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('form[name="review"]', 'La note est obligatoire.');
        self::assertNull($this->findReview('user+0@email.com'));
    }

    /**
     * @dataProvider provideInvalidRatings
     */
    public function testShouldRejectReviewWithInvalidRating(string $rating): void
    {
        $this->login();

        $crawler = $this->get('/'.self::VIDEO_GAME_SLUG);
        $form = $crawler->selectButton('Poster')->form();
        $this->getRatingField($form)->disableValidation()->setValue($rating);

        $this->client->submit($form);

        self::assertResponseIsUnprocessable();
        self::assertNull($this->findReview('user+0@email.com'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidRatings(): iterable
    {
        yield 'note nulle' => ['0'];
        yield 'note trop haute' => ['6'];
        yield 'note non numérique' => ['abc'];
    }

    public function testShouldRejectTooLongComment(): void
    {
        $this->login();

        $this->get('/'.self::VIDEO_GAME_SLUG);

        $this->client->submitForm('Poster', [
            'review[rating]' => '3',
            'review[comment]' => str_repeat('a', Review::COMMENT_MAX_LENGTH + 1),
        ]);

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('form[name="review"]', 'Le commentaire ne doit pas dépasser');
        self::assertNull($this->findReview('user+0@email.com'));
    }

    public function testShouldNotShowReviewFormToAnonymousUser(): void
    {
        $this->get('/'.self::VIDEO_GAME_SLUG);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="review"]');
    }

    public function testShouldReturnUnauthorizedWhenAnonymousUserPostsReview(): void
    {
        $this->client->request('POST', '/'.self::VIDEO_GAME_SLUG, [
            'review' => ['rating' => '4', 'comment' => 'Avis anonyme'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertCount(1, $this->getVideoGame()->getReviews());
    }

    public function testShouldNotShowReviewFormToUserWhoAlreadyReviewed(): void
    {
        $this->login('user+2@email.com');

        $this->get('/'.self::VIDEO_GAME_SLUG);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="review"]');
    }

    public function testShouldForbidSecondReviewFromSameUser(): void
    {
        $this->login('user+2@email.com');

        $this->client->request('POST', '/'.self::VIDEO_GAME_SLUG, [
            'review' => ['rating' => '5'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(1, $this->getVideoGame()->getReviews());
    }

    private function getRatingField(Form $form): ChoiceFormField
    {
        $field = $form->get('review[rating]');
        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }

    private function getVideoGame(): VideoGame
    {
        $videoGame = $this->getEntityManager()
            ->getRepository(VideoGame::class)
            ->findOneBy(['slug' => self::VIDEO_GAME_SLUG]);

        self::assertInstanceOf(VideoGame::class, $videoGame);

        return $videoGame;
    }

    private function findReview(string $email): ?Review
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $entityManager->getRepository(Review::class)->findOneBy([
            'user' => $user,
            'videoGame' => $this->getVideoGame(),
        ]);
    }
}
