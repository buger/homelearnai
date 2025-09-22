<?php

namespace Tests\Unit\Factories;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardFactoryTest extends TestCase
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
        $this->subject = Subject::factory()->create(['user_id' => $this->user->id]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
    }

    // ==================== Basic Factory Functionality ====================

    public function test_flashcard_factory_creates_basic_flashcard(): void
    {
        $flashcard = Flashcard::factory()->create();

        $this->assertInstanceOf(Flashcard::class, $flashcard);
        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'card_type' => $flashcard->card_type,
            'question' => $flashcard->question,
            'answer' => $flashcard->answer,
        ]);
    }

    public function test_flashcard_factory_default_attributes(): void
    {
        $flashcard = Flashcard::factory()->make();

        $this->assertNotNull($flashcard->topic_id); // Default creates with topic
        $this->assertContains($flashcard->card_type, Flashcard::getCardTypes());
        $this->assertContains($flashcard->difficulty_level, Flashcard::getDifficultyLevels());
        $this->assertTrue($flashcard->is_active);
        $this->assertIsArray($flashcard->choices);
        $this->assertIsArray($flashcard->correct_choices);
        $this->assertIsArray($flashcard->cloze_answers);
        $this->assertIsArray($flashcard->tags);
        $this->assertIsArray($flashcard->occlusion_data);
    }

    // ==================== Topic-Specific Factory Methods ====================

    public function test_for_topic_factory_method(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $this->assertEquals($this->topic->id, $flashcard->topic_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertEquals($this->topic->unit_id, $flashcard->unit_id);
    }

    public function test_for_topic_factory_method_with_make(): void
    {
        $flashcard = Flashcard::factory()->forTopic($this->topic)->make();

        $this->assertEquals($this->topic->id, $flashcard->topic_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertNull($flashcard->id); // Not persisted
    }

    public function test_for_topic_factory_method_creates_multiple(): void
    {
        $flashcards = Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        $this->assertCount(3, $flashcards);

        foreach ($flashcards as $flashcard) {
            $this->assertEquals($this->topic->id, $flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }

        $this->assertEquals(3, $this->topic->fresh()->flashcards()->count());
    }

    public function test_flashcard_must_belong_to_topic(): void
    {
        // In the new architecture, all flashcards must belong to a topic
        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $this->assertNotNull($flashcard->topic_id);
        $this->assertEquals($this->topic->id, $flashcard->topic_id);
        $this->assertEquals($this->unit->id, $flashcard->unit_id); // Derived from topic
    }

    public function test_flashcard_cannot_exist_without_topic(): void
    {
        // Test that flashcards cannot be created without a topic_id
        $this->expectException(\Exception::class);

        // This should fail because topic_id is required
        Flashcard::factory()->create(['topic_id' => null]);
    }

    // ==================== Combined Factory State Methods ====================

    public function test_for_topic_with_card_type_states(): void
    {
        // Test basic card for topic
        $basicCard = Flashcard::factory()->forTopic($this->topic)->basic()->create();
        $this->assertEquals($this->topic->id, $basicCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_BASIC, $basicCard->card_type);

        // Test multiple choice card for topic
        $mcCard = Flashcard::factory()->forTopic($this->topic)->multipleChoice()->create();
        $this->assertEquals($this->topic->id, $mcCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_MULTIPLE_CHOICE, $mcCard->card_type);
        $this->assertNotEmpty($mcCard->choices);
        $this->assertNotEmpty($mcCard->correct_choices);

        // Test cloze card for topic
        $clozeCard = Flashcard::factory()->forTopic($this->topic)->cloze()->create();
        $this->assertEquals($this->topic->id, $clozeCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_CLOZE, $clozeCard->card_type);
        $this->assertNotNull($clozeCard->cloze_text);
        $this->assertNotEmpty($clozeCard->cloze_answers);

        // Test true/false card for topic
        $tfCard = Flashcard::factory()->forTopic($this->topic)->trueFalse()->create();
        $this->assertEquals($this->topic->id, $tfCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_TRUE_FALSE, $tfCard->card_type);
        $this->assertCount(2, $tfCard->choices);
        $this->assertEquals(['True', 'False'], $tfCard->choices);

        // Test image occlusion card for topic
        $ioCard = Flashcard::factory()->forTopic($this->topic)->imageOcclusion()->create();
        $this->assertEquals($this->topic->id, $ioCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_IMAGE_OCCLUSION, $ioCard->card_type);
        $this->assertNotNull($ioCard->question_image_url);
        $this->assertNotEmpty($ioCard->occlusion_data);

        // Test typed answer card for topic
        $taCard = Flashcard::factory()->forTopic($this->topic)->typedAnswer()->create();
        $this->assertEquals($this->topic->id, $taCard->topic_id);
        $this->assertEquals(Flashcard::CARD_TYPE_TYPED_ANSWER, $taCard->card_type);
    }

    public function test_for_topic_with_difficulty_states(): void
    {
        $easyCard = Flashcard::factory()->forTopic($this->topic)->easy()->create();
        $mediumCard = Flashcard::factory()->forTopic($this->topic)->medium()->create();
        $hardCard = Flashcard::factory()->forTopic($this->topic)->hard()->create();

        $this->assertEquals($this->topic->id, $easyCard->topic_id);
        $this->assertEquals($this->topic->id, $mediumCard->topic_id);
        $this->assertEquals($this->topic->id, $hardCard->topic_id);

        $this->assertEquals(Flashcard::DIFFICULTY_EASY, $easyCard->difficulty_level);
        $this->assertEquals(Flashcard::DIFFICULTY_MEDIUM, $mediumCard->difficulty_level);
        $this->assertEquals(Flashcard::DIFFICULTY_HARD, $hardCard->difficulty_level);
    }

    public function test_for_topic_with_status_states(): void
    {
        $activeCard = Flashcard::factory()->forTopic($this->topic)->create(['is_active' => true]);
        $inactiveCard = Flashcard::factory()->forTopic($this->topic)->inactive()->create();

        $this->assertEquals($this->topic->id, $activeCard->topic_id);
        $this->assertEquals($this->topic->id, $inactiveCard->topic_id);

        $this->assertTrue($activeCard->is_active);
        $this->assertFalse($inactiveCard->is_active);

        // Test that topic only shows active flashcards
        $this->assertEquals(1, $this->topic->fresh()->flashcards()->where('is_active', true)->count());
    }

    public function test_for_topic_with_tags_state(): void
    {
        $cardWithDefaultTags = Flashcard::factory()->forTopic($this->topic)->withTags()->create();
        $cardWithCustomTags = Flashcard::factory()->forTopic($this->topic)->withTags(['biology', 'plants', 'science'])->create();

        $this->assertEquals($this->topic->id, $cardWithDefaultTags->topic_id);
        $this->assertEquals($this->topic->id, $cardWithCustomTags->topic_id);

        $this->assertIsArray($cardWithDefaultTags->tags);
        $this->assertNotEmpty($cardWithDefaultTags->tags);

        $this->assertEquals(['biology', 'plants', 'science'], $cardWithCustomTags->tags);
    }

    public function test_for_topic_with_import_source_state(): void
    {
        $importedCard = Flashcard::factory()->forTopic($this->topic)->imported('anki')->create();

        $this->assertEquals($this->topic->id, $importedCard->topic_id);
        $this->assertEquals('anki', $importedCard->import_source);
    }

    // ==================== Complex Factory Combinations ====================

    public function test_complex_topic_flashcard_combinations(): void
    {
        $complexCard = Flashcard::factory()
            ->forTopic($this->topic)
            ->multipleChoice()
            ->hard()
            ->withTags(['advanced', 'biology'])
            ->imported('quizlet')
            ->create();

        $this->assertEquals($this->topic->id, $complexCard->topic_id);
        $this->assertEquals($this->unit->id, $complexCard->unit_id);
        $this->assertEquals(Flashcard::CARD_TYPE_MULTIPLE_CHOICE, $complexCard->card_type);
        $this->assertEquals(Flashcard::DIFFICULTY_HARD, $complexCard->difficulty_level);
        $this->assertEquals(['advanced', 'biology'], $complexCard->tags);
        $this->assertEquals('quizlet', $complexCard->import_source);
        $this->assertNotEmpty($complexCard->choices);
        $this->assertNotEmpty($complexCard->correct_choices);
    }

    public function test_multiple_topics_with_different_flashcard_types(): void
    {
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);

        // Create different types in different topics
        $basicCards = Flashcard::factory()->count(2)->forTopic($this->topic)->basic()->create();
        $mcCards = Flashcard::factory()->count(3)->forTopic($topic2)->multipleChoice()->create();

        $this->assertEquals(2, $this->topic->fresh()->flashcards()->count());
        $this->assertEquals(3, $topic2->fresh()->flashcards()->count());

        foreach ($basicCards as $card) {
            $this->assertEquals($this->topic->id, $card->topic_id);
            $this->assertEquals(Flashcard::CARD_TYPE_BASIC, $card->card_type);
        }

        foreach ($mcCards as $card) {
            $this->assertEquals($topic2->id, $card->topic_id);
            $this->assertEquals(Flashcard::CARD_TYPE_MULTIPLE_CHOICE, $card->card_type);
        }
    }

    // ==================== Factory Validation ====================

    public function test_factory_creates_valid_flashcard_data(): void
    {
        $multipleChoiceCard = Flashcard::factory()->forTopic($this->topic)->multipleChoice()->create();

        $errors = $multipleChoiceCard->validateCardData();
        $this->assertEmpty($errors, 'Factory should create valid multiple choice card data');

        $clozeCard = Flashcard::factory()->forTopic($this->topic)->cloze()->create();
        $errors = $clozeCard->validateCardData();
        $this->assertEmpty($errors, 'Factory should create valid cloze card data');

        $imageCard = Flashcard::factory()->forTopic($this->topic)->imageOcclusion()->create();
        $errors = $imageCard->validateCardData();
        $this->assertEmpty($errors, 'Factory should create valid image occlusion card data');
    }

    public function test_factory_respects_topic_unit_relationship(): void
    {
        // Create topic in different unit
        $otherUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $otherTopic = Topic::factory()->create(['unit_id' => $otherUnit->id]);

        $flashcard = Flashcard::factory()->forTopic($otherTopic)->create();

        $this->assertEquals($otherTopic->id, $flashcard->topic_id);
        $this->assertEquals($otherUnit->id, $flashcard->unit_id);
        $this->assertEquals($otherTopic->unit_id, $flashcard->unit_id);
    }

    // ==================== Edge Cases and Error Handling ====================

    public function test_factory_with_non_existent_topic(): void
    {
        // This test verifies that the factory properly handles invalid topic relationships
        // In a real scenario, this would likely fail at the database level due to foreign key constraints

        $this->expectException(\Exception::class);

        // Create a topic, then delete it
        $tempTopic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $tempTopicId = $tempTopic->id;
        $tempTopic->delete();

        // Try to create flashcard for deleted topic (should fail)
        Flashcard::factory()->create([
            'topic_id' => $tempTopicId,
            'unit_id' => $this->unit->id,
        ]);
    }

    public function test_factory_performance_with_large_batches(): void
    {
        $startTime = microtime(true);

        // Create a large batch of topic flashcards
        $flashcards = Flashcard::factory()
            ->count(100)
            ->forTopic($this->topic)
            ->basic()
            ->create();

        $endTime = microtime(true);

        $this->assertCount(100, $flashcards);
        $this->assertEquals(100, $this->topic->fresh()->flashcards()->count());

        // Performance should be reasonable (under 2 seconds for 100 cards)
        $this->assertLessThan(2.0, $endTime - $startTime);

        // Verify all cards have correct relationships
        foreach ($flashcards->take(5) as $flashcard) { // Test a sample
            $this->assertEquals($this->topic->id, $flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
            $this->assertEquals(Flashcard::CARD_TYPE_BASIC, $flashcard->card_type);
        }
    }

    public function test_factory_sequence_consistency(): void
    {
        // Create a sequence of flashcards to ensure consistency
        $cards = [];

        for ($i = 1; $i <= 5; $i++) {
            $cards[] = Flashcard::factory()
                ->forTopic($this->topic)
                ->basic()
                ->create([
                    'question' => "Question {$i}",
                    'answer' => "Answer {$i}",
                ]);
        }

        // Verify all cards maintain topic relationship
        foreach ($cards as $index => $card) {
            $this->assertEquals($this->topic->id, $card->topic_id);
            $this->assertEquals($this->unit->id, $card->unit_id);
            $this->assertEquals('Question '.($index + 1), $card->question);
            $this->assertEquals('Answer '.($index + 1), $card->answer);
        }

        $this->assertEquals(5, $this->topic->fresh()->flashcards()->count());
    }
}
