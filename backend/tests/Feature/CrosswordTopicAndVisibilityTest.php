<?php

namespace Tests\Feature;

use App\Enums\Direction;
use App\Models\Clue;
use App\Models\Crossword;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrosswordTopicAndVisibilityTest extends TestCase
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
     * Visszaadja a tesztekhez használt érvényes elhelyezéseket.
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
     * Létrehoz egy privát rejtvényt a frissítési tesztekhez.
     */
    private function createPrivateCrossword(): Crossword
    {
        $crossword = Crossword::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Témás rejtvény',
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
     * Ellenőrzi, hogy létrehozáskor a kiválasztott témák kapcsolódnak a rejtvényhez.
     *
     * @test
     */
    public function testCreatingCrosswordAttachesSelectedTopics(): void
    {
        $firstTopic = Topic::query()->create(['name' => 'Földrajz']);
        $secondTopic = Topic::query()->create(['name' => 'Nyelvek']);

        $this->hello->topics()->attach($firstTopic->id);
        $this->world->topics()->attach($secondTopic->id);

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Témás tesztrejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [$firstTopic->id, $secondTopic->id],
            ])
            ->assertCreated();

        $crossword = Crossword::query()->findOrFail($response->json('crossword.id'));
        $topicIds = $crossword->topics()->pluck('topics.id')->sort()->values()->all();

        $this->assertSame([$firstTopic->id, $secondTopic->id], $topicIds);
    }

    /**
     * Ellenőrzi, hogy frissítéskor az új témák lecserélik a korábbi kapcsolatokat.
     *
     * @test
     */
    public function testUpdatingCrosswordReplacesPreviousTopics(): void
    {
        $oldTopic = Topic::query()->create(['name' => 'Régi téma']);
        $newTopic = Topic::query()->create(['name' => 'Új téma']);

        $this->hello->topics()->attach([
            $oldTopic->id,
            $newTopic->id,
        ]);

        $this->world->topics()->attach([
            $oldTopic->id,
            $newTopic->id,
        ]);


        $crossword = $this->createPrivateCrossword();
        $crossword->topics()->attach($oldTopic->id);

        $this
            ->actingAs($this->user)
            ->putJson('/api/updateCrossword', [
                'id' => $crossword->id,
                'title' => 'Frissített témás rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [$newTopic->id],
            ])
            ->assertOk();

        $this->assertSame(
            [$newTopic->id],
            $crossword->fresh()->topics()->pluck('topics.id')->all(),
        );
    }

    /**
     * Ellenőrzi, hogy az üres témaválasztás eltávolítja a korábbi kapcsolatokat.
     *
     * @test
     */
    public function testClearingTopicsDetachesPreviousAssociations(): void
    {
        $topic = Topic::query()->create(['name' => 'Törlendő téma']);
        $crossword = $this->createPrivateCrossword();
        $crossword->topics()->attach($topic->id);

        $this
            ->actingAs($this->user)
            ->putJson('/api/updateCrossword', [
                'id' => $crossword->id,
                'title' => 'Téma nélküli rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [],
            ])
            ->assertOk();

        $this->assertSame([], $crossword->fresh()->topics()->pluck('topics.id')->all());
    }

    /**
     * Ellenőrzi, hogy a nem létező témaazonosító validációs hibát eredményez.
     *
     * @test
     */
    public function testInvalidTopicIdIsRejected(): void
    {
        $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Hibás témás rejtvény',
                'entries' => $this->validEntries(),
                'topic_ids' => [999999],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic_ids.0']);
    }

    /**
     * Ellenőrzi, hogy create és update kéréssel nem kerülhető meg a publikációs folyamat.
     *
     * @test
     */
    public function testCreateAndUpdateCannotPublishWithoutSetVisibility(): void
    {
        $createResponse = $this
            ->actingAs($this->user)
            ->postJson('/api/createCrossword', [
                'title' => 'Publikációs határteszt',
                'entries' => $this->validEntries(),
                'is_public' => true,
            ])
            ->assertCreated();

        $crossword = Crossword::query()->findOrFail($createResponse->json('crossword.id'));
        $this->assertFalse($crossword->is_public);

        $this
            ->actingAs($this->user)
            ->putJson('/api/updateCrossword', [
                'id' => $crossword->id,
                'title' => 'Frissített határteszt',
                'entries' => $this->validEntries(),
                'is_public' => true,
            ])
            ->assertOk();

        $this->assertFalse($crossword->fresh()->is_public);

        $this
            ->actingAs($this->user)
            ->patchJson('/api/setVisibility', [
                'id' => $crossword->id,
                'is_public' => true,
            ])
            ->assertOk()
            ->assertJsonPath('is_public', true);

        $this->assertTrue($crossword->fresh()->is_public);
    }
}
