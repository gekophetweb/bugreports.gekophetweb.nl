<?php

use App\Enums\FeedbackStatus;
use App\Enums\Role;
use App\Livewire\Admin\Feedback as AdminFeedback;
use App\Livewire\Feedback\Create;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use App\Notifications\FeedbackStatusChanged;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

function submitFeedback(array $fields = [])
{
    $c = Livewire::test(Create::class);
    foreach ($fields + ['name' => 'Ann', 'email' => 'ann@example.test', 'type' => 'bug', 'message' => 'The date filter ignores my timezone.'] as $k => $v) {
        $c->set($k, $v);
    }

    return $c->call('submit');
}

it('shows the feedback form', function () {
    $this->get('/feedback')->assertOk()->assertSee('Send feedback');
});

it('lets a guest send feedback and follow its status', function () {
    submitFeedback()->assertHasNoErrors();

    $feedback = Feedback::sole();
    expect($feedback->status)->toBe(FeedbackStatus::New)->and($feedback->token)->toHaveLength(40);
    Notification::assertSentOnDemand(FeedbackReceived::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'ann@example.test'
        && str_contains($n->toMail($notifiable)->render(), route('feedback.status', $feedback->token)));

    $this->get(route('feedback.status', $feedback->token))->assertOk()->assertSee('New')->assertSee('The date filter ignores my timezone.');
});

it('tells the superadmins about new feedback', function () {
    $admin = User::factory()->create(['role' => Role::Superadmin]);

    submitFeedback();

    Notification::assertSentTo($admin, FeedbackReceived::class);
});

it('prefills a signed-in user and lists their feedback', function () {
    $user = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.test']);
    $this->actingAs($user);

    Livewire::test(Create::class)->assertSet('email', 'bob@example.test')->assertSet('name', 'Bob');
    submitFeedback(['name' => 'Bob', 'email' => 'bob@example.test', 'message' => 'Please add Kotlin.']);

    expect(Feedback::sole()->user_id)->toBe($user->id);
    $this->get('/my/feedback')->assertOk()->assertSee('Please add Kotlin.')->assertSee('New');
});

it('validates the form', function () {
    submitFeedback(['email' => 'nope', 'message' => '', 'type' => 'rant'])->assertHasErrors(['email', 'message', 'type']);
});

it('limits submissions to 5 per hour', function () {
    foreach (range(1, 5) as $i) {
        submitFeedback(['message' => "Message $i"])->assertHasNoErrors();
    }

    submitFeedback(['message' => 'One too many'])->assertHasErrors('message');
    expect(Feedback::count())->toBe(5);
});

it('lets the superadmin change the status with a note and tells the submitter', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
    submitFeedback();
    $feedback = Feedback::sole();

    Livewire::test(AdminFeedback::class)
        ->call('edit', $feedback->id)
        ->set('status', 'planned')->set('note', 'Coming in the next release.')
        ->call('save')->assertHasNoErrors();

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);
    Notification::assertSentOnDemand(FeedbackStatusChanged::class);
    $this->get(route('feedback.status', $feedback->token))->assertSee('Planned')->assertSee('Coming in the next release.');
});

it('does not email when only the note changes', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
    submitFeedback();
    $feedback = Feedback::sole();

    Livewire::test(AdminFeedback::class)->call('edit', $feedback->id)->set('note', 'Looking into it.')->call('save');

    Notification::assertNotSentTo(new Illuminate\Notifications\AnonymousNotifiable, FeedbackStatusChanged::class);
    Notification::assertSentOnDemandTimes(FeedbackStatusChanged::class, 0);
});

it('404s for a wrong token', function () {
    $this->get('/feedback/'.str_repeat('a', 40))->assertNotFound();
});

it('keeps the admin page for superadmins', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.feedback'))->assertForbidden();
});
