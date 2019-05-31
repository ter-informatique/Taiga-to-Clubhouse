<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$authToken = generate_taiga_auth_token($_ENV['TAIGA_API'], [
    'username' => $_ENV['TAIGA_USERNAME'],
    'password' => $_ENV['TAIGA_PASSWORD'],
    'type'    => 'normal'
]);

$taigaClient = new GuzzleHttp\Client([
    'base_uri' => $_ENV['TAIGA_API'],
    'headers' => [
        'authorization' => sprintf('Bearer %s', $authToken),
        'language' => 'fr',
        'x-disable-pagination' => true
    ]
]);

$clubhouseClient = new GuzzleHttp\Client([
    'base_uri' => $_ENV['CLUBHOUSE_API'],
]);

$stories = json_decode($taigaClient->get('userstories', [
    'query' => [
        'project' => $_ENV['TAIGA_PROJECT_ID_TO_IMPORT'],
    ]
])->getBody(), true);


$epicsMap = [];

foreach ($stories as $storyData) {
    $storyDetail = json_decode($taigaClient->get(sprintf('userstories/%d', $storyData['id']))->getBody(), true);

    $subject = $storyDetail['subject'];
    $description = $storyDetail['description'];
    $epicId = null;

    if ($storyDetail['epics'] != null) {

        $firstEpic = end($storyDetail['epics']);

        if (!array_key_exists($firstEpic['id'], $epicsMap)) {
            $epicDetail = json_decode($taigaClient->get(sprintf('epics/%d', $firstEpic['id']))->getBody(), true);

            $clubhouseEpic = json_decode($clubhouseClient->post('epics', [
                'query' => [
                    'token' => $_ENV['CLUBHOUSE_API_TOKEN']
                ],
                'json' => [
                    'name' => $epicDetail['subject'],
                    'description' => $epicDetail['description']
                ]
            ])->getBody(), true);

            $epicsMap[$firstEpic['id']] = $clubhouseEpic['id'];
        }

        $epicId = $epicsMap[$firstEpic['id']];
    }

    $clubhouseEpic = json_decode($clubhouseClient->post('stories', [
        'query' => [
            'token' => $_ENV['CLUBHOUSE_API_TOKEN']
        ],
        'json' => [
            'name' => $subject,
            'project_id' => $_ENV['CLUBHOUSE_PROJECT_ID'],
            'story_type' => 'feature',
            'description' => $description,
            'epic_id' => $epicId,
        ]
    ])->getBody(), true);
}