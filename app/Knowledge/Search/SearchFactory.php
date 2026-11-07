<?php

namespace App\Knowledge\Search;

/** Picks the search implementation from config/knowledge.php (no service provider binding needed). */
class SearchFactory
{
    public static function make(): KnowledgeSearch
    {
        $keyword = new KeywordKnowledgeSearch;
        if (! config('knowledge.semantic.enabled')) {
            return $keyword;
        }

        return new SemanticKnowledgeSearch($keyword, self::ranker());
    }

    private static function ranker(): ?SemanticRanker
    {
        $class = config('knowledge.semantic.ranker');
        if (! is_string($class) || $class === '' || ! class_exists($class) || ! is_subclass_of($class, SemanticRanker::class)) {
            return null;
        }

        return app($class);
    }
}
