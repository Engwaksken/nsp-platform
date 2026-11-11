<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Published FAQs for the public /faqs page, written to match how the platform works.
 * Safe to run more than once: a question that already exists is left as admins edited it.
 * Run: php artisan db:seed --class=FaqSeeder --force
 */
class FaqSeeder extends Seeder
{
    /** @var array<string, array<string, string>> topic => [question => answer (Markdown)] */
    public const FAQS = [
        'About Hive NSP' => [
            'What is Hive NSP?' => 'Hive NSP is the startup growth and ecosystem platform of Hive. In one place a startup can keep its company profile, check its growth stage, work through the Venture Builder, find opportunities and funding, meet experts, hire, and trade with other startups. Investors, experts, job seekers and partner organizations use the same platform to work with startups.',
            'Who is it for?' => "- **Startup founders and their teams**: profile, growth, funding, hiring and the marketplace.\n- **Investors**: verified investors see startups that chose to share with them.\n- **Experts and mentors**: offer advice and mentoring sessions to startups.\n- **Job seekers**: apply for jobs, internships and gigs at startups.\n- **Organizations** such as universities, incubators, development partners and corporates: follow the startups that agree to share with them.",
        ],
        'Your account' => [
            'How do I create an account?' => 'On the [sign-in page](/login), choose **Sign in with your Hive account** if it is shown: your first sign-in with a Google account creates your NSP account. You can also be invited by email, for example by a startup team you are joining. If you cannot sign in, [contact us](/contact).',
            'Which password can I use?' => 'At least 12 characters, with both letters and numbers. You can change it any time in **My profile, Password**. Changing it signs you out on your other devices and sends a security email to your address.',
            'How do I change my email address?' => 'Go to **My profile, Personal details**, enter the new address and your current password. We send a confirmation link to the new address; your email only changes when you open that link (it works for 60 minutes). We also tell your old address, so you would notice if someone else tried it. Accounts that sign in with Google keep the email from Google.',
            'What is two-step sign-in and do I need it?' => 'Two-step sign-in asks for a 6-digit code from an authenticator app on your phone (for example Google Authenticator or Microsoft Authenticator) after your password. It is required for Hive staff with access to the admin area and recommended for everyone else. Turn it on in **My profile, More settings, Sign-in security**, and keep your recovery codes somewhere safe.',
            'I forgot my password. What now?' => 'Use **Forgot password** on the [sign-in page](/login). If an account with a password uses your email, we send a reset link that works for 60 minutes. Resetting signs the account out everywhere.',
            'Can I change the colours I see?' => 'Yes. In **My profile, Appearance** pick one of the colour palettes; only you see it, on every device you sign in on. You can also choose light, dark or your device setting there.',
            'How do I choose which emails I get?' => 'Open **Notification settings** from the menu under your name. Security emails about your own account are always sent; most other messages can be turned off by email, on the site, or both.',
        ],
        'Startups and teams' => [
            'How do I register my startup?' => 'After signing in, choose **Register a startup** on your dashboard. The form has three short tabs (Company, Sector and location, Private details); only the registered name, trading name and country are required, and you can complete the rest later.',
            'It says my startup is already registered. Why?' => 'Each company has one profile on Hive. If a startup with the same registration number, website or name already exists, you can ask its team to let you in instead of creating a copy. If it really is a different company, confirm that on the form and Hive staff will double-check.',
            'How do I add my co-founders and team?' => 'Open your startup, then **Team, Invite someone**. They get an email link and must sign in with that same address to join. People can also ask to join, and an owner or admin approves them.',
            'What can each team role do?' => "- **Owner**: everything, including managing other owners and admins. A startup always keeps at least one owner.\n- **Founder** and **Admin**: edit the profile and manage the team (admins cannot change owners).\n- **Team member** and **Viewer**: see the profile and the work of the startup.",
            'Who can see our revenue, funding and contact details?' => 'These private details are stored encrypted and are never public. For each group (revenue and funding, traction, contact) you choose who can see it: only owners, founders and admins; your whole team; or your team and Hive staff. Change this when you edit the profile.',
            'Can we download all our data?' => 'Yes. On your startup dashboard use **Download our data** to get everything your startup entered as a file. Downloads are recorded.',
        ],
        'Data sharing and privacy' => [
            'Is anything about my startup shared automatically?' => "No. On the startup's **Data sharing** page each kind of sharing is off until an owner or founder turns it on: prefilling Hive programme applications, listing in the public startup directory, showing your funding request to verified investors, and including you in anonymised ecosystem reports. Turning one off stops that sharing from that moment.",
            'How do organizations get access to our startup?' => 'An organization (for example an incubator or university) can ask to follow your startup. You see the request on your **Organizations** page and can approve it, approve only some of the items they asked for, set an end date, decline it, or revoke access later. They only ever see what you approved.',
            'How long do you keep CVs from job applications?' => 'Copies of CVs sent with applications are deleted automatically after the retention period (180 days unless Hive sets another period).',
            'How do I ask for my personal data to be corrected or deleted?' => 'You can correct most details yourself in **My profile** and on your startup pages. For anything else, including deleting your account, [contact us](/contact) and choose **Privacy or data request**.',
        ],
        'Growth and Venture Builder' => [
            'What is the growth assessment?' => 'A set of questions about your startup that places it at a growth stage and suggests where to focus next, with a growth plan of actions you can track. You can retake it as your startup develops.',
            'What is the Venture Builder?' => 'A step-by-step programme of modules and steps for building your venture. You submit evidence for each step, get reviews and comments, and see your progress.',
        ],
        'Funding and investors' => [
            'How do investors find my startup?' => 'Turn on **Show our funding request to verified investors** on the Data sharing page and create a funding request on your **Funding** page. Only investors verified by Hive can see it, and an introduction must be accepted before you share more.',
            'How do I become a verified investor?' => 'Sign in and apply from the investor page with your organisation, investment focus and ticket size. Hive reviews each application; once verified you get access to deal flow and introductions.',
            'What is Capital Exchange?' => 'An extension of Funding (switched on by Hive when ready). Founders decide exactly what each connected investor may see and for how long, answer due-diligence checklists and document requests from their data room, and get a readiness report. Investors keep a private pipeline and watchlist. Access ends immediately when the founder stops sharing or the end date passes.',
            'Is our data room safe?' => 'Files in the data room are private. An investor can only open them after you give that investor data-room access, every download is recorded, and you can withdraw access at any time.',
        ],
        'Experts, jobs and the marketplace' => [
            'How do I get help from an expert or mentor?' => "Open your startup's **Experts** page, find a verified expert and send a request. Sessions, notes, actions and feedback are kept in the engagement.",
            'How do I become an expert on Hive?' => 'Create your expert profile with your areas of expertise. Hive verifies experts before startups can book them.',
            'How do I apply for a job at a startup?' => 'Browse the [jobs board](/jobs), create your candidate profile once, and apply. You can follow your applications under **My applications**.',
            'How do I post a vacancy?' => "Open your startup's **Hiring** page, create the vacancy and publish it. You can shortlist candidates, invite them to interviews and keep notes in one place.",
            'What can I do in the marketplace?' => 'List products or services you offer, post requests to buy, or look for partners. Buyers and suppliers message each other through the platform, and listings can be reported to Hive if something is wrong.',
        ],
        'Opportunities, challenges and knowledge' => [
            'Where do I find grants, competitions and events?' => 'On the [opportunities page](/opportunities). When you are signed in with a startup, you also see opportunities matched to your startup and whether it meets each eligibility rule.',
            'Can I post an opportunity?' => 'Yes, use **Submit an opportunity**. Hive reviews every submission before it is published.',
            'What is the Challenge Exchange?' => 'A place where real problems from communities, sectors and organisations are posted so startups can propose solutions. Anyone signed in can submit a challenge; Hive reviewers check it and its sources before publishing it.',
            'What is in the Knowledge Hub?' => 'Guides, templates, toolkits and research for startups. You can save resources and see recommendations for your stage.',
        ],
        'For organizations' => [
            'How does our organization join Hive NSP?' => '[Contact us](/contact). Hive creates and verifies the organization and invites its first admin, who can then invite colleagues with roles such as manager, analyst or member.',
            'What can our organization see about startups?' => 'Only what each startup approves for your organization, for the purpose you stated, until the end date it set. A startup can revoke access at any time.',
        ],
        'Help and support' => [
            'How do I contact Hive?' => 'Use the [contact form](/contact) and choose the topic that fits; your message goes to the right team. Our contact details are on the same page.',
            'I think someone else used my account. What should I do?' => 'Change your password straight away (or use **Forgot password**), turn on two-step sign-in, and [contact us](/contact). Security emails about password and email changes tell you when they happened.',
        ],
    ];

    public function run(): void
    {
        // Remove the old unpublished placeholder from StarterContentSeeder.
        Faq::query()->where('question', 'What is NSP?')->where('answer', 'like', 'Placeholder%')->delete();

        $order = 0;
        foreach (self::FAQS as $topic => $items) {
            foreach ($items as $question => $answer) {
                $order += 10;
                Faq::query()->firstOrCreate(
                    ['question' => $question],
                    ['answer' => $answer, 'topic' => $topic, 'sort_order' => $order, 'is_published' => true],
                );
            }
        }
    }
}
