<section class="panel" aria-labelledby="seo-heading">
    <h2 id="seo-heading">Search engines and sharing</h2>
    @include('partials.field', ['name' => 'seo_title', 'label' => 'SEO title', 'value' => $item->seo_title, 'maxlength' => 200, 'hint' => 'Shown in search results. Defaults to the title.'])
    @include('partials.field', ['name' => 'seo_description', 'label' => 'SEO description', 'type' => 'textarea', 'rows' => 3, 'value' => $item->seo_description, 'hint' => 'One or two sentences, up to 320 characters.'])
</section>
