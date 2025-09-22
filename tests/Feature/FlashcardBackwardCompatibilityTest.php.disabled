<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardBackwardCompatibilityTest extends TestCase
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

        // Disable CSRF for API testing
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // Clear session state
        session()->flush();

        // Create test data
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->subject = Subject::factory()->create(['user_id' => $this->user->id]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
    }

    // ==================== Legacy Unit-Based Flashcard Operations ====================

    public function test_legacy_unit_flashcard_creation_still_works(): void
    {
        $this->actingAs($this->user);

        // Create flashcard using legacy unit-based endpoint
        $flashcardData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Legacy unit question?',
            'answer' => 'Legacy unit answer',
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
            'tags' => ['legacy', 'unit'],
        ];

        $response = $this->postJson("/api/units/{$this->unit->id}/flashcards", $flashcardData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard created successfully',
                'flashcard' => [
                    'unit_id' => $this->unit->id,
                    'topic_id' => null, // Should be null for unit-based
                    'question' => 'Legacy unit question?',
                    'answer' => 'Legacy unit answer',
                ],
                'context' => 'unit', // Should indicate unit context
            ]);

        // Verify in database
        $this->assertDatabaseHas('flashcards', [
            'unit_id' => $this->unit->id,
            'topic_id' => null,
            'question' => 'Legacy unit question?',
        ]);
    }

    public function test_legacy_unit_flashcard_retrieval_unchanged(): void
    {
        $this->actingAs($this->user);

        // Create legacy unit flashcards
        $unitFlashcards = Flashcard::factory()->count(3)->forUnit($this->unit)->create();

        // Create topic flashcards for comparison (should not appear in unit endpoint)
        $topicFlashcards = Flashcard::factory()->count(2)->forTopic($this->topic)->create();

        $response = $this->getJson("/api/units/{$this->unit->id}/flashcards");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'flashcards'); // Only unit flashcards

        $flashcards = $response->json('flashcards');
        foreach ($flashcards as $flashcard) {
            $this->assertEquals($this->unit->id, $flashcard['unit_id']);
            $this->assertNull($flashcard['topic_id']);
        }
    }

    public function test_legacy_unit_flashcard_update_preserved(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forUnit($this->unit)->basic()->create();

        $updateData = [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
            'question' => 'Updated legacy question?',
            'answer' => 'Updated legacy answer',
            'difficulty_level' => Flashcard::DIFFICULTY_HARD,
        ];

        $response = $this->putJson("/api/units/{$this->unit->id}/flashcards/{$flashcard->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'flashcard' => [
                    'id' => $flashcard->id,
                    'unit_id' => $this->unit->id,
                    'topic_id' => null, // Should remain null
                    'question' => 'Updated legacy question?',
                ],
            ]);

        $this->assertDatabaseHas('flashcards', [
            'id' => $flashcard->id,
            'topic_id' => null,
            'question' => 'Updated legacy question?',
        ]);
    }

    public function test_legacy_unit_flashcard_deletion_unchanged(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forUnit($this->unit)->create();

        $response = $this->deleteJson("/api/units/{$this->unit->id}/flashcards/{$flashcard->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Flashcard deleted successfully',
            ]);

        $this->assertSoftDeleted('flashcards', ['id' => $flashcard->id]);
    }

    // ==================== Static Method Backward Compatibility ====================

    public function test_static_for_unit_method_compatibility(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(3)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(2)->forTopic($this->topic)->create();

        // Static method should only return unit flashcards
        $result = Flashcard::forUnit($this->unit->id);

        $this->assertCount(3, $result);
        foreach ($result as $flashcard) {
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
            $this->assertNull($flashcard->topic_id);
        }
    }

    public function test_static_for_topic_method_new_functionality(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(3)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(2)->forTopic($this->topic)->create();

        // Static method should only return topic flashcards
        $result = Flashcard::forTopic($this->topic->id);

        $this->assertCount(2, $result);
        foreach ($result as $flashcard) {
            $this->assertEquals($this->topic->id, $flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }
    }

    // ==================== Model Scope Backward Compatibility ====================

    public function test_for_unit_scope_still_works(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        $scopedResult = Flashcard::forUnit($this->unit->id);

        $this->assertCount(2, $scopedResult);
        foreach ($scopedResult as $flashcard) {
            $this->assertNull($flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }
    }

    public function test_for_topic_scope_new_functionality(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(2)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        $scopedResult = Flashcard::forTopic($this->topic->id);

        $this->assertCount(3, $scopedResult);
        foreach ($scopedResult as $flashcard) {
            $this->assertEquals($this->topic->id, $flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }
    }

    // ==================== Unit Model Method Compatibility ====================

    public function test_unit_flashcards_relationship_unchanged(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(3)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(2)->forTopic($this->topic)->create();

        // Original flashcards relationship should only return direct unit flashcards
        $directFlashcards = $this->unit->flashcards;

        $this->assertCount(3, $directFlashcards);
        foreach ($directFlashcards as $flashcard) {
            $this->assertNull($flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
        }
    }

    public function test_unit_all_flashcards_includes_both_contexts(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(3)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(2)->forTopic($this->topic)->create();

        // New allFlashcards method should include both
        $allFlashcards = $this->unit->allFlashcards()->get();

        $this->assertCount(5, $allFlashcards);

        $directCount = $allFlashcards->where('topic_id', null)->count();
        $topicCount = $allFlashcards->whereNotNull('topic_id')->count();

        $this->assertEquals(3, $directCount);
        $this->assertEquals(2, $topicCount);
    }

    public function test_unit_count_methods_work_correctly(): void
    {
        // Create mixed flashcards
        $unitFlashcards = Flashcard::factory()->count(4)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(3)->forTopic($this->topic)->create();

        // Test various count methods
        $this->assertEquals(4, $this->unit->getDirectFlashcardsCount());
        $this->assertEquals(3, $this->unit->getTopicFlashcardsCount());
        $this->assertEquals(7, $this->unit->getAllFlashcardsCount());

        $this->assertTrue($this->unit->hasDirectFlashcards());
        $this->assertTrue($this->unit->hasTopicFlashcards());
        $this->assertTrue($this->unit->hasAnyFlashcards());
    }

    // ==================== Mixed Context Operations ====================

    public function test_unit_operations_dont_affect_topic_flashcards(): void
    {
        $this->actingAs($this->user);

        // Create flashcards in both contexts
        $unitFlashcard = Flashcard::factory()->forUnit($this->unit)->create();
        $topicFlashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Bulk operation on unit should not affect topic flashcards
        $response = $this->patchJson("/api/units/{$this->unit->id}/flashcards/bulk-status", [
            'flashcard_ids' => [$unitFlashcard->id],
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        // Verify only unit flashcard was affected
        $this->assertDatabaseHas('flashcards', [
            'id' => $unitFlashcard->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('flashcards', [
            'id' => $topicFlashcard->id,
            'is_active' => true, // Should remain unchanged
        ]);
    }

    public function test_topic_operations_dont_affect_unit_flashcards(): void
    {
        $this->actingAs($this->user);

        // Create flashcards in both contexts
        $unitFlashcard = Flashcard::factory()->forUnit($this->unit)->create();
        $topicFlashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // Bulk operation on topic should not affect unit flashcards
        $response = $this->patchJson("/api/topics/{$this->topic->id}/flashcards/bulk-status", [
            'flashcard_ids' => [$topicFlashcard->id],
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        // Verify only topic flashcard was affected
        $this->assertDatabaseHas('flashcards', [
            'id' => $topicFlashcard->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('flashcards', [
            'id' => $unitFlashcard->id,
            'is_active' => true, // Should remain unchanged
        ]);
    }

    // ==================== Authorization Compatibility ====================

    public function test_unit_flashcard_authorization_unchanged(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forUnit($this->unit)->create();

        // User should be able to access their own unit flashcard
        $this->assertTrue($flashcard->canBeAccessedBy($this->user->id));

        // Other user should not be able to access
        $this->assertFalse($flashcard->canBeAccessedBy($this->otherUser->id));
    }

    public function test_topic_flashcard_authorization_works(): void
    {
        $this->actingAs($this->user);

        $flashcard = Flashcard::factory()->forTopic($this->topic)->create();

        // User should be able to access their own topic flashcard
        $this->assertTrue($flashcard->canBeAccessedBy($this->user->id));

        // Other user should not be able to access
        $this->assertFalse($flashcard->canBeAccessedBy($this->otherUser->id));
    }

    // ==================== Data Integrity and Consistency ====================

    public function test_mixed_flashcards_maintain_data_integrity(): void
    {
        $this->actingAs($this->user);

        // Create various flashcards
        $unitBasic = Flashcard::factory()->forUnit($this->unit)->basic()->create();
        $unitMC = Flashcard::factory()->forUnit($this->unit)->multipleChoice()->create();
        $topicBasic = Flashcard::factory()->forTopic($this->topic)->basic()->create();
        $topicCloze = Flashcard::factory()->forTopic($this->topic)->cloze()->create();

        // Verify each flashcard maintains its context
        $this->assertNull($unitBasic->topic_id);
        $this->assertNull($unitMC->topic_id);
        $this->assertEquals($this->topic->id, $topicBasic->topic_id);
        $this->assertEquals($this->topic->id, $topicCloze->topic_id);

        // All should belong to the same unit
        $this->assertEquals($this->unit->id, $unitBasic->unit_id);
        $this->assertEquals($this->unit->id, $unitMC->unit_id);
        $this->assertEquals($this->unit->id, $topicBasic->unit_id);
        $this->assertEquals($this->unit->id, $topicCloze->unit_id);

        // Verify counts
        $this->assertEquals(2, $this->unit->getDirectFlashcardsCount());
        $this->assertEquals(2, $this->unit->getTopicFlashcardsCount());
        $this->assertEquals(4, $this->unit->getAllFlashcardsCount());
        $this->assertEquals(2, $this->topic->getFlashcardsCount());
    }

    public function test_legacy_import_functionality_still_works(): void
    {
        $this->actingAs($this->user);

        // Test that legacy import still creates unit-based flashcards
        $importData = [
            'import_method' => 'text',
            'import_text' => "Question 1\tAnswer 1\nQuestion 2\tAnswer 2\nQuestion 3\tAnswer 3",
        ];

        // Preview import (legacy unit-based)
        $previewResponse = $this->postJson("/api/units/{$this->unit->id}/flashcards/import/preview", $importData);
        $previewResponse->assertStatus(200);

        // Perform import
        $importResponse = $this->postJson("/api/units/{$this->unit->id}/flashcards/import", [
            'import_method' => 'text',
            'import_data' => base64_encode($importData['import_text']),
            'confirm_import' => true,
        ]);

        $importResponse->assertStatus(200);

        // Verify imported flashcards are unit-based (no topic_id)
        $importedFlashcards = Flashcard::where('unit_id', $this->unit->id)
            ->where('import_source', 'manual_import')
            ->get();

        $this->assertCount(3, $importedFlashcards);

        foreach ($importedFlashcards as $flashcard) {
            $this->assertNull($flashcard->topic_id);
            $this->assertEquals($this->unit->id, $flashcard->unit_id);
            $this->assertEquals('manual_import', $flashcard->import_source);
        }
    }

    // ==================== Performance and Compatibility ====================

    public function test_large_scale_mixed_flashcards_performance(): void
    {
        // Create a substantial number of flashcards in both contexts
        $unitFlashcards = Flashcard::factory()->count(50)->forUnit($this->unit)->create();
        $topicFlashcards = Flashcard::factory()->count(50)->forTopic($this->topic)->create();

        $startTime = microtime(true);

        // Test various operations for performance
        $directCount = $this->unit->getDirectFlashcardsCount();
        $topicCount = $this->unit->getTopicFlashcardsCount();
        $allCount = $this->unit->getAllFlashcardsCount();

        $unitFlashcardsQuery = $this->unit->flashcards;
        $allFlashcardsQuery = $this->unit->allFlashcards()->get();

        $topicFlashcardsQuery = $this->topic->flashcards;

        $endTime = microtime(true);

        // Verify counts
        $this->assertEquals(50, $directCount);
        $this->assertEquals(50, $topicCount);
        $this->assertEquals(100, $allCount);
        $this->assertCount(50, $unitFlashcardsQuery);
        $this->assertCount(100, $allFlashcardsQuery);
        $this->assertCount(50, $topicFlashcardsQuery);

        // Performance should be reasonable (under 1 second for this dataset)
        $this->assertLessThan(1.0, $endTime - $startTime);
    }

    public function test_flashcard_to_array_includes_both_unit_and_topic_fields(): void
    {
        $unitFlashcard = Flashcard::factory()->forUnit($this->unit)->create();
        $topicFlashcard = Flashcard::factory()->forTopic($this->topic)->create();

        $unitArray = $unitFlashcard->toArray();
        $topicArray = $topicFlashcard->toArray();

        // Both should have unit_id and topic_id fields
        $this->assertArrayHasKey('unit_id', $unitArray);
        $this->assertArrayHasKey('topic_id', $unitArray);
        $this->assertArrayHasKey('unit_id', $topicArray);
        $this->assertArrayHasKey('topic_id', $topicArray);

        // Unit flashcard should have null topic_id
        $this->assertEquals($this->unit->id, $unitArray['unit_id']);
        $this->assertNull($unitArray['topic_id']);

        // Topic flashcard should have both set
        $this->assertEquals($this->unit->id, $topicArray['unit_id']);
        $this->assertEquals($this->topic->id, $topicArray['topic_id']);
    }
}
