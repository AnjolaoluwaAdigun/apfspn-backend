<?php

namespace Tests\Docs\Strategies;

class QueryParametersFromFormRequest extends ParametersFromFormRequest
{
    protected function appliesTo(string $httpMethod): bool
    {
        return in_array($httpMethod, ['GET', 'HEAD', 'DELETE'], true);
    }
}
