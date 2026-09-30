<?php

namespace Tests\Feature;

use App\Models\Crossword;
use App\Models\CrosswordAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Létrehoz egy próbálkozást a megadott felhasználóhoz és rejtvényhez.
     */
    private function createAttempt(
        User $user,
        Crossword $crossword,
        string $status,
        int $elapsedTime = 0,
    ): CrosswordAttempt {
        return CrosswordAttempt::query()->create([
            'user_id' => $user->id,
            'crossword_id' => $crossword->id,
            'status' => $status,
            'grid_state' => [
                'schema_version' => 2,
                'cell_inputs' => [],
                'correct_entry_ids' => [],
            ],
            'state_version' => 0,
            'elapsed_time' => $elapsedTime,
            'completed_at' => $status === 'completed' ? now() : null,
            'abandoned_at' => $status === 'abandoned' ? now() : null,
        ]);
    }

    /**
     * Ellenőrzi, hogy a kizárólag not_started próbálkozással rendelkező rejtvény nem jelenik meg.
     *
     * @test
     */
    public function testCrosswordWithOnlyNotStartedAttemptIsNotDisplayed(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $crossword = Crossword::factory()->create([
            'user_id' => $creator->id,
            'title' => 'Csak megnyitott rejtvény',
        ]);

        $this->createAttempt($user, $crossword, 'not_started');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame([], $response->json('attempts'));
    }

    /**
     * Ellenőrzi, hogy több valódi próbálkozásból csak a legújabb jelenik meg egyetlen kártyán.
     *
     * @test
     */
    public function testLatestRealAttemptIsSelectedOncePerCrossword(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $crossword = Crossword::factory()->create([
            'user_id' => $creator->id,
            'title' => 'Többször próbált rejtvény',
        ]);

        $completed = $this->createAttempt($user, $crossword, 'completed', 80);
        $abandoned = $this->createAttempt($user, $crossword, 'abandoned', 35);
        $notStarted = $this->createAttempt($user, $crossword, 'not_started');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk();

        $attempts = $response->json('attempts');

        $this->assertCount(1, $attempts);
        $this->assertSame($abandoned->id, $attempts[0]['id']);
        $this->assertSame($crossword->id, $attempts[0]['crossword_id']);
        $this->assertSame('abandoned', $attempts[0]['status']);
        $this->assertSame(35, $attempts[0]['elapsed_time']);
        $this->assertNotSame($completed->id, $attempts[0]['id']);
        $this->assertNotSame($notStarted->id, $attempts[0]['id']);
    }

    /**
     * Ellenőrzi, hogy a legújabb not_started próbálkozás nem rejti el a korábbi valódi próbálkozást.
     *
     * @test
     */
    public function testNewerNotStartedAttemptDoesNotHideOlderRealAttempt(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $crossword = Crossword::factory()->create(['user_id' => $creator->id]);

        $realAttempt = $this->createAttempt($user, $crossword, 'completed', 61);
        $this->createAttempt($user, $crossword, 'not_started');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk();

        $this->assertCount(1, $response->json('attempts'));
        $this->assertSame($realAttempt->id, $response->json('attempts.0.id'));
        $this->assertSame('completed', $response->json('attempts.0.status'));
    }

    /**
     * Ellenőrzi, hogy különböző rejtvények próbálkozásai külön profilkártyát kapnak.
     *
     * @test
     */
    public function testDifferentCrosswordsEachReceiveTheirOwnAttemptCard(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $firstCrossword = Crossword::factory()->create([
            'user_id' => $creator->id,
            'title' => 'Első rejtvény',
        ]);
        $secondCrossword = Crossword::factory()->create([
            'user_id' => $creator->id,
            'title' => 'Második rejtvény',
        ]);

        $firstAttempt = $this->createAttempt($user, $firstCrossword, 'completed', 45);
        $secondAttempt = $this->createAttempt($user, $secondCrossword, 'in_progress', 12);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk();

        $attempts = $response->json('attempts');
        $attemptIds = array_column($attempts, 'id');
        $crosswordIds = array_column($attempts, 'crossword_id');

        $this->assertCount(2, $attempts);
        $this->assertTrue(in_array($firstAttempt->id, $attemptIds, true));
        $this->assertTrue(in_array($secondAttempt->id, $attemptIds, true));
        $this->assertTrue(in_array($firstCrossword->id, $crosswordIds, true));
        $this->assertTrue(in_array($secondCrossword->id, $crosswordIds, true));
    }

    /**
     * Ellenőrzi, hogy a létrehozott és a megpróbált rejtvények a megfelelő profilszakaszba kerülnek.
     *
     * @test
     */
    public function testCreatedAndAttemptedCrosswordsUseCorrectProfileSections(): void
    {
        $user = User::factory()->create();
        $otherCreator = User::factory()->create();

        $createdCrossword = Crossword::factory()->create([
            'user_id' => $user->id,
            'title' => 'Saját rejtvény',
        ]);

        $attemptedCrossword = Crossword::factory()->create([
            'user_id' => $otherCreator->id,
            'title' => 'Más rejtvénye',
        ]);

        $attempt = $this->createAttempt($user, $attemptedCrossword, 'in_progress', 17);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk();

        $createdIds = array_column($response->json('crosswords'), 'id');
        $attemptedCrosswordIds = array_column($response->json('attempts'), 'crossword_id');

        $this->assertSame([$createdCrossword->id], $createdIds);
        $this->assertSame([$attemptedCrossword->id], $attemptedCrosswordIds);
        $this->assertSame($attempt->id, $response->json('attempts.0.id'));
        $this->assertFalse(in_array($attemptedCrossword->id, $createdIds, true));
        $this->assertFalse(in_array($createdCrossword->id, $attemptedCrosswordIds, true));
    }
}
