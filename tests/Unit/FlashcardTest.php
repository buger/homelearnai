<?php

namespace Tests\Unit;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Subject $subject;

    protected Unit $unit;

    protected Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::factory()->create();
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

    public function test_flashcard_belongs_to_unit_through_topic(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $flashcard->load('topic.unit'); // Ensure topic and unit are loaded

        // unit_id is computed from the topic relationship
        $this->assertEquals($this->unit->id, $flashcard->unit_id);

        // Access unit through topic relationship (proper Eloquent way)
        $this->assertInstanceOf(Unit::class, $flashcard->topic->unit);
        $this->assertEquals($this->unit->id, $flashcard->topic->unit->id);

        // Test the direct unit relationship
        $this->assertInstanceOf(Unit::class, $flashcard->unit);
        $this->assertEquals($this->unit->id, $flashcard->unit->id);
    }

    public function test_flashcard_belongs_to_subject_through_topic(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $flashcard->load('topic.unit.subject'); // Ensure the full chain is loaded

        // Access subject through proper Eloquent relationships
        $this->assertInstanceOf(Subject::class, $flashcard->topic->unit->subject);
        $this->assertEquals($this->subject->id, $flashcard->topic->unit->subject->id);

        // Test the convenience method
        $this->assertInstanceOf(Subject::class, $flashcard->subject());
        $this->assertEquals($this->subject->id, $flashcard->subject()->id);
    }

    public function test_topic_has_many_flashcards(): void
    {
        $flashcards = Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        $this->assertCount(3, $this->topic->flashcards);
        $this->assertInstanceOf(Flashcard::class, $this->topic->flashcards->first());

        // Verify all flashcards belong to the correct topic
        foreach ($flashcards as $flashcard) {
            $this->assertEquals($this->topic->id, $flashcard->topic_id);
        }
    }

    public function test_soft_delete_functionality(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Test soft delete
        $flashcard->delete();

        $this->assertSoftDeleted('flashcards', ['id' => $flashcard->id]);
        $this->assertNotNull($flashcard->fresh()->deleted_at);
    }

    public function test_soft_deleted_flashcards_excluded_from_active_scope(): void
    {
        $activeFlashcard = Flashcard::factory()->forTopic($this->topic)->create([
            'is_active' => true,
        ]);

        $deletedFlashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $deletedFlashcard->delete();

        $activeFlashcards = Flashcard::active()->get();

        $this->assertCount(1, $activeFlashcards);
        $this->assertEquals($activeFlashcard->id, $activeFlashcards->first()->id);
    }

    public function test_flashcard_can_be_restored_after_soft_delete(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $flashcard->delete();
        $this->assertSoftDeleted('flashcards', ['id' => $flashcard->id]);

        $flashcard->restore();
        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'deleted_at' => null,
        ]);
    }

    public function test_flashcard_validation_for_multiple_choice(): void
    {
        $flashcard = new Flashcard([
            'topic_id' => $this->topic->id,
            'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
            'question' => 'Test question',
            'answer' => 'Test answer',
            'choices' => ['Option 1', 'Option 2'],
            'correct_choices' => [0],
        ]);

        $errors = $flashcard->validateCardData();
        $this->assertEmpty($errors);
    }

    public function test_flashcard_validation_fails_for_invalid_multiple_choice(): void
    {
        $flashcard = new Flashcard([
            'topic_id' => $this->topic->id,
            'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
            'question' => 'Test question',
            'answer' => 'Test answer',
            'choices' => ['Only one choice'],
            'correct_choices' => [],
        ]);

        $errors = $flashcard->validateCardData();
        $this->assertNotEmpty($errors);
        $this->assertContains('Multiple choice cards must have at least 2 choices', $errors);
        $this->assertContains('Multiple choice cards must have correct choices specified', $errors);
    }

    public function test_flashcard_validation_for_cloze_deletion(): void
    {
        $flashcard = new Flashcard([
            'topic_id' => $this->topic->id,
            'card_type' => Flashcard::CARD_TYPE_CLOZE,
            'question' => 'Test question',
            'answer' => 'Test answer',
            'cloze_text' => 'The {{c1::answer}} is correct',
            'cloze_answers' => ['answer'],
        ]);

        $errors = $flashcard->validateCardData();
        $this->assertEmpty($errors);
    }

    public function test_flashcard_validation_fails_for_invalid_cloze(): void
    {
        $flashcard = new Flashcard([
            'topic_id' => $this->topic->id,
            'card_type' => Flashcard::CARD_TYPE_CLOZE,
            'question' => 'Test question',
            'answer' => 'Test answer',
        ]);

        $errors = $flashcard->validateCardData();
        $this->assertNotEmpty($errors);
        $this->assertContains('Cloze deletion cards must have cloze text', $errors);
        $this->assertContains('Cloze deletion cards must have cloze answers', $errors);
    }

    public function test_flashcard_scopes(): void
    {
        $basicCard = Flashcard::factory()->forTopic($this->topic)->create([
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'difficulty_level' => Flashcard::DIFFICULTY_EASY,
        ]);

        $multipleChoiceCard = Flashcard::factory()->forTopic($this->topic)->create([
            'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
            'difficulty_level' => Flashcard::DIFFICULTY_HARD,
        ]);

        // Test byCardType scope
        $basicCards = Flashcard::byCardType(Flashcard::CARD_TYPE_BASIC)->get();
        $this->assertCount(1, $basicCards);
        $this->assertEquals($basicCard->id, $basicCards->first()->id);

        // Test byDifficulty scope
        $easyCards = Flashcard::byDifficulty(Flashcard::DIFFICULTY_EASY)->get();
        $this->assertCount(1, $easyCards);
        $this->assertEquals($basicCard->id, $easyCards->first()->id);

        // Test forTopic scope instead of forUnit
        $topicCards = Flashcard::forTopic($this->topic->id);
        $this->assertCount(2, $topicCards);
    }

    public function test_flashcard_access_control(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Test user can access their own flashcard
        $this->assertTrue($flashcard->canBeAccessedBy($this->user->id));

        // Test different user cannot access flashcard
        $otherUser = User::factory()->create();
        $this->assertFalse($flashcard->canBeAccessedBy($otherUser->id));
    }

    public function test_flashcard_card_type_requirements(): void
    {
        $basicCard = new Flashcard(['card_type' => Flashcard::CARD_TYPE_BASIC]);
        $multipleChoiceCard = new Flashcard(['card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE]);
        $clozeCard = new Flashcard(['card_type' => Flashcard::CARD_TYPE_CLOZE]);
        $imageCard = new Flashcard(['card_type' => Flashcard::CARD_TYPE_IMAGE_OCCLUSION]);

        $this->assertFalse($basicCard->requiresMultipleChoiceData());
        $this->assertTrue($multipleChoiceCard->requiresMultipleChoiceData());
        $this->assertFalse($basicCard->requiresClozeData());
        $this->assertTrue($clozeCard->requiresClozeData());
        $this->assertFalse($basicCard->requiresImageData());
        $this->assertTrue($imageCard->requiresImageData());
    }

    public function test_flashcard_constants(): void
    {
        $cardTypes = Flashcard::getCardTypes();
        $this->assertContains(Flashcard::CARD_TYPE_BASIC, $cardTypes);
        $this->assertContains(Flashcard::CARD_TYPE_MULTIPLE_CHOICE, $cardTypes);
        $this->assertCount(6, $cardTypes);

        $difficultyLevels = Flashcard::getDifficultyLevels();
        $this->assertContains(Flashcard::DIFFICULTY_EASY, $difficultyLevels);
        $this->assertContains(Flashcard::DIFFICULTY_MEDIUM, $difficultyLevels);
        $this->assertContains(Flashcard::DIFFICULTY_HARD, $difficultyLevels);
        $this->assertCount(3, $difficultyLevels);
    }

    public function test_flashcard_to_array(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create([
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Test question',
            'answer' => 'Test answer',
        ]);

        $array = $flashcard->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('unit_id', $array);
        $this->assertArrayHasKey('topic_id', $array);
        $this->assertArrayHasKey('card_type', $array);
        $this->assertArrayHasKey('question', $array);
        $this->assertArrayHasKey('answer', $array);
        $this->assertArrayHasKey('requires_multiple_choice_data', $array);
        $this->assertArrayHasKey('requires_cloze_data', $array);
        $this->assertArrayHasKey('requires_image_data', $array);

        $this->assertEquals('Test question', $array['question']);
        $this->assertEquals('Test answer', $array['answer']);
        $this->assertFalse($array['requires_multiple_choice_data']);
    }

    // ==================== Topic Relationship Tests ====================

    public function test_flashcard_belongs_to_topic(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $this->assertInstanceOf(Topic::class, $flashcard->topic);
        $this->assertEquals($this->topic->id, $flashcard->topic->id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertEquals($this->topic->id, $flashcard->topic_id);
    }

    public function test_flashcard_cannot_exist_without_topic(): void
    {
        // In the new architecture, all flashcards must belong to a topic
        $this->expectException(\Exception::class);

        // This should fail because topic_id is required
        Flashcard::factory()->create(['topic_id' => null]);
    }

    public function test_flashcard_access_control_with_topic(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Test user can access their own flashcard through topic
        $this->assertTrue($flashcard->canBeAccessedBy($this->user->id));

        // Test different user cannot access flashcard
        $otherUser = User::factory()->create();
        $this->assertFalse($flashcard->canBeAccessedBy($otherUser->id));
    }

    public function test_flashcard_for_topic_scope(): void
    {
        // Create flashcards for different topics
        $topicFlashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $otherTopic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $otherTopicFlashcard = Flashcard::factory()->forTopic($otherTopic)->create();

        // Test forTopic scope returns only flashcards for specific topic
        $topicFlashcards = Flashcard::forTopic($this->topic->id);
        $this->assertCount(1, $topicFlashcards);
        $this->assertEquals($topicFlashcard->id, $topicFlashcards->first()->id);

        // Test forTopic scope for other topic
        $otherTopicFlashcards = Flashcard::forTopic($otherTopic->id);
        $this->assertCount(1, $otherTopicFlashcards);
        $this->assertEquals($otherTopicFlashcard->id, $otherTopicFlashcards->first()->id);
    }

    public function test_topic_flashcard_maintains_unit_relationship(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Ensure topic's unit_id matches flashcard's unit_id
        $this->assertEquals($this->topic->unit_id, $flashcard->unit_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
    }

    public function test_flashcard_factory_for_topic_method(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->basic()->create();

        $this->assertEquals($this->topic->id, $flashcard->topic_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertEquals(Flashcard::CARD_TYPE_BASIC, $flashcard->card_type);
    }

    public function test_flashcard_factory_for_topic_with_card_types(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->multipleChoice()->create();

        $this->assertNotNull($flashcard->topic_id);
        $this->assertEquals($this->topic->id, $flashcard->topic_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertEquals(Flashcard::CARD_TYPE_MULTIPLE_CHOICE, $flashcard->card_type);
    }

    public function test_multiple_topics_flashcards_in_same_unit(): void
    {
        // Create flashcards through different topics in the same unit
        $topicFlashcard1 = Flashcard::factory()->forTopic($this->topic)->create();

        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcard2 = Flashcard::factory()->forTopic($topic2)->create();

        // All flashcards should belong to topics in the same unit
        $allUnitFlashcards = Flashcard::whereHas('topic', function ($query) {
            $query->where('unit_id', $this->unit->id);
        })->get();
        $this->assertCount(2, $allUnitFlashcards);

        // All flashcards should have topic_id (none should be null)
        $topicFlashcards = Flashcard::whereHas('topic', function ($query) {
            $query->where('unit_id', $this->unit->id);
        })->whereNotNull('topic_id')->get();
        $this->assertCount(2, $topicFlashcards);

        // No flashcards should exist without topic_id (in the new architecture)
        $directUnitFlashcards = Flashcard::whereNull('topic_id')->get();
        $this->assertCount(0, $directUnitFlashcards);
    }

    public function test_flashcard_to_array_includes_topic_id(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create([
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Test question',
            'answer' => 'Test answer',
        ]);

        $array = $flashcard->toArray();

        $this->assertArrayHasKey('topic_id', $array);
        $this->assertEquals($this->topic->id, $array['topic_id']);
        $this->assertEquals($this->unit->id, $array['unit_id']);
    }

    public function test_flashcard_validation_with_topic_context(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->multipleChoice()->make();

        $errors = $flashcard->validateCardData();
        $this->assertEmpty($errors, 'Topic-based flashcard should validate correctly');
    }

    public function test_static_methods_topic_only_architecture(): void
    {
        // Create flashcards using topic-only approach
        $topicFlashcard = Flashcard::factory()->forTopic($this->topic)->create();
        $otherTopic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $otherTopicFlashcard = Flashcard::factory()->forTopic($otherTopic)->create();

        // Test static forUnit method returns empty (no unit-only flashcards exist)
        $unitFlashcards = Flashcard::forUnit($this->unit->id);
        $this->assertCount(0, $unitFlashcards, 'forUnit should return empty as all flashcards now belong to topics');

        // Test static forTopic method works correctly
        $topicFlashcards = Flashcard::forTopic($this->topic->id);
        $this->assertCount(1, $topicFlashcards);
        $this->assertEquals($topicFlashcard->id, $topicFlashcards->first()->id);
    }
}
