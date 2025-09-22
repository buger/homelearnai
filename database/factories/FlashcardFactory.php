<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flashcard>
 */
class FlashcardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Flashcard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'card_type' => fake()->randomElement(Flashcard::getCardTypes()),
            'question' => fake()->sentence().'?',
            'answer' => fake()->sentence(),
            'hint' => fake()->optional()->sentence(),
            'choices' => [],
            'correct_choices' => [],
            'cloze_text' => null,
            'cloze_answers' => [],
            'question_image_url' => null,
            'answer_image_url' => null,
            'occlusion_data' => [],
            'difficulty_level' => fake()->randomElement(Flashcard::getDifficultyLevels()),
            'tags' => [],
            'is_active' => true,
            'import_source' => null,
        ];
    }

    /**
     * Create a basic flashcard.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_BASIC,
        ]);
    }

    /**
     * Create a multiple choice flashcard.
     */
    public function multipleChoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_MULTIPLE_CHOICE,
            'choices' => ['Option A', 'Option B', 'Option C', 'Option D'],
            'correct_choices' => [0, 2], // First and third options correct
        ]);
    }

    /**
     * Create a true/false flashcard.
     */
    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_TRUE_FALSE,
            'choices' => ['True', 'False'],
            'correct_choices' => [fake()->boolean() ? 0 : 1],
        ]);
    }

    /**
     * Create a cloze deletion flashcard.
     */
    public function cloze(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_CLOZE,
            'cloze_text' => 'The {{c1::capital}} of France is {{c2::Paris}}.',
            'cloze_answers' => ['capital', 'Paris'],
        ]);
    }

    /**
     * Create a typed answer flashcard.
     */
    public function typedAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_TYPED_ANSWER,
        ]);
    }

    /**
     * Create an image occlusion flashcard.
     */
    public function imageOcclusion(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => Flashcard::CARD_TYPE_IMAGE_OCCLUSION,
            'question_image_url' => fake()->imageUrl(),
            'occlusion_data' => [
                [
                    'type' => 'rectangle',
                    'x' => 100,
                    'y' => 150,
                    'width' => 200,
                    'height' => 50,
                    'answer' => 'Hidden text',
                ],
            ],
        ]);
    }

    /**
     * Create an easy flashcard.
     */
    public function easy(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => Flashcard::DIFFICULTY_EASY,
        ]);
    }

    /**
     * Create a medium difficulty flashcard.
     */
    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => Flashcard::DIFFICULTY_MEDIUM,
        ]);
    }

    /**
     * Create a hard flashcard.
     */
    public function hard(): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty_level' => Flashcard::DIFFICULTY_HARD,
        ]);
    }

    /**
     * Create an inactive flashcard.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a flashcard with tags.
     */
    public function withTags(?array $tags = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags ?? fake()->words(rand(1, 3)),
        ]);
    }

    /**
     * Create a flashcard from an import source.
     */
    public function imported(string $source = 'manual'): static
    {
        return $this->state(fn (array $attributes) => [
            'import_source' => $source,
        ]);
    }

    /**
     * Create a flashcard for a specific topic.
     */
    public function forTopic(Topic $topic): static
    {
        return $this->state(fn (array $attributes) => [
            'topic_id' => $topic->id,
            'unit_id' => $topic->unit_id, // Ensure unit_id matches the topic's unit
        ]);
    }

    /**
     * Create a flashcard for a specific unit.
     * In the topic-only architecture, this creates a flashcard via a default topic
     * to maintain backward compatibility while respecting the topic-only constraint.
     *
     * @deprecated This method is for backward compatibility in tests only.
     * In production, all flashcards should be created via topics.
     */
    public function forUnit(\App\Models\Unit $unit): static
    {
        return $this->state(function (array $attributes) use ($unit) {
            // Create a default topic for the unit if needed for backward compatibility
            $topic = \App\Models\Topic::firstOrCreate([
                'unit_id' => $unit->id,
                'title' => 'Default Topic for Unit Tests',
            ], [
                'description' => 'Auto-created topic for unit-level flashcards (backward compatibility)',
                'required' => false,
                'estimated_minutes' => 30,
            ]);

            return [
                'topic_id' => $topic->id,
                // Note: unit_id will be automatically set by the topic relationship
                // but we can still set it explicitly for clarity
                'unit_id' => $unit->id,
            ];
        });
    }
}
