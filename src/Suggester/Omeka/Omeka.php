<?php
namespace ValueSuggest\Suggester\Omeka;

use ValueSuggest\Suggester\SuggesterWithContextInterface;

class Omeka implements SuggesterWithContextInterface
{
    protected $services;

    public function __construct($services, $name)
    {
        $this->services = $services;
        $this->name = $name;
    }

    public function getSuggestions($query, $lang = null, array $context = [])
    {
        $propertyId = (int) ($context['property_id'] ?? 0);
        $resourceTemplateId = (int) ($context['resource_template_id'] ?? 0);
        $resourceClassId = (int) ($context['resource_class_id'] ?? 0);

        $em = $this->services->get('Omeka\EntityManager');
        $qb = $em->createQueryBuilder()
            ->select('v.value value, v.uri uri, COUNT(v.value) has_count')
            ->from('Omeka\Entity\Value', 'v')
            ->andWhere('v.property = :propertyId')
            ->andWhere('LOCATE(:query, v.value) > 0')
            ->groupBy('value', 'uri')
            ->orderBy('has_count', 'DESC')
            ->addOrderBy('value', 'ASC')
            ->setParameter('propertyId', $propertyId)
            ->setParameter('query', $query);

        switch ($this->name) {
            case 'valuesuggest:omeka:propertyResourceTemplate':
                $qb->join('v.resource', 'r')
                    ->andWhere('r.resourceTemplate = :resourceTemplateId')
                    ->setParameter('resourceTemplateId', $resourceTemplateId);
                break;
            case 'valuesuggest:omeka:propertyResourceClass':
                $qb->join('v.resource', 'r')
                    ->andWhere('r.resourceClass = :resourceClassId')
                    ->setParameter('resourceClassId', $resourceClassId);
                break;
            case 'valuesuggest:omeka:property':
            default:
                // Do nothing
                break;
        }

        $suggestions = [];
        foreach ($qb->getQuery()->getResult() as $result) {
            $suggestions[] = [
                'value' => $result['value'],
                'data' => [
                    'uri' => $result['uri'],
                    'info' => $result['uri'] ? sprintf('%s %s', $result['value'], $result['uri']) : null,
                ],
            ];
        }
        return $suggestions;
    }
}
