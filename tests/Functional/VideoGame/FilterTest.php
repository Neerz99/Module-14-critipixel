<?php

declare(strict_types=1);

namespace App\Tests\Functional\VideoGame;

use App\Model\Entity\Tag;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class FilterTest extends FunctionalTestCase
{
    private const TOTAL_VIDEO_GAMES = 50;

    public function testShouldListTenVideoGames(): void
    {
        $this->get('/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'article.game-card');
        $this->client->clickLink('2');
        self::assertResponseIsSuccessful();
    }

    public function testShouldFilterVideoGamesBySearch(): void
    {
        $this->get('/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'article.game-card');
        $this->client->submitForm('Filtrer', ['filter[search]' => 'Jeu vidéo 49'], 'GET');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'article.game-card');
    }

    /**
     * @dataProvider provideTags
     *
     * @param string[] $tagNames
     * @param int[]    $expectedVideoGames numéros des jeux attendus ("Jeu vidéo N")
     */
    public function testShouldFilterVideoGamesByTags(array $tagNames, array $expectedVideoGames): void
    {
        $crawler = $this->get('/', ['limit' => self::TOTAL_VIDEO_GAMES]);
        self::assertResponseIsSuccessful();

        $tagIds = $this->getTagIds($tagNames);

        $form = $crawler->selectButton('Filtrer')->form();
        /** @var \Symfony\Component\DomCrawler\Field\ChoiceFormField[] $checkboxes */
        $checkboxes = $form['filter[tags]'];
        foreach ($checkboxes as $checkbox) {
            if (\in_array($checkbox->availableOptionValues()[0], $tagIds, true)) {
                $checkbox->tick();
            }
        }

        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();

        $expectedTitles = array_map(static fn (int $number): string => sprintf('Jeu vidéo %d', $number), $expectedVideoGames);

        self::assertEqualsCanonicalizing($expectedTitles, $this->getDisplayedTitles($crawler));
        self::assertSelectorTextContains('div.fw-bold', sprintf('sur les %d jeux vidéo', \count($expectedVideoGames)));

        $crawler->filter('article.game-card')->each(static function (Crawler $card) use ($tagNames): void {
            $cardTags = $card->filter('.game-card-tags .tag')->each(static fn (Crawler $tag): string => $tag->text());
            foreach ($tagNames as $tagName) {
                self::assertContains($tagName, $cardTags);
            }
        });
    }

    /**
     * Valeurs issues de VideoGameFixtures::addTags().
     *
     * @return iterable<string, array{string[], int[]}>
     */
    public static function provideTags(): iterable
    {
        yield 'aucun tag' => [[], range(0, self::TOTAL_VIDEO_GAMES - 1)];
        yield 'un tag : Action' => [['Action'], [0, 9, 14, 18, 23, 27, 28, 37, 42, 46]];
        yield 'un tag : Monde ouvert' => [['Monde ouvert'], [3, 13, 22, 27, 31, 41]];
        yield 'un tag sur plusieurs pages : RPG' => [['RPG'], [2, 6, 11, 15, 16, 25, 30, 34, 39, 43, 44]];
        yield 'deux tags : Aventure et Course' => [['Aventure', 'Course'], [1, 10, 15, 19, 29, 38, 43, 47]];
        yield 'deux tags : Action et Sport' => [['Action', 'Sport'], [14, 23, 42]];
        yield 'trois tags : Action, Sport et Puzzle' => [['Action', 'Sport', 'Puzzle'], [14, 23, 42]];
        yield 'deux tags sans jeu commun' => [['Action', 'Aventure'], []];
        yield 'quatre tags sans jeu commun' => [['RPG', 'Course', 'Multijoueur', 'Plateforme'], []];
    }

    public function testShouldPaginateVideoGamesFilteredByTag(): void
    {
        $crawler = $this->get('/', ['filter' => ['tags' => $this->getTagIds(['RPG'])]]);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(10, 'article.game-card');
        self::assertSelectorTextContains('div.fw-bold', 'sur les 11 jeux vidéo');

        // "Jeu vidéo 2" est dans la liste, on cible donc le lien de la pagination
        $this->client->click($crawler->filter('.pagination')->selectLink('2')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'article.game-card');
    }

    public function testShouldCombineSearchAndTags(): void
    {
        $this->get('/', [
            'limit' => self::TOTAL_VIDEO_GAMES,
            'filter' => [
                'search' => 'Jeu vidéo 2',
                'tags' => $this->getTagIds(['Action']),
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(
            ['Jeu vidéo 23', 'Jeu vidéo 27', 'Jeu vidéo 28'],
            $this->getDisplayedTitles($this->client->getCrawler())
        );
    }

    /**
     * @dataProvider provideUnknownTags
     *
     * @param string[] $tags
     */
    public function testShouldIgnoreUnknownTag(array $tags): void
    {
        $crawler = $this->get('/', [
            'limit' => self::TOTAL_VIDEO_GAMES,
            'filter' => ['tags' => $tags],
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(self::TOTAL_VIDEO_GAMES, $this->getDisplayedTitles($crawler));
        self::assertSelectorTextContains('div.fw-bold', sprintf('sur les %d jeux vidéo', self::TOTAL_VIDEO_GAMES));
    }

    /**
     * @return iterable<string, array{string[]}>
     */
    public static function provideUnknownTags(): iterable
    {
        yield 'identifiant inexistant' => [['999999']];
        yield 'identifiant non numérique' => [['abc']];
    }

    /**
     * @param string[] $tagNames
     *
     * @return string[]
     */
    private function getTagIds(array $tagNames): array
    {
        $repository = $this->getEntityManager()->getRepository(Tag::class);

        return array_map(static function (string $name) use ($repository): string {
            $tag = $repository->findOneBy(['name' => $name]);
            self::assertInstanceOf(Tag::class, $tag, sprintf('Le tag "%s" est absent des fixtures.', $name));

            return (string) $tag->getId();
        }, $tagNames);
    }

    /**
     * @return string[]
     */
    private function getDisplayedTitles(Crawler $crawler): array
    {
        return $crawler->filter('article.game-card .game-card-title')->each(
            static fn (Crawler $title): string => trim($title->text())
        );
    }
}
