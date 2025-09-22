<?php

namespace Tests\Unit\Services;

use App\Models\Flashcard;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DuplicateDetectionService $service;

    private User $user;

    private Unit $unit;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DuplicateDetectionService;
        $this->user = User::factory()->create();

        // Create a unit for testing
        $subject = \App\Models\Subject::create([
            'name' => 'Test Subject',
            'user_id' => $this->user->id,
            'color' => '#3b82f6',
        ]);

        $this->unit = \App\Models\Unit::create([
            'name' => 'Test Unit',
            'description' => 'Test unit for duplicate detection',
            'subject_id' => $subject->id,
        ]);

        $this->topic = Topic::create([
            'unit_id' => $this->unit->id,
            'title' => 'Test Topic',
            'description' => 'Test topic for duplicate detection',
            'estimated_minutes' => 30,
            'required' => true,
        ]);
    }

    public function test_detects_exact_duplicates(): void
    {
        // Create an existing flashcard
        Flashcard::create([
            'topic_id' => $this->topic->id,
            'card_type' => 'basic',
            'question' => 'What is the capital of France?',
            'answer' => 'Paris',
            'is_active' => true,
        ]);

        // Import cards with exact duplicate
        $importCards = [
            [
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
                'card_type' => 'basic',
            ],
            [
                'question' => 'What is the capital of Spain?',
                'answer' => 'Madrid',
                'card_type' => 'basic',
            ],
        ];

        $result = $this->service->detectDuplicates($importCards, $this->topic->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['duplicate_count']);
        $this->assertEquals(1, $result['unique_count']);
        $this->assertCount(1, $result['duplicates']);

        $duplicate = $result['duplicates'][0];
        $this->assertEquals(0, $duplicate['import_index']);
        $this->assertEquals('existing', $duplicate['duplicate_type']);
        $this->assertEquals(1.0, $duplicate['similarity_score']);
        $this->assertEquals('exact_match', $duplicate['match_reason']);
    }

    public function test_detects_similar_duplicates(): void
    {
        // Create an existing flashcard
        Flashcard::create([
            'topic_id' => $this->topic->id,
            'card_type' => 'basic',
            'question' => 'What is the capital of France?',
            'answer' => 'Paris',
            'is_active' => true,
        ]);

        // Import cards with similar duplicate (slight difference in wording)
        $importCards = [
            [
                'question' => 'What\'s the capital of France?',
                'answer' => 'Paris',
                'card_type' => 'basic',
            ],
        ];

        $result = $this->service->detectDuplicates($importCards, $this->topic->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['duplicate_count']);

        $duplicate = $result['duplicates'][0];
        $this->assertEquals('existing', $duplicate['duplicate_type']);
        $this->assertGreaterThan(0.8, $duplicate['similarity_score']); // High similarity threshold
        $this->assertEquals('similar_content', $duplicate['match_reason']);
    }

    public function test_detects_duplicates_within_import(): void
    {
        // Import cards with internal duplicates
        $importCards = [
            [
                'question' => 'What is 2 + 2?',
                'answer' => '4',
                'card_type' => 'basic',
            ],
            [
                'question' => 'What is the capital of Spain?',
                'answer' => 'Madrid',
                'card_type' => 'basic',
            ],
            [
                'question' => 'What is 2 + 2?', // Duplicate within import
                'answer' => '4',
                'card_type' => 'basic',
            ],
        ];

        $result = $this->service->detectDuplicates($importCards, $this->topic->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['duplicate_count']);
        $this->assertEquals(2, $result['unique_count']);

        $duplicate = $result['duplicates'][0];
        $this->assertEquals(2, $duplicate['import_index']); // Third card is duplicate
        $this->assertEquals('within_import', $duplicate['duplicate_type']);
    }

    public function test_no_duplicates_found(): void
    {
        // Create an existing flashcard
        Flashcard::create([
            'topic_id' => $this->topic->id,
            'card_type' => 'basic',
            'question' => 'What is the capital of Germany?',
            'answer' => 'Berlin',
            'is_active' => true,
        ]);

        // Import completely different cards
        $importCards = [
            [
                'question' => 'What is the capital of France?',
                'answer' => 'Paris',
                'card_type' => 'basic',
            ],
            [
                'question' => 'What is the capital of Spain?',
                'answer' => 'Madrid',
                'card_type' => 'basic',
            ],
        ];

        $result = $this->service->detectDuplicates($importCards, $this->topic->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['duplicate_count']);
        $this->assertEquals(2, $result['unique_count']);
        $this->assertEmpty($result['duplicates']);
    }

    public function test_apply_skip_merge_strategy(): void
    {
        $duplicates = [
            [
                'import_index' => 0,
                'duplicate_type' => 'existing',
                'existing_card' => ['id' => 1],
                'import_card' => [
                    'question' => 'Test question',
                    'answer' => 'Test answer',
                    'card_type' => 'basic',
                ],
            ],
        ];

        $strategy = [
            'global_action' => 'skip',
        ];

        $result = $this->service->applyMergeStrategy($duplicates, $strategy, $this->topic->id, $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['results']['skipped']);
        $this->assertEquals(0, $result['results']['updated']);
    }

    public function test_get_detection_statistics(): void
    {
        // Create some existing flashcards
        for ($i = 0; $i < 5; $i++) {
            Flashcard::create([
                'topic_id' => $this->topic->id,
                'card_type' => 'basic',
                'question' => "Question {$i}",
                'answer' => "Answer {$i}",
                'is_active' => true,
            ]);
        }

        $stats = $this->service->getDetectionStatistics($this->topic->id);

        $this->assertEquals(5, $stats['existing_cards']);
        $this->assertEquals(5, $stats['will_check_against']);
        $this->assertEquals(0.8, $stats['similarity_threshold']);
    }
}
