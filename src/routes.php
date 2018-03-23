<?php

require __DIR__ . '/../src/classes/RepoData.php';
// Routes

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
