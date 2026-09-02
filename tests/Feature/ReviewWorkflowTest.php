<?php

namespace Azuriom\Plugin\Ronove\Tests\Feature;

use Azuriom\Models\Post;
use Azuriom\Models\Role;
use Azuriom\Models\Setting;
use Azuriom\Models\User;
use Azuriom\Plugin\Ronove\Events\TranslationPublished;
use Azuriom\Plugin\Ronove\Events\TranslationReviewCompleted;
use Azuriom\Plugin\Ronove\Events\TranslationSubmittedForReview;
use Azuriom\Plugin\Ronove\Models\Locale;
use Azuriom\Plugin\Ronove\Models\Translation;
use Azuriom\Plugin\Ronove\Models\TranslationRevision;
use Azuriom\Plugin\Ronove\Services\LocaleManager;
use Azuriom\Plugin\Ronove\Services\PublicLanguagePage;
use Azuriom\Plugin\Ronove\Services\ReviewWorkflow;
use Azuriom\Plugin\Ronove\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ReviewWorkflowTest extends TestCase
{
    public function test_the_review_workflow_can_be_enabled_and_disabled_from_settings(): void
    {
        $admin = $this->user(admin: true);
        $workflow = app(ReviewWorkflow::class);

        $this->assertFalse($workflow->enabled());
        $this->assertFalse(app('ronove')->reviewWorkflowEnabled());

        $this->actingAs($admin)
            ->get(route('ronove.admin.settings.index'))
            ->assertOk()
            ->assertSee('Enable the translation review workflow')
            ->assertSee('Public language page')
            ->assertSee('Revision history is recorded automatically');

        $this->actingAs($admin)
            ->post(route('ronove.admin.settings.update'), [
                'review_workflow_enabled' => '1',
                'public_language_page_enabled' => '1',
            ])
            ->assertRedirect(route('ronove.admin.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($workflow->enabled());
        $this->assertTrue(app('ronove')->reviewWorkflowEnabled());
        $this->assertSame('1', setting(ReviewWorkflow::SETTING_KEY));
        $this->assertSame('1', setting(PublicLanguagePage::SETTING_KEY));

        $this->actingAs($admin)
            ->post(route('ronove.admin.settings.update'), [
                'review_workflow_enabled' => '0',
                'public_language_page_enabled' => '0',
            ])
            ->assertRedirect(route('ronove.admin.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($workflow->enabled());
        $this->assertSame('0', setting(ReviewWorkflow::SETTING_KEY));
        $this->assertSame('0', setting(PublicLanguagePage::SETTING_KEY));
    }

    public function test_disabled_workflow_keeps_direct_publication_and_records_revisions_automatically(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $spanish = $this->locale('es_ES', true);
        $post = $this->createPost($admin, 'Direct source', 'direct-source');

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.update', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), [
                'locale' => $spanish->code,
                'status' => Translation::PUBLISHED,
                'values' => [
                    'title' => 'Publicación directa',
                    'content' => '<p>Sin paso de revisión</p>',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation = Translation::query()->firstOrFail();
        $this->assertSame(Translation::PUBLISHED, $translation->status);
        $this->assertSame(Translation::REVIEW_APPROVED, $translation->review_status);
        $this->assertSame($admin->id, $translation->reviewed_by);
        $this->assertDatabaseHas('ronove_translation_revisions', [
            'translation_id' => $translation->id,
            'action' => TranslationRevision::SAVED,
            'status' => Translation::PUBLISHED,
            'review_status' => Translation::REVIEW_APPROVED,
        ]);

        $this->actingAs($admin)
            ->post(route('ronove.admin.translations.reviews.approve', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.integration', [
                'integration' => 'core',
                'type' => 'core.post',
                'locale' => $spanish->code,
                'review_status' => Translation::REVIEW_PENDING,
            ]))
            ->assertStatus(422);

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Publicación directa');

        $this->actingAs($admin)
            ->get(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish->code,
            ]))
            ->assertOk()
            ->assertSee(trans('ronove::admin.translations.save_draft'))
            ->assertSee(trans('ronove::admin.translations.save_publish'))
            ->assertSee(trans('ronove::admin.revisions.title'))
            ->assertSee('Publicación directa')
            ->assertDontSee(trans('ronove::admin.reviews.submit'));
    }

    public function test_an_authorized_reviewer_can_save_and_publish_from_the_editor_menu(): void
    {
        Event::fake([TranslationPublished::class]);
        Setting::updateSettings([
            'locale' => 'en',
            ReviewWorkflow::SETTING_KEY => '1',
        ]);
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $spanish = $this->locale('es_ES', true);
        $post = $this->createPost($admin, 'Review source', 'review-source');
        $editRoute = route('ronove.admin.translations.edit', [
            'type' => 'core.post',
            'key' => $post->id,
            'locale' => $spanish->code,
        ]);

        $this->actingAs($admin)
            ->get($editRoute)
            ->assertOk()
            ->assertSee('Save as draft')
            ->assertSee('Save and publish')
            ->assertSee('Submit for review');

        $this->actingAs($admin)
            ->put(route('ronove.admin.translations.update', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), [
                'locale' => $spanish->code,
                'workflow_action' => 'publish',
                'values' => [
                    'title' => 'Published from menu',
                    'content' => '<p>Published immediately</p>',
                ],
            ])
            ->assertRedirect($editRoute)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'The translation has been saved and published.');

        $translation = Translation::query()->firstOrFail();
        $this->assertSame(Translation::PUBLISHED, $translation->status);
        $this->assertSame(Translation::REVIEW_APPROVED, $translation->review_status);
        $this->assertSame($admin->id, $translation->reviewed_by);
        $this->assertSame('Published from menu', $translation->publicValues()['title']);
        $this->assertDatabaseHas('ronove_translation_revisions', [
            'translation_id' => $translation->id,
            'action' => TranslationRevision::APPROVED,
            'status' => Translation::PUBLISHED,
            'review_status' => Translation::REVIEW_APPROVED,
        ]);
        Event::assertDispatched(TranslationPublished::class);
    }

    public function test_pending_edits_keep_the_last_approved_version_public(): void
    {
        Event::fake([TranslationPublished::class]);
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $spanish = $this->locale('es_ES', true);
        $post = $this->createPost($admin, 'Versioned source', 'versioned-source');
        $updateRoute = route('ronove.admin.translations.update', [
            'type' => 'core.post',
            'key' => $post->id,
        ]);

        $this->actingAs($admin)
            ->put($updateRoute, [
                'locale' => $spanish->code,
                'status' => Translation::PUBLISHED,
                'values' => [
                    'title' => 'Versión aprobada',
                    'content' => '<p>Contenido aprobado</p>',
                ],
            ])
            ->assertRedirect();

        Setting::updateSettings(ReviewWorkflow::SETTING_KEY, '1');

        $this->actingAs($admin)
            ->put($updateRoute, [
                'locale' => $spanish->code,
                'workflow_action' => 'submit',
                'values' => [
                    'title' => 'Versión pendiente',
                    'content' => '<p>Contenido pendiente</p>',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation = Translation::query()->firstOrFail();
        $this->assertSame(Translation::DRAFT, $translation->status);
        $this->assertSame(Translation::REVIEW_PENDING, $translation->review_status);
        $this->assertSame('Versión pendiente', $translation->values['title']);
        $this->assertSame('Versión aprobada', $translation->publicValues()['title']);
        $this->assertSame(
            1,
            app('ronove')->coverage('core.post', $spanish->code)->count(Translation::PUBLISHED),
        );

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Versión aprobada')
            ->assertDontSee('Versión pendiente');

        $this->flushSession();
        $this->actingAs($admin)
            ->post(route('ronove.admin.translations.reviews.approve', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertRedirect();

        $translation->refresh();
        $this->assertSame('Versión pendiente', $translation->publicValues()['title']);
        Event::assertDispatchedTimes(TranslationPublished::class, 2);
        Event::assertDispatched(TranslationPublished::class, fn (TranslationPublished $event) => $event->values['title'] === 'Versión pendiente'
            && $event->previousStatus === Translation::PUBLISHED);

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Versión pendiente');
    }

    public function test_enabled_workflow_requires_review_before_publication(): void
    {
        Event::fake([
            TranslationPublished::class,
            TranslationReviewCompleted::class,
            TranslationSubmittedForReview::class,
        ]);
        Setting::updateSettings([
            'locale' => 'en',
            ReviewWorkflow::SETTING_KEY => '1',
        ]);
        $translator = $this->userWithPermissions('Translator', [
            'admin.access', 'ronove.translations',
        ]);
        $reviewer = $this->userWithPermissions('Reviewer', [
            'admin.access', 'ronove.translations', 'ronove.review', 'ronove.publish',
        ]);
        $this->locale('en', true);
        $spanish = $this->locale('es_ES', true);
        $post = $this->createPost($translator, 'Reviewed source', 'reviewed-source');
        $notSubmitted = $this->createPost($translator, 'Not submitted source', 'not-submitted-source');
        $route = route('ronove.admin.translations.update', [
            'type' => 'core.post',
            'key' => $post->id,
        ]);

        $this->actingAs($translator)
            ->from(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish->code,
            ]))
            ->put($route, [
                'locale' => $spanish->code,
                'status' => Translation::PUBLISHED,
                'values' => ['title' => 'No debe publicarse'],
            ])
            ->assertSessionHasErrors('workflow_action');

        $this->assertDatabaseCount('ronove_translations', 0);

        $this->actingAs($translator)
            ->put($route, [
                'locale' => $spanish->code,
                'status' => Translation::PUBLISHED,
                'workflow_action' => 'submit',
                'values' => [
                    'title' => 'Primera propuesta',
                    'content' => '<p>Contenido propuesto</p>',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation = Translation::query()->firstOrFail();
        $this->assertSame(Translation::DRAFT, $translation->status);
        $this->assertSame(Translation::REVIEW_PENDING, $translation->review_status);
        $this->assertNull($translation->reviewed_by);
        Event::assertDispatched(TranslationSubmittedForReview::class, fn ($event) => $event->resourceType === 'core.post'
            && $event->resourceKey === (string) $post->id
            && $event->locale === 'es_ES'
            && $event->userId === $translator->id);
        Event::assertNotDispatched(TranslationPublished::class);

        $this->actingAs($translator)
            ->get(route('ronove.admin.translations.integration', [
                'integration' => 'core',
                'type' => 'core.post',
                'locale' => $spanish->code,
                'review_status' => Translation::REVIEW_PENDING,
            ]))
            ->assertOk()
            ->assertSee($post->title)
            ->assertDontSee($notSubmitted->title);

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Reviewed source')
            ->assertDontSee('Primera propuesta');

        $this->flushSession();
        $this->actingAs($translator)
            ->post(route('ronove.admin.translations.reviews.approve', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertForbidden();

        $feedback = 'Use a friendlier title and preserve the original tone.';
        $this->flushSession();
        $this->actingAs($reviewer)
            ->post(route('ronove.admin.translations.reviews.changes', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]), ['feedback' => $feedback])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation->refresh();
        $this->assertSame(Translation::DRAFT, $translation->status);
        $this->assertSame(Translation::REVIEW_CHANGES_REQUESTED, $translation->review_status);
        $this->assertSame($reviewer->id, $translation->reviewed_by);
        $this->assertSame($feedback, $translation->review_feedback);

        $this->flushSession();
        $this->actingAs($translator)
            ->get(route('ronove.admin.translations.edit', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish->code,
            ]))
            ->assertOk()
            ->assertSee('Changes requested')
            ->assertSee($feedback)
            ->assertSee('Submit for review');

        $this->actingAs($translator)
            ->put($route, [
                'locale' => $spanish->code,
                'workflow_action' => 'save',
                'values' => [
                    'title' => 'Propuesta corregida',
                    'content' => '<p>Contenido corregido</p>',
                ],
            ])
            ->assertRedirect();

        $translation->refresh();
        $this->assertSame(Translation::REVIEW_DRAFT, $translation->review_status);
        $this->assertSame($feedback, $translation->review_feedback);

        $this->actingAs($translator)
            ->put($route, [
                'locale' => $spanish->code,
                'workflow_action' => 'save',
                'values' => $translation->values,
            ])
            ->assertRedirect();

        $translation->refresh();
        $this->assertSame($feedback, $translation->review_feedback);

        $this->actingAs($translator)
            ->put($route, [
                'locale' => $spanish->code,
                'workflow_action' => 'submit',
                'values' => $translation->values,
            ])
            ->assertRedirect();

        $translation->refresh();
        $this->assertSame(Translation::REVIEW_PENDING, $translation->review_status);
        $this->assertNull($translation->review_feedback);

        $this->flushSession();
        $this->actingAs($reviewer)
            ->post(route('ronove.admin.translations.reviews.approve', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]), ['feedback' => 'Approved after the requested correction.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation->refresh();
        $this->assertSame(Translation::PUBLISHED, $translation->status);
        $this->assertSame(Translation::REVIEW_APPROVED, $translation->review_status);
        $this->assertSame($reviewer->id, $translation->reviewed_by);
        $this->assertSame([
            TranslationRevision::SUBMITTED,
            TranslationRevision::CHANGES_REQUESTED,
            TranslationRevision::SAVED,
            TranslationRevision::SAVED,
            TranslationRevision::SUBMITTED,
            TranslationRevision::APPROVED,
        ], $translation->revisions()->oldest('id')->pluck('action')->all());
        Event::assertDispatched(TranslationReviewCompleted::class, fn ($event) => $event->decision === Translation::REVIEW_APPROVED
            && $event->reviewerId === $reviewer->id);
        Event::assertDispatched(TranslationPublished::class, fn ($event) => $event->resourceKey === (string) $post->id
            && $event->locale === 'es_ES');

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Propuesta corregida');

        $this->actingAs($reviewer)
            ->post(route('ronove.admin.translations.reviews.approve', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
            ]))
            ->assertStatus(409);
    }

    public function test_a_revision_can_be_restored_only_to_its_translation_as_a_new_draft(): void
    {
        Setting::updateSettings('locale', 'en');
        $admin = $this->user(admin: true);
        $this->locale('en', true);
        $spanish = $this->locale('es_ES', true);
        $post = $this->createPost($admin, 'Revision source', 'revision-source');
        $otherPost = $this->createPost($admin, 'Other source', 'other-source');

        $this->saveDirect($admin, $post, $spanish, 'Primera versión', Translation::DRAFT);
        $translation = Translation::query()->firstOrFail();
        $firstRevision = $translation->revisions()->firstOrFail();
        $this->saveDirect($admin, $post, $spanish, 'Segunda versión', Translation::PUBLISHED);
        $this->saveDirect($admin, $otherPost, $spanish, 'Otra traducción', Translation::DRAFT);
        $otherRevision = Translation::query()
            ->whereHas('resource', fn ($query) => $query->where('resource_key', (string) $otherPost->id))
            ->firstOrFail()
            ->revisions()
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('ronove.admin.translations.revisions.restore', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
                'revision' => $otherRevision,
            ]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('ronove.admin.translations.revisions.restore', [
                'type' => 'core.post',
                'key' => $post->id,
                'locale' => $spanish,
                'revision' => $firstRevision,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $translation->refresh();
        $this->assertSame(['title' => 'Primera versión'], $translation->values);
        $this->assertSame(Translation::DRAFT, $translation->status);
        $this->assertSame(Translation::REVIEW_DRAFT, $translation->review_status);
        $this->assertNull($translation->reviewed_by);
        $this->assertSame(TranslationRevision::RESTORED, $translation->revisions()->latest('id')->firstOrFail()->action);
        $this->assertSame(3, $translation->revisions()->count());

        $this->withSession([LocaleManager::SESSION_KEY => $spanish->code])
            ->get('/news/'.$post->slug)
            ->assertOk()
            ->assertSee('Segunda versión')
            ->assertDontSee('Primera versión')
            ->assertDontSee('Revision source');
    }

    private function saveDirect(
        User $user,
        Post $post,
        Locale $locale,
        string $title,
        string $status,
    ): void {
        $this->actingAs($user)
            ->put(route('ronove.admin.translations.update', [
                'type' => 'core.post',
                'key' => $post->id,
            ]), [
                'locale' => $locale->code,
                'status' => $status,
                'values' => ['title' => $title],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(string $name, array $permissions): User
    {
        $role = Role::query()->create([
            'name' => $name,
            'color' => '6c757d',
            'power' => Role::query()->max('power') + 1,
            'is_admin' => false,
        ]);

        foreach ($permissions as $permission) {
            $role->permissions()->create(['permission' => $permission]);
        }

        return User::query()->forceCreate([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
        ]);
    }

    private function user(bool $admin = false): User
    {
        $user = User::query()->create([
            'name' => $admin ? 'Ronove Admin' : fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => 1,
        ]);

        if ($admin) {
            $user->role->forceFill(['is_admin' => true])->save();
        }

        return $user;
    }

    private function locale(string $code, bool $enabled): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => $code,
            'native_name' => $code,
            'is_enabled' => $enabled,
            'position' => Locale::query()->count(),
        ]);
    }

    private function createPost(User $author, string $title, string $slug): Post
    {
        return $author->posts()->create([
            'title' => $title,
            'description' => 'Original SEO description',
            'slug' => $slug,
            'content' => '<p>Original content</p>',
            'is_pinned' => false,
            'published_at' => now()->subMinute(),
        ]);
    }
}
