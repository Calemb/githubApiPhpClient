App is using Slim php framework which is based on composer.

To get composer: https://getcomposer.org/download/


## Install the Application

After clone repository just use:
```
composer install
```

## USING REST
To get the basics of two repositories together with their comparison use pattern:
```
REST_URL/api/user1/repoName1/user2/repoName2
```

Remember that REST_URL must provide path to [a relative link](public/index.php) file!

Github API without enterprise version is limited to 30 requests per hour.
Be sure to check message delivered with each repo data response to validate income data.