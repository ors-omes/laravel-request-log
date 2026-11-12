<?php

declare(strict_types=1);

namespace OrsOmes\RequestLog\Recording;

enum Reason: string
{
    case Error = 'error';
    case Mutation = 'mutation';
    case Slow = 'slow';
    case Sampled = 'sampled';
    case Forced = 'forced';
}
