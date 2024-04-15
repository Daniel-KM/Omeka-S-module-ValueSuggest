<?php
namespace ValueSuggest\Suggester\Geonames;

use Doctrine\DBAL\Connection;
use Laminas\Http\Client;
use ValueSuggest\Suggester\SuggesterInterface;

class GeonamesSuggest implements SuggesterInterface
{
    /**
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function __construct(Client $client, Connection $connection)
    {
        $this->client = $client;
        $this->connection = $connection;
    }

    /**
     * Retrieve suggestions from the Geonames web services API.
     *
     * @see http://www.geonames.org/export/geonames-search.html
     * @param string $query
     * @param string $lang
     * @return array
     */
    public function getSuggestions($query, $lang = null)
    {
        $params = ['q' => $query, 'maxRows' => 1000, 'username' => 'kdlinfo'];
        if ($lang) {
            // Geonames requires an ISO-639 2-letter language code. Remove the
            // first underscore and anything after it ("zh_CN" becomes "zh").
            $params['lang'] = strstr($lang, '_', true) ?: $lang;
        }
        $response = $this->client
        ->setUri('http://api.geonames.org/searchJSON')
        ->setParameterGet($params)
        ->send();
        if (!$response->isSuccess()) {
            return [];
        }

        // Parse the JSON response.
        // Count the uris, checking the result key.
        $results = json_decode($response->getBody(), true);
        $uris = [];
        $values = [];
        $infos = [];
        foreach ($results['geonames'] as $result) {
            $info = [];
            if (isset($result['fcodeName']) && $result['fcodeName']) {
                $info[] = sprintf('Feature: %s', $result['fcodeName']);
            }
            if (isset($result['countryName']) && $result['countryName']) {
                $info[] = sprintf('Country: %s', $result['countryName']);
            }
            if (isset($result['adminName1']) && $result['adminName1']) {
                $info[] = sprintf('Admin name: %s', $result['adminName1']);
            }
            if (isset($result['population']) && $result['population']) {
                $info[] = sprintf('Population: %s', number_format($result['population']));
            }
            $uri = sprintf('http://www.geonames.org/%s', $result['geonameId']);
            $uris[$uri] = 0;
            $values[$uri] = $result['name'];
            $infos[$uri] = implode("\n", $info);
        }

        $sql = <<<'SQL'
SELECT `value`.`uri`, COUNT(`value`.`uri`)
FROM `value`
WHERE `value`.`uri` IN (:uris)
GROUP BY `value`.`uri`
ORDER BY COUNT(`value`.`uri`) DESC
;
SQL;
        $totals = $this->connection->executeQuery($sql, ['uris' => array_keys($uris)], ['uris' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY])->fetchAllKeyValue();

        // Keep sort by most used uris first.
        $uris = array_map('intval', $totals) + $uris;

        $suggestions = [];
        foreach ($uris as $uri => $count) {
            $suggestions[] = [
                'value' => sprintf('%s (%s)', $values[$uri], $count),
                'data' => [
                    'uri' => $uri,
                    'info' => $infos[$uri],
                ],
            ];
        }

        return $suggestions;
    }
}
