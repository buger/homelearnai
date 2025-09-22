<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected Subject $subject;

    protected Unit $unit;

    protected Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable session middleware but disable unnecessary middleware for testing
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Clear any session state that might interfere with tests
        session()->flush();
        session()->forget('kids_mode_active');
        session()->forget('kids_mode_child_id');

        // Create test data
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->subject = Subject::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->unit = Unit::factory()->create([
            'subject_id' => $this->subject->id,
        ]);

        $this->topic = Topic::factory()->create([
            'unit_id' => $this->unit->id,
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        // Ensure user is not authenticated
        auth()->logout();

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards");
        $response->assertStatus(401); // Unauthenticated user
    }

    public function test_index_returns_flashcards_for_authorized_user(): void
    {
        $this->actingAs($this->user);

        // Create flashcards for this topic
        Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'flashcards' => [
                    '*' => [
                        'id',
                        'topic_id',
                        'card_type',
                        'question',
                        'answer',
                        'difficulty_level',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'topic',
                'unit',
            ])
            ->assertJsonCount(3, 'flashcards');
    }

    public function test_index_denies_access_to_other_users_flashcards(): void
    {
        $this->actingAs($this->otherUser);

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards");
        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        // Ensure user is not authenticated
        auth()->logout();

        $flashcardData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Test question?',
            'answer' => 'Test answer',
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
        ];

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards", $flashcardData);
        // With CSRF disabled in tests, unauthenticated requests correctly return 401
        $response->assertStatus(401); // Unauthorized for unauthenticated API requests
    }

    public function test_store_creates_flashcard_successfully(): void
    {
        $this->actingAs($this->user);

        $flashcardData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'What is the capital of France?',
            'answer' => 'Paris',
            'hint' => 'It is a city of lights',
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
            'tags' => ['geography', 'capitals'],
        ];

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards", $flashcardData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard created successfully',
                'flashcard' => [
                    'card_type' => Flashcard::CARD_TYPE_BASIC,
                    'question' => 'What is the capital of France?',
                    'answer' => 'Paris',
                    'hint' => 'It is a city of lights',
                    'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
                    'tags' => ['geography', 'capitals'],
                ],
            ]);

        $this->assertDatabaseHas('flashcards', [
            'topic_id' => $this->topic->id,
            'question' => 'What is the capital of France?',
            'answer' => 'Paris',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards", []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed',
                'errors' => [
                    'card_type' => ['The card type field is required.'],
                    'question' => ['The question field is required.'],
                    'answer' => ['The answer field is required.'],
                    'difficulty_level' => ['The difficulty level field is required.'],
                ],
            ]);
    }

    public function test_store_validates_multiple_choice_card_data(): void
    {
        $this->actingAs($this->user);

        $flashcardData = [
            'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
            'question' => 'Test question?',
            'answer' => 'Test answer',
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
            'choices' => ['Only one choice'], // Should have at least 2
            'correct_choices' => [], // Should not be empty
        ];

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards", $flashcardData);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Validation failed',
            ])
            ->assertJsonPath('errors.choices.0', 'Multiple choice cards must have at least 2 choices.')
            ->assertJsonPath('errors.correct_choices.0', 'Multiple choice cards must have at least 1 correct choice.');
    }

    public function test_store_denies_access_to_other_users(): void
    {
        $this->actingAs($this->otherUser);

        $flashcardData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Test question?',
            'answer' => 'Test answer',
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
        ];

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards", $flashcardData);
        $response->assertStatus(403);
    }

    public function test_show_returns_flashcard_for_authorized_user(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards/{$flashcard->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'flashcard' => [
                    'id' => $flashcard->id,
                    'unit_id' => $this->unit->id,
                    'question' => $flashcard->question,
                    'answer' => $flashcard->answer,
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_flashcard(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards/999999");
        $response->assertStatus(404);
    }

    public function test_update_modifies_flashcard_successfully(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->basic()->forTopic($this->topic)->create();

        $updateData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Updated question?',
            'answer' => 'Updated answer',
            'difficulty_level' => Flashcard::DIFFICULTY_HARD,
            'tags' => ['updated', 'tag'],
        ];

        $response = $this->putJson("/api/topics/{$this->topic->id}/flashcards/{$flashcard->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard updated successfully',
                'flashcard' => [
                    'question' => 'Updated question?',
                    'answer' => 'Updated answer',
                    'difficulty_level' => Flashcard::DIFFICULTY_HARD,
                    'tags' => ['updated', 'tag'],
                ],
            ]);

        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'question' => 'Updated question?',
            'answer' => 'Updated answer',
        ]);
    }

    public function test_destroy_soft_deletes_flashcard(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $response = $this->deleteJson("/api/topics/{$this->topic->id}/flashcards/{$flashcard->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard deleted successfully',
            ]);

        $this->assertSoftDeleted('flashcards', ['id' => $flashcard->id]);
    }

    public function test_restore_undeletes_flashcard(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $flashcard->delete(); // Soft delete first

        $response = $this->postJson("/api/topics/{$this->topic->id}/flashcards/{$flashcard->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard restored successfully',
            ]);

        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'deleted_at' => null,
        ]);
    }

    public function test_force_destroy_permanently_deletes_flashcard(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $flashcard->delete(); // Soft delete first

        $response = $this->deleteJson("/api/topics/{$this->topic->id}/flashcards/{$flashcard->id}/force");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard permanently deleted',
            ]);

        $this->assertDatabaseMissing('flashcards', ['id' => $flashcard->id]);
    }

    public function test_get_by_type_returns_filtered_flashcards(): void
    {
        $this->actingAs($this->user);

        // Create different types of flashcards
        Flashcard::factory()->basic()->count(2)->forTopic($this->topic)->create();
        Flashcard::factory()->multipleChoice()->count(1)->forTopic($this->topic)->create();

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards/type/basic");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'flashcards')
            ->assertJsonFragment(['card_type' => Flashcard::CARD_TYPE_BASIC]);
    }

    public function test_get_by_type_validates_card_type(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson("/api/topics/{$this->topic->id}/flashcards/type/invalid_type");

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid card type']);
    }

    public function test_bulk_update_status_changes_multiple_flashcards(): void
    {
        $this->actingAs($this->user);

        $flashcards = Flashcard::factory()->count(3)->create([
            'topic_id' => $this->topic->id,
            'is_active' => true,
        ]);

        $flashcardIds = $flashcards->pluck('id')->toArray();

        $response = $this->patchJson("/api/topics/{$this->topic->id}/flashcards/bulk-status", [
            'flashcard_ids' => $flashcardIds,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'updated_count' => 3,
            ]);

        foreach ($flashcardIds as $id) {
            $this->assertDatabaseHas('flashcards', [
                'id' => $id,
                'is_active' => false,
            ]);
        }
    }

    public function test_bulk_update_validates_flashcard_ids(): void
    {
        $this->actingAs($this->user);

        $response = $this->patchJson("/api/topics/{$this->topic->id}/flashcards/bulk-status", [
            'flashcard_ids' => [999999], // Non-existent ID
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
                'messages' => [
                    'flashcard_ids.0' => ['The selected flashcard_ids.0 is invalid.'],
                ],
            ]);
    }

    public function test_authorization_prevents_access_to_wrong_unit(): void
    {
        $this->actingAs($this->user);

        // Create another user's topic
        $otherSubject = Subject::factory()->create(['user_id' => $this->otherUser->id]);
        $otherUnit = Unit::factory()->create(['subject_id' => $otherSubject->id]);
        $otherTopic = Topic::factory()->create(['unit_id' => $otherUnit->id]);

        $response = $this->getJson("/api/topics/{$otherTopic->id}/flashcards");
        $response->assertStatus(403);
    }
}
