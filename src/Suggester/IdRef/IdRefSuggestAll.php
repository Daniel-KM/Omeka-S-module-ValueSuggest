<?php

namespace ValueSuggest\Suggester\IdRef;

use Doctrine\DBAL\Connection;
use Laminas\Http\Client;
use ValueSuggest\Suggester\SuggesterInterface;

class IdRefSuggestAll implements SuggesterInterface
{
    /**
     * @var \Laminas\Http\Client
     */
    protected $client;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $url;

    public function __construct(
        Client $client,
        Connection $connection,
        string $url
    ) {
        $this->client = $client;
        $this->connection = $connection;
        $this->url = $url;
    }

    /**
     * Retrieve suggestions from the IdRef web services API (based on Solr).
     *
     * @see https://www.idref.fr
     * @param string $query
     * @param string $lang
     * @return array
     */
    public function getSuggestions($query, $lang = null)
    {
        // Convert the query into a Solr query.
        $query = trim($query);
        if (strpos($query, ' ')) {
            $query = '(' . implode('%20AND%20', array_map('urlencode', explode(' ', $query))) . ')';
        } else {
            $query = urlencode($query);
        }
        $url = $this->url . $query;

        $response = $this->client->setUri($url)->send();
        if (!$response->isSuccess()) {
            return [];
        }

        // Parse the JSON response.
        $results = json_decode($response->getBody(), true);

        if (empty($results['response']['docs'])) {
            return [];
        }

        // Count the uris, checking the result key.
        $uris = [];
        $values = [];
        foreach ($results['response']['docs'] as $result) {
            if (empty($result['ppn_z'])) {
                continue;
            }
            // "affcourt" may be not present in some results (empty words).
            if (isset($result['affcourt_r'])) {
                $value = is_array($result['affcourt_r']) ? reset($result['affcourt_r']) : $result['affcourt_r'];
            } elseif (isset($result['affcourt_z'])) {
                $value = is_array($result['affcourt_z']) ? reset($result['affcourt_z']) : $result['affcourt_z'];
            } else {
                $value = $result['ppn_z'];
            }
            $uri = 'https://www.idref.fr/' . $result['ppn_z'];
            $uris[$uri] = 0;
            $values[$uri] = $value;
        }

        if (!$uris) {
            return [];
        }

        $sql = <<<'SQL'
SELECT `value`.`uri`, COUNT(`value`.`uri`)
FROM `value`
WHERE `value`.`uri` IN (:uris)
GROUP BY `value`.`uri`
;
SQL;
        $totals = $this->connection->executeQuery($sql, ['uris' => array_keys($uris)], ['uris' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY])->fetchAllKeyValue();

        $uris = array_replace($uris, array_map('intval', $totals));

        $suggestions = [];
        foreach ($uris as $uri => $count) {
            $suggestions[] = [
                'value' => sprintf('%s (%s)', $values[$uri], $count),
                'data' => [
                    'uri' => $uri,
                    'info' => null,
                ],
            ];
        }

        return $suggestions;
    }
}
