<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Menu;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Placeholder structure so a fresh install is navigable. Every text here is a
 * neutral placeholder for Hive staff to replace; it makes no claims about impact.
 * Only creates what does not exist yet.
 */
class StarterContentSeeder extends Seeder
{
    public function run(): void
    {
        $home = Page::query()->firstOrCreate(['slug' => 'home'], [
            'title' => 'Home',
            'template' => 'landing',
            'status' => 'draft',
            'summary' => 'Hive Startup Growth & Ecosystem Platform',
            'blocks' => [
                ['type' => 'hero', 'heading' => 'Grow your startup with the Hive ecosystem', 'body' => 'Replace this text in Admin › Pages › Home.', 'items' => '', 'link_label' => 'Contact us', 'link_url' => '/contact'],
                ['type' => 'text', 'heading' => 'What NSP offers', 'body' => "Describe NSP's services here.", 'items' => '', 'link_label' => '', 'link_url' => ''],
                ['type' => 'latest_posts', 'heading' => 'Latest news', 'body' => '', 'items' => '', 'link_label' => 'All news', 'link_url' => '/news'],
                ['type' => 'faqs', 'heading' => 'Questions', 'body' => '', 'items' => '', 'link_label' => 'All FAQs', 'link_url' => '/faqs'],
            ],
        ]);
        if (! $home->revisions()->exists()) {
            $home->snapshot(null, 'Starter content');
        }

        $about = Page::query()->firstOrCreate(['slug' => 'about'], [
            'title' => 'About NSP',
            'template' => 'default',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'text', 'heading' => 'About Hive NSP', 'body' => 'Replace with the approved description of Hive and NSP.', 'items' => '', 'link_label' => '', 'link_url' => ''],
            ],
        ]);

        $header = Menu::query()->firstOrCreate(['location' => 'header'], ['name' => 'Header']);
        if (! $header->items()->exists()) {
            $header->items()->createMany([
                ['label' => 'About', 'page_id' => $about->id, 'sort_order' => 1],
                ['label' => 'News', 'url' => '/news', 'sort_order' => 2],
                ['label' => 'FAQs', 'url' => '/faqs', 'sort_order' => 3],
                ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 4],
            ]);
        }
        $footer = Menu::query()->firstOrCreate(['location' => 'footer'], ['name' => 'Footer']);
        if (! $footer->items()->exists()) {
            $footer->items()->createMany([
                ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 1],
                ['label' => 'FAQs', 'url' => '/faqs', 'sort_order' => 2],
            ]);
        }

        if (! Faq::query()->exists()) {
            Faq::query()->create([
                'question' => 'What is NSP?',
                'answer' => 'Placeholder answer. Replace in Admin › FAQs.',
                'topic' => 'General',
                'is_published' => false,
            ]);
        }
    }
}
