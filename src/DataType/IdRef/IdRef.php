<?php
namespace ValueSuggest\DataType\IdRef;

use ValueSuggest\DataType\AbstractDataType;
use ValueSuggest\Suggester\IdRef\IdRefSuggestAll;

class IdRef extends AbstractDataType
{
    protected $dataType;
    protected $dataTypeLabel;
    protected $idrefUrl;

    public function setDataType($dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function setDataTypeLabel($dataTypeLabel)
    {
        $this->dataTypeLabel = $dataTypeLabel;
        return $this;
    }

    public function setIdrefUrl($idrefUrl)
    {
        $this->idrefUrl = $idrefUrl;
        return $this;
    }

    public function getSuggester()
    {
        return new IdRefSuggestAll(
            $this->services->get('Omeka\HttpClient'),
            $this->services->get('Omeka\Connection'),
            $this->dataType,
            $this->idrefUrl
        );
    }

    public function getName()
    {
        return $this->dataType;
    }

    public function getLabel()
    {
        return $this->dataTypeLabel;
    }
}
