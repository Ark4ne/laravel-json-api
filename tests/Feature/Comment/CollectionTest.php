<?php

declare(strict_types=1);

namespace Test\Feature\Comment;

use Test\Feature\FeatureTestCase;

class CollectionTest extends FeatureTestCase
{
    public function test_the_index_renders_the_first_page_of_comments(): void
    {
        $response = $this->get("comment");

        $this->assertDocument('index', $response);
    }

    public function test_the_index_includes_the_post_of_each_comment(): void
    {
        $response = $this->get("comment?include=post");

        $this->assertDocument('index-include-post', $response);
    }

}
