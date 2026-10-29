<?php

declare(strict_types=1);

namespace Test\app\Enums;

enum State: int
{
    case Draft = 0;
    case Published = 1;
}
