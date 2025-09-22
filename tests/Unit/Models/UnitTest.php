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
        // In the topic-only architecture, all flashcards must have a topic_id
        // The "direct" flashcards relationship returns empty for backward compatibility

        // Create flashcards via the forUnit method (which creates them via a default topic)
        $unitBasedFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        // Create additional topic flashcards for comparison
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcards = Flashcard::factory()->count(1)->forTopic($topic)->create();

        // Test direct flashcards relationship (should be empty in topic-only architecture)
        $unitFlashcards = $this->unit->flashcards;
        $this->assertCount(0, $unitFlashcards); // No direct flashcards in topic-only architecture

        // But all flashcards created for this unit should be accessible via allFlashcards
        $allFlashcards = $this->unit->allFlashcards;

        // In topic-only architecture, forUnit() creates flashcards via a default topic
        // So we should have: 2 flashcards via default topic + 1 flashcard via explicit topic = 3 total
        $this->assertCount(3, $allFlashcards);

        foreach ($allFlashcards as $flashcard) {
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
            $this->assertNotNull($flashcard->topic_id); // All must have topic_id in topic-only architecture
        }
    }

    public function test_unit_flashcards_relationship_only_active(): void
    {
        $activeFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => true]);
        $inactiveFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => false]);

        // In topic-only architecture, direct flashcards relationship is empty
        $flashcards = $this->unit->flashcards;
        $this->assertCount(0, $flashcards);

        // But allFlashcards should only return active ones
        $allFlashcards = $this->unit->allFlashcards;
        $this->assertCount(1, $allFlashcards);
        $this->assertEquals($activeFlashcard->id, $allFlashcards->first()->id);
        $this->assertTrue($allFlashcards->first()->is_active);
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
        // Create flashcards via forUnit (these will use a default topic)
        Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(3)->forTopic($topic)->create();

        // In topic-only architecture, there are no "direct" unit flashcards
        // All flashcards must have a topic_id, so this method returns 0
        $this->assertEquals(0, $this->unit->getDirectFlashcardsCount());

        // All flashcards should be accessible via getAllFlashcardsCount
        $this->assertEquals(5, $this->unit->getAllFlashcardsCount()); // 2 + 3 = 5
    }

    public function test_unit_topic_flashcards_count_method(): void
    {
        // Create flashcards via forUnit (these use a default topic)
        Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->count(2)->forTopic($topic1)->create();
        Flashcard::factory()->count(1)->forTopic($topic2)->create();

        // In topic-only architecture, ALL flashcards are "topic flashcards"
        // So we should have: 2 (via default topic) + 2 + 1 = 5 total
        $this->assertEquals(5, $this->unit->getTopicFlashcardsCount());

        // This should equal getAllFlashcardsCount since all flashcards have topics
        $this->assertEquals($this->unit->getAllFlashcardsCount(), $this->unit->getTopicFlashcardsCount());
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

        // In topic-only architecture, forUnit creates flashcards via default topic
        // So even after forUnit(), hasDirectFlashcards() should still return false
        Flashcard::factory()->forUnit($this->unit)->create();
        $this->assertFalse($this->unit->hasDirectFlashcards()); // Always false in topic-only architecture

        // But hasAnyFlashcards() should return true
        $this->assertTrue($this->unit->hasAnyFlashcards());
    }

    public function test_unit_has_topic_flashcards_method(): void
    {
        // Unit with no flashcards
        $this->assertFalse($this->unit->hasTopicFlashcards());

        // In topic-only architecture, forUnit() creates topic flashcards via default topic
        Flashcard::factory()->forUnit($this->unit)->create();
        $this->assertTrue($this->unit->hasTopicFlashcards()); // Now returns true because all flashcards have topics

        // Add explicit topic flashcard
        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        Flashcard::factory()->forTopic($topic)->create();
        $this->assertTrue($this->unit->hasTopicFlashcards());

        // hasTopicFlashcards should equal hasAnyFlashcards in topic-only architecture
        $this->assertEquals($this->unit->hasAnyFlashcards(), $this->unit->hasTopicFlashcards());
    }

    // ==================== Mixed Scenarios ====================

    public function test_unit_with_mixed_flashcard_types(): void
    {
        // Create a comprehensive scenario in topic-only architecture
        $unitBasedFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();

        $topic1 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topic2 = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $topicFlashcards1 = Flashcard::factory()->count(3)->forTopic($topic1)->create();
        $topicFlashcards2 = Flashcard::factory()->count(1)->forTopic($topic2)->create();

        // Test all counts and existence methods for topic-only architecture
        // Total: 2 (via default topic) + 3 + 1 = 6 flashcards
        $this->assertEquals(6, $this->unit->getAllFlashcardsCount());
        $this->assertEquals(0, $this->unit->getDirectFlashcardsCount()); // Always 0 in topic-only
        $this->assertEquals(6, $this->unit->getTopicFlashcardsCount()); // All flashcards are topic flashcards

        $this->assertTrue($this->unit->hasAnyFlashcards());
        $this->assertFalse($this->unit->hasDirectFlashcards()); // Always false in topic-only
        $this->assertTrue($this->unit->hasTopicFlashcards());

        // Test relationship queries
        $this->assertCount(0, $this->unit->flashcards); // No direct flashcards in topic-only
        $this->assertCount(6, $this->unit->allFlashcards()->get()); // All via topics

        // Verify all flashcards have topic_id (topic-only architecture)
        foreach ($this->unit->allFlashcards as $flashcard) {
            $this->assertNotNull($flashcard->topic_id);
        }
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
        // Create active and inactive flashcards in topic-only architecture
        $activeUnitFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => true]);
        $inactiveUnitFlashcard = Flashcard::factory()->forUnit($this->unit)->create(['is_active' => false]);

        $topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
        $activeTopicFlashcard = Flashcard::factory()->forTopic($topic)->create(['is_active' => true]);
        $inactiveTopicFlashcard = Flashcard::factory()->forTopic($topic)->create(['is_active' => false]);

        // Methods should only count active flashcards in topic-only architecture
        $this->assertEquals(2, $this->unit->getAllFlashcardsCount()); // 1 active from default topic + 1 active from explicit topic
        $this->assertEquals(0, $this->unit->getDirectFlashcardsCount()); // Always 0 in topic-only
        $this->assertEquals(2, $this->unit->getTopicFlashcardsCount()); // Same as getAllFlashcardsCount in topic-only

        $this->assertCount(0, $this->unit->flashcards); // No direct flashcards in topic-only
        $this->assertCount(2, $this->unit->allFlashcards()->get()); // All active topic flashcards

        // But database should have all 4 (both active and inactive)
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
        // Create a substantial number of flashcards in topic-only architecture
        Flashcard::factory()->count(10)->forUnit($this->unit)->create(); // Via default topic

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

        // Verify counts for topic-only architecture
        $this->assertEquals(0, $directCount); // Always 0 in topic-only
        $this->assertEquals(33, $topicCount); // 10 + 15 + 8 = 33 (all flashcards have topics)
        $this->assertEquals(33, $allCount); // Same as topic count in topic-only

        // Verify that topic and all counts are identical in topic-only architecture
        $this->assertEquals($topicCount, $allCount);

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
