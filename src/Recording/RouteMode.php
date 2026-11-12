<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Recording;

enum RouteMode: string
{
    case Always = 'always';
    case Off = 'off';
    case Metadata = 'metadata';
}
