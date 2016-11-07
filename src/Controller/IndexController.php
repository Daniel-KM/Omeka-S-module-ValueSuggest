<?php
namespace ValueSuggest\Controller;

use Omeka\DataType\Manager as DataTypeManager;
use ValueSuggest\DataType\DataTypeInterface;
use ValueSuggest\Suggester\SuggesterInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Model\JsonModel;

class IndexController extends AbstractActionController
{
    protected $dataTypes;

    public function __construct(DataTypeManager $dataTypes)
    {
        $this->dataTypes = $dataTypes;
    }

    /**
     * Generic proxy action for suggest requests.
     *
     * Responsible for accepting an AJAX request, retrieving suggestions from
     * the data type, and formatting the response according to specs.
     */
    public function proxyAction()
    {
        $type = $this->params()->fromQuery('type');
        $query = $this->params()->fromQuery('query');
        $request = $this->getRequest();
        $response = $this->getResponse();

        if (!$request->isXmlHttpRequest()){
            $errorMessage = sprintf('The request must be a XMLHttpRequest.', $type);
            return $response->setStatusCode('415')->setContent($errorMessage);
        }
        if ('' === trim($type)) {
            $errorMessage = sprintf('The request must include a data type.', $type);
            return $response->setStatusCode('400')->setContent($errorMessage);
        }

        try {
            $dataType = $this->dataTypes->get($type);
        } catch (ServiceNotFoundException $e) {
            $errorMessage = sprintf('The "%s" data type not found.', $type);
            return $response->setStatusCode('400')->setContent($errorMessage);
        }
        if (!$dataType instanceof DataTypeInterface) {
            $errorMessage = sprintf('The "%s" data type does not implement ValueSuggest\DataType\DataTypeInterface.', $type);
            return $response->setStatusCode('500')->setContent($errorMessage);
        }

        $suggester = $dataType->getSuggester();
        if (!$suggester instanceof SuggesterInterface) {
            $errorMessage = sprintf('The "%s" suggester does not implement ValueSuggest\Suggester\SuggesterInterface.', $type);
            return $response->setStatusCode('500')->setContent($errorMessage);
        }

        $suggestions = $suggester->getSuggestions($query);
        if (!is_array($suggestions)) {
            $errorMessage = sprintf('The "%s" data type must return an array; %s given.', $type, gettype($suggestions));
            return $response->setStatusCode('500')->setContent($errorMessage);
        }

        // Set the response format defined by Ajax Autocomplete.
        // @see https://github.com/devbridge/jQuery-Autocomplete#response-format
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        return $response->setContent(json_encode(['suggestions' => $suggestions]));
    }
}
