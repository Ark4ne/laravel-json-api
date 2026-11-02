<?php

declare(strict_types=1);

namespace Test\Feature\User;

use Test\Feature\FeatureTestCase;

class CollectionTest extends FeatureTestCase
{
    public function test_the_index_renders_the_users(): void
    {
        $this->assertDocument('index', $this->get('user'));
    }

}
