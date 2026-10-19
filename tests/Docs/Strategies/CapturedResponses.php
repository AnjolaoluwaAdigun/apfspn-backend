<?php

namespace Tests\Docs\Strategies;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Symfony\Component\HttpFoundation\Response;
use Tests\Docs\ResponseRecorder;

class CapturedResponses extends Strategy
{
    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $file = ResponseRecorder::DIRECTORY.'/'.$endpointData->route->getName().'.json';

        if (! is_file($file)) {
            return [];
        }

        $responses = [];

        foreach (json_decode((string) file_get_contents($file), true) as $kind => $example) {
            $csv = $example['body'] === null;

            $responses[] = [
                'status' => $example['status'],
                'description' => ($kind === 'success' ? 'Success' : 'Failure').' ('.(Response::$statusTexts[$example['status']] ?? $example['status']).')'.($csv ? ': CSV file download' : ''),
                'content' => $csv ? 'CSV file (see the column list in the description).' : json_encode($example['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'headers' => $csv ? ['Content-Type' => $example['content_type']] : [],
            ];
        }

        return $responses;
    }
}
