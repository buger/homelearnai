<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TopicControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Child $child;

    private Subject $subject;

    private Unit $unit;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->child = Child::factory()->create(['user_id' => $this->user->id]);
        $this->subject = Subject::factory()->create([
            'user_id' => $this->user->id,
            'child_id' => $this->child->id,
        ]);
        $this->unit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $this->topic = Topic::factory()->create(['unit_id' => $this->unit->id]);
    }

    #[Test]
    public function it_can_show_topic_with_correct_route_parameters()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('units.topics.show', [
            'unit' => $this->unit->id,
            'topic' => $this->topic->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('topics.show');
        $response->assertViewHas('topic', $this->topic);
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('subject', $this->subject);
    }

    #[Test]
    public function it_returns_partial_view_for_htmx_request()
    {
        $this->actingAs($this->user);

        $response = $this->get(
            route('units.topics.show', [
                'unit' => $this->unit->id,
                'topic' => $this->topic->id,
            ]),
            ['HX-Request' => 'true']
        );

        $response->assertOk();
        $response->assertViewIs('topics.partials.topic-details');
        $response->assertViewHas('topic', $this->topic);
    }

    #[Test]
    public function it_redirects_to_login_when_not_authenticated()
    {
        $response = $this->get(route('units.topics.show', [
            'unit' => $this->unit->id,
            'topic' => $this->topic->id,
        ]));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_denies_access_to_other_users_topics()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->get(route('units.topics.show', [
            'unit' => $this->unit->id,
            'topic' => $this->topic->id,
        ]));

        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('error', 'Access denied.');
    }

    #[Test]
    public function it_handles_non_existent_unit()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('units.topics.show', [
            'unit' => 999999,
            'topic' => $this->topic->id,
        ]));

        $response->assertRedirect(route('subjects.index'));
        $response->assertSessionHas('error', 'Unit not found.');
    }

    #[Test]
    public function it_handles_non_existent_topic()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('units.topics.show', [
            'unit' => $this->unit->id,
            'topic' => 999999,
        ]));

        $response->assertRedirect(route('subjects.units.show', [$this->subject->id, $this->unit->id]));
        $response->assertSessionHas('error', 'Topic not found.');
    }

    #[Test]
    public function it_handles_topic_from_different_unit()
    {
        $this->actingAs($this->user);

        // Create another unit and topic
        $anotherUnit = Unit::factory()->create(['subject_id' => $this->subject->id]);
        $anotherTopic = Topic::factory()->create(['unit_id' => $anotherUnit->id]);

        // Try to access the topic with wrong unit ID
        $response = $this->get(route('units.topics.show', [
            'unit' => $this->unit->id,
            'topic' => $anotherTopic->id,
        ]));

        $response->assertRedirect(route('subjects.units.show', [$this->subject->id, $this->unit->id]));
        $response->assertSessionHas('error', 'Topic not found.');
    }

    #[Test]
    public function it_can_create_topic_for_unit()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('units.topics.store', $this->unit->id), [
            'title' => 'New Topic',
            'description' => 'Topic description',
            'estimated_minutes' => 30,
            'required' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('topics', [
            'unit_id' => $this->unit->id,
            'title' => 'New Topic',
            'estimated_minutes' => 30,
            'required' => true,
        ]);
    }

    #[Test]
    public function it_validates_topic_creation_data()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('units.topics.store', $this->unit->id), [
            'title' => '', // Invalid: empty title
            'estimated_minutes' => 1000, // Invalid: exceeds max
        ]);

        $response->assertSessionHasErrors(['title', 'estimated_minutes']);
    }

    #[Test]
    public function it_handles_topic_edit_route()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('topics.edit', $this->topic->id));

        $response->assertOk();
        $response->assertViewHas('topic', $this->topic);
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('subject', $this->subject);
    }

    #[Test]
    public function it_handles_topic_update()
    {
        $this->actingAs($this->user);

        $response = $this->put(route('topics.update', $this->topic->id), [
            'title' => 'Updated Topic Title',
            'estimated_minutes' => 45,
            'required' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('topics', [
            'id' => $this->topic->id,
            'title' => 'Updated Topic Title',
            'estimated_minutes' => 45,
            'required' => false,
        ]);
    }

    #[Test]
    public function it_handles_topic_deletion()
    {
        $this->actingAs($this->user);

        $response = $this->delete(route('topics.destroy', $this->topic->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('topics', ['id' => $this->topic->id]);
    }

    #[Test]
    public function it_handles_htmx_topic_deletion()
    {
        $this->actingAs($this->user);

        $response = $this->delete(
            route('topics.destroy', $this->topic->id),
            [],
            ['HX-Request' => 'true']
        );

        $response->assertOk();
        $response->assertViewIs('topics.partials.topics-list');
        $this->assertDatabaseMissing('topics', ['id' => $this->topic->id]);
    }

    #[Test]
    public function it_correctly_renders_topic_list_with_3_column_layout()
    {
        $this->actingAs($this->user);

        // Create multiple topics
        Topic::factory()->count(5)->create(['unit_id' => $this->unit->id]);

        $response = $this->get(route('subjects.units.show', [
            'subject' => $this->subject->id,
            'unit' => $this->unit->id,
        ]));

        $response->assertOk();
        $response->assertSee('grid-cols-1 md:grid-cols-2 lg:grid-cols-3');
    }

    #[Test]
    public function it_shows_correct_route_in_topic_view_button()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('subjects.units.show', [
            'subject' => $this->subject->id,
            'unit' => $this->unit->id,
        ]));

        $response->assertOk();
        // Check that the view button uses the correct route
        $expectedUrl = route('units.topics.show', [$this->unit->id, $this->topic->id]);
        $response->assertSee($expectedUrl);
    }

    #[Test]
    public function it_shows_correct_create_topic_route_in_unit_view()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('subjects.units.show', [
            'subject' => $this->subject->id,
            'unit' => $this->unit->id,
        ]));

        $response->assertOk();
        // Check that the create topic button uses the correct route with both subject and unit parameters
        $expectedUrl = route('topics.create', [$this->subject->id, $this->unit->id]);
        $response->assertSee($expectedUrl);
    }

    #[Test]
    public function it_can_access_topic_create_form()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('topics.create', [$this->subject->id, $this->unit->id]));

        $response->assertOk();
        $response->assertViewIs('topics.create');
        $response->assertViewHas('unit', $this->unit);
        $response->assertViewHas('subject', $this->subject);
    }
}
