<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->child = Child::factory()->create(['user_id' => $this->user->id]);
    }

    #[Test]
    public function it_can_complete_full_workflow_subject_unit_topic_flashcard()
    {
        $this->actingAs($this->user);

        // Step 1: Create Subject
        $subjectData = [
            'name' => 'Mathematics',
            'color' => '#3b82f6', // Blue color
            'child_id' => $this->child->id,
        ];

        $subjectResponse = $this->post(route('subjects.store'), $subjectData);
        $subjectResponse->assertRedirect();

        $subject = Subject::where('name', 'Mathematics')->first();
        $this->assertNotNull($subject);
        $this->assertEquals($this->user->id, $subject->user_id);

        // Step 2: Create Unit within Subject
        $unitData = [
            'name' => 'Algebra Basics',
            'description' => 'Introduction to algebraic concepts',
            'target_completion_date' => now()->addDays(30)->format('Y-m-d'),
        ];

        $unitResponse = $this->post(route('subjects.units.store', $subject->id), $unitData);
        $unitResponse->assertRedirect();

        $unit = Unit::where('name', 'Algebra Basics')->first();
        $this->assertNotNull($unit);
        $this->assertEquals($subject->id, $unit->subject_id);

        // Step 3: Create Topic within Unit
        $topicData = [
            'title' => 'Linear Equations',
            'description' => 'Solving linear equations with one variable',
            'estimated_minutes' => 45,
            'required' => true,
        ];

        // Test the route that was causing issues
        $createTopicUrl = route('topics.create', [$subject->id, $unit->id]);
        $this->assertStringContainsString((string) $subject->id, $createTopicUrl);
        $this->assertStringContainsString((string) $unit->id, $createTopicUrl);

        // Test accessing the create form
        $createFormResponse = $this->get($createTopicUrl);
        $createFormResponse->assertOk();

        // Create the topic
        $topicResponse = $this->post(route('units.topics.store', $unit->id), $topicData);
        $topicResponse->assertRedirect();

        $topic = Topic::where('title', 'Linear Equations')->first();
        $this->assertNotNull($topic);
        $this->assertEquals($unit->id, $topic->unit_id);

        // Step 4: Create Flashcard within Topic
        $flashcardData = [
            'card_type' => 'basic',
            'question' => 'What is the solution to x + 5 = 12?',
            'answer' => 'x = 7',
            'hint' => 'Subtract 5 from both sides',
            'difficulty_level' => 'easy',
            'tags' => 'algebra,linear,basic',
            'topic_id' => $topic->id,
        ];

        $flashcardResponse = $this->post(route('topics.flashcards.store', $topic->id), $flashcardData);

        // The route returns either JSON (API) or HTML (form submission) based on request type
        // For form submission, it returns 200 with HTML view showing the flashcard list
        $flashcardResponse->assertStatus(200);
        $flashcardResponse->assertSee('What is the solution to x + 5 = 12?'); // Check flashcard appears in response

        $flashcard = Flashcard::where('question', 'What is the solution to x + 5 = 12?')->first();
        $this->assertNotNull($flashcard);
        $this->assertEquals($topic->id, $flashcard->topic_id);
        $this->assertEquals('x = 7', $flashcard->answer);

        // Step 5: Verify complete hierarchy and relationships

        // Test subject->units relationship
        $this->assertTrue($subject->units->contains($unit));

        // Test unit->topics relationship
        $this->assertTrue($unit->topics->contains($topic));

        // Test topic->flashcards relationship
        $this->assertTrue($topic->flashcards->contains($flashcard));

        // Test unit->allFlashcards relationship (topic-only architecture)
        $unitFlashcards = $unit->allFlashcards;
        $this->assertTrue($unitFlashcards->contains($flashcard));
        $this->assertEquals(1, $unit->getAllFlashcardsCount());

        // Test that direct unit flashcards count is 0 (topic-only architecture)
        $this->assertEquals(0, $unit->getDirectFlashcardsCount());

        // Test subject->allFlashcards through units and topics
        $subjectFlashcards = $subject->units()
            ->with('topics.flashcards')
            ->get()
            ->pluck('topics')
            ->flatten()
            ->pluck('flashcards')
            ->flatten();
        $this->assertTrue($subjectFlashcards->contains($flashcard));

        // Step 6: Test navigation routes work correctly

        // Test subject show route
        $subjectShowResponse = $this->get(route('subjects.show', $subject->id));
        $subjectShowResponse->assertOk();
        $subjectShowResponse->assertSee('Algebra Basics'); // Unit should be visible

        // Test unit show route
        $unitShowResponse = $this->get(route('subjects.units.show', [$subject->id, $unit->id]));
        $unitShowResponse->assertOk();
        $unitShowResponse->assertSee('Linear Equations'); // Topic should be visible

        // Test topic show route
        $topicShowResponse = $this->get(route('units.topics.show', [$unit->id, $topic->id]));
        $topicShowResponse->assertOk();

        // The topic page loads flashcards via HTMX, so check the HTMX attributes are correct
        $topicShowResponse->assertSee(route('topics.flashcards.list', $topic->id), false); // false = don't escape HTML

        // Also test the flashcard list endpoint directly (what HTMX would call)
        $flashcardListResponse = $this->get(route('topics.flashcards.list', $topic->id));
        $flashcardListResponse->assertOk();
        $flashcardListResponse->assertSee('What is the solution to x + 5 = 12?'); // Flashcard should be visible in list

        // Step 7: Test that topic creation route parameters are correct in views
        $unitShowResponse->assertSee(route('topics.create', [$subject->id, $unit->id]));
    }

    #[Test]
    public function it_can_create_multiple_flashcard_types_in_topic()
    {
        $this->actingAs($this->user);

        // Setup hierarchy
        $subject = Subject::factory()->create([
            'user_id' => $this->user->id,
            'child_id' => $this->child->id,
        ]);
        $unit = Unit::factory()->create(['subject_id' => $subject->id]);
        $topic = Topic::factory()->create(['unit_id' => $unit->id]);

        // Create basic flashcard
        $basicFlashcard = $this->createFlashcard($topic, [
            'card_type' => 'basic',
            'question' => 'What is 2 + 2?',
            'answer' => '4',
        ]);

        // Create multiple choice flashcard
        $mcFlashcard = $this->createFlashcard($topic, [
            'card_type' => 'multiple_choice',
            'question' => 'Which are even numbers?',
            'answer' => '2, 4', // Required field
            'choices' => ['2', '3', '4', '5'],
            'correct_choices' => [0, 2], // 2 and 4
        ]);

        // Create true/false flashcard
        $tfFlashcard = $this->createFlashcard($topic, [
            'card_type' => 'true_false',
            'question' => 'The Earth is round',
            'answer' => 'True', // Required field
            'true_false_answer' => 'true', // Required field for true/false
        ]);

        // Verify all flashcards belong to the topic
        $this->assertEquals(3, $topic->flashcards()->count());
        $this->assertEquals(3, $unit->getAllFlashcardsCount());

        // Verify different card types work
        $this->assertEquals('basic', $basicFlashcard->card_type);
        $this->assertEquals('multiple_choice', $mcFlashcard->card_type);
        $this->assertEquals('true_false', $tfFlashcard->card_type);
    }

    #[Test]
    public function it_maintains_topic_only_architecture_constraints()
    {
        $this->actingAs($this->user);

        $subject = Subject::factory()->create([
            'user_id' => $this->user->id,
            'child_id' => $this->child->id,
        ]);
        $unit = Unit::factory()->create(['subject_id' => $subject->id]);
        $topic = Topic::factory()->create(['unit_id' => $unit->id]);

        // Create flashcard - should require topic_id
        $flashcard = Flashcard::factory()->forTopic($topic)->create();

        // Verify topic-only constraints
        $this->assertNotNull($flashcard->topic_id);
        $this->assertEquals($topic->id, $flashcard->topic_id);

        // Unit should have 0 direct flashcards (topic-only architecture)
        $this->assertEquals(0, $unit->getDirectFlashcardsCount());

        // But unit should see flashcards through topics
        $this->assertEquals(1, $unit->getAllFlashcardsCount());
        $this->assertTrue($unit->allFlashcards->contains($flashcard));

        // Topic should have the flashcard
        $this->assertEquals(1, $topic->flashcards()->count());
        $this->assertTrue($topic->flashcards->contains($flashcard));
    }

    #[Test]
    public function it_handles_route_generation_correctly()
    {
        $this->actingAs($this->user);

        $subject = Subject::factory()->create([
            'user_id' => $this->user->id,
            'child_id' => $this->child->id,
        ]);
        $unit = Unit::factory()->create(['subject_id' => $subject->id]);

        // Test that route generation includes both subject and unit parameters
        $createRoute = route('topics.create', [$subject->id, $unit->id]);

        // Verify route contains both IDs
        $this->assertStringContainsString((string) $subject->id, $createRoute);
        $this->assertStringContainsString((string) $unit->id, $createRoute);

        // Verify route is accessible
        $response = $this->get($createRoute);
        $response->assertOk();

        // Should have both subject and unit in view data
        $response->assertViewHas('subject', $subject);
        $response->assertViewHas('unit', $unit);
    }

    private function createFlashcard(Topic $topic, array $data): Flashcard
    {
        $defaultData = [
            'card_type' => 'basic',
            'question' => 'Test Question',
            'answer' => 'Test Answer',
            'difficulty_level' => 'easy',
            'topic_id' => $topic->id,
        ];

        $flashcardData = array_merge($defaultData, $data);

        $response = $this->post(route('topics.flashcards.store', $topic->id), $flashcardData);
        $response->assertStatus(200); // Form submission returns HTML view
        $response->assertSee($flashcardData['question']); // Check flashcard appears in response

        return Flashcard::where('question', $flashcardData['question'])->first();
    }
}
