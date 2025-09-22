<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlashcardCardTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subject $subject;

    private Unit $unit;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->subject = Subject::factory()->for($this->user)->create();
        $this->unit = Unit::factory()->for($this->subject)->create();
        $this->topic = Topic::factory()->for($this->unit)->create();
    }

    #[Test]
    public function it_creates_basic_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'basic',
            'question' => 'What is the capital of France?',
            'answer' => 'Paris',
            'hint' => 'City of light',
            'difficulty_level' => 'easy',
            'tags' => 'geography,europe',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('basic', $flashcard->card_type);
        $this->assertEquals('What is the capital of France?', $flashcard->question);
        $this->assertEquals('Paris', $flashcard->answer);
        $this->assertEquals('City of light', $flashcard->hint);
        $this->assertEquals(['geography', 'europe'], $flashcard->tags);
    }

    #[Test]
    public function it_creates_multiple_choice_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'multiple_choice',
                '_token' => 'test-token',
                'question' => 'Which of these are programming languages?',
                'answer' => 'PHP, JavaScript',
                'choices' => ['PHP', 'HTML', 'JavaScript', 'CSS'],
                'correct_choices' => [0, 2], // PHP and JavaScript
                'difficulty_level' => 'medium',
                'tags' => 'programming,code',
            ]);
        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('multiple_choice', $flashcard->card_type);
        $this->assertEquals(['PHP', 'HTML', 'JavaScript', 'CSS'], $flashcard->choices);
        $this->assertEquals([0, 2], $flashcard->correct_choices);
    }

    #[Test]
    public function it_validates_multiple_choice_minimum_choices()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'multiple_choice',
                '_token' => 'test-token',
                'question' => 'Choose the best option',
                'answer' => 'Option A',
                'choices' => ['Option A'], // Only one choice - invalid
                'correct_choices' => [0],
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.choices.0', 'Multiple choice cards must have at least 2 choices.');
    }

    #[Test]
    public function it_validates_multiple_choice_correct_choices()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'multiple_choice',
                '_token' => 'test-token',
                'question' => 'Choose the best option',
                'answer' => 'Option A',
                'choices' => ['Option A', 'Option B', 'Option C'],
                'correct_choices' => [], // No correct choices - invalid
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.correct_choices.0', 'Multiple choice cards must have at least 1 correct choice.');
    }

    #[Test]
    public function it_creates_true_false_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'true_false',
                '_token' => 'test-token',
                'question' => 'The Earth is round',
                'answer' => 'True',
                'true_false_answer' => 'true',
                'difficulty_level' => 'easy',
                'tags' => 'geography, science',
            ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('true_false', $flashcard->card_type);
        $this->assertEquals(['True', 'False'], $flashcard->choices);
        $this->assertEquals([0], $flashcard->correct_choices); // True = index 0
        $this->assertEquals('True', $flashcard->answer);
    }

    #[Test]
    public function it_creates_false_answer_true_false_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'true_false',
                '_token' => 'test-token',
                'question' => 'The Earth is flat',
                'answer' => 'False',
                'true_false_answer' => 'false',
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals([1], $flashcard->correct_choices); // False = index 1
        $this->assertEquals('False', $flashcard->answer);
    }

    #[Test]
    public function it_validates_true_false_answer_required()
    {
        $this->actingAs($this->user);

        $response = $this->withSession(['_token' => 'test-token'])
            ->postJson(route('api.topics.flashcards.store', $this->topic->id), [
                'card_type' => 'true_false',
                '_token' => 'test-token',
                'question' => 'The Earth is round',
                'answer' => 'True',
                // Missing true_false_answer
                'difficulty_level' => 'easy',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.true_false_answer.0', 'The true false answer field is required.');
    }

    #[Test]
    public function it_creates_cloze_deletion_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            'cloze_text' => 'The {{capital}} of France is {{Paris}}.',
            'difficulty_level' => 'medium',
            'tags' => 'geography',
        ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('cloze', $flashcard->card_type);
        $this->assertEquals('The {{capital}} of France is {{Paris}}.', $flashcard->cloze_text);
        $this->assertEquals(['capital', 'Paris'], $flashcard->cloze_answers);
        $this->assertStringContainsString('[...]', $flashcard->question);
        $this->assertEquals('capital, Paris', $flashcard->answer);
    }

    #[Test]
    public function it_validates_cloze_text_required()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            // Missing cloze_text
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.cloze_text.0', 'Cloze deletion cards must have cloze text with {{}} syntax.');
    }

    #[Test]
    public function it_validates_cloze_syntax()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            'cloze_text' => 'This text has no cloze deletions.',
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cloze_text']);
    }

    #[Test]
    public function it_creates_typed_answer_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'typed_answer',
            'question' => 'What is the capital of Japan?',
            'answer' => 'Tokyo',
            'difficulty_level' => 'easy',
        ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('typed_answer', $flashcard->card_type);
        $this->assertEquals('What is the capital of Japan?', $flashcard->question);
        $this->assertEquals('Tokyo', $flashcard->answer);
    }

    #[Test]
    public function it_creates_image_occlusion_flashcard()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'image_occlusion',
            'question' => 'Identify the highlighted organ',
            'answer' => 'Heart',
            'question_image_url' => 'https://example.com/anatomy.jpg',
            'answer_image_url' => 'https://example.com/heart-highlighted.jpg',
            'occlusion_data' => [
                ['x' => 100, 'y' => 50, 'width' => 80, 'height' => 60],
            ],
            'difficulty_level' => 'hard',
        ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals('image_occlusion', $flashcard->card_type);
        $this->assertEquals('https://example.com/anatomy.jpg', $flashcard->question_image_url);
        $this->assertEquals('https://example.com/heart-highlighted.jpg', $flashcard->answer_image_url);
    }

    #[Test]
    public function it_validates_image_occlusion_image_url_required()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'image_occlusion',
            'question' => 'Identify the highlighted organ',
            'answer' => 'Heart',
            // Missing question_image_url
            'difficulty_level' => 'hard',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.question_image_url.0', 'Image occlusion cards must have a question image URL.');
    }

    #[Test]
    public function it_validates_image_url_format()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'image_occlusion',
            'question' => 'Identify the highlighted organ',
            'answer' => 'Heart',
            'question_image_url' => 'not-a-valid-url',
            'difficulty_level' => 'hard',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.question_image_url.0', 'Question image URL must be a valid URL.');
    }

    #[Test]
    public function it_updates_flashcard_with_card_type_change()
    {
        $this->actingAs($this->user);

        // Create a basic flashcard
        $flashcard = Flashcard::factory()->basic()->forTopic($this->topic)->create();

        // Update to multiple choice
        $response = $this->putJson(route('api.topics.flashcards.update', [$this->topic->id, $flashcard->id]), [
            'card_type' => 'multiple_choice',
            'question' => 'Updated question',
            'answer' => 'Option A',
            'choices' => ['Option A', 'Option B', 'Option C'],
            'correct_choices' => [0],
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(200);

        $flashcard->refresh();
        $this->assertEquals('multiple_choice', $flashcard->card_type);
        $this->assertEquals(['Option A', 'Option B', 'Option C'], $flashcard->choices);
        $this->assertEquals([0], $flashcard->correct_choices);
    }

    #[Test]
    public function it_rejects_invalid_card_type()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'invalid_type',
            'question' => 'Test question',
            'answer' => 'Test answer',
            'difficulty_level' => 'easy',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['card_type']);
    }

    #[Test]
    public function it_validates_unique_choices_in_multiple_choice()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'multiple_choice',
            'question' => 'Choose unique options',
            'answer' => 'Option A',
            'choices' => ['Option A', 'Option A', 'Option B'], // Duplicate choices
            'correct_choices' => [0],
            'difficulty_level' => 'easy',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['choices']);
    }

    #[Test]
    public function it_validates_correct_choice_indices_in_multiple_choice()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'multiple_choice',
            'question' => 'Choose the best option',
            'answer' => 'Option A',
            'choices' => ['Option A', 'Option B'],
            'correct_choices' => [5], // Index 5 doesn't exist
            'difficulty_level' => 'easy',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['correct_choices']);
    }

    #[Test]
    public function it_validates_empty_cloze_deletions()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            'cloze_text' => 'This has an {{}} empty deletion.',
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cloze_text']);
    }

    #[Test]
    public function it_validates_nested_cloze_syntax()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            'cloze_text' => 'This has {{nested {{bad}} syntax}}.',
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cloze_text']);
    }

    #[Test]
    public function it_validates_image_file_extension()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'image_occlusion',
            'question' => 'Identify the organ',
            'answer' => 'Heart',
            'question_image_url' => 'https://example.com/document.pdf', // Not an image
            'difficulty_level' => 'hard',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['question_image_url']);
    }

    #[Test]
    public function it_handles_anki_style_cloze_syntax()
    {
        $this->actingAs($this->user);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'cloze',
            'cloze_text' => 'The {{c1::mitochondria}} is the {{c2::powerhouse}} of the cell.',
            'difficulty_level' => 'medium',
        ]);

        $response->assertStatus(201);

        $flashcard = Flashcard::first();
        $this->assertEquals(['mitochondria', 'powerhouse'], $flashcard->cloze_answers);
        $this->assertEquals('mitochondria, powerhouse', $flashcard->answer);
    }

    #[Test]
    public function it_prevents_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->postJson(route('api.topics.flashcards.store', $this->topic->id), [
            'card_type' => 'basic',
            'question' => 'Unauthorized question',
            'answer' => 'Should not work',
            'difficulty_level' => 'easy',
        ]);

        $response->assertStatus(403);
        $this->assertCount(0, Flashcard::all());
    }
}
