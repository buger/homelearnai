<?php

namespace Tests\Unit\Models;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Subject $subject;

    protected Unit $unit;

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
    }

    public function test_unit_belongs_to_subject(): void
    {
        $this->assertInstanceOf(Subject::class, $this->unit->subject);
        $this->assertEquals($this->subject->id, $this->unit->subject->id);
    }

    public function test_unit_has_many_topics(): void
    {
        $topics = Topic::factory()->count(3)->create(['unit_id' => $this->unit->id]);

        $this->assertCount(3, $this->unit->topics);
        $this->assertInstanceOf(Topic::class, $this->unit->topics->first());
    }

    // ==================== Direct Flashcard Relationship Tests ====================

    public function test_unit_has_many_direct_flashcards(): void
    {
        // Create direct unit flashcards (no topic)
        $directFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        // Create topic flashcards for comparison
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcards = Flashcard::factory()->count(1)->forTopic($topic)->create();

        // Test direct flashcards relationship (should only include unit flashcards, not topic ones)
        $unitFlashcards = $this->unit->flashcards;
        $this->assertCount(2, $unitFlashcards);

        foreach ($unitFlashcards as $flashcard) {
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
            $this->assertNull($flashcard->topic_id);
        }
    }

    public function test_unit_flashcards_relationship_only_active(): void
    {
        $activeFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => true]);
        $inactiveFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => false]);

        $flashcards = $this->unit->flashcards;

        $this->assertCount(1, $flashcards);
        $this->assertEquals($activeFlashcard->id, $flashcards->first()->id);
    }

    // ==================== All Flashcards (Unit + Topic) Tests ====================

    public function test_unit_all_flashcards_includes_both_direct_and_topic_flashcards(): void
    {
        // Create direct unit flashcards
        $directFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        // Create topic flashcards
        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcards1 = Flashcard::factory()->count(2)->forTopic($topic1)->create();
        $topicFlashcards2 = Flashcard::factory()->count(1)->forTopic($topic2)->create();

        // Test allFlashcards method
        $allFlashcards = $this->unit->allFlashcards()->get();
        $this->assertCount(5, $allFlashcards); // 2 direct + 2 + 1 topic

        // Verify all belong to this unit
        foreach ($allFlashcards as $flashcard) {
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }
    }

    public function test_unit_all_flashcards_count_method(): void
    {
        // Create mixed flashcards
        Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(3)->forTopic($topic)->create();

        // Test count method
        $this->assertEquals(5, $this->unit->getAllFlashcardsCount());
    }

    public function test_unit_direct_flashcards_count_method(): void
    {
        // Create mixed flashcards
        Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(3)->forTopic($topic)->create();

        // Test direct count method (should only count unit flashcards, not topic)
        $this->assertEquals(2, $this->unit->getDirectFlashcardsCount());
    }

    public function test_unit_topic_flashcards_count_method(): void
    {
        // Create mixed flashcards
        Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(2)->forTopic($topic1)->create();
        Flashcard::factory()->count(1)->forTopic($topic2)->create();

        // Test topic flashcards count method
        $this->assertEquals(3, $this->unit->getTopicFlashcardsCount());
    }

    // ==================== Flashcard Existence Check Methods ====================

    public function test_unit_has_any_flashcards_method(): void
    {
        // Unit with no flashcards
        $this->assertFalse($this->unit->hasAnyFlashcards());

        // Add direct flashcard
        Flashcard::factory()->forUnit($this->unit)->create();
        $this->assertTrue($this->unit->hasAnyFlashcards());

        // Test with only topic flashcards
        $emptyUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $topic = Topic::factory()->create(['unit_id' => $emptyUnit->id]);
        Flashcard::factory()->forTopic($topic)->create();
        $this->assertTrue($emptyUnit->hasAnyFlashcards());
    }

    public function test_unit_has_direct_flashcards_method(): void
    {
        // Unit with no flashcards
        $this->assertFalse($this->unit->hasDirectFlashcards());

        // Add topic flashcard (should not count as direct)
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->forTopic($topic)->create();
        $this->assertFalse($this->unit->hasDirectFlashcards());

        // Add direct flashcard
        Flashcard::factory()->forUnit($this->unit)->create();
        $this->assertTrue($this->unit->hasDirectFlashcards());
    }

    public function test_unit_has_topic_flashcards_method(): void
    {
        // Unit with no flashcards
        $this->assertFalse($this->unit->hasTopicFlashcards());

        // Add direct flashcard (should not count as topic)
        Flashcard::factory()->forUnit($this->unit)->create();
        $this->assertFalse($this->unit->hasTopicFlashcards());

        // Add topic flashcard
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->forTopic($topic)->create();
        $this->assertTrue($this->unit->hasTopicFlashcards());
    }

    // ==================== Mixed Scenarios ====================

    public function test_unit_with_mixed_flashcard_types(): void
    {
        // Create a comprehensive scenario
        $directFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcards1 = Flashcard::factory()->count(3)->forTopic($topic1)->create();
        $topicFlashcards2 = Flashcard::factory()->count(1)->forTopic($topic2)->create();

        // Test all counts and existence methods
        $this->assertEquals(6, $this->unit->getAllFlashcardsCount());
        $this->assertEquals(2, $this->unit->getDirectFlashcardsCount());
        $this->assertEquals(4, $this->unit->getTopicFlashcardsCount());

        $this->assertTrue($this->unit->hasAnyFlashcards());
        $this->assertTrue($this->unit->hasDirectFlashcards());
        $this->assertTrue($this->unit->hasTopicFlashcards());

        // Test relationship queries
        $this->assertCount(2, $this->unit->flashcards); // Direct only
        $this->assertCount(6, $this->unit->allFlashcards()->get()); // All
    }

    public function test_unit_flashcard_soft_deletion_behavior(): void
    {
        // Create flashcards
        $directFlashcard = Flashcard::factory()->forUnit($this->unit)->create();
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcard = Flashcard::factory()->forTopic($topic)->create();

        $this->assertEquals(2, $this->unit->getAllFlashcardsCount());

        // Soft delete direct flashcard
        $directFlashcard->delete();
        $this->assertEquals(1, $this->unit->fresh()->getAllFlashcardsCount());
        $this->assertFalse($this->unit->fresh()->hasDirectFlashcards());
        $this->assertTrue($this->unit->fresh()->hasTopicFlashcards());

        // Soft delete topic flashcard
        $topicFlashcard->delete();
        $this->assertEquals(0, $this->unit->fresh()->getAllFlashcardsCount());
        $this->assertFalse($this->unit->fresh()->hasAnyFlashcards());
    }

    public function test_unit_flashcard_inactive_behavior(): void
    {
        // Create active and inactive flashcards
        $activeDirectFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => true]);
        $inactiveDirectFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => false]);

        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $activeTopicFlashcard = Flashcard::factory()->forTopic($topic)->create(['is_active' => true]);
        $inactiveTopicFlashcard = Flashcard::factory()->forTopic($topic)->create(['is_active' => false]);

        // Methods should only count active flashcards
        $this->assertEquals(2, $this->unit->getAllFlashcardsCount());
        $this->assertEquals(1, $this->unit->getDirectFlashcardsCount());
        $this->assertEquals(1, $this->unit->getTopicFlashcardsCount());

        $this->assertCount(1, $this->unit->flashcards); // Direct active only
        $this->assertCount(2, $this->unit->allFlashcards()->get()); // All active

        // But database should have all 4
        $allInDatabase = Flashcard::where('unit_id', $this->unit->id)->get();
        $this->assertCount(4, $allInDatabase);
    }

    // ==================== Edge Cases ====================

    public function test_unit_with_no_flashcards_returns_zero_counts(): void
    {
        $this->assertEquals(0, $this->unit->getAllFlashcardsCount());
        $this->assertEquals(0, $this->unit->getDirectFlashcardsCount());
        $this->assertEquals(0, $this->unit->getTopicFlashcardsCount());

        $this->assertFalse($this->unit->hasAnyFlashcards());
        $this->assertFalse($this->unit->hasDirectFlashcards());
        $this->assertFalse($this->unit->hasTopicFlashcards());

        $this->assertCount(0, $this->unit->flashcards);
        $this->assertCount(0, $this->unit->allFlashcards()->get());
    }

    public function test_unit_with_topics_but_no_flashcards(): void
    {
        // Create topics but no flashcards
        Topic::factory()->count(3)->create(['unit_id' => $this->unit->id]);

        $this->assertEquals(0, $this->unit->getAllFlashcardsCount());
        $this->assertFalse($this->unit->hasAnyFlashcards());
        $this->assertCount(3, $this->unit->topics); // Topics exist
    }

    public function test_unit_flashcard_relationship_consistency(): void
    {
        // Test that topic flashcards maintain unit_id consistency
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $flashcard = Flashcard::factory()->forTopic($topic)->create();

        // Topic flashcard should have same unit_id as its topic
        $this->assertEquals($this->unit->id, $flashcard->unit_id);
        $this->assertEquals($topic->unit_id, $flashcard->unit_id);
        $this->assertEquals($topic->id, $flashcard->topic_id);

        // Should be included in unit's all flashcards
        $this->assertTrue($this->unit->allFlashcards()->get()->contains($flashcard));
        $this->assertFalse($this->unit->flashcards->contains($flashcard)); // Not in direct
    }

    // ==================== Performance Considerations ====================

    public function test_unit_flashcard_queries_are_efficient(): void
    {
        // Create a substantial number of flashcards
        Flashcard::factory()->count(10)->forUnit($this->unit)->create();

        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(15)->forTopic($topic1)->create();
        Flashcard::factory()->count(8)->forTopic($topic2)->create();

        // Test that queries return expected results efficiently
        $startTime = microtime(true);

        $directCount = $this->unit->getDirectFlashcardsCount();
        $topicCount = $this->unit->getTopicFlashcardsCount();
        $allCount = $this->unit->getAllFlashcardsCount();

        $endTime = microtime(true);

        // Verify counts
        $this->assertEquals(10, $directCount);
        $this->assertEquals(23, $topicCount);
        $this->assertEquals(33, $allCount);

        // Verify reasonable performance (should be under 100ms for this dataset)
        $this->assertLessThan(0.1, $endTime - $startTime);
    }

    public function test_unit_with_count_avoids_n_plus_one_queries(): void
    {
        // Create multiple units with flashcards
        $units = collect();
        for ($i = 0; $i < 3; $i++) {
            $unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
            Flashcard::factory()->count(rand(2, 5))->forUnit($unit)->create();
            $units->push($unit);
        }

        // Test 1: Verify withCount() works
        $unitsWithCount = Unit::where('subject_id', $this->subject->id)
            ->withCount('allFlashcards')
            ->get();

        foreach ($unitsWithCount as $unit) {
            // Verify that the count attribute is set
            $this->assertArrayHasKey('all_flashcards_count', $unit->getAttributes());

            // Verify getAllFlashcardsCount() uses the preloaded count
            $countFromMethod = $unit->getAllFlashcardsCount();
            $countFromAttribute = $unit->all_flashcards_count;

            $this->assertEquals($countFromAttribute, $countFromMethod);
            $this->assertGreaterThanOrEqual(0, $countFromMethod);
        }

        // Test 2: Verify static bulk method works
        $unitsForBulk = Unit::where('subject_id', $this->subject->id)->get();
        $bulkCounts = Unit::getFlashcardCountsForUnits($unitsForBulk);

        $this->assertCount($unitsForBulk->count(), $bulkCounts);

        foreach ($unitsForBulk as $unit) {
            $this->assertArrayHasKey($unit->id, $bulkCounts);
            $this->assertEquals($unit->getAllFlashcardsCount(), $bulkCounts[$unit->id]);
        }

        // Test 3: Test empty collection handling
        $emptyCounts = Unit::getFlashcardCountsForUnits(collect());
        $this->assertEmpty($emptyCounts);
    }
}
