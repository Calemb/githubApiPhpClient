<?php

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}
//
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/classes/RepoData.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';

$app = new \Slim\App($settings);
function SanitizeInput($input)
{
    $sanitizedInput = trim(strip_tags($input));
    return $sanitizedInput;
}

//route to proper app path
$app->get('/api/{user1}/{repo1}/{user2}/{repo2}', function ($request, $response, $args) {
    
    //validate input data!
    $user1 = SanitizeInput($args['user1']);
    $repo1 = SanitizeInput($args['repo1']);

    $user2 = SanitizeInput($args['user2']);
    $repo2 = SanitizeInput($args['repo2']);

    //gather data about repos
    $r1 = new \STD\GitHubStatistics\RepoData($user1, $repo1);
    $firstRepo = $r1->GetRepoData();

    //to prevent API request again for the same (limited API calls per hour without enterprise!)
    if ($user1 === $user2 && $repo1 === $repo2) {
        $secRepo = $firstRepo;
    } else {
        $r2 = new \STD\GitHubStatistics\RepoData($user2, $repo2);
        $secRepo = $r2->GetRepoData();
    }


    $comparision = \STD\GitHubStatistics\RepoData::Compare($firstRepo, $secRepo);

    //create response data array
    $data = array('0' => $firstRepo, '1' => $secRepo, '2' => $comparision);

    //response with json
    $newResponse = $response->withJson($data);

    return $newResponse;
});

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
