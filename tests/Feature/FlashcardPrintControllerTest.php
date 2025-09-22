<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashcardPrintControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subject $subject;

    private Unit $unit;

    private $flashcards;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable session middleware but disable unnecessary middleware for testing
        $this->withoutMiddleware([
            \App\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $this->user = User::factory()->create();
        $this->subject = Subject::factory()->create(['user_id' => $this->user->id]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);

        // Create test flashcards using forUnit() for backward compatibility
        $this->flashcards = collect([
            Flashcard::factory()->forUnit($this->unit)->create([
                'card_type' => 'basic',
                'question' => 'What is 2 + 2?',
                'answer' => '4',
            ]),
            Flashcard::factory()->forUnit($this->unit)->create([
                'card_type' => 'multiple_choice',
                'question' => 'Which is the largest planet?',
                'answer' => 'Jupiter',
                'choices' => ['Earth', 'Jupiter', 'Saturn', 'Mars'],
                'correct_choices' => [1],
            ]),
            Flashcard::factory()->forUnit($this->unit)->create([
                'card_type' => 'true_false',
                'question' => 'The sun is a star.',
                'answer' => 'True',
                'choices' => ['True', 'False'],
                'correct_choices' => [0],
            ]),
        ]);
    }

    public function test_shows_print_options_modal(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('flashcards.print.options', $this->unit->id));

        $response->assertStatus(200);
        $response->assertViewIs('flashcards.partials.print-modal');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('flashcards');
        $response->assertViewHas('layouts');
        $response->assertViewHas('pageSizes');
        $response->assertViewHas('totalCards', $this->flashcards->count());
    }

    public function test_print_options_requires_authentication(): void
    {
        $response = $this->get(route('flashcards.print.options', $this->unit->id));
        $response->assertRedirect(route('login'));
    }

    public function test_print_options_requires_unit_access(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('flashcards.print.options', $this->unit->id));

        $response->assertStatus(403);
    }

    public function test_print_options_handles_empty_unit(): void
    {
        $emptyUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);

        $response = $this->actingAs($this->user)
            ->get(route('flashcards.print.options', $emptyUnit->id));

        $response->assertStatus(422);
        $response->assertSeeText('No flashcards available to print');
    }

    public function test_generates_print_preview(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.preview', $this->unit->id), [
                'layout' => 'index',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
                'include_answers' => true,
                'include_hints' => true,
            ]);

        $response->assertStatus(200);
        $response->assertViewIs('flashcards.partials.print-preview');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('flashcards');
        $response->assertViewHas('layout', 'index');
        $response->assertViewHas('options');
        $response->assertViewHas('previewContent');
    }

    public function test_print_preview_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.preview', $this->unit->id), []);

        $response->assertStatus(422);
    }

    public function test_print_preview_validates_layout(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.preview', $this->unit->id), [
                'layout' => 'invalid_layout',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        $response->assertStatus(422);
    }

    public function test_print_preview_validates_page_size(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.preview', $this->unit->id), [
                'layout' => 'index',
                'page_size' => 'invalid_size',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        $response->assertStatus(422);
    }

    public function test_print_preview_with_selected_cards(): void
    {
        $selectedCards = $this->flashcards->take(2)->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.preview', $this->unit->id), [
                'layout' => 'grid',
                'page_size' => 'a4',
                'font_size' => 'large',
                'color_mode' => 'grayscale',
                'margin' => 'wide',
                'include_answers' => false,
                'include_hints' => false,
                'selected_cards' => $selectedCards,
            ]);

        $response->assertStatus(200);
        $response->assertViewHas('flashcards', function ($flashcards) use ($selectedCards) {
            return $flashcards->count() === count($selectedCards) &&
                   $flashcards->pluck('id')->sort()->values()->toArray() === collect($selectedCards)->sort()->values()->toArray();
        });
    }

    public function test_downloads_pdf(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'index',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
                'include_answers' => true,
                'include_hints' => true,
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_download_requires_authentication(): void
    {
        $response = $this->post(route('flashcards.print.download', $this->unit->id), [
            'layout' => 'index',
            'page_size' => 'letter',
            'font_size' => 'medium',
            'color_mode' => 'color',
            'margin' => 'normal',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_pdf_download_validates_access(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'index',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Access denied']);
    }

    public function test_pdf_download_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), []);

        $response->assertStatus(422);

        // Since this is an API endpoint that should return validation errors,
        // just verify we get the correct status code and it's a validation error
        $this->assertEquals(422, $response->status());

        // If it's JSON, check the structure
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $responseData = $response->json();
            // Laravel validation responses typically have a 'message' field and 'errors' field
            $this->assertTrue(
                isset($responseData['message']) || isset($responseData['errors']) || isset($responseData['error']),
                'Response should contain validation error information'
            );
        }
    }

    public function test_pdf_download_with_selected_cards(): void
    {
        $selectedCards = $this->flashcards->take(1)->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'study_sheet',
                'page_size' => 'a4',
                'font_size' => 'small',
                'color_mode' => 'color',
                'margin' => 'tight',
                'include_answers' => true,
                'include_hints' => false,
                'selected_cards' => $selectedCards,
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_download_handles_empty_selection(): void
    {
        $emptyUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);

        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $emptyUnit->id), [
                'layout' => 'index',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'No flashcards available to print']);
    }

    public function test_shows_bulk_print_selection(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('flashcards.print.bulk_selection', $this->unit->id));

        $response->assertStatus(200);
        $response->assertViewIs('flashcards.partials.bulk-print-selection');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('flashcards');
        $response->assertSeeText('Select specific flashcards');
    }

    public function test_bulk_selection_shows_paginated_results(): void
    {
        // Create more flashcards to test pagination
        for ($i = 4; $i <= 55; $i++) {
            Flashcard::factory()->forUnit($this->unit)->create([
                'card_type' => 'basic',
                'question' => "Question {$i}",
                'answer' => "Answer {$i}",
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get(route('flashcards.print.bulk_selection', $this->unit->id));

        $response->assertStatus(200);
        $response->assertViewHas('flashcards', function ($flashcards) {
            return $flashcards->hasPages() && $flashcards->perPage() === 50;
        });
    }

    public function test_bulk_selection_requires_authentication(): void
    {
        $response = $this->get(route('flashcards.print.bulk_selection', $this->unit->id));
        $response->assertRedirect(route('login'));
    }

    public function test_bulk_selection_validates_access(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get(route('flashcards.print.bulk_selection', $this->unit->id));

        $response->assertStatus(403);
    }

    public function test_all_print_layouts_work(): void
    {
        $layouts = ['index', 'grid', 'foldable', 'study_sheet'];

        foreach ($layouts as $layout) {
            $response = $this->actingAs($this->user)
                ->post(route('flashcards.print.download', $this->unit->id), [
                    'layout' => $layout,
                    'page_size' => 'letter',
                    'font_size' => 'medium',
                    'color_mode' => 'color',
                    'margin' => 'normal',
                    'include_answers' => true,
                    'include_hints' => true,
                ]);

            $response->assertStatus(200, "Failed for layout: {$layout}");
            $response->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_all_page_sizes_work(): void
    {
        $pageSizes = ['letter', 'a4', 'legal', 'index35', 'index46'];

        foreach ($pageSizes as $pageSize) {
            $response = $this->actingAs($this->user)
                ->post(route('flashcards.print.download', $this->unit->id), [
                    'layout' => 'index',
                    'page_size' => $pageSize,
                    'font_size' => 'medium',
                    'color_mode' => 'color',
                    'margin' => 'normal',
                    'include_answers' => true,
                    'include_hints' => true,
                ]);

            $response->assertStatus(200, "Failed for page size: {$pageSize}");
            $response->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_pdf_filename_is_descriptive(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'index',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        // With CSRF disabled, test should now work properly
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Test that the filename includes the unit name (formatted for filename)
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('flashcards-', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);
        $this->assertStringContainsString('index', $contentDisposition);
    }

    public function test_handles_large_flashcard_sets(): void
    {
        // Create 100 flashcards to test performance
        for ($i = 4; $i <= 100; $i++) {
            Flashcard::factory()->forUnit($this->unit)->create([
                'card_type' => 'basic',
                'question' => "Performance test question {$i}",
                'answer' => "Performance test answer {$i}",
            ]);
        }

        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'grid',
                'page_size' => 'letter',
                'font_size' => 'small',
                'color_mode' => 'grayscale',
                'margin' => 'tight',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Check that response is reasonably sized (not empty, not too large)
        $content = $response->getContent();
        $this->assertGreaterThan(10000, strlen($content)); // At least 10KB
        $this->assertLessThan(50000000, strlen($content)); // Less than 50MB
    }

    public function test_handles_special_characters_in_pdf(): void
    {
        // Create flashcard with special characters
        Flashcard::factory()->forUnit($this->unit)->create([
            'card_type' => 'basic',
            'question' => 'What is the formula for water? H₂O & other symbols: ñáéíóú',
            'answer' => 'H₂O (dihydrogen monoxide) — it\'s essential for life!',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('flashcards.print.download', $this->unit->id), [
                'layout' => 'study_sheet',
                'page_size' => 'letter',
                'font_size' => 'medium',
                'color_mode' => 'color',
                'margin' => 'normal',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
