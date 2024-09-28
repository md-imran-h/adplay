<?php

require 'vendor/autoload.php';

use Valitron\Validator;

$bidRequestFile = 'bid_request.json';
$campaignFile = 'campaigns.json';

if (!file_exists($bidRequestFile)) {
    die("Bid Request file not found.");
}

$bidRequestJson = file_get_contents($bidRequestFile);
$bidRequestData = json_decode($bidRequestJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON format in bid request file.");
}

if (!file_exists($campaignFile)) {
    die("Campaign file not found.");
}

$campaignJson = file_get_contents($campaignFile);
$campaignData = json_decode($campaignJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON format in campaign file.");
}

// Validate Bid Request
$v = new Validator($bidRequestData);
$v->rule('required', ['id', 'imp', 'device']);
$v->rule('required', ['geo', 'country'], 'device');
$v->rule('required', 'imp.0.bidfloor');

// Validate Campaigns
foreach ($campaignData as $campaign) {
    $v = new Validator($campaign);
    $v->rule('required', ['campaignname', 'price', 'country', 'hs_os']);
    $v->rule('numeric', 'price');

    if (!$v->validate()) {
        die("Campaign validation failed for {$campaign['campaignname']}: " . implode(', ', $v->errors()));
    }
}

$bidRequest = $bidRequestData; 
$campaigns = $campaignData; 

$bidFloor = $bidRequest['imp'][0]['bidfloor'] ?? 0;
$deviceOs = strtolower($bidRequest['device']['os'] ?? '');
$country = $bidRequest['device']['geo']['country'] ?? '';

$selectedCampaign = null;

foreach ($campaigns as $campaign) {
    if (strpos(strtolower($campaign['country']), strtolower($country)) !== false && 
        strpos(strtolower($campaign['hs_os']), strtolower($deviceOs)) !== false) {
        if ($campaign['price'] >= $bidFloor) {
            if ($selectedCampaign === null || $campaign['price'] > $selectedCampaign['price']) {
                $selectedCampaign = $campaign;
            }
        }
    }
}

if ($selectedCampaign) {
    $response = [
        'id' => $bidRequest['id'],
        'seatbid' => [
            [
                'bid' => [
                    [
                        'id' => uniqid(),
                        'impid' => $bidRequest['imp'][0]['id'],
                        'price' => $selectedCampaign['price'],
                        'adm' => '<img src="' . $selectedCampaign['image_url'] . '" />',
                        'adomain' => [$selectedCampaign['tld']],
                        'crid' => $selectedCampaign['creative_id'],
                        'w' => explode('x', $selectedCampaign['dimension'])[0],
                        'h' => explode('x', $selectedCampaign['dimension'])[1],
                        'dealid' => $selectedCampaign['code'],
                        'cat' => ['IAB1'],
                        'attr' => [1]
                    ]
                ]
            ]
        ]
    ];
} else {
    $response = [
        'id' => $bidRequest['id'],
        'nbr' => 2,
        'message' => 'No campaign selected due to insufficient bid floor or matching criteria.'
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
