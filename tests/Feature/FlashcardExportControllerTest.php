<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\FlashcardExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlashcardExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subject $subject;

    private Unit $unit;

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

        // Create sample flashcards using forUnit factory method for topic-only architecture
        Flashcard::factory()->count(10)->forUnit($this->unit)->create([
            'card_type' => 'basic',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_shows_export_options_modal_for_authenticated_user()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('flashcards.export.options', $this->unit->id));

        $response->assertOk();
        $response->assertViewIs('flashcards.partials.export-modal');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('exportFormats');
        $response->assertViewHas('totalCards', 10);
        $response->assertViewHas('maxExportSize', FlashcardExportService::MAX_EXPORT_SIZE);
    }

    #[Test]
    public function it_denies_access_to_export_options_for_unauthenticated_user()
    {
        $response = $this->get(route('flashcards.export.options', $this->unit->id));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_denies_access_to_export_options_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->get(route('flashcards.export.options', $this->unit->id));

        $response->assertForbidden();
    }

    #[Test]
    public function it_shows_no_flashcards_message_when_unit_is_empty()
    {
        $emptyUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->actingAs($this->user);

        $response = $this->get(route('flashcards.export.options', $emptyUnit->id));

        $response->assertStatus(422);
        $response->assertSee('No flashcards available to export');
    }

    #[Test]
    public function it_generates_export_preview_with_valid_data()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'json',
            'include_metadata' => true,
        ]);

        $response->assertOk();
        $response->assertViewIs('flashcards.partials.export-preview');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('format', 'json');
        $response->assertViewHas('formatName', 'JSON Export (.json)');
        $response->assertViewHas('totalCards', 10);
        $response->assertViewHas('canExport', true);
    }

    #[Test]
    public function it_validates_export_format_in_preview()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'invalid_format',
        ]);

        $response->assertStatus(422);
        $response->assertSee('The selected export format is invalid.');
    }

    #[Test]
    public function it_handles_selected_cards_in_preview()
    {
        $this->actingAs($this->user);
        $selectedCards = $this->unit->allFlashcards()->take(3)->pluck('id')->toArray();

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'csv',
            'selected_cards' => $selectedCards,
        ]);

        $response->assertOk();
        $response->assertViewHas('totalCards', 3);
    }

    #[Test]
    public function it_validates_anki_deck_name_in_preview()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'anki',
            'deck_name' => '', // Empty deck name should fail
        ]);

        $response->assertStatus(422);
        $response->assertSee('Deck name cannot be empty');
    }

    #[Test]
    public function it_downloads_json_export_successfully()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
            'include_metadata' => true,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertHeader('content-disposition');

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('exported_at', $content);
        $this->assertArrayHasKey('flashcards', $content);
        $this->assertEquals(10, $content['total_cards']);
    }

    #[Test]
    public function it_downloads_csv_export_successfully()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'csv',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $lines = array_values(array_filter(explode("\n", $content), function ($line) {
            return trim($line) !== '';
        }));
        $this->assertCount(11, $lines); // 10 cards + header

        // Check header
        $header = str_getcsv($lines[0]);
        $this->assertContains('ID', $header);
        $this->assertContains('Question', $header);
        $this->assertContains('Answer', $header);
    }

    #[Test]
    public function it_downloads_quizlet_tsv_export_successfully()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'quizlet',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/tab-separated-values; charset=UTF-8');

        $content = $response->getContent();
        $lines = explode("\n", $content);
        $this->assertCount(10, $lines);

        // Check that each line has tab-separated values
        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            $this->assertCount(2, $parts);
        }
    }

    #[Test]
    public function it_downloads_anki_export_with_deck_name()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'anki',
            'deck_name' => 'My Custom Deck',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $filename = $response->headers->get('content-disposition');
        $this->assertStringContainsString('.apkg', $filename);
    }

    #[Test]
    public function it_downloads_mnemosyne_xml_export()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'mnemosyne',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/xml');

        $content = $response->getContent();
        $this->assertStringContainsString('<?xml', $content);
        $this->assertStringContainsString('<mnemosyne', $content);
        $this->assertStringContainsString('<card>', $content);
    }

    #[Test]
    public function it_downloads_supermemo_txt_export()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'supermemo',
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Q:', $content);
        $this->assertStringContainsString('A:', $content);
    }

    #[Test]
    public function it_handles_export_with_selected_cards_only()
    {
        $this->actingAs($this->user);
        $selectedCards = $this->unit->allFlashcards()->take(3)->pluck('id')->toArray();

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
            'selected_cards' => $selectedCards,
        ]);

        $response->assertOk();

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(3, $content['total_cards']);
        $this->assertCount(3, $content['flashcards']);
    }

    #[Test]
    public function it_returns_error_when_no_flashcards_selected_for_export()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
            'selected_cards' => [], // Empty selection
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'No flashcards available to export']);
    }

    #[Test]
    public function it_validates_export_download_parameters()
    {
        $this->actingAs($this->user);

        // Missing export format
        $response = $this->post(route('flashcards.export.download', $this->unit->id), []);
        $response->assertStatus(422);

        // Invalid export format
        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'invalid',
        ]);
        $response->assertStatus(422);

        // Invalid deck name (too long)
        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'anki',
            'deck_name' => str_repeat('x', 101),
        ]);
        $response->assertStatus(422);
    }

    #[Test]
    public function it_shows_bulk_export_selection_interface()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('flashcards.export.bulk_selection', $this->unit->id));

        $response->assertOk();
        $response->assertViewIs('flashcards.partials.bulk-export-selection');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('flashcards');
        $response->assertViewHas('exportFormats');
        $response->assertViewHas('totalCards', 10);
    }

    #[Test]
    public function it_returns_export_statistics()
    {
        // Create flashcards with different types and difficulties
        Flashcard::factory()->forUnit($this->unit)->create([
            'card_type' => 'multiple_choice',
            'difficulty_level' => 'easy',
            'hint' => 'Test hint',
            'tags' => ['test', 'sample'],
        ]);

        Flashcard::factory()->forUnit($this->unit)->create([
            'card_type' => 'true_false',
            'difficulty_level' => 'hard',
            'question_image_url' => 'http://example.com/image.jpg',
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('flashcards.export.stats', $this->unit->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stats' => [
                'total_cards' => 12, // 10 + 2 new cards
                'max_export_size' => FlashcardExportService::MAX_EXPORT_SIZE,
                'can_export_all' => true,
            ],
            'unit' => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ],
        ]);

        $stats = $response->json('stats');
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('by_difficulty', $stats);
        $this->assertArrayHasKey('with_images', $stats);
        $this->assertArrayHasKey('with_hints', $stats);
        $this->assertArrayHasKey('with_tags', $stats);

        // Check specific counts
        $this->assertEquals(10, $stats['by_type']['basic']); // Original basic cards
        $this->assertEquals(1, $stats['by_type']['multiple_choice']);
        $this->assertEquals(1, $stats['by_type']['true_false']);
        $this->assertEquals(1, $stats['with_images']);
        $this->assertGreaterThanOrEqual(1, $stats['with_hints']); // At least 1 (manually created), but factory may create more
        $this->assertEquals(1, $stats['with_tags']);
    }

    #[Test]
    public function it_denies_unauthorized_access_to_export_endpoints()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        // Export options
        $response = $this->get(route('flashcards.export.options', $this->unit->id));
        $response->assertForbidden();

        // Export preview
        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'json',
        ]);
        $response->assertForbidden();

        // Export download
        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
        ]);
        $response->assertForbidden();

        // Bulk selection
        $response = $this->get(route('flashcards.export.bulk_selection', $this->unit->id));
        $response->assertForbidden();

        // Export stats
        $response = $this->get(route('flashcards.export.stats', $this->unit->id));
        $response->assertForbidden();
    }

    #[Test]
    public function it_handles_large_exports_with_warning()
    {
        // Create many flashcards to exceed the limit
        Flashcard::factory()->count(FlashcardExportService::MAX_EXPORT_SIZE + 10)->forUnit($this->unit)->create([
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $response = $this->get(route('flashcards.export.options', $this->unit->id));

        $response->assertOk();
        $response->assertSee('Large Export Warning');
        $response->assertViewHas('totalCards', FlashcardExportService::MAX_EXPORT_SIZE + 20); // 10 original + new ones
    }

    #[Test]
    public function it_handles_export_service_errors_gracefully()
    {
        $this->actingAs($this->user);

        // Try to export with an invalid card ID
        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
            'selected_cards' => [99999], // Non-existent card
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Validation failed']);
    }

    #[Test]
    public function it_includes_correct_export_options_in_preview()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'anki',
            'deck_name' => 'Custom Test Deck',
        ]);

        $response->assertOk();
        $response->assertViewHas('options', ['deck_name' => 'Custom Test Deck']);

        $response = $this->post(route('flashcards.export.preview', $this->unit->id), [
            'export_format' => 'json',
            'include_metadata' => false,
        ]);

        $response->assertOk();
        $response->assertViewHas('options', ['include_metadata' => false]);
    }

    #[Test]
    public function it_filters_inactive_flashcards_from_export()
    {
        // Create some inactive flashcards
        Flashcard::factory()->count(5)->create([
            'unit_id' => $this->unit->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->user);

        $response = $this->post(route('flashcards.export.download', $this->unit->id), [
            'export_format' => 'json',
        ]);

        $response->assertOk();

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(10, $content['total_cards']); // Only active cards
    }
}
