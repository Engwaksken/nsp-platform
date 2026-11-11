<?php

namespace Tests\Feature;

use App\Models\Faq;
use Database\Seeders\FaqSeeder;
use Tests\TestCase;

class FaqSeederTest extends TestCase
{
    public function test_faqs_are_published_grouped_and_never_overwrite_admin_edits(): void
    {
        Faq::query()->create(['question' => 'What is NSP?', 'answer' => 'Placeholder answer. Replace in Admin › FAQs.', 'topic' => 'General', 'is_published' => false]);

        $this->seed(FaqSeeder::class);

        $total = collect(FaqSeeder::FAQS)->flatten()->count();
        $this->assertSame($total, Faq::query()->where('is_published', true)->count());
        $this->assertDatabaseMissing('faqs', ['question' => 'What is NSP?']);

        // An admin edits an answer; seeding again keeps the edit and adds no duplicates.
        Faq::query()->where('question', 'What is Hive NSP?')->update(['answer' => 'Edited by an admin.']);
        $this->seed(FaqSeeder::class);
        $this->assertSame($total, Faq::query()->count());
        $this->assertSame('Edited by an admin.', Faq::query()->where('question', 'What is Hive NSP?')->value('answer'));

        $page = $this->get(route('faqs'))->assertOk()->assertSee('Your account')->assertSee('How do I register my startup?')->getContent();
        foreach (array_keys(FaqSeeder::FAQS) as $topic) {
            $this->assertStringContainsString(e($topic), $page);
        }

        // House style: no em dashes in the published text.
        foreach (collect(FaqSeeder::FAQS)->flatMap(fn ($items) => array_merge(array_keys($items), array_values($items))) as $text) {
            $this->assertStringNotContainsString("\u{2014}", $text);
        }
    }
}
