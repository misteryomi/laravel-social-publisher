<?php

namespace Misteryomi\SocialPublisher\Contracts;

use Misteryomi\SocialPublisher\Content\SocialPost;

/**
 * Contract for app-level content generators.
 *
 * Implement this in your app using whatever LLM library you prefer.
 * The package provides SocialPostSchema::standard() to get the JSON schema
 * for the structured output call, and SocialPost::fromArray() to turn the
 * raw response into a typed value object.
 *
 * Example (using a hypothetical $llm service):
 *
 *   class JobSpotlightGenerator implements SocialCopyGeneratorContract
 *   {
 *       public function generate(array $context): SocialPost
 *       {
 *           $raw = $this->llm->structured($this->prompt($context), SocialPostSchema::standard());
 *           return SocialPost::fromArray($raw);
 *       }
 *   }
 */
interface SocialCopyGeneratorContract
{
    /**
     * Generate a SocialPost from the given context.
     *
     * What $context contains is entirely up to the app — it might be a list
     * of jobs, a policy update string, a topic slug, or anything else.
     *
     * @param  array<string, mixed>  $context
     */
    public function generate(array $context): SocialPost;
}
