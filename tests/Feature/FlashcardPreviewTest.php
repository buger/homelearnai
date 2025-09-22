<?php

namespace Tests\Feature;

use App\Models\Flashcard;
use App\Models\Review;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlashcardPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subject $subject;

    private Unit $unit;

    private Flashcard $flashcard;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable only CSRF middleware for tests (need session middleware)
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $this->user = User::factory()->create();
        $this->subject = Subject::factory()->create(['user_id' => $this->user->id]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->flashcard = Flashcard::factory()->forUnit($this->unit)->create();

        $this->actingAs($this->user);
    }

    #[Test]
    public function it_can_start_a_preview_session()
    {
        $response = $this->get(route('units.flashcards.preview.start', $this->unit));

        $response->assertSuccessful();
        $response->assertViewIs('flashcards.preview.session');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('currentFlashcard', $this->flashcard);
        $response->assertViewHas('isPreview', true);
    }

    #[Test]
    public function it_prevents_access_in_kids_mode()
    {
        session(['kids_mode' => true]);

        $response = $this->get(route('units.flashcards.preview.start', $this->unit));

        $response->assertForbidden();
    }

    #[Test]
    public function it_prevents_access_to_other_users_units()
    {
        $otherUser = User::factory()->create();
        $otherSubject = Subject::factory()->create(['user_id' => $otherUser->id]);
        $otherUnit = Unit::factory()->create(['subject_id' => $otherSubject->id]);

        $response = $this->get(route('units.flashcards.preview.start', $otherUnit));

        $response->assertForbidden();
    }

    #[Test]
    public function it_redirects_when_unit_has_no_flashcards()
    {
        $emptyUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);

        $response = $this->get(route('units.flashcards.preview.start', $emptyUnit));

        $response->assertRedirect(route('units.show', $emptyUnit));
        $response->assertSessionHas('error');
    }

    #[Test]
    public function preview_session_stores_data_in_session_only()
    {
        // Start preview session
        $response = $this->get(route('units.flashcards.preview.start', $this->unit));
        $response->assertSuccessful();

        // Check that session contains preview data
        $this->assertTrue(session()->has('flashcard_preview'));

        // Verify session data structure
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $this->assertCount(1, $sessionKeys);

        $sessionId = $sessionKeys[0];
        $sessionData = session()->get("flashcard_preview.{$sessionId}");

        $this->assertArrayHasKey('unit_id', $sessionData);
        $this->assertArrayHasKey('user_id', $sessionData);
        $this->assertArrayHasKey('flashcard_ids', $sessionData);
        $this->assertArrayHasKey('current_index', $sessionData);
        $this->assertArrayHasKey('is_preview', $sessionData);

        $this->assertEquals($this->unit->id, $sessionData['unit_id']);
        $this->assertEquals($this->user->id, $sessionData['user_id']);
        $this->assertTrue($sessionData['is_preview']);
    }

    #[Test]
    public function preview_answers_do_not_create_review_records()
    {
        // Start preview session
        $this->get(route('units.flashcards.preview.start', $this->unit));

        // Get session ID
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $sessionId = $sessionKeys[0];

        // Count reviews before
        $reviewCountBefore = Review::count();

        // Submit preview answer
        $response = $this->postJson(route('flashcards.preview.answer', $sessionId), [
            'user_answer' => 'test answer',
            'is_correct' => true,
            'time_spent' => 5000,
        ]);

        $response->assertSuccessful();

        // Verify NO review records were created
        $this->assertEquals($reviewCountBefore, Review::count());

        // Verify response indicates preview mode
        $response->assertJson(['is_preview' => true]);
        $response->assertJson(['message' => 'Preview answer recorded (not saved to learning progress)']);
    }

    #[Test]
    public function preview_session_can_be_ended()
    {
        // Start preview session
        $this->get(route('units.flashcards.preview.start', $this->unit));

        // Get session ID
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $sessionId = $sessionKeys[0];

        // Submit an answer to progress session
        $this->postJson(route('flashcards.preview.answer', $sessionId), [
            'user_answer' => 'test answer',
            'is_correct' => true,
            'time_spent' => 5000,
        ]);

        // End preview session
        $response = $this->get(route('flashcards.preview.end', $sessionId));

        $response->assertSuccessful();
        $response->assertViewIs('flashcards.preview.complete');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('previewStats');
        $response->assertViewHas('isPreview', true);

        // Verify session data is cleaned up
        $this->assertFalse(session()->has("flashcard_preview.{$sessionId}"));
    }

    #[Test]
    public function preview_session_status_returns_correct_data()
    {
        // Start preview session
        $this->get(route('units.flashcards.preview.start', $this->unit));

        // Get session ID
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $sessionId = $sessionKeys[0];

        // Get session status
        $response = $this->getJson(route('flashcards.preview.status', $sessionId));

        $response->assertSuccessful();
        $response->assertJson([
            'success' => true,
            'current_index' => 0,
            'total_cards' => 1,
            'answers_count' => 0,
            'is_preview' => true,
        ]);
    }

    #[Test]
    public function preview_prevents_access_to_other_users_sessions()
    {
        $otherUser = User::factory()->create();

        // Start session as first user
        $this->actingAs($this->user);
        $this->get(route('units.flashcards.preview.start', $this->unit));

        // Get session ID
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $sessionId = $sessionKeys[0];

        // Try to access as different user
        $this->actingAs($otherUser);

        $response = $this->getJson(route('flashcards.preview.status', $sessionId));
        $response->assertStatus(403); // Session belongs to different user
    }

    #[Test]
    public function preview_validates_session_ownership()
    {
        // Start preview session
        $this->get(route('units.flashcards.preview.start', $this->unit));

        // Get session ID
        $sessionKeys = array_keys(session()->get('flashcard_preview', []));
        $sessionId = $sessionKeys[0];

        // Tamper with session data to change user ID
        $sessionData = session()->get("flashcard_preview.{$sessionId}");
        $sessionData['user_id'] = 999; // Invalid user ID
        session()->put("flashcard_preview.{$sessionId}", $sessionData);

        // Try to submit answer
        $response = $this->postJson(route('flashcards.preview.answer', $sessionId), [
            'user_answer' => 'test answer',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function preview_rejects_non_preview_sessions()
    {
        // Manually create session data without is_preview flag
        $sessionId = 'test_session_'.time();
        session()->put("flashcard_preview.{$sessionId}", [
            'unit_id' => $this->unit->id,
            'user_id' => $this->user->id,
            'flashcard_ids' => [$this->flashcard->id],
            'current_index' => 0,
            // Missing is_preview or set to false
            'is_preview' => false,
        ]);

        // Try to submit answer
        $response = $this->postJson(route('flashcards.preview.answer', $sessionId), [
            'user_answer' => 'test answer',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid session type']);
    }
}
