<?php

namespace Database\Seeders;

use App\Enums\AdminStatus;
use App\Enums\ArticleCategory;
use App\Enums\EventAudience;
use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\MemberActivityType;
use App\Enums\MembershipStatus;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\OrganisationProfile;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Articles\ArticleService;
use App\Services\Articles\ArticleWorkflowService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use App\Services\Membership\MemberActivityService;
use App\Services\Membership\MemberApprovalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public const ADMIN_PASSWORD = 'Password123!';

    public const MEMBER_PASSWORD = 'Member123!';

    public const ADMINS = [
        'super_admin' => ['Ngozi Adeyemi', 'superadmin@apfspn.test'],
        'association_manager' => ['Oluwaseun Bello', 'manager@apfspn.test'],
        'editor' => ['Chiamaka Eze', 'editor@apfspn.test'],
        'author' => ['Ibrahim Sule', 'author@apfspn.test'],
        'events_coordinator' => ['Folake Ajayi', 'events@apfspn.test'],
        'membership_coordinator' => ['Emeka Nwosu', 'membership@apfspn.test'],
    ];

    private array $admins = [];

    private array $members = [];

    public function __construct(
        private readonly MemberApprovalService $approvals,
        private readonly MemberActivityService $activities,
        private readonly EventService $events,
        private readonly EventRegistrationService $registrations,
        private readonly ArticleService $articles,
        private readonly ArticleWorkflowService $workflow,
    ) {}

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs when APP_ENV=local.');
        }

        if (Admin::where('email', self::ADMINS['super_admin'][1])->exists()) {
            $this->command?->warn('Demo data is already present. Run migrate:fresh first to reseed.');

            return;
        }

        $bus = Bus::getFacadeRoot();
        $mailer = Mail::getFacadeRoot();
        Bus::fake();
        Mail::fake();

        try {
            $this->call(RolesAndPermissionsSeeder::class);
            $this->seedAdmins();
            $this->seedMembers();
            $this->seedEvents();
            $this->seedArticles();
        } finally {
            Auth::forgetUser();
            Bus::swap($bus);
            Mail::swap($mailer);
        }

        $this->command?->info('Demo data seeded. Admin password: '.self::ADMIN_PASSWORD.', member password: '.self::MEMBER_PASSWORD);
    }

    private function seedAdmins(): void
    {
        foreach (self::ADMINS as $role => [$name, $email]) {
            $this->admins[$role] = tap(Admin::create([
                'name' => $name,
                'email' => $email,
                'password' => self::ADMIN_PASSWORD,
                'status' => AdminStatus::Active,
                'must_reset_password' => false,
            ]))->assignRole($role);
        }
    }

    private function seedMembers(): void
    {
        $this->actAs('membership_coordinator');

        $this->member('adaobi', 'Adaobi Okafor', 'LA', 'Ikeja', twoFactor: false);
        $this->member('chinedu', 'Chinedu Obi', 'ED', 'Benin City', twoFactor: true);
        $this->member('tunde', 'Tunde Bakare', 'LA', 'Victoria Island', type: MemberType::Corporate, organisation: [
            'name' => 'Chow Co Limited',
            'registration_number' => 'RC1234567',
            'year_established' => 2012,
            'business_type' => 'Catering',
            'address' => '12 Allen Avenue, Ikeja',
            'website' => 'https://chowco.apfspn.test',
        ]);
        $this->member('kemi', 'Kemi Adebayo', 'OY', 'Ibadan');
        $this->member('yusuf', 'Yusuf Danjuma', 'KN', 'Kano');
        $this->member('amaka', 'Amaka Nnaji', 'EN', 'Enugu');
        $this->member('musa', 'Musa Abdullahi', 'KD', 'Kaduna');
        $this->member('funke', 'Funke Ogunleye', 'OG', 'Abeokuta');
        $this->member('ngozi', 'Ngozi Umeh', 'FC', 'Abuja', type: MemberType::Institutional, organisation: [
            'name' => 'Federal Catering and Hotel Management College',
            'registration_number' => 'FME/2009/114',
            'institution_type' => 'College',
            'department' => 'Hospitality Studies',
            'address' => '4 Garki Road, Area 11',
        ]);
        $this->member('bola', 'Bola Ige', 'RI', 'Port Harcourt');
        $this->member('emeka', 'Emeka Okonkwo', 'AN', 'Awka', verified: false);

        foreach (['adaobi', 'chinedu', 'tunde', 'kemi', 'yusuf', 'amaka', 'musa', 'funke'] as $key) {
            $this->approvals->approve($this->members[$key]->getKey());
        }

        $this->approvals->reject($this->members['bola']->getKey(), 'The submitted details could not be verified.');
        $this->approvals->suspend($this->members['musa']->getKey(), 'Membership dues are under review.');

        $funke = $this->members['funke']->membership()->first();
        $funke->forceFill([
            'status' => MembershipStatus::Expired,
            'approved_at' => now()->subYear()->subMonth(),
            'expires_at' => now()->subMonth(),
        ])->save();
    }

    private function seedEvents(): void
    {
        $this->actAs('events_coordinator');

        $summit = $this->publishedEvent([
            'title' => 'APFSPN Annual Food Service Summit 2026',
            'description' => 'Talks, workshops and an exhibition for food service professionals across Nigeria.',
            'starts_at' => now()->addDays(30)->setTime(9, 0),
            'ends_at' => now()->addDays(30)->setTime(17, 0),
            'venue' => 'Eko Hotel and Suites',
            'address' => 'Plot 1415 Adetokunbo Ademola Street, Victoria Island',
            'city' => 'Lagos',
            'state_code' => 'LA',
            'capacity' => 200,
            'schedule' => [
                ['time' => '09:00', 'title' => 'Registration and breakfast'],
                ['time' => '10:00', 'title' => 'Keynote: The future of food service in Nigeria'],
            ],
        ]);

        $workshop = $this->publishedEvent([
            'title' => 'Food Safety and Hygiene Workshop',
            'description' => 'A practical online session on food safety standards for kitchens and caterers.',
            'starts_at' => now()->addDays(14)->setTime(11, 0),
            'ends_at' => now()->addDays(14)->setTime(13, 0),
            'is_virtual' => true,
            'virtual_link' => 'https://meet.apfspn.test/food-safety',
            'capacity' => 3,
        ]);

        $masterclass = $this->publishedEvent([
            'title' => 'Catering Business Masterclass',
            'description' => 'Members-only masterclass on pricing, contracts and growing a catering business.',
            'starts_at' => now()->addDays(45)->setTime(10, 0),
            'ends_at' => now()->addDays(45)->setTime(15, 0),
            'venue' => 'Transcorp Hilton',
            'address' => '1 Aguiyi Ironsi Street, Maitama',
            'city' => 'Abuja',
            'state_code' => 'FC',
            'audience' => EventAudience::MembersOnly,
        ]);

        $expo = $this->publishedEvent([
            'title' => 'Port Harcourt Hospitality Expo',
            'description' => 'Regional expo for hospitality suppliers and food service operators.',
            'starts_at' => now()->addDays(21)->setTime(10, 0),
            'ends_at' => now()->addDays(21)->setTime(16, 0),
            'venue' => 'Hotel Presidential',
            'address' => 'Aba Road, GRA Phase 2',
            'city' => 'Port Harcourt',
            'state_code' => 'RI',
        ]);

        $this->events->create([
            'title' => 'Abuja Chefs Networking Evening',
            'description' => 'An evening of networking for chefs and kitchen managers in the FCT.',
            'starts_at' => now()->addDays(60)->setTime(18, 0),
            'ends_at' => now()->addDays(60)->setTime(21, 0),
            'venue' => 'Sheraton Abuja',
            'address' => 'Ladi Kwali Street, Wuse Zone 4',
            'city' => 'Abuja',
            'state_code' => 'FC',
        ]);

        foreach (['adaobi', 'tunde', 'chinedu'] as $key) {
            $this->registrations->register($this->members[$key], $summit->getKey());
        }

        foreach (['kemi', 'yusuf', 'amaka', 'adaobi', 'chinedu'] as $key) {
            $this->registrations->register($this->members[$key], $workshop->getKey());
        }

        $this->registrations->register($this->members['tunde'], $masterclass->getKey());
        $this->registrations->register($this->members['adaobi'], $expo->getKey());
        $this->events->cancel($expo->getKey(), 'The venue is no longer available on this date.');

        $this->completedEvent();
    }

    private function seedArticles(): void
    {
        $author = $this->admins['author'];
        $editor = $this->admins['editor'];

        $this->actAs('author');
        $this->article('Choosing Local Ingredients for Your Menu', ArticleCategory::Blog, $author);
        $inReview = $this->article('How Caterers Can Prepare for the Festive Season', ArticleCategory::Blog, $author);
        $changes = $this->article('Five Habits of Successful Kitchen Managers', ArticleCategory::Blog, $author);
        $fromAuthor = $this->article('APFSPN Welcomes New Members in Lagos', ArticleCategory::News, $author);

        foreach ([$inReview, $changes, $fromAuthor] as $article) {
            $this->workflow->submit($article, $author);
        }

        $this->actAs('editor');
        $this->workflow->sendBack($changes, 'Please add a short introduction and one example.', $editor);
        $this->workflow->publish($fromAuthor, $editor);

        $this->workflow->publish($this->article('APFSPN Announces the 2026 Food Service Summit', ArticleCategory::PressRelease, $editor), $editor);
        $this->workflow->schedule($this->article('New Food Safety Guidelines Take Effect Next Month', ArticleCategory::News, $editor), now()->addDays(3)->startOfHour(), $editor);

        $archived = $this->article('Highlights from the 2025 Members Meeting', ArticleCategory::News, $editor);
        $this->workflow->publish($archived, $editor);
        $this->workflow->archive($archived, $editor);
    }

    private function member(string $key, string $name, string $state, string $city, MemberType $type = MemberType::Individual, ?array $organisation = null, bool $verified = true, bool $twoFactor = false): void
    {
        $isIndividual = $type === MemberType::Individual;

        $user = User::create([
            'name' => $name,
            'email' => $key.'@apfspn.test',
            'email_verified_at' => $verified ? now() : null,
            'phone' => '0803'.str_pad((string) (count($this->members) + 1), 7, '0', STR_PAD_LEFT),
            'password' => self::MEMBER_PASSWORD,
            'status' => UserStatus::Active,
            'state_code' => $isIndividual ? $state : null,
            'city' => $isIndividual ? $city : null,
            'two_factor_enabled' => $twoFactor,
            'consent_accepted_at' => now(),
        ]);

        if (! $isIndividual) {
            OrganisationProfile::create($organisation + [
                'user_uuid' => $user->getKey(),
                'type' => $type,
                'state_code' => $state,
                'city' => $city,
                'document_public_id' => 'members/documents/demo-'.$key.'.pdf',
            ]);
        }

        $membership = Membership::create([
            'user_uuid' => $user->getKey(),
            'member_type' => $type,
            'state_code' => $state,
            'status' => MembershipStatus::PendingApproval,
        ]);

        $this->activities->record($user->getKey(), MemberActivityType::ApplicationSubmitted, $membership);

        if ($verified) {
            $this->activities->record($user->getKey(), MemberActivityType::EmailVerified);
        }

        $this->members[$key] = $user;
    }

    private function publishedEvent(array $data): Event
    {
        return $this->events->publish($this->events->create($data)->getKey());
    }

    private function completedEvent(): void
    {
        $event = Event::create([
            'title' => '2026 Mid-Year Members Meeting',
            'slug' => '2026-mid-year-members-meeting',
            'description' => 'The association\'s mid-year general meeting and awards.',
            'starts_at' => now()->subDays(20)->setTime(10, 0),
            'ends_at' => now()->subDays(20)->setTime(14, 0),
            'venue' => 'Lagos Oriental Hotel',
            'address' => '3 Lekki Road, Victoria Island',
            'city' => 'Lagos',
            'state_code' => 'LA',
            'audience' => EventAudience::Public,
            'status' => EventStatus::Completed,
            'published_at' => now()->subDays(40),
            'created_by_admin_uuid' => $this->admins['events_coordinator']->getKey(),
            'updated_by_admin_uuid' => $this->admins['events_coordinator']->getKey(),
        ]);

        foreach (['adaobi', 'tunde', 'kemi'] as $key) {
            EventRegistration::create([
                'event_uuid' => $event->getKey(),
                'user_uuid' => $this->members[$key]->getKey(),
                'status' => EventRegistrationStatus::Attended,
                'registered_at' => now()->subDays(30),
                'attended_at' => $event->starts_at,
            ]);
        }
    }

    private function article(string $title, ArticleCategory $category, Admin $admin): string
    {
        return $this->articles->create([
            'title' => $title,
            'category' => $category->value,
            'excerpt' => 'A short summary of "'.$title.'" for the article listing.',
            'body' => '<h2>'.e($title).'</h2><p>This demo article shows how content looks once it has been through the editorial workflow.</p>',
            'tags' => ['demo', $category->value],
        ], $admin)->getKey();
    }

    private function actAs(string $role): void
    {
        Auth::setUser($this->admins[$role]->withAccessToken(new PersonalAccessToken(['abilities' => ['admin']])));
    }
}
