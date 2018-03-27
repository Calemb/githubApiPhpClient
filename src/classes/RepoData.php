<?php
namespace STD\GitHubStatistics;

class RepoData
{
    //num of last page in pull request counting (results pagination!)
    private $curlLastRequestPageNum = 0;

    //controll amount of results per page - prevent from default API value changes
    private $perPageRes = 30;

    //flag controll if there is more than one page of pull requests
    private $LinksFound = false;

    private $repoUser = "";
    private $repoName = "";

    //base api URL to construct other url calls
    private static $baseGitHubUrl = "https://api.github.com/repos/";

    public function __construct($user, $repoName)
    {
        $this->repoUser = $user;
        $this->repoName = $repoName;
    }

    /**
     * Create github api url with repo user & name as params
     *
     * @param string $user repository username
     * @param string $repoName repository name
     *
     * @return string
     */
    private function GetRepoUrl($user, $repoName)
    {
        return RepoData::$baseGitHubUrl . $user . "/" . $repoName;
    }

    /**
     * Create github api url about pullRequests in concrete state & with resitemsCount perPage
     * @param string $baseUrl base github api URL
     * @param int $itemsCount how many items per page should github api return
     * @param string $state (open|close) state of pull requests that should be looking for
     */
    private function GetRepoPullsUrl($baseUrl, $itemsCount, $state)
    {
        return $baseUrl . "/pulls?per_page=" . $itemsCount . "&state=" . $state;
    }

    /**
     * simple compare based on num values
     *
     * @param int $firstVal First value to compare with
     * @param int $secVal Second value to compare with
     * @param string $firstName  Name connected with $firstVal
     * @param string $secName  Name connected with $secVal
     *
     * @return string ('none'|$firstName|$secName) based on value compare
     */
    private static function SimpleCompare($firstVal, $secVal, $firstName, $secName)
    {
        $winner = 'none';

        if ($firstVal > $secVal) {
            $winner = $firstName;
        } else if ($secVal > $firstVal) {
            $winner = $secName;
        }

        return $winner;
    }

    /**
     * Calculate coopertion value of repositories
     *
     * @param array $firstRepoData First repository data
     * @param array $secRepoData Second repository data
     *
     * @return string name of repository that is compare
     */
    private static function GetCooperationWinner($firstRepoData, $secRepoData)
    {
        $cooperationWinner = RepoData::SimpleCompare(
            $firstRepoData['closed_pull_requests'],
            $secRepoData['closed_pull_requests'],
            $firstRepoData['name'],
            $secRepoData['name']
        );

        return $cooperationWinner;
    }

    /**
     * Calculate extendability value of repositories
     *
     * @param array $firstRepoData First repository data
     * @param array $secRepoData Second repository data
     *
     * @return string name of repository that is compare
     */
    private static function GetExtendabilityWinner($firstRepoData, $secRepoData)
    {
        $extendabilityWinner = RepoData::SimpleCompare(
            $firstRepoData['forks'],
            $secRepoData['forks'],
            $firstRepoData['name'],
            $secRepoData['name']
        );

        return $extendabilityWinner;
    }

    /**
     * Calculate popularity value of repositories
     *
     * @param array $firstRepoData First repository data
     * @param array $secRepoData Second repository data
     *
     * @return string name of repository that is compare
     */
    private static function GetPopularityWinner($firstRepoData, $secRepoData)
    {
        $popularityWinner = RepoData::SimpleCompare(
            (int) $firstRepoData['stars'] + (int) $firstRepoData['watchers'],
            (int) $secRepoData['stars'] + (int) $secRepoData['watchers'],
            $firstRepoData['name'],
            $secRepoData['name']
        );

        return $popularityWinner;
    }

    /**
     * Compare two repository data
     *
     * @param array $firstRepoData First repository data
     * @param array $secRepoData Second repository data
     *
     * @return array parameters with repository winner names
     */
    public static function Compare($firstRepoData, $secRepoData)
    {
        $cooperation = RepoData::GetCooperationWinner($firstRepoData, $secRepoData);

        $extendability = RepoData::GetExtendabilityWinner($firstRepoData, $secRepoData);

        $popularity = RepoData::GetPopularityWinner($firstRepoData, $secRepoData);

        return RepoData::MakeCompareDataArray($cooperation, $extendability, $popularity);
    }

    /**
     * Make github API request and collect data about repository
     *
     * @return array repo data array filled with parameters or empty with message
     */
    public function GetRepoData()
    {
        $baseUrl = $this->GetRepoUrl($this->repoUser, $this->repoName);

        $urlPullsClosed = $this->GetRepoPullsUrl($baseUrl, $this->perPageRes, "close");
        $urlPullsOpened = $this->GetRepoPullsUrl($baseUrl, $this->perPageRes, "open");

        $repoData = $this->MakeCurlRequest($baseUrl, [], []);

        $data = null;
        if ($repoData['http_code'] != 200) {
            //return empty data array if curl request fail
            $msg = 'Unknown issue...';
            if (isset($repoData['message'])) {
                $msg = $repoData['message']; //set message from github API - like too many requests, not found repo etc.
            }

            $data = $this->MakeEmptyRepoDataArray($msg);

        } else {
            if ($repoData['private'] != 'false') { //only public repos allowed
                $pullReqCloseNum = $this->GetNumOfPullRequests($urlPullsClosed);

                $pullReqOpenNum = $this->GetNumOfPullRequests($urlPullsOpened);

                $data = $this->MakeRepoDataArray(
                    $this->repoUser,
                    $this->repoName,
                    $repoData['forks'],
                    $repoData['stargazers_count'],
                    $repoData['subscribers_count'],
                    $repoData['updated_at'],
                    $pullReqOpenNum,
                    $pullReqCloseNum
                );
            } else {
                //searching only in public repos
                $data = $this->MakeEmptyRepoDataArray('Only public repo data are allowed');
            }
        }

        return $data;
    }

    /**
     * Parse each header from curl request (if proper flag was set)
     * Search for 'links' headers to collect pull request pagination
     *
     * @param resource $resURL curl handler
     * @param string $strHeader value of header
     */
    private function parseHeader($resURL, $strHeader)
    {
        $matches = array();
        preg_match('/page=[0-9]+>; rel="last"/', $strHeader, $matches); //get string with number of last page

        if (count($matches) > 0) { //if there is Link header with last page marker
            preg_match('/[0-9]+/', $matches[0], $result); //get exac number of last page

            $this->curlLastRequestPageNum = $result[0]; //collect number of the last page

            //set flag in case that links wont be last header to not override page num with '-1'
            $this->LinksFound = true;
        } else if ($this->LinksFound == false) {
            //there is no Links with last page, so just count pullRequest in next steps

            $this->curlLastRequestPageNum = -1;
        }

        return strlen($strHeader);
    }

    /**
     * Perform curl request
     *
     * @param string $url request specify url
     * @param array $headers use custom headers for request
     * @param array $customOptions flags for curl_setopt_array - will be merged and override existing ones
     *
     * @return json decoded json response
     */
    private function MakeCurlRequest($url, $headers, $customOptions = [])
    {
        $data = null;
        $ch = curl_init();

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0',
        );

        $opt = $customOptions + $options; //if keyCollision -> use custom ones

        curl_setopt_array($ch, $opt);
        $exec = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //get additional info about http response code
        $result = json_decode($exec, true);

        $result['http_code'] = $httpCode; //save github api http response code

        curl_close($ch);

        return $result;
    }

    /**
     * Get number of pull requests with specified oprions (included in url)
     *
     * @param string $pullUrl repositor url with options for pull requests
     *
     * @return int number of pull requests according to params in $pullUrl
     */
    private function GetNumOfPullRequests($pullUrl)
    {
        $this->LinksFound = false; //reset object flag before next search

        $pullReqNum = 0;

        //use custom function to parse incoming headers - searching for 'links' header
        $cInitPullReqPage = $this->MakeCurlRequest($pullUrl, [], [CURLOPT_HEADERFUNCTION => array($this, 'parseHeader')]);

        if ($this->curlLastRequestPageNum == -1) { //only one page with results - count it!
            $pullReqNum = count($cInitPullReqPage) - 1;//curl results contains additional http_response elem in array
            // var_dump($cInitPullReqPage);
        } else {
            $pullReqNum = ($this->curlLastRequestPageNum - 1) * $this->perPageRes; //total pull requests without last page

            $lastPageClosePullData = $this->MakeCurlRequest($pullUrl . "&page=" . $this->curlLastRequestPageNum, [], []); //get last page of pull requests

            $pullReqNum += count($lastPageClosePullData) - 1; //add pull requests amount from last page
        }

        return $pullReqNum;
    }

    /**
     * Create valid repo array, buth without data
     *
     * @param string $message to pass custom message - like errors etc.
     *
     * @return array repo data array
     */
    private function MakeEmptyRepoDataArray($message = '')
    {
        return $this->MakeRepoDataArray('', '', '', '', '', '', '', '', $message);
    }

    /**
     * Create repo data array filled with parameters
     * @param string $user repo username
     * @param string $repoName repo name
     * @param int $forks number of forks
     * @param int $stars number of stars marks
     * @param int $watchers number of watchers of this repo
     * @param string $lastUpdate date string of last update
     * @param int $pullRequests number of open pull requests
     * @param int $closePullRequests number of closed pull requests
     * @param string $message custom message - for errors etc.
     *
     * @return array repo data array
     */
    private function MakeRepoDataArray($user, $repoName, $forks, $stars, $watchers, $lastUpdate, $pullRequests, $closePullRequests, $message = '')
    {
        return array(
            'name' => $user . '/' . $repoName,
            'forks' => $forks,
            'stars' => $stars,
            'watchers' => $watchers,
            'last_update' => $lastUpdate,
            'pull_requests' => $pullRequests,
            'closed_pull_requests' => $closePullRequests,
            'message' => $message,
        );
    }

    /**
     * Create Compare data array with repos names for each category
     *
     * @param string $cooperation winner repo name of cooperation with community category
     * @param string $extendability winner repo name of extendability category
     * @param string $popularity winner repo name of popularity cateogry
     *
     * @return array compare data array
     */
    private static function MakeCompareDataArray($cooperation, $extendability, $popularity)
    {
        return array(
            'community_cooperation' => $cooperation,
            'extendability' => $extendability,
            'popularity' => $popularity,
        );
    }
}
