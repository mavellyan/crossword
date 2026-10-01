<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Enums\ValidationErrors;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswordTopicConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Clue $hello;
    private Clue $world;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->hello = Clue::factory()->create([
            'definition' => 'Angol köszönés',
            'solution' => 'HELLO',
        ]);

        $this->world = Clue::factory()->create([
            'definition' => 'Világ angolul',
            'solution' => 'WORLD',
        ]);
    }

    /**
     * Létrehoz egy témát a megadott névvel.
     */
    private function createTopic(string $name): Topic
    {
        return Topic::query()->create([
            'name' => $name,
        ]);
    }

    /**
     * Visszaad egy érvényes, egymást metsző elrendezést.
     */
    private function validEntries(): array
    {
        return [
            [
                'clue_id' => $this->hello->id,
                'direction' => Direction::HORIZONTAL,
                'start_row' => 1,
                'start_col' => 0,
            ],
            [
                'clue_id' => $this->world->id,
                'direction' => Direction::VERTICAL,
                'start_row' => 0,
                'start_col' => 4,
            ],
        ];
    }

    /**
     * Létrehoz egy privát rejtvényt közvetlenül az adatbázisban.
     */
    private function createPrivateCrossword(string $title = 'Eredeti rejtvény'): Crossword
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->user->id,
            'title' => $title,
            'main_solution' => null,
            'is_public' => false,
        ]);

        foreach ($this->validEntries() as $entry) {
            $crossword->crosswordClues()->create([
                ...$entry,
            ]);
        }

        return $crossword;
    }

    /**
     * Ellenőrzi, hogy a közös témával rendelkező clue-k elfogadásra kerülnek.
     *
     * @test
     */
    public function testCluesSharingCrosswordTopicAreAccepted(): void
    {
        $topic = $this->createTopic('Angol');

        $this->hello->topics()->attach($topic->id);
        $this->world->topics()->attach($topic->id);

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Angol rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [$topic->id],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $crossword = Crossword::query()->findOrFail(
            $response->json('crossword.id')
        );

        $this->assertSame(
            [$topic->id],
            $crossword->topics()->pluck('topics.id')->all(),
        );
    }

    /**
     * Ellenőrzi, hogy a kiválasztott témák egyikéhez sem tartozó clue elutasításra kerül.
     *
     * @test
     */
    public function testClueWithoutSelectedTopicReturns422(): void
    {
        $topic = $this->createTopic('Angol');

        $this->hello->topics()->attach($topic->id);
        // A WORLD clue szándékosan nem kap témát.

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Hibás témájú rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [$topic->id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.0.code',
                ValidationErrors::CLUE_TOPIC_MISMATCH->value,
            )
            ->assertJsonPath('errors.0.clue_ids.0', $this->world->id);

        $this->assertDatabaseMissing('crosswords', [
            'title' => 'Hibás témájú rejtvény',
        ]);
    }

    /**
     * Ellenőrzi, hogy egy többtémás clue elfogadható, ha legalább egy kiválasztott témával egyezik.
     *
     * @test
     */
    public function testMultiTopicClueSharingAtLeastOneTopicIsAccepted(): void
    {
        $languageTopic = $this->createTopic('Nyelvek');
        $geographyTopic = $this->createTopic('Földrajz');
        $unselectedTopic = $this->createTopic('Egyéb');

        $this->hello->topics()->attach([
            $languageTopic->id,
            $unselectedTopic->id,
        ]);

        $this->world->topics()->attach([
            $geographyTopic->id,
            $unselectedTopic->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Többtémás rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [
                    $languageTopic->id,
                    $geographyTopic->id,
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $crossword = Crossword::query()->findOrFail(
            $response->json('crossword.id')
        );

        $actualTopicIds = $crossword->topics()
            ->pluck('topics.id')
            ->sort()
            ->values()
            ->all();

        $expectedTopicIds = [
            $languageTopic->id,
            $geographyTopic->id,
        ];

        sort($expectedTopicIds);

        $this->assertSame($expectedTopicIds, $actualTopicIds);
    }

    /**
     * Ellenőrzi, hogy téma nélküli rejtvénynél nincs clue-témakorlátozás.
     *
     * @test
     */
    public function testCrosswordWithoutTopicsIsAccepted(): void
    {
        $unrelatedTopic = $this->createTopic('Nem kiválasztott téma');

        $this->hello->topics()->attach($unrelatedTopic->id);
        // A WORLD clue témamentes marad.

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Téma nélküli rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $crossword = Crossword::query()->findOrFail(
            $response->json('crossword.id')
        );

        $this->assertSame(
            [],
            $crossword->topics()->pluck('topics.id')->all(),
        );
    }

    /**
     * Ellenőrzi, hogy a hibás frissítés nem módosítja a korábbi bejegyzéseket és témákat.
     *
     * @test
     */
    public function testFailedUpdatePreservesOldEntriesAndTopics(): void
    {
        $oldTopic = $this->createTopic('Régi téma');
        $newTopic = $this->createTopic('Új téma');

        $this->hello->topics()->attach([
            $oldTopic->id,
            $newTopic->id,
        ]);

        $this->world->topics()->attach($oldTopic->id);

        $crossword = $this->createPrivateCrossword();
        $crossword->topics()->attach($oldTopic->id);

        $oldEntries = $crossword->crosswordClues()
            ->orderBy('id')
            ->get([
                'id',
                'clue_id',
                'direction',
                'start_row',
                'start_col',
            ])
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'clue_id' => $entry->clue_id,
                'direction' => $entry->getDirection(),
                'start_row' => $entry->start_row,
                'start_col' => $entry->start_col,
            ])
            ->all();

        $response = $this
            ->actingAs($this->user)
            ->putJson('/api/updateCrossword', [
                'id' => $crossword->id,
                'title' => 'Nem menthető módosítás',
                'entries' => $this->validEntries(),
                'topic_ids' => [$newTopic->id],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.0.code',
                ValidationErrors::CLUE_TOPIC_MISMATCH->value,
            )
            ->assertJsonPath('errors.0.clue_ids.0', $this->world->id);

        $crossword->refresh();

        $currentEntries = $crossword->crosswordClues()
            ->orderBy('id')
            ->get([
                'id',
                'clue_id',
                'direction',
                'start_row',
                'start_col',
            ])
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'clue_id' => $entry->clue_id,
                'direction' => $entry->getDirection(),
                'start_row' => $entry->start_row,
                'start_col' => $entry->start_col,
            ])
            ->all();

        $this->assertSame('Eredeti rejtvény', $crossword->title);
        $this->assertSame($oldEntries, $currentEntries);
        $this->assertSame(
            [$oldTopic->id],
            $crossword->topics()->pluck('topics.id')->all(),
        );
    }

    /**
     * Ellenőrzi, hogy közvetlenül létrehozott inkonzisztens rejtvény nem publikálható.
     *
     * @test
     */
    public function testDirectlyCreatedInconsistentCrosswordCannotBePublished(): void
    {
        $topic = $this->createTopic('Angol');

        $this->hello->topics()->attach($topic->id);
        // A WORLD clue szándékosan nem tartozik a rejtvény témájához.

        $crossword = $this->createPrivateCrossword(
            'Közvetlenül létrehozott rejtvény'
        );

        $crossword->topics()->attach($topic->id);

        $response = $this
            ->actingAs($this->user)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.0.code',
                ValidationErrors::CLUE_TOPIC_MISMATCH->value,
            )
            ->assertJsonPath('errors.0.clue_ids.0', $this->world->id);

        $this->assertFalse($crossword->fresh()->is_public);
    }
}